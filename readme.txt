=== AI Price Negotiator for WooCommerce ===
Contributors: ankitmaru
Tags: woocommerce, negotiation, price offer, ai, dynamic pricing
Requires at least: 5.8
Tested up to: 6.9
Stable tag: 0.0.1
Requires PHP: 7.4
Requires Plugins: woocommerce
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered checkout negotiation — customers make offers on their cart and an AI negotiator closes the deal automatically.

== Description ==

Built by the team behind [PluginStack.dev](https://pluginstack.dev/), AI Price Negotiator for WooCommerce transforms your checkout page into a smart negotiation experience. Instead of losing price-sensitive customers, let them make an offer on their entire cart and watch an AI sales negotiator close the deal.

This plugin is **100% free and open source**. All features are included — no premium tiers, no upsells, no feature locks. Contributions are welcome on [GitHub](https://github.com/wpankit/ai-price-negotiator-for-woocommerce).

**How It Works:**

1. Customer adds products to cart and goes to checkout.
2. A "Want a better deal?" widget appears on the checkout page.
3. Customer makes an offer on the entire cart total.
4. The AI negotiator counter-offers, just like a real salesperson would.
5. When a deal is reached, a coupon is auto-applied and the cart total updates.
6. Customer completes checkout at the negotiated price.

**Core Features:**

* AI-powered negotiation at checkout (not a generic chatbot).
* Cart-level negotiation — one deal for the entire cart.
* Smart rules engine with behavioral analysis.
* Per-product floor prices — set the minimum you'll accept.
* Global floor price percentage — default for all products.
* Auto-generated coupons applied instantly.
* Conversation persistence across page reloads.
* Negotiation logging for insights.
* Configurable widget position and color.
* Fully translatable and accessible.

**Advanced Features (all included):**

* AI personality customization (friendly, professional, playful, firm).
* Cross-sell and upsell AI suggestions during negotiation.
* Volume discount rules and cart-value bonuses.
* Urgency messaging (low stock alerts, session limits).
* Counter-offer strategy control (aggressive, moderate, flexible).
* Negotiation analytics dashboard with conversion metrics.
* Per-order negotiation details with chat transcript.
* Per-product discount breakdown for admin.
* Custom greeting messages.
* Configurable coupon expiry and max negotiation turns.

**Why This Is Different:**

Unlike other quote/negotiation plugins that require manual admin responses, our AI handles the entire negotiation in real-time. The rules engine adapts to customer behavior — detecting lowballers, rewarding upward trends, and knowing when to close the deal. It's like having your best salesperson available 24/7.

**Contribute:**

This is a community-driven project. Found a bug? Have a feature idea? Want to improve the code?
[Contribute on GitHub](https://github.com/wpankit/ai-price-negotiator-for-woocommerce)

== Installation ==

1. Upload `ai-price-negotiator-for-woocommerce` to `/wp-content/plugins/`.
2. Activate the plugin.
3. Go to **WooCommerce → Settings → AI Negotiator** and enter your OpenAI API key.
4. Set the **Global Floor Price %** (default: 70% of product price).
5. Optionally, edit individual products to set specific floor prices.
6. Visit your checkout page with items in cart — the negotiation widget will appear.

== Frequently Asked Questions ==

= Is this plugin really free? =
Yes. All features are included at no cost. There are no premium tiers, no locked features, and no upsells. This is an open-source project.

= Where is the API key stored? =
In your WordPress database only. It is sent directly to OpenAI for generating responses and never shared with third parties.

= Does it work without WooCommerce? =
No, WooCommerce must be active.

= How does the AI negotiate? =
The AI uses a comprehensive rules engine that considers cart value, floor prices, conversation history, customer behavior patterns, and configurable strategies to negotiate like a skilled salesperson.

= Can customers negotiate on individual products? =
Currently, negotiation happens at the cart level on the checkout page. Product-level negotiation is planned for a future update.

= What happens when a deal is accepted? =
A unique one-time coupon is automatically created and applied to the cart. The checkout page updates to show the new total.

= Can I set different floor prices per product? =
Yes. Edit any product and set a specific floor price under Product Data → Pricing. Products without a floor price use the global percentage default.

= Does it work for guests? =
Yes. The plugin uses WooCommerce sessions, which work for both logged-in users and guests.

= How can I contribute? =
Visit our [GitHub repository](https://github.com/wpankit/ai-price-negotiator-for-woocommerce) to report bugs, suggest features, or submit pull requests.

== External Services ==

This plugin connects to the OpenAI API to generate AI negotiation responses.

When a customer uses the negotiation widget, the plugin sends:
- Cart item names, prices, and quantities.
- Negotiation rules and conversation history.
- No customer personal data (name, email, phone, address, IP) is ever sent.

Negotiato Service:
Website: https://negotiato.com/

OpenAI:
Terms: https://openai.com/policies/terms-of-use
Privacy: https://openai.com/policies/privacy-policy

== Changelog ==

= 0.0.1 =
* Initial open-source release — all features free for everyone.
* AI-powered checkout negotiation with smart counter-offers.
* Comprehensive rules engine with behavioral analysis.
* Conversation state management with full history.
* Fixed_cart coupon type for entire cart discounts.
* Global floor price percentage and per-product floor prices.
* Per-product negotiation enable/disable toggle.
* Widget position and color customization.
* Negotiation logging with custom database table.
* Session persistence across page reloads.
* AI personality customization.
* Cross-sell and upsell AI suggestions.
* Volume discount and urgency rules.
* Analytics dashboard with conversion metrics.
* Per-order negotiation details with chat transcript.
