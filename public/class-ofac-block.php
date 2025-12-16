<?php
/**
 * Gutenberg Block functionality
 *
 * @package Ocade_Fusion_AnythingLLM_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class OFAC_Block
 */
class OFAC_Block {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'init', array( $this, 'register_block' ) );
        add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
    }

    /**
     * Register Gutenberg block
     */
    public function register_block() {
        if ( ! function_exists( 'register_block_type' ) ) {
            return;
        }

        // Prevent double registration
        if ( WP_Block_Type_Registry::get_instance()->is_registered( 'ofac/chatbot' ) ) {
            return;
        }

        register_block_type( 'ofac/chatbot', array(
            'editor_script'   => 'ofac-block-editor',
            'editor_style'    => 'ofac-block-editor-style',
            'render_callback' => array( $this, 'render' ),
            'attributes'      => array(
                'width' => array(
                    'type'    => 'string',
                    'default' => '',
                ),
                'height' => array(
                    'type'    => 'string',
                    'default' => '',
                ),
                'className' => array(
                    'type'    => 'string',
                    'default' => '',
                ),
            ),
        ) );
    }

    /**
     * Enqueue block editor assets
     */
    public function enqueue_editor_assets() {
        wp_enqueue_script(
            'ofac-block-editor',
            OFAC_PLUGIN_URL . 'assets/js/block-editor.js',
            array( 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n' ),
            OFAC_VERSION,
            true
        );

        wp_enqueue_style(
            'ofac-block-editor-style',
            OFAC_PLUGIN_URL . 'assets/css/block-editor.css',
            array(),
            OFAC_VERSION
        );

        wp_localize_script( 'ofac-block-editor', 'ofacBlockConfig', array(
            'title'       => __( 'AnythingLLM Chatbot', 'anythingllm-chatbot' ),
            'description' => __( 'Ajoute un chatbot IA propulsé par AnythingLLM.', 'anythingllm-chatbot' ),
            'icon'        => 'format-chat',
            'category'    => 'widgets',
            'keywords'    => array(
                __( 'chat', 'anythingllm-chatbot' ),
                __( 'bot', 'anythingllm-chatbot' ),
                __( 'ia', 'anythingllm-chatbot' ),
                __( 'assistant', 'anythingllm-chatbot' ),
            ),
            'labels'      => array(
                'width'   => __( 'Largeur', 'anythingllm-chatbot' ),
                'height'  => __( 'Hauteur', 'anythingllm-chatbot' ),
                'preview' => __( 'Aperçu du chatbot', 'anythingllm-chatbot' ),
                'info'    => __( 'Le chatbot s\'affichera ici en mode frontal.', 'anythingllm-chatbot' ),
            ),
        ) );
    }

    /**
     * Render block
     *
     * @param array $attributes Block attributes.
     * @return string
     */
    public function render( $attributes ) {
        $chatbot = OFAC_Chatbot::get_instance();

        if ( ! $chatbot->is_enabled() ) {
            return '';
        }

        // Enqueue assets
        $this->enqueue_assets();

        // Build wrapper attributes
        $wrapper_class = 'ofac-block-wrapper';
        if ( ! empty( $attributes['className'] ) ) {
            $wrapper_class .= ' ' . sanitize_html_class( $attributes['className'] );
        }

        $wrapper_style = '';
        if ( ! empty( $attributes['width'] ) ) {
            $wrapper_style .= 'width: ' . esc_attr( $attributes['width'] ) . ';';
        }
        if ( ! empty( $attributes['height'] ) ) {
            $wrapper_style .= 'height: ' . esc_attr( $attributes['height'] ) . ';';
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
     * Enqueue assets for block
     */
    private function enqueue_assets() {
        wp_enqueue_style( 'ofac-chatbot' );
        wp_enqueue_style( 'ofac-prism' );
        wp_enqueue_script( 'ofac-marked' );
        wp_enqueue_script( 'ofac-prism' );
        wp_enqueue_script( 'ofac-chatbot' );
    }
}
