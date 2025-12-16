<?php
/**
 * Chatbot class
 *
 * @package OcadeFusion_AnythingLLM_Chatbot
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class OFAC_Chatbot
 * Main chatbot logic
 */
class OFAC_Chatbot {

    /**
     * Single instance
     *
     * @var OFAC_Chatbot|null
     */
    private static $instance = null;

    /**
     * Settings instance
     *
     * @var OFAC_Settings
     */
    private $settings;

    /**
     * Get single instance
     *
     * @return OFAC_Chatbot
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
        $this->settings = OFAC_Settings::get_instance();
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action( 'wp_ajax_ofac_save_feedback', array( $this, 'save_feedback' ) );
        add_action( 'wp_ajax_nopriv_ofac_save_feedback', array( $this, 'save_feedback' ) );
        add_action( 'wp_ajax_ofac_export_conversation', array( $this, 'export_conversation' ) );
        add_action( 'wp_ajax_nopriv_ofac_export_conversation', array( $this, 'export_conversation' ) );
        add_action( 'wp_ajax_ofac_clear_history', array( $this, 'clear_history' ) );
        add_action( 'wp_ajax_nopriv_ofac_clear_history', array( $this, 'clear_history' ) );
    }

    /**
     * Check if chatbot is enabled
     *
     * @return bool
     */
    public function is_enabled() {
        if ( ! $this->settings->get( 'ofac_enabled', false ) ) {
            return false;
        }

        // Check if API is configured
        $api = new OFAC_API();
        if ( ! $api->is_configured() ) {
            return false;
        }

        // Check role restrictions
        $allowed_roles = $this->settings->get( 'ofac_allowed_roles', array() );
        if ( ! empty( $allowed_roles ) ) {
            if ( ! is_user_logged_in() ) {
                return false;
            }

            $user  = wp_get_current_user();
            $match = array_intersect( $user->roles, $allowed_roles );
            if ( empty( $match ) ) {
                return false;
            }
        }

        /**
         * Filter chatbot enabled status
         *
         * @since 1.0.0
         * @param bool $enabled Whether chatbot is enabled
         */
        return apply_filters( 'ofac_is_enabled', true );
    }

    /**
     * Check if current page should display chatbot
     *
     * @return bool
     */
    public function should_display() {
        if ( ! $this->is_enabled() ) {
            return false;
        }

        // Don't show in admin
        if ( is_admin() ) {
            return false;
        }

        // Don't show on login page
        if ( $GLOBALS['pagenow'] === 'wp-login.php' ) {
            return false;
        }

        /**
         * Filter whether to display chatbot
         *
         * @since 1.0.0
         * @param bool $display Whether to display chatbot
         */
        return apply_filters( 'ofac_should_display', true );
    }

    /**
     * Generate session ID
     *
     * @return string
     */
    public function generate_session_id() {
        return wp_generate_uuid4();
    }

    /**
     * Process special commands
     *
     * @param string $message User message
     * @return array|false Command response or false if not a command
     */
    public function process_command( $message ) {
        $message = trim( strtolower( $message ) );

        $commands = array(
            '/reset' => array(
                'action'   => 'reset',
                'response' => __( 'Conversation réinitialisée.', 'anythingllm-chatbot' ),
            ),
            '/help' => array(
                'action'   => 'help',
                'response' => $this->get_help_text(),
            ),
            '/export' => array(
                'action'   => 'export',
                'response' => __( 'Export de la conversation en cours...', 'anythingllm-chatbot' ),
            ),
        );

        /**
         * Filter available commands
         *
         * @since 1.0.0
         * @param array $commands Available commands
         */
        $commands = apply_filters( 'ofac_commands', $commands );

        if ( isset( $commands[ $message ] ) ) {
            return $commands[ $message ];
        }

        return false;
    }

    /**
     * Get help text
     *
     * @return string
     */
    private function get_help_text() {
        $help = __( "Commandes disponibles :\n", 'anythingllm-chatbot' );
        $help .= __( "/reset - Réinitialiser la conversation\n", 'anythingllm-chatbot' );
        $help .= __( "/help - Afficher cette aide\n", 'anythingllm-chatbot' );
        $help .= __( "/export - Exporter la conversation", 'anythingllm-chatbot' );

        return $help;
    }

    /**
     * Save feedback
     */
    public function save_feedback() {
        if ( ! check_ajax_referer( 'ofac_chat_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Nonce invalide', 'anythingllm-chatbot' ) ), 403 );
        }

        $message_id = isset( $_POST['message_id'] ) ? absint( $_POST['message_id'] ) : 0;
        $rating     = isset( $_POST['rating'] ) ? intval( $_POST['rating'] ) : 0;

        if ( ! $message_id || ! in_array( $rating, array( -1, 1 ), true ) ) {
            wp_send_json_error( array( 'message' => __( 'Paramètres invalides', 'anythingllm-chatbot' ) ), 400 );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ofac_feedback';

        $wpdb->insert(
            $table,
            array(
                'message_id' => $message_id,
                'rating'     => $rating,
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%s' )
        );

        wp_send_json_success( array( 'message' => __( 'Merci pour votre retour !', 'anythingllm-chatbot' ) ) );
    }

    /**
     * Export conversation
     */
    public function export_conversation() {
        if ( ! check_ajax_referer( 'ofac_chat_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Nonce invalide', 'anythingllm-chatbot' ) ), 403 );
        }

        $session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';

        if ( empty( $session_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Session invalide', 'anythingllm-chatbot' ) ), 400 );
        }

        global $wpdb;

        $conversation = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ofac_conversations WHERE session_id = %s",
                $session_id
            )
        );

        if ( ! $conversation ) {
            wp_send_json_error( array( 'message' => __( 'Conversation non trouvée', 'anythingllm-chatbot' ) ), 404 );
        }

        $messages = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT role, content, created_at FROM {$wpdb->prefix}ofac_messages WHERE conversation_id = %d ORDER BY created_at ASC",
                $conversation->id
            )
        );

        $export = "=== Export de conversation ===\n";
        $export .= sprintf( "Date: %s\n\n", current_time( 'Y-m-d H:i:s' ) );

        foreach ( $messages as $message ) {
            $role_label = $message->role === 'user' ? __( 'Vous', 'anythingllm-chatbot' ) : __( 'Assistant', 'anythingllm-chatbot' );
            $export .= sprintf( "[%s] %s:\n%s\n\n", $message->created_at, $role_label, $message->content );
        }

        wp_send_json_success( array(
            'content'  => $export,
            'filename' => sprintf( 'conversation-%s.txt', date( 'Y-m-d-His' ) ),
        ) );
    }

    /**
     * Clear conversation history
     */
    public function clear_history() {
        if ( ! check_ajax_referer( 'ofac_chat_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Nonce invalide', 'anythingllm-chatbot' ) ), 403 );
        }

        $session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';

        if ( empty( $session_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Session invalide', 'anythingllm-chatbot' ) ), 400 );
        }

        global $wpdb;

        $conversation = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ofac_conversations WHERE session_id = %s",
                $session_id
            )
        );

        if ( $conversation ) {
            $wpdb->delete(
                $wpdb->prefix . 'ofac_messages',
                array( 'conversation_id' => $conversation ),
                array( '%d' )
            );

            $wpdb->delete(
                $wpdb->prefix . 'ofac_conversations',
                array( 'id' => $conversation ),
                array( '%d' )
            );
        }

        wp_send_json_success( array( 'message' => __( 'Historique effacé', 'anythingllm-chatbot' ) ) );
    }

    /**
     * Get conversation count for user
     *
     * @param int $user_id User ID
     * @return int
     */
    public function get_user_conversation_count( $user_id ) {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ofac_conversations WHERE user_id = %d",
                $user_id
            )
        );
    }
}
