<?php
/**
 * Admin Logs Page
 *
 * @package Ocade_Fusion_AnythingLLM_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class OFAC_Admin_Logs
 */
class OFAC_Admin_Logs {

    /**
     * Items per page
     *
     * @var int
     */
    private $per_page = 20;

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
        add_action( 'wp_ajax_ofac_get_conversation_messages', array( $this, 'ajax_get_messages' ) );
        add_action( 'wp_ajax_ofac_delete_conversation', array( $this, 'ajax_delete_conversation' ) );
        add_action( 'wp_ajax_ofac_bulk_delete_conversations', array( $this, 'ajax_bulk_delete' ) );
    }

    /**
     * Render logs page
     */
    public function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $logs = OFAC_Logs::get_instance();
        
        // Get filters
        $current_page = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
        $date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( $_GET['date_from'] ) : '';
        $date_to = isset( $_GET['date_to'] ) ? sanitize_text_field( $_GET['date_to'] ) : '';
        $search = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
        $user_id = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;

        // Build filters
        $filters = array();
        if ( $date_from ) {
            $filters['date_from'] = $date_from;
        }
        if ( $date_to ) {
            $filters['date_to'] = $date_to;
        }
        if ( $search ) {
            $filters['search'] = $search;
        }
        if ( $user_id ) {
            $filters['user_id'] = $user_id;
        }

        // Get conversations
        $conversations = $logs->get_conversations(
            $current_page,
            $this->per_page,
            $filters,
            'started_at',
            'DESC'
        );

        $total_items = $conversations['total'];
        $total_pages = ceil( $total_items / $this->per_page );
        ?>
        <div class="wrap ofac-admin-wrap">
            <h1><?php esc_html_e( 'Logs des conversations', 'anythingllm-chatbot' ); ?></h1>

            <div class="ofac-logs-filters">
                <form method="get" action="">
                    <input type="hidden" name="page" value="ofac-logs">
                    
                    <div class="ofac-filter-row">
                        <label for="date_from"><?php esc_html_e( 'Du', 'anythingllm-chatbot' ); ?></label>
                        <input type="date" id="date_from" name="date_from" value="<?php echo esc_attr( $date_from ); ?>">

                        <label for="date_to"><?php esc_html_e( 'Au', 'anythingllm-chatbot' ); ?></label>
                        <input type="date" id="date_to" name="date_to" value="<?php echo esc_attr( $date_to ); ?>">

                        <label for="user_id"><?php esc_html_e( 'Utilisateur', 'anythingllm-chatbot' ); ?></label>
                        <?php
                        wp_dropdown_users( array(
                            'name'             => 'user_id',
                            'id'               => 'user_id',
                            'selected'         => $user_id,
                            'show_option_all'  => __( 'Tous', 'anythingllm-chatbot' ),
                            'option_none_value' => 0,
                        ) );
                        ?>

                        <input type="search" name="s" placeholder="<?php esc_attr_e( 'Rechercher...', 'anythingllm-chatbot' ); ?>" 
                               value="<?php echo esc_attr( $search ); ?>">

                        <button type="submit" class="button"><?php esc_html_e( 'Filtrer', 'anythingllm-chatbot' ); ?></button>
                        <a href="?page=ofac-logs" class="button"><?php esc_html_e( 'Réinitialiser', 'anythingllm-chatbot' ); ?></a>
                    </div>
                </form>
            </div>

            <form method="post" id="ofac-logs-form">
                <?php wp_nonce_field( 'ofac_bulk_action', 'ofac_bulk_nonce' ); ?>
                
                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <select name="bulk_action" id="bulk-action-selector-top">
                            <option value=""><?php esc_html_e( 'Actions groupées', 'anythingllm-chatbot' ); ?></option>
                            <option value="delete"><?php esc_html_e( 'Supprimer', 'anythingllm-chatbot' ); ?></option>
                        </select>
                        <button type="button" class="button action" id="ofac-bulk-action-btn">
                            <?php esc_html_e( 'Appliquer', 'anythingllm-chatbot' ); ?>
                        </button>
                    </div>

                    <div class="tablenav-pages">
                        <span class="displaying-num">
                            <?php printf( 
                                esc_html( _n( '%s élément', '%s éléments', $total_items, 'anythingllm-chatbot' ) ), 
                                number_format_i18n( $total_items ) 
                            ); ?>
                        </span>
                        <?php if ( $total_pages > 1 ) : ?>
                            <?php echo $this->pagination_links( $current_page, $total_pages ); ?>
                        <?php endif; ?>
                    </div>
                </div>

                <table class="wp-list-table widefat fixed striped ofac-logs-table">
                    <thead>
                        <tr>
                            <td class="manage-column column-cb check-column">
                                <input type="checkbox" id="cb-select-all">
                            </td>
                            <th scope="col"><?php esc_html_e( 'ID', 'anythingllm-chatbot' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Session', 'anythingllm-chatbot' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Utilisateur', 'anythingllm-chatbot' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Messages', 'anythingllm-chatbot' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Début', 'anythingllm-chatbot' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Fin', 'anythingllm-chatbot' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Actions', 'anythingllm-chatbot' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $conversations['items'] ) ) : ?>
                            <tr>
                                <td colspan="8"><?php esc_html_e( 'Aucune conversation trouvée.', 'anythingllm-chatbot' ); ?></td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ( $conversations['items'] as $conv ) : ?>
                                <tr data-id="<?php echo esc_attr( $conv->id ); ?>">
                                    <th scope="row" class="check-column">
                                        <input type="checkbox" name="conversation_ids[]" value="<?php echo esc_attr( $conv->id ); ?>">
                                    </th>
                                    <td><?php echo esc_html( $conv->id ); ?></td>
                                    <td>
                                        <code title="<?php echo esc_attr( $conv->session_id ); ?>">
                                            <?php echo esc_html( substr( $conv->session_id, 0, 8 ) . '...' ); ?>
                                        </code>
                                    </td>
                                    <td>
                                        <?php if ( $conv->user_id ) : ?>
                                            <?php $user = get_userdata( $conv->user_id ); ?>
                                            <?php echo $user ? esc_html( $user->display_name ) : esc_html__( 'Inconnu', 'anythingllm-chatbot' ); ?>
                                        <?php else : ?>
                                            <span class="ofac-anonymous"><?php esc_html_e( 'Anonyme', 'anythingllm-chatbot' ); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html( $conv->message_count ); ?></td>
                                    <td><?php echo esc_html( wp_date( 'd/m/Y H:i', strtotime( $conv->started_at ) ) ); ?></td>
                                    <td>
                                        <?php if ( $conv->ended_at ) : ?>
                                            <?php echo esc_html( wp_date( 'd/m/Y H:i', strtotime( $conv->ended_at ) ) ); ?>
                                        <?php else : ?>
                                            <span class="ofac-active"><?php esc_html_e( 'Active', 'anythingllm-chatbot' ); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="button button-small ofac-view-messages" data-id="<?php echo esc_attr( $conv->id ); ?>">
                                            <?php esc_html_e( 'Voir', 'anythingllm-chatbot' ); ?>
                                        </button>
                                        <button type="button" class="button button-small ofac-delete-conversation" data-id="<?php echo esc_attr( $conv->id ); ?>">
                                            <?php esc_html_e( 'Supprimer', 'anythingllm-chatbot' ); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php if ( $total_pages > 1 ) : ?>
                            <?php echo $this->pagination_links( $current_page, $total_pages ); ?>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- Modal for viewing messages -->
        <div id="ofac-messages-modal" class="ofac-modal" style="display:none;">
            <div class="ofac-modal-content">
                <div class="ofac-modal-header">
                    <h2><?php esc_html_e( 'Messages de la conversation', 'anythingllm-chatbot' ); ?></h2>
                    <button type="button" class="ofac-modal-close">&times;</button>
                </div>
                <div class="ofac-modal-body">
                    <div id="ofac-messages-list"></div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Generate pagination links
     *
     * @param int $current_page Current page.
     * @param int $total_pages  Total pages.
     * @return string
     */
    private function pagination_links( $current_page, $total_pages ) {
        $base_url = add_query_arg( array(
            'page' => 'ofac-logs',
        ), admin_url( 'admin.php' ) );

        // Preserve filters
        foreach ( array( 'date_from', 'date_to', 's', 'user_id' ) as $param ) {
            if ( isset( $_GET[ $param ] ) && $_GET[ $param ] !== '' ) {
                $base_url = add_query_arg( $param, sanitize_text_field( $_GET[ $param ] ), $base_url );
            }
        }

        $output = '<span class="pagination-links">';

        // First page
        if ( $current_page > 1 ) {
            $output .= sprintf(
                '<a class="first-page button" href="%s"><span aria-hidden="true">&laquo;</span></a> ',
                esc_url( add_query_arg( 'paged', 1, $base_url ) )
            );
            $output .= sprintf(
                '<a class="prev-page button" href="%s"><span aria-hidden="true">&lsaquo;</span></a> ',
                esc_url( add_query_arg( 'paged', $current_page - 1, $base_url ) )
            );
        } else {
            $output .= '<span class="tablenav-pages-navspan button disabled">&laquo;</span> ';
            $output .= '<span class="tablenav-pages-navspan button disabled">&lsaquo;</span> ';
        }

        $output .= sprintf(
            '<span class="paging-input">%s / %s</span> ',
            $current_page,
            $total_pages
        );

        // Last page
        if ( $current_page < $total_pages ) {
            $output .= sprintf(
                '<a class="next-page button" href="%s"><span aria-hidden="true">&rsaquo;</span></a> ',
                esc_url( add_query_arg( 'paged', $current_page + 1, $base_url ) )
            );
            $output .= sprintf(
                '<a class="last-page button" href="%s"><span aria-hidden="true">&raquo;</span></a>',
                esc_url( add_query_arg( 'paged', $total_pages, $base_url ) )
            );
        } else {
            $output .= '<span class="tablenav-pages-navspan button disabled">&rsaquo;</span> ';
            $output .= '<span class="tablenav-pages-navspan button disabled">&raquo;</span>';
        }

        $output .= '</span>';

        return $output;
    }

    /**
     * AJAX: Get conversation messages
     */
    public function ajax_get_messages() {
        check_ajax_referer( 'ofac_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission refusée.', 'anythingllm-chatbot' ) );
        }

        $conversation_id = isset( $_POST['conversation_id'] ) ? absint( $_POST['conversation_id'] ) : 0;

        if ( ! $conversation_id ) {
            wp_send_json_error( __( 'ID de conversation invalide.', 'anythingllm-chatbot' ) );
        }

        $logs = OFAC_Logs::get_instance();
        $messages = $logs->get_messages( $conversation_id );

        $html = '';
        foreach ( $messages as $msg ) {
            $role_class = $msg->role === 'user' ? 'ofac-msg-user' : 'ofac-msg-bot';
            $role_label = $msg->role === 'user' 
                ? __( 'Utilisateur', 'anythingllm-chatbot' ) 
                : __( 'Bot', 'anythingllm-chatbot' );

            $html .= sprintf(
                '<div class="ofac-message %s">
                    <div class="ofac-message-header">
                        <span class="ofac-message-role">%s</span>
                        <span class="ofac-message-time">%s</span>
                    </div>
                    <div class="ofac-message-content">%s</div>
                </div>',
                esc_attr( $role_class ),
                esc_html( $role_label ),
                esc_html( wp_date( 'd/m/Y H:i:s', strtotime( $msg->created_at ) ) ),
                wp_kses_post( nl2br( $msg->content ) )
            );
        }

        wp_send_json_success( array( 'html' => $html ) );
    }

    /**
     * AJAX: Delete conversation
     */
    public function ajax_delete_conversation() {
        check_ajax_referer( 'ofac_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission refusée.', 'anythingllm-chatbot' ) );
        }

        $conversation_id = isset( $_POST['conversation_id'] ) ? absint( $_POST['conversation_id'] ) : 0;

        if ( ! $conversation_id ) {
            wp_send_json_error( __( 'ID de conversation invalide.', 'anythingllm-chatbot' ) );
        }

        $logs = OFAC_Logs::get_instance();
        $result = $logs->delete_conversation( $conversation_id );

        if ( $result ) {
            wp_send_json_success( __( 'Conversation supprimée.', 'anythingllm-chatbot' ) );
        } else {
            wp_send_json_error( __( 'Erreur lors de la suppression.', 'anythingllm-chatbot' ) );
        }
    }

    /**
     * AJAX: Bulk delete conversations
     */
    public function ajax_bulk_delete() {
        check_ajax_referer( 'ofac_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission refusée.', 'anythingllm-chatbot' ) );
        }

        $ids = isset( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : array();

        if ( empty( $ids ) ) {
            wp_send_json_error( __( 'Aucune conversation sélectionnée.', 'anythingllm-chatbot' ) );
        }

        $logs = OFAC_Logs::get_instance();
        $deleted = 0;

        foreach ( $ids as $id ) {
            if ( $logs->delete_conversation( $id ) ) {
                $deleted++;
            }
        }

        wp_send_json_success( sprintf(
            __( '%d conversation(s) supprimée(s).', 'anythingllm-chatbot' ),
            $deleted
        ) );
    }
}
