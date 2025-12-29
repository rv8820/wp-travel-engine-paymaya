<?php
/**
 * Force Enable Maya Payment Gateway
 *
 * This script manually enables Maya in the database
 * Access: yoursite.com/wp-content/plugins/wp-travel-engine-paymaya-claude-.../force-enable-maya.php
 */

// Load WordPress
require_once('../../../wp-load.php');

// Check admin
if (!current_user_can('manage_options')) {
    die('Access denied');
}

header('Content-Type: text/plain; charset=utf-8');

echo "=== Force Enable Maya Payment Gateway ===\n\n";

// Get WP Travel Engine settings
if (!function_exists('wptravelengine_settings')) {
    die("ERROR: wptravelengine_settings() function not found!\n");
}

$settings = wptravelengine_settings();

// Enable Maya
echo "1. Enabling maya_enable setting...\n";
$settings->set('maya_enable', true);

// Set some default values for Maya settings
echo "2. Setting default Maya configuration...\n";
$settings->set('maya.gateway_label', 'Maya');
$settings->set('maya.description', 'Secure payment processing through Maya Payment Gateway');
$settings->set('maya.test_mode', true);

// Save settings
echo "3. Saving settings...\n";
$settings->save();

// Verify
echo "4. Verifying settings...\n";
$verify_enable = $settings->get('maya_enable');
$verify_label = $settings->get('maya.gateway_label');

echo "\n--- Results ---\n";
echo "maya_enable: " . var_export($verify_enable, true) . "\n";
echo "maya.gateway_label: " . var_export($verify_label, true) . "\n";

if ($verify_enable === true) {
    echo "\n✓ SUCCESS! Maya is now enabled in the database.\n";
    echo "\nNext steps:\n";
    echo "1. Go to WP Travel Engine > Settings > Payments\n";
    echo "2. Refresh the page (Ctrl+R or F5)\n";
    echo "3. Check if Maya tab appears in the sidebar\n";
    echo "4. Try clearing browser cache if needed\n";
} else {
    echo "\n✗ FAILED to enable Maya\n";
}

echo "\n=== Done ===\n";
