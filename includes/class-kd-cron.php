<?php

class KD_Cron {

    public static function init() {
        add_action( 'kd_sync_transactions', [ __CLASS__, 'sync_transactions' ] );
        add_action( 'kd_sync_clicks',       [ __CLASS__, 'sync_clicks' ] );

        if ( ! wp_next_scheduled( 'kd_sync_transactions' ) ) {
            wp_schedule_event( time(), 'kd_15min', 'kd_sync_transactions' );
        }
        if ( ! wp_next_scheduled( 'kd_sync_clicks' ) ) {
            wp_schedule_event( time(), 'daily', 'kd_sync_clicks' );
        }

        add_filter( 'cron_schedules', [ __CLASS__, 'add_schedules' ] );
    }

    public static function add_schedules( array $schedules ): array {
        $schedules['kd_15min'] = [
            'interval' => 900,
            'display'  => '15 minuten',
        ];
        return $schedules;
    }

    public static function deactivate() {
        wp_clear_scheduled_hook( 'kd_sync_transactions' );
        wp_clear_scheduled_hook( 'kd_sync_clicks' );
    }

    public static function sync_transactions() {
        $token = get_option( 'kd_api_key', '' );
        if ( ! $token ) return;

        $api          = new KD_API( $token );
        $updated_since = get_option( 'kd_next_updated_since', '' );

        if ( $updated_since ) {
            $result = $api->transactions( $updated_since );
        } else {
            // Eerste keer: haal laatste 90 dagen op
            $result = $api->transactions( '', date( 'Y-m-d', strtotime( '-90 days' ) ), date( 'Y-m-d' ) );
        }

        if ( is_wp_error( $result ) ) {
            update_option( 'kd_last_error', $result->get_error_message() );
            return;
        }

        $items = $result['data'] ?? [];
        foreach ( $items as $t ) {
            KD_DB::upsert_transaction( $t );
        }

        // Sla next_updated_since op voor volgende run
        if ( ! empty( $result['next_updated_since'] ) ) {
            update_option( 'kd_next_updated_since', $result['next_updated_since'] );
        }

        update_option( 'kd_last_sync', current_time( 'mysql' ) );
    }

    public static function sync_clicks() {
        $token = get_option( 'kd_api_key', '' );
        if ( ! $token ) return;

        $api    = new KD_API( $token );
        $from   = date( 'Y-m-d', strtotime( '-90 days' ) );
        $to     = date( 'Y-m-d', strtotime( '-1 day' ) ); // t/m gisteren
        $result = $api->clicks_daily( $from, $to );

        if ( is_wp_error( $result ) ) {
            update_option( 'kd_last_error', 'clicks: ' . $result->get_error_message() );
            update_option( 'kd_clicks_debug', 'WP_Error: ' . $result->get_error_message() );
            return;
        }

        update_option( 'kd_clicks_debug', wp_json_encode( $result ) );

        $items = $result['data'] ?? $result ?? [];
        foreach ( $items as $c ) {
            KD_DB::upsert_click( $c );
        }
    }
}
