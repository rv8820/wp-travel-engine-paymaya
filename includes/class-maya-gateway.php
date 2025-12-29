<?php
/**
 * Maya Payment Gateway Class
 *
 * @package WTE_Maya
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPTravelEngine\PaymentGateways\BaseGateway;
use WPTravelEngine\Core\Booking\BookingProcess;
use WPTravelEngine\Core\Models\Post\Booking;
use WPTravelEngine\Core\Models\Post\Payment;

/**
 * Maya Payment Gateway Class
 */
class WTE_Maya_Gateway extends BaseGateway {

    /**
     * Gateway ID
     *
     * @var string
     */
    protected $gateway_id = 'maya_enable';

    /**
     * Cart version compatibility
     *
     * @var string
     */
    public static string $cart_version = '4.0';

    /**
     * Get unique gateway identifier
     *
     * @return string
     */
    public function get_gateway_id(): string {
        return $this->gateway_id;
    }

    /**
     * Get gateway label (shown in admin settings)
     *
     * @return string
     */
    public function get_label(): string {
        return __( 'Maya', 'wte-maya' );
    }

    /**
     * Get public label (shown on checkout page)
     *
     * @return string
     */
    public function get_public_label(): string {
        return __( 'Maya Payment Gateway', 'wte-maya' );
    }

    /**
     * Get icon for backend settings
     *
     * @return string
     */
    public function get_icon(): string {
        // Maya logo SVG
        return '<svg width="60" height="24" viewBox="0 0 60 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <text x="0" y="18" font-family="Arial, sans-serif" font-size="18" font-weight="bold" fill="#00D632">Maya</text>
        </svg>';
    }

    /**
     * Get display icon for checkout page
     *
     * @return string
     */
    public function get_display_icon(): string {
        return $this->get_icon();
    }

    /**
     * Get info text shown at checkout
     *
     * @return string
     */
    public function get_info(): string {
        return wptravelengine_settings()->get(
            'maya.instruction',
            __( 'You will be redirected to Maya to complete your payment securely. You can pay using credit/debit cards, QRPh, Maya Wallet, and other payment methods.', 'wte-maya' )
        );
    }

    /**
     * Get gateway description
     *
     * @return string
     */
    public function get_description(): string {
        return wptravelengine_settings()->get(
            'maya.description',
            __( 'Secure payment processing through Maya Payment Gateway. Accept credit cards, debit cards, QRPh, and Maya Wallet.', 'wte-maya' )
        );
    }

    /**
     * Process the payment
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
            'currency' => 'PHP',
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

        // Get Maya API settings
        $settings = $this->get_settings();

        if ( empty( $settings['public_key'] ) ) {
            wp_die(
                esc_html__( 'Maya payment gateway is not configured properly. Please contact the site administrator.', 'wte-maya' ),
                esc_html__( 'Payment Gateway Error', 'wte-maya' ),
                array( 'response' => 500 )
            );
        }

        // Initialize Maya API client
        $api_client = new WTE_Maya_API_Client(
            $settings['public_key'],
            $settings['test_mode']
        );

        // Build checkout data
        $checkout_data = $this->prepare_checkout_data( $booking, $payment, $amount );

        // Create checkout session
        $response = $api_client->create_checkout( $checkout_data );

        if ( is_wp_error( $response ) ) {
            wp_die(
                esc_html( $response->get_error_message() ),
                esc_html__( 'Payment Gateway Error', 'wte-maya' ),
                array( 'response' => 500 )
            );
        }

        // Save checkout ID to payment meta
        if ( isset( $response['checkoutId'] ) ) {
            $payment->set_meta( 'maya_checkout_id', $response['checkoutId'] );
            $payment->save();
        }

        // Redirect to Maya checkout page
        if ( isset( $response['redirectUrl'] ) ) {
            wp_redirect( $response['redirectUrl'] );
            exit;
        }

        // If no redirect URL, show error
        wp_die(
            esc_html__( 'Failed to create payment session. Please try again.', 'wte-maya' ),
            esc_html__( 'Payment Gateway Error', 'wte-maya' ),
            array( 'response' => 500 )
        );
    }

    /**
     * Prepare checkout data for Maya API
     *
     * @param Booking $booking Booking object
     * @param Payment $payment Payment object
     * @param array   $amount Amount data
     * @return array Checkout data
     */
    private function prepare_checkout_data( Booking $booking, Payment $payment, array $amount ): array {

        // Get booking details
        $booking_id = $booking->get_id();
        $booking_meta = get_post_meta( $booking_id );

        // Get traveler/customer information
        $first_name = get_post_meta( $booking_id, 'wp_travel_engine_booking_setting_fname', true );
        $last_name = get_post_meta( $booking_id, 'wp_travel_engine_booking_setting_lname', true );
        $email = get_post_meta( $booking_id, 'wp_travel_engine_booking_setting_email', true );
        $phone = get_post_meta( $booking_id, 'wp_travel_engine_booking_setting_phone', true );
        $country = get_post_meta( $booking_id, 'wp_travel_engine_booking_setting_country', true );
        $address = get_post_meta( $booking_id, 'wp_travel_engine_booking_setting_address', true );
        $city = get_post_meta( $booking_id, 'wp_travel_engine_booking_setting_city', true );

        // Get trip information
        $trip_id = get_post_meta( $booking_id, 'wp_travel_engine_booking_setting_trip_id', true );
        $trip_name = get_the_title( $trip_id );

        // Generate unique reference number
        $reference_number = $booking_id . '-' . $payment->get_id() . '-' . time();

        // Prepare items array
        $items = array(
            array(
                'name'        => $trip_name,
                'quantity'    => 1,
                'code'        => 'TRIP-' . $trip_id,
                'description' => sprintf( __( 'Booking #%s', 'wte-maya' ), $booking_id ),
                'amount'      => array(
                    'value' => $amount['value'],
                ),
                'totalAmount' => array(
                    'value' => $amount['value'],
                ),
            ),
        );

        // Prepare buyer information
        $buyer = array(
            'firstName' => $first_name ?: 'Guest',
            'lastName'  => $last_name ?: 'Customer',
            'contact'   => array(
                'email' => $email ?: '',
                'phone' => $phone ?: '',
            ),
        );

        // Add address if available
        if ( ! empty( $address ) || ! empty( $city ) ) {
            $buyer['billingAddress'] = array(
                'line1'       => $address ?: '',
                'city'        => $city ?: '',
                'countryCode' => $country ?: 'PH',
                'zipCode'     => '',
            );
        }

        // Get callback URLs
        $webhook_url = add_query_arg(
            array(
                'wte-maya-webhook' => '1',
                'payment_key'      => $payment->get_payment_key(),
            ),
            home_url( '/' )
        );

        $success_url = add_query_arg(
            array(
                'payment_key' => $payment->get_payment_key(),
                'status'      => 'success',
            ),
            $booking->get_confirmation_url()
        );

        $failure_url = add_query_arg(
            array(
                'payment_key' => $payment->get_payment_key(),
                'status'      => 'failed',
            ),
            home_url( '/' )
        );

        $cancel_url = add_query_arg(
            array(
                'payment_key' => $payment->get_payment_key(),
                'status'      => 'cancelled',
            ),
            home_url( '/' )
        );

        // Build and return checkout data
        return array(
            'totalAmount' => array(
                'value'    => (float) $amount['value'],
                'currency' => $amount['currency'],
            ),
            'items' => $items,
            'buyer' => $buyer,
            'redirectUrl' => array(
                'success' => $success_url,
                'failure' => $failure_url,
                'cancel'  => $cancel_url,
            ),
            'requestReferenceNumber' => $reference_number,
        );
    }

    /**
     * Process webhook notification from Maya
     *
     * @return void
     */
    public function process_webhook() {
        // Get raw POST data
        $raw_post = file_get_contents( 'php://input' );
        $webhook_data = json_decode( $raw_post, true );

        // Log webhook for debugging
        $this->log_debug( 'Webhook Received', $webhook_data );

        // Validate webhook data
        if ( empty( $webhook_data ) || ! isset( $webhook_data['id'] ) ) {
            status_header( 400 );
            die( 'Invalid webhook data' );
        }

        // Get payment key from URL
        $payment_key = isset( $_GET['payment_key'] ) ? sanitize_text_field( $_GET['payment_key'] ) : '';

        if ( empty( $payment_key ) ) {
            status_header( 400 );
            die( 'Missing payment key' );
        }

        // Get payment ID from transient
        $payment_id = get_transient( "payment_key_$payment_key" );

        if ( ! $payment_id ) {
            // Try to find payment by payment key in post meta
            $payments = get_posts(
                array(
                    'post_type'      => 'wte-payment',
                    'post_status'    => 'any',
                    'posts_per_page' => 1,
                    'meta_query'     => array(
                        array(
                            'key'   => 'payment_key',
                            'value' => $payment_key,
                        ),
                    ),
                )
            );

            if ( empty( $payments ) ) {
                status_header( 404 );
                die( 'Payment not found' );
            }

            $payment_id = $payments[0]->ID;
        }

        // Get payment and booking objects
        $payment = Payment::get( $payment_id );
        $booking = Booking::get( $payment->get_meta( 'booking_id' ) );

        if ( ! $payment || ! $booking ) {
            status_header( 404 );
            die( 'Payment or booking not found' );
        }

        // Process based on payment status
        $payment_status = isset( $webhook_data['paymentStatus'] ) ? $webhook_data['paymentStatus'] : '';

        switch ( $payment_status ) {
            case 'PAYMENT_SUCCESS':
                $this->handle_payment_success( $booking, $payment, $webhook_data );
                break;

            case 'PAYMENT_FAILED':
            case 'PAYMENT_DROPOUT':
            case 'PAYMENT_CANCELLED':
                $this->handle_payment_failure( $booking, $payment, $webhook_data );
                break;

            default:
                $this->log_debug( 'Unknown payment status', $payment_status );
                break;
        }

        // Send success response
        status_header( 200 );
        die( 'OK' );
    }

    /**
     * Handle successful payment
     *
     * @param Booking $booking Booking object
     * @param Payment $payment Payment object
     * @param array   $webhook_data Webhook data
     */
    private function handle_payment_success( Booking $booking, Payment $payment, array $webhook_data ) {

        // Check if already processed
        if ( 'completed' === $payment->get_status() ) {
            return;
        }

        // Update payment status
        $payment->set_status( 'completed' );
        $payment->set_meta( 'payment_status', 'completed' );
        $payment->set_meta( 'maya_payment_id', $webhook_data['id'] );
        $payment->set_meta( 'maya_receipt_number', $webhook_data['receiptNumber'] ?? '' );
        $payment->set_meta( 'maya_webhook_data', $webhook_data );
        $payment->save();

        // Update booking amounts
        $payable = $payment->get_meta( 'payable' );
        $amount = (float) $payable['amount'];

        $paid_amount = (float) $booking->get_paid_amount();
        $due_amount = (float) $booking->get_due_amount();

        $booking->set_meta( 'paid_amount', $paid_amount + $amount );
        $booking->set_meta( 'due_amount', max( $due_amount - $amount, 0 ) );

        // Update booking status if fully paid
        if ( $due_amount - $amount <= 0 ) {
            $booking->set_meta( 'payment_status', 'paid' );
        } else {
            $booking->set_meta( 'payment_status', 'partially-paid' );
        }

        $booking->save();

        $this->log_debug( 'Payment successful', array(
            'booking_id' => $booking->get_id(),
            'payment_id' => $payment->get_id(),
            'amount'     => $amount,
        ) );
    }

    /**
     * Handle failed payment
     *
     * @param Booking $booking Booking object
     * @param Payment $payment Payment object
     * @param array   $webhook_data Webhook data
     */
    private function handle_payment_failure( Booking $booking, Payment $payment, array $webhook_data ) {

        // Update payment status
        $payment->set_status( 'failed' );
        $payment->set_meta( 'payment_status', 'failed' );
        $payment->set_meta( 'maya_payment_id', $webhook_data['id'] ?? '' );
        $payment->set_meta( 'maya_webhook_data', $webhook_data );
        $payment->save();

        $this->log_debug( 'Payment failed', array(
            'booking_id' => $booking->get_id(),
            'payment_id' => $payment->get_id(),
            'status'     => $webhook_data['paymentStatus'] ?? 'unknown',
        ) );
    }

    /**
     * Get Maya gateway settings
     *
     * @return array Settings array
     */
    private function get_settings(): array {
        $settings = wptravelengine_settings()->get( 'maya', array() );

        return wp_parse_args(
            $settings,
            array(
                'public_key' => '',
                'test_mode'  => true,
                'enabled'    => false,
            )
        );
    }

    /**
     * Log debug message
     *
     * @param string $title Log title
     * @param mixed  $data Log data
     */
    private function log_debug( $title, $data ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( sprintf(
                '[WTE-Maya Debug] %s: %s',
                $title,
                print_r( $data, true )
            ) );
        }
    }
}
