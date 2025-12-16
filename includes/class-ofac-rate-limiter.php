<?php
/**
 * Rate limiter class
 *
 * @package OcadeFusion_AnythingLLM_Chatbot
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class OFAC_Rate_Limiter
 * Handles request rate limiting
 */
class OFAC_Rate_Limiter {

    /**
     * Transient prefix
     *
     * @var string
     */
    const TRANSIENT_PREFIX = 'ofac_rate_';

    /**
     * Rate limit (requests per minute)
     *
     * @var int
     */
    private $limit;

    /**
     * Window duration in seconds
     *
     * @var int
     */
    private $window = 60;

    /**
     * Constructor
     */
    public function __construct() {
        $settings    = OFAC_Settings::get_instance();
        $this->limit = $settings->get( 'ofac_rate_limit', 30 );
    }

    /**
     * Check if request is allowed
     *
     * @return bool
     */
    public function check() {
        $key   = $this->get_key();
        $count = $this->get_count( $key );

        if ( $count >= $this->limit ) {
            /**
             * Fires when rate limit is exceeded
             *
             * @since 1.0.0
             * @param string $key   Rate limit key
             * @param int    $count Current count
             * @param int    $limit Rate limit
             */
            do_action( 'ofac_rate_limit_exceeded', $key, $count, $this->limit );

            return false;
        }

        $this->increment( $key );
        return true;
    }

    /**
     * Get rate limit key for current request
     *
     * @return string
     */
    private function get_key() {
        $ip = $this->get_client_ip();
        return self::TRANSIENT_PREFIX . md5( $ip );
    }

    /**
     * Get current count for key
     *
     * @param string $key Rate limit key
     * @return int
     */
    private function get_count( $key ) {
        $data = get_transient( $key );

        if ( $data === false ) {
            return 0;
        }

        return (int) $data['count'];
    }

    /**
     * Increment count for key
     *
     * @param string $key Rate limit key
     */
    private function increment( $key ) {
        $data = get_transient( $key );

        if ( $data === false ) {
            $data = array(
                'count'   => 1,
                'started' => time(),
            );
        } else {
            $data['count']++;
        }

        set_transient( $key, $data, $this->window );
    }

    /**
     * Get remaining requests
     *
     * @return int
     */
    public function get_remaining() {
        $key   = $this->get_key();
        $count = $this->get_count( $key );

        return max( 0, $this->limit - $count );
    }

    /**
     * Get time until reset
     *
     * @return int Seconds until reset
     */
    public function get_reset_time() {
        $key  = $this->get_key();
        $data = get_transient( $key );

        if ( $data === false ) {
            return 0;
        }

        $elapsed = time() - $data['started'];
        return max( 0, $this->window - $elapsed );
    }

    /**
     * Reset rate limit for current IP
     */
    public function reset() {
        $key = $this->get_key();
        delete_transient( $key );
    }

    /**
     * Get client IP
     *
     * @return string
     */
    private function get_client_ip() {
        $ip = '';

        if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
        } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }

        // Handle comma-separated IPs
        if ( strpos( $ip, ',' ) !== false ) {
            $ips = explode( ',', $ip );
            $ip  = trim( $ips[0] );
        }

        return $ip;
    }

    /**
     * Get rate limit headers for response
     *
     * @return array
     */
    public function get_headers() {
        return array(
            'X-RateLimit-Limit'     => $this->limit,
            'X-RateLimit-Remaining' => $this->get_remaining(),
            'X-RateLimit-Reset'     => time() + $this->get_reset_time(),
        );
    }
}
