<?php
/**
 * Session Manager — stores conversation state in WooCommerce sessions.
 *
 * @package AI_Price_Negotiator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AIPN_Session_Manager {

    private const SESSION_KEY     = 'aipn_negotiation';
    private const EXPIRY_SECONDS  = 1800; // 30 minutes.

    /**
     * Get the current session or create a new one.
     */
    public function get_or_create( AIPN_Cart_Analyzer $cart_analyzer ): array {
        $this->ensure_wc_session();

        $session = WC()->session->get( self::SESSION_KEY );

        if ( is_array( $session ) ) {
            // Expire after inactivity.
            if ( time() - ( $session['last_activity'] ?? 0 ) > self::EXPIRY_SECONDS ) {
                $this->end_session( $session, 'expired' );
                $session = null;
            }
            // Invalidate if cart changed.
            elseif ( WC()->cart && $session['cart_hash'] !== WC()->cart->get_cart_hash() ) {
                $this->end_session( $session, 'abandoned' );
                $session = null;
            }
            // Session already completed — don't reuse.
            elseif ( isset( $session['status'] ) && $session['status'] !== 'active' ) {
                $session = null;
            }
        }

        if ( ! is_array( $session ) ) {
            $session = $this->create_new( $cart_analyzer );
        }

        return $session;
    }

    /**
     * Get existing session without creating.
     */
    public function get_existing(): ?array {
        $this->ensure_wc_session();

        $session = WC()->session->get( self::SESSION_KEY );

        if ( ! is_array( $session ) ) {
            return null;
        }

        // For completed sessions (accepted/rejected/expired).
        if ( isset( $session['status'] ) && $session['status'] !== 'active' ) {

            // Only show accepted state if coupon is actually applied in the cart right now.
            if ( $session['status'] === 'accepted' && ! empty( $session['coupon_code'] ) && WC()->cart ) {
                $applied_coupons = WC()->cart->get_applied_coupons();
                if ( in_array( strtolower( $session['coupon_code'] ), array_map( 'strtolower', $applied_coupons ), true ) ) {
                    return $session;
                }
            }

            // Coupon not applied, different status, or expired — clear it and start fresh.
            WC()->session->set( self::SESSION_KEY, null );
            return null;
        }

        // Active session: check expiry.
        if ( time() - ( $session['last_activity'] ?? 0 ) > self::EXPIRY_SECONDS ) {
            WC()->session->set( self::SESSION_KEY, null );
            return null;
        }

        return $session;
    }

    /**
     * Persist session updates.
     */
    public function update( array $session ): void {
        $session['last_activity'] = time();
        WC()->session->set( self::SESSION_KEY, $session );
    }

    /**
     * Add a message to the conversation.
     */
    public function add_message( array &$session, string $role, string $content ): void {
        $session['conversation'][] = array(
            'role'    => $role,
            'content' => $content,
        );
    }

    /**
     * Increment the turn count (called once per user message, regardless of offer).
     */
    public function increment_turn( array &$session ): void {
        $session['turn_count']++;
    }

    /**
     * Record a customer offer (does NOT increment turn count — that's done separately).
     */
    public function record_offer( array &$session, float $offer ): void {
        $session['offers'][]            = $offer;
        $session['best_customer_offer'] = max( $session['best_customer_offer'], $offer );
    }

    /**
     * Record an AI counter-offer.
     */
    public function record_counter_offer( array &$session, float $counter ): void {
        $session['counter_offers'][] = $counter;
    }

    /**
     * Mark the session as accepted.
     */
    public function accept( array &$session, float $accepted_price, string $coupon_code ): void {
        $session['status']         = 'accepted';
        $session['accepted_price'] = $accepted_price;
        $session['coupon_code']    = $coupon_code;
        $session['completed_at']   = time();
        $this->update( $session );

        do_action( 'aipn_session_ended', $session );
    }

    /**
     * End negotiation with a given status (public wrapper for end_session).
     */
    public function end_negotiation( array &$session, string $status ): void {
        $this->end_session( $session, $status );
    }

    /**
     * Destroy the current session.
     */
    public function destroy(): void {
        $this->ensure_wc_session();
        WC()->session->set( self::SESSION_KEY, null );
    }

    /**
     * Ensure WC session is available.
     */
    private function ensure_wc_session(): void {
        if ( function_exists( 'WC' ) && WC()->session && ! WC()->session->has_session() ) {
            WC()->session->set_customer_session_cookie( true );
        }
    }

    /**
     * Create a fresh negotiation session.
     */
    private function create_new( AIPN_Cart_Analyzer $cart_analyzer ): array {
        $cart_data = $cart_analyzer->analyze();

        // Auto-fill email for logged-in users — skip the email capture form.
        $customer_email = '';
        $customer_name  = '';
        $email_captured = false;

        if ( is_user_logged_in() ) {
            $user           = wp_get_current_user();
            $customer_email = $user->user_email;
            $customer_name  = $user->display_name;
            $email_captured = true;
        }

        $session = array(
            'session_id'          => 'aipn_' . wp_generate_password( 12, false ),
            'cart_hash'           => WC()->cart ? WC()->cart->get_cart_hash() : '',
            'started_at'          => time(),
            'last_activity'       => time(),
            'turn_count'          => 0,
            'offers'              => array(),
            'counter_offers'      => array(),
            'best_customer_offer' => 0.0,
            'status'              => 'active',
            'accepted_price'      => 0.0,
            'coupon_code'         => '',
            'completed_at'        => 0,
            'cart_total'          => $cart_data['cart_total'],
            'floor_total'         => $cart_data['floor_total'],
            'conversation'        => array(),
            'cart_items'          => $cart_data['items'],
            'customer_email'      => $customer_email,
            'customer_name'       => $customer_name,
            'email_captured'      => $email_captured,
        );

        WC()->session->set( self::SESSION_KEY, $session );

        return $session;
    }

    /**
     * End a session with a given status and fire the event.
     */
    private function end_session( array $session, string $status ): void {
        $session['status']       = $status;
        $session['completed_at'] = time();

        do_action( 'aipn_session_ended', $session );

        $this->ensure_wc_session();
        WC()->session->set( self::SESSION_KEY, null );
    }
}
