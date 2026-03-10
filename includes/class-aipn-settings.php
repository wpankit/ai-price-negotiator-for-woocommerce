<?php
/**
 * Settings page for AI Price Negotiator — dedicated top-level admin menu.
 *
 * WooCommerce-style tab + section navigation. Modern card-based UI.
 *
 * @package AI_Price_Negotiator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AIPN_Settings {

    private const MENU_SLUG    = 'aipn-settings';
    private const NONCE_ACTION = 'aipn_save_settings';

    public function register(): void {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_init', array( $this, 'handle_save' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'wp_ajax_aipn_test_api_key', array( $this, 'ajax_test_api_key' ) );
        add_action( 'wp_ajax_aipn_reset_styling', array( $this, 'ajax_reset_styling' ) );
    }

    public function add_menu(): void {
        add_menu_page(
            __( 'AI Negotiator', 'ai-price-negotiator-for-woocommerce' ),
            __( 'AI Negotiator', 'ai-price-negotiator-for-woocommerce' ),
            'manage_woocommerce',
            self::MENU_SLUG,
            array( $this, 'render' ),
            'dashicons-money-alt',
            56
        );

        add_submenu_page(
            self::MENU_SLUG,
            __( 'Settings', 'ai-price-negotiator-for-woocommerce' ),
            __( 'Settings', 'ai-price-negotiator-for-woocommerce' ),
            'manage_woocommerce',
            self::MENU_SLUG,
            array( $this, 'render' )
        );
    }

    /* ─── Render ──────────────────────────────────────────────────────── */

    public function render(): void {
        if ( ! function_exists( 'woocommerce_admin_fields' ) ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'WooCommerce is required.', 'ai-price-negotiator-for-woocommerce' ) . '</p></div>';
            return;
        }

        $current_tab     = $this->get_current_tab();
        $current_section = $this->get_current_section( $current_tab );
        $tabs            = $this->get_tabs();
        $sections        = $this->get_sections_for_tab( $current_tab );
        ?>
        <div class="wrap aipn-settings-wrap">

            <div class="aipn-page-header">
                <div class="aipn-page-header__icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                        <path d="M12 7v2"></path>
                        <path d="M12 13h.01"></path>
                    </svg>
                </div>
                <div>
                    <h1><?php esc_html_e( 'AI Price Negotiator for WooCommerce', 'ai-price-negotiator-for-woocommerce' ); ?>
                        <span class="aipn-version-badge">v<?php echo esc_html( AIPN_VERSION ); ?></span>
                        <span class="aipn-free-badge"><?php esc_html_e( 'Forever Free', 'ai-price-negotiator-for-woocommerce' ); ?></span>
                    </h1>
                    <p>
                        <?php esc_html_e( 'AI-powered checkout negotiation — let customers make offers and close deals automatically.', 'ai-price-negotiator-for-woocommerce' ); ?>
                        <a href="https://github.com/wpankit/ai-price-negotiator-for-woocommerce" target="_blank" rel="noopener noreferrer" class="aipn-contribute-link">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0024 12c0-6.63-5.37-12-12-12z"/></svg>
                            <?php esc_html_e( 'Contribute on GitHub', 'ai-price-negotiator-for-woocommerce' ); ?>
                        </a>
                    </p>
                </div>
            </div>

            <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- WP core settings-updated query param. ?>
            <?php if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] === 'true' ) : ?>
                <div class="aipn-notice-saved">
                    <span class="aipn-notice-saved__icon"></span>
                    <?php esc_html_e( 'Your settings have been saved.', 'ai-price-negotiator-for-woocommerce' ); ?>
                </div>
            <?php endif; ?>

            <!-- Tab Navigation -->
            <nav class="nav-tab-wrapper aipn-nav-tabs">
                <?php foreach ( $tabs as $slug => $label ) :
                    $url   = add_query_arg( array( 'tab' => $slug ), admin_url( 'admin.php?page=' . self::MENU_SLUG ) );
                    $class = $current_tab === $slug ? 'nav-tab nav-tab-active' : 'nav-tab';
                ?>
                    <a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $class ); ?>"><?php echo esc_html( $label ); ?></a>
                <?php endforeach; ?>
            </nav>

            <?php $has_sections = count( $sections ) > 1; ?>

            <!-- Section Links -->
            <?php if ( $has_sections ) : ?>
            <div class="aipn-section-nav">
                <ul class="aipn-section-links">
                    <?php foreach ( $sections as $section_id => $section_label ) :
                        $url   = add_query_arg(
                            array( 'tab' => $current_tab, 'section' => $section_id ),
                            admin_url( 'admin.php?page=' . self::MENU_SLUG )
                        );
                        $class = $current_section === $section_id ? 'aipn-section-link--active' : '';
                    ?>
                        <li>
                            <a href="<?php echo esc_url( $url ); ?>" class="aipn-section-link <?php echo esc_attr( $class ); ?>"><?php echo esc_html( $section_label ); ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <!-- Content -->
                <form method="post" action="">
                    <?php wp_nonce_field( self::NONCE_ACTION, 'aipn_settings_nonce' ); ?>
                    <input type="hidden" name="aipn_active_tab" value="<?php echo esc_attr( $current_tab ); ?>" />
                    <input type="hidden" name="aipn_active_section" value="<?php echo esc_attr( $current_section ); ?>" />

                    <div class="aipn-form-card<?php echo $has_sections ? ' aipn-form-card--sectioned' : ''; ?>">
                        <?php
                        if ( $current_tab === 'pro' ) {
                            $pro_wc_fields = $this->get_pro_wc_fields( $current_section );
                            if ( ! empty( $pro_wc_fields ) ) {
                                woocommerce_admin_fields( $pro_wc_fields );
                            } else {
                                do_action( 'aipn_pro_tab_fields', $current_section );
                            }
                        } else {
                            woocommerce_admin_fields( $this->get_fields_for_tab( $current_tab ) );
                        }
                        ?>
                    </div>

                    <p class="submit aipn-submit">
                        <button type="submit" class="button-primary aipn-save-btn" name="aipn_save" value="1">
                            <?php esc_html_e( 'Save changes', 'ai-price-negotiator-for-woocommerce' ); ?>
                        </button>
                        <?php if ( $current_tab === 'pro' && $current_section === 'styling' ) : ?>
                            <button type="button" class="button aipn-reset-styling-btn" id="aipn-reset-styling">
                                <?php esc_html_e( 'Reset Styling to Defaults', 'ai-price-negotiator-for-woocommerce' ); ?>
                            </button>
                        <?php endif; ?>
                    </p>
                </form>
        </div>
        <?php
    }

    /* ─── Save ────────────────────────────────────────────────────────── */

    public function handle_save(): void {
        if ( ! isset( $_POST['aipn_save'] ) || ! isset( $_POST['aipn_settings_nonce'] ) ) {
            return;
        }
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aipn_settings_nonce'] ) ), self::NONCE_ACTION ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        $tab     = isset( $_POST['aipn_active_tab'] ) ? sanitize_text_field( wp_unslash( $_POST['aipn_active_tab'] ) ) : 'general';
        $section = isset( $_POST['aipn_active_section'] ) ? sanitize_text_field( wp_unslash( $_POST['aipn_active_section'] ) ) : '';

        if ( $tab === 'pro' ) {
            $pro_wc_fields = $this->get_pro_wc_fields( $section );
            if ( ! empty( $pro_wc_fields ) ) {
                woocommerce_update_options( $pro_wc_fields );
            } else {
                do_action( 'aipn_save_pro_settings', $section );
            }
        } else {
            woocommerce_update_options( $this->get_fields_for_tab( $tab ) );
        }

        $redirect_args = array(
            'page'             => self::MENU_SLUG,
            'tab'              => $tab,
            'settings-updated' => 'true',
        );
        if ( $section !== '' ) {
            $redirect_args['section'] = $section;
        }

        wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
        exit;
    }

    /* ─── Assets ──────────────────────────────────────────────────────── */

    public function enqueue_admin_assets( string $hook ): void {
        if ( 'toplevel_page_' . self::MENU_SLUG !== $hook ) {
            return;
        }

        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script(
            'aipn-admin-settings',
            AIPN_PLUGIN_URL . 'assets/js/admin-settings.js',
            array( 'jquery', 'wp-color-picker' ),
            AIPN_VERSION,
            true
        );
        wp_localize_script( 'aipn-admin-settings', 'aipnAdmin', array(
            'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
            'nonce'      => wp_create_nonce( 'aipn_test_api_key' ),
            'resetNonce' => wp_create_nonce( 'aipn_reset_styling' ),
            'strings'    => array(
                'testBtn'      => __( 'Test API Key', 'ai-price-negotiator-for-woocommerce' ),
                'testing'      => __( 'Testing...', 'ai-price-negotiator-for-woocommerce' ),
                'success'      => __( 'API key is valid! Connected to OpenAI successfully.', 'ai-price-negotiator-for-woocommerce' ),
                'error'        => __( 'Invalid API key or connection failed.', 'ai-price-negotiator-for-woocommerce' ),
                'empty'        => __( 'Please enter an API key first.', 'ai-price-negotiator-for-woocommerce' ),
                'resetConfirm' => __( 'Reset all widget styling options to their defaults? This cannot be undone.', 'ai-price-negotiator-for-woocommerce' ),
                'resetting'    => __( 'Resetting...', 'ai-price-negotiator-for-woocommerce' ),
                'resetDone'    => __( 'Styling reset to defaults!', 'ai-price-negotiator-for-woocommerce' ),
                'resetError'   => __( 'Failed to reset styling. Please try again.', 'ai-price-negotiator-for-woocommerce' ),
            ),
        ) );
        wp_enqueue_style(
            'aipn-admin-settings',
            AIPN_PLUGIN_URL . 'assets/css/admin-settings.css',
            array(),
            AIPN_VERSION
        );
    }

    /* ─── AJAX: Test API Key ──────────────────────────────────────────── */

    public function ajax_test_api_key(): void {
        check_ajax_referer( 'aipn_test_api_key', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ai-price-negotiator-for-woocommerce' ) ) );
        }

        $api_key = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';

        if ( $api_key === '' ) {
            wp_send_json_error( array( 'message' => __( 'API key is empty.', 'ai-price-negotiator-for-woocommerce' ) ) );
        }

        $response = wp_remote_get(
            'https://api.openai.com/v1/models',
            array(
                'headers' => array( 'Authorization' => 'Bearer ' . $api_key ),
                'timeout' => 15,
            )
        );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => $response->get_error_message() ) );
        }

        $code = wp_remote_retrieve_response_code( $response );

        if ( $code === 200 ) {
            wp_send_json_success( array( 'message' => __( 'API key is valid!', 'ai-price-negotiator-for-woocommerce' ) ) );
        } elseif ( $code === 401 ) {
            wp_send_json_error( array( 'message' => __( 'Invalid API key. Please check and try again.', 'ai-price-negotiator-for-woocommerce' ) ) );
        } else {
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            $msg  = $body['error']['message'] ?? __( 'OpenAI returned an unexpected response.', 'ai-price-negotiator-for-woocommerce' );
            wp_send_json_error( array( 'message' => $msg ) );
        }
    }

    /* ─── AJAX: Reset Styling ────────────────────────────────────────── */

    public function ajax_reset_styling(): void {
        check_ajax_referer( 'aipn_reset_styling', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ai-price-negotiator-for-woocommerce' ) ) );
        }

        $styling_options = array(
            'aipn_widget_icon',
            'aipn_header_bg_color',
            'aipn_header_text_color',
            'aipn_widget_bg_color',
            'aipn_cta_bg_color',
            'aipn_cta_text_color',
            'aipn_cta_btn_bg_color',
            'aipn_cta_btn_text_color',
            'aipn_send_btn_color',
            'aipn_widget_border_color',
            'aipn_widget_shadow',
            'aipn_widget_width',
        );

        foreach ( $styling_options as $option ) {
            delete_option( $option );
        }

        wp_send_json_success( array( 'message' => __( 'Styling reset to defaults.', 'ai-price-negotiator-for-woocommerce' ) ) );
    }

    /* ═══════════════════════════════════════════════════════════════════ *
     *  Navigation helpers
     * ═══════════════════════════════════════════════════════════════════ */

    private function get_tabs(): array {
        return array(
            'general'    => __( 'General', 'ai-price-negotiator-for-woocommerce' ),
            'appearance' => __( 'Appearance', 'ai-price-negotiator-for-woocommerce' ),
            'text'       => __( 'Widget Text', 'ai-price-negotiator-for-woocommerce' ),
            'pro'        => __( 'Advanced', 'ai-price-negotiator-for-woocommerce' ),
        );
    }

    private function get_current_tab(): string {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab navigation.
        $tab  = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'general';
        $tabs = $this->get_tabs();
        return array_key_exists( $tab, $tabs ) ? $tab : 'general';
    }

    private function get_sections_for_tab( string $tab ): array {
        switch ( $tab ) {
            case 'general':
                return array(
                    ''        => __( 'Setup', 'ai-price-negotiator-for-woocommerce' ),
                    'pricing' => __( 'Pricing Rules', 'ai-price-negotiator-for-woocommerce' ),
                );
            case 'appearance':
                return array(
                    '' => __( 'Colors', 'ai-price-negotiator-for-woocommerce' ),
                );
            case 'pro':
                return array(
                    ''              => __( 'AI Behavior', 'ai-price-negotiator-for-woocommerce' ),
                    'custom_rules'  => __( 'Custom Rules', 'ai-price-negotiator-for-woocommerce' ),
                    'typography'    => __( 'Typography', 'ai-price-negotiator-for-woocommerce' ),
                    'sales'         => __( 'Sales & Urgency', 'ai-price-negotiator-for-woocommerce' ),
                    'visibility'    => __( 'Widget Visibility', 'ai-price-negotiator-for-woocommerce' ),
                    'styling'       => __( 'Widget Styling', 'ai-price-negotiator-for-woocommerce' ),
                );
            default:
                return array( '' => '' );
        }
    }

    private function get_current_section( string $tab ): string {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Read-only section navigation.
        if ( isset( $_POST['aipn_active_section'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $section = sanitize_text_field( wp_unslash( $_POST['aipn_active_section'] ) );
        } else {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $section = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : '';
        }
        $sections = $this->get_sections_for_tab( $tab );
        return array_key_exists( $section, $sections ) ? $section : '';
    }

    /* ═══════════════════════════════════════════════════════════════════ *
     *  Field routing
     * ═══════════════════════════════════════════════════════════════════ */

    private function get_fields_for_tab( string $tab ): array {
        $section = $this->get_current_section( $tab );

        switch ( $tab ) {
            case 'general':
                return $section === 'pricing' ? $this->get_pricing_fields() : $this->get_setup_fields();
            case 'appearance':
                return $this->get_colors_fields();
            case 'text':
                return $this->get_text_fields();
            default:
                return array();
        }
    }

    /* ─── General → Setup ─────────────────────────────────────────────── */

    private function get_setup_fields(): array {
        return array(
            array(
                'title' => __( 'Setup', 'ai-price-negotiator-for-woocommerce' ),
                'type'  => 'title',
                'id'    => 'aipn_general_settings',
                'desc'  => __( 'Configure your AI-powered price negotiation system. Customers can negotiate prices at checkout, and the AI will handle counter-offers within your defined rules.', 'ai-price-negotiator-for-woocommerce' ),
            ),
            array(
                'title'   => __( 'Enable Negotiation', 'ai-price-negotiator-for-woocommerce' ),
                'id'      => 'aipn_enabled',
                'type'    => 'checkbox',
                'desc'    => __( 'Enable AI price negotiation on the checkout page.', 'ai-price-negotiator-for-woocommerce' ),
                'default' => 'yes',
            ),
            array(
                'title'             => __( 'OpenAI API Key', 'ai-price-negotiator-for-woocommerce' ),
                'id'                => 'aipn_openai_key',
                'type'              => 'password',
                'desc_tip'          => true,
                'desc'              => __( 'Your OpenAI API key (starts with sk-). Stored locally, never sent to third parties.', 'ai-price-negotiator-for-woocommerce' ),
                'custom_attributes' => array( 'autocomplete' => 'off', 'spellcheck' => 'false' ),
            ),
            array( 'type' => 'sectionend', 'id' => 'aipn_general_settings' ),
        );
    }

    /* ─── General → Pricing ───────────────────────────────────────────── */

    private function get_pricing_fields(): array {
        return array(
            array(
                'title' => __( 'Pricing Rules', 'ai-price-negotiator-for-woocommerce' ),
                'type'  => 'title',
                'id'    => 'aipn_pricing_settings',
                'desc'  => __( 'Set the global floor price as a percentage. Per-product overrides available from each product\'s edit screen.', 'ai-price-negotiator-for-woocommerce' ),
            ),
            array(
                'title'             => __( 'Global Floor Price (%)', 'ai-price-negotiator-for-woocommerce' ),
                'id'                => 'aipn_global_floor_pct',
                'type'              => 'number',
                'desc_tip'          => true,
                'desc'              => __( 'Minimum acceptable price as a percentage of the product price. E.g., 70 means the AI will never accept less than 70% of the listed price.', 'ai-price-negotiator-for-woocommerce' ),
                'default'           => '70',
                'custom_attributes' => array( 'min' => '1', 'max' => '100', 'step' => '1' ),
                'css'               => 'width: 80px;',
                'suffix'            => '%',
            ),
            array( 'type' => 'sectionend', 'id' => 'aipn_pricing_settings' ),
        );
    }

    /* ─── Appearance → Colors ─────────────────────────────────────────── */

    private function get_colors_fields(): array {
        return array(
            array(
                'title' => __( 'Colors', 'ai-price-negotiator-for-woocommerce' ),
                'type'  => 'title',
                'id'    => 'aipn_color_settings',
                'desc'  => __( 'Customize the color scheme of the negotiation chat widget.', 'ai-price-negotiator-for-woocommerce' ),
            ),
            array(
                'title'   => __( 'Primary Color', 'ai-price-negotiator-for-woocommerce' ),
                'id'      => 'aipn_primary_color',
                'type'    => 'text',
                'desc'    => __( 'Primary accent color for header, buttons, and user messages.', 'ai-price-negotiator-for-woocommerce' ),
                'default' => '#4F46E5',
                'class'   => 'aipn-color-picker',
                'css'     => 'width: 80px;',
            ),
            array(
                'title'   => __( 'User Message Background', 'ai-price-negotiator-for-woocommerce' ),
                'id'      => 'aipn_user_bubble_color',
                'type'    => 'text',
                'desc'    => __( 'Background color for the customer\'s chat bubbles. Leave empty to use Primary Color.', 'ai-price-negotiator-for-woocommerce' ),
                'default' => '',
                'class'   => 'aipn-color-picker',
                'css'     => 'width: 80px;',
            ),
            array(
                'title'   => __( 'User Message Text', 'ai-price-negotiator-for-woocommerce' ),
                'id'      => 'aipn_user_text_color',
                'type'    => 'text',
                'desc'    => __( 'Text color for the customer\'s chat bubbles.', 'ai-price-negotiator-for-woocommerce' ),
                'default' => '#ffffff',
                'class'   => 'aipn-color-picker',
                'css'     => 'width: 80px;',
            ),
            array(
                'title'   => __( 'Assistant Message Background', 'ai-price-negotiator-for-woocommerce' ),
                'id'      => 'aipn_assistant_bubble_color',
                'type'    => 'text',
                'desc'    => __( 'Background color for the AI assistant\'s chat bubbles.', 'ai-price-negotiator-for-woocommerce' ),
                'default' => '#f3f4f6',
                'class'   => 'aipn-color-picker',
                'css'     => 'width: 80px;',
            ),
            array(
                'title'   => __( 'Assistant Message Text', 'ai-price-negotiator-for-woocommerce' ),
                'id'      => 'aipn_assistant_text_color',
                'type'    => 'text',
                'desc'    => __( 'Text color for the AI assistant\'s chat bubbles.', 'ai-price-negotiator-for-woocommerce' ),
                'default' => '#111827',
                'class'   => 'aipn-color-picker',
                'css'     => 'width: 80px;',
            ),
            array( 'type' => 'sectionend', 'id' => 'aipn_color_settings' ),
        );
    }

    /* ─── Appearance → Typography ─────────────────────────────────────── */

    private function get_typography_fields(): array {
        return array(
            array(
                'title' => __( 'Typography', 'ai-price-negotiator-for-woocommerce' ),
                'type'  => 'title',
                'id'    => 'aipn_typography_settings',
                'desc'  => __( 'Configure fonts and sizing for the negotiation widget.', 'ai-price-negotiator-for-woocommerce' ),
            ),
            array(
                'title'             => __( 'Border Radius (px)', 'ai-price-negotiator-for-woocommerce' ),
                'id'                => 'aipn_border_radius',
                'type'              => 'number',
                'desc'              => __( 'Widget corner roundness in pixels.', 'ai-price-negotiator-for-woocommerce' ),
                'default'           => '12',
                'custom_attributes' => array( 'min' => '0', 'max' => '30', 'step' => '1' ),
                'css'               => 'width: 80px;',
                'suffix'            => 'px',
            ),
            array(
                'title'   => __( 'Font Family', 'ai-price-negotiator-for-woocommerce' ),
                'id'      => 'aipn_font_family',
                'type'    => 'select',
                'desc'    => __( 'Font used in the chat widget. "Inherit" uses your theme\'s font.', 'ai-price-negotiator-for-woocommerce' ),
                'default' => 'inherit',
                'options' => array(
                    'inherit'    => __( 'Inherit from theme', 'ai-price-negotiator-for-woocommerce' ),
                    'system'     => __( 'System UI (Default)', 'ai-price-negotiator-for-woocommerce' ),
                    'inter'      => 'Inter',
                    'roboto'     => 'Roboto',
                    'open-sans'  => 'Open Sans',
                    'lato'       => 'Lato',
                    'poppins'    => 'Poppins',
                    'nunito'     => 'Nunito',
                    'montserrat' => 'Montserrat',
                    'georgia'    => 'Georgia (Serif)',
                ),
                'css'     => 'width: 250px;',
            ),
            array(
                'title'             => __( 'Font Size (px)', 'ai-price-negotiator-for-woocommerce' ),
                'id'                => 'aipn_font_size',
                'type'              => 'number',
                'desc'              => __( 'Base font size for the widget (header, buttons, input).', 'ai-price-negotiator-for-woocommerce' ),
                'default'           => '14',
                'custom_attributes' => array( 'min' => '11', 'max' => '20', 'step' => '1' ),
                'css'               => 'width: 80px;',
                'suffix'            => 'px',
            ),
            array(
                'title'             => __( 'Chat Bubble Font Size (px)', 'ai-price-negotiator-for-woocommerce' ),
                'id'                => 'aipn_bubble_font_size',
                'type'              => 'number',
                'desc'              => __( 'Font size for message bubbles in the chat.', 'ai-price-negotiator-for-woocommerce' ),
                'default'           => '13',
                'custom_attributes' => array( 'min' => '11', 'max' => '20', 'step' => '1' ),
                'css'               => 'width: 80px;',
                'suffix'            => 'px',
            ),
            array( 'type' => 'sectionend', 'id' => 'aipn_typography_settings' ),
        );
    }

    /* ─── Advanced → Custom Rules ────────────────────────────────────── */

    private function get_custom_rules_fields(): array {
        return array(
            array(
                'title' => __( 'Custom Rules', 'ai-price-negotiator-for-woocommerce' ),
                'type'  => 'title',
                'id'    => 'aipn_custom_rules_settings',
                'desc'  => __( 'Add your own negotiation rules. The AI will follow these alongside the system rules.', 'ai-price-negotiator-for-woocommerce' ),
            ),
            array(
                'title'    => __( 'Custom Rules', 'ai-price-negotiator-for-woocommerce' ),
                'id'       => 'aipn_custom_rules',
                'type'     => 'textarea',
                'desc'     => __( 'One rule per line. These are injected directly into the AI prompt. Examples:', 'ai-price-negotiator-for-woocommerce' ) .
                    '<br><code>' . esc_html__( 'Never offer free shipping as part of the deal', 'ai-price-negotiator-for-woocommerce' ) . '</code>' .
                    '<br><code>' . esc_html__( 'Always mention our 30-day money-back guarantee when customer hesitates', 'ai-price-negotiator-for-woocommerce' ) . '</code>' .
                    '<br><code>' . esc_html__( 'If customer mentions a competitor, highlight our premium quality', 'ai-price-negotiator-for-woocommerce' ) . '</code>',
                'default'  => '',
                'css'      => 'width: 100%; min-height: 120px;',
                'desc_tip' => false,
            ),
            array( 'type' => 'sectionend', 'id' => 'aipn_custom_rules_settings' ),
        );
    }

    /**
     * Get WC-native fields for Advanced sections that use standard WC settings (Custom Rules, Typography).
     */
    private function get_pro_wc_fields( string $section ): array {
        switch ( $section ) {
            case 'custom_rules':
                return $this->get_custom_rules_fields();
            case 'typography':
                return $this->get_typography_fields();
            default:
                return array();
        }
    }

    /* ─── Widget Text ─────────────────────────────────────────────────── */

    private function get_text_fields(): array {
        return array(
            array(
                'title' => __( 'Widget Text', 'ai-price-negotiator-for-woocommerce' ),
                'type'  => 'title',
                'id'    => 'aipn_widget_text_settings',
                'desc'  => __( 'Customize all text displayed in the negotiation widget. Leave empty to use defaults.', 'ai-price-negotiator-for-woocommerce' ),
            ),
            array( 'title' => __( 'CTA Heading', 'ai-price-negotiator-for-woocommerce' ), 'id' => 'aipn_cta_title', 'type' => 'text', 'default' => '', 'placeholder' => __( 'Want a better deal?', 'ai-price-negotiator-for-woocommerce' ), 'css' => 'width: 350px;' ),
            array( 'title' => __( 'CTA Subtitle', 'ai-price-negotiator-for-woocommerce' ), 'id' => 'aipn_cta_subtitle', 'type' => 'text', 'default' => '', 'placeholder' => __( 'Make an offer on your cart', 'ai-price-negotiator-for-woocommerce' ), 'css' => 'width: 350px;' ),
            array( 'title' => __( 'CTA Button Text', 'ai-price-negotiator-for-woocommerce' ), 'id' => 'aipn_cta_button', 'type' => 'text', 'default' => '', 'placeholder' => __( 'Make an Offer', 'ai-price-negotiator-for-woocommerce' ), 'css' => 'width: 250px;' ),
            array( 'title' => __( 'Chat Header Title', 'ai-price-negotiator-for-woocommerce' ), 'id' => 'aipn_chat_header', 'type' => 'text', 'default' => '', 'placeholder' => __( 'Price Negotiation', 'ai-price-negotiator-for-woocommerce' ), 'css' => 'width: 250px;' ),
            array( 'title' => __( 'Input Placeholder', 'ai-price-negotiator-for-woocommerce' ), 'id' => 'aipn_input_placeholder', 'type' => 'text', 'default' => '', 'placeholder' => __( 'Type your offer or message...', 'ai-price-negotiator-for-woocommerce' ), 'css' => 'width: 350px;' ),
            array( 'title' => __( 'Deal Accepted Text', 'ai-price-negotiator-for-woocommerce' ), 'id' => 'aipn_deal_message', 'type' => 'text', 'default' => '', 'placeholder' => __( 'Deal accepted!', 'ai-price-negotiator-for-woocommerce' ), 'css' => 'width: 250px;' ),
            array( 'title' => __( 'Sending Text', 'ai-price-negotiator-for-woocommerce' ), 'id' => 'aipn_sending_text', 'type' => 'text', 'default' => '', 'placeholder' => __( 'Negotiating...', 'ai-price-negotiator-for-woocommerce' ), 'css' => 'width: 250px;' ),
            array( 'title' => __( 'Error Message', 'ai-price-negotiator-for-woocommerce' ), 'id' => 'aipn_error_message', 'type' => 'text', 'default' => '', 'placeholder' => __( 'Something went wrong. Please try again.', 'ai-price-negotiator-for-woocommerce' ), 'css' => 'width: 350px;' ),
            array( 'type' => 'sectionend', 'id' => 'aipn_widget_text_settings' ),
        );
    }

}
