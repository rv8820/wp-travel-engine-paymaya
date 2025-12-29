<?php
/**
 * Maya API Client
 *
 * Handles all communication with Maya Checkout API
 *
 * @package WTE_Maya
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Maya API Client Class
 */
class WTE_Maya_API_Client {

    /**
     * API public key
     *
     * @var string
     */
    private $public_key;

    /**
     * Test mode flag
     *
     * @var bool
     */
    private $test_mode;

    /**
     * API base URL
     *
     * @var string
     */
    private $api_url;

    /**
     * Constructor
     *
     * @param string $public_key Maya public API key
     * @param bool   $test_mode Whether to use sandbox mode
     */
    public function __construct( $public_key, $test_mode = true ) {
        $this->public_key = $public_key;
        $this->test_mode = $test_mode;
        $this->api_url = $test_mode
            ? 'https://pg-sandbox.paymaya.com'
            : 'https://pg.maya.ph';
    }

    /**
     * Create a checkout session
     *
     * @param array $checkout_data Checkout data
     * @return array|WP_Error Response data or error
     */
    public function create_checkout( $checkout_data ) {
        $endpoint = '/checkout/v1/checkouts';

        $response = $this->make_request( 'POST', $endpoint, $checkout_data );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        return $response;
    }

    /**
     * Make API request to Maya
     *
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $endpoint API endpoint
     * @param array  $data Request data
     * @return array|WP_Error Response data or error
     */
    private function make_request( $method, $endpoint, $data = array() ) {
        $url = $this->api_url . $endpoint;

        // Prepare authentication header
        $auth_string = base64_encode( $this->public_key . ':' );

        $args = array(
            'method'  => $method,
            'headers' => array(
                'Authorization' => 'Basic ' . $auth_string,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ),
            'timeout' => 30,
        );

        // Add body for POST requests
        if ( 'POST' === $method && ! empty( $data ) ) {
            $args['body'] = wp_json_encode( $data );
        }

        // Make the request
        $response = wp_remote_request( $url, $args );

        // Check for errors
        if ( is_wp_error( $response ) ) {
            $this->log_error( 'HTTP Error', $response->get_error_message() );
            return $response;
        }

        // Get response code and body
        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );

        // Log the response for debugging
        $this->log_debug( 'API Response', array(
            'code' => $response_code,
            'body' => $response_body,
        ) );

        // Decode JSON response
        $decoded_response = json_decode( $response_body, true );

        // Check for successful response
        if ( $response_code < 200 || $response_code >= 300 ) {
            $error_message = isset( $decoded_response['message'] )
                ? $decoded_response['message']
                : 'Unknown API error';

            $this->log_error( 'API Error', array(
                'code'    => $response_code,
                'message' => $error_message,
                'body'    => $decoded_response,
            ) );

            return new WP_Error(
                'maya_api_error',
                $error_message,
                array( 'status' => $response_code )
            );
        }

        return $decoded_response;
    }

    /**
     * Build checkout data for Maya API
     *
     * @param array $args Checkout arguments
     * @return array Formatted checkout data
     */
    public function build_checkout_data( $args ) {
        $defaults = array(
            'amount'                   => 0,
            'currency'                 => 'PHP',
            'items'                    => array(),
            'buyer'                    => array(),
            'request_reference_number' => '',
            'success_url'              => '',
            'failure_url'              => '',
            'cancel_url'               => '',
        );

        $args = wp_parse_args( $args, $defaults );

        $checkout_data = array(
            'totalAmount' => array(
                'value'    => (float) $args['amount'],
                'currency' => $args['currency'],
            ),
            'items' => $args['items'],
            'redirectUrl' => array(
                'success' => $args['success_url'],
                'failure' => $args['failure_url'],
                'cancel'  => $args['cancel_url'],
            ),
            'requestReferenceNumber' => $args['request_reference_number'],
        );

        // Add buyer information if provided
        if ( ! empty( $args['buyer'] ) ) {
            $checkout_data['buyer'] = $args['buyer'];
        }

        return $checkout_data;
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

    /**
     * Log error message
     *
     * @param string $title Error title
     * @param mixed  $data Error data
     */
    private function log_error( $title, $data ) {
        error_log( sprintf(
            '[WTE-Maya Error] %s: %s',
            $title,
            print_r( $data, true )
        ) );
    }
}
