<?php
/**
 * Public-facing functionality
 *
 * @package Ocade_Fusion_AnythingLLM_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class OFAC_Public
 */
class OFAC_Public {

    /**
     * Instance
     *
     * @var OFAC_Public
     */
    private static $instance = null;

    /**
     * Settings
     *
     * @var OFAC_Settings
     */
    private $settings;

    /**
     * Shortcode instance
     *
     * @var OFAC_Shortcode
     */
    private $shortcode;

    /**
     * Block instance
     *
     * @var OFAC_Block
     */
    private $block;

    /**
     * Get instance
     *
     * @return OFAC_Public
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
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load dependencies
     */
    private function load_dependencies() {
        require_once OFAC_PLUGIN_DIR . 'public/class-ofac-shortcode.php';
        require_once OFAC_PLUGIN_DIR . 'public/class-ofac-block.php';

        $this->shortcode = new OFAC_Shortcode();
        $this->block = new OFAC_Block();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
        add_action( 'wp_footer', array( $this, 'render_chatbot' ) );
        add_action( 'wp_body_open', array( $this, 'add_skip_link' ) );
    }

    /**
     * Register and enqueue assets
     */
    public function register_assets() {
        // Always register assets first
        wp_register_style(
            'ofac-chatbot',
            OFAC_PLUGIN_URL . 'assets/css/chatbot.css',
            array(),
            OFAC_VERSION
        );

        wp_register_script(
            'ofac-chatbot',
            OFAC_PLUGIN_URL . 'assets/js/chatbot.js',
            array( 'jquery' ),
            OFAC_VERSION,
            true
        );

        // Check if chatbot should be displayed
        $chatbot = OFAC_Chatbot::get_instance();
        if ( ! $chatbot->is_enabled() || ! $chatbot->should_display() ) {
            return;
        }

        // Enqueue styles
        wp_enqueue_style( 'ofac-chatbot' );

        // Enqueue main chatbot script
        wp_enqueue_script( 'ofac-chatbot' );

        // Localize settings - MUST be after enqueue
        wp_localize_script( 
            'ofac-chatbot',
            'ofacConfig',
            $this->get_frontend_config()
        );
    }

    /**
     * Get frontend configuration
     *
     * @return array
     */
    private function get_frontend_config() {
        $accessibility = OFAC_Accessibility::get_instance();

        return array(
            'ajax_url'       => admin_url( 'admin-ajax.php' ),
            'nonce'          => wp_create_nonce( 'ofac_chat_nonce' ),
            'stream_enabled' => (bool) $this->settings->get( 'ofac_enable_streaming', false ),
            'settings'       => $this->settings->get_public_settings(),
            'accessibility'  => $accessibility->get_settings(),
            'labels'         => $this->get_frontend_labels(),
            'commands'       => array(
                'reset'  => '/reset',
                'help'   => '/help',
                'export' => '/export',
            ),
        );
    }

    /**
     * Get frontend labels
     *
     * @return array
     */
    private function get_frontend_labels() {
        $accessibility = OFAC_Accessibility::get_instance();

        return array(
            'openChat'       => $accessibility->get_label( 'open_chat' ),
            'closeChat'      => $accessibility->get_label( 'close_chat' ),
            'sendMessage'    => $accessibility->get_label( 'send_message' ),
            'typing'         => $accessibility->get_label( 'typing' ),
            'newMessage'     => $accessibility->get_label( 'new_message' ),
            'copySuccess'    => __( 'Copié !', 'anythingllm-chatbot' ),
            'copyError'      => __( 'Erreur de copie', 'anythingllm-chatbot' ),
            'exportSuccess'  => __( 'Conversation exportée', 'anythingllm-chatbot' ),
            'resetSuccess'   => __( 'Conversation réinitialisée', 'anythingllm-chatbot' ),
            'errorMessage'   => $this->settings->get( 'error_message' ),
            'networkError'   => __( 'Erreur réseau. Veuillez réessayer.', 'anythingllm-chatbot' ),
            'rateLimited'    => __( 'Trop de messages. Veuillez patienter.', 'anythingllm-chatbot' ),
            'consentTitle'   => __( 'Consentement requis', 'anythingllm-chatbot' ),
            'consentAccept'  => __( 'Accepter', 'anythingllm-chatbot' ),
            'consentDecline' => __( 'Refuser', 'anythingllm-chatbot' ),
            'helpTitle'      => __( 'Aide', 'anythingllm-chatbot' ),
            'helpCommands'   => __( 'Commandes disponibles :', 'anythingllm-chatbot' ),
            'helpReset'      => __( '/reset - Réinitialiser la conversation', 'anythingllm-chatbot' ),
            'helpExport'     => __( '/export - Exporter la conversation', 'anythingllm-chatbot' ),
            'helpHelp'       => __( '/help - Afficher cette aide', 'anythingllm-chatbot' ),
            'uploadFile'     => __( 'Joindre un fichier', 'anythingllm-chatbot' ),
            'fileTooLarge'   => __( 'Fichier trop volumineux', 'anythingllm-chatbot' ),
            'fileTypeError'  => __( 'Type de fichier non autorisé', 'anythingllm-chatbot' ),
            'uploading'      => __( 'Envoi en cours...', 'anythingllm-chatbot' ),
            'feedbackThanks' => __( 'Merci pour votre retour !', 'anythingllm-chatbot' ),
        );
    }

    /**
     * Render chatbot HTML
     */
    public function render_chatbot() {
        $chatbot = OFAC_Chatbot::get_instance();

        if ( ! $chatbot->is_enabled() || ! $chatbot->should_display() ) {
            return;
        }

        // Don't render if using shortcode or block
        if ( $this->is_rendered_by_shortcode() ) {
            return;
        }

        $this->render_chatbot_html();
    }

    /**
     * Check if chatbot is rendered by shortcode
     *
     * @return bool
     */
    private function is_rendered_by_shortcode() {
        global $post;

        if ( ! $post ) {
            return false;
        }

        // Check for shortcode
        if ( has_shortcode( $post->post_content, 'ofac_chatbot' ) ) {
            return true;
        }

        // Check for block
        if ( function_exists( 'has_block' ) && has_block( 'ofac/chatbot', $post ) ) {
            return true;
        }

        return false;
    }

    /**
     * Render chatbot HTML structure
     *
     * @param bool $inline Whether this is an inline (shortcode/block) render.
     */
    public function render_chatbot_html( $inline = false ) {
        $consent = OFAC_Consent::get_instance();
        $accessibility = OFAC_Accessibility::get_instance();
        $has_consent = $consent->has_consent();

        $position = $this->settings->get( 'ofac_position', 'bottom-right' );
        $bot_name = $this->settings->get( 'ofac_bot_name', 'Service Client' );
        $welcome_message = $this->settings->get( 'ofac_welcome_message', '' );
        $placeholder = $this->settings->get( 'ofac_placeholder_text', 'Tapez votre message...' );
        $bot_avatar = $this->settings->get( 'ofac_bot_avatar', '' );
        $user_avatar = $this->settings->get( 'ofac_user_avatar', '' );
        $primary_color = $this->settings->get( 'ofac_primary_color', '#2563eb' );
        $width_desktop = $this->settings->get( 'ofac_width_desktop', 400 );
        $height_desktop = $this->settings->get( 'ofac_height_desktop', 600 );

        $bot_avatar_url = $bot_avatar ? wp_get_attachment_url( $bot_avatar ) : '';
        $user_avatar_url = $user_avatar ? wp_get_attachment_url( $user_avatar ) : '';

        $container_class = 'ofac-chatbot';
        if ( $inline ) {
            $container_class .= ' ofac-inline';
        }
        $container_class .= ' ofac-position-' . esc_attr( $position );

        // Apply custom CSS variables
        $style = sprintf(
            '--ofac-primary: %s; --ofac-primary-hover: %s; --ofac-modal-width: %dpx; --ofac-modal-height: %dpx; --ofac-bg-message-user: %s; --ofac-border-focus: %s; --ofac-text-link: %s;',
            esc_attr( $primary_color ),
            esc_attr( $this->adjust_brightness( $primary_color, -20 ) ),
            intval( $width_desktop ),
            intval( $height_desktop ),
            esc_attr( $primary_color ),
            esc_attr( $primary_color ),
            esc_attr( $primary_color )
        );

        include OFAC_PLUGIN_DIR . 'templates/chatbot.php';
    }

    /**
     * Add skip link for accessibility
     */
    public function add_skip_link() {
        $chatbot = OFAC_Chatbot::get_instance();

        if ( ! $chatbot->is_enabled() || ! $chatbot->should_display() ) {
            return;
        }

        if ( ! $this->settings->get( 'show_skip_link' ) ) {
            return;
        }

        $accessibility = OFAC_Accessibility::get_instance();
        echo $accessibility->get_skip_link_html();
    }

    /**
     * Adjust color brightness
     *
     * @param string $hex    Hex color.
     * @param int    $steps  Steps (-255 to 255).
     * @return string
     */
    private function adjust_brightness( $hex, $steps ) {
        $hex = str_replace( '#', '', $hex );

        $r = hexdec( substr( $hex, 0, 2 ) );
        $g = hexdec( substr( $hex, 2, 2 ) );
        $b = hexdec( substr( $hex, 4, 2 ) );

        $r = max( 0, min( 255, $r + $steps ) );
        $g = max( 0, min( 255, $g + $steps ) );
        $b = max( 0, min( 255, $b + $steps ) );

        return '#' . sprintf( '%02x%02x%02x', $r, $g, $b );
    }

    /**
     * Get shortcode instance
     *
     * @return OFAC_Shortcode
     */
    public function get_shortcode() {
        return $this->shortcode;
    }

    /**
     * Get block instance
     *
     * @return OFAC_Block
     */
    public function get_block() {
        return $this->block;
    }
}
