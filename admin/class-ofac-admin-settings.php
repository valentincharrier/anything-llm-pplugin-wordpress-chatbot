<?php
/**
 * Admin Settings Page
 *
 * @package Ocade_Fusion_AnythingLLM_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class OFAC_Admin_Settings
 */
class OFAC_Admin_Settings {

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
        add_action( 'wp_ajax_ofac_test_connection', array( $this, 'ajax_test_connection' ) );
        add_action( 'wp_ajax_ofac_export_settings', array( $this, 'ajax_export_settings' ) );
        add_action( 'wp_ajax_ofac_import_settings', array( $this, 'ajax_import_settings' ) );
        add_action( 'wp_ajax_ofac_reset_settings', array( $this, 'ajax_reset_settings' ) );
    }

    /**
     * Render settings page
     */
    public function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Save settings if form submitted
        if ( isset( $_POST['ofac_settings_nonce'] ) && wp_verify_nonce( $_POST['ofac_settings_nonce'], 'ofac_save_settings' ) ) {
            $this->save_settings( $_POST );
        }

        $schema = $this->settings->get_schema();
        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'api';
        ?>
        <div class="wrap ofac-admin-wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

            <div class="ofac-admin-container">
                <div class="ofac-admin-main">
                    <nav class="nav-tab-wrapper ofac-tabs">
                        <?php foreach ( $schema as $section_id => $section ) : ?>
                            <a href="?page=ofac-settings&tab=<?php echo esc_attr( $section_id ); ?>" 
                               class="nav-tab <?php echo $active_tab === $section_id ? 'nav-tab-active' : ''; ?>">
                                <?php echo esc_html( $section['title'] ); ?>
                            </a>
                        <?php endforeach; ?>
                    </nav>

                    <form method="post" action="" class="ofac-settings-form">
                        <?php wp_nonce_field( 'ofac_save_settings', 'ofac_settings_nonce' ); ?>
                        <input type="hidden" name="ofac_active_tab" value="<?php echo esc_attr( $active_tab ); ?>">

                        <?php foreach ( $schema as $section_id => $section ) : ?>
                            <div class="ofac-tab-content" id="tab-<?php echo esc_attr( $section_id ); ?>" 
                                 style="<?php echo $active_tab !== $section_id ? 'display:none;' : ''; ?>">
                                
                                <?php if ( ! empty( $section['description'] ) ) : ?>
                                    <p class="description"><?php echo esc_html( $section['description'] ); ?></p>
                                <?php endif; ?>

                                <table class="form-table">
                                    <?php 
                                    if ( isset( $section['fields'] ) && is_array( $section['fields'] ) ) :
                                        foreach ( $section['fields'] as $field_id => $field ) : 
                                    ?>
                                        <tr>
                                            <th scope="row">
                                                <label for="<?php echo esc_attr( $field_id ); ?>">
                                                    <?php echo esc_html( isset( $field['label'] ) ? $field['label'] : $field_id ); ?>
                                                </label>
                                            </th>
                                            <td>
                                                <?php $this->render_field( $field_id, $field ); ?>
                                                <?php if ( ! empty( $field['description'] ) ) : ?>
                                                    <p class="description"><?php echo esc_html( $field['description'] ); ?></p>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php 
                                        endforeach;
                                    else :
                                    ?>
                                        <tr>
                                            <td colspan="2">
                                                <p class="description"><?php esc_html_e( 'Aucun champ configuré pour cette section.', 'anythingllm-chatbot' ); ?></p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </table>

                                <?php if ( $section_id === 'api' ) : ?>
                                    <p>
                                        <button type="button" class="button" id="ofac-test-connection">
                                            <?php esc_html_e( 'Tester la connexion', 'anythingllm-chatbot' ); ?>
                                        </button>
                                        <span id="ofac-connection-result"></span>
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                        <p class="submit">
                            <button type="submit" class="button button-primary">
                                <?php esc_html_e( 'Enregistrer les modifications', 'anythingllm-chatbot' ); ?>
                            </button>
                            <button type="button" class="button" id="ofac-export-settings">
                                <?php esc_html_e( 'Exporter', 'anythingllm-chatbot' ); ?>
                            </button>
                            <button type="button" class="button" id="ofac-import-settings">
                                <?php esc_html_e( 'Importer', 'anythingllm-chatbot' ); ?>
                            </button>
                            <button type="button" class="button" id="ofac-reset-settings">
                                <?php esc_html_e( 'Réinitialiser', 'anythingllm-chatbot' ); ?>
                            </button>
                        </p>
                    </form>

                    <input type="file" id="ofac-import-file" accept=".json" style="display:none;">
                </div>

                <div class="ofac-admin-sidebar">
                    <div class="ofac-preview-container">
                        <h3><?php esc_html_e( 'Aperçu', 'anythingllm-chatbot' ); ?></h3>
                        <div class="ofac-preview-wrapper">
                            <div class="ofac-preview" id="ofac-live-preview">
                                <?php $this->render_preview(); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render a form field
     *
     * @param string $field_id Field ID.
     * @param array  $field    Field config.
     */
    private function render_field( $field_id, $field ) {
        $value = $this->settings->get( $field_id );
        $type = isset( $field['type'] ) ? $field['type'] : 'text';
        $default = isset( $field['default'] ) ? $field['default'] : '';
        
        // Ensure value is never null
        if ( null === $value ) {
            $value = $default;
        }
        
        $id = $field_id;
        $name = $field_id;

        switch ( $type ) {
            case 'text':
            case 'url':
            case 'email':
            case 'number':
                $input_type = $type === 'number' ? 'number' : 'text';
                $extra_attrs = '';
                if ( $type === 'number' ) {
                    if ( isset( $field['min'] ) ) {
                        $extra_attrs .= ' min="' . esc_attr( $field['min'] ) . '"';
                    }
                    if ( isset( $field['max'] ) ) {
                        $extra_attrs .= ' max="' . esc_attr( $field['max'] ) . '"';
                    }
                }
                printf(
                    '<input type="%s" id="%s" name="%s" value="%s" class="regular-text ofac-field" data-field="%s"%s>',
                    esc_attr( $input_type ),
                    esc_attr( $id ),
                    esc_attr( $name ),
                    esc_attr( (string) $value ),
                    esc_attr( $field_id ),
                    $extra_attrs
                );
                break;

            case 'password':
                printf(
                    '<input type="password" id="%s" name="%s" value="%s" class="regular-text ofac-field" data-field="%s" autocomplete="new-password">',
                    esc_attr( $id ),
                    esc_attr( $name ),
                    esc_attr( (string) $value ),
                    esc_attr( $field_id )
                );
                break;

            case 'textarea':
                printf(
                    '<textarea id="%s" name="%s" rows="5" class="large-text ofac-field" data-field="%s">%s</textarea>',
                    esc_attr( $id ),
                    esc_attr( $name ),
                    esc_attr( $field_id ),
                    esc_textarea( (string) $value )
                );
                break;

            case 'checkbox':
                printf(
                    '<label><input type="checkbox" id="%s" name="%s" value="1" class="ofac-field" data-field="%s" %s> %s</label>',
                    esc_attr( $id ),
                    esc_attr( $name ),
                    esc_attr( $field_id ),
                    checked( $value, true, false ),
                    esc_html__( 'Activer', 'anythingllm-chatbot' )
                );
                break;

            case 'select':
                $options = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array();
                printf( '<select id="%s" name="%s" class="ofac-field" data-field="%s">', esc_attr( $id ), esc_attr( $name ), esc_attr( $field_id ) );
                foreach ( $options as $option_value => $option_label ) {
                    printf(
                        '<option value="%s" %s>%s</option>',
                        esc_attr( $option_value ),
                        selected( $value, $option_value, false ),
                        esc_html( $option_label )
                    );
                }
                echo '</select>';
                break;

            case 'multiselect':
                $value = is_array( $value ) ? $value : array();
                $options = $this->get_field_options( $field );
                printf( '<select id="%s" name="%s[]" class="ofac-field" data-field="%s" multiple style="min-height:100px;">', esc_attr( $id ), esc_attr( $name ), esc_attr( $field_id ) );
                foreach ( $options as $option_value => $option_label ) {
                    printf(
                        '<option value="%s" %s>%s</option>',
                        esc_attr( $option_value ),
                        in_array( $option_value, $value, true ) ? 'selected' : '',
                        esc_html( $option_label )
                    );
                }
                echo '</select>';
                break;

            case 'color':
                printf(
                    '<input type="color" id="%s" name="%s" value="%s" class="ofac-color-picker ofac-field" data-field="%s">',
                    esc_attr( $id ),
                    esc_attr( $name ),
                    esc_attr( (string) $value ),
                    esc_attr( $field_id )
                );
                break;

            case 'media':
            case 'image':
                $image_url = $value ? wp_get_attachment_url( $value ) : '';
                ?>
                <div class="ofac-media-field">
                    <input type="hidden" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ? $value : '' ); ?>" class="ofac-field" data-field="<?php echo esc_attr( $field_id ); ?>">
                    <div class="ofac-media-preview" style="margin-bottom:10px;">
                        <?php if ( $image_url ) : ?>
                            <img src="<?php echo esc_url( $image_url ); ?>" style="max-width:150px;max-height:150px;">
                        <?php endif; ?>
                    </div>
                    <button type="button" class="button ofac-media-upload"><?php esc_html_e( 'Choisir une image', 'anythingllm-chatbot' ); ?></button>
                    <button type="button" class="button ofac-media-remove" <?php echo ! $value ? 'style="display:none;"' : ''; ?>><?php esc_html_e( 'Supprimer', 'anythingllm-chatbot' ); ?></button>
                </div>
                <?php
                break;

            default:
                printf(
                    '<input type="text" id="%s" name="%s" value="%s" class="regular-text ofac-field" data-field="%s">',
                    esc_attr( $id ),
                    esc_attr( $name ),
                    esc_attr( (string) $value ),
                    esc_attr( $field_id )
                );
                break;
        }
    }

    /**
     * Get field options (handles special cases like wp_roles)
     *
     * @param array $field Field config.
     * @return array
     */
    private function get_field_options( $field ) {
        if ( ! isset( $field['options'] ) ) {
            return array();
        }

        // Special case: WordPress roles
        if ( $field['options'] === 'wp_roles' ) {
            $roles = array();
            $wp_roles = wp_roles();
            foreach ( $wp_roles->roles as $role_key => $role ) {
                $roles[ $role_key ] = $role['name'];
            }
            return $roles;
        }

        // Regular array options
        if ( is_array( $field['options'] ) ) {
            return $field['options'];
        }

        return array();
    }

    /**
     * Render chatbot preview
     */
    private function render_preview() {
        $settings = $this->settings;
        $primary_color = $settings->get( 'ofac_primary_color', '#0073aa' );
        $position = $settings->get( 'ofac_position', 'bottom-right' );
        $welcome_message = $settings->get( 'ofac_welcome_message', 'Bonjour ! Comment puis-je vous aider ?' );
        $placeholder = $settings->get( 'ofac_placeholder_text', 'Tapez votre message...' );
        ?>
        <div class="ofac-preview-container">
            <div class="ofac-preview-chat" style="--ofac-primary: <?php echo esc_attr( $primary_color ); ?>;">
                <div class="ofac-preview-header">
                    <span class="ofac-preview-title">💬 Service Client</span>
                    <span class="ofac-preview-close">×</span>
                </div>
                <div class="ofac-preview-body">
                    <div class="ofac-preview-message ofac-preview-bot">
                        <div class="ofac-preview-avatar">🤖</div>
                        <div class="ofac-preview-bubble"><?php echo esc_html( $welcome_message ?: 'Bonjour ! Comment puis-je vous aider ?' ); ?></div>
                    </div>
                    <div class="ofac-preview-message ofac-preview-user">
                        <div class="ofac-preview-bubble"><?php esc_html_e( 'Bonjour !', 'anythingllm-chatbot' ); ?></div>
                        <div class="ofac-preview-avatar">👤</div>
                    </div>
                    <div class="ofac-preview-message ofac-preview-bot">
                        <div class="ofac-preview-avatar">🤖</div>
                        <div class="ofac-preview-bubble ofac-preview-typing">
                            <span></span><span></span><span></span>
                        </div>
                    </div>
                </div>
                <div class="ofac-preview-footer">
                    <input type="text" placeholder="<?php echo esc_attr( $placeholder ?: 'Tapez votre message...' ); ?>" disabled>
                    <button type="button" disabled style="background: <?php echo esc_attr( $primary_color ); ?>;">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="white"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                    </button>
                </div>
            </div>
            <div class="ofac-preview-trigger" style="background: <?php echo esc_attr( $primary_color ); ?>;">
                <svg viewBox="0 0 24 24" width="28" height="28" fill="white"><path d="M12 3c5.5 0 10 3.58 10 8s-4.5 8-10 8c-1.24 0-2.43-.18-3.53-.5C5.55 21 2 21 2 21c2.33-2.33 2.7-3.9 2.75-4.5C3.05 15.07 2 13.13 2 11c0-4.42 4.5-8 10-8z"/></svg>
            </div>
        </div>
        <?php
    }

    /**
     * Get CSS for button position
     *
     * @param string $position Position value.
     * @return string
     */
    private function get_position_style( $position ) {
        $positions = array(
            'bottom-right' => 'bottom: 20px; right: 20px;',
            'bottom-left'  => 'bottom: 20px; left: 20px;',
            'top-right'    => 'top: 20px; right: 20px;',
            'top-left'     => 'top: 20px; left: 20px;',
        );

        return isset( $positions[ $position ] ) ? $positions[ $position ] : $positions['bottom-right'];
    }

    /**
     * Save settings
     *
     * @param array $data Posted data.
     */
    private function save_settings( $data ) {
        // Remove slashes added by WordPress
        $data = wp_unslash( $data );
        
        $schema = $this->settings->get_schema();
        $settings = array();

        foreach ( $schema as $section ) {
            if ( ! isset( $section['fields'] ) || ! is_array( $section['fields'] ) ) {
                continue;
            }
            foreach ( $section['fields'] as $field_id => $field ) {
                $type = isset( $field['type'] ) ? $field['type'] : 'text';
                
                if ( isset( $data[ $field_id ] ) ) {
                    $settings[ $field_id ] = $data[ $field_id ];
                } elseif ( $type === 'checkbox' ) {
                    $settings[ $field_id ] = false;
                } elseif ( $type === 'multiselect' ) {
                    $settings[ $field_id ] = array();
                }
            }
        }

        foreach ( $settings as $key => $value ) {
            $this->settings->set( $key, $value );
        }

        add_settings_error(
            'ofac_settings',
            'settings_updated',
            __( 'Réglages enregistrés.', 'anythingllm-chatbot' ),
            'success'
        );
    }

    /**
     * AJAX: Test API connection
     */
    public function ajax_test_connection() {
        check_ajax_referer( 'ofac_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission refusée.', 'anythingllm-chatbot' ) ) );
        }

        $api = OFAC_API::get_instance();
        $result = $api->test_connection();

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( $result );
    }

    /**
     * AJAX: Export settings
     */
    public function ajax_export_settings() {
        check_ajax_referer( 'ofac_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission refusée.', 'anythingllm-chatbot' ) );
        }

        $export = $this->settings->export();

        wp_send_json_success( array(
            'data'     => $export,
            'filename' => 'ofac-settings-' . date( 'Y-m-d' ) . '.json',
        ) );
    }

    /**
     * AJAX: Import settings
     */
    public function ajax_import_settings() {
        check_ajax_referer( 'ofac_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission refusée.', 'anythingllm-chatbot' ) );
        }

        $json = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : '';

        if ( empty( $json ) ) {
            wp_send_json_error( __( 'Données invalides.', 'anythingllm-chatbot' ) );
        }

        $result = $this->settings->import( $json );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success( __( 'Réglages importés avec succès.', 'anythingllm-chatbot' ) );
    }

    /**
     * AJAX: Reset settings
     */
    public function ajax_reset_settings() {
        check_ajax_referer( 'ofac_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission refusée.', 'anythingllm-chatbot' ) );
        }

        delete_option( 'ofac_settings' );

        wp_send_json_success( __( 'Réglages réinitialisés.', 'anythingllm-chatbot' ) );
    }
}
