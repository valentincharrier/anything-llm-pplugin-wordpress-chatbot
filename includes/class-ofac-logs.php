<?php
/**
 * Logs class
 *
 * @package OcadeFusion_AnythingLLM_Chatbot
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class OFAC_Logs
 * Handles conversation logging
 */
class OFAC_Logs {

    /**
     * Settings instance
     *
     * @var OFAC_Settings
     */
    private $settings;

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
        add_action( 'ofac_cleanup_logs', array( $this, 'cleanup_old_logs' ) );

        if ( ! wp_next_scheduled( 'ofac_cleanup_logs' ) ) {
            wp_schedule_event( time(), 'daily', 'ofac_cleanup_logs' );
        }
    }

    /**
     * Log a message
     *
     * @param string $session_id Session ID
     * @param string $role Message role (user/assistant)
     * @param string $content Message content
     * @return int|false Message ID or false on failure
     */
    public function log_message( $session_id, $role, $content ) {
        if ( ! $this->settings->get( 'ofac_enable_logs', true ) ) {
            return false;
        }

        global $wpdb;

        // Get or create conversation
        $conversation_id = $this->get_or_create_conversation( $session_id );

        if ( ! $conversation_id ) {
            return false;
        }

        // Insert message
        $result = $wpdb->insert(
            $wpdb->prefix . 'ofac_messages',
            array(
                'conversation_id' => $conversation_id,
                'role'            => $role,
                'content'         => $content,
                'created_at'      => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s' )
        );

        if ( $result === false ) {
            return false;
        }

        $message_id = $wpdb->insert_id;

        // Update conversation message count
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}ofac_conversations SET message_count = message_count + 1 WHERE id = %d",
                $conversation_id
            )
        );

        /**
         * Fires after a message is logged
         *
         * @since 1.0.0
         * @param int    $message_id      Message ID
         * @param int    $conversation_id Conversation ID
         * @param string $role            Message role
         * @param string $content         Message content
         */
        do_action( 'ofac_message_logged', $message_id, $conversation_id, $role, $content );

        return $message_id;
    }

    /**
     * Get or create conversation
     *
     * @param string $session_id Session ID
     * @return int|false Conversation ID
     */
    private function get_or_create_conversation( $session_id ) {
        global $wpdb;

        // Check for existing conversation
        $conversation_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ofac_conversations WHERE session_id = %s",
                $session_id
            )
        );

        if ( $conversation_id ) {
            return (int) $conversation_id;
        }

        // Create new conversation
        $user_id = is_user_logged_in() ? get_current_user_id() : null;
        $ip_hash = $this->hash_ip( $this->get_client_ip() );

        $result = $wpdb->insert(
            $wpdb->prefix . 'ofac_conversations',
            array(
                'session_id'    => $session_id,
                'user_id'       => $user_id,
                'ip_hash'       => $ip_hash,
                'started_at'    => current_time( 'mysql' ),
                'message_count' => 0,
            ),
            array( '%s', '%d', '%s', '%s', '%d' )
        );

        if ( $result === false ) {
            return false;
        }

        $conversation_id = $wpdb->insert_id;

        // Update stats
        OFAC_Stats::increment_conversations();

        /**
         * Fires after a conversation is created
         *
         * @since 1.0.0
         * @param int    $conversation_id Conversation ID
         * @param string $session_id      Session ID
         */
        do_action( 'ofac_conversation_created', $conversation_id, $session_id );

        return $conversation_id;
    }

    /**
     * Get conversations with pagination
     *
     * @param array $args Query arguments
     * @return array
     */
    public function get_conversations( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'per_page' => 20,
            'page'     => 1,
            'orderby'  => 'started_at',
            'order'    => 'DESC',
            'search'   => '',
            'user_id'  => null,
            'date_from' => null,
            'date_to'   => null,
        );

        $args   = wp_parse_args( $args, $defaults );
        $offset = ( $args['page'] - 1 ) * $args['per_page'];

        $where = array( '1=1' );
        $values = array();

        if ( ! empty( $args['user_id'] ) ) {
            $where[]  = 'c.user_id = %d';
            $values[] = $args['user_id'];
        }

        if ( ! empty( $args['date_from'] ) ) {
            $where[]  = 'c.started_at >= %s';
            $values[] = $args['date_from'] . ' 00:00:00';
        }

        if ( ! empty( $args['date_to'] ) ) {
            $where[]  = 'c.started_at <= %s';
            $values[] = $args['date_to'] . ' 23:59:59';
        }

        if ( ! empty( $args['search'] ) ) {
            $where[]  = '(c.session_id LIKE %s OR m.content LIKE %s)';
            $search   = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $values[] = $search;
            $values[] = $search;
        }

        $where_sql = implode( ' AND ', $where );

        // Whitelist orderby
        $allowed_orderby = array( 'started_at', 'message_count' );
        $orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'started_at';
        $order           = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

        $values[] = $args['per_page'];
        $values[] = $offset;

        $sql = "SELECT c.*, u.display_name as user_name 
                FROM {$wpdb->prefix}ofac_conversations c 
                LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID 
                LEFT JOIN {$wpdb->prefix}ofac_messages m ON c.id = m.conversation_id 
                WHERE {$where_sql} 
                GROUP BY c.id 
                ORDER BY c.{$orderby} {$order} 
                LIMIT %d OFFSET %d";

        $conversations = $wpdb->get_results(
            $wpdb->prepare( $sql, ...$values ),
            ARRAY_A
        );

        // Get total count
        $count_values = array_slice( $values, 0, -2 );
        $count_sql = "SELECT COUNT(DISTINCT c.id) 
                      FROM {$wpdb->prefix}ofac_conversations c 
                      LEFT JOIN {$wpdb->prefix}ofac_messages m ON c.id = m.conversation_id 
                      WHERE {$where_sql}";

        $total = empty( $count_values ) 
            ? $wpdb->get_var( $count_sql ) 
            : $wpdb->get_var( $wpdb->prepare( $count_sql, ...$count_values ) );

        return array(
            'conversations' => $conversations,
            'total'         => (int) $total,
            'pages'         => ceil( $total / $args['per_page'] ),
            'current_page'  => $args['page'],
        );
    }

    /**
     * Get messages for a conversation
     *
     * @param int $conversation_id Conversation ID
     * @return array
     */
    public function get_messages( $conversation_id ) {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ofac_messages 
                WHERE conversation_id = %d 
                ORDER BY created_at ASC",
                $conversation_id
            ),
            ARRAY_A
        );
    }

    /**
     * Delete conversation
     *
     * @param int $conversation_id Conversation ID
     * @return bool
     */
    public function delete_conversation( $conversation_id ) {
        global $wpdb;

        // Delete messages
        $wpdb->delete(
            $wpdb->prefix . 'ofac_messages',
            array( 'conversation_id' => $conversation_id ),
            array( '%d' )
        );

        // Delete feedback
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}ofac_feedback 
                WHERE message_id IN (
                    SELECT id FROM {$wpdb->prefix}ofac_messages WHERE conversation_id = %d
                )",
                $conversation_id
            )
        );

        // Delete conversation
        $result = $wpdb->delete(
            $wpdb->prefix . 'ofac_conversations',
            array( 'id' => $conversation_id ),
            array( '%d' )
        );

        return $result !== false;
    }

    /**
     * Cleanup old logs
     */
    public function cleanup_old_logs() {
        $retention_days = $this->settings->get( 'ofac_data_retention_days', 30 );

        global $wpdb;

        $cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) );

        // Get old conversation IDs
        $old_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ofac_conversations WHERE started_at < %s",
                $cutoff_date
            )
        );

        if ( empty( $old_ids ) ) {
            return;
        }

        foreach ( $old_ids as $id ) {
            $this->delete_conversation( $id );
        }

        /**
         * Fires after old logs are cleaned up
         *
         * @since 1.0.0
         * @param int $count Number of conversations deleted
         */
        do_action( 'ofac_logs_cleanup', count( $old_ids ) );
    }

    /**
     * Get client IP
     *
     * @return string
     */
    private function get_client_ip() {
        $ip = '';

        if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
        } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }

        if ( strpos( $ip, ',' ) !== false ) {
            $ips = explode( ',', $ip );
            $ip  = trim( $ips[0] );
        }

        return $ip;
    }

    /**
     * Hash IP for anonymization
     *
     * @param string $ip IP address
     * @return string
     */
    private function hash_ip( $ip ) {
        // Anonymize last octet
        if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
            $parts    = explode( '.', $ip );
            $parts[3] = '0';
            $ip       = implode( '.', $parts );
        }

        return hash( 'sha256', $ip . wp_salt( 'auth' ) );
    }
}
