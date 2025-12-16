<?php
/**
 * Accessibility class
 *
 * @package OcadeFusion_AnythingLLM_Chatbot
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class OFAC_Accessibility
 * Handles RGAA/WCAG compliance
 */
class OFAC_Accessibility {

    /**
     * Single instance
     *
     * @var OFAC_Accessibility|null
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
     * @return OFAC_Accessibility
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
        add_action( 'wp_body_open', array( $this, 'add_skip_link' ), 1 );
        add_filter( 'ofac_chatbot_attributes', array( $this, 'add_aria_attributes' ) );
    }

    /**
     * Add skip link to page
     */
    public function add_skip_link() {
        if ( ! $this->settings->get( 'ofac_show_skip_link', true ) ) {
            return;
        }

        $chatbot = OFAC_Chatbot::get_instance();
        if ( ! $chatbot->should_display() ) {
            return;
        }

        $text = $this->settings->get( 'ofac_skip_link_text', __( 'Aller au chatbot', 'anythingllm-chatbot' ) );

        printf(
            '<a href="#ofac-chatbot" class="ofac-skip-link screen-reader-text" tabindex="0">%s</a>',
            esc_html( $text )
        );
    }

    /**
     * Add ARIA attributes to chatbot
     *
     * @param array $attributes Existing attributes
     * @return array
     */
    public function add_aria_attributes( $attributes ) {
        $attributes['role']            = 'dialog';
        $attributes['aria-modal']      = 'true';
        $attributes['aria-labelledby'] = 'ofac-chatbot-title';
        $attributes['aria-describedby'] = 'ofac-chatbot-description';

        return $attributes;
    }

    /**
     * Get accessibility settings for frontend
     *
     * @return array
     */
    public function get_settings() {
        return array(
            'ariaLive'           => 'polite',
            'focusTrap'          => true,
            'reducedMotion'      => $this->prefers_reduced_motion(),
            'skipLinkEnabled'    => $this->settings->get( 'ofac_show_skip_link', true ),
            'announceNewMessages' => true,
            'keyboardNavigation' => true,
        );
    }

    /**
     * Check if user prefers reduced motion
     *
     * @return bool
     */
    private function prefers_reduced_motion() {
        // This will be checked client-side via JS
        return false;
    }

    /**
     * Get landmark role
     *
     * @return string
     */
    public function get_landmark_role() {
        return 'complementary';
    }

    /**
     * Get focus order elements
     *
     * @return array
     */
    public function get_focus_order() {
        return array(
            'close-button',
            'message-list',
            'input-field',
            'send-button',
            'file-upload',
            'actions',
        );
    }

    /**
     * Generate accessible label
     *
     * @param string $type Label type
     * @param array  $context Additional context
     * @return string
     */
    public function get_label( $type, $context = array() ) {
        $labels = array(
            'open_chat'       => __( 'Ouvrir le chat d\'assistance', 'anythingllm-chatbot' ),
            'close_chat'      => __( 'Fermer le chat', 'anythingllm-chatbot' ),
            'send_message'    => __( 'Envoyer le message', 'anythingllm-chatbot' ),
            'message_input'   => __( 'Tapez votre message', 'anythingllm-chatbot' ),
            'upload_file'     => __( 'Joindre un fichier', 'anythingllm-chatbot' ),
            'copy_message'    => __( 'Copier le message', 'anythingllm-chatbot' ),
            'rate_positive'   => __( 'Réponse utile', 'anythingllm-chatbot' ),
            'rate_negative'   => __( 'Réponse non utile', 'anythingllm-chatbot' ),
            'new_message'     => __( 'Nouveau message de l\'assistant', 'anythingllm-chatbot' ),
            'typing'          => __( 'L\'assistant est en train d\'écrire', 'anythingllm-chatbot' ),
            'error'           => __( 'Erreur lors de l\'envoi du message', 'anythingllm-chatbot' ),
            'message_sent'    => __( 'Message envoyé', 'anythingllm-chatbot' ),
            'file_uploaded'   => __( 'Fichier uploadé', 'anythingllm-chatbot' ),
            'consent_title'   => __( 'Consentement requis', 'anythingllm-chatbot' ),
            'export_chat'     => __( 'Exporter la conversation', 'anythingllm-chatbot' ),
            'reset_chat'      => __( 'Réinitialiser la conversation', 'anythingllm-chatbot' ),
            'message_count'   => __( '%d messages dans la conversation', 'anythingllm-chatbot' ),
        );

        /**
         * Filter accessibility labels
         *
         * @since 1.0.0
         * @param array $labels All labels
         */
        $labels = apply_filters( 'ofac_accessibility_labels', $labels );

        if ( isset( $labels[ $type ] ) ) {
            $label = $labels[ $type ];

            // Handle placeholders
            if ( ! empty( $context ) && strpos( $label, '%' ) !== false ) {
                $label = vsprintf( $label, $context );
            }

            return $label;
        }

        return '';
    }

    /**
     * Generate screen reader text
     *
     * @param string $text Text content
     * @return string
     */
    public function sr_only( $text ) {
        return sprintf( '<span class="screen-reader-text">%s</span>', esc_html( $text ) );
    }

    /**
     * Check color contrast ratio
     *
     * @param string $color1 First color (hex)
     * @param string $color2 Second color (hex)
     * @return float
     */
    public function get_contrast_ratio( $color1, $color2 ) {
        $l1 = $this->get_luminance( $color1 );
        $l2 = $this->get_luminance( $color2 );

        $lighter = max( $l1, $l2 );
        $darker  = min( $l1, $l2 );

        return ( $lighter + 0.05 ) / ( $darker + 0.05 );
    }

    /**
     * Get relative luminance of a color
     *
     * @param string $hex Hex color
     * @return float
     */
    private function get_luminance( $hex ) {
        $hex = ltrim( $hex, '#' );

        $r = hexdec( substr( $hex, 0, 2 ) ) / 255;
        $g = hexdec( substr( $hex, 2, 2 ) ) / 255;
        $b = hexdec( substr( $hex, 4, 2 ) ) / 255;

        $r = $r <= 0.03928 ? $r / 12.92 : pow( ( $r + 0.055 ) / 1.055, 2.4 );
        $g = $g <= 0.03928 ? $g / 12.92 : pow( ( $g + 0.055 ) / 1.055, 2.4 );
        $b = $b <= 0.03928 ? $b / 12.92 : pow( ( $b + 0.055 ) / 1.055, 2.4 );

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    /**
     * Check if contrast meets WCAG AA
     *
     * @param string $foreground Foreground color
     * @param string $background Background color
     * @param string $size Text size ('normal' or 'large')
     * @return bool
     */
    public function meets_contrast_aa( $foreground, $background, $size = 'normal' ) {
        $ratio    = $this->get_contrast_ratio( $foreground, $background );
        $required = $size === 'large' ? 3.0 : 4.5;

        return $ratio >= $required;
    }
}
