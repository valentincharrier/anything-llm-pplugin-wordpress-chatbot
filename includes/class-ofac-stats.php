<?php
/**
 * Statistics class
 *
 * @package OcadeFusion_AnythingLLM_Chatbot
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class OFAC_Stats
 * Handles usage statistics
 */
class OFAC_Stats {

    /**
     * Single instance
     *
     * @var OFAC_Stats|null
     */
    private static $instance = null;

    /**
     * Today's stats transient key
     *
     * @var string
     */
    const TODAY_KEY = 'ofac_stats_today';

    /**
     * Get single instance
     *
     * @return OFAC_Stats
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
        add_action( 'ofac_daily_stats', array( $this, 'save_daily_stats' ) );

        // Schedule daily stats save
        if ( ! wp_next_scheduled( 'ofac_daily_stats' ) ) {
            wp_schedule_event( strtotime( 'tomorrow midnight' ), 'daily', 'ofac_daily_stats' );
        }
    }

    /**
     * Increment conversations count
     */
    public static function increment_conversations() {
        if ( ! OFAC_Settings::get_instance()->get( 'ofac_enable_stats', true ) ) {
            return;
        }

        $stats = self::get_today_stats();
        $stats['conversations']++;
        self::save_today_stats( $stats );
    }

    /**
     * Increment messages count
     */
    public static function increment_messages() {
        if ( ! OFAC_Settings::get_instance()->get( 'ofac_enable_stats', true ) ) {
            return;
        }

        $stats = self::get_today_stats();
        $stats['messages']++;
        self::save_today_stats( $stats );
    }

    /**
     * Increment errors count
     */
    public static function increment_errors() {
        if ( ! OFAC_Settings::get_instance()->get( 'ofac_enable_stats', true ) ) {
            return;
        }

        $stats = self::get_today_stats();
        $stats['errors']++;
        self::save_today_stats( $stats );
    }

    /**
     * Update average response time
     *
     * @param float $time Response time in seconds
     */
    public static function update_response_time( $time ) {
        if ( ! OFAC_Settings::get_instance()->get( 'ofac_enable_stats', true ) ) {
            return;
        }

        $stats = self::get_today_stats();

        // Calculate rolling average
        $total_responses = $stats['messages'];
        if ( $total_responses > 0 ) {
            $stats['avg_response_time'] = (
                ( $stats['avg_response_time'] * ( $total_responses - 1 ) ) + $time
            ) / $total_responses;
        } else {
            $stats['avg_response_time'] = $time;
        }

        self::save_today_stats( $stats );
    }

    /**
     * Get today's stats
     *
     * @return array
     */
    public static function get_today_stats() {
        $stats = get_transient( self::TODAY_KEY );

        if ( $stats === false ) {
            $stats = array(
                'date'              => date( 'Y-m-d' ),
                'conversations'     => 0,
                'messages'          => 0,
                'errors'            => 0,
                'avg_response_time' => 0,
            );
        }

        // Reset if it's a new day
        if ( $stats['date'] !== date( 'Y-m-d' ) ) {
            self::save_daily_stats();
            $stats = array(
                'date'              => date( 'Y-m-d' ),
                'conversations'     => 0,
                'messages'          => 0,
                'errors'            => 0,
                'avg_response_time' => 0,
            );
        }

        return $stats;
    }

    /**
     * Save today's stats
     *
     * @param array $stats Stats data
     */
    private static function save_today_stats( $stats ) {
        set_transient( self::TODAY_KEY, $stats, DAY_IN_SECONDS );
    }

    /**
     * Save daily stats to database
     */
    public function save_daily_stats() {
        global $wpdb;

        $stats = get_transient( self::TODAY_KEY );

        if ( $stats === false || $stats['date'] === date( 'Y-m-d' ) ) {
            return;
        }

        $table = $wpdb->prefix . 'ofac_stats';

        // Check if entry exists
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE date = %s",
                $stats['date']
            )
        );

        if ( $existing ) {
            $wpdb->update(
                $table,
                array(
                    'conversations'     => $stats['conversations'],
                    'messages'          => $stats['messages'],
                    'errors'            => $stats['errors'],
                    'avg_response_time' => $stats['avg_response_time'],
                ),
                array( 'date' => $stats['date'] ),
                array( '%d', '%d', '%d', '%f' ),
                array( '%s' )
            );
        } else {
            $wpdb->insert(
                $table,
                array(
                    'date'              => $stats['date'],
                    'conversations'     => $stats['conversations'],
                    'messages'          => $stats['messages'],
                    'errors'            => $stats['errors'],
                    'avg_response_time' => $stats['avg_response_time'],
                ),
                array( '%s', '%d', '%d', '%d', '%f' )
            );
        }

        delete_transient( self::TODAY_KEY );
    }

    /**
     * Get stats for date range
     *
     * @param string $start Start date (Y-m-d)
     * @param string $end   End date (Y-m-d)
     * @return array
     */
    public function get_range( $start, $end ) {
        global $wpdb;

        $table = $wpdb->prefix . 'ofac_stats';

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE date BETWEEN %s AND %s ORDER BY date ASC",
                $start,
                $end
            ),
            ARRAY_A
        );

        // Include today's stats if in range
        $today = date( 'Y-m-d' );
        if ( $today >= $start && $today <= $end ) {
            $today_stats = self::get_today_stats();
            $results[]   = $today_stats;
        }

        return $results;
    }

    /**
     * Get summary stats for a date range
     *
     * @param string $start Start date (Y-m-d).
     * @param string $end   End date (Y-m-d).
     * @return array
     */
    public function get_summary( $start, $end ) {
        $stats = $this->get_range( $start, $end );

        $summary = array(
            'total_conversations' => 0,
            'total_messages'      => 0,
            'total_errors'        => 0,
            'avg_response_time'   => 0,
            'error_rate'          => 0,
            'daily_average'       => 0,
        );

        if ( empty( $stats ) ) {
            return $summary;
        }

        $response_times = array();

        foreach ( $stats as $day ) {
            $summary['total_conversations'] += isset( $day['conversations'] ) ? (int) $day['conversations'] : 0;
            $summary['total_messages']      += isset( $day['messages'] ) ? (int) $day['messages'] : 0;
            $summary['total_errors']        += isset( $day['errors'] ) ? (int) $day['errors'] : 0;

            if ( isset( $day['avg_response_time'] ) && $day['avg_response_time'] > 0 ) {
                $response_times[] = (float) $day['avg_response_time'];
            }
        }

        // Calculate averages
        if ( ! empty( $response_times ) ) {
            $summary['avg_response_time'] = array_sum( $response_times ) / count( $response_times );
        }

        if ( $summary['total_messages'] > 0 ) {
            $summary['error_rate'] = ( $summary['total_errors'] / $summary['total_messages'] ) * 100;
        }

        $days_count = max( 1, count( $stats ) );
        $summary['daily_average'] = $summary['total_conversations'] / $days_count;

        return $summary;
    }

    /**
     * Get chart data for visualization
     *
     * @param string $start Start date (Y-m-d).
     * @param string $end   End date (Y-m-d).
     * @return array
     */
    public function get_chart_data( $start, $end ) {
        $stats = $this->get_range( $start, $end );

        // Index stats by date for easy lookup
        $stats_by_date = array();
        foreach ( $stats as $day ) {
            if ( isset( $day['date'] ) ) {
                $stats_by_date[ $day['date'] ] = $day;
            }
        }

        $chart_data = array(
            'labels'        => array(),
            'conversations' => array(),
            'messages'      => array(),
            'errors'        => array(),
            'response_time' => array(),
        );

        // Create array with all dates
        $current = strtotime( $start );
        $end_ts  = strtotime( $end );

        while ( $current <= $end_ts ) {
            $date = date( 'Y-m-d', $current );
            $chart_data['labels'][] = date( 'd/m', $current );

            if ( isset( $stats_by_date[ $date ] ) ) {
                $day_data = $stats_by_date[ $date ];
                $chart_data['conversations'][] = isset( $day_data['conversations'] ) ? (int) $day_data['conversations'] : 0;
                $chart_data['messages'][]      = isset( $day_data['messages'] ) ? (int) $day_data['messages'] : 0;
                $chart_data['errors'][]        = isset( $day_data['errors'] ) ? (int) $day_data['errors'] : 0;
                $chart_data['response_time'][] = isset( $day_data['avg_response_time'] ) ? (float) $day_data['avg_response_time'] : 0;
            } else {
                $chart_data['conversations'][] = 0;
                $chart_data['messages'][]      = 0;
                $chart_data['errors'][]        = 0;
                $chart_data['response_time'][] = 0;
            }

            $current = strtotime( '+1 day', $current );
        }

        return $chart_data;
    }

    /**
     * Get today's stats for display
     *
     * @return array
     */
    public function get_today() {
        $today_stats = self::get_today_stats();

        return array(
            'conversations'     => isset( $today_stats['conversations'] ) ? (int) $today_stats['conversations'] : 0,
            'messages'          => isset( $today_stats['messages'] ) ? (int) $today_stats['messages'] : 0,
            'errors'            => isset( $today_stats['errors'] ) ? (int) $today_stats['errors'] : 0,
            'avg_response_time' => isset( $today_stats['avg_response_time'] ) ? (float) $today_stats['avg_response_time'] : 0,
        );
    }
}
