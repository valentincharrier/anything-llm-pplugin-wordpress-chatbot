<?php
/**
 * GDPR compliance class
 *
 * @package OcadeFusion_AnythingLLM_Chatbot
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class OFAC_GDPR
 * Handles GDPR compliance features
 */
class OFAC_GDPR {

    /**
     * Single instance
     *
     * @var OFAC_GDPR|null
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
     * @return OFAC_GDPR
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
    private function __construct() {
        $this->settings = OFAC_Settings::get_instance();
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Schedule data cleanup
        add_action( 'ofac_cleanup_gdpr_data', array( $this, 'cleanup_expired_data' ) );

        if ( ! wp_next_scheduled( 'ofac_cleanup_gdpr_data' ) ) {
            wp_schedule_event( time(), 'daily', 'ofac_cleanup_gdpr_data' );
        }

        // WordPress privacy hooks
        add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_data_exporter' ) );
        add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_data_eraser' ) );

        // AJAX handlers
        add_action( 'wp_ajax_ofac_export_user_data', array( $this, 'export_user_data' ) );
        add_action( 'wp_ajax_ofac_delete_user_data', array( $this, 'delete_user_data' ) );
    }

    /**
     * Cleanup expired data
     *
     * @return int Number of conversations deleted
     */
    public function cleanup_expired_data() {
        global $wpdb;

        $retention_days = $this->settings->get( 'ofac_data_retention_days', 30 );
        $cutoff_date    = date( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) );

        // Delete old conversations and messages
        $old_conversations = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ofac_conversations WHERE started_at < %s",
                $cutoff_date
            )
        );

        $deleted_count = 0;

        if ( ! empty( $old_conversations ) ) {
            $ids_placeholder = implode( ',', array_fill( 0, count( $old_conversations ), '%d' ) );

            // Delete messages
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->prefix}ofac_messages WHERE conversation_id IN ({$ids_placeholder})",
                    ...$old_conversations
                )
            );

            // Delete feedback
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->prefix}ofac_feedback
                    WHERE message_id IN (
                        SELECT id FROM {$wpdb->prefix}ofac_messages
                        WHERE conversation_id IN ({$ids_placeholder})
                    )",
                    ...$old_conversations
                )
            );

            // Delete conversations
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->prefix}ofac_conversations WHERE id IN ({$ids_placeholder})",
                    ...$old_conversations
                )
            );

            $deleted_count = count( $old_conversations );
        }

        // Delete expired consents
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}ofac_consents WHERE expires_at < %s",
                current_time( 'mysql' )
            )
        );

        /**
         * Fires after GDPR data cleanup
         *
         * @since 1.0.0
         * @param string $cutoff_date Cutoff date
         * @param int    $deleted     Number of conversations deleted
         */
        do_action( 'ofac_gdpr_cleanup', $cutoff_date, $deleted_count );

        return $deleted_count;
    }

    /**
     * Register data exporter
     *
     * @param array $exporters Existing exporters
     * @return array
     */
    public function register_data_exporter( $exporters ) {
        $exporters['ofac-chatbot'] = array(
            'exporter_friendly_name' => __( 'Ocade Fusion AnythingLLM Chatbot', 'anythingllm-chatbot' ),
            'callback'               => array( $this, 'wp_data_exporter' ),
        );

        return $exporters;
    }

    /**
     * Register data eraser
     *
     * @param array $erasers Existing erasers
     * @return array
     */
    public function register_data_eraser( $erasers ) {
        $erasers['ofac-chatbot'] = array(
            'eraser_friendly_name' => __( 'Ocade Fusion AnythingLLM Chatbot', 'anythingllm-chatbot' ),
            'callback'             => array( $this, 'wp_data_eraser' ),
        );

        return $erasers;
    }

    /**
     * WordPress privacy data exporter callback
     *
     * @param string $email_address User email
     * @param int    $page          Page number
     * @return array
     */
    public function wp_data_exporter( $email_address, $page = 1 ) {
        $user = get_user_by( 'email', $email_address );

        if ( ! $user ) {
            return array(
                'data' => array(),
                'done' => true,
            );
        }

        $export_items = array();
        $per_page     = 100;
        $offset       = ( $page - 1 ) * $per_page;

        global $wpdb;

        $conversations = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT c.*, GROUP_CONCAT(m.content SEPARATOR '|||') as messages 
                FROM {$wpdb->prefix}ofac_conversations c 
                LEFT JOIN {$wpdb->prefix}ofac_messages m ON c.id = m.conversation_id 
                WHERE c.user_id = %d 
                GROUP BY c.id 
                ORDER BY c.started_at DESC 
                LIMIT %d OFFSET %d",
                $user->ID,
                $per_page,
                $offset
            )
        );

        foreach ( $conversations as $conversation ) {
            $data = array(
                array(
                    'name'  => __( 'Date de début', 'anythingllm-chatbot' ),
                    'value' => $conversation->started_at,
                ),
                array(
                    'name'  => __( 'Nombre de messages', 'anythingllm-chatbot' ),
                    'value' => $conversation->message_count,
                ),
            );

            if ( ! empty( $conversation->messages ) ) {
                $messages = explode( '|||', $conversation->messages );
                $data[]   = array(
                    'name'  => __( 'Messages', 'anythingllm-chatbot' ),
                    'value' => implode( "\n---\n", $messages ),
                );
            }

            $export_items[] = array(
                'group_id'          => 'ofac-conversations',
                'group_label'       => __( 'Conversations Chatbot', 'anythingllm-chatbot' ),
                'group_description' => __( 'Historique des conversations avec le chatbot', 'anythingllm-chatbot' ),
                'item_id'           => 'conversation-' . $conversation->id,
                'data'              => $data,
            );
        }

        $done = count( $conversations ) < $per_page;

        return array(
            'data' => $export_items,
            'done' => $done,
        );
    }

    /**
     * WordPress privacy data eraser callback
     *
     * @param string $email_address User email
     * @param int    $page          Page number
     * @return array
     */
    public function wp_data_eraser( $email_address, $page = 1 ) {
        $user = get_user_by( 'email', $email_address );

        if ( ! $user ) {
            return array(
                'items_removed'  => false,
                'items_retained' => false,
                'messages'       => array(),
                'done'           => true,
            );
        }

        global $wpdb;

        // Get user conversations
        $conversation_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ofac_conversations WHERE user_id = %d",
                $user->ID
            )
        );

        $items_removed = 0;

        if ( ! empty( $conversation_ids ) ) {
            $ids_placeholder = implode( ',', array_fill( 0, count( $conversation_ids ), '%d' ) );

            // Delete messages
            $items_removed += $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->prefix}ofac_messages WHERE conversation_id IN ({$ids_placeholder})",
                    ...$conversation_ids
                )
            );

            // Delete conversations
            $items_removed += $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->prefix}ofac_conversations WHERE id IN ({$ids_placeholder})",
                    ...$conversation_ids
                )
            );
        }

        // Delete user consent meta
        delete_user_meta( $user->ID, 'ofac_consent' );

        return array(
            'items_removed'  => $items_removed > 0,
            'items_retained' => false,
            'messages'       => array(),
            'done'           => true,
        );
    }

    /**
     * Export user data via AJAX (admin)
     */
    public function export_user_data() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Non autorisé', 'anythingllm-chatbot' ) ), 403 );
        }

        if ( ! check_ajax_referer( 'ofac_admin_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Nonce invalide', 'anythingllm-chatbot' ) ), 403 );
        }

        $identifier = isset( $_POST['identifier'] ) ? sanitize_text_field( wp_unslash( $_POST['identifier'] ) ) : '';

        if ( empty( $identifier ) ) {
            wp_send_json_error( array( 'message' => __( 'Identifiant requis', 'anythingllm-chatbot' ) ), 400 );
        }

        $data = $this->get_user_data( $identifier );

        wp_send_json_success( array(
            'data'     => $data,
            'filename' => sprintf( 'chatbot-data-%s.json', date( 'Y-m-d' ) ),
        ) );
    }

    /**
     * Delete user data via AJAX (admin)
     */
    public function delete_user_data() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Non autorisé', 'anythingllm-chatbot' ) ), 403 );
        }

        if ( ! check_ajax_referer( 'ofac_admin_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Nonce invalide', 'anythingllm-chatbot' ) ), 403 );
        }

        $identifier = isset( $_POST['identifier'] ) ? sanitize_text_field( wp_unslash( $_POST['identifier'] ) ) : '';

        if ( empty( $identifier ) ) {
            wp_send_json_error( array( 'message' => __( 'Identifiant requis', 'anythingllm-chatbot' ) ), 400 );
        }

        $deleted = $this->erase_user_data( $identifier );

        wp_send_json_success( array(
            'message' => sprintf( __( '%d éléments supprimés', 'anythingllm-chatbot' ), $deleted ),
        ) );
    }

    /**
     * Get user data by identifier (session_id or user_id)
     *
     * @param string $identifier Session ID or user ID
     * @return array
     */
    public function get_user_data( $identifier ) {
        global $wpdb;

        $data = array(
            'conversations' => array(),
            'consents'      => array(),
        );

        // Check if it's a user ID or session ID
        if ( is_numeric( $identifier ) ) {
            $where = $wpdb->prepare( "user_id = %d", $identifier );
        } else {
            $where = $wpdb->prepare( "session_id = %s", $identifier );
        }

        // Get conversations
        $conversations = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}ofac_conversations WHERE {$where}"
        );

        foreach ( $conversations as $conversation ) {
            $messages = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT role, content, created_at FROM {$wpdb->prefix}ofac_messages 
                    WHERE conversation_id = %d ORDER BY created_at ASC",
                    $conversation->id
                )
            );

            $data['conversations'][] = array(
                'session_id'    => $conversation->session_id,
                'started_at'    => $conversation->started_at,
                'message_count' => $conversation->message_count,
                'messages'      => $messages,
            );
        }

        // Get consents
        if ( ! is_numeric( $identifier ) ) {
            $consents = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT consented_at, expires_at FROM {$wpdb->prefix}ofac_consents WHERE session_id = %s",
                    $identifier
                )
            );

            $data['consents'] = $consents;
        }

        return $data;
    }

    /**
     * Erase user data by identifier
     *
     * @param string $identifier Session ID or user ID
     * @return int Number of items deleted
     */
    public function erase_user_data( $identifier ) {
        global $wpdb;

        $deleted = 0;

        // Get conversation IDs
        if ( is_numeric( $identifier ) ) {
            $conversation_ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}ofac_conversations WHERE user_id = %d",
                    $identifier
                )
            );
        } else {
            $conversation_ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}ofac_conversations WHERE session_id = %s",
                    $identifier
                )
            );
        }

        if ( ! empty( $conversation_ids ) ) {
            $ids_placeholder = implode( ',', array_fill( 0, count( $conversation_ids ), '%d' ) );

            // Delete messages
            $deleted += $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->prefix}ofac_messages WHERE conversation_id IN ({$ids_placeholder})",
                    ...$conversation_ids
                )
            );

            // Delete conversations
            $deleted += $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->prefix}ofac_conversations WHERE id IN ({$ids_placeholder})",
                    ...$conversation_ids
                )
            );
        }

        // Delete consents
        if ( ! is_numeric( $identifier ) ) {
            $deleted += $wpdb->delete(
                $wpdb->prefix . 'ofac_consents',
                array( 'session_id' => $identifier ),
                array( '%s' )
            );
        }

        return $deleted;
    }
}
