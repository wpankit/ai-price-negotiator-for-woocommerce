<?php
/**
 * Analytics Dashboard — negotiation statistics and insights.
 *
 * @package AI_Price_Negotiator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AIPN_Analytics {

    /** @var AIPN_Logger */
    private $logger;

    public function __construct( AIPN_Logger $logger ) {
        $this->logger = $logger;
    }

    /**
     * Register hooks.
     */
    public function register(): void {
        add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_ajax_aipn_export_csv', array( $this, 'ajax_export_csv' ) );
    }

    /**
     * Add analytics page under AI Negotiator menu.
     */
    public function add_menu_page(): void {
        add_submenu_page(
            'aipn-settings',
            __( 'Negotiation Analytics', 'ai-price-negotiator-for-woocommerce' ),
            __( 'Analytics & Chat Logs', 'ai-price-negotiator-for-woocommerce' ),
            'manage_woocommerce',
            'aipn-analytics',
            array( $this, 'render_page' )
        );
    }

    /**
     * Enqueue analytics assets.
     */
    public function enqueue_assets( string $hook ): void {
        if ( 'ai-negotiator_page_aipn-analytics' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'aipn-analytics',
            AIPN_PLUGIN_URL . 'pro/assets/css/analytics-dashboard.css',
            array(),
            AIPN_VERSION
        );

        wp_enqueue_script(
            'aipn-analytics',
            AIPN_PLUGIN_URL . 'pro/assets/js/analytics-dashboard.js',
            array( 'jquery' ),
            AIPN_VERSION,
            true
        );

        wp_localize_script( 'aipn-analytics', 'aipnAnalytics', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'aipn_export_csv' ),
            'strings' => array(
                'exporting' => __( 'Exporting...', 'ai-price-negotiator-for-woocommerce' ),
                'exportBtn' => __( 'Export CSV', 'ai-price-negotiator-for-woocommerce' ),
                'view'      => __( 'View', 'ai-price-negotiator-for-woocommerce' ),
                'hide'      => __( 'Hide', 'ai-price-negotiator-for-woocommerce' ),
            ),
        ) );
    }

    /**
     * Render the analytics page.
     */
    public function render_page(): void {
        $period = 30;
        if ( isset( $_GET['_aipn_nonce'], $_GET['period'] ) && wp_verify_nonce( sanitize_key( $_GET['_aipn_nonce'] ), 'aipn_analytics_period' ) ) {
            $period = (int) $_GET['period'];
        }
        $stats        = $this->logger->get_stats( $period );
        $breakdown    = $this->logger->get_status_breakdown( $period );
        $top_products = $this->logger->get_top_products( $period, 5 );
        $recent       = $this->logger->get_negotiations( array( 'limit' => 20 ) );

        $currency = html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' );
        ?>
        <div class="wrap aipn-analytics-wrap">

            <!-- Page Header -->
            <div class="aipn-page-header">
                <div class="aipn-page-header__icon"><span class="dashicons dashicons-chart-bar"></span></div>
                <div>
                    <h1><?php esc_html_e( 'Analytics & Chat Logs', 'ai-price-negotiator-for-woocommerce' ); ?></h1>
                    <p><?php esc_html_e( 'Track negotiation performance and review conversations', 'ai-price-negotiator-for-woocommerce' ); ?></p>
                </div>
            </div>

            <!-- Toolbar: Period Selector + Export -->
            <div class="aipn-analytics-toolbar">
                <div class="aipn-analytics-period">
                    <?php
                    $periods = array( 7 => '7 days', 30 => '30 days', 90 => '90 days' );
                    foreach ( $periods as $days => $label ) :
                        $url = wp_nonce_url( add_query_arg( 'period', $days ), 'aipn_analytics_period', '_aipn_nonce' );
                        $class = $period === $days ? 'aipn-period-btn aipn-period-btn--active' : 'aipn-period-btn';
                        ?>
                        <a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $class ); ?>"><?php echo esc_html( $label ); ?></a>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="aipn-btn-export" id="aipn-export-csv" data-period="<?php echo esc_attr( $period ); ?>">
                    <span class="dashicons dashicons-download"></span>
                    <?php esc_html_e( 'Export CSV', 'ai-price-negotiator-for-woocommerce' ); ?>
                </button>
            </div>

            <!-- Stats Cards (9 cards) -->
            <div class="aipn-analytics-cards">
                <div class="aipn-analytics-card">
                    <div class="aipn-analytics-card__icon aipn-analytics-card__icon--total"><?php echo esc_html( "\xF0\x9F\x92\xAC" ); ?></div>
                    <span class="aipn-analytics-card__value"><?php echo esc_html( $stats['total_negotiations'] ); ?></span>
                    <span class="aipn-analytics-card__label"><?php esc_html_e( 'Total Negotiations', 'ai-price-negotiator-for-woocommerce' ); ?></span>
                </div>
                <div class="aipn-analytics-card aipn-analytics-card--success">
                    <div class="aipn-analytics-card__icon aipn-analytics-card__icon--rate"><?php echo esc_html( "\xE2\x9C\x93" ); ?></div>
                    <span class="aipn-analytics-card__value"><?php echo esc_html( $stats['acceptance_rate'] ); ?>%</span>
                    <span class="aipn-analytics-card__label"><?php esc_html_e( 'Acceptance Rate', 'ai-price-negotiator-for-woocommerce' ); ?></span>
                </div>
                <div class="aipn-analytics-card">
                    <div class="aipn-analytics-card__icon aipn-analytics-card__icon--discount"><?php echo esc_html( "\xF0\x9F\x8F\xB7" ); ?></div>
                    <span class="aipn-analytics-card__value"><?php echo esc_html( $currency . number_format( $stats['avg_discount'], 2 ) ); ?></span>
                    <span class="aipn-analytics-card__label"><?php esc_html_e( 'Avg. Discount', 'ai-price-negotiator-for-woocommerce' ); ?></span>
                </div>
                <div class="aipn-analytics-card">
                    <div class="aipn-analytics-card__icon aipn-analytics-card__icon--pct"><?php echo esc_html( "%" ); ?></div>
                    <span class="aipn-analytics-card__value"><?php echo esc_html( $stats['avg_discount_pct'] ); ?>%</span>
                    <span class="aipn-analytics-card__label"><?php esc_html_e( 'Avg. Discount %', 'ai-price-negotiator-for-woocommerce' ); ?></span>
                </div>
                <div class="aipn-analytics-card aipn-analytics-card--revenue">
                    <div class="aipn-analytics-card__icon aipn-analytics-card__icon--revenue"><?php echo esc_html( "\xF0\x9F\x92\xB0" ); ?></div>
                    <span class="aipn-analytics-card__value"><?php echo esc_html( $currency . number_format( $stats['total_revenue'], 2 ) ); ?></span>
                    <span class="aipn-analytics-card__label"><?php esc_html_e( 'Revenue from Negotiations', 'ai-price-negotiator-for-woocommerce' ); ?></span>
                </div>
                <div class="aipn-analytics-card">
                    <div class="aipn-analytics-card__icon aipn-analytics-card__icon--turns"><?php echo esc_html( "\xF0\x9F\x94\x84" ); ?></div>
                    <span class="aipn-analytics-card__value"><?php echo esc_html( $stats['avg_turns'] ); ?></span>
                    <span class="aipn-analytics-card__label"><?php esc_html_e( 'Avg. Turns to Close', 'ai-price-negotiator-for-woocommerce' ); ?></span>
                </div>
                <div class="aipn-analytics-card">
                    <div class="aipn-analytics-card__icon aipn-analytics-card__icon--savings"><?php echo esc_html( "\xF0\x9F\x92\xB8" ); ?></div>
                    <span class="aipn-analytics-card__value"><?php echo esc_html( $currency . number_format( $stats['total_discount_given'], 2 ) ); ?></span>
                    <span class="aipn-analytics-card__label"><?php esc_html_e( 'Total Discount Given', 'ai-price-negotiator-for-woocommerce' ); ?></span>
                </div>
                <div class="aipn-analytics-card aipn-analytics-card--warning">
                    <div class="aipn-analytics-card__icon aipn-analytics-card__icon--abandoned"><?php echo esc_html( "\xF0\x9F\x9A\xAB" ); ?></div>
                    <span class="aipn-analytics-card__value"><?php echo esc_html( $stats['abandoned_count'] ); ?></span>
                    <span class="aipn-analytics-card__label"><?php esc_html_e( 'Abandoned / Expired', 'ai-price-negotiator-for-woocommerce' ); ?></span>
                </div>
                <div class="aipn-analytics-card">
                    <div class="aipn-analytics-card__icon aipn-analytics-card__icon--cart"><?php echo esc_html( "\xF0\x9F\x9B\x92" ); ?></div>
                    <span class="aipn-analytics-card__value"><?php echo esc_html( $currency . number_format( $stats['avg_cart_value'], 2 ) ); ?></span>
                    <span class="aipn-analytics-card__label"><?php esc_html_e( 'Avg. Cart Value', 'ai-price-negotiator-for-woocommerce' ); ?></span>
                </div>
            </div>

            <!-- Revenue Impact Panel -->
            <div class="aipn-revenue-impact">
                <h3><?php esc_html_e( 'Revenue Impact', 'ai-price-negotiator-for-woocommerce' ); ?></h3>
                <div class="aipn-revenue-impact__grid">
                    <div class="aipn-revenue-impact__item">
                        <span class="aipn-revenue-impact__value"><?php echo esc_html( $currency . number_format( $stats['accepted_cart_total'], 2 ) ); ?></span>
                        <span class="aipn-revenue-impact__label"><?php esc_html_e( 'Original Cart Value', 'ai-price-negotiator-for-woocommerce' ); ?></span>
                    </div>
                    <div class="aipn-revenue-impact__item aipn-revenue-impact__item--primary">
                        <span class="aipn-revenue-impact__value"><?php echo esc_html( $currency . number_format( $stats['total_revenue'], 2 ) ); ?></span>
                        <span class="aipn-revenue-impact__label"><?php esc_html_e( 'Negotiated Revenue', 'ai-price-negotiator-for-woocommerce' ); ?></span>
                    </div>
                    <div class="aipn-revenue-impact__item aipn-revenue-impact__item--savings">
                        <span class="aipn-revenue-impact__value"><?php echo esc_html( $currency . number_format( $stats['total_discount_given'], 2 ) ); ?></span>
                        <span class="aipn-revenue-impact__label"><?php esc_html_e( 'Total Customer Savings', 'ai-price-negotiator-for-woocommerce' ); ?></span>
                    </div>
                </div>
            </div>

            <!-- Negotiation Outcomes Breakdown -->
            <div class="aipn-analytics-section">
                <div class="aipn-analytics-section__header">
                    <h2><?php esc_html_e( 'Negotiation Outcomes', 'ai-price-negotiator-for-woocommerce' ); ?></h2>
                </div>
                <div class="aipn-outcomes-card">
                    <?php
                    $statuses = array(
                        'accepted'  => array( 'label' => __( 'Accepted', 'ai-price-negotiator-for-woocommerce' ), 'color' => 'green' ),
                        'expired'   => array( 'label' => __( 'Expired', 'ai-price-negotiator-for-woocommerce' ), 'color' => 'yellow' ),
                        'abandoned' => array( 'label' => __( 'Abandoned', 'ai-price-negotiator-for-woocommerce' ), 'color' => 'gray' ),
                    );
                    foreach ( $statuses as $status_key => $status_meta ) :
                        $count = $breakdown[ $status_key ];
                        $pct   = $breakdown['total'] > 0 ? round( ( $count / $breakdown['total'] ) * 100, 1 ) : 0;
                    ?>
                    <div class="aipn-outcome-row">
                        <div class="aipn-outcome-row__label">
                            <span class="aipn-outcome-row__status"><?php echo esc_html( $status_meta['label'] ); ?></span>
                            <span class="aipn-outcome-row__count"><?php echo esc_html( $count ); ?> (<?php echo esc_html( $pct ); ?>%)</span>
                        </div>
                        <div class="aipn-outcome-row__bar">
                            <div class="aipn-outcome-row__fill aipn-outcome-row__fill--<?php echo esc_attr( $status_meta['color'] ); ?>"
                                 style="width: <?php echo esc_attr( $pct ); ?>%;"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Top Negotiated Products -->
            <?php if ( ! empty( $top_products ) ) : ?>
            <div class="aipn-analytics-section">
                <div class="aipn-analytics-section__header">
                    <h2><?php esc_html_e( 'Top Negotiated Products', 'ai-price-negotiator-for-woocommerce' ); ?></h2>
                </div>
                <div class="aipn-table-card">
                    <table class="wp-list-table widefat fixed">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Product', 'ai-price-negotiator-for-woocommerce' ); ?></th>
                                <th><?php esc_html_e( 'Times Negotiated', 'ai-price-negotiator-for-woocommerce' ); ?></th>
                                <th><?php esc_html_e( 'Total Cart Value', 'ai-price-negotiator-for-woocommerce' ); ?></th>
                                <th><?php esc_html_e( 'Acceptance Rate', 'ai-price-negotiator-for-woocommerce' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $top_products as $product ) : ?>
                            <tr>
                                <td><?php echo esc_html( $product['name'] ); ?></td>
                                <td><?php echo esc_html( $product['count'] ); ?></td>
                                <td><?php echo esc_html( $currency . number_format( $product['total_cart_value'], 2 ) ); ?></td>
                                <td><?php echo esc_html( $product['acceptance_rate'] ); ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recent Negotiations -->
            <div class="aipn-analytics-section">
                <div class="aipn-analytics-section__header">
                    <h2><?php esc_html_e( 'Recent Negotiations', 'ai-price-negotiator-for-woocommerce' ); ?></h2>
                </div>

                <div class="aipn-table-card">
                    <table class="wp-list-table widefat fixed">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Date', 'ai-price-negotiator-for-woocommerce' ); ?></th>
                                <th><?php esc_html_e( 'Customer', 'ai-price-negotiator-for-woocommerce' ); ?></th>
                                <th><?php esc_html_e( 'Status', 'ai-price-negotiator-for-woocommerce' ); ?></th>
                                <th><?php esc_html_e( 'Cart Total', 'ai-price-negotiator-for-woocommerce' ); ?></th>
                                <th><?php esc_html_e( 'Final Price', 'ai-price-negotiator-for-woocommerce' ); ?></th>
                                <th><?php esc_html_e( 'Discount', 'ai-price-negotiator-for-woocommerce' ); ?></th>
                                <th><?php esc_html_e( 'Turns', 'ai-price-negotiator-for-woocommerce' ); ?></th>
                                <th><?php esc_html_e( 'Coupon', 'ai-price-negotiator-for-woocommerce' ); ?></th>
                                <th><?php esc_html_e( 'Chat', 'ai-price-negotiator-for-woocommerce' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( empty( $recent ) ) : ?>
                                <tr>
                                    <td colspan="9">
                                        <div class="aipn-empty-state">
                                            <div class="aipn-empty-state__icon"><?php echo esc_html( "\xF0\x9F\x92\xAC" ); ?></div>
                                            <p><?php esc_html_e( 'No negotiations recorded yet. They will appear here once customers start negotiating.', 'ai-price-negotiator-for-woocommerce' ); ?></p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else : ?>
                                <?php foreach ( $recent as $index => $row ) : ?>
                                <tr>
                                    <td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $row['created_at'] ) ) ); ?></td>
                                    <td>
                                        <?php
                                        $customer_name  = $row['customer_name'] ?? '';
                                        $customer_email = $row['customer_email'] ?? '';
                                        $customer_id    = (int) ( $row['customer_id'] ?? 0 );

                                        if ( $customer_name !== '' ) {
                                            echo esc_html( $customer_name );
                                            if ( $customer_email !== '' ) {
                                                echo '<br><small style="color:#6b7280;">' . esc_html( $customer_email ) . '</small>';
                                            }
                                        } elseif ( $customer_id > 0 ) {
                                            $user = get_userdata( $customer_id );
                                            echo $user ? esc_html( $user->display_name ) : esc_html( '#' . $customer_id );
                                        } else {
                                            esc_html_e( 'Guest', 'ai-price-negotiator-for-woocommerce' );
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="aipn-status-badge aipn-status-badge--<?php echo esc_attr( $row['status'] ); ?>">
                                            <?php echo esc_html( ucfirst( $row['status'] ) ); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html( $currency . number_format( (float) $row['cart_total'], 2 ) ); ?></td>
                                    <td><?php echo esc_html( $row['status'] === 'accepted' ? $currency . number_format( (float) $row['final_price'], 2 ) : '—' ); ?></td>
                                    <td><?php echo esc_html( $row['status'] === 'accepted' ? '-' . $currency . number_format( (float) $row['discount_amount'], 2 ) : '—' ); ?></td>
                                    <td><?php echo esc_html( $row['turn_count'] ); ?></td>
                                    <td><?php if ( $row['coupon_code'] ) : ?><span class="aipn-coupon-code"><?php echo esc_html( $row['coupon_code'] ); ?></span><?php else : ?>—<?php endif; ?></td>
                                    <td>
                                        <?php
                                        $chat_log = json_decode( $row['chat_log'] ?? '[]', true );
                                        if ( ! empty( $chat_log ) ) :
                                        ?>
                                            <button type="button" class="aipn-btn-chat aipn-view-chat" data-chat-index="<?php echo esc_attr( $index ); ?>">
                                                <?php esc_html_e( 'View', 'ai-price-negotiator-for-woocommerce' ); ?>
                                            </button>
                                        <?php else : ?>
                                            <span style="color: #9ca3af;">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php if ( ! empty( $chat_log ) ) : ?>
                                <tr class="aipn-chat-row" id="aipn-chat-row-<?php echo esc_attr( $index ); ?>" style="display: none;">
                                    <td colspan="9">
                                        <div class="aipn-chat-log">
                                            <div class="aipn-chat-log__header">
                                                <strong><?php esc_html_e( 'Chat Transcript', 'ai-price-negotiator-for-woocommerce' ); ?></strong>
                                                <span class="aipn-chat-log__meta">
                                                    <?php echo esc_html( $row['session_id'] ?? '' ); ?> &middot;
                                                    <?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $row['created_at'] ) ) ); ?>
                                                </span>
                                                <button type="button" class="aipn-chat-log__close aipn-close-chat" data-chat-index="<?php echo esc_attr( $index ); ?>">
                                                    <?php esc_html_e( 'Close', 'ai-price-negotiator-for-woocommerce' ); ?>
                                                </button>
                                            </div>
                                            <?php if ( ! empty( $row['cart_items'] ) ) :
                                                $cart_items = json_decode( $row['cart_items'], true );
                                                if ( ! empty( $cart_items ) ) :
                                            ?>
                                            <div class="aipn-chat-log__cart">
                                                <strong><?php esc_html_e( 'Cart Items:', 'ai-price-negotiator-for-woocommerce' ); ?></strong>
                                                <?php foreach ( $cart_items as $item ) : ?>
                                                    <span class="aipn-chat-log__cart-item"><?php echo esc_html( $item['name'] ?? '' ); ?> (x<?php echo esc_html( $item['quantity'] ?? 1 ); ?>) — <?php echo esc_html( $currency . number_format( (float) ( $item['line_total'] ?? 0 ), 2 ) ); ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php endif; endif; ?>
                                            <div class="aipn-chat-log__messages">
                                                <?php foreach ( $chat_log as $msg ) :
                                                    $role    = $msg['role'] ?? 'assistant';
                                                    $content = $msg['content'] ?? '';
                                                ?>
                                                    <div class="aipn-chat-log__msg aipn-chat-log__msg--<?php echo esc_attr( $role ); ?>">
                                                        <span class="aipn-chat-log__role"><?php echo esc_html( $role === 'user' ? __( 'Customer', 'ai-price-negotiator-for-woocommerce' ) : __( 'AI Agent', 'ai-price-negotiator-for-woocommerce' ) ); ?></span>
                                                        <span class="aipn-chat-log__text"><?php echo esc_html( $content ); ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    /* ─── AJAX: Export CSV ────────────────────────────────────────────── */

    public function ajax_export_csv(): void {
        check_ajax_referer( 'aipn_export_csv', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ai-price-negotiator-for-woocommerce' ) ) );
        }

        $period = isset( $_POST['period'] ) ? (int) $_POST['period'] : 30;
        $rows   = $this->logger->get_export_data( $period );

        $csv_rows = array();

        $csv_rows[] = $this->csv_encode_row( array(
            __( 'Date', 'ai-price-negotiator-for-woocommerce' ),
            __( 'Name', 'ai-price-negotiator-for-woocommerce' ),
            __( 'Email', 'ai-price-negotiator-for-woocommerce' ),
            __( 'Status', 'ai-price-negotiator-for-woocommerce' ),
            __( 'Cart Total', 'ai-price-negotiator-for-woocommerce' ),
            __( 'Final Price', 'ai-price-negotiator-for-woocommerce' ),
            __( 'Discount', 'ai-price-negotiator-for-woocommerce' ),
            __( 'Turns', 'ai-price-negotiator-for-woocommerce' ),
            __( 'Coupon Code', 'ai-price-negotiator-for-woocommerce' ),
        ) );

        foreach ( $rows as $row ) {
            $customer_name  = $row['customer_name'] ?? '';
            $customer_email = $row['customer_email'] ?? '';

            // Fallback to WP user data if name/email columns are empty (old records).
            if ( $customer_name === '' ) {
                $customer_id = (int) ( $row['customer_id'] ?? 0 );
                if ( $customer_id > 0 ) {
                    $user           = get_userdata( $customer_id );
                    $customer_name  = $user ? $user->display_name : '#' . $customer_id;
                    $customer_email = $customer_email ?: ( $user ? $user->user_email : '' );
                } else {
                    $customer_name = __( 'Guest', 'ai-price-negotiator-for-woocommerce' );
                }
            }

            $csv_rows[] = $this->csv_encode_row( array(
                $row['created_at'],
                $customer_name,
                $customer_email,
                ucfirst( $row['status'] ),
                $row['cart_total'],
                $row['final_price'],
                $row['discount_amount'],
                $row['turn_count'],
                $row['coupon_code'],
            ) );
        }

        $csv_content = implode( "\n", $csv_rows );

        wp_send_json_success( array(
            'csv'      => $csv_content,
            'filename' => 'negotiations-' . gmdate( 'Y-m-d' ) . '.csv',
        ) );
    }

    /**
     * Encode a single row as a CSV line without fopen/fputcsv.
     *
     * @param array $fields Row values.
     * @return string CSV-formatted line.
     */
    private function csv_encode_row( array $fields ): string {
        $escaped = array_map( static function ( $value ) {
            $value = (string) $value;
            return '"' . str_replace( '"', '""', $value ) . '"';
        }, $fields );

        return implode( ',', $escaped );
    }
}
