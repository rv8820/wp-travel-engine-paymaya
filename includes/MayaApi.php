<?php
namespace WPTravelEngineMaya;

/**
 * Maya API Client
 */
class MayaApi {
    /**
     * API public key
     */
    private $public_key;

    /**
     * Test mode flag
     */
    private $test_mode;

    /**
     * API base URL
     */
    private $api_url;

    /**
     * Constructor
     */
    public function __construct( $settings ) {
        $this->public_key = $settings['public_key'] ?? '';
        $this->test_mode = wptravelengine_toggled( $settings['test_mode'] ?? false );
        $this->api_url = $this->test_mode
            ? 'https://pg-sandbox.paymaya.com'
            : 'https://pg.maya.ph';
    }

    /**
     * Create checkout session
     */
    public function create_checkout( $data ) {
        $endpoint = '/checkout/v1/checkouts';
        return $this->make_request( 'POST', $endpoint, $data );
    }

    /**
     * Make API request
     */
    private function make_request( $method, $endpoint, $data = array() ) {
        $url = $this->api_url . $endpoint;
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

        if ( 'POST' === $method && ! empty( $data ) ) {
            $args['body'] = wp_json_encode( $data );
            error_log( '[Maya API Request] URL: ' . $url . ', Data: ' . wp_json_encode( $data ) );
        }

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            error_log( '[Maya API Error] ' . $response->get_error_message() );
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        $decoded_response = json_decode( $response_body, true );

        if ( $response_code < 200 || $response_code >= 300 ) {
            $error_message = $decoded_response['message'] ?? 'Unknown API error';
            $error_details = wp_json_encode( $decoded_response );
            error_log( '[Maya API Error] Code: ' . $response_code . ', Response: ' . $error_details );

            // Return detailed error message
            $display_message = $error_message;
            if ( ! empty( $decoded_response['errors'] ) ) {
                $display_message .= ' - ' . wp_json_encode( $decoded_response['errors'] );
            }

            return new \WP_Error( 'maya_api_error', $display_message, array( 'status' => $response_code ) );
        }

        return $decoded_response;
    }
}
