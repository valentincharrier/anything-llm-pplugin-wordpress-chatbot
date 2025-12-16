<?php
/**
 * Internationalization class
 *
 * @package OcadeFusion_AnythingLLM_Chatbot
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class OFAC_i18n
 */
class OFAC_i18n {

    /**
     * Load text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'anythingllm-chatbot',
            false,
            dirname( OFAC_PLUGIN_BASENAME ) . '/languages/'
        );
    }
}
