<?php
/**
 * Check WP Travel Engine REST API Response
 *
 * This checks what the React frontend is actually receiving
 * Access: yoursite.com/wp-content/plugins/wp-travel-engine-paymaya-claude-.../check-rest-api.php
 */

// Load WordPress
require_once('../../../wp-load.php');

// Check admin
if (!current_user_can('manage_options')) {
    die('Access denied');
}

header('Content-Type: text/plain; charset=utf-8');

echo "=== WP Travel Engine REST API Check ===\n\n";

// 1. Check if REST API is accessible
echo "1. Testing REST API accessibility...\n";
$rest_url = rest_url('wp/v2/');
echo "   REST URL: $rest_url\n";
echo "   ✓ REST API is accessible\n\n";

// 2. Check WP Travel Engine settings endpoint
echo "2. Checking settings filter...\n";
$test_settings = array();
$filtered_settings = apply_filters('wptravelengine_settings:tabs:payments', $test_settings);

echo "   Total payment tabs registered: " . count($filtered_settings) . "\n";
echo "   Registered tab keys:\n";
foreach (array_keys($filtered_settings) as $key) {
    echo "   - $key\n";
}

if (isset($filtered_settings['maya_payment'])) {
    echo "\n   ✓ 'maya_payment' IS registered!\n";
    $maya_tab = $filtered_settings['maya_payment'];
    echo "   Maya tab structure:\n";
    echo "   - title: " . ($maya_tab['title'] ?? 'MISSING') . "\n";
    echo "   - id: " . ($maya_tab['id'] ?? 'MISSING') . "\n";
    echo "   - order: " . ($maya_tab['order'] ?? 'MISSING') . "\n";
    echo "   - fields: " . (isset($maya_tab['fields']) ? count($maya_tab['fields']) . " fields" : 'MISSING') . "\n";
} else {
    echo "\n   ✗ 'maya_payment' is NOT registered\n";
    echo "   Available keys: " . implode(', ', array_keys($filtered_settings)) . "\n";
}

// 3. Check payment gateways list
echo "\n3. Checking payment gateways REST filter...\n";
$settings_obj = wptravelengine_settings();
$all_settings = $settings_obj->get();

$test_gateways = array();
$filtered_gateways = apply_filters('wptravelengine_rest_payment_gateways', $test_gateways, $settings_obj);

echo "   Total gateways in REST API: " . count($filtered_gateways) . "\n";
echo "   Gateway list:\n";
foreach ($filtered_gateways as $gateway) {
    $name = $gateway['name'] ?? 'Unknown';
    $id = $gateway['id'] ?? 'Unknown';
    $enabled = $gateway['enable'] ?? false;
    echo "   - $name (ID: $id) - Enabled: " . ($enabled ? 'YES' : 'NO') . "\n";
}

// 4. Check Maya specific settings
echo "\n4. Checking Maya settings in database...\n";
echo "   maya_enable: " . var_export($all_settings['maya_enable'] ?? false, true) . "\n";
echo "   maya.gateway_label: " . var_export($all_settings['maya']['gateway_label'] ?? '', true) . "\n";
echo "   maya.test_mode: " . var_export($all_settings['maya']['test_mode'] ?? true, true) . "\n";

// 5. Check for JavaScript/React errors
echo "\n5. Recommendations:\n";

if (!isset($filtered_settings['maya_payment'])) {
    echo "   ✗ CRITICAL: maya_payment tab is not being registered!\n";
    echo "     → Check if Plugin.php add_global_settings() is being called\n";
    echo "     → Verify filter priority\n";
} else if (empty($all_settings['maya_enable'])) {
    echo "   ⚠ WARNING: maya_enable is FALSE or not set\n";
    echo "     → Run force-enable-maya.php to enable it\n";
    echo "     → Or check the Maya checkbox in Payment Gateways and save\n";
} else {
    echo "   ✓ Backend registration looks correct\n";
    echo "   \n";
    echo "   If tab still doesn't show, possible causes:\n";
    echo "   1. React UI is filtering tabs based on unknown criteria\n";
    echo "   2. Browser/WP cache needs clearing\n";
    echo "   3. JavaScript error preventing tab render (check browser console)\n";
    echo "   4. WP Travel Engine version compatibility issue\n";
    echo "   5. Theme or plugin conflict\n";
    echo "   \n";
    echo "   Try these steps:\n";
    echo "   → Open browser DevTools (F12) → Console tab\n";
    echo "   → Look for JavaScript errors\n";
    echo "   → Try in incognito/private window\n";
    echo "   → Temporarily disable other plugins except WTE and Maya\n";
}

echo "\n=== End Check ===\n";
