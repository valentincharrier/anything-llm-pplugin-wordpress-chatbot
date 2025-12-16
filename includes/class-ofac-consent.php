<?php
/**
 * Consent management class
 *
 * @package OcadeFusion_AnythingLLM_Chatbot
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class OFAC_Consent
 * Handles user consent for GDPR compliance
 */
class OFAC_Consent {

    /**
     * Single instance
     *
     * @var OFAC_Consent|null
     */
    private static $instance = null;

    /**
     * Cookie name
     *
     * @var string
     */
    const COOKIE_NAME = 'ofac_consent';

    /**
     * Cookie duration in days
     *
     * @var int
     */
    const COOKIE_DURATION = 30;

    /**
     * Get single instance
     *
     * @return OFAC_Consent
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
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action( 'wp_ajax_ofac_set_consent', array( $this, 'set_consent' ) );
        add_action( 'wp_ajax_nopriv_ofac_set_consent', array( $this, 'set_consent' ) );
        add_action( 'wp_ajax_ofac_revoke_consent', array( $this, 'revoke_consent' ) );
        add_action( 'wp_ajax_nopriv_ofac_revoke_consent', array( $this, 'revoke_consent' ) );
        add_action( 'wp_ajax_ofac_check_consent', array( $this, 'check_consent_ajax' ) );
        add_action( 'wp_ajax_nopriv_ofac_check_consent', array( $this, 'check_consent_ajax' ) );
    }

    /**
     * Check if user has consented
     *
     * @return bool
     */
    public function has_consent() {
        if ( isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
            $consent_data = json_decode( stripslashes( $_COOKIE[ self::COOKIE_NAME ] ), true );
            
            if ( isset( $consent_data['consented'] ) && $consent_data['consented'] === true ) {
                // Check if consent is still valid
                if ( isset( $consent_data['expires'] ) && $consent_data['expires'] > time() ) {
                    return true;
                }
            }
        }

        // Also check database for logged-in users
        if ( is_user_logged_in() ) {
            $user_id = get_current_user_id();
            $consent = get_user_meta( $user_id, 'ofac_consent', true );
            
            if ( ! empty( $consent ) && isset( $consent['expires'] ) && $consent['expires'] > time() ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Set consent via AJAX
     */
    public function set_consent() {
        if ( ! check_ajax_referer( 'ofac_chat_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Nonce invalide', 'anythingllm-chatbot' ) ), 403 );
        }

        $session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';
        $consent    = isset( $_POST['consent'] ) ? sanitize_text_field( wp_unslash( $_POST['consent'] ) ) : '1';

        if ( empty( $session_id ) ) {
            $session_id = wp_generate_uuid4();
        }

        // Si l'utilisateur refuse le consentement
        if ( $consent === '0' ) {
            $this->delete_consent( $session_id );
            wp_send_json_success( array(
                'message'    => __( 'Consentement refusé', 'anythingllm-chatbot' ),
                'session_id' => $session_id,
                'consented'  => false,
            ) );
            return;
        }

        // Sinon, enregistrer le consentement
        $result = $this->record_consent( $session_id );

        if ( $result ) {
            wp_send_json_success( array(
                'message'    => __( 'Consentement enregistré', 'anythingllm-chatbot' ),
                'session_id' => $session_id,
                'consented'  => true,
            ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Erreur lors de l\'enregistrement', 'anythingllm-chatbot' ) ), 500 );
        }
    }

    /**
     * Record consent
     *
     * @param string $session_id Session ID
     * @return bool
     */
    public function record_consent( $session_id ) {
        $expires = time() + ( self::COOKIE_DURATION * DAY_IN_SECONDS );

        // Set cookie
        $consent_data = array(
            'consented'  => true,
            'session_id' => $session_id,
            'timestamp'  => time(),
            'expires'    => $expires,
        );

        $cookie_set = setcookie(
            self::COOKIE_NAME,
            wp_json_encode( $consent_data ),
            array(
                'expires'  => $expires,
                'path'     => COOKIEPATH,
                'domain'   => COOKIE_DOMAIN,
                'secure'   => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            )
        );

        // Store for logged-in users
        if ( is_user_logged_in() ) {
            update_user_meta( get_current_user_id(), 'ofac_consent', $consent_data );
        }

        // Store in database
        global $wpdb;
        $table = $wpdb->prefix . 'ofac_consents';

        $ip_hash = $this->hash_ip( $this->get_client_ip() );

        $wpdb->insert(
            $table,
            array(
                'session_id'   => $session_id,
                'ip_hash'      => $ip_hash,
                'consented_at' => current_time( 'mysql' ),
                'expires_at'   => date( 'Y-m-d H:i:s', $expires ),
            ),
            array( '%s', '%s', '%s', '%s' )
        );

        /**
         * Fires when consent is recorded
         *
         * @since 1.0.0
         * @param string $session_id Session ID
         * @param array  $consent_data Consent data
         */
        do_action( 'ofac_consent_recorded', $session_id, $consent_data );

        return true;
    }

    /**
     * Revoke consent via AJAX
     */
    public function revoke_consent() {
        if ( ! check_ajax_referer( 'ofac_chat_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Nonce invalide', 'anythingllm-chatbot' ) ), 403 );
        }

        $session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';

        $this->delete_consent( $session_id );

        wp_send_json_success( array( 'message' => __( 'Consentement révoqué', 'anythingllm-chatbot' ) ) );
    }

    /**
     * Delete consent
     *
     * @param string $session_id Session ID
     */
    public function delete_consent( $session_id = '' ) {
        // Delete cookie
        setcookie(
            self::COOKIE_NAME,
            '',
            array(
                'expires'  => time() - YEAR_IN_SECONDS,
                'path'     => COOKIEPATH,
                'domain'   => COOKIE_DOMAIN,
                'secure'   => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            )
        );

        // Delete from user meta
        if ( is_user_logged_in() ) {
            delete_user_meta( get_current_user_id(), 'ofac_consent' );
        }

        // Delete from database
        if ( ! empty( $session_id ) ) {
            global $wpdb;
            $wpdb->delete(
                $wpdb->prefix . 'ofac_consents',
                array( 'session_id' => $session_id ),
                array( '%s' )
            );
        }

        /**
         * Fires when consent is revoked
         *
         * @since 1.0.0
         * @param string $session_id Session ID
         */
        do_action( 'ofac_consent_revoked', $session_id );
    }

    /**
     * Check consent via AJAX
     */
    public function check_consent_ajax() {
        wp_send_json_success( array(
            'has_consent' => $this->has_consent(),
        ) );
    }

    /**
     * Get consent text
     *
     * @return string
     */
    public function get_consent_text() {
        $settings = OFAC_Settings::get_instance();
        $text     = $settings->get( 'ofac_consent_text' );

        if ( empty( $text ) ) {
            $text = __( 'En utilisant ce chatbot, vous acceptez que vos messages soient traités pour vous fournir une assistance. Vos données sont conservées pendant 30 jours.', 'anythingllm-chatbot' );
        }

        /**
         * Filter consent text
         *
         * @since 1.0.0
         * @param string $text Consent text
         */
        return apply_filters( 'ofac_consent_text', $text );
    }

    /**
     * Get privacy policy URL
     *
     * @return string
     */
    public function get_privacy_policy_url() {
        $settings = OFAC_Settings::get_instance();
        $url      = $settings->get( 'ofac_privacy_policy_url' );

        if ( empty( $url ) ) {
            // Try to get WordPress privacy policy page
            $privacy_page_id = get_option( 'wp_page_for_privacy_policy' );
            if ( $privacy_page_id ) {
                $url = get_permalink( $privacy_page_id );
            }
        }

        return $url;
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
     * Hash IP address for anonymization
     *
     * @param string $ip IP address
     * @return string
     */
    private function hash_ip( $ip ) {
        // Anonymize by removing last octet for IPv4 or last 80 bits for IPv6
        if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
            $parts    = explode( '.', $ip );
            $parts[3] = '0';
            $ip       = implode( '.', $parts );
        } elseif ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
            $ip = inet_ntop( inet_pton( $ip ) & inet_pton( 'ffff:ffff:ffff:0000:0000:0000:0000:0000' ) );
        }

        return hash( 'sha256', $ip . wp_salt( 'auth' ) );
    }
}
