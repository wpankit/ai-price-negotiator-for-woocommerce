<?php
/**
 * Advanced Feature Loader — bootstraps all advanced features (available to everyone).
 *
 * @package AI_Price_Negotiator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AIPN_Pro_Loader {

    /** @var AIPN_Rules_Engine */
    private $rules_engine;

    /** @var AIPN_Logger */
    private $logger;

    public function __construct( AIPN_Rules_Engine $rules_engine, AIPN_Logger $logger ) {
        $this->rules_engine = $rules_engine;
        $this->logger       = $logger;

        $this->load_features();
    }

    /**
     * Load all advanced feature classes.
     */
    private function load_features(): void {
        // Advanced settings (adds fields to the WooCommerce settings tab).
        require_once AIPN_PLUGIN_DIR . 'pro/class-aipn-advanced-settings.php';
        $settings = new AIPN_Advanced_Settings();
        $settings->register();

        // Cross-sell engine (hooks into rules context).
        require_once AIPN_PLUGIN_DIR . 'pro/class-aipn-cross-sell-engine.php';
        $cross_sell = new AIPN_Cross_Sell_Engine();
        $cross_sell->register();

        // Advanced rules (volume, urgency).
        require_once AIPN_PLUGIN_DIR . 'pro/class-aipn-advanced-rules.php';
        $advanced_rules = new AIPN_Advanced_Rules();
        $advanced_rules->register();

        // Order meta (negotiation details on order page).
        require_once AIPN_PLUGIN_DIR . 'pro/class-aipn-order-meta.php';
        $order_meta = new AIPN_Order_Meta();
        $order_meta->register();

        // Widget visibility conditions.
        require_once AIPN_PLUGIN_DIR . 'pro/class-aipn-visibility-conditions.php';
        $visibility = new AIPN_Visibility_Conditions();
        $visibility->register();

        // Analytics dashboard.
        require_once AIPN_PLUGIN_DIR . 'pro/class-aipn-analytics.php';
        $analytics = new AIPN_Analytics( $this->logger );
        $analytics->register();
    }
}
