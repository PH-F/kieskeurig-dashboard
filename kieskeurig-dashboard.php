<?php
/**
 * Plugin Name: Kieskeurig Dashboard
 * Description: Toont Kieskeurig Partner API sales, klikken en vergoedingen in een WP admin dashboard.
 * Version: 1.0.8
 * Author: PH-F
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'KD_VERSION', '1.0.0' );
define( 'KD_PATH', plugin_dir_path( __FILE__ ) );
define( 'KD_URL', plugin_dir_url( __FILE__ ) );

require_once KD_PATH . 'includes/class-kd-db.php';
require_once KD_PATH . 'includes/class-kd-api.php';
require_once KD_PATH . 'includes/class-kd-cron.php';
require_once KD_PATH . 'includes/class-kd-admin.php';

register_activation_hook( __FILE__, [ 'KD_DB', 'install' ] );
register_deactivation_hook( __FILE__, [ 'KD_Cron', 'deactivate' ] );

add_action( 'plugins_loaded', function () {
    KD_Cron::init();
    KD_Admin::init();
} );
