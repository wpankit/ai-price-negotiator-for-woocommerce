<?php
/**
 * Cart Analyzer — introspects the WooCommerce cart to build negotiation data.
 *
 * @package AI_Price_Negotiator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AIPN_Cart_Analyzer {

    /**
     * Analyze the current cart and return structured data.
     *
     * @return array {
     *     @type array  $items               Cart items with negotiation metadata.
     *     @type float  $cart_total           Total cart value.
     *     @type float  $floor_total          Sum of all floor prices × quantities.
     *     @type int    $item_count           Number of unique items.
     *     @type int    $total_quantity       Total quantity of all items.
     *     @type bool   $has_negotiable       Whether any items are negotiable.
     *     @type float  $negotiable_total     Sum of negotiable items' line totals.
     *     @type float  $non_negotiable_total Sum of non-negotiable items' line totals.
     *     @type string $currency             Currency code.
     *     @type string $currency_symbol      Currency symbol.
     * }
     */
    public function analyze(): array {
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
            return $this->empty_cart_data();
        }

        $items              = array();
        $cart_total         = 0.0;
        $floor_total        = 0.0;
        $cost_total         = 0.0;
        $negotiable_total   = 0.0;
        $global_floor_pct   = (float) get_option( 'aipn_global_floor_pct', 90 );

        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
            /** @var WC_Product $product */
            $product  = $cart_item['data'];
            $quantity = (int) $cart_item['quantity'];
            $price    = (float) $product->get_price();

            // Floor price: per-product meta takes priority, then global %.
            $floor_meta  = get_post_meta( $product->get_id(), AIPN_Product_Meta::META_FLOOR_PRICE, true );
            $floor_price = ( $floor_meta !== '' && $floor_meta !== false )
                ? (float) $floor_meta
                : round( $price * ( $global_floor_pct / 100 ), 2 );

            // Cost price for margin calculations.
            $cost_meta  = get_post_meta( $product->get_id(), AIPN_Product_Meta::META_COST_PRICE, true );
            $cost_price = ( $cost_meta !== '' && $cost_meta !== false ) ? (float) $cost_meta : 0.0;

            $line_total       = round( $price * $quantity, 2 );
            $line_floor_total = round( $floor_price * $quantity, 2 );
            $line_cost_total  = round( $cost_price * $quantity, 2 );

            // Check if negotiation is enabled for this product.
            $negotiation_meta = get_post_meta( $product->get_id(), AIPN_Product_Meta::META_NEGOTIATION_ENABLED, true );
            $is_negotiable    = $negotiation_meta !== 'no'; // Default to yes.

            // If "Allow During Sales" is off, mark sale items as non-negotiable.
            if ( $is_negotiable && $product->is_on_sale() && get_option( 'aipn_allow_during_sales', 'no' ) !== 'yes' ) {
                $is_negotiable = false;
            }

            $items[] = array(
                'cart_item_key' => $cart_item_key,
                'product_id'    => $product->get_id(),
                'name'          => $product->get_name(),
                'price'         => $price,
                'cost_price'    => $cost_price,
                'quantity'      => $quantity,
                'line_total'    => $line_total,
                'line_cost'     => $line_cost_total,
                'floor_price'   => $floor_price,
                'line_floor'    => $line_floor_total,
                'is_negotiable' => $is_negotiable,
                'stock_qty'     => $product->managing_stock() ? $product->get_stock_quantity() : null,
                'image_url'     => wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ) ?: '',
            );

            $cart_total += $line_total;
            $cost_total += $line_cost_total;

            // Non-negotiable items keep full price in floor calculation.
            $floor_total += $is_negotiable ? $line_floor_total : $line_total;

            if ( $is_negotiable ) {
                $negotiable_total += $line_total;
            }
        }

        return array(
            'items'               => $items,
            'cart_total'          => round( $cart_total, 2 ),
            'cost_total'          => round( $cost_total, 2 ),
            'floor_total'         => round( $floor_total, 2 ),
            'item_count'          => count( $items ),
            'total_quantity'      => array_sum( array_column( $items, 'quantity' ) ),
            'has_negotiable'      => count( array_filter( $items, function ( $i ) { return $i['is_negotiable']; } ) ) > 0,
            'negotiable_total'    => round( $negotiable_total, 2 ),
            'non_negotiable_total' => round( $cart_total - $negotiable_total, 2 ),
            'currency'            => get_woocommerce_currency(),
            'currency_symbol'     => get_woocommerce_currency_symbol(),
        );
    }

    /**
     * Empty cart data structure for when no cart is available.
     */
    private function empty_cart_data(): array {
        return array(
            'items'                => array(),
            'cart_total'           => 0.0,
            'cost_total'           => 0.0,
            'floor_total'          => 0.0,
            'item_count'           => 0,
            'total_quantity'       => 0,
            'has_negotiable'       => false,
            'negotiable_total'     => 0.0,
            'non_negotiable_total' => 0.0,
            'currency'             => get_woocommerce_currency(),
            'currency_symbol'      => get_woocommerce_currency_symbol(),
        );
    }
}
