<?php
/**
 * Checkout Widget — injects the negotiation chat on the WooCommerce checkout page.
 * Also provides [aipn_negotiator] shortcode for custom placement.
 *
 * @package AI_Price_Negotiator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AIPN_Checkout_Widget {

    /** @var AIPN_Cart_Analyzer */
    private $cart_analyzer;

    /** @var bool Track if widget has been rendered to prevent duplicates. */
    private $rendered = false;

    /** @var bool Track if assets have been enqueued. */
    private $assets_enqueued = false;

    public function __construct( AIPN_Cart_Analyzer $cart_analyzer ) {
        $this->cart_analyzer = $cart_analyzer;
    }

    /**
     * Register hooks.
     */
    public function register(): void {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp', array( $this, 'attach_widget_hook' ) );
        add_shortcode( 'aipn_negotiator', array( $this, 'shortcode_render' ) );
    }

    /**
     * Attach the widget render on the checkout page.
     */
    public function attach_widget_hook(): void {
        if ( ! is_checkout() ) {
            return;
        }

        // Classic checkout: render after order review.
        add_action( 'woocommerce_checkout_after_order_review', array( $this, 'render_widget' ) );

        // Block checkout fallback — render via wp_footer if classic hook doesn't fire.
        add_action( 'wp_footer', array( $this, 'render_widget_fallback' ) );
    }

    /**
     * Fallback render via wp_footer for block-based checkout.
     * Only outputs if the widget wasn't already rendered by a classic hook or shortcode.
     */
    public function render_widget_fallback(): void {
        if ( $this->rendered ) {
            return;
        }

        $this->render_widget();
    }

    /**
     * Shortcode handler: [aipn_negotiator]
     * Allows developers to place the widget anywhere on the checkout page.
     */
    public function shortcode_render( $atts ): string {
        if ( ! $this->should_show_widget() ) {
            return '';
        }

        // Enqueue assets if not already done (shortcode may be on a non-checkout page).
        $this->do_enqueue_assets();

        // Capture template output.
        ob_start();
        $this->render_widget();
        return ob_get_clean();
    }

    /**
     * Enqueue frontend assets on checkout.
     */
    public function enqueue_assets(): void {
        if ( ! is_checkout() || ! $this->should_show_widget() ) {
            return;
        }

        $this->do_enqueue_assets();
    }

    /**
     * Actually enqueue the CSS/JS (called from both enqueue_assets and shortcode).
     */
    private function do_enqueue_assets(): void {
        if ( $this->assets_enqueued ) {
            return;
        }

        $this->assets_enqueued = true;

        // Appearance options.
        $primary_color    = get_option( 'aipn_primary_color', '#F59E0B' );
        $user_bubble      = get_option( 'aipn_user_bubble_color', '' );
        $assistant_bubble = get_option( 'aipn_assistant_bubble_color', '#f3f4f6' );
        $border_radius    = (int) get_option( 'aipn_border_radius', 12 );
        $font_family      = get_option( 'aipn_font_family', 'system' );
        $font_size        = (int) get_option( 'aipn_font_size', 14 );
        $bubble_font_size = (int) get_option( 'aipn_bubble_font_size', 13 );

        // Text options (empty = use default).
        $text = $this->get_widget_text();

        // Resolve font family to CSS value.
        $font_css = $this->resolve_font_family( $font_family );

        wp_enqueue_style(
            'aipn-checkout-widget',
            AIPN_PLUGIN_URL . 'assets/css/checkout-widget.css',
            array(),
            AIPN_VERSION
        );

        // Load Google Font if needed.
        $google_fonts = array( 'inter', 'roboto', 'open-sans', 'lato', 'poppins', 'nunito', 'montserrat' );
        if ( in_array( $font_family, $google_fonts, true ) ) {
            $font_name = ucfirst( str_replace( '-', '+', $font_family ) );
            if ( $font_family === 'open-sans' ) {
                $font_name = 'Open+Sans';
            }
            wp_enqueue_style(
                'aipn-google-font',
                'https://fonts.googleapis.com/css2?family=' . $font_name . ':wght@400;600;700&display=swap',
                array(),
                AIPN_VERSION
            );
        }

        // Inject CSS custom properties from settings.
        $css_vars = sprintf(
            '--aipn-primary: %s; --aipn-primary-hover: %s; --aipn-radius: %dpx; --aipn-radius-sm: %dpx;',
            esc_attr( $primary_color ),
            esc_attr( $this->darken_color( $primary_color, 15 ) ),
            $border_radius,
            max( 4, $border_radius - 4 )
        );

        // User bubble — falls back to primary if empty.
        $css_vars .= sprintf(
            ' --aipn-msg-user: %s;',
            esc_attr( $user_bubble ?: $primary_color )
        );

        // User bubble text color.
        $user_text_color = get_option( 'aipn_user_text_color', '' );
        if ( $user_text_color ) {
            $css_vars .= sprintf( ' --aipn-msg-user-text: %s;', esc_attr( $user_text_color ) );
        }

        // Assistant bubble.
        if ( $assistant_bubble ) {
            $css_vars .= sprintf( ' --aipn-msg-assistant: %s;', esc_attr( $assistant_bubble ) );
        }

        // Assistant bubble text color.
        $assistant_text_color = get_option( 'aipn_assistant_text_color', '' );
        if ( $assistant_text_color ) {
            $css_vars .= sprintf( ' --aipn-msg-assistant-text: %s;', esc_attr( $assistant_text_color ) );
        }

        // Typography.
        $css_vars .= sprintf( ' --aipn-font: %s;', $font_css );
        $css_vars .= sprintf( ' --aipn-font-size: %dpx;', $font_size );
        $css_vars .= sprintf( ' --aipn-bubble-font-size: %dpx;', $bubble_font_size );

        // Advanced styling options.
        $adv_colors = array(
            'aipn_header_bg_color'     => '--aipn-header-bg',
            'aipn_header_text_color'   => '--aipn-header-text',
            'aipn_widget_bg_color'     => '--aipn-bg',
            'aipn_cta_bg_color'        => '--aipn-cta-bg',
            'aipn_cta_text_color'      => '--aipn-cta-text',
            'aipn_cta_btn_bg_color'    => '--aipn-cta-btn-bg',
            'aipn_cta_btn_text_color'  => '--aipn-cta-btn-text',
            'aipn_send_btn_color'      => '--aipn-send-bg',
            'aipn_widget_border_color' => '--aipn-border',
        );
        foreach ( $adv_colors as $option_key => $css_var ) {
            $val = get_option( $option_key, '' );
            if ( $val !== '' ) {
                $css_vars .= sprintf( ' %s: %s;', $css_var, esc_attr( $val ) );
            }
        }

        // Widget shadow.
        $shadow = get_option( 'aipn_widget_shadow', 'subtle' );
        $shadow_map = array(
            'none'   => 'none',
            'subtle' => '0 1px 3px rgba(0,0,0,0.1), 0 1px 2px rgba(0,0,0,0.06)',
            'medium' => '0 4px 12px rgba(0,0,0,0.1), 0 2px 4px rgba(0,0,0,0.06)',
            'strong' => '0 8px 30px rgba(0,0,0,0.12), 0 4px 8px rgba(0,0,0,0.08)',
        );
        if ( isset( $shadow_map[ $shadow ] ) ) {
            $css_vars .= sprintf( ' --aipn-shadow: %s; --aipn-shadow-lg: %s;', $shadow_map[ $shadow ], $shadow_map[ $shadow ] );
        }

        // Widget width.
        $width = (int) get_option( 'aipn_widget_width', 500 );
        if ( $width && $width !== 500 ) {
            $css_vars .= sprintf( ' --aipn-widget-width: %dpx;', $width );
        }

        wp_add_inline_style( 'aipn-checkout-widget', ':root { ' . $css_vars . ' }' );

        wp_enqueue_script(
            'aipn-checkout-widget',
            AIPN_PLUGIN_URL . 'assets/js/checkout-widget.js',
            array( 'jquery' ),
            AIPN_VERSION,
            true
        );

        wp_localize_script( 'aipn-checkout-widget', 'AIPNNegotiator', array(
            'restUrl'    => esc_url_raw( rest_url( 'aipn/v1' ) ),
            'nonce'      => wp_create_nonce( 'wp_rest' ),
            'cartTotal'  => WC()->cart ? WC()->cart->get_subtotal() : 0,
            'currency'   => get_woocommerce_currency_symbol(),
            'strings'    => array(
                'sending'       => $text['sending_text'],
                'dealAccepted'  => $text['deal_message'],
                'errorGeneric'  => $text['error_message'],
                'couponApplied'  => __( 'Discount applied:', 'ai-price-negotiator-for-woocommerce' ),
                'addToCart'      => __( 'Add to Cart', 'ai-price-negotiator-for-woocommerce' ),
                'cartUpdated'    => __( 'Cart updated!', 'ai-price-negotiator-for-woocommerce' ),
                'emailCaptured'  => __( 'Thanks! Your details have been saved.', 'ai-price-negotiator-for-woocommerce' ),
            ),
        ) );
    }

    /**
     * Get all widget text values (from settings or defaults).
     */
    public function get_widget_text(): array {
        $defaults = array(
            'cta_title'         => __( 'Want a better deal?', 'ai-price-negotiator-for-woocommerce' ),
            'cta_subtitle'      => __( 'Make an offer on your cart', 'ai-price-negotiator-for-woocommerce' ),
            'cta_button'        => __( 'Make an Offer', 'ai-price-negotiator-for-woocommerce' ),
            'chat_header'       => __( 'Price Negotiation', 'ai-price-negotiator-for-woocommerce' ),
            'input_placeholder' => __( 'Type your offer or message...', 'ai-price-negotiator-for-woocommerce' ),
            'deal_message'      => __( 'Deal accepted!', 'ai-price-negotiator-for-woocommerce' ),
            'sending_text'      => __( 'Negotiating...', 'ai-price-negotiator-for-woocommerce' ),
            'error_message'     => __( 'Something went wrong. Please try again.', 'ai-price-negotiator-for-woocommerce' ),
        );

        $text = array();
        foreach ( $defaults as $key => $default ) {
            $option_key  = 'aipn_' . $key;
            $saved_value = get_option( $option_key, '' );
            $text[ $key ] = ! empty( $saved_value ) ? $saved_value : $default;
        }

        return $text;
    }

    /**
     * Render the widget HTML.
     */
    public function render_widget(): void {
        if ( $this->rendered || ! $this->should_show_widget() ) {
            return;
        }

        $this->rendered = true;

        // Make text values available to the template.
        $aipn_widget_text = $this->get_widget_text();

        // Widget icon.
        $icon_key            = get_option( 'aipn_widget_icon', 'chat-bubble' );
        $aipn_widget_icon    = $this->get_widget_icon_svg( $icon_key, 20 );
        $aipn_widget_icon_sm = $this->get_widget_icon_svg( $icon_key, 18 );

        $template = AIPN_PLUGIN_DIR . 'templates/checkout-widget.php';
        if ( file_exists( $template ) ) {
            include $template;
        }
    }

    /**
     * Determine whether the widget should be shown.
     */
    private function should_show_widget(): bool {
        // Global toggle.
        if ( get_option( 'aipn_enabled', 'yes' ) !== 'yes' ) {
            return false;
        }

        // API key required.
        if ( trim( (string) get_option( 'aipn_openai_key', '' ) ) === '' ) {
            return false;
        }

        // Cart must have negotiable items.
        if ( ! WC()->cart || WC()->cart->is_empty() ) {
            return false;
        }

        $cart_data = $this->cart_analyzer->analyze();
        if ( ! $cart_data['has_negotiable'] ) {
            return false;
        }

        return apply_filters( 'aipn_should_show_widget', true, $cart_data );
    }

    /**
     * Resolve font family setting to CSS font-family value.
     */
    private function resolve_font_family( string $key ): string {
        $map = array(
            'inherit'    => 'inherit',
            'system'     => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, sans-serif',
            'inter'      => '"Inter", -apple-system, BlinkMacSystemFont, sans-serif',
            'roboto'     => '"Roboto", -apple-system, BlinkMacSystemFont, sans-serif',
            'open-sans'  => '"Open Sans", -apple-system, BlinkMacSystemFont, sans-serif',
            'lato'       => '"Lato", -apple-system, BlinkMacSystemFont, sans-serif',
            'poppins'    => '"Poppins", -apple-system, BlinkMacSystemFont, sans-serif',
            'nunito'     => '"Nunito", -apple-system, BlinkMacSystemFont, sans-serif',
            'montserrat' => '"Montserrat", -apple-system, BlinkMacSystemFont, sans-serif',
            'georgia'    => 'Georgia, "Times New Roman", Times, serif',
        );

        return $map[ $key ] ?? $map['system'];
    }

    /**
     * Get widget icon SVG markup by key.
     *
     * @param string $key   Icon key.
     * @param int    $size  Icon size in px.
     * @return string SVG markup.
     */
    public function get_widget_icon_svg( string $key = 'chat-bubble', int $size = 20 ): string {
        $icons = array(
            'chat-bubble' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>',
            'chat-dots'   => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path><circle cx="8" cy="10" r="1" fill="currentColor" stroke="none"></circle><circle cx="12" cy="10" r="1" fill="currentColor" stroke="none"></circle><circle cx="16" cy="10" r="1" fill="currentColor" stroke="none"></circle>',
            'price-tag'   => '<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line>',
            'handshake'   => '<path d="M11 17a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h4l2 2h6a1 1 0 0 1 1 1v2"></path><path d="M14 14l2-2 4 4-2 2"></path><path d="M10 14l-2-2-4 4 2 2"></path><path d="M10 18l4-4"></path>',
            'sparkle'     => '<path d="M12 2l2.4 7.2L22 12l-7.6 2.8L12 22l-2.4-7.2L2 12l7.6-2.8z"></path>',
            'megaphone'   => '<path d="M3 11l18-5v12L3 13v-2z"></path><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"></path>',
        );

        $inner = $icons[ $key ] ?? $icons['chat-bubble'];

        $svg = sprintf(
            '<svg width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">%2$s</svg>',
            $size,
            $inner
        );

        return apply_filters( 'aipn_widget_icon_svg', $svg, $key, $size );
    }

    /**
     * Darken a hex color by a percentage.
     */
    private function darken_color( string $hex, int $percent ): string {
        $hex = ltrim( $hex, '#' );
        if ( strlen( $hex ) === 3 ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        $r = max( 0, hexdec( substr( $hex, 0, 2 ) ) - (int) ( 255 * $percent / 100 ) );
        $g = max( 0, hexdec( substr( $hex, 2, 2 ) ) - (int) ( 255 * $percent / 100 ) );
        $b = max( 0, hexdec( substr( $hex, 4, 2 ) ) - (int) ( 255 * $percent / 100 ) );

        return sprintf( '#%02x%02x%02x', $r, $g, $b );
    }
}
