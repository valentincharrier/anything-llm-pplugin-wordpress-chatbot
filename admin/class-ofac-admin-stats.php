<?php
/**
 * Admin Stats Page
 *
 * @package Ocade_Fusion_AnythingLLM_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class OFAC_Admin_Stats
 */
class OFAC_Admin_Stats {

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
        add_action( 'wp_ajax_ofac_get_stats_data', array( $this, 'ajax_get_stats_data' ) );
    }

    /**
     * Render stats page
     */
    public function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $stats = OFAC_Stats::get_instance();
        
        // Default to last 30 days
        $date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( $_GET['date_from'] ) : date( 'Y-m-d', strtotime( '-30 days' ) );
        $date_to = isset( $_GET['date_to'] ) ? sanitize_text_field( $_GET['date_to'] ) : date( 'Y-m-d' );

        $summary = $stats->get_summary( $date_from, $date_to );
        $chart_data = $stats->get_chart_data( $date_from, $date_to );
        ?>
        <div class="wrap ofac-admin-wrap">
            <h1><?php esc_html_e( 'Statistiques', 'anythingllm-chatbot' ); ?></h1>

            <div class="ofac-stats-filters">
                <form method="get" action="">
                    <input type="hidden" name="page" value="ofac-stats">
                    
                    <div class="ofac-filter-row">
                        <label for="date_from"><?php esc_html_e( 'Du', 'anythingllm-chatbot' ); ?></label>
                        <input type="date" id="date_from" name="date_from" value="<?php echo esc_attr( $date_from ); ?>">

                        <label for="date_to"><?php esc_html_e( 'Au', 'anythingllm-chatbot' ); ?></label>
                        <input type="date" id="date_to" name="date_to" value="<?php echo esc_attr( $date_to ); ?>">

                        <button type="submit" class="button"><?php esc_html_e( 'Appliquer', 'anythingllm-chatbot' ); ?></button>

                        <div class="ofac-quick-ranges">
                            <a href="?page=ofac-stats&date_from=<?php echo esc_attr( date( 'Y-m-d', strtotime( '-7 days' ) ) ); ?>&date_to=<?php echo esc_attr( date( 'Y-m-d' ) ); ?>" class="button">
                                <?php esc_html_e( '7 jours', 'anythingllm-chatbot' ); ?>
                            </a>
                            <a href="?page=ofac-stats&date_from=<?php echo esc_attr( date( 'Y-m-d', strtotime( '-30 days' ) ) ); ?>&date_to=<?php echo esc_attr( date( 'Y-m-d' ) ); ?>" class="button">
                                <?php esc_html_e( '30 jours', 'anythingllm-chatbot' ); ?>
                            </a>
                            <a href="?page=ofac-stats&date_from=<?php echo esc_attr( date( 'Y-m-d', strtotime( '-90 days' ) ) ); ?>&date_to=<?php echo esc_attr( date( 'Y-m-d' ) ); ?>" class="button">
                                <?php esc_html_e( '90 jours', 'anythingllm-chatbot' ); ?>
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Summary Cards -->
            <div class="ofac-stats-cards">
                <div class="ofac-stat-card">
                    <div class="ofac-stat-icon">
                        <span class="dashicons dashicons-format-chat"></span>
                    </div>
                    <div class="ofac-stat-content">
                        <div class="ofac-stat-value"><?php echo esc_html( number_format_i18n( $summary['total_conversations'] ) ); ?></div>
                        <div class="ofac-stat-label"><?php esc_html_e( 'Conversations', 'anythingllm-chatbot' ); ?></div>
                    </div>
                </div>

                <div class="ofac-stat-card">
                    <div class="ofac-stat-icon">
                        <span class="dashicons dashicons-admin-comments"></span>
                    </div>
                    <div class="ofac-stat-content">
                        <div class="ofac-stat-value"><?php echo esc_html( number_format_i18n( $summary['total_messages'] ) ); ?></div>
                        <div class="ofac-stat-label"><?php esc_html_e( 'Messages', 'anythingllm-chatbot' ); ?></div>
                    </div>
                </div>

                <div class="ofac-stat-card">
                    <div class="ofac-stat-icon">
                        <span class="dashicons dashicons-clock"></span>
                    </div>
                    <div class="ofac-stat-content">
                        <div class="ofac-stat-value"><?php echo esc_html( number_format( $summary['avg_response_time'], 2 ) ); ?>s</div>
                        <div class="ofac-stat-label"><?php esc_html_e( 'Temps de réponse moyen', 'anythingllm-chatbot' ); ?></div>
                    </div>
                </div>

                <div class="ofac-stat-card <?php echo $summary['error_rate'] > 5 ? 'ofac-stat-warning' : ''; ?>">
                    <div class="ofac-stat-icon">
                        <span class="dashicons dashicons-warning"></span>
                    </div>
                    <div class="ofac-stat-content">
                        <div class="ofac-stat-value"><?php echo esc_html( number_format( $summary['error_rate'], 1 ) ); ?>%</div>
                        <div class="ofac-stat-label"><?php esc_html_e( 'Taux d\'erreur', 'anythingllm-chatbot' ); ?></div>
                    </div>
                </div>

                <div class="ofac-stat-card">
                    <div class="ofac-stat-icon">
                        <span class="dashicons dashicons-chart-line"></span>
                    </div>
                    <div class="ofac-stat-content">
                        <div class="ofac-stat-value"><?php echo esc_html( number_format( $summary['daily_average'], 1 ) ); ?></div>
                        <div class="ofac-stat-label"><?php esc_html_e( 'Messages/jour', 'anythingllm-chatbot' ); ?></div>
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="ofac-stats-charts">
                <div class="ofac-chart-container">
                    <h3><?php esc_html_e( 'Conversations & Messages', 'anythingllm-chatbot' ); ?></h3>
                    <canvas id="ofac-chart-activity"></canvas>
                </div>

                <div class="ofac-chart-container">
                    <h3><?php esc_html_e( 'Temps de réponse', 'anythingllm-chatbot' ); ?></h3>
                    <canvas id="ofac-chart-response-time"></canvas>
                </div>

                <div class="ofac-chart-container">
                    <h3><?php esc_html_e( 'Erreurs', 'anythingllm-chatbot' ); ?></h3>
                    <canvas id="ofac-chart-errors"></canvas>
                </div>
            </div>

            <!-- Today's Stats -->
            <div class="ofac-stats-today">
                <h3><?php esc_html_e( 'Aujourd\'hui', 'anythingllm-chatbot' ); ?></h3>
                <?php
                $today = $stats->get_today();
                ?>
                <table class="widefat">
                    <tbody>
                        <tr>
                            <th><?php esc_html_e( 'Conversations', 'anythingllm-chatbot' ); ?></th>
                            <td><?php echo esc_html( $today['conversations'] ); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Messages', 'anythingllm-chatbot' ); ?></th>
                            <td><?php echo esc_html( $today['messages'] ); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Erreurs', 'anythingllm-chatbot' ); ?></th>
                            <td><?php echo esc_html( $today['errors'] ); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Temps de réponse moyen', 'anythingllm-chatbot' ); ?></th>
                            <td><?php echo esc_html( number_format( $today['avg_response_time'], 2 ) ); ?>s</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <script type="text/javascript">
            var ofacChartData = <?php echo wp_json_encode( $chart_data ); ?>;
        </script>
        <?php
    }

    /**
     * AJAX: Get stats data for charts
     */
    public function ajax_get_stats_data() {
        check_ajax_referer( 'ofac_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission refusée.', 'anythingllm-chatbot' ) );
        }

        $date_from = isset( $_POST['date_from'] ) ? sanitize_text_field( $_POST['date_from'] ) : date( 'Y-m-d', strtotime( '-30 days' ) );
        $date_to = isset( $_POST['date_to'] ) ? sanitize_text_field( $_POST['date_to'] ) : date( 'Y-m-d' );

        $stats = OFAC_Stats::get_instance();

        wp_send_json_success( array(
            'summary'    => $stats->get_summary( $date_from, $date_to ),
            'chart_data' => $stats->get_chart_data( $date_from, $date_to ),
        ) );
    }
}
