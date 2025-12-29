# Custom Payment Gateway Integration Guide

> **⚠️ IMPORTANT NOTICE**
>
> We do not recommend implementing custom payment gateways at this time. Our development team is currently working on major updates to the checkout calculation system, which may significantly change the payment flow and integration process.
>
> **Please wait 1-2 weeks** before starting your custom payment gateway integration to ensure compatibility with the upcoming changes.
>
> If you have urgent requirements, please contact our support team to discuss your specific needs.

## Quick Start

### 1. Register Your Payment Gateway

Use the `wptravelengine_registering_payment_gateways` filter to register your gateway:

```php
<?php
// Register the gateway
add_filter( 'wptravelengine_registering_payment_gateways', function( $payment_gateways ) {
    $payment_gateways['my_custom_gateway'] = new MyCustomGateway();
    return $payment_gateways;
} );

```
### 1. Create Your Payment Gateway Class

Create a new class that extends the `BaseGateway` class:

```php
<?php
/**
 * Custom Payment Gateway
 */

namespace YourNamespace\PaymentGateways;

use WPTravelEngine\PaymentGateways\BaseGateway;
use WPTravelEngine\Core\Booking\BookingProcess;
use WPTravelEngine\Core\Models\Post\Booking;
use WPTravelEngine\Core\Models\Post\Payment;

class CustomPaymentGateway extends BaseGateway {

    /**
     * Cart version compatibility
     * Set to '4.0' for latest version or '3.0' for legacy support
     */
    public static string $cart_version = '4.0';

    /**
     * Get unique gateway identifier
     *
     * @return string
     */
    public function get_gateway_id(): string {
        return 'custom_payment_gateway'; // Unique ID for your gateway
    }

    /**
     * Get gateway label (shown in admin settings)
     *
     * @return string
     */
    public function get_label(): string {
        return __( 'Custom Payment Gateway', 'your-textdomain' );
    }

    /**
     * Get public label (shown on checkout page)
     *
     * @return string
     */
    public function get_public_label(): string {
        return __( 'Pay with Custom Gateway', 'your-textdomain' );
    }

    /**
     * Get icon for backend settings
     * Returns HTML (SVG or img tag)
     *
     * @return string
     */
    public function get_icon(): string {
        return '<svg width="38" height="24" viewBox="0 0 38 24" fill="none">
            <!-- Your SVG icon code here -->
        </svg>';

        // Or return an image tag:
        // return '<img src="' . plugin_dir_url( __FILE__ ) . 'assets/icon.png" alt="Gateway Icon" />';
    }

    /**
     * Get display icon for checkout page
     * This is what customers see when selecting payment method
     *
     * @return string
     */
    public function get_display_icon(): string {
        // You can return the same as get_icon() or a different icon
        return $this->get_icon();
    }

    /**
     * Get info text shown at checkout
     * Provides instructions or information to customers
     *
     * @return string
     */
    public function get_info(): string {
        return wptravelengine_settings()->get(
            'custom_payment_gateway.instruction',
            __( 'You will be redirected to complete your payment securely.', 'your-textdomain' )
        );
    }

    /**
     * Get gateway description
     * Additional description for the gateway
     *
     * @return string
     */
    public function get_description(): string {
        return wptravelengine_settings()->get(
            'custom_payment_gateway.description',
            __( 'Secure payment processing through Custom Gateway.', 'your-textdomain' )
        );
    }

    /**
     * Process the payment
     * This is the main method where you handle payment processing
     *
     * @param Booking        $booking Booking object
     * @param Payment        $payment Payment object
     * @param BookingProcess $booking_instance Booking process instance
     *
     * @return void
     */
    public function process_payment( Booking $booking, Payment $payment, BookingProcess $booking_instance ): void {

        // Get payment details
        $payable = $payment->get_meta( 'payable' );
        $amount = array(
            'value'    => (float) $payable['amount'],
            'currency' => $payable['currency'],
        );

        // Update booking payment gateway
        update_post_meta(
            $booking->get_id(),
            'wp_travel_engine_booking_payment_gateway',
            $this->get_label()
        );

        // Set initial payment status
        $payment->set_status( 'pending' );
        $payment->set_payment_gateway( $this->get_gateway_id() );
        $payment->set_meta( 'payment_status', 'pending' );
        $payment->set_meta( 'payment_amount', $amount );
        $payment->save();

        // Your custom payment processing logic here
        // For example, redirect to payment gateway:

        $redirect_url = $this->get_payment_redirect_url( $booking, $payment, $amount );

        // Redirect to payment gateway
        wp_redirect( $redirect_url );
        exit;
    }

    /**
     * Example: Get payment redirect URL
     * Build the URL to redirect customer to payment gateway
     *
     * @param Booking $booking
     * @param Payment $payment
     * @param array   $amount
     *
     * @return string
     */
    private function get_payment_redirect_url( Booking $booking, Payment $payment, array $amount ): string {

        // Get callback URLs for success/cancel/notification
        $success_url = $this->get_callback_url( $payment, 'success' );
        $cancel_url = $this->get_callback_url( $payment, 'cancel' );
        $notify_url = $this->get_callback_url( $payment, 'notification' );

        // Build your payment gateway URL with parameters
        $gateway_url = 'https://payment-gateway.com/checkout';

        $params = array(
            'amount'      => $amount['value'],
            'currency'    => $amount['currency'],
            'booking_id'  => $booking->get_id(),
            'payment_id'  => $payment->get_id(),
            'success_url' => $success_url,
            'cancel_url'  => $cancel_url,
            'notify_url'  => $notify_url,
            // Add your gateway-specific parameters
        );

        return add_query_arg( $params, $gateway_url );
    }

    /**
     * Optional: Process payment callback
     * Handle success/cancel/notification callbacks from payment gateway
     */
    public function handle_payment_callback() {

        $payment_key = isset( $_GET['payment_key'] ) ? sanitize_text_field( $_GET['payment_key'] ) : '';
        $callback_type = isset( $_GET['callback_type'] ) ? sanitize_text_field( $_GET['callback_type'] ) : '';

        if ( empty( $payment_key ) ) {
            return;
        }

        // Get payment ID from transient
        $payment_id = get_transient( "payment_key_$payment_key" );

        if ( ! $payment_id ) {
            return;
        }

        $payment = Payment::get( $payment_id );
        $booking = Booking::get( $payment->get_meta( 'booking_id' ) );

        switch ( $callback_type ) {
            case 'success':
                $this->handle_success_callback( $booking, $payment );
                break;

            case 'cancel':
                $this->handle_cancel_request( $booking, $payment );
                break;

            case 'notification':
                $this->handle_notification_callback( $booking, $payment );
                break;
        }
    }

    /**
     * Handle successful payment
     * 
     * for only if your payment requires server responses.
     */
    private function handle_success_callback( Booking $booking, Payment $payment ) {

        // Verify payment with your gateway
        // Update payment status
        $payment->set_status( 'completed' );
        $payment->save();

        // Update booking
        $payable = $payment->get_meta( 'payable' );
        $amount = (float) $payable['amount'];

        $paid_amount = (float) $booking->get_paid_amount();
        $due_amount = (float) $booking->get_due_amount();

        $booking->set_meta( 'paid_amount', $paid_amount + $amount );
        $booking->set_meta( 'due_amount', max( $due_amount - $amount, 0 ) );
        $booking->save();

        // Clean up transient
        delete_transient( "payment_key_{$payment->get_payment_key()}" );

        // Redirect to thank you page
        $thank_you_url = $booking->get_confirmation_url();
        wp_redirect( $thank_you_url );
        exit;
    }
}
```