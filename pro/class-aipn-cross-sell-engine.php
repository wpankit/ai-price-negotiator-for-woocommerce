<?php
/**
 * Cross-Sell Engine — enriches the rules context with cross-sell/upsell suggestions.
 *
 * @package AI_Price_Negotiator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AIPN_Cross_Sell_Engine {

    /**
     * Register hooks.
     */
    public function register(): void {
        add_filter( 'aipn_rules_context', array( $this, 'enrich_context' ), 10, 3 );
    }

    /**
     * Add cross-sell data to the rules context.
     *
     * Timing rules (Part 2.3 of rulebook):
     * - Never cross-sell during the first 2 messages
     * - Maximum 1 cross-sell suggestion per conversation
     * - Only suggest after the customer has engaged in negotiation
     */
    public function enrich_context( array $context, array $cart_data, array $session ): array {
        if ( get_option( 'aipn_enable_cross_sells', 'yes' ) !== 'yes' ) {
            return $context;
        }

        $current_turn = $session['turn_count'] ?? 0;

        // Timing gate: never suggest in first 2 messages.
        if ( $current_turn < 2 ) {
            return $context;
        }

        // One-shot gate: if AI already suggested a product, don't offer more options.
        if ( ! empty( $session['cross_sell_suggested'] ) ) {
            return $context;
        }

        $suggestions = $this->gather_suggestions( $cart_data['items'] );

        if ( empty( $suggestions ) ) {
            return $context;
        }

        $currency = $context['currency'] ?? html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' );

        $context['cross_sells'] = array(
            'available' => $suggestions,
            'rules'     => array(
                'You may suggest ONE cross-sell or upsell per conversation — never more. This is your one shot.',
                'TIMING: Suggest a product when (a) the customer accepts a deal, (b) their offer is too low and you need a sweetener, or (c) negotiation stalls. Never suggest randomly.',
                'If the customer declines the suggestion, NEVER bring it up again.',
                'When suggesting a product, explain WHY it complements what\'s already in the cart.',
                sprintf( 'Format suggestions naturally: "How about adding the [Product] for just %s[price]? It pairs perfectly with your [cart item]."', $currency ),
                'Only suggest ONE product per message. Don\'t overwhelm the customer.',
                'When suggesting, include the tag [SUGGEST_PRODUCT:PRODUCT_ID:PRICE] at the end of your message, where PRODUCT_ID and PRICE are numbers only.',
                'BUNDLE MARGIN CHECK: A bundle deal must protect minimum margins on EACH product individually. Never subsidize one product\'s margin to make the bundle look good.',
            ),
        );

        return $context;
    }

    /**
     * Gather cross-sell and upsell product suggestions.
     */
    private function gather_suggestions( array $cart_items ): array {
        $cross_sell_ids   = array();
        $cart_product_ids = array_column( $cart_items, 'product_id' );

        foreach ( $cart_items as $item ) {
            $product = wc_get_product( $item['product_id'] );
            if ( ! $product ) {
                continue;
            }

            $cross_sell_ids = array_merge( $cross_sell_ids, $product->get_cross_sell_ids() );
            $cross_sell_ids = array_merge( $cross_sell_ids, $product->get_upsell_ids() );
        }

        // Remove duplicates and items already in cart.
        $cross_sell_ids = array_unique( array_diff( $cross_sell_ids, $cart_product_ids ) );

        // Limit to 3 suggestions.
        $cross_sell_ids = array_slice( $cross_sell_ids, 0, 3 );

        $global_floor_pct = (float) get_option( 'aipn_global_floor_pct', 70 );
        $suggestions      = array();

        foreach ( $cross_sell_ids as $product_id ) {
            $product = wc_get_product( $product_id );
            if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
                continue;
            }

            $price = (float) $product->get_price();
            if ( $price <= 0 ) {
                continue;
            }

            // Calculate floor and special offer price.
            $floor_meta  = get_post_meta( $product_id, '_aipn_floor_price', true );
            $floor_price = ( $floor_meta !== '' && $floor_meta !== false )
                ? (float) $floor_meta
                : round( $price * ( $global_floor_pct / 100 ) * 2 ) / 2;

            // Special price: 10% above floor (a genuine deal for the customer).
            // Use clean rounding to nearest .00 or .50 to avoid odd decimals.
            $special_price = round( ( $floor_price * 1.10 ) * 2 ) / 2;

            // Ensure special price doesn't exceed regular price after rounding.
            if ( $special_price >= $price ) {
                $special_price = round( ( $price * 0.85 ) * 2 ) / 2;
            }

            // Find which cart item this product relates to.
            $related_to = $this->find_relationship( $product, $cart_items );

            $suggestions[] = array(
                'product_id'    => $product_id,
                'name'          => $product->get_name(),
                'price'         => $price,
                'floor_price'   => $floor_price,
                'special_price' => $special_price,
                'related_to'    => $related_to,
                'reason'        => $this->generate_reason( $product, $related_to ),
                'image_url'     => wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ) ?: '',
                'permalink'     => $product->get_permalink(),
            );
        }

        return $suggestions;
    }

    /**
     * Find which cart item a cross-sell product is most related to.
     */
    private function find_relationship( $product, array $cart_items ): string {
        $product_id = $product->get_id();

        foreach ( $cart_items as $item ) {
            $cart_product = wc_get_product( $item['product_id'] );
            if ( ! $cart_product ) {
                continue;
            }

            // Check if this cart item has the suggested product in its cross-sells/upsells.
            $related_ids = array_merge(
                $cart_product->get_cross_sell_ids(),
                $cart_product->get_upsell_ids()
            );

            if ( in_array( $product_id, $related_ids, true ) ) {
                return $item['name'];
            }

            // Same category.
            $product_cats    = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
            $cart_item_cats  = wp_get_post_terms( $item['product_id'], 'product_cat', array( 'fields' => 'ids' ) );

            if ( ! empty( array_intersect( $product_cats, $cart_item_cats ) ) ) {
                return $item['name'];
            }
        }

        // Default to first item.
        return ! empty( $cart_items[0]['name'] ) ? $cart_items[0]['name'] : '';
    }

    /**
     * Generate a human-readable reason for the suggestion.
     */
    private function generate_reason( $product, string $related_to ): string {
        if ( empty( $related_to ) ) {
            return 'Popular complementary product.';
        }

        $reasons = array(
            'Pairs perfectly with the %s in your cart.',
            'Customers who buy %s often add this too.',
            'Great companion for your %s.',
            'Complements your %s nicely.',
        );

        $template = $reasons[ array_rand( $reasons ) ];
        return sprintf( $template, $related_to );
    }
}
