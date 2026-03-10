<?php
/**
 * Checkout negotiation widget template.
 *
 * This template can be overridden by copying it to
 * yourtheme/ai-price-negotiator/checkout-widget.php
 *
 * Available variables:
 *   $aipn_widget_text    - array of customizable text strings from settings.
 *   $aipn_widget_icon    - SVG markup for CTA icon (20px).
 *   $aipn_widget_icon_sm - SVG markup for header icon (18px).
 *
 * @package AI_Price_Negotiator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Ensure $aipn_widget_text is available (safety fallback).
if ( ! isset( $aipn_widget_text ) || ! is_array( $aipn_widget_text ) ) {
    $aipn_widget_text = array(
        'cta_title'         => __( 'Want a better deal?', 'ai-price-negotiator-for-woocommerce' ),
        'cta_subtitle'      => __( 'Make an offer on your cart', 'ai-price-negotiator-for-woocommerce' ),
        'cta_button'        => __( 'Make an Offer', 'ai-price-negotiator-for-woocommerce' ),
        'chat_header'       => __( 'Price Negotiation', 'ai-price-negotiator-for-woocommerce' ),
        'input_placeholder' => __( 'Type your offer or message...', 'ai-price-negotiator-for-woocommerce' ),
        'deal_message'      => __( 'Deal accepted!', 'ai-price-negotiator-for-woocommerce' ),
    );
}

// Ensure icon variables are available (safety fallback).
$aipn_default_icon = '<svg width="%d" height="%d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>';
if ( ! isset( $aipn_widget_icon ) ) {
    $aipn_widget_icon = sprintf( $aipn_default_icon, 20, 20 );
}
if ( ! isset( $aipn_widget_icon_sm ) ) {
    $aipn_widget_icon_sm = sprintf( $aipn_default_icon, 18, 18 );
}
?>

<div id="aipn-negotiation-widget" class="aipn-widget" aria-label="<?php echo esc_attr( $aipn_widget_text['chat_header'] ); ?>">

    <!-- Collapsed state: CTA button -->
    <div class="aipn-widget__cta" id="aipn-cta">
        <div class="aipn-widget__cta-content">
            <span class="aipn-widget__cta-icon" aria-hidden="true">
                <?php echo $aipn_widget_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </span>
            <span class="aipn-widget__cta-text">
                <strong><?php echo esc_html( $aipn_widget_text['cta_title'] ); ?></strong>
                <small><?php echo esc_html( $aipn_widget_text['cta_subtitle'] ); ?></small>
            </span>
            <button type="button" class="aipn-widget__cta-btn" id="aipn-open-btn" aria-expanded="false" aria-controls="aipn-chat-panel">
                <?php echo esc_html( $aipn_widget_text['cta_button'] ); ?>
            </button>
        </div>
    </div>

    <!-- Expanded state: Chat panel -->
    <div class="aipn-widget__panel" id="aipn-chat-panel" role="region" aria-label="<?php echo esc_attr( $aipn_widget_text['chat_header'] ); ?>" hidden>

        <!-- Header -->
        <div class="aipn-widget__header">
            <div class="aipn-widget__header-info">
                <span class="aipn-widget__header-icon" aria-hidden="true">
                    <?php echo $aipn_widget_icon_sm; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </span>
                <span><?php echo esc_html( $aipn_widget_text['chat_header'] ); ?></span>
            </div>
            <button type="button" class="aipn-widget__close" id="aipn-close-btn" aria-label="<?php esc_attr_e( 'Close', 'ai-price-negotiator-for-woocommerce' ); ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>

        <!-- Messages area -->
        <div class="aipn-widget__messages" id="aipn-messages" role="log" aria-live="polite" aria-label="<?php esc_attr_e( 'Chat messages', 'ai-price-negotiator-for-woocommerce' ); ?>">
            <!-- Messages populated by JS -->
        </div>

        <!-- Typing indicator -->
        <div class="aipn-widget__typing" id="aipn-typing" hidden aria-hidden="true">
            <div class="aipn-widget__typing-dots">
                <span></span><span></span><span></span>
            </div>
        </div>

        <!-- Suggestions area (for cross-sell products) -->
        <div class="aipn-widget__suggestions" id="aipn-suggestions" hidden>
            <!-- Populated by JS -->
        </div>

        <!-- Email capture form (shown mid-chat for guests) -->
        <div class="aipn-widget__email-capture" id="aipn-email-capture" hidden>
            <div class="aipn-widget__email-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                    <path d="M22 7l-10 7L2 7"></path>
                </svg>
            </div>
            <p class="aipn-widget__email-text"><?php esc_html_e( 'To lock in your special price, enter your details below:', 'ai-price-negotiator-for-woocommerce' ); ?></p>
            <form class="aipn-widget__email-form" id="aipn-email-form" autocomplete="on">
                <input
                    type="text"
                    id="aipn-capture-name"
                    name="customer_name"
                    placeholder="<?php esc_attr_e( 'Your name', 'ai-price-negotiator-for-woocommerce' ); ?>"
                    class="aipn-widget__email-input"
                    required
                    autocomplete="name"
                />
                <input
                    type="email"
                    id="aipn-capture-email"
                    name="customer_email"
                    placeholder="<?php esc_attr_e( 'Your email', 'ai-price-negotiator-for-woocommerce' ); ?>"
                    class="aipn-widget__email-input"
                    required
                    autocomplete="email"
                />
                <button type="submit" class="aipn-widget__email-btn">
                    <?php esc_html_e( 'Continue Negotiating', 'ai-price-negotiator-for-woocommerce' ); ?>
                </button>
            </form>
        </div>

        <!-- Deal accepted banner -->
        <div class="aipn-widget__deal-banner" id="aipn-deal-banner" hidden>
            <div class="aipn-widget__deal-icon" aria-hidden="true">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
            </div>
            <div class="aipn-widget__deal-text">
                <strong id="aipn-deal-message"><?php echo esc_html( $aipn_widget_text['deal_message'] ); ?></strong>
                <span id="aipn-deal-coupon"></span>
            </div>
        </div>

        <!-- Input form -->
        <form class="aipn-widget__form" id="aipn-form" autocomplete="off">
            <div class="aipn-widget__form-row">
                <div class="aipn-widget__field aipn-widget__field--message">
                    <label for="aipn-message" class="screen-reader-text"><?php esc_html_e( 'Message', 'ai-price-negotiator-for-woocommerce' ); ?></label>
                    <input
                        type="text"
                        id="aipn-message"
                        name="message"
                        placeholder="<?php echo esc_attr( $aipn_widget_text['input_placeholder'] ); ?>"
                        class="aipn-widget__input"
                    />
                </div>
                <button type="submit" class="aipn-widget__send" id="aipn-submit" aria-label="<?php esc_attr_e( 'Send', 'ai-price-negotiator-for-woocommerce' ); ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="22" y1="2" x2="11" y2="13"></line>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                    </svg>
                </button>
            </div>
        </form>
    </div>
</div>
