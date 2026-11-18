<?php

class KD_DB {

    public static function install() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $sql_transactions = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}kd_transactions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            transaction_id VARCHAR(100) NOT NULL,
            shop_name VARCHAR(255) DEFAULT '',
            order_amount DECIMAL(10,2) DEFAULT 0,
            commission DECIMAL(10,2) DEFAULT 0,
            status VARCHAR(50) DEFAULT '',
            transaction_date DATETIME DEFAULT NULL,
            updated_at DATETIME DEFAULT NULL,
            raw JSON DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY transaction_id (transaction_id),
            KEY status (status),
            KEY transaction_date (transaction_date)
        ) $charset;";

        $sql_clicks = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}kd_clicks (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            click_date DATE NOT NULL,
            shop_name VARCHAR(255) DEFAULT '',
            clicks INT DEFAULT 0,
            commission DECIMAL(10,2) DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY date_shop (click_date, shop_name)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_transactions );
        dbDelta( $sql_clicks );

        add_option( 'kd_db_version', KD_VERSION );
    }

    public static function upsert_transaction( array $t ) {
        global $wpdb;
        $wpdb->replace(
            $wpdb->prefix . 'kd_transactions',
            [
                'transaction_id'   => $t['transaction_id'] ?? '',
                'shop_name'        => $t['shop'] ?? '',
                'order_amount'     => $t['sale_amount'] ?? 0,
                'commission'       => $t['vergoeding'] ?? 0,
                'status'           => $t['status'] ?? '',
                'transaction_date' => isset( $t['order_at'] ) ? date( 'Y-m-d H:i:s', strtotime( $t['order_at'] ) ) : null,
                'updated_at'       => isset( $t['updated_at'] ) ? date( 'Y-m-d H:i:s', strtotime( $t['updated_at'] ) ) : current_time( 'mysql' ),
                'raw'              => wp_json_encode( $t ),
            ]
        );
    }

    public static function upsert_click( array $c ) {
        global $wpdb;
        $wpdb->replace(
            $wpdb->prefix . 'kd_clicks',
            [
                'click_date' => $c['dag'] ?? '',
                'shop_name'  => $c['shop'] ?? '',
                'clicks'     => $c['clicks'] ?? 0,
                'commission' => $c['vergoeding_cpc'] ?? 0,
            ]
        );
    }

    public static function get_summary( string $from, string $to ): array {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT shop_name,
                    COUNT(*) AS total_orders,
                    SUM(order_amount) AS total_amount,
                    SUM(commission) AS total_commission,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected
             FROM {$wpdb->prefix}kd_transactions
             WHERE transaction_date BETWEEN %s AND %s
             GROUP BY shop_name
             ORDER BY total_commission DESC",
            $from . ' 00:00:00',
            $to . ' 23:59:59'
        ), ARRAY_A );
    }

    public static function get_transactions( string $from, string $to, int $page = 1, int $per_page = 50 ): array {
        global $wpdb;
        $offset = ( $page - 1 ) * $per_page;
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT transaction_id, shop_name, order_amount, commission, status, transaction_date
             FROM {$wpdb->prefix}kd_transactions
             WHERE transaction_date BETWEEN %s AND %s
             ORDER BY transaction_date DESC
             LIMIT %d OFFSET %d",
            $from . ' 00:00:00',
            $to . ' 23:59:59',
            $per_page,
            $offset
        ), ARRAY_A );

        $total = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}kd_transactions WHERE transaction_date BETWEEN %s AND %s",
            $from . ' 00:00:00',
            $to . ' 23:59:59'
        ) );

        return [ 'rows' => $rows, 'total' => $total ];
    }

    public static function get_clicks_chart( string $from, string $to ): array {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT click_date, SUM(clicks) AS clicks, SUM(commission) AS commission
             FROM {$wpdb->prefix}kd_clicks
             WHERE click_date BETWEEN %s AND %s
             GROUP BY click_date
             ORDER BY click_date ASC",
            $from,
            $to
        ), ARRAY_A );
    }

    public static function get_commission_chart( string $from, string $to ): array {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT DATE(transaction_date) AS day, SUM(commission) AS commission
             FROM {$wpdb->prefix}kd_transactions
             WHERE transaction_date BETWEEN %s AND %s AND status = 'approved'
             GROUP BY day
             ORDER BY day ASC",
            $from . ' 00:00:00',
            $to . ' 23:59:59'
        ), ARRAY_A );
    }
}
