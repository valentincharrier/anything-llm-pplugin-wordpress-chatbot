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
     * Single instance
     *
     * @var OFAC_Logs|null
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
     * @return OFAC_Logs
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
     * @param int    $page     Current page number.
     * @param int    $per_page Items per page.
     * @param array  $filters  Optional filters (search, user_id, date_from, date_to).
     * @param string $orderby  Order by column.
     * @param string $order    Order direction (ASC/DESC).
     * @return array
     */
    public function get_conversations( $page = 1, $per_page = 20, $filters = array(), $orderby = 'started_at', $order = 'DESC' ) {
        global $wpdb;

        $offset = ( $page - 1 ) * $per_page;

        $where = array( '1=1' );
        $values = array();

        if ( ! empty( $filters['user_id'] ) ) {
            $where[]  = 'c.user_id = %d';
            $values[] = $filters['user_id'];
        }

        if ( ! empty( $filters['date_from'] ) ) {
            $where[]  = 'c.started_at >= %s';
            $values[] = $filters['date_from'] . ' 00:00:00';
        }

        if ( ! empty( $filters['date_to'] ) ) {
            $where[]  = 'c.started_at <= %s';
            $values[] = $filters['date_to'] . ' 23:59:59';
        }

        if ( ! empty( $filters['search'] ) ) {
            $where[]  = '(c.session_id LIKE %s OR m.content LIKE %s)';
            $search   = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
            $values[] = $search;
            $values[] = $search;
        }

        $where_sql = implode( ' AND ', $where );

        // Whitelist orderby
        $allowed_orderby = array( 'started_at', 'message_count', 'id' );
        $orderby         = in_array( $orderby, $allowed_orderby, true ) ? $orderby : 'started_at';
        $order           = strtoupper( $order ) === 'ASC' ? 'ASC' : 'DESC';

        // Build SQL for conversations
        $sql = "SELECT c.*
                FROM {$wpdb->prefix}ofac_conversations c";

        // Only join messages if search is used
        if ( ! empty( $filters['search'] ) ) {
            $sql .= " LEFT JOIN {$wpdb->prefix}ofac_messages m ON c.id = m.conversation_id";
        }

        $sql .= " WHERE {$where_sql}
                  GROUP BY c.id
                  ORDER BY c.{$orderby} {$order}
                  LIMIT %d OFFSET %d";

        $values[] = $per_page;
        $values[] = $offset;

        if ( ! empty( $values ) ) {
            $conversations = $wpdb->get_results(
                $wpdb->prepare( $sql, ...$values )
            );
        } else {
            $conversations = $wpdb->get_results( $sql );
        }

        // Get total count
        $count_sql = "SELECT COUNT(DISTINCT c.id)
                      FROM {$wpdb->prefix}ofac_conversations c";

        if ( ! empty( $filters['search'] ) ) {
            $count_sql .= " LEFT JOIN {$wpdb->prefix}ofac_messages m ON c.id = m.conversation_id";
        }

        $count_sql .= " WHERE {$where_sql}";

        // Remove LIMIT and OFFSET from values for count query
        $count_values = array_slice( $values, 0, -2 );

        $total = empty( $count_values )
            ? (int) $wpdb->get_var( $count_sql )
            : (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$count_values ) );

        return array(
            'items' => $conversations,
            'total' => $total,
            'pages' => ceil( $total / $per_page ),
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
            )
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
