<?php
/**
 * Plugin orchestrator — registers all hooks and wires up services.
 *
 * @package AI_Price_Negotiator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class AIPN_Plugin {

    /** @var AIPN_Settings */
    private $settings;

    /** @var AIPN_Product_Meta */
    private $product_meta;

    /** @var AIPN_Cart_Analyzer */
    private $cart_analyzer;

    /** @var AIPN_Session_Manager */
    private $session_manager;

    /** @var AIPN_Rules_Engine */
    private $rules_engine;

    /** @var AIPN_Prompt_Builder */
    private $prompt_builder;

    /** @var AIPN_Coupon_Manager */
    private $coupon_manager;

    /** @var AIPN_Logger */
    private $logger;

    /** @var AIPN_Chat_Handler */
    private $chat_handler;

    /** @var AIPN_Checkout_Widget */
    private $checkout_widget;

    public function __construct() {
        $this->init_services();
        $this->register_hooks();
        $this->load_advanced_features();
    }

    /**
     * Instantiate all service classes.
     */
    private function init_services(): void {
        $this->settings        = new AIPN_Settings();
        $this->product_meta    = new AIPN_Product_Meta();
        $this->cart_analyzer   = new AIPN_Cart_Analyzer();
        $this->session_manager = new AIPN_Session_Manager();
        $this->rules_engine    = new AIPN_Rules_Engine( $this->cart_analyzer, $this->session_manager );
        $this->prompt_builder  = new AIPN_Prompt_Builder();
        $this->coupon_manager  = new AIPN_Coupon_Manager();
        $this->logger          = new AIPN_Logger();

        $this->chat_handler = new AIPN_Chat_Handler(
            $this->session_manager,
            $this->rules_engine,
            $this->prompt_builder,
            $this->coupon_manager,
            $this->cart_analyzer,
            $this->logger
        );

        $this->checkout_widget = new AIPN_Checkout_Widget( $this->cart_analyzer );
    }

    /**
     * Register all WordPress / WooCommerce hooks.
     */
    private function register_hooks(): void {
        // Admin settings.
        $this->settings->register();

        // Product meta fields.
        $this->product_meta->register();

        // REST API.
        add_action( 'rest_api_init', array( $this->chat_handler, 'register_routes' ) );

        // Coupon validation hooks (billing email persistence for page reloads).
        $this->coupon_manager->register();

        // Checkout widget.
        $this->checkout_widget->register();

        // Logger listens for session end events.
        $this->logger->register();
    }

    /**
     * Load advanced features (formerly pro-only, now available to everyone).
     */
    private function load_advanced_features(): void {
        $pro_loader = AIPN_PLUGIN_DIR . 'pro/class-aipn-pro-loader.php';
        if ( file_exists( $pro_loader ) ) {
            require_once $pro_loader;
            new AIPN_Pro_Loader( $this->rules_engine, $this->logger );
        }
    }
}
