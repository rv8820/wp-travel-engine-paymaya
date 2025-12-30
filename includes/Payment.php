<?php
namespace WPTravelEngineMaya;

use WPTravelEngine\PaymentGateways\BaseGateway;
use WPTravelEngine\Core\Models\Post\Booking;
use WPTravelEngine\Core\Models\Post\Payment as PaymentModel;
use WPTravelEngine\Core\Booking\BookingProcess;

/**
 * Maya Payment Gateway Class
 */
class Payment extends BaseGateway {
    /**
     * Gateway ID
     */
    protected $gateway_id = 'maya_enable';

    /**
     * Cart version requirement
     */
    public static string $cart_version = '4.0';

    /**
     * Plugin settings
     */
    protected $settings;

    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = wptravelengine_settings()->get();
    }

    /**
     * Get gateway ID
     */
    public function get_gateway_id(): string {
        return $this->gateway_id;
    }

    /**
     * Get gateway label (admin)
     */
    public function get_label(): string {
        return ! empty( $this->settings['maya']['gateway_label'] )
            ? $this->settings['maya']['gateway_label']
            : __( 'Maya', 'wptravelengine-maya-payment' );
    }

    /**
     * Get public label (checkout)
     */
    public function get_public_label(): string {
        return $this->get_label();
    }

    /**
     * Get gateway description
     */
    public function get_description(): string {
        return ! empty( $this->settings['maya']['description'] )
            ? $this->settings['maya']['description']
            : __( 'Pay securely using Maya payment gateway.', 'wptravelengine-maya-payment' );
    }

    /**
     * Get admin icon URL
     */
    public function get_icon(): string {
        return plugin_dir_url( WPTRAVELENGINE_MAYA_FILE_PATH ) . 'src/images/maya.png';
    }

    /**
     * Get checkout display icon URL
     */
    public function get_display_icon(): string {
        return plugin_dir_url( WPTRAVELENGINE_MAYA_FILE_PATH ) . 'src/images/maya-checkout.png';
    }

    /**
     * Check if gateway supports currency
     */
    public function is_supports_currency( string $currency ): bool {
        return 'PHP' === $currency;
    }

    /**
     * Check if in test mode
     */
    public function is_test_mode(): bool {
        return wptravelengine_toggled( $this->settings['maya']['test_mode'] ?? false );
    }

    /**
     * Process payment - Modern method (WP Travel Engine v6.7.0+)
     */
    public function process_payment_v2( Booking $booking, PaymentModel $payment, BookingProcess $booking_process ): void {
        // Get payable amount from payment object
        $payable_amount = (float) $payment->get_amount();

        $this->_process( $booking, $payment, $payable_amount );
    }

    /**
     * Internal payment processing logic
     */
    protected function _process( Booking $booking, PaymentModel $payment, float $amount ): void {
        // Get API settings
        if ( empty( $this->settings['maya']['secret_key'] ) ) {
            wp_die(
                esc_html__( 'Maya payment gateway is not configured. Please enter your Secret API Key in the settings.', 'wptravelengine-maya-payment' ),
                esc_html__( 'Payment Error', 'wptravelengine-maya-payment' )
            );
        }

        // Initialize Maya API
        $api = new MayaApi( $this->settings['maya'] );

        // Prepare checkout data
        $checkout_data = $this->prepare_checkout_data( $booking, $payment, $amount );

        // Create checkout
        $response = $api->create_checkout( $checkout_data );

        if ( is_wp_error( $response ) ) {
            wp_die(
                esc_html( $response->get_error_message() ),
                esc_html__( 'Payment Error', 'wptravelengine-maya-payment' )
            );
        }

        // Save checkout ID
        if ( isset( $response['checkoutId'] ) ) {
            $payment->set_meta( 'maya_checkout_id', $response['checkoutId'] );
            $payment->save();
        }

        // Redirect to Maya
        if ( isset( $response['redirectUrl'] ) ) {
            wp_redirect( $response['redirectUrl'] );
            exit;
        }

        wp_die( esc_html__( 'Failed to create payment session.', 'wptravelengine-maya-payment' ) );
    }

    /**
     * Prepare checkout data for Maya API
     */
    private function prepare_checkout_data( Booking $booking, PaymentModel $payment, float $amount ): array {
        $booking_id = $booking->get_id();
        $billing_info = $payment->get_meta( 'billing_info' );
        $cart_info = $payment->get_meta( 'cart_info' );

        // Get trip info from cart or booking
        $trip_id = '';
        $trip_name = '';

        // Try to get from cart info first
        if ( ! empty( $cart_info['items'] ) ) {
            $first_item = reset( $cart_info['items'] );
            $trip_id = $first_item['trip_id'] ?? '';
            $trip_name = $first_item['title'] ?? '';
        }

        // Fallback to booking meta
        if ( empty( $trip_id ) ) {
            $trip_id = get_post_meta( $booking_id, 'wp_travel_engine_booking_setting_trip_id', true );
        }
        if ( empty( $trip_name ) && ! empty( $trip_id ) ) {
            $trip_name = get_the_title( $trip_id );
        }

        // Final fallback
        if ( empty( $trip_name ) ) {
            $trip_name = sprintf( __( 'Trip Booking #%s', 'wptravelengine-maya-payment' ), $booking_id );
        }

        // Prepare items
        $items = array(
            array(
                'name'        => $trip_name,
                'quantity'    => 1,
                'code'        => 'TRIP-' . $trip_id,
                'description' => sprintf( __( 'Booking #%s', 'wptravelengine-maya-payment' ), $booking_id ),
                'amount'      => array(
                    'value'    => $amount,
                    'currency' => 'PHP',
                ),
                'totalAmount' => array(
                    'value'    => $amount,
                    'currency' => 'PHP',
                ),
            ),
        );

        // Prepare buyer
        $buyer = array(
            'firstName' => $billing_info['fname'] ?? 'Guest',
            'lastName'  => $billing_info['lname'] ?? 'Customer',
            'contact'   => array(
                'email' => $billing_info['email'] ?? '',
                'phone' => $billing_info['phone'] ?? '',
            ),
        );

        // Get callback URLs
        $success_url = add_query_arg(
            array(
                'payment_key' => $payment->get_payment_key(),
                'callback_type' => 'success',
            ),
            home_url( '/' )
        );

        $failure_url = add_query_arg(
            array(
                'payment_key' => $payment->get_payment_key(),
                'callback_type' => 'cancel',
            ),
            home_url( '/' )
        );

        $webhook_url = add_query_arg(
            array(
                'payment_key' => $payment->get_payment_key(),
                'callback_type' => 'notification',
            ),
            home_url( '/' )
        );

        return array(
            'totalAmount' => array(
                'value'    => $amount,
                'currency' => 'PHP',
            ),
            'items' => $items,
            'buyer' => $buyer,
            'redirectUrl' => array(
                'success' => $success_url,
                'failure' => $failure_url,
                'cancel'  => $failure_url,
            ),
            'requestReferenceNumber' => $booking_id . '-' . $payment->get_id() . '-' . time(),
        );
    }

    /**
     * Handle successful payment callback
     */
    public function handle_success_request( Booking $booking, PaymentModel $payment ): void {
        $payment_key = sanitize_text_field( $_REQUEST['payment_key'] ?? '' );
        wp_redirect( add_query_arg( 'payment_key', $payment_key, $booking->get_confirmation_url() ) );
        exit;
    }

    /**
     * Handle cancelled payment callback
     */
    public function handle_cancel_request( Booking $booking, PaymentModel $payment ): void {
        $payment->set_status( 'canceled' );
        $payment_key = sanitize_text_field( $_REQUEST['payment_key'] ?? '' );
        if ( $payment_key ) {
            delete_transient( "payment_key_{$payment_key}" );
        }
        wp_redirect( add_query_arg( 'payment_error', '1', home_url( '/checkout' ) ) );
        exit;
    }

    /**
     * Handle IPN/webhook notification callback
     */
    public function handle_notification_request( Booking $booking, PaymentModel $payment ): void {
        // Get webhook data
        $webhook_data = json_decode( file_get_contents( 'php://input' ), true );

        if ( empty( $webhook_data['id'] ) || empty( $webhook_data['paymentStatus'] ) ) {
            status_header( 400 );
            exit( 'Invalid webhook data' );
        }

        // Prevent duplicate processing
        $processed_key = 'maya_processed_' . $webhook_data['id'];
        if ( get_transient( $processed_key ) ) {
            status_header( 200 );
            exit( 'Already processed' );
        }

        // Determine payment status
        $status = $webhook_data['paymentStatus'];
        $is_successful = in_array( $status, array( 'PAYMENT_SUCCESS' ), true );
        $is_pending = in_array( $status, array( 'PAYMENT_PENDING', 'PAYMENT_PROCESSING' ), true );

        // Update payment (v6.7.0+)
        $this->after_cart_v4_version( $payment, $is_successful, $is_pending );

        // Save webhook data
        $payment->set_meta( 'maya_transaction_id', $webhook_data['id'] );
        $payment->set_meta( 'maya_webhook_data', $webhook_data );
        $payment->save();

        // Mark as processed
        set_transient( $processed_key, true, HOUR_IN_SECONDS );

        // Send emails
        do_action( 'wptravelengine_after_booking_process_completed', $booking->get_id() );

        status_header( 200 );
        exit( 'OK' );
    }

    /**
     * Process payment status for WP Travel Engine v6.7.0+
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
}
