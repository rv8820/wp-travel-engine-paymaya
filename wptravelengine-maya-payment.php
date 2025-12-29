<?php
/**
 * Plugin Name: WP Travel Engine - Maya Payment
 * Plugin URI: https://github.com/rv8820/wp-travel-engine-paymaya
 * Description: Maya Payment Gateway integration for WP Travel Engine
 * Version: 1.0.0
 * Author: WP Travel Engine
 * Author URI: https://wptravelengine.com/
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: wptravelengine-maya-payment
 * Domain Path: /languages
 * Requires at least: 5.9
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'WPTRAVELENGINE_MAYA_VERSION', '1.0.0' );
define( 'WPTRAVELENGINE_MAYA_FILE_PATH', __FILE__ );
define( 'WPTRAVELENGINE_MAYA_ABSPATH', dirname( WPTRAVELENGINE_MAYA_FILE_PATH ) . '/' );
define( 'WPTRAVELENGINE_MAYA_BASE_PATH', plugin_basename( WPTRAVELENGINE_MAYA_FILE_PATH ) );
define( 'WPTRAVELENGINE_MAYA_BASE_NAME', dirname( plugin_basename( WPTRAVELENGINE_MAYA_FILE_PATH ) ) );

/**
 * Check if WP Travel Engine is active
 */
function wptravelengine_maya_check_compatibility() {
    if ( ! defined( 'WP_TRAVEL_ENGINE_VERSION' ) ) {
        add_action( 'admin_notices', 'wptravelengine_maya_admin_notice' );
        return false;
    }
    return true;
}

/**
 * Display admin notice if WP Travel Engine is not active
 */
function wptravelengine_maya_admin_notice() {
    ?>
    <div class="notice notice-error">
        <p>
            <?php
            echo wp_kses_post(
                sprintf(
                    __( '<strong>WP Travel Engine - Maya Payment</strong> requires WP Travel Engine to be installed and activated. Please install <a href="%s" target="_blank">WP Travel Engine</a>.', 'wptravelengine-maya-payment' ),
                    'https://wordpress.org/plugins/wp-travel-engine/'
                )
            );
            ?>
        </p>
    </div>
    <?php
}

/**
 * PSR-4 Autoloader for WPTravelEngineMaya namespace
 */
spl_autoload_register( function ( $class ) {
    // Only autoload classes in our namespace
    $prefix = 'WPTravelEngineMaya\\';
    $base_dir = WPTRAVELENGINE_MAYA_ABSPATH . 'includes/';

    // Check if the class uses the namespace prefix
    $len = strlen( $prefix );
    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        return;
    }

    // Get the relative class name
    $relative_class = substr( $class, $len );

    // Replace namespace separators with directory separators
    $file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

    // If the file exists, require it
    if ( file_exists( $file ) ) {
        require $file;
    }
} );

/**
 * Initialize the plugin
 */
function wptravelengine_maya_payment_init() {
    // Check compatibility
    if ( ! wptravelengine_maya_check_compatibility() ) {
        return;
    }

    // Initialize plugin
    WPTravelEngineMaya\Plugin::instance();
}
add_action( 'plugins_loaded', 'wptravelengine_maya_payment_init', 9 );
