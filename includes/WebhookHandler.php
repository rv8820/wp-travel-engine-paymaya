<?php
namespace WPTravelEngineMaya;

/**
 * Webhook Handler
 * Handles incoming Maya webhook notifications
 */
class WebhookHandler {
    /**
     * Initialize webhook handler
     */
    public static function init() {
        add_action( 'rest_api_init', array( __CLASS__, 'register_webhook_endpoint' ) );
    }

    /**
     * Register webhook REST API endpoint
     */
    public static function register_webhook_endpoint() {
        register_rest_route( 'wte-maya/v1', '/webhook', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'handle_webhook' ),
            'permission_callback' => '__return_true', // Maya webhooks don't use WP auth
        ) );
    }

    /**
     * Handle incoming webhook
     */
    public static function handle_webhook( $request ) {
        try {
            // Log webhook for debugging
            error_log( '[Maya Webhook] Received: ' . wp_json_encode( $request->get_json_params() ) );

            // Get webhook data
            $webhook_data = $request->get_json_params();

            if ( empty( $webhook_data ) ) {
                $webhook_data = $request->get_body_params();
            }

            // Validate webhook data
            if ( empty( $webhook_data['id'] ) ) {
                error_log( '[Maya Webhook] Error: Missing transaction ID' );
                return new \WP_REST_Response( array(
                    'success' => false,
                    'message' => 'Invalid webhook data - missing transaction ID',
                ), 400 );
            }

            // Get transaction/checkout ID
            $transaction_id = sanitize_text_field( $webhook_data['id'] );
            $status = sanitize_text_field( $webhook_data['status'] ?? '' );

            error_log( '[Maya Webhook] Processing - ID: ' . $transaction_id . ', Status: ' . $status );

            // Find payment by Maya checkout ID
            $payment = self::find_payment_by_checkout_id( $transaction_id );

            if ( ! $payment ) {
                // Transaction ID might be in requestReferenceNumber
                $ref_number = sanitize_text_field( $webhook_data['requestReferenceNumber'] ?? '' );
                if ( $ref_number ) {
                    error_log( '[Maya Webhook] Trying to find by reference: ' . $ref_number );
                    $payment = self::find_payment_by_reference( $ref_number );
                }
            }

            if ( ! $payment ) {
                error_log( '[Maya Webhook] Payment not found for transaction: ' . $transaction_id );
                // Return 200 for test webhooks to acknowledge receipt
                return new \WP_REST_Response( array(
                    'success' => true,
                    'message' => 'Webhook received but payment not found (likely a test webhook)',
                    'transaction_id' => $transaction_id,
                ), 200 );
            }

            // Check if already processed
            $processed_key = 'maya_webhook_processed_' . $transaction_id;
            if ( get_transient( $processed_key ) ) {
                error_log( '[Maya Webhook] Already processed: ' . $transaction_id );
                return new \WP_REST_Response( array(
                    'success' => true,
                    'message' => 'Webhook already processed',
                ), 200 );
            }

            // Determine payment status
            $is_successful = self::is_successful_status( $status );
            $is_pending = self::is_pending_status( $status );

            // Update payment status
            if ( $is_successful ) {
                $payment->set_status( 'publish' );
                $payment->set_meta( 'payment_status', 'completed' );
                error_log( '[Maya Webhook] Payment marked as completed: ' . $payment->get_id() );

                // Update booking status
                $booking_id = $payment->get_meta( 'booking_id' );
                if ( $booking_id && is_numeric( $booking_id ) && $booking_id > 0 ) {
                    // Verify booking exists
                    $booking_post = get_post( $booking_id );
                    if ( $booking_post && $booking_post->post_type === 'booking' ) {
                        wp_update_post( array(
                            'ID' => $booking_id,
                            'post_status' => 'publish',
                        ) );
                        update_post_meta( $booking_id, 'wp_travel_engine_booking_status', 'booked' );
                        update_post_meta( $booking_id, 'wp_travel_engine_booking_payment_status', 'paid' );
                        error_log( '[Maya Webhook] Booking status updated to booked: ' . $booking_id );
                    } else {
                        error_log( '[Maya Webhook] Invalid booking ID: ' . $booking_id );
                    }
                }
            } elseif ( $is_pending ) {
                $payment->set_status( 'pending' );
                $payment->set_meta( 'payment_status', 'pending' );
                error_log( '[Maya Webhook] Payment marked as pending: ' . $payment->get_id() );
            } else {
                $payment->set_status( 'failed' );
                $payment->set_meta( 'payment_status', 'failed' );
                error_log( '[Maya Webhook] Payment marked as failed: ' . $payment->get_id() );
            }

            // Save webhook data
            $payment->set_meta( 'maya_transaction_id', $transaction_id );
            $payment->set_meta( 'maya_webhook_data', $webhook_data );
            $payment->set_meta( 'maya_webhook_received_at', current_time( 'mysql' ) );
            $payment->save();

            // Mark as processed
            set_transient( $processed_key, true, HOUR_IN_SECONDS );

            // Get booking
            $booking_id = $payment->get_meta( 'booking_id' );
            if ( $booking_id && $is_successful ) {
                // Send booking confirmation emails
                do_action( 'wptravelengine_after_booking_process_completed', $booking_id );
                error_log( '[Maya Webhook] Triggered booking confirmation for booking: ' . $booking_id );
            }

            return new \WP_REST_Response( array(
                'success' => true,
                'message' => 'Webhook processed successfully',
                'status'  => $status,
                'payment_id' => $payment->get_id(),
            ), 200 );

        } catch ( \Exception $e ) {
            error_log( '[Maya Webhook] Exception: ' . $e->getMessage() );
            error_log( '[Maya Webhook] Trace: ' . $e->getTraceAsString() );

            return new \WP_REST_Response( array(
                'success' => false,
                'message' => 'Webhook processing error: ' . $e->getMessage(),
            ), 500 );
        }
    }

    /**
     * Find payment by Maya checkout ID
     */
    private static function find_payment_by_checkout_id( $checkout_id ) {
        global $wpdb;

        $payment_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta}
            WHERE meta_key = 'maya_checkout_id'
            AND meta_value = %s
            LIMIT 1",
            $checkout_id
        ) );

        if ( $payment_id ) {
            return new \WPTravelEngine\Core\Models\Post\Payment( $payment_id );
        }

        return null;
    }

    /**
     * Find payment by reference number
     */
    private static function find_payment_by_reference( $ref_number ) {
        global $wpdb;

        // Reference number format: {booking_id}-{payment_id}-{timestamp}
        // Skip if it looks like a UUID (has non-numeric parts)
        if ( ! preg_match( '/^\d+-\d+-\d+$/', $ref_number ) ) {
            return null;
        }

        $parts = explode( '-', $ref_number );
        if ( count( $parts ) >= 2 ) {
            $payment_id = intval( $parts[1] );
            if ( $payment_id > 0 ) {
                $payment = new \WPTravelEngine\Core\Models\Post\Payment( $payment_id );
                // Validate payment exists
                if ( $payment && $payment->get_id() > 0 ) {
                    return $payment;
                }
            }
        }

        return null;
    }

    /**
     * Check if status is successful
     */
    private static function is_successful_status( $status ) {
        $successful_statuses = array(
            'PAYMENT_SUCCESS',
            'COMPLETED',
            'SUCCESS',
            'AUTHORIZED',
        );
        return in_array( strtoupper( $status ), $successful_statuses, true );
    }

    /**
     * Check if status is pending
     */
    private static function is_pending_status( $status ) {
        $pending_statuses = array(
            'PAYMENT_PROCESSING',
            'PENDING',
            'FOR_AUTHENTICATION',
        );
        return in_array( strtoupper( $status ), $pending_statuses, true );
    }
}
