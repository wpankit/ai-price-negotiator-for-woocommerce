<?php
/**
 * Logger — stores negotiation data in a custom database table.
 *
 * All database queries in this class use a custom table ({prefix}aipn_negotiations)
 * with no WordPress API equivalent. Table name is derived from $wpdb->prefix (trusted)
 * and a hardcoded suffix. All dynamic values go through $wpdb->prepare() placeholders.
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery
 * phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
 * phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
 *
 * @package AI_Price_Negotiator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AIPN_Logger {

    /**
     * Return the fully-qualified, escaped table name.
     */
    private function table_name(): string {
        global $wpdb;
        return esc_sql( $wpdb->prefix . 'aipn_negotiations' );
    }

    /**
     * Register hooks.
     */
    public function register(): void {
        add_action( 'aipn_session_ended', array( $this, 'log_negotiation' ) );
    }

    /**
     * Log a completed negotiation session.
     */
    public function log_negotiation( array $session ): void {
        global $wpdb;

        $wpdb->insert(
            $this->table_name(),
            array(
                'session_id'      => $session['session_id'] ?? '',
                'customer_id'     => get_current_user_id(),
                'customer_name'   => $session['customer_name'] ?? '',
                'customer_email'  => $session['customer_email'] ?? '',
                'cart_hash'       => $session['cart_hash'] ?? '',
                'cart_total'      => $session['cart_total'] ?? 0,
                'floor_total'     => $session['floor_total'] ?? 0,
                'final_price'     => $session['accepted_price'] ?? 0,
                'discount_amount' => ( $session['cart_total'] ?? 0 ) - ( $session['accepted_price'] ?? 0 ),
                'coupon_code'     => $session['coupon_code'] ?? '',
                'status'          => $session['status'] ?? 'unknown',
                'turn_count'      => $session['turn_count'] ?? 0,
                'chat_log'        => wp_json_encode( $session['conversation'] ?? array() ),
                'cart_items'      => wp_json_encode( $session['cart_items'] ?? array() ),
                'created_at'      => gmdate( 'Y-m-d H:i:s', $session['started_at'] ?? time() ),
                'updated_at'      => current_time( 'mysql', true ),
            ),
            array( '%s', '%d', '%s', '%s', '%s', '%f', '%f', '%f', '%f', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
        );
    }

    /**
     * Get negotiations with optional filters.
     *
     * @param array $args Query arguments.
     * @return array
     */
    public function get_negotiations( array $args = array() ): array {
        global $wpdb;

        $table    = $this->table_name();
        $defaults = array(
            'status'   => '',
            'limit'    => 50,
            'offset'   => 0,
            'order_by' => 'created_at',
            'order'    => 'DESC',
        );
        $args = wp_parse_args( $args, $defaults );

        $allowed_order_by = array( 'created_at', 'cart_total', 'discount_amount', 'turn_count' );
        $order_by         = esc_sql( in_array( $args['order_by'], $allowed_order_by, true ) ? $args['order_by'] : 'created_at' );
        $order            = esc_sql( strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC' );

        if ( ! empty( $args['status'] ) ) {
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM `{$table}` WHERE status = %s ORDER BY `{$order_by}` {$order} LIMIT %d OFFSET %d",
                    $args['status'],
                    (int) $args['limit'],
                    (int) $args['offset']
                ),
                ARRAY_A
            ) ?: array();
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM `{$table}` WHERE 1=1 ORDER BY `{$order_by}` {$order} LIMIT %d OFFSET %d",
                (int) $args['limit'],
                (int) $args['offset']
            ),
            ARRAY_A
        ) ?: array();
    }

    /**
     * Get summary statistics for the analytics dashboard.
     */
    public function get_stats( int $days = 30 ): array {
        global $wpdb;

        $table = $this->table_name();
        $since = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    COUNT(*) as total_negotiations,
                    SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted_count,
                    AVG(CASE WHEN status = 'accepted' THEN discount_amount ELSE NULL END) as avg_discount,
                    SUM(CASE WHEN status = 'accepted' THEN final_price ELSE 0 END) as total_revenue,
                    AVG(CASE WHEN status = 'accepted' THEN turn_count ELSE NULL END) as avg_turns,
                    AVG(CASE WHEN status = 'accepted' AND cart_total > 0 THEN (discount_amount / cart_total * 100) ELSE NULL END) as avg_discount_pct,
                    SUM(CASE WHEN status = 'accepted' THEN discount_amount ELSE 0 END) as total_discount_given,
                    SUM(CASE WHEN status IN ('expired','abandoned') THEN 1 ELSE 0 END) as abandoned_count,
                    AVG(cart_total) as avg_cart_value,
                    SUM(CASE WHEN status = 'accepted' THEN cart_total ELSE 0 END) as accepted_cart_total
                FROM `{$table}`
                WHERE created_at >= %s",
                $since
            ),
            ARRAY_A
        );

        return array(
            'total_negotiations' => (int) ( $row['total_negotiations'] ?? 0 ),
            'accepted_count'     => (int) ( $row['accepted_count'] ?? 0 ),
            'acceptance_rate'    => $row['total_negotiations'] > 0
                ? round( ( $row['accepted_count'] / $row['total_negotiations'] ) * 100, 1 )
                : 0,
            'avg_discount'       => round( (float) ( $row['avg_discount'] ?? 0 ), 2 ),
            'avg_discount_pct'   => round( (float) ( $row['avg_discount_pct'] ?? 0 ), 1 ),
            'total_revenue'      => round( (float) ( $row['total_revenue'] ?? 0 ), 2 ),
            'avg_turns'          => round( (float) ( $row['avg_turns'] ?? 0 ), 1 ),
            'total_discount_given' => round( (float) ( $row['total_discount_given'] ?? 0 ), 2 ),
            'abandoned_count'    => (int) ( $row['abandoned_count'] ?? 0 ),
            'avg_cart_value'     => round( (float) ( $row['avg_cart_value'] ?? 0 ), 2 ),
            'accepted_cart_total' => round( (float) ( $row['accepted_cart_total'] ?? 0 ), 2 ),
        );
    }

    /**
     * Get count breakdown by status for a given period.
     */
    public function get_status_breakdown( int $days = 30 ): array {
        global $wpdb;

        $table = $this->table_name();
        $since = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT status, COUNT(*) as cnt FROM `{$table}` WHERE created_at >= %s GROUP BY status",
                $since
            ),
            ARRAY_A
        );

        $breakdown = array( 'accepted' => 0, 'expired' => 0, 'abandoned' => 0 );
        $total     = 0;

        foreach ( $rows as $row ) {
            $s = $row['status'];
            if ( isset( $breakdown[ $s ] ) ) {
                $breakdown[ $s ] = (int) $row['cnt'];
            }
            $total += (int) $row['cnt'];
        }

        $breakdown['total'] = $total;

        return $breakdown;
    }

    /**
     * Get the most frequently negotiated products.
     */
    public function get_top_products( int $days = 30, int $limit = 5 ): array {
        global $wpdb;

        $table = $this->table_name();
        $since = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT cart_items, status, cart_total FROM `{$table}` WHERE created_at >= %s",
                $since
            ),
            ARRAY_A
        );

        $products = array();

        foreach ( $rows as $row ) {
            $items = json_decode( $row['cart_items'] ?? '[]', true );
            if ( ! is_array( $items ) ) {
                continue;
            }
            foreach ( $items as $item ) {
                $name = $item['name'] ?? '';
                if ( '' === $name ) {
                    continue;
                }
                if ( ! isset( $products[ $name ] ) ) {
                    $products[ $name ] = array( 'name' => $name, 'count' => 0, 'total_cart_value' => 0, 'accepted' => 0 );
                }
                $products[ $name ]['count']++;
                $products[ $name ]['total_cart_value'] += (float) $row['cart_total'];
                if ( 'accepted' === $row['status'] ) {
                    $products[ $name ]['accepted']++;
                }
            }
        }

        usort( $products, function ( $a, $b ) {
            return $b['count'] - $a['count'];
        } );

        $products = array_slice( $products, 0, $limit );
        foreach ( $products as &$p ) {
            $p['acceptance_rate']  = $p['count'] > 0 ? round( ( $p['accepted'] / $p['count'] ) * 100, 1 ) : 0;
            $p['total_cart_value'] = round( $p['total_cart_value'], 2 );
        }

        return $products;
    }

    /**
     * Get the most recent negotiation for a customer within a time window.
     *
     * Used for cooldown enforcement — checks if the customer negotiated recently.
     *
     * @param string $email      Customer email.
     * @param int    $user_id    Customer user ID (0 for guests).
     * @param int    $hours      Lookback window in hours.
     * @return array|null        Previous negotiation row or null.
     */
    public function get_recent_negotiation( string $email, int $user_id, int $hours ): ?array {
        global $wpdb;

        $table = $this->table_name();
        $since = gmdate( 'Y-m-d H:i:s', strtotime( "-{$hours} hours" ) );

        // Build query based on available identifiers.
        if ( $email !== '' && $user_id > 0 ) {
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM `{$table}` WHERE created_at >= %s AND (customer_email = %s OR customer_id = %d) ORDER BY created_at DESC LIMIT 1",
                    $since,
                    $email,
                    $user_id
                ),
                ARRAY_A
            );
        } elseif ( $email !== '' ) {
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM `{$table}` WHERE created_at >= %s AND customer_email = %s ORDER BY created_at DESC LIMIT 1",
                    $since,
                    $email
                ),
                ARRAY_A
            );
        } elseif ( $user_id > 0 ) {
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM `{$table}` WHERE created_at >= %s AND customer_id = %d ORDER BY created_at DESC LIMIT 1",
                    $since,
                    $user_id
                ),
                ARRAY_A
            );
        } else {
            return null;
        }

        return $row ?: null;
    }

    /**
     * Get negotiation data for CSV export.
     */
    public function get_export_data( int $days = 30 ): array {
        global $wpdb;

        $table = $this->table_name();
        $since = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT created_at, customer_name, customer_email, customer_id, status, cart_total, final_price,
                        discount_amount, turn_count, coupon_code
                 FROM `{$table}`
                 WHERE created_at >= %s
                 ORDER BY created_at DESC",
                $since
            ),
            ARRAY_A
        ) ?: array();
    }
}
