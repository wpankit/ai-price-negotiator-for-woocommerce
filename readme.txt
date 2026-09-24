=== AI Price Negotiator for WooCommerce ===
Contributors: ankitmaru, siapanchal
Tags: woocommerce, ai chatbot, price negotiation, make an offer, dynamic pricing
Requires at least: 5.8
Tested up to: 7.1
Stable tag: 0.0.1
Requires PHP: 7.4
Requires Plugins: woocommerce
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Let shoppers make an offer on their cart at checkout. An AI negotiator counters within your price limits and applies the deal as a coupon.

== Description ==

AI Price Negotiator adds a "Want a better deal?" box to your WooCommerce checkout. Shoppers make an offer on their whole cart, and an AI negotiator replies like a good salesperson: it counters, holds your minimum and closes the deal. When both sides agree, the plugin creates a one-time coupon and applies it to the cart, and the checkout total updates.

You stay in control. The negotiator never sells below the floor prices you set, for the whole store or for each product, and the plugin checks every agreed price on your server before it creates a coupon. Every conversation is saved, so you can see how each deal was made.

AI Price Negotiator is made by [WPAnkit](https://wpankit.com/) and is free: every feature is included, with no premium version, upsells or locked features. It uses your own OpenAI API key, and OpenAI bills you directly for what you use. The code is open source on [GitHub](https://github.com/wpankit/ai-price-negotiator-for-woocommerce), and you can learn more at [negotiato.com](https://negotiato.com/).

= How it works =

1. A shopper adds products to the cart and goes to checkout.
2. A "Want a better deal?" box appears on the checkout page.
3. The shopper makes an offer on the cart.
4. The AI negotiator counters, within the limits you set.
5. When they agree, a one-time coupon is applied and the total updates.
6. The shopper completes checkout at the agreed price.

= Negotiation =

* Negotiates on the whole cart, at checkout. It is not a general chatbot.
* A global floor price (90% of the price by default), plus a floor price, cost price and an on/off switch on each product.
* Agreed prices are checked on your server: never below your floor, never above the cart total.
* One-time coupons, limited to the shopper's email and the products in the cart, with an expiry you choose.
* Choose the negotiator's name, its personality (friendly, professional, playful or firm), how much of your margin it can use and how firmly it counters.
* Add your own rules in plain English, such as "Never offer free shipping as part of a deal."
* Product suggestions during the negotiation, with an Add to Cart button.
* Extra room for multiples and large carts, optional urgency messages such as low stock, and a limit on the number of rounds.
* Remembers returning negotiators, with a cooldown you choose.
* The conversation carries on after a page reload.

= Your store, your rules =

* Show the negotiation box only when you want: by cart value or size, product category, guests or logged-in customers, user role, past orders or spend, country, device, new or returning visitors, and day and time.
* Match your store: colors, fonts, icon, corner radius, shadow, width and every piece of text.
* Works with the Checkout block and the classic checkout.
* Translation ready.

= Analytics =

* Negotiations, acceptance rate, average discount, revenue from negotiations and abandoned chats, for the last 7, 30 or 90 days.
* The products shoppers negotiate on most.
* Every chat transcript, and a CSV export.
* The deal, the transcript and a per-product discount breakdown on each order (classic checkout).

= More free plugins by the author =

* [NoteFlow](https://wordpress.org/plugins/noteflow/): notes, checklists and team collaboration in your WordPress admin.
* [Like Dislike](https://wordpress.org/plugins/like-dislike-for-wp/): like and dislike buttons with live counts, "Was this helpful?" votes and stats.
* [Hide Admin Bar Based on User Roles](https://wordpress.org/plugins/hide-admin-bar-based-on-user-roles/): hide the toolbar for the roles you choose.
* [UltimaKit](https://wordpress.org/plugins/ultimakit-for-wp/): admin tools, security and performance in one plugin.
* [Page Visit Counter Analytics](https://wordpress.org/plugins/page-visit-counter-analytics/): simple, privacy-friendly page view stats.
* [Disable Block Editor FullScreen mode](https://wordpress.org/plugins/disable-block-editor-fullscreen-mode/): open the block editor with the admin menu in view.

== Installation ==

1. In your WordPress admin, go to **Plugins → Add New Plugin**, search for "AI Price Negotiator", then install and activate it. WooCommerce must be active.
2. Go to **AI Negotiator → Settings**, paste your OpenAI API key and click **Test API Key**.
3. Under **General → Pricing Rules**, set the global floor price: the lowest share of the price the negotiator may accept (90% by default).
4. Optionally, edit a product to set its own floor price or cost price, or to turn negotiation off for it, under **Product data → General**.
5. Add a product to your cart and go to the checkout: the "Want a better deal?" box appears there.

== Frequently Asked Questions ==

= Is it really free? =

Yes. Every feature is included, with no premium version and no locked features. You need an OpenAI API key, and OpenAI bills you for what you use. The plugin uses the gpt-4o-mini model and keeps replies short, so each conversation costs very little.

= Do I need an OpenAI account? =

Yes. Create an API key in your OpenAI account and paste it under AI Negotiator → Settings. Until a key is saved, the negotiation box stays hidden.

= Can the AI sell below my minimum price? =

No. The negotiator is told your minimum for the cart, and the plugin checks every agreed price on your server before it creates a coupon: a price below your floor is raised to the floor, and a price above the cart total is lowered to the total.

= How do floor prices work? =

The global floor price is a share of each product's price, 90% by default. You can give any product a fixed floor price instead, or turn negotiation off for it, under Product data → General. The cart's minimum is the sum of its products' floors.

= Does it work with the Checkout block? =

Yes. It works with the Checkout block and with the classic checkout, for guests and logged-in customers.

= Can shoppers negotiate on a single product? =

Not at the moment. The negotiation covers the whole cart, at checkout.

= What happens when a deal is agreed? =

The plugin creates a one-time coupon for the difference, applies it to the cart and updates the checkout total. The coupon only works for that shopper and those products, and it expires after 24 hours unless you change the expiry.

= What does the plugin store? =

Each negotiation is saved in your WordPress database: the conversation, the cart, the prices and, when known, the shopper's name and email. You can read them under AI Negotiator → Analytics & Chat Logs and export them as CSV.

= Where is my API key stored? =

In your WordPress database. It is only sent to OpenAI, with each request.

= How can I contribute? =

Report bugs, suggest features or send pull requests on [GitHub](https://github.com/wpankit/ai-price-negotiator-for-woocommerce).

== Screenshots ==

1. A shopper makes an offer at checkout, and the negotiator counters.
2. Deal agreed: a one-time coupon is applied and the total updates.
3. The negotiator can suggest a product at a special price.
4. The negotiation box waits below the order summary until the shopper opens it.
5. Analytics: negotiations, acceptance rate, discounts and revenue from negotiations.
6. Every conversation is saved, with the cart and the outcome.
7. Paste your OpenAI API key and test it.
8. A floor price, cost price and on/off switch on each product.
9. The negotiator's name, personality, generosity and counter-offer strategy.
10. Your own rules, in plain English.
11. Product suggestions, urgency messages and coupon expiry.
12. Choose when the negotiation box appears.
13. The colors of the chat.
14. Every piece of text in the negotiation box.

== External services ==

This plugin connects to two external services.

**OpenAI API**, which writes the negotiator's replies.

* When: each time a shopper opens the negotiation box or sends a message, and when you click Test API Key in the settings.
* What is sent: your API key; your store's name; the products in the cart with their prices and quantities; the lowest and target prices for the cart; your negotiation settings and custom rules; products the negotiator may suggest; the conversation so far; and the shopper's name, when the plugin knows it from their account, the checkout form or the negotiation box. The shopper's email, address, phone number and IP address are not sent.
* [OpenAI terms of use](https://openai.com/policies/terms-of-use) and [privacy policy](https://openai.com/policies/privacy-policy).

**Google Fonts**, only if you choose a Google font for the negotiation box.

* When: if you pick Inter, Roboto, Open Sans, Lato, Poppins, Nunito or Montserrat under AI Negotiator → Settings → Advanced → Typography, shoppers' browsers load that font from Google on the checkout page. The default, "Inherit from theme", loads nothing from Google.
* What is sent: the shopper's IP address and browser details, as with any file loaded from Google.
* [Google terms of service](https://policies.google.com/terms) and [privacy policy](https://policies.google.com/privacy).

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
