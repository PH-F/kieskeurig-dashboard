<?php

class KD_Admin {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
        add_action( 'wp_ajax_kd_manual_sync', [ __CLASS__, 'ajax_manual_sync' ] );
    }

    public static function add_menu() {
        add_menu_page(
            'Kieskeurig Dashboard',
            'Kieskeurig',
            'manage_options',
            'kieskeurig-dashboard',
            [ __CLASS__, 'page_dashboard' ],
            'dashicons-chart-bar',
            30
        );
        add_submenu_page(
            'kieskeurig-dashboard',
            'Instellingen',
            'Instellingen',
            'manage_options',
            'kieskeurig-settings',
            [ __CLASS__, 'page_settings' ]
        );
    }

    public static function register_settings() {
        register_setting( 'kd_settings', 'kd_api_key', [ 'sanitize_callback' => 'sanitize_text_field' ] );
    }

    public static function enqueue( string $hook ) {
        if ( strpos( $hook, 'kieskeurig' ) === false ) return;
        wp_enqueue_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js', [], null, false );
        wp_enqueue_style( 'kd-admin', KD_URL . 'assets/admin.css', [], KD_VERSION );
    }

    public static function ajax_manual_sync() {
        check_ajax_referer( 'kd_manual_sync' );
        KD_Cron::sync_transactions();
        KD_Cron::sync_clicks();
        wp_send_json_success( [
            'message'      => 'Sync klaar om ' . current_time( 'H:i:s' ),
            'clicks_debug' => get_option( 'kd_clicks_debug', '' ),
        ] );
    }

    public static function page_settings() {
        $last_sync  = get_option( 'kd_last_sync', '—' );
        $last_error = get_option( 'kd_last_error', '' );
        ?>
        <div class="wrap">
            <h1>Kieskeurig Instellingen</h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'kd_settings' ); ?>
                <table class="form-table">
                    <tr>
                        <th>API Sleutel</th>
                        <td>
                            <input type="text" name="kd_api_key" value="<?= esc_attr( get_option( 'kd_api_key' ) ); ?>" class="regular-text" placeholder="wl_xxxx_...">
                            <p class="description">Te vinden in je Kieskeurig partner account.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>

            <hr>
            <h2>Status</h2>
            <p><strong>Laatste sync:</strong> <?= esc_html( $last_sync ); ?></p>
            <?php if ( $last_error ) : ?>
                <p style="color:red;"><strong>Laatste fout:</strong> <?= esc_html( $last_error ); ?></p>
            <?php endif; ?>
            <button id="kd-sync-btn" class="button button-secondary">Nu synchroniseren</button>
            <span id="kd-sync-status" style="margin-left:10px;"></span>
            <script>
            jQuery('#kd-sync-btn').on('click', function() {
                var $btn = jQuery(this);
                $btn.prop('disabled', true);
                jQuery('#kd-sync-status').text('Bezig...');
                jQuery.post(ajaxurl, {
                    action: 'kd_manual_sync',
                    _ajax_nonce: '<?= wp_create_nonce( 'kd_manual_sync' ); ?>'
                }, function(res) {
                    jQuery('#kd-sync-status').text(res.data.message);
                    if (res.data.clicks_debug) {
                        jQuery('#kd-sync-status').append('<br><pre style="background:#f0f0f0;padding:8px;margin-top:8px;overflow:auto;max-height:300px;">' + res.data.clicks_debug + '</pre>');
                    }
                    $btn.prop('disabled', false);
                });
            });
            </script>
        </div>
        <?php
    }

    public static function page_dashboard() {
        $from = sanitize_text_field( $_GET['from'] ?? date( 'Y-m-d', strtotime( '-90 days' ) ) );
        $to   = sanitize_text_field( $_GET['to']   ?? date( 'Y-m-d' ) );
        $page = max( 1, (int) ( $_GET['paged'] ?? 1 ) );

        $launch_time  = new DateTime( '2026-11-18 19:00:00', new DateTimeZone( 'Europe/Amsterdam' ) );
        $now          = new DateTime( 'now', new DateTimeZone( 'Europe/Amsterdam' ) );
        $in_onderhoud = $now < $launch_time;

        $summary     = KD_DB::get_summary( $from, $to );
        $transactions = KD_DB::get_transactions( $from, $to, $page );
        $clicks_data  = KD_DB::get_clicks_chart( $from, $to );
        $commission_data = KD_DB::get_commission_chart( $from, $to );

        $click_labels      = wp_json_encode( array_column( $clicks_data, 'click_date' ) );
        $click_values      = wp_json_encode( array_map( 'intval', array_column( $clicks_data, 'clicks' ) ) );
        $commission_labels = wp_json_encode( array_column( $commission_data, 'day' ) );
        $commission_values = wp_json_encode( array_map( 'floatval', array_column( $commission_data, 'commission' ) ) );

        $total_pages = ceil( $transactions['total'] / 50 );
        ?>
        <div class="wrap kd-wrap">
            <h1>Kieskeurig Dashboard</h1>

            <form method="get" class="kd-filter">
                <input type="hidden" name="page" value="kieskeurig-dashboard">
                <label>Van: <input type="date" name="from" value="<?= esc_attr( $from ); ?>"></label>
                <label>Tot: <input type="date" name="to" value="<?= esc_attr( $to ); ?>"></label>
                <button type="submit" class="button">Filter</button>
            </form>

            <?php if ( $in_onderhoud ) : ?>
            <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:4px;padding:20px 24px;margin:24px 0;font-size:15px;">
                <strong>In onderhoud</strong> — Het dashboard is beschikbaar vanaf 18 november 2026 om 19:00 uur.
            </div>
            <?php return; endif; ?>

            <h2>Samenvatting per winkel</h2>
            <?php if ( $summary ) : ?>
            <table class="wp-list-table widefat fixed striped kd-table">
                <thead>
                    <tr>
                        <th>Winkel</th>
                        <th>Orders</th>
                        <th>Omzet</th>
                        <th>Vergoeding</th>
                        <th>Goedgekeurd</th>
                        <th>In behandeling</th>
                        <th>Afgekeurd</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $summary as $row ) : ?>
                    <tr>
                        <td><?= esc_html( $row['shop_name'] ); ?></td>
                        <td><?= (int) $row['total_orders']; ?></td>
                        <td>€ <?= number_format( $row['total_amount'], 2, ',', '.' ); ?></td>
                        <td>€ <?= number_format( $row['total_commission'], 2, ',', '.' ); ?></td>
                        <td><?= (int) $row['approved']; ?></td>
                        <td><?= (int) $row['pending']; ?></td>
                        <td><?= (int) $row['rejected']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else : ?>
                <p>Geen data voor deze periode.</p>
            <?php endif; ?>

            <div class="kd-charts">
                <div class="kd-chart-box">
                    <h2>Kliks per dag</h2>
                    <canvas id="kd-clicks-chart"></canvas>
                </div>
                <div class="kd-chart-box">
                    <h2>Vergoeding per dag (goedgekeurd)</h2>
                    <canvas id="kd-commission-chart"></canvas>
                </div>
            </div>

            <h2>Transacties</h2>
            <?php if ( $transactions['rows'] ) : ?>
            <table class="wp-list-table widefat fixed striped kd-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Winkel</th>
                        <th>Orderbedrag</th>
                        <th>Vergoeding</th>
                        <th>Status</th>
                        <th>Datum</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $transactions['rows'] as $t ) : ?>
                    <tr>
                        <td><?= esc_html( $t['transaction_id'] ); ?></td>
                        <td><?= esc_html( $t['shop_name'] ); ?></td>
                        <td>€ <?= number_format( $t['order_amount'], 2, ',', '.' ); ?></td>
                        <td>€ <?= number_format( $t['commission'], 2, ',', '.' ); ?></td>
                        <td><span class="kd-status kd-status--<?= esc_attr( $t['status'] ); ?>"><?= esc_html( $t['status'] ); ?></span></td>
                        <td><?= esc_html( $t['transaction_date'] ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ( $total_pages > 1 ) : ?>
            <div class="kd-pagination">
                <?php for ( $i = 1; $i <= $total_pages; $i++ ) : ?>
                    <a href="<?= esc_url( add_query_arg( [ 'paged' => $i, 'from' => $from, 'to' => $to ] ) ); ?>"
                       class="button <?= $i === $page ? 'button-primary' : ''; ?>"><?= $i; ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
            <?php else : ?>
                <p>Geen transacties voor deze periode.</p>
            <?php endif; ?>
        </div>

        <script>
        (function() {
            console.log('KD clicks labels:', <?= $click_labels; ?>);
            console.log('KD clicks values:', <?= $click_values; ?>);
            var clickCtx = document.getElementById('kd-clicks-chart');
            if (clickCtx) {
                new Chart(clickCtx, {
                    type: 'bar',
                    data: {
                        labels: <?= $click_labels; ?>,
                        datasets: [{ label: 'Klikken', data: <?= $click_values; ?>, backgroundColor: '#2271b1' }]
                    },
                    options: { responsive: true, plugins: { legend: { display: false } } }
                });
            }
            var commCtx = document.getElementById('kd-commission-chart');
            if (commCtx) {
                new Chart(commCtx, {
                    type: 'line',
                    data: {
                        labels: <?= $commission_labels; ?>,
                        datasets: [{ label: '€ Vergoeding', data: <?= $commission_values; ?>, borderColor: '#00a32a', fill: true, backgroundColor: 'rgba(0,163,42,.1)' }]
                    },
                    options: { responsive: true, plugins: { legend: { display: false } } }
                });
            }
        })();
        </script>
        <?php
    }
}
