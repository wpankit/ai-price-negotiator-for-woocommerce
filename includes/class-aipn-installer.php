<?php
/**
 * Installer — creates custom DB tables on activation.
 *
 * @package AI_Price_Negotiator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AIPN_Installer {

    private const DB_VERSION_OPTION = 'aipn_db_version';
    private const DB_VERSION        = '0.0.1';

    /**
     * Run on plugin activation.
     */
    public static function activate(): void {
        self::create_tables();
        update_option( self::DB_VERSION_OPTION, self::DB_VERSION );

        // Set default options if they don't exist.
        if ( get_option( 'aipn_enabled' ) === false ) {
            update_option( 'aipn_enabled', 'yes' );
        }
        if ( get_option( 'aipn_global_floor_pct' ) === false ) {
            update_option( 'aipn_global_floor_pct', '90' );
        }
        if ( get_option( 'aipn_widget_position' ) === false ) {
            update_option( 'aipn_widget_position', 'before_submit' );
        }
    }

    /**
     * Create the negotiations log table.
     */
    private static function create_tables(): void {
        global $wpdb;

        $table_name      = $wpdb->prefix . 'aipn_negotiations';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id VARCHAR(50) NOT NULL DEFAULT '',
            customer_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            customer_name VARCHAR(100) NOT NULL DEFAULT '',
            customer_email VARCHAR(100) NOT NULL DEFAULT '',
            cart_hash VARCHAR(32) NOT NULL DEFAULT '',
            cart_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            floor_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            final_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            coupon_code VARCHAR(50) NOT NULL DEFAULT '',
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            turn_count INT UNSIGNED NOT NULL DEFAULT 0,
            chat_log LONGTEXT,
            cart_items LONGTEXT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY session_id (session_id),
            KEY customer_id (customer_id),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Check if DB needs upgrading (for future migrations).
     */
    public static function maybe_upgrade(): void {
        $installed_version = get_option( self::DB_VERSION_OPTION, '0' );

        if ( version_compare( $installed_version, self::DB_VERSION, '<' ) ) {
            self::create_tables();
            update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
        }
    }
}
