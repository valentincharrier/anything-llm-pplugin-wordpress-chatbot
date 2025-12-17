<?php
/**
 * Settings management class
 *
 * @package OcadeFusion_AnythingLLM_Chatbot
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class OFAC_Settings
 * Handles all plugin settings
 */
class OFAC_Settings {

    /**
     * Single instance
     *
     * @var OFAC_Settings|null
     */
    private static $instance = null;

    /**
     * Cached settings
     *
     * @var array
     */
    private $settings = array();

    /**
     * Settings schema
     *
     * @var array|null
     */
    private $schema = null;

    /**
     * Get single instance
     *
     * @return OFAC_Settings
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
        $this->load_settings();
    }

    /**
     * Get schema (lazy loading)
     *
     * @return array
     */
    public function get_schema() {
        if ( null === $this->schema ) {
            $this->define_schema();
        }
        return $this->schema;
    }

    /**
     * Define settings schema
     */
    private function define_schema() {
        $this->schema = array(
            // API Settings
            'api' => array(
                'title'  => __( 'Configuration API', 'anythingllm-chatbot' ),
                'fields' => array(
                    'ofac_api_url' => array(
                        'type'        => 'url',
                        'label'       => __( 'URL de l\'instance AnythingLLM', 'anythingllm-chatbot' ),
                        'description' => __( 'L\'URL complète de votre instance AnythingLLM (ex: https://anything-llm.example.com)', 'anythingllm-chatbot' ),
                        'default'     => '',
                        'required'    => true,
                    ),
                    'ofac_api_key' => array(
                        'type'        => 'password',
                        'label'       => __( 'Clé API', 'anythingllm-chatbot' ),
                        'description' => __( 'Votre clé API AnythingLLM', 'anythingllm-chatbot' ),
                        'default'     => '',
                        'required'    => true,
                    ),
                    'ofac_workspace_slug' => array(
                        'type'        => 'text',
                        'label'       => __( 'Slug du Workspace', 'anythingllm-chatbot' ),
                        'description' => __( 'Le slug du workspace à utiliser pour les conversations', 'anythingllm-chatbot' ),
                        'default'     => '',
                        'required'    => true,
                    ),
                    'ofac_timeout' => array(
                        'type'        => 'number',
                        'label'       => __( 'Timeout (secondes)', 'anythingllm-chatbot' ),
                        'description' => __( 'Délai d\'attente maximum pour les requêtes API', 'anythingllm-chatbot' ),
                        'default'     => 60,
                        'min'         => 10,
                        'max'         => 300,
                    ),
                ),
            ),
            // General Settings
            'general' => array(
                'title'  => __( 'Paramètres généraux', 'anythingllm-chatbot' ),
                'fields' => array(
                    'ofac_enabled' => array(
                        'type'        => 'checkbox',
                        'label'       => __( 'Activer le chatbot', 'anythingllm-chatbot' ),
                        'description' => __( 'Afficher le chatbot sur le site', 'anythingllm-chatbot' ),
                        'default'     => false,
                    ),
                    'ofac_position' => array(
                        'type'        => 'select',
                        'label'       => __( 'Position du bouton', 'anythingllm-chatbot' ),
                        'description' => __( 'Position du bouton d\'ouverture du chatbot', 'anythingllm-chatbot' ),
                        'default'     => 'bottom-right',
                        'options'     => array(
                            'bottom-right' => __( 'Bas droite', 'anythingllm-chatbot' ),
                            'bottom-left'  => __( 'Bas gauche', 'anythingllm-chatbot' ),
                            'top-right'    => __( 'Haut droite', 'anythingllm-chatbot' ),
                            'top-left'     => __( 'Haut gauche', 'anythingllm-chatbot' ),
                        ),
                    ),
                    'ofac_allowed_roles' => array(
                        'type'        => 'multiselect',
                        'label'       => __( 'Rôles autorisés', 'anythingllm-chatbot' ),
                        'description' => __( 'Laisser vide pour autoriser tous les visiteurs', 'anythingllm-chatbot' ),
                        'default'     => array(),
                        'options'     => 'wp_roles',
                    ),
                ),
            ),
            // Appearance Settings
            'appearance' => array(
                'title'  => __( 'Apparence', 'anythingllm-chatbot' ),
                'fields' => array(
                    'ofac_width_desktop' => array(
                        'type'        => 'number',
                        'label'       => __( 'Largeur (desktop)', 'anythingllm-chatbot' ),
                        'description' => __( 'Largeur de la fenêtre en pixels', 'anythingllm-chatbot' ),
                        'default'     => 400,
                        'min'         => 300,
                        'max'         => 800,
                    ),
                    'ofac_height_desktop' => array(
                        'type'        => 'number',
                        'label'       => __( 'Hauteur (desktop)', 'anythingllm-chatbot' ),
                        'description' => __( 'Hauteur de la fenêtre en pixels', 'anythingllm-chatbot' ),
                        'default'     => 600,
                        'min'         => 400,
                        'max'         => 900,
                    ),
                    'ofac_theme_mode' => array(
                        'type'        => 'select',
                        'label'       => __( 'Mode de thème', 'anythingllm-chatbot' ),
                        'description' => __( 'Thème clair/sombre', 'anythingllm-chatbot' ),
                        'default'     => 'auto',
                        'options'     => array(
                            'auto'  => __( 'Automatique (selon le site)', 'anythingllm-chatbot' ),
                            'light' => __( 'Clair', 'anythingllm-chatbot' ),
                            'dark'  => __( 'Sombre', 'anythingllm-chatbot' ),
                        ),
                    ),
                    'ofac_primary_color' => array(
                        'type'        => 'color',
                        'label'       => __( 'Couleur principale', 'anythingllm-chatbot' ),
                        'description' => __( 'Couleur principale du chatbot', 'anythingllm-chatbot' ),
                        'default'     => '#0073aa',
                    ),
                    'ofac_bot_avatar' => array(
                        'type'        => 'image',
                        'label'       => __( 'Avatar du bot', 'anythingllm-chatbot' ),
                        'description' => __( 'Image avatar pour les messages du bot', 'anythingllm-chatbot' ),
                        'default'     => '',
                    ),
                    'ofac_user_avatar' => array(
                        'type'        => 'image',
                        'label'       => __( 'Avatar utilisateur', 'anythingllm-chatbot' ),
                        'description' => __( 'Image avatar pour les messages utilisateur', 'anythingllm-chatbot' ),
                        'default'     => '',
                    ),
                ),
            ),
            // Messages Settings
            'messages' => array(
                'title'  => __( 'Messages', 'anythingllm-chatbot' ),
                'fields' => array(
                    'ofac_bot_name' => array(
                        'type'        => 'text',
                        'label'       => __( 'Nom du chatbot', 'anythingllm-chatbot' ),
                        'description' => __( 'Nom affiché dans l\'en-tête du chat', 'anythingllm-chatbot' ),
                        'default'     => __( 'Service Client', 'anythingllm-chatbot' ),
                    ),
                    'ofac_welcome_message' => array(
                        'type'        => 'textarea',
                        'label'       => __( 'Message d\'accueil', 'anythingllm-chatbot' ),
                        'description' => __( 'Message affiché à l\'ouverture du chat', 'anythingllm-chatbot' ),
                        'default'     => __( 'Bonjour ! Comment puis-je vous aider ?', 'anythingllm-chatbot' ),
                    ),
                    'ofac_fallback_message' => array(
                        'type'        => 'textarea',
                        'label'       => __( 'Message d\'erreur', 'anythingllm-chatbot' ),
                        'description' => __( 'Message affiché en cas d\'erreur API', 'anythingllm-chatbot' ),
                        'default'     => __( 'Désolé, je ne suis pas disponible actuellement. Veuillez réessayer plus tard.', 'anythingllm-chatbot' ),
                    ),
                    'ofac_placeholder_text' => array(
                        'type'        => 'text',
                        'label'       => __( 'Placeholder', 'anythingllm-chatbot' ),
                        'description' => __( 'Texte placeholder du champ de saisie', 'anythingllm-chatbot' ),
                        'default'     => __( 'Tapez votre message...', 'anythingllm-chatbot' ),
                    ),
                    'ofac_max_chars' => array(
                        'type'        => 'number',
                        'label'       => __( 'Limite de caractères', 'anythingllm-chatbot' ),
                        'description' => __( 'Nombre maximum de caractères par message', 'anythingllm-chatbot' ),
                        'default'     => 5000,
                        'min'         => 100,
                        'max'         => 10000,
                    ),
                ),
            ),
            // File Upload Settings
            'files' => array(
                'title'  => __( 'Upload de fichiers', 'anythingllm-chatbot' ),
                'fields' => array(
                    'ofac_enable_file_upload' => array(
                        'type'        => 'checkbox',
                        'label'       => __( 'Autoriser l\'upload', 'anythingllm-chatbot' ),
                        'description' => __( 'Permettre aux utilisateurs d\'envoyer des fichiers', 'anythingllm-chatbot' ),
                        'default'     => true,
                    ),
                    'ofac_max_file_size' => array(
                        'type'        => 'number',
                        'label'       => __( 'Taille max (Mo)', 'anythingllm-chatbot' ),
                        'description' => __( 'Taille maximale des fichiers uploadés', 'anythingllm-chatbot' ),
                        'default'     => 5,
                        'min'         => 1,
                        'max'         => 50,
                    ),
                    'ofac_allowed_file_types' => array(
                        'type'        => 'text',
                        'label'       => __( 'Types autorisés', 'anythingllm-chatbot' ),
                        'description' => __( 'Extensions autorisées, séparées par des virgules', 'anythingllm-chatbot' ),
                        'default'     => 'jpg,jpeg,png,gif,pdf,doc,docx,txt',
                    ),
                ),
            ),
            // Security Settings
            'security' => array(
                'title'  => __( 'Sécurité', 'anythingllm-chatbot' ),
                'fields' => array(
                    'ofac_rate_limit' => array(
                        'type'        => 'number',
                        'label'       => __( 'Limite de requêtes', 'anythingllm-chatbot' ),
                        'description' => __( 'Nombre maximum de requêtes par minute par IP', 'anythingllm-chatbot' ),
                        'default'     => 10,
                        'min'         => 5,
                        'max'         => 100,
                    ),
                    'ofac_enable_honeypot' => array(
                        'type'        => 'checkbox',
                        'label'       => __( 'Protection honeypot', 'anythingllm-chatbot' ),
                        'description' => __( 'Activer la protection anti-spam honeypot', 'anythingllm-chatbot' ),
                        'default'     => true,
                    ),
                ),
            ),
            // Privacy Settings
            'privacy' => array(
                'title'  => __( 'Confidentialité (RGPD)', 'anythingllm-chatbot' ),
                'fields' => array(
                    'ofac_require_consent' => array(
                        'type'        => 'checkbox',
                        'label'       => __( 'Demander le consentement', 'anythingllm-chatbot' ),
                        'description' => __( 'Demander le consentement de l\'utilisateur avant d\'utiliser le chatbot (recommandé pour la conformité RGPD)', 'anythingllm-chatbot' ),
                        'default'     => true,
                    ),
                    'ofac_data_retention_days' => array(
                        'type'        => 'number',
                        'label'       => __( 'Rétention des données (jours)', 'anythingllm-chatbot' ),
                        'description' => __( 'Durée de conservation des conversations', 'anythingllm-chatbot' ),
                        'default'     => 10,
                        'min'         => 1,
                        'max'         => 365,
                    ),
                    'ofac_privacy_policy_url' => array(
                        'type'        => 'url',
                        'label'       => __( 'URL Politique de confidentialité', 'anythingllm-chatbot' ),
                        'description' => __( 'Lien vers votre politique de confidentialité', 'anythingllm-chatbot' ),
                        'default'     => '',
                    ),
                    'ofac_consent_text' => array(
                        'type'        => 'textarea',
                        'label'       => __( 'Texte de consentement', 'anythingllm-chatbot' ),
                        'description' => __( 'Texte affiché avant la première utilisation', 'anythingllm-chatbot' ),
                        'default'     => __( 'En utilisant ce chatbot, vous acceptez que vos messages soient traités pour vous fournir une assistance. Vos données sont conservées pendant 30 jours.', 'anythingllm-chatbot' ),
                    ),
                ),
            ),
            // Accessibility Settings
            'accessibility' => array(
                'title'  => __( 'Accessibilité', 'anythingllm-chatbot' ),
                'fields' => array(
                    'ofac_show_skip_link' => array(
                        'type'        => 'checkbox',
                        'label'       => __( 'Lien d\'accès rapide', 'anythingllm-chatbot' ),
                        'description' => __( 'Ajouter un lien dans les skip-links', 'anythingllm-chatbot' ),
                        'default'     => true,
                    ),
                    'ofac_skip_link_text' => array(
                        'type'        => 'text',
                        'label'       => __( 'Texte du lien d\'accès', 'anythingllm-chatbot' ),
                        'description' => __( 'Texte pour le lien d\'accès rapide', 'anythingllm-chatbot' ),
                        'default'     => __( 'Aller au chatbot', 'anythingllm-chatbot' ),
                    ),
                ),
            ),
            // Cache Settings
            'cache' => array(
                'title'  => __( 'Cache', 'anythingllm-chatbot' ),
                'fields' => array(
                    'ofac_enable_cache' => array(
                        'type'        => 'checkbox',
                        'label'       => __( 'Activer le cache', 'anythingllm-chatbot' ),
                        'description' => __( 'Mettre en cache les réponses fréquentes', 'anythingllm-chatbot' ),
                        'default'     => true,
                    ),
                    'ofac_cache_duration' => array(
                        'type'        => 'number',
                        'label'       => __( 'Durée du cache (secondes)', 'anythingllm-chatbot' ),
                        'description' => __( 'Durée de conservation des réponses en cache', 'anythingllm-chatbot' ),
                        'default'     => 3600,
                        'min'         => 60,
                        'max'         => 86400,
                    ),
                ),
            ),
            // Logs & Stats Settings
            'logs' => array(
                'title'  => __( 'Logs & Statistiques', 'anythingllm-chatbot' ),
                'fields' => array(
                    'ofac_enable_logs' => array(
                        'type'        => 'checkbox',
                        'label'       => __( 'Activer les logs', 'anythingllm-chatbot' ),
                        'description' => __( 'Enregistrer les conversations', 'anythingllm-chatbot' ),
                        'default'     => true,
                    ),
                    'ofac_enable_stats' => array(
                        'type'        => 'checkbox',
                        'label'       => __( 'Activer les statistiques', 'anythingllm-chatbot' ),
                        'description' => __( 'Collecter des statistiques d\'usage', 'anythingllm-chatbot' ),
                        'default'     => true,
                    ),
                ),
            ),
        );

        /**
         * Filter settings schema
         *
         * @since 1.0.0
         * @param array $schema Settings schema
         */
        $this->schema = apply_filters( 'ofac_settings_schema', $this->schema );
    }

    /**
     * Load all settings
     */
    private function load_settings() {
        // Define all setting keys with non-translatable defaults
        $setting_keys = array(
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
            'ofac_bot_name'             => 'Service Client',
            'ofac_welcome_message'      => '',
            'ofac_fallback_message'     => '',
            'ofac_placeholder_text'     => '',
            'ofac_max_chars'            => 5000,
            'ofac_max_file_size'        => 5,
            'ofac_allowed_file_types'   => 'jpg,jpeg,png,gif,pdf,doc,docx,txt',
            'ofac_rate_limit'           => 30,
            'ofac_cache_duration'       => 3600,
            'ofac_data_retention_days'  => 30,
            'ofac_privacy_policy_url'   => '',
            'ofac_show_skip_link'       => true,
            'ofac_allowed_roles'        => array(),
            'ofac_enable_logs'          => true,
            'ofac_enable_stats'         => true,
            'ofac_enable_streaming'     => true,
            'ofac_quick_replies'        => '',
            'ofac_honeypot_enabled'     => true,
            'ofac_require_consent'      => true,
        );

        foreach ( $setting_keys as $key => $default ) {
            $this->settings[ $key ] = get_option( $key, $default );
        }
    }

    /**
     * Get setting value
     *
     * @param string $key Setting key
     * @param mixed  $default Default value
     * @return mixed
     */
    public function get( $key, $default = null ) {
        if ( isset( $this->settings[ $key ] ) ) {
            return $this->settings[ $key ];
        }

        $value = get_option( $key, $default );
        $this->settings[ $key ] = $value;

        return $value;
    }

    /**
     * Set setting value
     *
     * @param string $key Setting key
     * @param mixed  $value Value
     * @return bool
     */
    public function set( $key, $value ) {
        $sanitized = $this->sanitize( $key, $value );
        $result    = update_option( $key, $sanitized );

        if ( $result ) {
            $this->settings[ $key ] = $sanitized;
        }

        return $result;
    }

    /**
     * Sanitize setting value
     *
     * @param string $key Setting key
     * @param mixed  $value Value
     * @return mixed
     */
    public function sanitize( $key, $value ) {
        $field = $this->get_field_schema( $key );

        if ( ! $field ) {
            return sanitize_text_field( $value );
        }

        switch ( $field['type'] ) {
            case 'checkbox':
                return (bool) $value;

            case 'number':
                $value = intval( $value );
                if ( isset( $field['min'] ) ) {
                    $value = max( $field['min'], $value );
                }
                if ( isset( $field['max'] ) ) {
                    $value = min( $field['max'], $value );
                }
                return $value;

            case 'url':
                return esc_url_raw( $value );

            case 'email':
                return sanitize_email( $value );

            case 'textarea':
                return sanitize_textarea_field( $value );

            case 'color':
                return sanitize_hex_color( $value );

            case 'select':
                if ( isset( $field['options'] ) && is_array( $field['options'] ) ) {
                    return array_key_exists( $value, $field['options'] ) ? $value : $field['default'];
                }
                return sanitize_text_field( $value );

            case 'multiselect':
                if ( is_array( $value ) ) {
                    return array_map( 'sanitize_text_field', $value );
                }
                return array();

            case 'password':
                return $value; // Don't sanitize passwords

            case 'image':
                return absint( $value );

            default:
                return sanitize_text_field( $value );
        }
    }

    /**
     * Get field schema
     *
     * @param string $key Field key
     * @return array|null
     */
    public function get_field_schema( $key ) {
        $schema = $this->get_schema();
        foreach ( $schema as $section ) {
            if ( isset( $section['fields'][ $key ] ) ) {
                return $section['fields'][ $key ];
            }
        }
        return null;
    }

    /**
     * Get all settings
     *
     * @return array
     */
    public function get_all() {
        return $this->settings;
    }

    /**
     * Export settings
     *
     * @return string JSON string
     */
    public function export() {
        $export = array();
        $schema = $this->get_schema();

        foreach ( $schema as $section_key => $section ) {
            if ( ! isset( $section['fields'] ) || ! is_array( $section['fields'] ) ) {
                continue;
            }
            foreach ( $section['fields'] as $key => $field ) {
                // Don't export sensitive data
                if ( isset( $field['type'] ) && $field['type'] === 'password' ) {
                    continue;
                }
                $export[ $key ] = $this->get( $key );
            }
        }

        return wp_json_encode( $export, JSON_PRETTY_PRINT );
    }

    /**
     * Import settings
     *
     * @param string $json JSON string
     * @return bool|WP_Error
     */
    public function import( $json ) {
        $data = json_decode( $json, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error( 'invalid_json', __( 'Format JSON invalide', 'anythingllm-chatbot' ) );
        }

        foreach ( $data as $key => $value ) {
            $field = $this->get_field_schema( $key );
            if ( $field && $field['type'] !== 'password' ) {
                $this->set( $key, $value );
            }
        }

        return true;
    }

    /**
     * Get public settings for frontend
     *
     * @return array
     */
    public function get_public_settings() {
        $public_keys = array(
            'ofac_position',
            'ofac_width_desktop',
            'ofac_height_desktop',
            'ofac_theme_mode',
            'ofac_primary_color',
            'ofac_bot_avatar',
            'ofac_user_avatar',
            'ofac_bot_name',
            'ofac_welcome_message',
            'ofac_fallback_message',
            'ofac_placeholder_text',
            'ofac_max_chars',
            'ofac_enable_file_upload',
            'ofac_max_file_size',
            'ofac_allowed_file_types',
            'ofac_privacy_policy_url',
            'ofac_consent_text',
            'ofac_show_skip_link',
            'ofac_skip_link_text',
            'ofac_require_consent',
        );

        $settings = array();
        foreach ( $public_keys as $key ) {
            $settings[ str_replace( 'ofac_', '', $key ) ] = $this->get( $key );
        }

        // Consentement requis par défaut (RGPD)
        // Force le cast en booléen et utilise true par défaut si la valeur n'est pas explicitement définie
        $require_consent_value = $this->get( 'ofac_require_consent' );
        // Si l'option n'a jamais été configurée (null, '', 0), on utilise true par défaut
        if ( $require_consent_value === null || $require_consent_value === '' || $require_consent_value === false ) {
            $settings['require_consent'] = true;
        } else {
            $settings['require_consent'] = (bool) $require_consent_value;
        }

        // Add avatar URLs
        if ( ! empty( $settings['bot_avatar'] ) ) {
            $settings['bot_avatar_url'] = wp_get_attachment_url( $settings['bot_avatar'] );
        }
        if ( ! empty( $settings['user_avatar'] ) ) {
            $settings['user_avatar_url'] = wp_get_attachment_url( $settings['user_avatar'] );
        }

        /**
         * Filter public settings
         *
         * @since 1.0.0
         * @param array $settings Public settings
         */
        return apply_filters( 'ofac_public_settings', $settings );
    }
}
