<?php
/**
 * Admin template: Negotiation details meta box on order page.
 *
 * @var array $data Negotiation data from order meta.
 * @package AI_Price_Negotiator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$aipn_currency = html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' );
?>

<div class="aipn-order-meta">
    <style>
        .aipn-order-meta { font-size: 13px; color: #374151; }
        .aipn-order-meta table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .aipn-order-meta th,
        .aipn-order-meta td { padding: 8px 0; text-align: left; font-size: 12.5px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
        .aipn-order-meta th { font-weight: 500; color: #6b7280; width: 42%; }
        .aipn-order-meta td { color: #1f2937; font-weight: 500; }

        .aipn-order-meta .aipn-val-green { color: #059669; font-weight: 600; }
        .aipn-order-meta .aipn-val-red { color: #dc2626; font-weight: 600; }
        .aipn-order-meta .aipn-val-code {
            font-family: ui-monospace, 'SFMono-Regular', monospace;
            font-size: 11px;
            background: #f3f4f6;
            padding: 2px 7px;
            border-radius: 4px;
            color: #4B5563;
        }

        /* Breakdown */
        .aipn-order-meta .aipn-breakdown { margin-top: 14px; }
        .aipn-order-meta .aipn-section-label {
            font-size: 11px;
            font-weight: 600;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            display: block;
        }
        .aipn-order-meta .aipn-breakdown th {
            font-size: 11px;
            color: #9ca3af;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            background: #f9fafb;
            padding: 6px 8px;
            border-radius: 4px 4px 0 0;
        }
        .aipn-order-meta .aipn-breakdown td { font-size: 12px; padding: 7px 8px; }

        /* Chat Toggle */
        .aipn-order-meta .aipn-chat-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 500;
            color: #F59E0B;
            background: #FFFBEB;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.15s ease;
            margin-top: 4px;
        }
        .aipn-order-meta .aipn-chat-btn:hover { background: #E0E7FF; }

        /* Chat Log */
        .aipn-order-meta .aipn-chat-log {
            display: none;
            margin-top: 12px;
            max-height: 350px;
            overflow-y: auto;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #f9fafb;
        }
        .aipn-order-meta .aipn-chat-msg {
            padding: 8px 14px;
            font-size: 12px;
            line-height: 1.55;
            border-bottom: 1px solid #f3f4f6;
        }
        .aipn-order-meta .aipn-chat-msg:last-child { border-bottom: none; }
        .aipn-order-meta .aipn-chat-msg strong {
            display: block;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 3px;
        }
        .aipn-order-meta .aipn-chat-msg--user strong { color: #D97706; }
        .aipn-order-meta .aipn-chat-msg--assistant strong { color: #059669; }
    </style>

    <!-- Summary -->
    <table>
        <tr>
            <th><?php esc_html_e( 'Original Total', 'ai-price-negotiator-for-woocommerce' ); ?></th>
            <td><?php echo esc_html( $aipn_currency . number_format( $data['cart_total'] ?? 0, 2 ) ); ?></td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Accepted Price', 'ai-price-negotiator-for-woocommerce' ); ?></th>
            <td class="aipn-val-green"><?php echo esc_html( $aipn_currency . number_format( $data['accepted_price'] ?? 0, 2 ) ); ?></td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Discount', 'ai-price-negotiator-for-woocommerce' ); ?></th>
            <td class="aipn-val-red">
                -<?php echo esc_html( $aipn_currency . number_format( $data['discount_amount'] ?? 0, 2 ) ); ?>
                <?php
                if ( ( $data['cart_total'] ?? 0 ) > 0 ) {
                    $aipn_pct = round( ( ( $data['discount_amount'] ?? 0 ) / $data['cart_total'] ) * 100, 1 );
                    echo ' <small style="color:#9ca3af;font-weight:400;">(' . esc_html( $aipn_pct . '%' ) . ')</small>';
                }
                ?>
            </td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Coupon', 'ai-price-negotiator-for-woocommerce' ); ?></th>
            <td><span class="aipn-val-code"><?php echo esc_html( $data['coupon_code'] ?? '—' ); ?></span></td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Turns', 'ai-price-negotiator-for-woocommerce' ); ?></th>
            <td><?php echo esc_html( $data['turn_count'] ?? 0 ); ?></td>
        </tr>
        <?php if ( ! empty( $data['offers'] ) ) : ?>
        <tr>
            <th><?php esc_html_e( 'Offers', 'ai-price-negotiator-for-woocommerce' ); ?></th>
            <td><?php echo esc_html( implode( ' → ', array_map( function( $o ) use ( $aipn_currency ) { return $aipn_currency . number_format( $o, 2 ); }, $data['offers'] ) ) ); ?></td>
        </tr>
        <?php endif; ?>
    </table>

    <!-- Per-product breakdown -->
    <?php if ( ! empty( $data['breakdown'] ) ) : ?>
    <div class="aipn-breakdown">
        <span class="aipn-section-label"><?php esc_html_e( 'Per-Product Breakdown', 'ai-price-negotiator-for-woocommerce' ); ?></span>
        <table>
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Product', 'ai-price-negotiator-for-woocommerce' ); ?></th>
                    <th><?php esc_html_e( 'Original', 'ai-price-negotiator-for-woocommerce' ); ?></th>
                    <th><?php esc_html_e( 'Discount', 'ai-price-negotiator-for-woocommerce' ); ?></th>
                    <th><?php esc_html_e( 'Final', 'ai-price-negotiator-for-woocommerce' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $data['breakdown'] as $aipn_item ) : ?>
                <tr>
                    <td><?php echo esc_html( $aipn_item['name'] ); ?> <?php if ( $aipn_item['quantity'] > 1 ) echo '&times;' . esc_html( $aipn_item['quantity'] ); ?></td>
                    <td><?php echo esc_html( $aipn_currency . number_format( $aipn_item['line_total'], 2 ) ); ?></td>
                    <td class="aipn-val-red">-<?php echo esc_html( $aipn_currency . number_format( $aipn_item['discount'], 2 ) ); ?></td>
                    <td><?php echo esc_html( $aipn_currency . number_format( $aipn_item['effective_total'], 2 ) ); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Chat log toggle -->
    <?php if ( ! empty( $data['conversation'] ) ) : ?>
    <p style="margin-bottom:0;">
        <button type="button" class="aipn-chat-btn" onclick="var el=document.getElementById('aipn-chat-log');el.style.display=el.style.display==='none'?'block':'none';">
            <?php esc_html_e( 'View Chat', 'ai-price-negotiator-for-woocommerce' ); ?> (<?php echo count( $data['conversation'] ); ?>)
        </button>
    </p>
    <div id="aipn-chat-log" class="aipn-chat-log">
        <?php foreach ( $data['conversation'] as $aipn_msg ) :
            $aipn_role = $aipn_msg['role'] ?? 'assistant';
        ?>
        <div class="aipn-chat-msg aipn-chat-msg--<?php echo esc_attr( $aipn_role ); ?>">
            <strong><?php echo esc_html( $aipn_role === 'user' ? __( 'Customer', 'ai-price-negotiator-for-woocommerce' ) : __( 'AI Agent', 'ai-price-negotiator-for-woocommerce' ) ); ?></strong>
            <?php echo esc_html( $aipn_msg['content'] ?? '' ); ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
