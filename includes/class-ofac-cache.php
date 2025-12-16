<?php
/**
 * Cache class
 *
 * @package OcadeFusion_AnythingLLM_Chatbot
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class OFAC_Cache
 * Handles response caching
 */
class OFAC_Cache {

    /**
     * Cache group
     *
     * @var string
     */
    const CACHE_GROUP = 'ofac_responses';

    /**
     * Settings instance
     *
     * @var OFAC_Settings
     */
    private $settings;

    /**
     * Cache duration
     *
     * @var int
     */
    private $duration;

    /**
     * Whether cache is enabled
     *
     * @var bool
     */
    private $enabled;

    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = OFAC_Settings::get_instance();
        $this->enabled  = $this->settings->get( 'ofac_enable_cache', true );
        $this->duration = $this->settings->get( 'ofac_cache_duration', 3600 );

        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action( 'ofac_cleanup_cache', array( $this, 'cleanup' ) );

        // Schedule cleanup if not already scheduled
        if ( ! wp_next_scheduled( 'ofac_cleanup_cache' ) ) {
            wp_schedule_event( time(), 'daily', 'ofac_cleanup_cache' );
        }
    }

    /**
     * Get cached response
     *
     * @param string $query User query
     * @return mixed|false
     */
    public function get( $query ) {
        if ( ! $this->enabled ) {
            return false;
        }

        $key = $this->generate_key( $query );

        // Try object cache first
        $cached = wp_cache_get( $key, self::CACHE_GROUP );

        if ( $cached !== false ) {
            return $cached;
        }

        // Try transient
        $cached = get_transient( 'ofac_cache_' . $key );

        if ( $cached !== false ) {
            // Store in object cache for faster subsequent access
            wp_cache_set( $key, $cached, self::CACHE_GROUP, $this->duration );
        }

        return $cached;
    }

    /**
     * Set cached response
     *
     * @param string $query User query
     * @param mixed  $response Response data
     * @return bool
     */
    public function set( $query, $response ) {
        if ( ! $this->enabled ) {
            return false;
        }

        $key = $this->generate_key( $query );

        // Store in object cache
        wp_cache_set( $key, $response, self::CACHE_GROUP, $this->duration );

        // Store in transient for persistence
        set_transient( 'ofac_cache_' . $key, $response, $this->duration );

        /**
         * Fires when a response is cached
         *
         * @since 1.0.0
         * @param string $key      Cache key
         * @param mixed  $response Response data
         * @param int    $duration Cache duration
         */
        do_action( 'ofac_cache_set', $key, $response, $this->duration );

        return true;
    }

    /**
     * Delete cached response
     *
     * @param string $query User query
     * @return bool
     */
    public function delete( $query ) {
        $key = $this->generate_key( $query );

        wp_cache_delete( $key, self::CACHE_GROUP );
        delete_transient( 'ofac_cache_' . $key );

        return true;
    }

    /**
     * Clear all cache
     *
     * @return bool
     */
    public function clear() {
        global $wpdb;

        // Clear object cache group
        wp_cache_flush();

        // Clear transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_ofac_cache_%' 
            OR option_name LIKE '_transient_timeout_ofac_cache_%'"
        );

        /**
         * Fires when cache is cleared
         *
         * @since 1.0.0
         */
        do_action( 'ofac_cache_cleared' );

        return true;
    }

    /**
     * Cleanup expired cache entries
     */
    public function cleanup() {
        global $wpdb;

        // Transients are auto-cleaned by WordPress
        // This is for any additional cleanup needed

        /**
         * Fires during cache cleanup
         *
         * @since 1.0.0
         */
        do_action( 'ofac_cache_cleanup' );
    }

    /**
     * Generate cache key from query
     *
     * @param string $query User query
     * @return string
     */
    private function generate_key( $query ) {
        // Normalize query
        $normalized = strtolower( trim( $query ) );

        // Remove extra whitespace
        $normalized = preg_replace( '/\s+/', ' ', $normalized );

        // Include workspace in key for multi-workspace setups
        $workspace = $this->settings->get( 'ofac_workspace_slug', '' );

        return md5( $workspace . ':' . $normalized );
    }

    /**
     * Get cache statistics
     *
     * @return array
     */
    public function get_stats() {
        global $wpdb;

        $count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_ofac_cache_%' 
            AND option_name NOT LIKE '_transient_timeout_%'"
        );

        return array(
            'entries'  => (int) $count,
            'duration' => $this->duration,
            'enabled'  => $this->enabled,
        );
    }

    /**
     * Check if caching is enabled
     *
     * @return bool
     */
    public function is_enabled() {
        return $this->enabled;
    }
}
