<?php
/**
 * Debug Settings Registration
 *
 * Upload this file to your plugin directory and access it via:
 * yoursite.com/wp-content/plugins/wp-travel-engine-paymaya-claude-.../debug-settings-registration.php
 */

// Load WordPress
require_once('../../../wp-load.php');

// Check if user is admin
if (!current_user_can('manage_options')) {
    die('Access denied');
}

header('Content-Type: text/plain; charset=utf-8');

echo "=== WP Travel Engine Maya Payment - Settings Registration Debug ===\n\n";

// 1. Check if WP Travel Engine is active
echo "1. WP Travel Engine Status:\n";
if (defined('WP_TRAVEL_ENGINE_VERSION')) {
    echo "   ✓ WP Travel Engine is active (v" . WP_TRAVEL_ENGINE_VERSION . ")\n\n";
} else {
    echo "   ✗ WP Travel Engine is NOT active\n\n";
}

// 2. Check if Maya plugin is loaded
echo "2. Maya Plugin Status:\n";
if (class_exists('WPTravelEngineMaya\Plugin')) {
    echo "   ✓ Maya Plugin class exists\n";
    $maya_instance = WPTravelEngineMaya\Plugin::instance();
    echo "   ✓ Maya Plugin instance created\n\n";
} else {
    echo "   ✗ Maya Plugin class does NOT exist\n\n";
}

// 3. Check registered filters
echo "3. Registered Filters:\n";
global $wp_filter;

if (isset($wp_filter['wptravelengine_settings:tabs:payments'])) {
    echo "   ✓ Filter 'wptravelengine_settings:tabs:payments' is registered\n";
    echo "   Callbacks:\n";
    foreach ($wp_filter['wptravelengine_settings:tabs:payments']->callbacks as $priority => $callbacks) {
        foreach ($callbacks as $callback) {
            $callback_name = '';
            if (is_array($callback['function'])) {
                if (is_object($callback['function'][0])) {
                    $callback_name = get_class($callback['function'][0]) . '::' . $callback['function'][1];
                } else {
                    $callback_name = $callback['function'][0] . '::' . $callback['function'][1];
                }
            } else {
                $callback_name = $callback['function'];
            }
            echo "   - Priority $priority: $callback_name\n";
        }
    }
    echo "\n";
} else {
    echo "   ✗ Filter 'wptravelengine_settings:tabs:payments' is NOT registered\n\n";
}

// 4. Apply the filter and check result
echo "4. Apply Settings Filter:\n";
$test_settings = array();
$result = apply_filters('wptravelengine_settings:tabs:payments', $test_settings);

if (isset($result['maya'])) {
    echo "   ✓ Maya settings found in filter result!\n";
    echo "   Settings array keys:\n";
    foreach ($result['maya'] as $key => $value) {
        if (is_array($value)) {
            echo "   - '$key' => [array with " . count($value) . " items]\n";
        } else {
            echo "   - '$key' => " . var_export($value, true) . "\n";
        }
    }
    echo "\n";
} else {
    echo "   ✗ Maya settings NOT found in filter result\n";
    echo "   Available keys: " . implode(', ', array_keys($result)) . "\n\n";
}

// 5. Check if global-settings.php file exists
echo "5. File System Check:\n";
$global_settings_file = WPTRAVELENGINE_MAYA_ABSPATH . 'includes/Builders/global-settings.php';
if (file_exists($global_settings_file)) {
    echo "   ✓ global-settings.php exists at:\n";
    echo "     $global_settings_file\n";

    // Try to include it
    $settings_array = require $global_settings_file;
    if (is_array($settings_array)) {
        echo "   ✓ global-settings.php returns an array\n";
        echo "   Required keys check:\n";
        $required_keys = array('title', 'order', 'id', 'fields');
        foreach ($required_keys as $key) {
            if (isset($settings_array[$key])) {
                echo "   ✓ '$key' => " . (is_array($settings_array[$key]) ? '[array]' : var_export($settings_array[$key], true)) . "\n";
            } else {
                echo "   ✗ '$key' => MISSING!\n";
            }
        }
        echo "\n";
    } else {
        echo "   ✗ global-settings.php does NOT return an array\n\n";
    }
} else {
    echo "   ✗ global-settings.php does NOT exist\n\n";
}

// 6. Check payment gateway registration
echo "6. Payment Gateway Registration:\n";
if (isset($wp_filter['wptravelengine_registering_payment_gateways'])) {
    echo "   ✓ Gateway registration filter exists\n\n";
} else {
    echo "   ✗ Gateway registration filter NOT found\n\n";
}

// 7. Get WP Travel Engine settings
echo "7. WP Travel Engine Settings Object:\n";
if (function_exists('wptravelengine_settings')) {
    $wte_settings = wptravelengine_settings();
    echo "   ✓ wptravelengine_settings() function exists\n";

    $all_settings = $wte_settings->get();
    if (isset($all_settings['maya'])) {
        echo "   ✓ 'maya' key found in settings!\n";
        echo "   Maya settings:\n";
        foreach ($all_settings['maya'] as $key => $value) {
            echo "   - $key => " . var_export($value, true) . "\n";
        }
    } else {
        echo "   ✗ 'maya' key NOT found in settings\n";
        echo "   Available top-level keys: " . implode(', ', array_keys($all_settings)) . "\n";
    }
} else {
    echo "   ✗ wptravelengine_settings() function does NOT exist\n";
}

echo "\n=== End Debug ===\n";
