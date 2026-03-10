<?php
/**
 * Product meta fields — floor price and negotiation toggle.
 *
 * @package AI_Price_Negotiator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AIPN_Product_Meta {

    public const META_FLOOR_PRICE         = '_aipn_floor_price';
    public const META_COST_PRICE          = '_aipn_cost_price';
    public const META_NEGOTIATION_ENABLED = '_aipn_negotiation_enabled';

    /**
     * Register hooks.
     */
    public function register(): void {
        add_action( 'woocommerce_product_options_pricing', array( $this, 'render_fields' ) );
        add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_fields' ) );
    }

    /**
     * Render floor price and negotiation toggle in product pricing tab.
     */
    public function render_fields(): void {
        echo '<div class="options_group aipn-product-options">';

        woocommerce_wp_checkbox(
            array(
                'id'          => self::META_NEGOTIATION_ENABLED,
                'label'       => __( 'Enable Negotiation', 'ai-price-negotiator-for-woocommerce' ),
                'description' => __( 'Allow customers to negotiate the price of this product at checkout.', 'ai-price-negotiator-for-woocommerce' ),
                'value'       => get_post_meta( get_the_ID(), self::META_NEGOTIATION_ENABLED, true ) ?: 'yes',
                'cbvalue'     => 'yes',
            )
        );

        woocommerce_wp_text_input(
            array(
                'id'                => self::META_COST_PRICE,
                'label'             => __( 'Cost Price', 'ai-price-negotiator-for-woocommerce' ) . ' (' . get_woocommerce_currency_symbol() . ')',
                'desc_tip'          => true,
                'description'       => __( 'Your actual cost for this product. Used to calculate protected margin during negotiations. Leave empty if unknown.', 'ai-price-negotiator-for-woocommerce' ),
                'type'              => 'number',
                'custom_attributes' => array(
                    'step' => '0.01',
                    'min'  => '0',
                ),
                'data_type'         => 'price',
            )
        );

        woocommerce_wp_text_input(
            array(
                'id'                => self::META_FLOOR_PRICE,
                'label'             => __( 'Floor Price', 'ai-price-negotiator-for-woocommerce' ) . ' (' . get_woocommerce_currency_symbol() . ')',
                'desc_tip'          => true,
                'description'       => __( 'Minimum acceptable price for AI negotiations. Leave empty to use the global floor price percentage.', 'ai-price-negotiator-for-woocommerce' ),
                'type'              => 'number',
                'custom_attributes' => array(
                    'step' => '0.01',
                    'min'  => '0',
                ),
                'data_type'         => 'price',
            )
        );

        echo '</div>';
    }

    /**
     * Save product meta fields.
     */
    public function save_fields( $product ): void {
        if (
            ! isset( $_POST['woocommerce_meta_nonce'] ) ||
            ! wp_verify_nonce(
                sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ),
                'woocommerce_save_data'
            )
        ) {
            return;
        }

        // Negotiation enabled toggle.
        $enabled = isset( $_POST[ self::META_NEGOTIATION_ENABLED ] ) ? 'yes' : 'no';
        $product->update_meta_data( self::META_NEGOTIATION_ENABLED, $enabled );

        // Cost price.
        $cost_raw = isset( $_POST[ self::META_COST_PRICE ] )
            ? wc_clean( sanitize_text_field( wp_unslash( $_POST[ self::META_COST_PRICE ] ) ) )
            : '';

        if ( $cost_raw === '' || $cost_raw === false ) {
            $product->delete_meta_data( self::META_COST_PRICE );
        } else {
            $cost_price = function_exists( 'wc_format_decimal' )
                ? wc_format_decimal( $cost_raw )
                : (float) $cost_raw;
            $product->update_meta_data( self::META_COST_PRICE, $cost_price );
        }

        // Floor price.
        $raw = isset( $_POST[ self::META_FLOOR_PRICE ] )
            ? wc_clean( sanitize_text_field( wp_unslash( $_POST[ self::META_FLOOR_PRICE ] ) ) )
            : '';

        if ( $raw === '' || $raw === false ) {
            $product->delete_meta_data( self::META_FLOOR_PRICE );
        } else {
            $price = function_exists( 'wc_format_decimal' )
                ? wc_format_decimal( $raw )
                : (float) $raw;
            $product->update_meta_data( self::META_FLOOR_PRICE, $price );
        }
    }
}
