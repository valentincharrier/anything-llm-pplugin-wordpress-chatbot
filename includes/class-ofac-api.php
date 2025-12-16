<?php
/**
 * API communication class
 *
 * @package OcadeFusion_AnythingLLM_Chatbot
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class OFAC_API
 * Handles communication with AnythingLLM API
 */
class OFAC_API {

    /**
     * Single instance
     *
     * @var OFAC_API|null
     */
    private static $instance = null;

    /**
     * API URL
     *
     * @var string
     */
    private $api_url;

    /**
     * API Key
     *
     * @var string
     */
    private $api_key;

    /**
     * Workspace slug
     *
     * @var string
     */
    private $workspace_slug;

    /**
     * Timeout
     *
     * @var int
     */
    private $timeout;

    /**
     * Get single instance
     *
     * @return OFAC_API
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        $settings = OFAC_Settings::get_instance();

        $this->api_url        = $settings->get( 'ofac_api_url' );
        $this->api_key        = $settings->get( 'ofac_api_key' );
        $this->workspace_slug = $settings->get( 'ofac_workspace_slug' );
        $this->timeout        = $settings->get( 'ofac_timeout', 60 );

        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action( 'wp_ajax_ofac_chat', array( $this, 'handle_chat_request' ) );
        add_action( 'wp_ajax_nopriv_ofac_chat', array( $this, 'handle_chat_request' ) );
        add_action( 'wp_ajax_ofac_chat_stream', array( $this, 'handle_stream_request' ) );
        add_action( 'wp_ajax_nopriv_ofac_chat_stream', array( $this, 'handle_stream_request' ) );
        add_action( 'wp_ajax_ofac_test_connection', array( $this, 'handle_test_connection' ) );
        add_action( 'wp_ajax_ofac_upload_file', array( $this, 'handle_file_upload' ) );
        add_action( 'wp_ajax_nopriv_ofac_upload_file', array( $this, 'handle_file_upload' ) );
    }

    /**
     * Check if API is configured
     *
     * @return bool
     */
    public function is_configured() {
        return ! empty( $this->api_url ) && ! empty( $this->api_key ) && ! empty( $this->workspace_slug );
    }

    /**
     * Get headers for API requests
     *
     * @return array
     */
    private function get_headers() {
        return array(
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        );
    }

    /**
     * Make API request
     *
     * @param string $endpoint Endpoint
     * @param string $method HTTP method
     * @param array  $body Request body
     * @return array|WP_Error
     */
    public function request( $endpoint, $method = 'GET', $body = array() ) {
        if ( ! $this->is_configured() ) {
            return new WP_Error( 'not_configured', __( 'API non configurée', 'anythingllm-chatbot' ) );
        }

        $url = trailingslashit( $this->api_url ) . ltrim( $endpoint, '/' );

        $args = array(
            'method'    => $method,
            'headers'   => $this->get_headers(),
            'timeout'   => $this->timeout,
            'sslverify' => true,
        );

        if ( ! empty( $body ) && in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) ) {
            $args['body'] = wp_json_encode( $body );
        }

        /**
         * Filter API request arguments
         *
         * @since 1.0.0
         * @param array  $args     Request arguments
         * @param string $endpoint Endpoint
         * @param string $method   HTTP method
         */
        $args = apply_filters( 'ofac_api_request_args', $args, $endpoint, $method );

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body        = wp_remote_retrieve_body( $response );
        $data        = json_decode( $body, true );

        if ( $status_code >= 400 ) {
            $message = isset( $data['message'] ) ? $data['message'] : __( 'Erreur API', 'anythingllm-chatbot' );
            return new WP_Error( 'api_error', $message, array( 'status' => $status_code ) );
        }

        return $data;
    }

    /**
     * Send chat message
     *
     * @param string $message User message
     * @param string $session_id Session ID
     * @param string $mode Chat mode (query or chat)
     * @return array|WP_Error
     */
    public function chat( $message, $session_id = '', $mode = 'chat', $image_data = null ) {
        $endpoint = sprintf( 'api/v1/workspace/%s/chat', $this->workspace_slug );

        $body = array(
            'message'    => $message,
            'mode'       => $mode,
            'sessionId'  => $session_id,
        );

        // Add image attachment if present
        if ( ! empty( $image_data ) && ! empty( $image_data['data'] ) ) {
            $body['attachments'] = array(
                array(
                    'name'          => $image_data['name'],
                    'mime'          => $image_data['mime'],
                    'contentString' => $image_data['data'], // data:image/png;base64,...
                ),
            );
        }

        /**
         * Filter chat request body
         *
         * @since 1.0.0
         * @param array  $body       Request body
         * @param string $message    User message
         * @param string $session_id Session ID
         */
        $body = apply_filters( 'ofac_chat_request_body', $body, $message, $session_id );

        return $this->request( $endpoint, 'POST', $body );
    }

    /**
     * Handle AJAX chat request
     */
    public function handle_chat_request() {
        // Verify nonce
        if ( ! check_ajax_referer( 'ofac_chat_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Nonce invalide', 'anythingllm-chatbot' ) ), 403 );
        }

        // Check rate limit
        $rate_limiter = new OFAC_Rate_Limiter();
        if ( ! $rate_limiter->check() ) {
            wp_send_json_error( array( 'message' => __( 'Trop de requêtes, veuillez patienter', 'anythingllm-chatbot' ) ), 429 );
        }

        // Check honeypot
        if ( ! empty( $_POST['ofac_hp'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Requête invalide', 'anythingllm-chatbot' ) ), 400 );
        }

        // Get and sanitize message
        $message    = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
        $session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';

        // Get image data if present
        $image_data = null;
        if ( ! empty( $_POST['image_data'] ) ) {
            $image_data = array(
                'data' => wp_unslash( $_POST['image_data'] ), // base64 data URL
                'name' => isset( $_POST['image_name'] ) ? sanitize_file_name( wp_unslash( $_POST['image_name'] ) ) : 'image.png',
                'mime' => isset( $_POST['image_mime'] ) ? sanitize_text_field( wp_unslash( $_POST['image_mime'] ) ) : 'image/png',
            );
        }

        // Allow empty message if image is present
        if ( empty( $message ) && empty( $image_data ) ) {
            wp_send_json_error( array( 'message' => __( 'Message vide', 'anythingllm-chatbot' ) ), 400 );
        }

        // Default message for image without text
        if ( empty( $message ) && ! empty( $image_data ) ) {
            $message = __( 'Que vois-tu sur cette image ?', 'anythingllm-chatbot' );
        }

        // Check message length
        $max_chars = OFAC_Settings::get_instance()->get( 'ofac_max_chars', 5000 );
        if ( mb_strlen( $message ) > $max_chars ) {
            wp_send_json_error( array( 'message' => sprintf( __( 'Message trop long (max %d caractères)', 'anythingllm-chatbot' ), $max_chars ) ), 400 );
        }

        // Don't cache requests with images
        if ( empty( $image_data ) ) {
            $cache = new OFAC_Cache();
            $cached_response = $cache->get( $message );
            if ( $cached_response !== false ) {
                wp_send_json_success( $cached_response );
            }
        }

        // Send request to API
        $start_time = microtime( true );
        $response   = $this->chat( $message, $session_id, 'chat', $image_data );
        $end_time   = microtime( true );

        if ( is_wp_error( $response ) ) {
            // Log error
            $this->log_error( $response );

            // Update stats
            OFAC_Stats::increment_errors();

            $fallback = OFAC_Settings::get_instance()->get( 'ofac_fallback_message' );
            wp_send_json_error( array( 'message' => $fallback ), 500 );
        }

        // Format response
        $formatted = $this->format_response( $response );

        // Cache response (only if no image)
        if ( empty( $image_data ) ) {
            $cache = new OFAC_Cache();
            $cache->set( $message, $formatted );
        }

        // Log conversation
        $this->log_conversation( $message, $formatted, $session_id );

        // Update stats
        OFAC_Stats::increment_messages();
        OFAC_Stats::update_response_time( $end_time - $start_time );

        wp_send_json_success( $formatted );
    }

    /**
     * Handle streaming chat request
     */
    public function handle_stream_request() {
        // Verify nonce (from GET for SSE)
        $nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'ofac_chat_nonce' ) ) {
            $this->send_sse_error( __( 'Nonce invalide', 'anythingllm-chatbot' ) );
            return;
        }

        // Check rate limit
        $rate_limiter = new OFAC_Rate_Limiter();
        if ( ! $rate_limiter->check() ) {
            $this->send_sse_error( __( 'Trop de requêtes', 'anythingllm-chatbot' ) );
            return;
        }

        // Get message from GET (SSE uses GET)
        $message    = isset( $_GET['message'] ) ? sanitize_textarea_field( wp_unslash( $_GET['message'] ) ) : '';
        $session_id = isset( $_GET['session_id'] ) ? sanitize_text_field( wp_unslash( $_GET['session_id'] ) ) : '';

        if ( empty( $message ) ) {
            $this->send_sse_error( __( 'Message vide', 'anythingllm-chatbot' ) );
            return;
        }

        // Set headers for SSE
        header( 'Content-Type: text/event-stream' );
        header( 'Cache-Control: no-cache' );
        header( 'Connection: keep-alive' );
        header( 'X-Accel-Buffering: no' );

        // Disable output buffering
        while ( ob_get_level() ) {
            ob_end_flush();
        }

        // Make streaming request
        $url = trailingslashit( $this->api_url ) . sprintf( 'api/v1/workspace/%s/stream-chat', $this->workspace_slug );

        $body = array(
            'message'   => $message,
            'mode'      => 'chat',
            'sessionId' => $session_id,
        );

        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, wp_json_encode( $body ) );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $this->api_key,
            'Content-Type: application/json',
            'Accept: text/event-stream',
        ) );
        curl_setopt( $ch, CURLOPT_TIMEOUT, $this->timeout );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, false );
        curl_setopt( $ch, CURLOPT_WRITEFUNCTION, function( $ch, $data ) {
            echo $data;
            flush();
            return strlen( $data );
        } );

        $result = curl_exec( $ch );

        if ( curl_errno( $ch ) ) {
            $this->send_sse_error( curl_error( $ch ) );
        }

        curl_close( $ch );

        // Send done event
        echo "event: done\n";
        echo "data: {}\n\n";
        flush();

        exit;
    }

    /**
     * Send SSE error
     *
     * @param string $message Error message
     */
    private function send_sse_error( $message ) {
        header( 'Content-Type: text/event-stream' );
        echo "event: error\n";
        echo "data: " . wp_json_encode( array( 'message' => $message ) ) . "\n\n";
        flush();
        exit;
    }

    /**
     * Format API response
     *
     * @param array $response API response
     * @return array
     */
    private function format_response( $response ) {
        $text = '';
        $sources = array();
        $suggestions = array();

        // Support both formats: 'textResponse' (AnythingLLM direct) and 'text' (some versions)
        if ( isset( $response['textResponse'] ) ) {
            $text = $response['textResponse'];
        } elseif ( isset( $response['text'] ) ) {
            $text = $response['text'];
        } elseif ( isset( $response['data']['text'] ) ) {
            // Handle wrapped response
            $text = $response['data']['text'];
            if ( isset( $response['data']['sources'] ) ) {
                $response['sources'] = $response['data']['sources'];
            }
        }

        if ( isset( $response['sources'] ) && is_array( $response['sources'] ) ) {
            $sources = array_map( function( $source ) {
                return array(
                    'title' => isset( $source['title'] ) ? $source['title'] : '',
                    'url'   => isset( $source['url'] ) ? $source['url'] : '',
                );
            }, $response['sources'] );
        }

        if ( isset( $response['suggestedQuestions'] ) && is_array( $response['suggestedQuestions'] ) ) {
            $suggestions = $response['suggestedQuestions'];
        } elseif ( isset( $response['suggestions'] ) && is_array( $response['suggestions'] ) ) {
            $suggestions = $response['suggestions'];
        }

        /**
         * Filter formatted response
         *
         * @since 1.0.0
         * @param array $formatted Formatted response
         * @param array $response  Raw API response
         */
        return apply_filters( 'ofac_format_response', array(
            'text'        => $text,
            'sources'     => $sources,
            'suggestions' => $suggestions,
        ), $response );
    }

    /**
     * Log conversation
     *
     * @param string $message User message
     * @param array  $response Bot response
     * @param string $session_id Session ID
     */
    private function log_conversation( $message, $response, $session_id ) {
        if ( ! OFAC_Settings::get_instance()->get( 'ofac_enable_logs', true ) ) {
            return;
        }

        $logs = new OFAC_Logs();
        $logs->log_message( $session_id, 'user', $message );
        $logs->log_message( $session_id, 'assistant', $response['text'] );
    }

    /**
     * Log error
     *
     * @param WP_Error $error Error object
     */
    private function log_error( $error ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( sprintf(
                '[OFAC API Error] %s: %s',
                $error->get_error_code(),
                $error->get_error_message()
            ) );
        }
    }

    /**
     * Test API connection
     *
     * @return array|WP_Error
     */
    public function test_connection() {
        // Use saved settings or POST values if provided
        $api_url = ! empty( $_POST['api_url'] ) ? esc_url_raw( wp_unslash( $_POST['api_url'] ) ) : $this->api_url;
        $api_key = ! empty( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : $this->api_key;
        $workspace_slug = ! empty( $_POST['workspace_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['workspace_slug'] ) ) : $this->workspace_slug;

        // Validate required settings
        if ( empty( $api_url ) ) {
            return new WP_Error( 'missing_url', __( 'URL de l\'API non configurée', 'anythingllm-chatbot' ) );
        }

        if ( empty( $api_key ) ) {
            return new WP_Error( 'missing_key', __( 'Clé API non configurée', 'anythingllm-chatbot' ) );
        }

        if ( empty( $workspace_slug ) ) {
            return new WP_Error( 'missing_workspace', __( 'Slug du workspace non configuré', 'anythingllm-chatbot' ) );
        }

        // Build request URL
        $url = trailingslashit( $api_url ) . 'api/v1/workspace/' . $workspace_slug;

        // Make test request
        $response = wp_remote_get( $url, array(
            'timeout' => 15,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Accept'        => 'application/json',
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'connection_failed', sprintf( __( 'Erreur de connexion: %s', 'anythingllm-chatbot' ), $response->get_error_message() ) );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( $code === 401 || $code === 403 ) {
            return new WP_Error( 'auth_failed', __( 'Clé API invalide ou expirée', 'anythingllm-chatbot' ) );
        }

        if ( $code === 404 ) {
            return new WP_Error( 'workspace_not_found', __( 'Workspace non trouvé. Vérifiez le slug.', 'anythingllm-chatbot' ) );
        }

        if ( $code !== 200 ) {
            $error_msg = isset( $data['message'] ) ? $data['message'] : sprintf( __( 'Erreur HTTP %d', 'anythingllm-chatbot' ), $code );
            return new WP_Error( 'api_error', $error_msg );
        }

        // Success
        $workspace_name = isset( $data['workspace']['name'] ) ? $data['workspace']['name'] : $workspace_slug;
        
        return array(
            'message'   => sprintf( __( 'Connexion réussie ! Workspace: %s', 'anythingllm-chatbot' ), $workspace_name ),
            'workspace' => $workspace_name,
        );
    }

    /**
     * Handle test connection AJAX request
     */
    public function handle_test_connection() {
        // Verify nonce
        if ( ! check_ajax_referer( 'ofac_admin_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Nonce invalide', 'anythingllm-chatbot' ) ), 403 );
            return;
        }

        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permissions insuffisantes', 'anythingllm-chatbot' ) ), 403 );
            return;
        }

        // Get values from POST
        $api_url = isset( $_POST['api_url'] ) ? esc_url_raw( wp_unslash( $_POST['api_url'] ) ) : '';
        $api_key = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
        $workspace_slug = isset( $_POST['workspace_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['workspace_slug'] ) ) : '';
        $save_on_success = isset( $_POST['save_on_success'] ) && $_POST['save_on_success'] === 'true';

        try {
            // Test connection with provided values
            $result = $this->test_connection();

            if ( is_wp_error( $result ) ) {
                wp_send_json_error( array( 
                    'message' => $result->get_error_message(),
                    'code'    => $result->get_error_code(),
                ) );
                return;
            }

            // Ensure result is an array
            if ( ! is_array( $result ) ) {
                $result = array( 'message' => __( 'Connexion réussie', 'anythingllm-chatbot' ) );
            }

            // If test successful and save requested, save the settings
            if ( $save_on_success && ! empty( $api_url ) && ! empty( $api_key ) && ! empty( $workspace_slug ) ) {
                $settings = OFAC_Settings::get_instance();
                $settings->set( 'ofac_api_url', $api_url );
                $settings->set( 'ofac_api_key', $api_key );
                $settings->set( 'ofac_workspace_slug', $workspace_slug );
                
                $result['saved'] = true;
                $result['message'] = $result['message'] . ' ' . __( '— Configuration sauvegardée !', 'anythingllm-chatbot' );
            }

            wp_send_json_success( $result );
            
        } catch ( Exception $e ) {
            wp_send_json_error( array( 
                'message' => $e->getMessage(),
                'code'    => 'exception',
            ) );
        }
    }

    /**
     * Handle file upload
     */
    public function handle_file_upload() {
        if ( ! check_ajax_referer( 'ofac_chat_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Nonce invalide', 'anythingllm-chatbot' ) ), 403 );
        }

        if ( empty( $_FILES['file'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Aucun fichier', 'anythingllm-chatbot' ) ), 400 );
        }

        $settings = OFAC_Settings::get_instance();

        if ( ! $settings->get( 'ofac_enable_file_upload', true ) ) {
            wp_send_json_error( array( 'message' => __( 'Upload désactivé', 'anythingllm-chatbot' ) ), 403 );
        }

        $file = $_FILES['file'];

        // Check file size
        $max_size = $settings->get( 'ofac_max_file_size', 5 ) * 1024 * 1024;
        if ( $file['size'] > $max_size ) {
            wp_send_json_error( array( 'message' => __( 'Fichier trop volumineux', 'anythingllm-chatbot' ) ), 400 );
        }

        // Check file type
        $allowed_types = array_map( 'trim', explode( ',', $settings->get( 'ofac_allowed_file_types', 'jpg,jpeg,png,gif,pdf,doc,docx,txt' ) ) );
        $file_ext      = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

        if ( ! in_array( $file_ext, $allowed_types, true ) ) {
            wp_send_json_error( array( 'message' => __( 'Type de fichier non autorisé', 'anythingllm-chatbot' ) ), 400 );
        }

        // Upload to AnythingLLM
        $url = trailingslashit( $this->api_url ) . sprintf( 'api/v1/workspace/%s/upload', $this->workspace_slug );

        $boundary = wp_generate_uuid4();
        $body     = '';

        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"" . basename( $file['name'] ) . "\"\r\n";
        $body .= "Content-Type: " . $file['type'] . "\r\n\r\n";
        $body .= file_get_contents( $file['tmp_name'] ) . "\r\n";
        $body .= "--{$boundary}--\r\n";

        $response = wp_remote_post( $url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'multipart/form-data; boundary=' . $boundary,
            ),
            'body'    => $body,
            'timeout' => $this->timeout,
        ) );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => $response->get_error_message() ), 500 );
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        wp_send_json_success( array(
            'message'  => __( 'Fichier uploadé', 'anythingllm-chatbot' ),
            'filename' => basename( $file['name'] ),
            'data'     => $data,
        ) );
    }

    /**
     * Get workspaces list
     *
     * @return array|WP_Error
     */
    public function get_workspaces() {
        return $this->request( 'api/v1/workspaces' );
    }
}
