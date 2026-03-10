<?php
/**
 * Advanced Settings — section-aware premium fields.
 *
 * Hooks into `aipn_pro_tab_fields` and `aipn_save_pro_settings` actions,
 * receiving the current section slug to render/save only relevant fields.
 *
 * @package AI_Price_Negotiator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AIPN_Advanced_Settings {

    public function register(): void {
        add_action( 'aipn_pro_tab_fields', array( $this, 'render_fields' ) );
        add_action( 'aipn_save_pro_settings', array( $this, 'save_fields' ) );
    }

    public function render_fields( string $section = '' ): void {
        $subsections = $this->get_subsections( $section );

        if ( $subsections ) {
            foreach ( $subsections as $fields ) {
                echo '<div class="aipn-subsection">';
                woocommerce_admin_fields( $fields );
                echo '</div>';
            }
        } else {
            woocommerce_admin_fields( $this->get_fields_for_section( $section ) );
        }
    }

    public function save_fields( string $section = '' ): void {
        woocommerce_update_options( $this->get_fields_for_section( $section ) );
    }

    /* ─── Field routing ───────────────────────────────────────────────── */

    private function get_fields_for_section( string $section ): array {
        switch ( $section ) {
            case 'sales':
                return array_merge( $this->get_cross_sell_fields(), $this->get_urgency_fields() );
            case 'visibility':
                return $this->get_visibility_fields();
            case 'styling':
                return array_merge( $this->get_icon_fields(), $this->get_header_cta_fields(), $this->get_layout_fields() );
            default:
                return $this->get_ai_behavior_fields();
        }
    }

    /**
     * Return an array of field-group arrays for sections with multiple sub-groups.
     * Returns null for single-group sections (rendered flat).
     */
    private function get_subsections( string $section ): ?array {
        switch ( $section ) {
            case 'sales':
                return array( $this->get_cross_sell_fields(), $this->get_urgency_fields() );
            case 'styling':
                return array( $this->get_icon_fields(), $this->get_header_cta_fields(), $this->get_layout_fields() );
            default:
                return null;
        }
    }

    /* ─── AI Behavior ─────────────────────────────────────────────────── */

    private function get_ai_behavior_fields(): array {
        return array(
            array(
                'title' => __( 'AI Behavior', 'ai-price-negotiator-for-woocommerce' ),
                'type'  => 'title',
                'id'    => 'aipn_pro_ai_settings',
                'desc'  => __( 'Fine-tune how the AI negotiator behaves and communicates with customers.', 'ai-price-negotiator-for-woocommerce' ),
            ),
            array(
                'title'   => __( 'Agent Name', 'ai-price-negotiator-for-woocommerce' ),
                'id'      => 'aipn_agent_name',
                'type'    => 'text',
                'desc'    => __( 'The name the AI uses in conversations. Customers will know the negotiator by this name.', 'ai-price-negotiator-for-woocommerce' ),
                'default' => 'Alex',
                'css'     => 'width: 200px;',
            ),
            array(
                'title'   => __( 'Generosity Level', 'ai-price-negotiator-for-woocommerce' ),
                'id'      => 'aipn_generosity_level',
                'type'    => 'select',
                'desc'    => __( 'How much of the negotiable margin the AI can use. Conservative protects more profit; Generous moves more inventory.', 'ai-price-negotiator-for-woocommerce' ),
                'default' => 'conservative',
                'options' => array(
                    'conservative' => __( 'Conservative — Uses 50% of margin (best for luxury/high-margin)', 'ai-price-negotiator-for-woocommerce' ),
                    'moderate'     => __( 'Moderate — Uses 70% of margin (general products)', 'ai-price-negotiator-for-woocommerce' ),
                    'generous'     => __( 'Generous — Uses 90% of margin (clearance/competitive)', 'ai-price-negotiator-for-woocommerce' ),
                ),
            ),
            array(
                'title'   => __( 'AI Personality', 'ai-price-negotiator-for-woocommerce' ),
                'id'      => 'aipn_tone',
                'type'    => 'select',
                'desc'    => __( 'How the AI communicates with customers during negotiations.', 'ai-price-negotiator-for-woocommerce' ),
                'default' => 'friendly',
                'options' => array(
                    'friendly'     => __( 'Friendly — Warm and enthusiastic', 'ai-price-negotiator-for-woocommerce' ),
                    'professional' => __( 'Professional — Business-like and courteous', 'ai-price-negotiator-for-woocommerce' ),
                    'playful'      => __( 'Playful — Fun and witty', 'ai-price-negotiator-for-woocommerce' ),
                    'firm'         => __( 'Firm — Direct and value-focused', 'ai-price-negotiator-for-woocommerce' ),
                ),
            ),
            array(
                'title'             => __( 'Max Negotiation Turns', 'ai-price-negotiator-for-woocommerce' ),
                'id'                => 'aipn_max_turns',
                'type'              => 'number',
                'desc_tip'          => true,
                'desc'              => __( 'Maximum back-and-forth exchanges before the AI makes a final offer.', 'ai-price-negotiator-for-woocommerce' ),
                'default'           => '5',
                'custom_attributes' => array( 'min' => '2', 'max' => '15', 'step' => '1' ),
                'css'               => 'width: 80px;',
            ),
            array(
                'title'   => __( 'Counter-offer Strategy', 'ai-price-negotiator-for-woocommerce' ),
                'id'      => 'aipn_counter_strategy',
                'type'    => 'select',
                'desc'    => __( 'How aggressively the AI protects your margins.', 'ai-price-negotiator-for-woocommerce' ),
                'default' => 'moderate',
                'options' => array(
                    'aggressive' => __( 'Aggressive — Protect margins, small discounts', 'ai-price-negotiator-for-woocommerce' ),
                    'moderate'   => __( 'Moderate — Balanced approach (recommended)', 'ai-price-negotiator-for-woocommerce' ),
                    'flexible'   => __( 'Flexible — Customer-friendly, bigger discounts', 'ai-price-negotiator-for-woocommerce' ),
                ),
            ),
            array(
                'title'   => __( 'Custom Greeting', 'ai-price-negotiator-for-woocommerce' ),
                'id'      => 'aipn_greeting',
                'type'    => 'textarea',
                'desc'    => __( 'Custom greeting style for the AI. Leave empty for default. Example: "Hey there! I see you have some great picks..."', 'ai-price-negotiator-for-woocommerce' ),
                'default' => '',
                'css'     => 'width: 100%; height: 60px;',
            ),
            array(
                'title'             => __( 'Offer Expiry (minutes)', 'ai-price-negotiator-for-woocommerce' ),
                'id'                => 'aipn_offer_expiry_mins',
                'type'              => 'number',
                'desc'              => __( 'The AI will tell customers the final offer is valid for this many minutes. Set to 0 to disable urgency messaging.', 'ai-price-negotiator-for-woocommerce' ),
                'default'           => '30',
                'custom_attributes' => array( 'min' => '0', 'max' => '1440', 'step' => '5' ),
                'css'               => 'width: 80px;',
            ),
            array(
                'title'             => __( 'Cooldown Period (hours)', 'ai-price-negotiator-for-woocommerce' ),
                'id'                => 'aipn_cooldown_hours',
                'type'              => 'number',
                'desc'              => __( 'Time before the same customer can renegotiate. Their previous offer is recalled. Set to 0 to disable.', 'ai-price-negotiator-for-woocommerce' ),
                'default'           => '0',
                'custom_attributes' => array( 'min' => '0', 'max' => '168', 'step' => '1' ),
                'css'               => 'width: 80px;',
            ),
            array(
                'title'   => __( 'Stock-Based Pricing', 'ai-price-negotiator-for-woocommerce' ),
                'id'      => 'aipn_enable_stock_pricing',
                'type'    => 'checkbox',
                'desc'    => __( 'Automatically adjust discount flexibility based on stock levels. Low stock = less discount, overstock = more discount.', 'ai-price-negotiator-for-woocommerce' ),
                'default' => 'no',
            ),
            array(
                'title'   => __( 'Allow During Sales', 'ai-price-negotiator-for-woocommerce' ),
                'id'      => 'aipn_allow_during_sales',
                'type'    => 'checkbox',
                'desc'    => __( 'Allow negotiation on items that are already on sale. If disabled, sale items use the sale price as the floor.', 'ai-price-negotiator-for-woocommerce' ),
                'default' => 'no',
            ),
            array( 'type' => 'sectionend', 'id' => 'aipn_pro_ai_settings' ),
        );
    }

    /* ─── Cross-sell ──────────────────────────────────────────────────── */

    private function get_cross_sell_fields(): array {
        return array(
            array(
                'title' => __( 'Cross-sell & Upsell', 'ai-price-negotiator-for-woocommerce' ),
                'type'  => 'title',
                'id'    => 'aipn_pro_cross_sell_settings',
                'desc'  => __( 'Let the AI suggest additional products during negotiation to increase order value.', 'ai-price-negotiator-for-woocommerce' ),
            ),
            array(
                'title'   => __( 'Enable Cross-sell Suggestions', 'ai-price-negotiator-for-woocommerce' ),
                'id'      => 'aipn_enable_cross_sells',
                'type'    => 'checkbox',
                'desc'    => __( 'Allow the AI to suggest related products during negotiation.', 'ai-price-negotiator-for-woocommerce' ),
                'default' => 'yes',
            ),
            array( 'type' => 'sectionend', 'id' => 'aipn_pro_cross_sell_settings' ),
        );
    }

    /* ─── Urgency ─────────────────────────────────────────────────────── */

    private function get_urgency_fields(): array {
        return array(
            array(
                'title' => __( 'Urgency & Volume', 'ai-price-negotiator-for-woocommerce' ),
                'type'  => 'title',
                'id'    => 'aipn_pro_urgency_settings',
            ),
            array(
                'title'   => __( 'Enable Urgency Messaging', 'ai-price-negotiator-for-woocommerce' ),
                'id'      => 'aipn_enable_urgency',
                'type'    => 'checkbox',
                'desc'    => __( 'AI mentions low stock levels and session time limits to encourage quick decisions.', 'ai-price-negotiator-for-woocommerce' ),
                'default' => 'no',
            ),
            array(
                'title'             => __( 'Coupon Expiry (hours)', 'ai-price-negotiator-for-woocommerce' ),
                'id'                => 'aipn_coupon_expiry_hours',
                'type'              => 'number',
                'desc'              => __( 'How long negotiated coupons remain valid.', 'ai-price-negotiator-for-woocommerce' ),
                'default'           => '24',
                'custom_attributes' => array( 'min' => '1', 'max' => '168', 'step' => '1' ),
                'css'               => 'width: 80px;',
            ),
            array( 'type' => 'sectionend', 'id' => 'aipn_pro_urgency_settings' ),
        );
    }

    /* ─── Widget Visibility ───────────────────────────────────────────── */

    private function get_visibility_fields(): array {
        return array(
            array(
                'title' => __( 'Widget Visibility', 'ai-price-negotiator-for-woocommerce' ),
                'type'  => 'title',
                'id'    => 'aipn_pro_visibility_settings',
                'desc'  => __( 'Control when the negotiation widget appears at checkout. All conditions use AND logic. Leave fields empty for no restriction.', 'ai-price-negotiator-for-woocommerce' ),
            ),
            array( 'title' => __( 'Minimum Cart Value', 'ai-price-negotiator-for-woocommerce' ), 'id' => 'aipn_visibility_min_cart', 'type' => 'number', 'desc' => __( 'Only show widget if cart total is at least this amount.', 'ai-price-negotiator-for-woocommerce' ), 'default' => '', 'placeholder' => __( 'No minimum', 'ai-price-negotiator-for-woocommerce' ), 'custom_attributes' => array( 'min' => '0', 'step' => '1' ), 'css' => 'width: 120px;' ),
            array( 'title' => __( 'Maximum Cart Value', 'ai-price-negotiator-for-woocommerce' ), 'id' => 'aipn_visibility_max_cart', 'type' => 'number', 'desc' => __( 'Hide widget if cart total exceeds this amount.', 'ai-price-negotiator-for-woocommerce' ), 'default' => '', 'placeholder' => __( 'No maximum', 'ai-price-negotiator-for-woocommerce' ), 'custom_attributes' => array( 'min' => '0', 'step' => '1' ), 'css' => 'width: 120px;' ),
            array( 'title' => __( 'Minimum Cart Items', 'ai-price-negotiator-for-woocommerce' ), 'id' => 'aipn_visibility_min_qty', 'type' => 'number', 'desc' => __( 'Only show widget if cart has at least this many unique items.', 'ai-price-negotiator-for-woocommerce' ), 'default' => '', 'placeholder' => __( 'No minimum', 'ai-price-negotiator-for-woocommerce' ), 'custom_attributes' => array( 'min' => '1', 'step' => '1' ), 'css' => 'width: 80px;' ),
            array(
                'title'   => __( 'Customer Login', 'ai-price-negotiator-for-woocommerce' ),
                'id'      => 'aipn_visibility_login_required',
                'type'    => 'select',
                'desc'    => __( 'Who can see the negotiation widget.', 'ai-price-negotiator-for-woocommerce' ),
                'default' => 'anyone',
                'options' => array(
                    'anyone'    => __( 'Anyone (guests & logged-in)', 'ai-price-negotiator-for-woocommerce' ),
                    'logged_in' => __( 'Logged-in customers only', 'ai-price-negotiator-for-woocommerce' ),
                    'guest'     => __( 'Guest customers only', 'ai-price-negotiator-for-woocommerce' ),
                ),
            ),
            array( 'title' => __( 'Minimum Past Orders', 'ai-price-negotiator-for-woocommerce' ), 'id' => 'aipn_visibility_min_orders', 'type' => 'number', 'desc' => __( 'Only show to customers who have placed at least this many orders. (Logged-in only)', 'ai-price-negotiator-for-woocommerce' ), 'default' => '', 'placeholder' => __( 'No minimum', 'ai-price-negotiator-for-woocommerce' ), 'custom_attributes' => array( 'min' => '0', 'step' => '1' ), 'css' => 'width: 80px;' ),
            array( 'title' => __( 'Minimum Lifetime Spend', 'ai-price-negotiator-for-woocommerce' ), 'id' => 'aipn_visibility_min_spent', 'type' => 'number', 'desc' => __( 'Only show to customers who have spent at least this amount in total. (Logged-in only)', 'ai-price-negotiator-for-woocommerce' ), 'default' => '', 'placeholder' => __( 'No minimum', 'ai-price-negotiator-for-woocommerce' ), 'custom_attributes' => array( 'min' => '0', 'step' => '1' ), 'css' => 'width: 120px;' ),
            array( 'title' => __( 'Allowed User Roles', 'ai-price-negotiator-for-woocommerce' ), 'id' => 'aipn_visibility_user_roles', 'type' => 'text', 'desc' => __( 'Comma-separated list of user roles (e.g., <code>customer, wholesale, subscriber</code>). Leave empty for all roles.', 'ai-price-negotiator-for-woocommerce' ), 'default' => '', 'placeholder' => __( 'All roles', 'ai-price-negotiator-for-woocommerce' ), 'css' => 'width: 300px;' ),
            array( 'title' => __( 'Product Categories (Include)', 'ai-price-negotiator-for-woocommerce' ), 'id' => 'aipn_visibility_categories', 'type' => 'text', 'desc' => __( 'Only show if cart contains items from these category slugs. Comma-separated (e.g., <code>electronics, clothing</code>). Leave empty for all categories.', 'ai-price-negotiator-for-woocommerce' ), 'default' => '', 'placeholder' => __( 'All categories', 'ai-price-negotiator-for-woocommerce' ), 'css' => 'width: 300px;' ),
            array( 'title' => __( 'Excluded Categories', 'ai-price-negotiator-for-woocommerce' ), 'id' => 'aipn_visibility_excluded_categories', 'type' => 'text', 'desc' => __( 'Hide widget if cart contains any item from these category slugs. Comma-separated (e.g., <code>gift-cards, sale</code>).', 'ai-price-negotiator-for-woocommerce' ), 'default' => '', 'placeholder' => __( 'None excluded', 'ai-price-negotiator-for-woocommerce' ), 'css' => 'width: 300px;' ),
            array( 'title' => __( 'Schedule — Days', 'ai-price-negotiator-for-woocommerce' ), 'id' => 'aipn_visibility_schedule_days', 'type' => 'text', 'desc' => __( 'Show widget only on these days. Comma-separated (e.g., <code>mon, tue, wed, thu, fri</code>). Leave empty for every day.', 'ai-price-negotiator-for-woocommerce' ), 'default' => '', 'placeholder' => __( 'Every day', 'ai-price-negotiator-for-woocommerce' ), 'css' => 'width: 300px;' ),
            array( 'title' => __( 'Schedule — Start Time', 'ai-price-negotiator-for-woocommerce' ), 'id' => 'aipn_visibility_schedule_start', 'type' => 'text', 'desc' => __( 'Show widget starting at this time (24h format, e.g., <code>09:00</code>). Uses your WordPress timezone.', 'ai-price-negotiator-for-woocommerce' ), 'default' => '', 'placeholder' => '00:00', 'css' => 'width: 80px;' ),
            array( 'title' => __( 'Schedule — End Time', 'ai-price-negotiator-for-woocommerce' ), 'id' => 'aipn_visibility_schedule_end', 'type' => 'text', 'desc' => __( 'Hide widget after this time (24h format, e.g., <code>17:00</code>). Uses your WordPress timezone.', 'ai-price-negotiator-for-woocommerce' ), 'default' => '', 'placeholder' => '23:59', 'css' => 'width: 80px;' ),
            array( 'title' => __( 'Allowed Countries', 'ai-price-negotiator-for-woocommerce' ), 'id' => 'aipn_visibility_countries', 'type' => 'text', 'desc' => __( 'Only show to customers from these countries. Comma-separated ISO codes (e.g., <code>US, GB, IN, CA</code>). Uses billing country or geolocation.', 'ai-price-negotiator-for-woocommerce' ), 'default' => '', 'placeholder' => __( 'All countries', 'ai-price-negotiator-for-woocommerce' ), 'css' => 'width: 300px;' ),
            array(
                'title'   => __( 'Device Type', 'ai-price-negotiator-for-woocommerce' ),
                'id'      => 'aipn_visibility_device',
                'type'    => 'select',
                'desc'    => __( 'Show widget only on specific device types.', 'ai-price-negotiator-for-woocommerce' ),
                'default' => 'any',
                'options' => array(
                    'any'     => __( 'Any device', 'ai-price-negotiator-for-woocommerce' ),
                    'desktop' => __( 'Desktop only', 'ai-price-negotiator-for-woocommerce' ),
                    'mobile'  => __( 'Mobile only', 'ai-price-negotiator-for-woocommerce' ),
                ),
            ),
            array(
                'title'   => __( 'Visitor Type', 'ai-price-negotiator-for-woocommerce' ),
                'id'      => 'aipn_visibility_visitor_type',
                'type'    => 'select',
                'desc'    => __( 'Show widget based on whether the customer has visited before (cookie-based).', 'ai-price-negotiator-for-woocommerce' ),
                'default' => 'anyone',
                'options' => array(
                    'anyone'    => __( 'Anyone', 'ai-price-negotiator-for-woocommerce' ),
                    'returning' => __( 'Returning visitors only', 'ai-price-negotiator-for-woocommerce' ),
                    'new'       => __( 'New visitors only', 'ai-price-negotiator-for-woocommerce' ),
                ),
            ),
            array( 'type' => 'sectionend', 'id' => 'aipn_pro_visibility_settings' ),
        );
    }

    /* ─── Widget Styling — Icon ───────────────────────────────────────── */

    private function get_icon_fields(): array {
        return array(
            array(
                'title' => __( 'Widget Icon', 'ai-price-negotiator-for-woocommerce' ),
                'type'  => 'title',
                'id'    => 'aipn_pro_icon_settings',
                'desc'  => __( 'Choose the icon displayed in the CTA banner and chat header.', 'ai-price-negotiator-for-woocommerce' ),
            ),
            array(
                'title'   => __( 'Widget Icon', 'ai-price-negotiator-for-woocommerce' ),
                'id'      => 'aipn_widget_icon',
                'type'    => 'select',
                'desc'    => __( 'Icon shown in the CTA area and chat header.', 'ai-price-negotiator-for-woocommerce' ),
                'default' => 'chat-bubble',
                'options' => array(
                    'chat-bubble' => __( 'Chat Bubble (default)', 'ai-price-negotiator-for-woocommerce' ),
                    'chat-dots'   => __( 'Chat Bubble with Dots', 'ai-price-negotiator-for-woocommerce' ),
                    'price-tag'   => __( 'Price Tag', 'ai-price-negotiator-for-woocommerce' ),
                    'handshake'   => __( 'Handshake', 'ai-price-negotiator-for-woocommerce' ),
                    'sparkle'     => __( 'AI Sparkle', 'ai-price-negotiator-for-woocommerce' ),
                    'megaphone'   => __( 'Megaphone', 'ai-price-negotiator-for-woocommerce' ),
                ),
            ),
            array( 'type' => 'sectionend', 'id' => 'aipn_pro_icon_settings' ),
        );
    }

    /* ─── Widget Styling — Header & CTA ──────────────────────────────── */

    private function get_header_cta_fields(): array {
        return array(
            array(
                'title' => __( 'Header & CTA', 'ai-price-negotiator-for-woocommerce' ),
                'type'  => 'title',
                'id'    => 'aipn_pro_header_cta_settings',
                'desc'  => __( 'Customize the chat header bar and the collapsed CTA banner. Leave empty to use defaults.', 'ai-price-negotiator-for-woocommerce' ),
            ),
            array( 'title' => __( 'Header Background', 'ai-price-negotiator-for-woocommerce' ), 'id' => 'aipn_header_bg_color', 'type' => 'text', 'desc' => __( 'Background color for the chat header. Empty = Primary Color.', 'ai-price-negotiator-for-woocommerce' ), 'default' => '', 'class' => 'aipn-color-picker', 'css' => 'width: 80px;' ),
            array( 'title' => __( 'Header Text Color', 'ai-price-negotiator-for-woocommerce' ), 'id' => 'aipn_header_text_color', 'type' => 'text', 'desc' => __( 'Text and icon color in the chat header.', 'ai-price-negotiator-for-woocommerce' ), 'default' => '#ffffff', 'class' => 'aipn-color-picker', 'css' => 'width: 80px;' ),
            array( 'title' => __( 'CTA Background', 'ai-price-negotiator-for-woocommerce' ), 'id' => 'aipn_cta_bg_color', 'type' => 'text', 'desc' => __( 'Background color for the collapsed CTA banner.', 'ai-price-negotiator-for-woocommerce' ), 'default' => '', 'class' => 'aipn-color-picker', 'css' => 'width: 80px;' ),
            array( 'title' => __( 'CTA Text Color', 'ai-price-negotiator-for-woocommerce' ), 'id' => 'aipn_cta_text_color', 'type' => 'text', 'desc' => __( 'Text color for the CTA title and subtitle.', 'ai-price-negotiator-for-woocommerce' ), 'default' => '', 'class' => 'aipn-color-picker', 'css' => 'width: 80px;' ),
            array( 'title' => __( 'CTA Button Color', 'ai-price-negotiator-for-woocommerce' ), 'id' => 'aipn_cta_btn_bg_color', 'type' => 'text', 'desc' => __( 'Background color for the "Make an Offer" button. Empty = Primary Color.', 'ai-price-negotiator-for-woocommerce' ), 'default' => '', 'class' => 'aipn-color-picker', 'css' => 'width: 80px;' ),
            array( 'title' => __( 'CTA Button Text', 'ai-price-negotiator-for-woocommerce' ), 'id' => 'aipn_cta_btn_text_color', 'type' => 'text', 'desc' => __( 'Text color for the CTA button.', 'ai-price-negotiator-for-woocommerce' ), 'default' => '#ffffff', 'class' => 'aipn-color-picker', 'css' => 'width: 80px;' ),
            array( 'type' => 'sectionend', 'id' => 'aipn_pro_header_cta_settings' ),
        );
    }

    /* ─── Widget Styling — Layout ────────────────────────────────────── */

    private function get_layout_fields(): array {
        return array(
            array(
                'title' => __( 'Chat Area & Layout', 'ai-price-negotiator-for-woocommerce' ),
                'type'  => 'title',
                'id'    => 'aipn_pro_layout_settings',
                'desc'  => __( 'Control the chat area background, send button, borders, shadow, and overall width.', 'ai-price-negotiator-for-woocommerce' ),
            ),
            array( 'title' => __( 'Chat Background', 'ai-price-negotiator-for-woocommerce' ), 'id' => 'aipn_widget_bg_color', 'type' => 'text', 'desc' => __( 'Background color for the chat messages area.', 'ai-price-negotiator-for-woocommerce' ), 'default' => '', 'class' => 'aipn-color-picker', 'css' => 'width: 80px;' ),
            array( 'title' => __( 'Send Button Color', 'ai-price-negotiator-for-woocommerce' ), 'id' => 'aipn_send_btn_color', 'type' => 'text', 'desc' => __( 'Background color for the send button. Empty = Primary Color.', 'ai-price-negotiator-for-woocommerce' ), 'default' => '', 'class' => 'aipn-color-picker', 'css' => 'width: 80px;' ),
            array( 'title' => __( 'Widget Border Color', 'ai-price-negotiator-for-woocommerce' ), 'id' => 'aipn_widget_border_color', 'type' => 'text', 'desc' => __( 'Border color for the widget container and dividers.', 'ai-price-negotiator-for-woocommerce' ), 'default' => '#e5e7eb', 'class' => 'aipn-color-picker', 'css' => 'width: 80px;' ),
            array(
                'title'   => __( 'Widget Shadow', 'ai-price-negotiator-for-woocommerce' ),
                'id'      => 'aipn_widget_shadow',
                'type'    => 'select',
                'desc'    => __( 'Shadow intensity around the chat panel.', 'ai-price-negotiator-for-woocommerce' ),
                'default' => 'subtle',
                'options' => array(
                    'none'   => __( 'None', 'ai-price-negotiator-for-woocommerce' ),
                    'subtle' => __( 'Subtle (default)', 'ai-price-negotiator-for-woocommerce' ),
                    'medium' => __( 'Medium', 'ai-price-negotiator-for-woocommerce' ),
                    'strong' => __( 'Strong', 'ai-price-negotiator-for-woocommerce' ),
                ),
            ),
            array(
                'title'             => __( 'Widget Max Width (px)', 'ai-price-negotiator-for-woocommerce' ),
                'id'                => 'aipn_widget_width',
                'type'              => 'number',
                'desc'              => __( 'Maximum width of the widget in pixels. Set to 0 for full width.', 'ai-price-negotiator-for-woocommerce' ),
                'default'           => '500',
                'custom_attributes' => array( 'min' => '0', 'max' => '800', 'step' => '10' ),
                'css'               => 'width: 100px;',
                'suffix'            => 'px',
            ),
            array( 'type' => 'sectionend', 'id' => 'aipn_pro_layout_settings' ),
        );
    }
}
