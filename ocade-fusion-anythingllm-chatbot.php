<?php
/**
 * Plugin Name: Ocade Fusion AnythingLLM Chatbot
 * Plugin URI: https://ocadefusion.fr/plugins/anythingllm-chatbot
 * Description: Intégrez un chatbot intelligent propulsé par AnythingLLM à votre site WordPress. Conforme RGAA et RGPD.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Ocade Fusion
 * Author URI: https://ocadefusion.fr
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: anythingllm-chatbot
 * Domain Path: /languages
 * Network: true
 *
 * @package OcadeFusion_AnythingLLM_Chatbot
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'OFAC_VERSION', '1.0.0' );
define( 'OFAC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'OFAC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'OFAC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'OFAC_MIN_PHP_VERSION', '8.0' );
define( 'OFAC_MIN_WP_VERSION', '6.0' );

/**
 * Check requirements before loading the plugin
 */
function ofac_check_requirements() {
    $errors = array();

    if ( version_compare( PHP_VERSION, OFAC_MIN_PHP_VERSION, '<' ) ) {
        $errors[] = sprintf(
            /* translators: 1: Current PHP version, 2: Required PHP version */
            __( 'Ocade Fusion AnythingLLM Chatbot requires PHP %2$s or higher. Your current version is %1$s.', 'anythingllm-chatbot' ),
            PHP_VERSION,
            OFAC_MIN_PHP_VERSION
        );
    }

    global $wp_version;
    if ( version_compare( $wp_version, OFAC_MIN_WP_VERSION, '<' ) ) {
        $errors[] = sprintf(
            /* translators: 1: Current WordPress version, 2: Required WordPress version */
            __( 'Ocade Fusion AnythingLLM Chatbot requires WordPress %2$s or higher. Your current version is %1$s.', 'anythingllm-chatbot' ),
            $wp_version,
            OFAC_MIN_WP_VERSION
        );
    }

    return $errors;
}

/**
 * Display admin notice for requirements
 */
function ofac_requirements_notice() {
    $errors = ofac_check_requirements();
    if ( ! empty( $errors ) ) {
        foreach ( $errors as $error ) {
            echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>';
        }
    }
}

/**
 * Main plugin class
 */
final class OFAC_Plugin {

    /**
     * Single instance
     *
     * @var OFAC_Plugin|null
     */
    private static $instance = null;

    /**
     * Plugin components
     */
    public $admin;
    public $frontend;
    public $api;
    public $chatbot;
    public $consent;
    public $accessibility;
    public $cache;
    public $stats;

    /**
     * Get single instance
     *
     * @return OFAC_Plugin
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
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required files
     */
    private function load_dependencies() {
        // Core classes
        require_once OFAC_PLUGIN_DIR . 'includes/class-ofac-loader.php';
        require_once OFAC_PLUGIN_DIR . 'includes/class-ofac-i18n.php';
        require_once OFAC_PLUGIN_DIR . 'includes/class-ofac-settings.php';
        require_once OFAC_PLUGIN_DIR . 'includes/class-ofac-api.php';
        require_once OFAC_PLUGIN_DIR . 'includes/class-ofac-chatbot.php';
        require_once OFAC_PLUGIN_DIR . 'includes/class-ofac-consent.php';
        require_once OFAC_PLUGIN_DIR . 'includes/class-ofac-accessibility.php';
        require_once OFAC_PLUGIN_DIR . 'includes/class-ofac-cache.php';
        require_once OFAC_PLUGIN_DIR . 'includes/class-ofac-stats.php';
        require_once OFAC_PLUGIN_DIR . 'includes/class-ofac-rate-limiter.php';
        require_once OFAC_PLUGIN_DIR . 'includes/class-ofac-gdpr.php';
        require_once OFAC_PLUGIN_DIR . 'includes/class-ofac-logs.php';

        // Admin classes
        if ( is_admin() ) {
            require_once OFAC_PLUGIN_DIR . 'admin/class-ofac-admin.php';
            require_once OFAC_PLUGIN_DIR . 'admin/class-ofac-admin-settings.php';
            require_once OFAC_PLUGIN_DIR . 'admin/class-ofac-admin-logs.php';
            require_once OFAC_PLUGIN_DIR . 'admin/class-ofac-admin-stats.php';
            require_once OFAC_PLUGIN_DIR . 'admin/class-ofac-admin-gdpr.php';
        }

        // Public classes
        require_once OFAC_PLUGIN_DIR . 'public/class-ofac-public.php';
        require_once OFAC_PLUGIN_DIR . 'public/class-ofac-shortcode.php';
        require_once OFAC_PLUGIN_DIR . 'public/class-ofac-block.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Load text domain on init (WordPress 6.7+ requirement)
        add_action( 'init', array( $this, 'load_textdomain' ) );

        // Initialize components
        add_action( 'plugins_loaded', array( $this, 'init_components' ), 20 );

        // Register activation/deactivation hooks
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

        // Register uninstall hook
        register_uninstall_hook( __FILE__, array( 'OFAC_Plugin', 'uninstall' ) );
    }

    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'anythingllm-chatbot',
            false,
            dirname( OFAC_PLUGIN_BASENAME ) . '/languages/'
        );
    }

    /**
     * Initialize plugin components
     */
    public function init_components() {
        // Check if tables need to be created/updated
        $this->maybe_create_tables();

        // Initialize settings first
        OFAC_Settings::get_instance();

        // Initialize API handler
        $this->api = new OFAC_API();

        // Initialize chatbot
        $this->chatbot = OFAC_Chatbot::get_instance();

        // Initialize consent manager
        $this->consent = OFAC_Consent::get_instance();

        // Initialize accessibility
        $this->accessibility = OFAC_Accessibility::get_instance();

        // Initialize cache
        $this->cache = new OFAC_Cache();

        // Initialize stats
        $this->stats = OFAC_Stats::get_instance();

        // Initialize rate limiter
        new OFAC_Rate_Limiter();

        // Initialize GDPR handler
        OFAC_GDPR::get_instance();

        // Initialize logs
        OFAC_Logs::get_instance();

        // Initialize admin
        if ( is_admin() ) {
            $this->admin = OFAC_Admin::get_instance();
        }

        // Initialize public
        OFAC_Public::get_instance();

        // Initialize shortcode
        new OFAC_Shortcode();

        // Initialize Gutenberg block
        new OFAC_Block();

        /**
         * Fires after all plugin components are initialized
         *
         * @since 1.0.0
         */
        do_action( 'ofac_init' );
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Check requirements
        $errors = ofac_check_requirements();
        if ( ! empty( $errors ) ) {
            wp_die( implode( '<br>', $errors ) );
        }

        // Create database tables
        $this->create_tables();

        // Set default options
        $this->set_default_options();

        // Clear rewrite rules
        flush_rewrite_rules();

        // Set activation flag
        set_transient( 'ofac_activated', true, 30 );

        /**
         * Fires on plugin activation
         *
         * @since 1.0.0
         */
        do_action( 'ofac_activate' );
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook( 'ofac_cleanup_logs' );
        wp_clear_scheduled_hook( 'ofac_cleanup_cache' );
        wp_clear_scheduled_hook( 'ofac_cleanup_gdpr_data' );

        // Clear rewrite rules
        flush_rewrite_rules();

        /**
         * Fires on plugin deactivation
         *
         * @since 1.0.0
         */
        do_action( 'ofac_deactivate' );
    }

    /**
     * Plugin uninstall
     */
    public static function uninstall() {
        global $wpdb;

        // Delete options
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'ofac_%'" );

        // Delete tables
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ofac_conversations" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ofac_messages" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ofac_stats" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ofac_feedback" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ofac_consents" );

        // Delete transients
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ofac_%'" );
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_ofac_%'" );

        /**
         * Fires on plugin uninstall
         *
         * @since 1.0.0
         */
        do_action( 'ofac_uninstall' );
    }

    /**
     * Check and create tables if needed
     */
    private function maybe_create_tables() {
        $db_version = get_option( 'ofac_db_version', '0' );
        
        if ( version_compare( $db_version, OFAC_VERSION, '<' ) ) {
            $this->create_tables();
            $this->set_default_options();
        }
    }

    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = array();

        // Conversations table
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ofac_conversations (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id varchar(64) NOT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            ip_hash varchar(64) NOT NULL,
            started_at datetime NOT NULL,
            ended_at datetime DEFAULT NULL,
            message_count int(11) DEFAULT 0,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY user_id (user_id),
            KEY started_at (started_at)
        ) $charset_collate;";

        // Messages table
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ofac_messages (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            conversation_id bigint(20) unsigned NOT NULL,
            role enum('user','assistant','system') NOT NULL,
            content longtext NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY conversation_id (conversation_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Stats table
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ofac_stats (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            date date NOT NULL,
            conversations int(11) DEFAULT 0,
            messages int(11) DEFAULT 0,
            errors int(11) DEFAULT 0,
            avg_response_time float DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY date (date)
        ) $charset_collate;";

        // Feedback table
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ofac_feedback (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            message_id bigint(20) unsigned NOT NULL,
            rating tinyint(1) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY message_id (message_id)
        ) $charset_collate;";

        // Consents table
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ofac_consents (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id varchar(64) NOT NULL,
            ip_hash varchar(64) NOT NULL,
            consented_at datetime NOT NULL,
            expires_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY expires_at (expires_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ( $sql as $query ) {
            dbDelta( $query );
        }

        update_option( 'ofac_db_version', OFAC_VERSION );
    }

    /**
     * Set default options
     */
    private function set_default_options() {
        $defaults = array(
            'ofac_api_url'              => '',
            'ofac_api_key'              => '',
            'ofac_workspace_slug'       => '',
            'ofac_timeout'              => 60,
            'ofac_enabled'              => false,
            'ofac_position'             => 'bottom-right',
            'ofac_width_desktop'        => 400,
            'ofac_height_desktop'       => 600,
            'ofac_theme_mode'           => 'auto',
            'ofac_primary_color'        => '#0073aa',
            'ofac_bot_avatar'           => '',
            'ofac_user_avatar'          => '',
            'ofac_welcome_message'      => 'Bonjour ! Comment puis-je vous aider ?',
            'ofac_fallback_message'     => 'Désolé, je ne suis pas disponible actuellement. Veuillez réessayer plus tard.',
            'ofac_placeholder_text'     => 'Tapez votre message...',
            'ofac_max_chars'            => 5000,
            'ofac_max_file_size'        => 5,
            'ofac_allowed_file_types'   => array( 'jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt' ),
            'ofac_rate_limit'           => 30,
            'ofac_cache_duration'       => 3600,
            'ofac_data_retention_days'  => 30,
            'ofac_privacy_policy_url'   => '',
            'ofac_show_skip_link'       => true,
            'ofac_allowed_roles'        => array(),
            'ofac_enable_logs'          => true,
            'ofac_enable_stats'         => true,
            'ofac_require_consent'      => true,
        );

        foreach ( $defaults as $key => $value ) {
            if ( false === get_option( $key ) ) {
                add_option( $key, $value );
            }
        }
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserializing
     */
    public function __wakeup() {
        throw new \Exception( 'Cannot unserialize singleton' );
    }
}

/**
 * Initialize the plugin
 */
function ofac_init() {
    $errors = ofac_check_requirements();
    if ( ! empty( $errors ) ) {
        add_action( 'admin_notices', 'ofac_requirements_notice' );
        return;
    }
    return OFAC_Plugin::get_instance();
}

// Start the plugin
add_action( 'plugins_loaded', 'ofac_init', 10 );

/**
 * Get plugin instance
 *
 * @return OFAC_Plugin
 */
function ofac() {
    return OFAC_Plugin::get_instance();
}
