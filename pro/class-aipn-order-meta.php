<?php
/**
 * Order Meta — saves negotiation data to orders and displays it in admin.
 *
 * @package AI_Price_Negotiator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AIPN_Order_Meta {

    private const META_KEY = '_aipn_negotiation_data';

    /**
     * Register hooks.
     */
    public function register(): void {
        // Save negotiation data when order is placed.
        add_action( 'woocommerce_checkout_order_processed', array( $this, 'save_to_order' ), 10, 3 );

        // Display in admin order page.
        add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
    }

    /**
     * Save negotiation session data to the order.
     */
    public function save_to_order( int $order_id, array $posted_data, $order ): void {
        if ( ! function_exists( 'WC' ) || ! WC()->session ) {
            return;
        }

        $session = WC()->session->get( 'aipn_negotiation' );
        if ( ! is_array( $session ) || $session['status'] !== 'accepted' ) {
            return;
        }

        $negotiation_data = array(
            'session_id'      => $session['session_id'],
            'cart_total'      => $session['cart_total'],
            'floor_total'     => $session['floor_total'],
            'accepted_price'  => $session['accepted_price'],
            'discount_amount' => $session['cart_total'] - $session['accepted_price'],
            'coupon_code'     => $session['coupon_code'],
            'turn_count'      => $session['turn_count'],
            'conversation'    => $session['conversation'],
            'cart_items'      => $session['cart_items'],
            'offers'          => $session['offers'],
            'counter_offers'  => $session['counter_offers'],
        );

        // Calculate per-product breakdown.
        $total_discount = $negotiation_data['discount_amount'];
        $cart_items     = $session['cart_items'];
        $cart_total     = $session['cart_total'];
        $breakdown      = array();

        if ( $cart_total > 0 && $total_discount > 0 ) {
            $allocated = 0.0;
            foreach ( $cart_items as $index => $item ) {
                $proportion = $item['line_total'] / $cart_total;
                $is_last    = $index === count( $cart_items ) - 1;
                $discount   = $is_last
                    ? round( $total_discount - $allocated, 2 )
                    : round( $total_discount * $proportion, 2 );
                $allocated += $discount;

                $breakdown[] = array(
                    'product_id'      => $item['product_id'],
                    'name'            => $item['name'],
                    'original_price'  => $item['price'],
                    'quantity'        => $item['quantity'],
                    'line_total'      => $item['line_total'],
                    'discount'        => $discount,
                    'effective_total' => round( $item['line_total'] - $discount, 2 ),
                );
            }
        }

        $negotiation_data['breakdown'] = $breakdown;

        $order->update_meta_data( self::META_KEY, $negotiation_data );
        $order->save();
    }

    /**
     * Add meta box to the order edit page.
     */
    public function add_meta_box( string $post_type ): void {
        $screen = get_current_screen();
        $screen_id = $screen ? $screen->id : '';

        // Support both HPOS and legacy post-based orders.
        $order_screens = array( 'shop_order', 'woocommerce_page_wc-orders' );

        if ( ! in_array( $post_type, $order_screens, true ) && ! in_array( $screen_id, $order_screens, true ) ) {
            return;
        }

        $box_screen = in_array( $screen_id, $order_screens, true ) ? $screen_id : $post_type;

        add_meta_box(
            'aipn_negotiation_details',
            __( 'AI Negotiation Details', 'ai-price-negotiator-for-woocommerce' ),
            array( $this, 'render_meta_box' ),
            $box_screen,
            'side',
            'default'
        );
    }

    /**
     * Render the negotiation details meta box.
     */
    public function render_meta_box( $post_or_order ): void {
        $order = $post_or_order instanceof WP_Post
            ? wc_get_order( $post_or_order->ID )
            : $post_or_order;

        if ( ! $order ) {
            return;
        }

        $data = $order->get_meta( self::META_KEY );

        if ( empty( $data ) ) {
            echo '<p class="description">' . esc_html__( 'No negotiation data for this order.', 'ai-price-negotiator-for-woocommerce' ) . '</p>';
            return;
        }

        $template = AIPN_PLUGIN_DIR . 'templates/admin/negotiation-details.php';
        if ( file_exists( $template ) ) {
            include $template;
        }
    }
}
