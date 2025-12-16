<?php
/**
 * Shortcode functionality
 *
 * @package Ocade_Fusion_AnythingLLM_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class OFAC_Shortcode
 */
class OFAC_Shortcode {

    /**
     * Constructor
     */
    public function __construct() {
        add_shortcode( 'ofac_chatbot', array( $this, 'render' ) );
    }

    /**
     * Render shortcode
     *
     * @param array  $atts    Shortcode attributes.
     * @param string $content Shortcode content.
     * @return string
     */
    public function render( $atts = array(), $content = '' ) {
        $chatbot = OFAC_Chatbot::get_instance();

        if ( ! $chatbot->is_enabled() ) {
            return '';
        }

        // Parse attributes
        $atts = shortcode_atts( array(
            'position' => '',
            'width'    => '',
            'height'   => '',
            'class'    => '',
        ), $atts, 'ofac_chatbot' );

        // Enqueue assets
        $this->enqueue_assets();

        // Build wrapper attributes
        $wrapper_class = 'ofac-shortcode-wrapper';
        if ( ! empty( $atts['class'] ) ) {
            $wrapper_class .= ' ' . sanitize_html_class( $atts['class'] );
        }

        $wrapper_style = '';
        if ( ! empty( $atts['width'] ) ) {
            $wrapper_style .= 'width: ' . esc_attr( $atts['width'] ) . ';';
        }
        if ( ! empty( $atts['height'] ) ) {
            $wrapper_style .= 'height: ' . esc_attr( $atts['height'] ) . ';';
        }

        // Start output buffering
        ob_start();

        echo '<div class="' . esc_attr( $wrapper_class ) . '"';
        if ( $wrapper_style ) {
            echo ' style="' . esc_attr( $wrapper_style ) . '"';
        }
        echo '>';

        // Render chatbot
        OFAC_Public::get_instance()->render_chatbot_html( true );

        echo '</div>';

        return ob_get_clean();
    }

    /**
     * Enqueue assets for shortcode
     */
    private function enqueue_assets() {
        $settings = OFAC_Settings::get_instance();

        // Always load assets when shortcode is used
        wp_enqueue_style( 'ofac-chatbot' );
        wp_enqueue_style( 'ofac-prism' );
        wp_enqueue_script( 'ofac-marked' );
        wp_enqueue_script( 'ofac-prism' );
        wp_enqueue_script( 'ofac-chatbot' );
    }
}
