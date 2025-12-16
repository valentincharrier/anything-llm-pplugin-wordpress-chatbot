<?php
/**
 * Admin GDPR Page
 *
 * @package Ocade_Fusion_AnythingLLM_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class OFAC_Admin_GDPR
 */
class OFAC_Admin_GDPR {

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action( 'wp_ajax_ofac_gdpr_search_user', array( $this, 'ajax_search_user' ) );
        add_action( 'wp_ajax_ofac_gdpr_export_data', array( $this, 'ajax_export_data' ) );
        add_action( 'wp_ajax_ofac_gdpr_delete_data', array( $this, 'ajax_delete_data' ) );
        add_action( 'wp_ajax_ofac_gdpr_cleanup', array( $this, 'ajax_cleanup' ) );
    }

    /**
     * Render GDPR page
     */
    public function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $gdpr = OFAC_GDPR::get_instance();
        $settings = OFAC_Settings::get_instance();
        ?>
        <div class="wrap ofac-admin-wrap">
            <h1><?php esc_html_e( 'Gestion RGPD', 'anythingllm-chatbot' ); ?></h1>

            <div class="ofac-gdpr-sections">
                <!-- Data Retention -->
                <div class="ofac-gdpr-section">
                    <h2><?php esc_html_e( 'Rétention des données', 'anythingllm-chatbot' ); ?></h2>
                    <p class="description">
                        <?php printf(
                            esc_html__( 'Les données sont conservées pendant %d jours puis automatiquement supprimées.', 'anythingllm-chatbot' ),
                            $settings->get( 'data_retention_days' )
                        ); ?>
                    </p>
                    <p>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=ofac-settings&tab=privacy' ) ); ?>" class="button">
                            <?php esc_html_e( 'Modifier la durée de rétention', 'anythingllm-chatbot' ); ?>
                        </a>
                    </p>

                    <h3><?php esc_html_e( 'Nettoyage manuel', 'anythingllm-chatbot' ); ?></h3>
                    <p class="description">
                        <?php esc_html_e( 'Supprimer immédiatement les données expirées.', 'anythingllm-chatbot' ); ?>
                    </p>
                    <p>
                        <button type="button" class="button" id="ofac-cleanup-data">
                            <?php esc_html_e( 'Nettoyer les données expirées', 'anythingllm-chatbot' ); ?>
                        </button>
                        <span id="ofac-cleanup-result"></span>
                    </p>
                </div>

                <!-- User Data Management -->
                <div class="ofac-gdpr-section">
                    <h2><?php esc_html_e( 'Données utilisateur', 'anythingllm-chatbot' ); ?></h2>
                    <p class="description">
                        <?php esc_html_e( 'Recherchez un utilisateur pour exporter ou supprimer ses données (RGPD art. 15 & 17).', 'anythingllm-chatbot' ); ?>
                    </p>

                    <div class="ofac-gdpr-search">
                        <h3><?php esc_html_e( 'Rechercher par utilisateur WordPress', 'anythingllm-chatbot' ); ?></h3>
                        <div class="ofac-search-row">
                            <?php
                            wp_dropdown_users( array(
                                'name'              => 'ofac_user_id',
                                'id'                => 'ofac_user_id',
                                'show_option_none'  => __( 'Sélectionner un utilisateur', 'anythingllm-chatbot' ),
                                'option_none_value' => '',
                            ) );
                            ?>
                            <button type="button" class="button" id="ofac-search-user-data">
                                <?php esc_html_e( 'Rechercher', 'anythingllm-chatbot' ); ?>
                            </button>
                        </div>

                        <h3><?php esc_html_e( 'Rechercher par session ID', 'anythingllm-chatbot' ); ?></h3>
                        <div class="ofac-search-row">
                            <input type="text" id="ofac_session_id" placeholder="<?php esc_attr_e( 'Session ID (UUID)', 'anythingllm-chatbot' ); ?>" class="regular-text">
                            <button type="button" class="button" id="ofac-search-session-data">
                                <?php esc_html_e( 'Rechercher', 'anythingllm-chatbot' ); ?>
                            </button>
                        </div>
                    </div>

                    <div id="ofac-user-data-results" style="display:none;">
                        <h3><?php esc_html_e( 'Données trouvées', 'anythingllm-chatbot' ); ?></h3>
                        <div id="ofac-data-summary"></div>
                        <div class="ofac-data-actions">
                            <button type="button" class="button button-primary" id="ofac-export-user-data">
                                <?php esc_html_e( 'Exporter les données (JSON)', 'anythingllm-chatbot' ); ?>
                            </button>
                            <button type="button" class="button button-secondary" id="ofac-delete-user-data">
                                <?php esc_html_e( 'Supprimer les données', 'anythingllm-chatbot' ); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Consent Management -->
                <div class="ofac-gdpr-section">
                    <h2><?php esc_html_e( 'Consentements', 'anythingllm-chatbot' ); ?></h2>
                    <p class="description">
                        <?php esc_html_e( 'Liste des consentements enregistrés.', 'anythingllm-chatbot' ); ?>
                    </p>

                    <?php
                    global $wpdb;
                    $table_name = $wpdb->prefix . 'ofac_consents';
                    $consents_count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );
                    $active_consents = $wpdb->get_var( $wpdb->prepare(
                        "SELECT COUNT(*) FROM $table_name WHERE expires_at > %s",
                        current_time( 'mysql' )
                    ) );
                    ?>

                    <table class="widefat">
                        <tbody>
                            <tr>
                                <th><?php esc_html_e( 'Total des consentements', 'anythingllm-chatbot' ); ?></th>
                                <td><?php echo esc_html( number_format_i18n( $consents_count ) ); ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Consentements actifs', 'anythingllm-chatbot' ); ?></th>
                                <td><?php echo esc_html( number_format_i18n( $active_consents ) ); ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Consentements expirés', 'anythingllm-chatbot' ); ?></th>
                                <td><?php echo esc_html( number_format_i18n( $consents_count - $active_consents ) ); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Data Collected Info -->
                <div class="ofac-gdpr-section">
                    <h2><?php esc_html_e( 'Données collectées', 'anythingllm-chatbot' ); ?></h2>
                    <p class="description">
                        <?php esc_html_e( 'Le chatbot collecte les données suivantes :', 'anythingllm-chatbot' ); ?>
                    </p>

                    <table class="widefat">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Type de donnée', 'anythingllm-chatbot' ); ?></th>
                                <th><?php esc_html_e( 'Description', 'anythingllm-chatbot' ); ?></th>
                                <th><?php esc_html_e( 'Rétention', 'anythingllm-chatbot' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?php esc_html_e( 'Messages', 'anythingllm-chatbot' ); ?></td>
                                <td><?php esc_html_e( 'Contenu des messages échangés avec le chatbot', 'anythingllm-chatbot' ); ?></td>
                                <td><?php echo esc_html( $settings->get( 'data_retention_days' ) ); ?> <?php esc_html_e( 'jours', 'anythingllm-chatbot' ); ?></td>
                            </tr>
                            <tr>
                                <td><?php esc_html_e( 'Horodatage', 'anythingllm-chatbot' ); ?></td>
                                <td><?php esc_html_e( 'Date et heure des messages', 'anythingllm-chatbot' ); ?></td>
                                <td><?php echo esc_html( $settings->get( 'data_retention_days' ) ); ?> <?php esc_html_e( 'jours', 'anythingllm-chatbot' ); ?></td>
                            </tr>
                            <tr>
                                <td><?php esc_html_e( 'Adresse IP', 'anythingllm-chatbot' ); ?></td>
                                <td><?php esc_html_e( 'Adresse IP anonymisée (hash SHA256 avec dernier octet masqué)', 'anythingllm-chatbot' ); ?></td>
                                <td><?php echo esc_html( $settings->get( 'data_retention_days' ) ); ?> <?php esc_html_e( 'jours', 'anythingllm-chatbot' ); ?></td>
                            </tr>
                            <tr>
                                <td><?php esc_html_e( 'Session ID', 'anythingllm-chatbot' ); ?></td>
                                <td><?php esc_html_e( 'Identifiant unique de session (UUID)', 'anythingllm-chatbot' ); ?></td>
                                <td><?php echo esc_html( $settings->get( 'data_retention_days' ) ); ?> <?php esc_html_e( 'jours', 'anythingllm-chatbot' ); ?></td>
                            </tr>
                            <tr>
                                <td><?php esc_html_e( 'User ID', 'anythingllm-chatbot' ); ?></td>
                                <td><?php esc_html_e( 'ID utilisateur WordPress (si connecté)', 'anythingllm-chatbot' ); ?></td>
                                <td><?php echo esc_html( $settings->get( 'data_retention_days' ) ); ?> <?php esc_html_e( 'jours', 'anythingllm-chatbot' ); ?></td>
                            </tr>
                            <tr>
                                <td><?php esc_html_e( 'Consentement', 'anythingllm-chatbot' ); ?></td>
                                <td><?php esc_html_e( 'Enregistrement du consentement utilisateur', 'anythingllm-chatbot' ); ?></td>
                                <td>30 <?php esc_html_e( 'jours', 'anythingllm-chatbot' ); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Privacy Tools Integration -->
                <div class="ofac-gdpr-section">
                    <h2><?php esc_html_e( 'Intégration WordPress Privacy Tools', 'anythingllm-chatbot' ); ?></h2>
                    <p class="description">
                        <?php esc_html_e( 'Ce plugin est intégré aux outils de confidentialité WordPress (export et suppression de données personnelles).', 'anythingllm-chatbot' ); ?>
                    </p>
                    <p>
                        <a href="<?php echo esc_url( admin_url( 'export-personal-data.php' ) ); ?>" class="button">
                            <?php esc_html_e( 'Exporter des données personnelles', 'anythingllm-chatbot' ); ?>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'erase-personal-data.php' ) ); ?>" class="button">
                            <?php esc_html_e( 'Effacer des données personnelles', 'anythingllm-chatbot' ); ?>
                        </a>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: Search user data
     */
    public function ajax_search_user() {
        check_ajax_referer( 'ofac_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission refusée.', 'anythingllm-chatbot' ) );
        }

        $user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
        $session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( $_POST['session_id'] ) : '';

        if ( ! $user_id && empty( $session_id ) ) {
            wp_send_json_error( __( 'Veuillez spécifier un utilisateur ou une session.', 'anythingllm-chatbot' ) );
        }

        $gdpr = OFAC_GDPR::get_instance();
        $data = $gdpr->get_user_data( $session_id, $user_id );

        if ( empty( $data['conversations'] ) ) {
            wp_send_json_error( __( 'Aucune donnée trouvée.', 'anythingllm-chatbot' ) );
        }

        $total_messages = 0;
        foreach ( $data['conversations'] as $conv ) {
            $total_messages += count( $conv['messages'] );
        }

        wp_send_json_success( array(
            'conversations' => count( $data['conversations'] ),
            'messages'      => $total_messages,
            'user_id'       => $user_id,
            'session_id'    => $session_id,
            'data'          => $data,
        ) );
    }

    /**
     * AJAX: Export user data
     */
    public function ajax_export_data() {
        check_ajax_referer( 'ofac_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission refusée.', 'anythingllm-chatbot' ) );
        }

        $user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
        $session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( $_POST['session_id'] ) : '';

        $gdpr = OFAC_GDPR::get_instance();
        $data = $gdpr->get_user_data( $session_id, $user_id );

        $filename = 'ofac-data-export-' . date( 'Y-m-d-His' ) . '.json';

        wp_send_json_success( array(
            'data'     => $data,
            'filename' => $filename,
        ) );
    }

    /**
     * AJAX: Delete user data
     */
    public function ajax_delete_data() {
        check_ajax_referer( 'ofac_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission refusée.', 'anythingllm-chatbot' ) );
        }

        $user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
        $session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( $_POST['session_id'] ) : '';

        $gdpr = OFAC_GDPR::get_instance();
        $result = $gdpr->erase_user_data( $session_id, $user_id );

        if ( $result['items_removed'] > 0 ) {
            wp_send_json_success( sprintf(
                __( '%d éléments supprimés.', 'anythingllm-chatbot' ),
                $result['items_removed']
            ) );
        } else {
            wp_send_json_error( __( 'Aucune donnée à supprimer.', 'anythingllm-chatbot' ) );
        }
    }

    /**
     * AJAX: Cleanup expired data
     */
    public function ajax_cleanup() {
        check_ajax_referer( 'ofac_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission refusée.', 'anythingllm-chatbot' ) );
        }

        $gdpr = OFAC_GDPR::get_instance();
        $deleted = $gdpr->cleanup_expired_data();

        wp_send_json_success( sprintf(
            __( '%d conversations expirées supprimées.', 'anythingllm-chatbot' ),
            $deleted
        ) );
    }
}
