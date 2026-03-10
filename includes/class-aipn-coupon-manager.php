<?php
/**
 * Coupon Manager — creates and applies fixed_cart coupons for negotiations.
 *
 * @package AI_Price_Negotiator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AIPN_Coupon_Manager {

    private const COUPON_PREFIX = 'NEGO-';

    /**
     * Register hooks for coupon validation on page load.
     */
    public function register(): void {
        add_action( 'woocommerce_before_calculate_totals', array( $this, 'set_billing_email_for_nego_coupons' ), 5 );
        add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_cross_sell_special_prices' ), 10 );
    }

    /**
     * Before WC calculates totals (and validates coupons), ensure the billing email
     * is set from our negotiation session. Without this, page reloads fail the
     * customer_email coupon restriction because the guest hasn't filled checkout fields yet.
     */
    public function set_billing_email_for_nego_coupons(): void {
        if ( ! WC()->cart || ! WC()->session || ! WC()->customer ) {
            return;
        }

        $applied_coupons = WC()->cart->get_applied_coupons();
        $has_nego_coupon = false;

        foreach ( $applied_coupons as $code ) {
            if ( stripos( $code, self::COUPON_PREFIX ) === 0 ) {
                $has_nego_coupon = true;
                break;
            }
        }

        if ( ! $has_nego_coupon ) {
            return;
        }

        // Already has a billing email — no need to override.
        if ( WC()->customer->get_billing_email() ) {
            return;
        }

        // Pull email from our negotiation session.
        $session = WC()->session->get( 'aipn_negotiation' );
        if ( is_array( $session ) && ! empty( $session['customer_email'] ) ) {
            WC()->customer->set_billing_email( $session['customer_email'] );
        }
    }

    /**
     * Apply special prices to cross-sell items added via the negotiation widget.
     */
    public function apply_cross_sell_special_prices(): void {
        if ( ! WC()->cart ) {
            return;
        }

        foreach ( WC()->cart->get_cart() as $cart_item ) {
            if ( ! empty( $cart_item['aipn_special_price'] ) ) {
                $special_price = (float) $cart_item['aipn_special_price'];
                if ( $special_price > 0 ) {
                    $cart_item['data']->set_price( $special_price );
                }
            }
        }
    }

    /**
     * Create a cart-level coupon for a negotiated discount.
     *
     * @param float  $discount       Total discount amount.
     * @param string $session_id     Negotiation session ID.
     * @param array  $cart_items     Cart items snapshot for per-product breakdown.
     * @param string $customer_email Customer email to restrict the coupon to.
     *
     * @return string|WP_Error Coupon code or error.
     */
    public function create_cart_coupon( float $discount, string $session_id, array $cart_items = array(), string $customer_email = '' ) {
        $coupon_code = $this->generate_unique_code();

        $coupon_id = wp_insert_post(
            array(
                'post_title'  => $coupon_code,
                'post_type'   => 'shop_coupon',
                'post_status' => 'publish',
                'post_author' => get_current_user_id() ?: 1,
            ),
            true
        );

        if ( is_wp_error( $coupon_id ) ) {
            return $coupon_id;
        }

        // Coupon configuration — fixed cart discount.
        update_post_meta( $coupon_id, 'discount_type', 'fixed_cart' );
        update_post_meta( $coupon_id, 'coupon_amount', wc_format_decimal( $discount ) );
        update_post_meta( $coupon_id, 'usage_limit', 1 );
        update_post_meta( $coupon_id, 'usage_limit_per_user', 1 );
        update_post_meta( $coupon_id, 'individual_use', 'yes' );
        $expiry_hours = (int) get_option( 'aipn_coupon_expiry_hours', 24 );
        $expiry_hours = max( 1, $expiry_hours ); // At least 1 hour.
        update_post_meta( $coupon_id, 'date_expires', strtotime( '+' . $expiry_hours . ' hours' ) );

        // Restrict coupon to the negotiator's email (WC native validation at checkout).
        if ( $customer_email !== '' && is_email( $customer_email ) ) {
            update_post_meta( $coupon_id, 'customer_email', array( strtolower( $customer_email ) ) );
        }

        // Restrict coupon to the exact products in the cart during negotiation.
        if ( ! empty( $cart_items ) ) {
            $product_ids = array_unique( array_filter( array_column( $cart_items, 'product_id' ) ) );
            if ( ! empty( $product_ids ) ) {
                update_post_meta( $coupon_id, 'product_ids', array_map( 'intval', $product_ids ) );
            }
        }

        // Custom meta for tracking.
        update_post_meta( $coupon_id, '_aipn_session_id', $session_id );
        update_post_meta( $coupon_id, '_aipn_negotiation', wp_json_encode( array(
            'discount'     => $discount,
            'session_id'   => $session_id,
            'breakdown'    => $this->calculate_per_product_breakdown( $discount, $cart_items ),
            'created_at'   => current_time( 'mysql' ),
        ) ) );

        return $coupon_code;
    }

    /**
     * Apply a coupon to the active WooCommerce cart.
     *
     * @param string $coupon_code Coupon code.
     * @return bool Whether the coupon was applied.
     */
    public function apply_to_cart( string $coupon_code, string $customer_email = '' ): bool {
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
            return false;
        }

        // Ensure cart session is loaded.
        if ( function_exists( 'wc_load_cart' ) ) {
            wc_load_cart();
        }

        // Set billing email on the WC customer so the email restriction passes validation.
        // Without this, guests who haven't filled the checkout form yet get rejected.
        if ( $customer_email !== '' && WC()->customer ) {
            WC()->customer->set_billing_email( $customer_email );
        }

        return WC()->cart->apply_coupon( $coupon_code );
    }

    /**
     * Calculate proportional per-product discount breakdown.
     *
     * @param float $total_discount Total discount to distribute.
     * @param array $cart_items     Cart items from the cart analyzer.
     * @return array Per-product breakdown.
     */
    public function calculate_per_product_breakdown( float $total_discount, array $cart_items ): array {
        $breakdown  = array();
        $cart_total = array_sum( array_column( $cart_items, 'line_total' ) );

        if ( $cart_total <= 0 ) {
            return $breakdown;
        }

        $allocated = 0.0;

        foreach ( $cart_items as $index => $item ) {
            $proportion = $item['line_total'] / $cart_total;
            $is_last    = $index === count( $cart_items ) - 1;

            // Last item gets the remainder to avoid rounding issues.
            $item_discount = $is_last
                ? round( $total_discount - $allocated, 2 )
                : round( $total_discount * $proportion, 2 );

            $allocated += $item_discount;

            $breakdown[] = array(
                'product_id'     => $item['product_id'],
                'name'           => $item['name'],
                'original_price' => $item['price'],
                'quantity'       => $item['quantity'],
                'line_total'     => $item['line_total'],
                'discount'       => $item_discount,
                'effective_total' => round( $item['line_total'] - $item_discount, 2 ),
            );
        }

        return $breakdown;
    }

    /**
     * Generate a unique coupon code.
     */
    private function generate_unique_code(): string {
        do {
            $code = self::COUPON_PREFIX . strtoupper( wp_generate_password( 10, false, false ) );
        } while ( wc_get_coupon_id_by_code( $code ) );

        return $code;
    }
}
