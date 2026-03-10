<?php
/**
 * Visibility Conditions — controls when the negotiation widget appears.
 *
 * @package AI_Price_Negotiator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AIPN_Visibility_Conditions {

    /**
     * Register hooks.
     */
    public function register(): void {
        add_filter( 'aipn_should_show_widget', array( $this, 'evaluate' ), 10, 2 );

        // Set returning-visitor cookie on every page load.
        add_action( 'wp_loaded', array( $this, 'set_visitor_cookie' ) );
    }

    /**
     * Set a cookie to track returning visitors.
     */
    public function set_visitor_cookie(): void {
        if ( ! isset( $_COOKIE['aipn_visited'] ) && ! headers_sent() ) {
            setcookie( 'aipn_visited', '1', time() + ( 30 * DAY_IN_SECONDS ), COOKIEPATH, COOKIE_DOMAIN, is_ssl(), false );
        }
    }

    /**
     * Evaluate all visibility conditions.
     * All active conditions use AND logic — every one must pass.
     */
    public function evaluate( bool $show, array $cart_data ): bool {
        if ( ! $show ) {
            return false;
        }

        // Minimum cart value.
        $min_cart = get_option( 'aipn_visibility_min_cart', '' );
        if ( $min_cart !== '' && (float) $cart_data['cart_total'] < (float) $min_cart ) {
            return false;
        }

        // Maximum cart value.
        $max_cart = get_option( 'aipn_visibility_max_cart', '' );
        if ( $max_cart !== '' && (float) $cart_data['cart_total'] > (float) $max_cart ) {
            return false;
        }

        // Minimum cart items.
        $min_qty = get_option( 'aipn_visibility_min_qty', '' );
        if ( $min_qty !== '' && (int) $cart_data['item_count'] < (int) $min_qty ) {
            return false;
        }

        // Login requirement.
        $login_req = get_option( 'aipn_visibility_login_required', 'anyone' );
        if ( $login_req === 'logged_in' && ! is_user_logged_in() ) {
            return false;
        }
        if ( $login_req === 'guest' && is_user_logged_in() ) {
            return false;
        }

        // Conditions below only apply to logged-in users.
        if ( is_user_logged_in() ) {
            $user_id = get_current_user_id();

            // Minimum past orders.
            $min_orders = get_option( 'aipn_visibility_min_orders', '' );
            if ( $min_orders !== '' ) {
                $order_count = wc_get_customer_order_count( $user_id );
                if ( $order_count < (int) $min_orders ) {
                    return false;
                }
            }

            // Minimum lifetime spend.
            $min_spent = get_option( 'aipn_visibility_min_spent', '' );
            if ( $min_spent !== '' ) {
                $customer = new WC_Customer( $user_id );
                $total_spent = (float) $customer->get_total_spent();
                if ( $total_spent < (float) $min_spent ) {
                    return false;
                }
            }

            // Allowed user roles.
            $allowed_roles = get_option( 'aipn_visibility_user_roles', '' );
            if ( $allowed_roles !== '' ) {
                $roles = array_map( 'trim', explode( ',', strtolower( $allowed_roles ) ) );
                $roles = array_filter( $roles );
                if ( ! empty( $roles ) ) {
                    $user_roles = wp_get_current_user()->roles;
                    if ( empty( array_intersect( $user_roles, $roles ) ) ) {
                        return false;
                    }
                }
            }
        } else {
            // Guest user — if min_orders or min_spent is set, guest can't satisfy it.
            $min_orders = get_option( 'aipn_visibility_min_orders', '' );
            $min_spent  = get_option( 'aipn_visibility_min_spent', '' );
            if ( ( $min_orders !== '' && (int) $min_orders > 0 ) || ( $min_spent !== '' && (float) $min_spent > 0 ) ) {
                return false;
            }
        }

        // Product categories (include).
        $include_cats = get_option( 'aipn_visibility_categories', '' );
        if ( $include_cats !== '' ) {
            $allowed_cats = array_map( 'trim', explode( ',', strtolower( $include_cats ) ) );
            $allowed_cats = array_filter( $allowed_cats );
            if ( ! empty( $allowed_cats ) && ! $this->cart_has_categories( $cart_data['items'], $allowed_cats ) ) {
                return false;
            }
        }

        // Excluded categories.
        $excluded_cats = get_option( 'aipn_visibility_excluded_categories', '' );
        if ( $excluded_cats !== '' ) {
            $blocked_cats = array_map( 'trim', explode( ',', strtolower( $excluded_cats ) ) );
            $blocked_cats = array_filter( $blocked_cats );
            if ( ! empty( $blocked_cats ) && $this->cart_has_categories( $cart_data['items'], $blocked_cats ) ) {
                return false;
            }
        }

        // Schedule — day of week.
        $schedule_days = get_option( 'aipn_visibility_schedule_days', '' );
        if ( $schedule_days !== '' ) {
            $allowed_days = array_map( 'trim', explode( ',', strtolower( $schedule_days ) ) );
            $allowed_days = array_filter( $allowed_days );
            if ( ! empty( $allowed_days ) ) {
                $current_day = strtolower( wp_date( 'D' ) ); // mon, tue, wed, etc.
                if ( ! in_array( $current_day, $allowed_days, true ) ) {
                    return false;
                }
            }
        }

        // Schedule — time window.
        $start_time = get_option( 'aipn_visibility_schedule_start', '' );
        $end_time   = get_option( 'aipn_visibility_schedule_end', '' );
        if ( $start_time !== '' || $end_time !== '' ) {
            $now = wp_date( 'H:i' );
            if ( $start_time !== '' && $now < $start_time ) {
                return false;
            }
            if ( $end_time !== '' && $now > $end_time ) {
                return false;
            }
        }

        // Allowed countries.
        $allowed_countries = get_option( 'aipn_visibility_countries', '' );
        if ( $allowed_countries !== '' ) {
            $countries = array_map( 'trim', explode( ',', strtoupper( $allowed_countries ) ) );
            $countries = array_filter( $countries );
            if ( ! empty( $countries ) ) {
                $customer_country = $this->get_customer_country();
                if ( $customer_country !== '' && ! in_array( $customer_country, $countries, true ) ) {
                    return false;
                }
            }
        }

        // Device type.
        $device = get_option( 'aipn_visibility_device', 'any' );
        if ( $device === 'mobile' && ! wp_is_mobile() ) {
            return false;
        }
        if ( $device === 'desktop' && wp_is_mobile() ) {
            return false;
        }

        // Visitor type (returning vs new — cookie-based).
        $visitor_type = get_option( 'aipn_visibility_visitor_type', 'anyone' );
        if ( $visitor_type === 'returning' && empty( $_COOKIE['aipn_visited'] ) ) {
            return false;
        }
        if ( $visitor_type === 'new' && ! empty( $_COOKIE['aipn_visited'] ) ) {
            return false;
        }

        return true;
    }

    /**
     * Check if any cart item belongs to the given category slugs.
     */
    private function cart_has_categories( array $items, array $category_slugs ): bool {
        foreach ( $items as $item ) {
            $terms = get_the_terms( $item['product_id'], 'product_cat' );
            if ( ! is_array( $terms ) ) {
                continue;
            }
            foreach ( $terms as $term ) {
                if ( in_array( strtolower( $term->slug ), $category_slugs, true ) ) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Get the customer's country from billing address or WC geolocation.
     */
    private function get_customer_country(): string {
        if ( function_exists( 'WC' ) && WC()->customer ) {
            $country = WC()->customer->get_billing_country();
            if ( $country ) {
                return strtoupper( $country );
            }
        }

        // Fallback to WC geolocation.
        if ( class_exists( 'WC_Geolocation' ) ) {
            $geo = WC_Geolocation::geolocate_ip();
            if ( ! empty( $geo['country'] ) ) {
                return strtoupper( $geo['country'] );
            }
        }

        return '';
    }
}
