# WP Travel Engine - Custom Payment Gateway Integration Guide

A comprehensive guide for developers to integrate custom payment gateways with WP Travel Engine.

---

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Version Compatibility Notice](#version-compatibility-notice)
3. [Quick Start](#quick-start)
4. [File Structure](#file-structure)
5. [Bootstrap File](#bootstrap-file)
6. [Main Plugin Class](#main-plugin-class)
7. [Payment Gateway Class](#payment-gateway-class)
8. [Payment Processing Flow](#payment-processing-flow)
9. [Callback URL Handling](#callback-url-handling)
10. [Settings Integration](#settings-integration)
11. [Icons and Logos](#icons-and-logos)
12. [Complete Code Examples](#complete-code-examples)
13. [Hooks Reference](#hooks-reference)
14. [Testing Your Integration](#testing-your-integration)
15. [Best Practices](#best-practices)

---

## Prerequisites

- WP Travel Engine plugin installed and activated (version 6.0+)
  - **Recommended:** v6.7.0 or higher for simplified implementation
  - Legacy versions (v6.0 - v6.6.x) are also supported
- PHP 7.4 or higher
- Understanding of WordPress plugin development
- Familiarity with your payment gateway's API
- Composer for autoloading (recommended)

---

## Version Compatibility Notice

> **Important:** This documentation supports both WP Travel Engine **v6.7.0 and above**, as well as **older versions** (v6.0 - v6.6.x).

### Understanding Version Differences

WP Travel Engine v6.7.0 introduced significant changes to how cart and booking data are handled:

- **WP Travel Engine v6.7.0+**: Uses modern cart architecture (Cart v4+) with streamlined payment processing
- **WP Travel Engine < v6.7.0**: Uses legacy cart system (Cart v3) with different data structures

### For Modern Implementations (v6.7.0+ Only)

If you are building a payment gateway **exclusively for WP Travel Engine v6.7.0 or above**, you can **safely ignore** the following legacy methods in this documentation:

- ❌ `process_payment()` - Legacy payment processing method
- ❌ `before_cart_v4_version()` - Pre-v6.7.0 payment handling
- ❌ Cart v3 specific logic and data structures

Instead, you only need to implement:

- ✅ `process_payment_v2()` - Modern payment processing method
- ✅ `after_cart_v4_version()` - Modern payment status handling

### For Backward Compatible Implementations

If you want to support **both old and new versions** of WP Travel Engine, you must implement **both sets of methods**:

- ✅ `process_payment()` + `process_payment_v2()`
- ✅ `before_cart_v4_version()` + `after_cart_v4_version()`

The gateway will automatically use the appropriate method based on the WP Travel Engine version.

### How to Check Version in Code

```php
// Check if WP Travel Engine supports Cart v4
if ( version_compare( WP_TRAVEL_ENGINE_CART_VERSION ?? '0', '4.0', '>=' ) ) {
    // Use modern methods
} else {
    // Use legacy methods
}
```

### Recommendation

For **new integrations**, we recommend:
- Target WP Travel Engine v6.7.0+ only
- Implement only `process_payment_v2()` and `after_cart_v4_version()`
- Simplify your codebase by avoiding legacy support

For **existing integrations**, maintain backward compatibility by implementing both method sets.

### Quick Reference

| WP Travel Engine Version | Required Methods | Optional Methods |
|-------------------------|------------------|------------------|
| **v6.7.0+ only** | `process_payment_v2()`<br>`after_cart_v4_version()` | None |
| **v6.0 - v6.6.x only** | `process_payment()`<br>`before_cart_v4_version()` | None |
| **Both (backward compatible)** | `process_payment()`<br>`process_payment_v2()`<br>`before_cart_v4_version()`<br>`after_cart_v4_version()` | None |

---

## Quick Start

### Minimum Requirements Checklist

To integrate a custom payment gateway, you need:

- ✅ Main bootstrap PHP file
- ✅ Plugin class (singleton pattern)
- ✅ Payment gateway class extending `BaseGateway`
- ✅ Settings configuration
- ✅ Two icon files (admin + checkout)
- ✅ Callback handlers (success, cancel, notification)
- ✅ Payment API client class (optional but recommended)

---

## File Structure

```
wptravelengine-yourgateway-payment/
├── wptravelengine-yourgateway-payment.php    # Bootstrap file
├── composer.json                              # Autoloader configuration
├── includes/
│   ├── Plugin.php                             # Main plugin class
│   ├── Payment.php                            # Gateway class
│   ├── YourGatewayApi.php                     # API client (optional)
│   └── Builders/
│       ├── API.php                            # REST API integration
│       └── global-settings.php                # Settings configuration
├── src/
│   └── images/
│       ├── yourgateway.png                    # Admin backend icon
│       └── yourgateway-checkout.png           # Checkout display icon
├── languages/                                  # Translation files
│   └── wptravelengine-yourgateway-payment.pot
└── README.md                                   # Documentation
```

---

## Bootstrap File

The main plugin file initializes your payment gateway.

### File: `wptravelengine-yourgateway-payment.php`

```php
<?php
/**
 * Plugin Name: WP Travel Engine - Your Gateway Payment
 * Plugin URI: https://wptravelengine.com/
 * Description: Payment gateway integration for WP Travel Engine
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com/
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: wptravelengine-yourgateway-payment
 * Domain Path: /languages
 * Requires at least: 5.9
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'WPTRAVELENGINE_YOURGATEWAY_VERSION', '1.0.0' );
define( 'WPTRAVELENGINE_YOURGATEWAY_FILE_PATH', __FILE__ );
define( 'WPTRAVELENGINE_YOURGATEWAY_ABSPATH', dirname( WPTRAVELENGINE_YOURGATEWAY_FILE_PATH ) . '/' );
define( 'WPTRAVELENGINE_YOURGATEWAY_BASE_PATH', plugin_basename( WPTRAVELENGINE_YOURGATEWAY_FILE_PATH ) );
define( 'WPTRAVELENGINE_YOURGATEWAY_BASE_NAME', dirname( plugin_basename( WPTRAVELENGINE_YOURGATEWAY_FILE_PATH ) ) );

/**
 * Check if WP Travel Engine is active
 */
function wptravelengine_yourgateway_check_compatibility() {
    if ( ! defined( 'WP_TRAVEL_ENGINE_VERSION' ) ) {
        add_action( 'admin_notices', 'wptravelengine_yourgateway_admin_notice' );
        return false;
    }
    return true;
}

/**
 * Display admin notice if WP Travel Engine is not active
 */
function wptravelengine_yourgateway_admin_notice() {
    ?>
    <div class="notice notice-error">
        <p>
            <?php
            echo wp_kses_post(
                sprintf(
                    __( '<strong>WP Travel Engine - Your Gateway Payment</strong> requires <strong>WP Travel Engine</strong> plugin to be installed and activated. Please install and activate <a href="%s" target="_blank">WP Travel Engine</a>.', 'wptravelengine-yourgateway-payment' ),
                    'https://wordpress.org/plugins/wp-travel-engine/'
                )
            );
            ?>
        </p>
    </div>
    <?php
}

/**
 * Initialize the plugin
 */
function wptravelengine_yourgateway_payment_init() {
    // Check compatibility
    if ( ! wptravelengine_yourgateway_check_compatibility() ) {
        return;
    }

    // Load Composer autoloader
    require_once WPTRAVELENGINE_YOURGATEWAY_ABSPATH . 'vendor/autoload.php';

    // Initialize plugin
    WPTravelEngineYourGateway\Plugin::instance();
}
add_action( 'plugins_loaded', 'wptravelengine_yourgateway_payment_init', 9 );
```

### File: `composer.json`

```json
{
    "name": "yourcompany/wptravelengine-yourgateway-payment",
    "description": "Your Gateway payment integration for WP Travel Engine",
    "type": "wordpress-plugin",
    "require": {
        "php": ">=7.4"
    },
    "autoload": {
        "psr-4": {
            "WPTravelEngineYourGateway\\": "includes/"
        }
    }
}
```

**Run:** `composer dump-autoload` after creating composer.json

---

## Main Plugin Class

The Plugin class is a singleton that manages hooks and initialization.

### File: `includes/Plugin.php`

```php
<?php
namespace WPTravelEngineYourGateway;

use WPTravelEngineYourGateway\Builders\API;

/**
 * Main Plugin Class
 */
class Plugin {
    /**
     * Singleton instance
     */
    protected static $instance = null;

    /**
     * Get singleton instance
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
        $this->init_api();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Load text domain for translations
        add_action( 'init', array( $this, 'load_textdomain' ) );

        // Add settings tab to WP Travel Engine
        add_filter( 'wptravelengine_settings:tabs:payments', array( $this, 'add_global_settings' ) );

        // Register payment gateway
        add_action( 'wptravelengine_registering_payment_gateways', array( $this, 'register_payment_gateway' ) );

        // Add gateway to REST API list
        add_filter( 'wptravelengine_rest_payment_gateways', array( $this, 'yourgateway_gateway_list' ), 10, 2 );

        // Customize checkout button (optional)
        add_filter( 'wptravelengine_checkout_yourgateway_enable_button', array( $this, 'print_checkout_button' ), 10, 2 );
    }

    /**
     * Initialize REST API integration
     */
    private function init_api() {
        new API();
    }

    /**
     * Load plugin text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'wptravelengine-yourgateway-payment',
            false,
            WPTRAVELENGINE_YOURGATEWAY_BASE_NAME . '/languages'
        );
    }

    /**
     * Add global settings tab
     */
    public function add_global_settings( $settings ) {
        $settings['yourgateway_payment'] = require_once WPTRAVELENGINE_YOURGATEWAY_ABSPATH . 'includes/Builders/global-settings.php';
        return $settings;
    }

    /**
     * Register payment gateway with WP Travel Engine
     */
    public function register_payment_gateway( $gateways ) {
        $gateways['yourgateway'] = new Payment();
        return $gateways;
    }

    /**
     * Add gateway to REST API payment gateways list
     */
    public function yourgateway_gateway_list( $gateways, $plugin_settings ) {
        $gateways[] = array(
            'id'     => 'yourgateway_enable',
            'name'   => __( 'Your Gateway', 'wptravelengine-yourgateway-payment' ),
            'enable' => wptravelengine_toggled( $plugin_settings->get( 'yourgateway_enable' ) ),
            'icon'   => plugin_dir_url( WPTRAVELENGINE_YOURGATEWAY_FILE_PATH ) . 'src/images/yourgateway.png',
        );
        return $gateways;
    }

    /**
     * Customize checkout button text (optional)
     */
    public function print_checkout_button( $label, $payment_gateway ) {
        return __( 'Proceed to Payment', 'wptravelengine-yourgateway-payment' );
    }
}
```

---

## Payment Gateway Class

This is the core class that handles all payment operations.

### File: `includes/Payment.php`

```php
<?php
namespace WPTravelEngineYourGateway;

use WPTravelEngine\PaymentGateways\BaseGateway;
use WPTravelEngine\Core\Models\Post\Booking;
use WPTravelEngine\Core\Models\Post\Payment as PaymentModel;
use WPTravelEngine\Core\Booking\BookingProcess;

/**
 * Payment Gateway Class
 */
class Payment extends BaseGateway {
    /**
     * Gateway ID - must match the key used in register_payment_gateway()
     */
    protected $gateway_id = 'yourgateway_enable';

    /**
     * Cart version requirement
     */
    public static string $cart_version = '4.0';

    /**
     * Plugin settings
     */
    protected $settings;

    /**
     * Current payment instance
     */
    protected $payment;

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
        $this->settings = wptravelengine_settings()->get();
    }

    /**
     * Get gateway ID
     *
     * @return string Gateway identifier
     */
    public function get_gateway_id(): string {
        return $this->gateway_id;
    }

    /**
     * Get gateway label (shown in admin)
     *
     * @return string Gateway label
     */
    public function get_label(): string {
        return ! empty( $this->settings['yourgateway']['gateway_label'] )
            ? $this->settings['yourgateway']['gateway_label']
            : __( 'Your Gateway', 'wptravelengine-yourgateway-payment' );
    }

    /**
     * Get public label (shown to customers)
     *
     * @return string Public label
     */
    public function get_public_label(): string {
        return $this->get_label();
    }

    /**
     * Get gateway description (optional)
     *
     * @return string Description
     */
    public function get_description(): string {
        return __( 'Pay securely using Your Gateway payment system.', 'wptravelengine-yourgateway-payment' );
    }

    /**
     * Get admin icon URL
     *
     * @return string Icon URL
     */
    public function get_icon(): string {
        return plugin_dir_url( WPTRAVELENGINE_YOURGATEWAY_FILE_PATH ) . 'src/images/yourgateway.png';
    }

    /**
     * Get checkout display icon URL
     *
     * @return string Icon URL
     */
    public function get_display_icon(): string {
        return plugin_dir_url( WPTRAVELENGINE_YOURGATEWAY_FILE_PATH ) . 'src/images/yourgateway-checkout.png';
    }

    /**
     * Check if gateway supports currency
     *
     * @param string $currency Currency code
     * @return bool
     */
    public function is_supports_currency( string $currency ): bool {
        $supported_currencies = array( 'USD', 'EUR', 'GBP' ); // Add your supported currencies
        return in_array( $currency, $supported_currencies, true );
    }

    /**
     * Check if in test mode
     *
     * @return bool
     */
    public function is_test_mode(): bool {
        return wptravelengine_toggled( $this->settings['yourgateway']['test_mode'] ?? false );
    }

    // ===================================================================
    // PAYMENT PROCESSING METHODS
    // ===================================================================
    // NOTE: If targeting WP Travel Engine v6.7.0+ only, you can skip
    // process_payment() and only implement process_payment_v2()
    // ===================================================================

    /**
     * Process payment - Legacy method (WP Travel Engine < v6.7.0)
     *
     * ⚠️ OPTIONAL: Skip this method if targeting WP Travel Engine v6.7.0+ only
     *
     * @param Booking $booking Booking instance
     * @param PaymentModel $payment Payment instance
     * @param BookingProcess $booking_process Booking process instance
     */
    public function process_payment( Booking $booking, PaymentModel $payment, BookingProcess $booking_process ): void {
        global $wte_cart;

		$this->booking = $booking;
		$this->payment = $payment;

		if ( 'partial' === $wte_cart->get_payment_type() ) {
			$payable_amount = $wte_cart->get_totals()['partial_total'];
		} elseif ( 'due' === $wte_cart->get_payment_type() ) {
			$payable_amount = round( $wte_cart->get_totals()['total'], 2 ) - round( $wte_cart->get_totals()['partial_total'], 2 );
		} else {
			$payable_amount = $wte_cart->get_totals()['total'];
		}

        $this->_process( $booking, $payment, $payable_amount );
    }

    /**
     * Process payment - Modern method (WP Travel Engine v6.7.0+)
     *
     * ✅ REQUIRED: This method is required for WP Travel Engine v6.7.0+
     *
     * @param Booking $booking Booking instance
     * @param PaymentModel $payment Payment instance
     * @param BookingProcess $booking_process Booking process instance
     */
    public function process_payment_v2( Booking $booking, PaymentModel $payment, BookingProcess $booking_process ): void {
        $this->payment = $payment;

        // Get total from cart
        $cart = $payment->get_meta( 'cart_info' );
        $payable_amount = (float) ( $cart['totals']['total'] ?? 0 );

        $this->_process( $booking, $payment, $payable_amount );
    }

    /**
     * Internal payment processing logic
     *
     * @param Booking $booking Booking instance
     * @param PaymentModel $payment Payment instance
     * @param float $amount Amount to charge
     */
    protected function _process( Booking $booking, PaymentModel $payment, float $amount ): void {
        // Generate unique order ID
        $order_id = $this->generate_order_id( $booking->get_id() );

        // Get billing information
        $billing_info = $payment->get_meta( 'billing_info' );

        // Prepare payment data
        $payment_data = array(
            'order_id'    => $order_id,
            'amount'      => $amount,
            'currency'    => wptravelengine_settings()->get( 'currency_code', 'USD' ),
            'description' => sprintf( __( 'Booking #%s', 'wptravelengine-yourgateway-payment' ), $booking->get_id() ),

            // Customer information
            'customer'    => array(
                'email'      => $billing_info['email'] ?? '',
                'first_name' => $billing_info['fname'] ?? '',
                'last_name'  => $billing_info['lname'] ?? '',
                'phone'      => $billing_info['phone'] ?? '',
                'address'    => $billing_info['address'] ?? '',
                'city'       => $billing_info['city'] ?? '',
                'country'    => $billing_info['country'] ?? '',
            ),

            // Callback URLs
            'success_url'      => $this->get_callback_url( $payment, 'success' ),
            'cancel_url'       => $this->get_callback_url( $payment, 'cancel' ),
            'notification_url' => $this->get_callback_url( $payment, 'notification' ),
        );

        // Create payment with your gateway API
        $api = new YourGatewayApi( $this->settings['yourgateway'] );
        $payment_response = $api->create_payment( $payment_data );

        if ( ! $payment_response || isset( $payment_response['error'] ) ) {
            wp_die( __( 'Payment initialization failed. Please try again.', 'wptravelengine-yourgateway-payment' ) );
        }

        // Redirect to payment page or do as required if want to send json and validate frontend, display form as required.
        if ( isset( $payment_response['redirect_url'] ) ) {
            // Direct redirect
            wp_redirect( $payment_response['redirect_url'] );
            exit;
        } elseif ( isset( $payment_response['form_fields'] ) ) {
            // Auto-submit form
            $this->display_payment_form( $payment_response['form_fields'], $payment_response['action_url'] );
        }
    }

    /**
     * Generate unique order ID
     *
     * @param int $booking_id Booking ID
     * @return string Order ID
     */
    protected function generate_order_id( int $booking_id ): string {
        return sprintf( 'WPTRAVELENGINE_%d_%d', $booking_id, time() );
    }

    /**
     * Display auto-submit payment form
     *
     * @param array $fields Form fields
     * @param string $action_url Form action URL
     */
    protected function display_payment_form( array $fields, string $action_url ): void {
        echo '<html><body>';
        echo '<form id="payment-form" method="post" action="' . esc_url( $action_url ) . '">';

        foreach ( $fields as $name => $value ) {
            echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
        }

        echo '</form>';
        echo '<script>document.getElementById("payment-form").submit();</script>';
        echo '</body></html>';
        exit;
    }

    // ===================================================================
    // CALLBACK HANDLERS
    // ===================================================================

    /**
     * Handle successful payment callback
     *
     * @param Booking $booking Booking instance
     * @param PaymentModel $payment Payment instance
     */
    public function handle_success_request( Booking $booking, PaymentModel $payment ): void {
        // Get payment key from request
        $payment_key = sanitize_text_field( $_REQUEST['payment_key'] ?? '' );

        // Redirect to thank you page
        wp_redirect( add_query_arg( 'payment_key', $payment_key, home_url( '/thank-you' ) ) );
        exit;
    }

    /**
     * Handle cancelled payment callback
     *
     * @param Booking $booking Booking instance
     * @param PaymentModel $payment Payment instance
     */
    public function handle_cancel_request( Booking $booking, PaymentModel $payment ): void {
        // Update payment status
        $payment->set_status( 'canceled' );

        // Clean up payment transient
        $payment_key = sanitize_text_field( $_REQUEST['payment_key'] ?? '' );
        if ( $payment_key ) {
            delete_transient( "payment_key_{$payment_key}" );
        }

        // Redirect to checkout with error message
        wp_redirect( add_query_arg( 'payment_error', '1', home_url( '/checkout' ) ) );
        exit;
    }

    /**
     * Handle IPN/webhook notification callback
     *
     * This is called asynchronously by the payment gateway
     *
     * @param Booking $booking Booking instance
     * @param PaymentModel $payment Payment instance
     */
    public function handle_notification_request( Booking $booking, PaymentModel $payment ): void {
        // Get IPN data
        $ipn_data = $_POST;

        // Validate required fields
        if ( empty( $ipn_data['transaction_id'] ) || empty( $ipn_data['status'] ) ) {
            status_header( 400 );
            exit( 'Invalid IPN data' );
        }

        // Verify signature/authenticity
        $api = new YourGatewayApi( $this->settings['yourgateway'] );
        if ( ! $api->verify_signature( $ipn_data ) ) {
            status_header( 403 );
            exit( 'Invalid signature' );
        }

        // Check if already processed
        $processed_key = 'ipn_processed_' . $ipn_data['transaction_id'];
        if ( get_transient( $processed_key ) ) {
            status_header( 200 );
            exit( 'Already processed' );
        }

        // Determine payment status
        $transaction_status = $ipn_data['status'];
        $is_successful = $this->is_successful_payment( $transaction_status );
        $is_pending = $this->is_pending_payment( $transaction_status );

        // Update payment based on Cart version
        if ( version_compare( WP_TRAVEL_ENGINE_CART_VERSION ?? '0', self::$cart_version, '<' ) ) {
            // Pre-Cart v4
            $this->before_cart_v4_version( $payment, $is_successful, $is_pending );
        } else {
            // Cart v4+
            $this->after_cart_v4_version( $payment, $is_successful, $is_pending );
        }

        // Mark as processed
        set_transient( $processed_key, true, HOUR_IN_SECONDS );

        // Send booking confirmation emails
        do_action( 'wptravelengine_after_booking_process_completed', $booking->get_id() );

        // Return success response
        status_header( 200 );
        exit( 'OK' );
    }

    /**
     * Process payment status for WP Travel Engine < v6.7.0
     *
     * ⚠️ OPTIONAL: Skip this method if targeting WP Travel Engine v6.7.0+ only
     *
     * @param PaymentModel $payment Payment instance
     * @param bool $is_successful Is payment successful
     * @param bool $is_pending Is payment pending
     */
    protected function before_cart_v4_version( PaymentModel $payment, bool $is_successful, bool $is_pending ): void {
        if ( $is_successful ) {
            $payment->set_status( 'completed' );
            $payment->set_meta( 'payment_status', 'completed' );
        } elseif ( $is_pending ) {
            $payment->set_status( 'pending' );
            $payment->set_meta( 'payment_status', 'pending' );
        } else {
            $payment->set_status( 'failed' );
            $payment->set_meta( 'payment_status', 'failed' );
        }
    }

    /**
     * Process payment status for WP Travel Engine v6.7.0+
     *
     * ✅ REQUIRED: This method is required for WP Travel Engine v6.7.0+
     *
     * @param PaymentModel $payment Payment instance
     * @param bool $is_successful Is payment successful
     * @param bool $is_pending Is payment pending
     */
    protected function after_cart_v4_version( PaymentModel $payment, bool $is_successful, bool $is_pending ): void {
        if ( $is_successful ) {
            $payment->set_status( 'publish' );
            $payment->set_meta( 'payment_status', 'completed' );
        } elseif ( $is_pending ) {
            $payment->set_status( 'pending' );
            $payment->set_meta( 'payment_status', 'pending' );
        } else {
            $payment->set_status( 'failed' );
            $payment->set_meta( 'payment_status', 'failed' );
        }
    }

    /**
     * Check if transaction status indicates successful payment
     *
     * @param string $status Transaction status
     * @return bool
     */
    protected function is_successful_payment( string $status ): bool {
        $successful_statuses = array( 'completed', 'authorized', 'captured', 'success' );
        return in_array( strtolower( $status ), $successful_statuses, true );
    }

    /**
     * Check if transaction status indicates pending payment
     *
     * @param string $status Transaction status
     * @return bool
     */
    protected function is_pending_payment( string $status ): bool {
        $pending_statuses = array( 'pending', 'processing', 'on-hold' );
        return in_array( strtolower( $status ), $pending_statuses, true );
    }
}
```

---

## Payment Processing Flow

### Flow Diagram

```
User Checkout
     |
     v
process_payment() or process_payment_v2()
     |
     v
_process()
     |
     +-- Generate Order ID
     +-- Get Billing Info
     +-- Prepare Payment Data
     +-- Call Gateway API
     +-- Redirect/Display Form
     |
     v
User at Payment Gateway
     |
     +-- Success --> handle_success_request() --> Thank You Page
     +-- Cancel  --> handle_cancel_request()  --> Checkout Page
     +-- IPN     --> handle_notification_request() --> Update Payment Status
```

### Callback URL System

WP Travel Engine automatically routes callbacks to your handlers:

```
URL: /?payment_key={key}&callback_type=success
  → Calls: handle_success_request()

URL: /?payment_key={key}&callback_type=cancel
  → Calls: handle_cancel_request()

URL: /?payment_key={key}&callback_type=notification
  → Calls: handle_notification_request()
```

---

## Callback URL Handling

### Success URL

Handle successful payment returns from the gateway.

```php
public function handle_success_request( Booking $booking, PaymentModel $payment ): void {
    $payment_key = sanitize_text_field( $_REQUEST['payment_key'] ?? '' );

    // Optional: Verify payment with gateway before redirecting
    // $api = new YourGatewayApi( $this->settings['yourgateway'] );
    // $is_valid = $api->verify_payment( $_GET['transaction_id'] );

    // Redirect to thank you page
    wp_redirect( add_query_arg( 'payment_key', $payment_key, home_url( '/thank-you' ) ) );
    exit;
}
```

### Cancel/Failed URL

Handle cancelled or failed payment attempts.

```php
public function handle_cancel_request( Booking $booking, PaymentModel $payment ): void {
    // Update payment status to canceled
    $payment->set_status( 'canceled' );

    // Clean up transient
    $payment_key = sanitize_text_field( $_REQUEST['payment_key'] ?? '' );
    if ( $payment_key ) {
        delete_transient( "payment_key_{$payment_key}" );
    }

    // Optional: Add error message
    wptravelengine_add_notice( __( 'Payment was cancelled.', 'wptravelengine-yourgateway-payment' ), 'error' );

    // Redirect back to checkout
    wp_redirect( add_query_arg( 'payment_error', '1', home_url( '/checkout' ) ) );
    exit;
}
```

### Notification URL (IPN/Webhook)

Handle asynchronous payment confirmations.

```php
public function handle_notification_request( Booking $booking, PaymentModel $payment ): void {
    // 1. Get IPN data
    $ipn_data = $_POST;

    // 2. Validate required fields
    if ( empty( $ipn_data['transaction_id'] ) ) {
        status_header( 400 );
        exit( 'Missing transaction_id' );
    }

    // 3. Verify signature
    $api = new YourGatewayApi( $this->settings['yourgateway'] );
    if ( ! $api->verify_signature( $ipn_data ) ) {
        status_header( 403 );
        exit( 'Invalid signature' );
    }

    // 4. Prevent duplicate processing
    $processed_key = 'ipn_processed_' . $ipn_data['transaction_id'];
    if ( get_transient( $processed_key ) ) {
        status_header( 200 );
        exit( 'Already processed' );
    }

    // 5. Determine payment status
    $is_successful = $this->is_successful_payment( $ipn_data['status'] );

    // 6. Update payment
    if ( $is_successful ) {
        $payment->set_status( 'publish' );
        $payment->set_meta( 'payment_status', 'completed' );
        $payment->set_meta( 'transaction_id', $ipn_data['transaction_id'] );
    }

    // 7. Mark as processed
    set_transient( $processed_key, true, HOUR_IN_SECONDS );

    // 8. Send emails
    do_action( 'wptravelengine_after_booking_process_completed', $booking->get_id() );

    // 9. Return success
    status_header( 200 );
    exit( 'OK' );
}
```

### Important Notes for IPN Handling

1. **Always verify signatures** - Never trust IPN data without verification
2. **Prevent duplicates** - Use transients or meta flags to track processed IPNs
3. **Return HTTP 200** - Payment gateways retry if they don't receive success response
4. **Be idempotent** - Processing the same IPN multiple times should be safe
5. **Log errors** - Use `error_log()` for debugging IPN issues

---

## Settings Integration

### File: `includes/Builders/global-settings.php`

```php
<?php
/**
 * Global Settings Configuration
 */

// Get notification URL for display
$callback_url = add_query_arg(
    array(
        'payment_key'   => '{payment_key}',
        'callback_type' => 'notification',
    ),
    home_url( '/' )
);

return array(
    'title'  => __( 'Your Gateway', 'wptravelengine-yourgateway-payment' ),
    'order'  => 10,
    'id'     => 'yourgateway_payment',
    'fields' => array(
        // Gateway Label
        array(
            'field_type'  => 'TEXT',
            'name'        => 'yourgateway.gateway_label',
            'label'       => __( 'Gateway Label', 'wptravelengine-yourgateway-payment' ),
            'placeholder' => __( 'Your Gateway', 'wptravelengine-yourgateway-payment' ),
            'default'     => __( 'Your Gateway', 'wptravelengine-yourgateway-payment' ),
        ),

        // IPN URL Instructions
        array(
            'field_type' => 'ALERT',
            'type'       => 'info',
            'name'       => 'yourgateway_ipn_instruction',
            'content'    => sprintf(
                __( 'Configure the following URL as your IPN/Webhook endpoint in Your Gateway dashboard: %s', 'wptravelengine-yourgateway-payment' ),
                '<code>' . esc_html( $callback_url ) . '</code>'
            ),
        ),

        // IPN URL (Copy)
        array(
            'field_type' => 'COPY_CODE',
            'label'      => __( 'Notification URL (IPN)', 'wptravelengine-yourgateway-payment' ),
            'code'       => $callback_url,
        ),

        // API Key
        array(
            'field_type'  => 'TEXT',
            'name'        => 'yourgateway.api_key',
            'label'       => __( 'API Key', 'wptravelengine-yourgateway-payment' ),
            'placeholder' => __( 'Enter your API key', 'wptravelengine-yourgateway-payment' ),
            'help'        => __( 'Found in your gateway dashboard under API settings.', 'wptravelengine-yourgateway-payment' ),
        ),

        // Secret Key
        array(
            'field_type'  => 'TEXT',
            'name'        => 'yourgateway.secret_key',
            'label'       => __( 'Secret Key', 'wptravelengine-yourgateway-payment' ),
            'placeholder' => __( 'Enter your secret key', 'wptravelengine-yourgateway-payment' ),
            'help'        => __( 'Used for signature verification.', 'wptravelengine-yourgateway-payment' ),
        ),

        // Test Mode
        array(
            'field_type' => 'TOGGLE',
            'name'       => 'yourgateway.test_mode',
            'label'      => __( 'Enable Test Mode', 'wptravelengine-yourgateway-payment' ),
            'default'    => true,
        ),

        // Debug Mode
        array(
            'field_type' => 'DEBUG_MODE',
            'name'       => 'debug_mode',
        ),
    ),
);
```

### Available Field Types

- `TEXT` - Text input
- `TEXTAREA` - Multi-line text
- `NUMBER` - Number input
- `SELECT` - Dropdown select
- `TOGGLE` - On/off switch
- `ALERT` - Information box
- `COPY_CODE` - Copyable code snippet
- `DEBUG_MODE` - Debug toggle
- `PASSWORD` - Password input

### File: `includes/Builders/API.php`

```php
<?php
namespace WPTravelEngineYourGateway\Builders;

use WP_REST_Request;

/**
 * REST API Integration
 */
class API {
    /**
     * Plugin settings instance
     */
    protected $plugin_settings;

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_filter( 'wptravelengine_settings_api_schema', array( $this, 'add_settings_schema' ), 10, 2 );
        add_filter( 'wptravelengine_rest_prepare_settings', array( $this, 'prepare_settings' ), 10, 3 );
        add_action( 'wptravelengine_api_update_settings', array( $this, 'update_settings' ), 10, 2 );
    }

    /**
     * Add settings schema for REST API
     */
    public function add_settings_schema( array $schema, $instance ): array {
        $schema['yourgateway'] = array(
            'description' => __( 'Your Gateway Settings', 'wptravelengine-yourgateway-payment' ),
            'type'        => 'object',
            'properties'  => array(
                'gateway_label' => array(
                    'type'        => 'string',
                    'description' => __( 'Gateway label displayed to users', 'wptravelengine-yourgateway-payment' ),
                ),
                'api_key'       => array(
                    'type'        => 'string',
                    'description' => __( 'API key from gateway', 'wptravelengine-yourgateway-payment' ),
                ),
                'secret_key'    => array(
                    'type'        => 'string',
                    'description' => __( 'Secret key for signature verification', 'wptravelengine-yourgateway-payment' ),
                ),
                'test_mode'     => array(
                    'type'        => 'boolean',
                    'description' => __( 'Enable test mode', 'wptravelengine-yourgateway-payment' ),
                    'default'     => true,
                ),
            ),
        );
        return $schema;
    }

    /**
     * Prepare settings for REST response
     */
    public function prepare_settings( $settings, WP_REST_Request $request, $instance ) {
        $this->plugin_settings = $instance->plugin_settings;

        $settings['yourgateway'] = array(
            'gateway_label' => $this->plugin_settings->get( 'yourgateway.gateway_label', '' ),
            'api_key'       => $this->plugin_settings->get( 'yourgateway.api_key', '' ),
            'secret_key'    => $this->plugin_settings->get( 'yourgateway.secret_key', '' ),
            'test_mode'     => $this->plugin_settings->get( 'yourgateway.test_mode', true ),
        );

        return $settings;
    }

    /**
     * Update settings via REST API
     */
    public function update_settings( WP_REST_Request $request, $instance ) {
        $this->plugin_settings = $instance->plugin_settings;

        if ( ! isset( $request['yourgateway'] ) ) {
            return;
        }

        $yourgateway = $request['yourgateway'];

        if ( isset( $yourgateway['gateway_label'] ) ) {
            $this->plugin_settings->set( 'yourgateway.gateway_label', sanitize_text_field( $yourgateway['gateway_label'] ) );
        }

        if ( isset( $yourgateway['api_key'] ) ) {
            $this->plugin_settings->set( 'yourgateway.api_key', sanitize_text_field( $yourgateway['api_key'] ) );
        }

        if ( isset( $yourgateway['secret_key'] ) ) {
            $this->plugin_settings->set( 'yourgateway.secret_key', sanitize_text_field( $yourgateway['secret_key'] ) );
        }

        if ( isset( $yourgateway['test_mode'] ) ) {
            $this->plugin_settings->set( 'yourgateway.test_mode', (bool) $yourgateway['test_mode'] );
        }

        $this->plugin_settings->save();
    }
}
```

---

## Icons and Logos

### Requirements

- **Admin Icon**: 64x64px PNG or SVG
- **Checkout Icon**: 200x60px PNG or SVG (can be wider for logos)
- **Format**: PNG with transparent background or SVG
- **Location**: `src/images/` directory

### File Structure

```
src/images/
├── yourgateway.png           # Admin backend icon (64x64px)
└── yourgateway-checkout.png  # Checkout display icon (200x60px)
```

### Implementation

In your `Payment.php` class:

```php
/**
 * Get admin icon URL (shown in payment settings)
 */
public function get_icon(): string {
    return plugin_dir_url( WPTRAVELENGINE_YOURGATEWAY_FILE_PATH ) . 'src/images/yourgateway.png';
}

/**
 * Get checkout display icon (shown to customers during checkout)
 */
public function get_display_icon(): string {
    return plugin_dir_url( WPTRAVELENGINE_YOURGATEWAY_FILE_PATH ) . 'src/images/yourgateway-checkout.png';
}
```

### Example Icon HTML Output

```html
<!-- Admin Settings -->
<img src="/wp-content/plugins/wptravelengine-yourgateway-payment/src/images/yourgateway.png"
     alt="Your Gateway"
     width="64"
     height="64">

<!-- Checkout Page -->
<img src="/wp-content/plugins/wptravelengine-yourgateway-payment/src/images/yourgateway-checkout.png"
     alt="Your Gateway"
     class="payment-gateway-logo">
```

---

## Complete Code Examples

### Example 1: Simple Redirect Gateway

For gateways that redirect users to an external payment page.

```php
protected function _process( Booking $booking, PaymentModel $payment, float $amount ): void {
    $api = new YourGatewayApi( $this->settings['yourgateway'] );

    $payment_url = $api->create_payment_session( array(
        'amount'       => $amount,
        'currency'     => wptravelengine_settings()->get( 'currency_code' ),
        'order_id'     => $this->generate_order_id( $booking->get_id() ),
        'return_url'   => $this->get_callback_url( $payment, 'success' ),
        'cancel_url'   => $this->get_callback_url( $payment, 'cancel' ),
        'webhook_url'  => $this->get_callback_url( $payment, 'notification' ),
    ) );

    if ( ! $payment_url ) {
        wp_die( __( 'Payment initialization failed.', 'wptravelengine-yourgateway-payment' ) );
    }

    wp_redirect( $payment_url );
    exit;
}
```

### Example 2: Form POST Gateway

For gateways that require form submission with hidden fields.

```php
protected function _process( Booking $booking, PaymentModel $payment, float $amount ): void {
    $api = new YourGatewayApi( $this->settings['yourgateway'] );

    $form_data = $api->create_payment_form( array(
        'amount'      => $amount,
        'currency'    => wptravelengine_settings()->get( 'currency_code' ),
        'order_id'    => $this->generate_order_id( $booking->get_id() ),
        'return_url'  => $this->get_callback_url( $payment, 'success' ),
    ) );

    // Display auto-submit form
    echo '<html><body>';
    echo '<form id="payment-form" method="post" action="' . esc_url( $form_data['action'] ) . '">';

    foreach ( $form_data['fields'] as $name => $value ) {
        echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
    }

    echo '<noscript><button type="submit">Continue to Payment</button></noscript>';
    echo '</form>';
    echo '<script>document.getElementById("payment-form").submit();</script>';
    echo '</body></html>';
    exit;
}
```

### Example 3: API Client Class

```php
<?php
namespace WPTravelEngineYourGateway;

/**
 * Gateway API Client
 */
class YourGatewayApi {
    /**
     * API base URL
     */
    private $api_url;

    /**
     * API credentials
     */
    private $api_key;
    private $secret_key;
    private $test_mode;

    /**
     * Constructor
     */
    public function __construct( array $settings ) {
        $this->api_key    = $settings['api_key'] ?? '';
        $this->secret_key = $settings['secret_key'] ?? '';
        $this->test_mode  = $settings['test_mode'] ?? false;
        $this->api_url    = $this->test_mode
            ? 'https://test-api.yourgateway.com'
            : 'https://api.yourgateway.com';
    }

    /**
     * Create payment session
     */
    public function create_payment( array $data ) {
        $response = wp_remote_post(
            $this->api_url . '/v1/payments',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type'  => 'application/json',
                ),
                'body'    => wp_json_encode( $data ),
                'timeout' => 30,
            )
        );

        if ( is_wp_error( $response ) ) {
            error_log( 'Payment Gateway API Error: ' . $response->get_error_message() );
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        return $body;
    }

    /**
     * Verify IPN signature
     */
    public function verify_signature( array $data ): bool {
        if ( empty( $data['signature'] ) ) {
            return false;
        }

        $received_signature = $data['signature'];
        unset( $data['signature'] );

        // Sort data
        ksort( $data );

        // Calculate expected signature
        $signature_string = http_build_query( $data ) . $this->secret_key;
        $expected_signature = hash( 'sha256', $signature_string );

        // Timing-safe comparison
        return hash_equals( $expected_signature, $received_signature );
    }

    /**
     * Get transaction status
     */
    public function get_transaction( string $transaction_id ) {
        $response = wp_remote_get(
            $this->api_url . '/v1/transactions/' . $transaction_id,
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_key,
                ),
                'timeout' => 15,
            )
        );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        return json_decode( wp_remote_retrieve_body( $response ), true );
    }
}
```

---

## Hooks Reference

### Action Hooks

#### 1. `plugins_loaded` (Priority: 9)
Initialize your plugin

```php
add_action( 'plugins_loaded', 'wptravelengine_yourgateway_payment_init', 9 );
```

#### 2. `wptravelengine_registering_payment_gateways`
Register your payment gateway

```php
add_action( 'wptravelengine_registering_payment_gateways', function( $gateways ) {
    $gateways['yourgateway'] = new YourGateway\Payment();
    return $gateways;
} );
```

#### 3. `wptravelengine_after_booking_process_completed`
Fired after successful booking

```php
do_action( 'wptravelengine_after_booking_process_completed', $booking_id );
```

#### 4. `wptravelengine_api_update_settings`
Update settings via REST API

```php
add_action( 'wptravelengine_api_update_settings', array( $this, 'update_settings' ), 10, 2 );
```

### Filter Hooks

#### 1. `wptravelengine_settings:tabs:payments`
Add settings tab

```php
add_filter( 'wptravelengine_settings:tabs:payments', function( $settings ) {
    $settings['yourgateway'] = require 'global-settings.php';
    return $settings;
} );
```

#### 2. `wptravelengine_rest_payment_gateways`
Add gateway to REST API list

```php
add_filter( 'wptravelengine_rest_payment_gateways', function( $gateways, $settings ) {
    $gateways[] = array(
        'id'     => 'yourgateway_enable',
        'name'   => 'Your Gateway',
        'enable' => true,
    );
    return $gateways;
}, 10, 2 );
```

#### 3. `wptravelengine_checkout_{gateway_id}_button`
Customize checkout button

```php
add_filter( 'wptravelengine_checkout_yourgateway_enable_button', function( $label ) {
    return __( 'Pay with Your Gateway', 'domain' );
} );
```

#### 4. `wptravelengine_settings_api_schema`
Add settings schema

```php
add_filter( 'wptravelengine_settings_api_schema', function( $schema ) {
    $schema['yourgateway'] = array(
        'type' => 'object',
        'properties' => array( /* ... */ ),
    );
    return $schema;
}, 10, 2 );
```

---

## Testing Your Integration

### 1. Test Checklist

- [ ] Gateway appears in admin payment settings
- [ ] Settings save correctly
- [ ] Gateway icon displays in admin
- [ ] Gateway appears on checkout page
- [ ] Checkout icon displays correctly
- [ ] Payment redirects to gateway
- [ ] Success URL redirects to thank you page
- [ ] Cancel URL redirects to checkout
- [ ] IPN/notification updates payment status
- [ ] Booking confirmation emails sent
- [ ] Payment recorded in admin
- [ ] Test mode works correctly

### 2. Debug Logging

Add debug logging to troubleshoot issues:

```php
if ( wptravelengine_toggled( $this->settings['debug_mode'] ?? false ) ) {
    error_log( 'YourGateway: ' . print_r( $data, true ) );
}
```

### 3. Test IPN with ngrok

```bash
# Install ngrok
ngrok http 80

# Use ngrok URL for IPN in gateway dashboard
https://abc123.ngrok.io/?payment_key=xxx&callback_type=notification
```

### 4. Manual Testing Flow

1. Create a test booking
2. Select your payment gateway
3. Proceed to checkout
4. Complete payment (use test card)
5. Verify success redirect
6. Check booking status in admin
7. Verify email notifications
8. Test cancel flow
9. Test IPN callback

---

## Best Practices

### Security

1. **Validate all input**
   ```php
   $transaction_id = sanitize_text_field( $_POST['transaction_id'] ?? '' );
   ```

2. **Verify signatures**
   ```php
   if ( ! $api->verify_signature( $_POST ) ) {
       status_header( 403 );
       exit( 'Invalid signature' );
   }
   ```

3. **Use nonces for forms** (if applicable)
   ```php
   wp_nonce_field( 'payment_action', 'payment_nonce' );
   ```

4. **Sanitize and validate settings**
   ```php
   $api_key = sanitize_text_field( $request['yourgateway']['api_key'] );
   ```

5. **Use HTTPS for callbacks**
   Always ensure your site uses HTTPS for IPN endpoints

### Performance

1. **Cache API responses** when appropriate
2. **Use transients** for temporary data
3. **Limit API calls** - don't verify on success callback if IPN is reliable
4. **Async processing** - let IPN handle final confirmation

### Error Handling

1. **Log errors**
   ```php
   error_log( 'Payment Error: ' . $e->getMessage() );
   ```

2. **User-friendly messages**
   ```php
   wp_die( __( 'Payment failed. Please try again.', 'domain' ) );
   ```

3. **Return appropriate HTTP codes** for IPN
   ```php
   status_header( 200 ); // Success
   status_header( 400 ); // Bad request
   status_header( 403 ); // Forbidden
   ```

### Code Quality

1. **Follow WordPress coding standards**
2. **Use type hints** (PHP 7.4+)
   ```php
   public function process_payment( Booking $booking, PaymentModel $payment ): void
   ```

3. **Document your code**
   ```php
   /**
    * Process payment for booking
    *
    * @param Booking $booking Booking instance
    * @param PaymentModel $payment Payment instance
    * @return void
    */
   ```

4. **Use constants** for configuration
   ```php
   define( 'YOURGATEWAY_API_VERSION', 'v1' );
   ```

### Internationalization

1. **Translate all strings**
   ```php
   __( 'Text', 'wptravelengine-yourgateway-payment' )
   ```

2. **Load text domain**
   ```php
   load_plugin_textdomain( 'wptravelengine-yourgateway-payment' );
   ```

3. **Use placeholders**
   ```php
   sprintf( __( 'Booking #%s', 'domain' ), $booking_id )
   ```

---

## Common Issues & Solutions

### Issue: Gateway not appearing on checkout

**Solution:**
- Verify gateway is enabled in settings
- Check `get_gateway_id()` matches registration key
- Ensure currency is supported via `is_supports_currency()`

### Issue: IPN not working

**Solution:**
- Check IPN URL is accessible (not behind firewall)
- Verify signature validation logic
- Check gateway dashboard IPN configuration
- Test with ngrok for local development

### Issue: Payment status not updating

**Solution:**
- Verify IPN signature validation
- Check duplicate prevention logic
- Ensure correct status mapping
- Check `do_action` for email triggering

### Issue: Icons not displaying

**Solution:**
- Verify file paths in `get_icon()` and `get_display_icon()`
- Check file permissions
- Ensure images exist in `src/images/` directory
- Clear browser cache

---

## Additional Resources

- [WP Travel Engine Documentation](https://docs.wptravelengine.com/)
- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)
- [WP Travel Engine GitHub](https://github.com/WPTravelEngine/)

---

## Support

For issues specific to WP Travel Engine integration:
- Visit [WP Travel Engine Support](https://wptravelengine.com/support/)

For payment gateway specific issues:
- Consult your payment gateway's developer documentation
- Contact your payment gateway's technical support

---

**Last Updated:** December 2025
**Compatible with:** WP Travel Engine 6.0+ (Supports both v6.7.0+ and legacy versions)
**Recommended:** WP Travel Engine v6.7.0+ for simplified implementation
**Minimum PHP:** 7.4

---

## License

This documentation is provided as-is for developers integrating payment gateways with WP Travel Engine.
