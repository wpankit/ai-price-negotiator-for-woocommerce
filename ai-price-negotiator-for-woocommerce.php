<?php
/**
 * Plugin Name: AI Price Negotiator for WooCommerce
 * Plugin URI: https://negotiato.com/
 * Description: AI-powered checkout negotiation — customers make offers on their entire cart and an AI negotiator closes the deal with smart counter-offers, cross-sells, and dynamic coupons.
 * Version: 0.0.1
 * Author: Ankit Panchal
 * Author URI: https://wpankit.com/
 * Text Domain: ai-price-negotiator-for-woocommerce
 * Domain Path: /languages
 * Requires at least: 5.8
 * Tested up to: 6.9
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * WC requires at least: 7.0
 * WC tested up to: 9.6
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants.
define( 'AIPN_VERSION', '0.0.1' );
define( 'AIPN_PLUGIN_FILE', __FILE__ );
define( 'AIPN_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AIPN_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AIPN_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * All features are available to everyone — no premium gating.
 *
 * This function is kept for backward compatibility with any third-party code
 * that may reference it. It always returns true.
 */
function aipn_is_premium() {
    return true;
}

/**
 * Autoloader: maps AIPN_ prefix to pro/ (advanced) and includes/ directories.
 */
spl_autoload_register( function ( $class ) {
    $prefix = 'AIPN_';

    if ( strpos( $class, $prefix ) !== 0 ) {
        return;
    }

    // Convert class name to file name: AIPN_Rules_Engine → class-aipn-rules-engine.php
    $relative = strtolower( str_replace( '_', '-', substr( $class, strlen( $prefix ) ) ) );
    $filename = 'class-aipn-' . $relative . '.php';

    // Check pro/ (advanced features) first.
    $pro_file = AIPN_PLUGIN_DIR . 'pro/' . $filename;
    if ( file_exists( $pro_file ) ) {
        require_once $pro_file;
        return;
    }

    $file = AIPN_PLUGIN_DIR . 'includes/' . $filename;
    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );

/**
 * Activation hook — create DB tables.
 */
register_activation_hook( __FILE__, function () {
    require_once AIPN_PLUGIN_DIR . 'includes/class-aipn-installer.php';
    AIPN_Installer::activate();
} );

/**
 * Deactivation hook — cleanup if needed.
 */
register_deactivation_hook( __FILE__, function () {
    // Future cleanup tasks (clear transients, etc.)
} );

/**
 * Declare compatibility with WooCommerce features (HPOS, Blocks).
 */
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
    }
} );

/**
 * Initialize the plugin after all plugins are loaded.
 */
add_action( 'plugins_loaded', function () {
    // WooCommerce dependency check.
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p>';
            esc_html_e(
                'AI Price Negotiator for WooCommerce requires WooCommerce to be installed and active.',
                'ai-price-negotiator-for-woocommerce'
            );
            echo '</p></div>';
        } );
        return;
    }

    // Boot the plugin.
    new AIPN_Plugin();
}, 20 );
