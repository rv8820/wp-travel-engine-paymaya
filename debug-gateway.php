<?php
/**
 * Debug Script for Maya Gateway
 *
 * Usage: Place this file in the plugin root and access it via:
 * wp-admin/admin.php?page=debug-maya-gateway
 *
 * Or run directly from wp-content/plugins/wte-maya/debug-gateway.php
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    // If accessed directly, load WordPress
    require_once( '../../../wp-load.php' );
}

echo '<h1>WTE-Maya Gateway Debug</h1>';

// Check if WP Travel Engine is active
echo '<h2>1. WP Travel Engine Status</h2>';
if ( class_exists( 'WP_Travel_Engine' ) ) {
    echo '✅ WP Travel Engine is active<br>';
} else {
    echo '❌ WP Travel Engine is NOT active - Plugin will not work!<br>';
}

// Check if BaseGateway class exists
echo '<h2>2. BaseGateway Class</h2>';
if ( class_exists( 'WPTravelEngine\\PaymentGateways\\BaseGateway' ) ) {
    echo '✅ BaseGateway class exists<br>';
} else {
    echo '❌ BaseGateway class NOT found - WTE version might be too old<br>';
}

// Check if our classes are loaded
echo '<h2>3. WTE-Maya Classes</h2>';
if ( class_exists( 'WTE_Maya_Plugin' ) ) {
    echo '✅ WTE_Maya_Plugin class loaded<br>';
} else {
    echo '❌ WTE_Maya_Plugin class NOT loaded - Plugin may not be activated<br>';
}

if ( class_exists( 'WTE_Maya_Gateway' ) ) {
    echo '✅ WTE_Maya_Gateway class loaded<br>';
} else {
    echo '❌ WTE_Maya_Gateway class NOT loaded<br>';
}

if ( class_exists( 'WTE_Maya_API_Client' ) ) {
    echo '✅ WTE_Maya_API_Client class loaded<br>';
} else {
    echo '❌ WTE_Maya_API_Client class NOT loaded<br>';
}

// Check registered payment gateways
echo '<h2>4. Registered Payment Gateways</h2>';
if ( function_exists( 'wptravelengine_payment_gateways' ) ) {
    $gateways = wptravelengine_payment_gateways();
    echo 'Found ' . count( $gateways ) . ' gateways:<br>';
    echo '<ul>';
    foreach ( $gateways as $id => $gateway ) {
        $class = get_class( $gateway );
        echo "<li><strong>$id</strong>: $class</li>";
    }
    echo '</ul>';

    if ( isset( $gateways['maya'] ) ) {
        echo '✅ Maya gateway is registered!<br>';
    } else {
        echo '❌ Maya gateway is NOT registered<br>';
    }
} else {
    echo '❌ wptravelengine_payment_gateways() function not found<br>';
}

// Check WTE version
echo '<h2>5. WP Travel Engine Version</h2>';
if ( defined( 'WP_TRAVEL_ENGINE_VERSION' ) ) {
    echo 'Version: ' . WP_TRAVEL_ENGINE_VERSION . '<br>';
} else {
    echo 'Version: Unknown<br>';
}

// Check for errors in error log
echo '<h2>6. Recent PHP Errors</h2>';
if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
    $log_file = WP_CONTENT_DIR . '/debug.log';
    if ( file_exists( $log_file ) ) {
        $log_contents = file_get_contents( $log_file );
        $lines = explode( "\n", $log_contents );
        $maya_errors = array_filter( $lines, function( $line ) {
            return strpos( $line, 'WTE-Maya' ) !== false || strpos( $line, 'Maya' ) !== false;
        } );

        if ( ! empty( $maya_errors ) ) {
            echo '<pre>' . esc_html( implode( "\n", array_slice( $maya_errors, -20 ) ) ) . '</pre>';
        } else {
            echo 'No Maya-related errors found<br>';
        }
    } else {
        echo 'Debug log file not found<br>';
    }
} else {
    echo 'Debug logging is not enabled. Add to wp-config.php:<br>';
    echo '<code>define( \'WP_DEBUG\', true );<br>define( \'WP_DEBUG_LOG\', true );</code><br>';
}

// Plugin file path
echo '<h2>7. Plugin File Paths</h2>';
if ( defined( 'WTE_MAYA_PLUGIN_DIR' ) ) {
    echo 'Plugin Dir: ' . WTE_MAYA_PLUGIN_DIR . '<br>';
    echo 'Exists: ' . ( file_exists( WTE_MAYA_PLUGIN_DIR ) ? '✅ Yes' : '❌ No' ) . '<br>';
} else {
    echo '❌ WTE_MAYA_PLUGIN_DIR not defined<br>';
}

// Check active plugins
echo '<h2>8. Active Plugins</h2>';
$active_plugins = get_option( 'active_plugins' );
echo '<ul>';
foreach ( $active_plugins as $plugin ) {
    echo '<li>' . esc_html( $plugin );
    if ( strpos( $plugin, 'maya' ) !== false || strpos( $plugin, 'wte' ) !== false ) {
        echo ' ⭐';
    }
    echo '</li>';
}
echo '</ul>';
