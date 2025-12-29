<?php
/**
 * Maya Settings Class
 *
 * Handles admin settings for Maya payment gateway
 *
 * @package WTE_Maya
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Maya Settings Class
 */
class WTE_Maya_Settings {

    /**
     * Constructor
     */
    public function __construct() {
        add_filter( 'wptravelengine_settings_fields', array( $this, 'add_settings_fields' ) );
    }

    /**
     * Add Maya settings fields to WP Travel Engine settings
     *
     * @param array $fields Existing settings fields
     * @return array Modified settings fields
     */
    public function add_settings_fields( $fields ) {

        // Check if payment settings section exists
        if ( ! isset( $fields['payment'] ) ) {
            return $fields;
        }

        // Add Maya gateway settings
        $fields['payment']['maya'] = array(
            'label'  => __( 'Maya Payment Gateway', 'wte-maya' ),
            'fields' => array(
                'enabled' => array(
                    'type'    => 'checkbox',
                    'label'   => __( 'Enable Maya Gateway', 'wte-maya' ),
                    'default' => false,
                ),
                'title' => array(
                    'type'        => 'text',
                    'label'       => __( 'Title', 'wte-maya' ),
                    'description' => __( 'This controls the title which the user sees during checkout.', 'wte-maya' ),
                    'default'     => __( 'Maya Payment Gateway', 'wte-maya' ),
                ),
                'description' => array(
                    'type'        => 'textarea',
                    'label'       => __( 'Description', 'wte-maya' ),
                    'description' => __( 'This controls the description which the user sees during checkout.', 'wte-maya' ),
                    'default'     => __( 'Pay securely using credit/debit cards, QRPh, Maya Wallet, and other payment methods.', 'wte-maya' ),
                ),
                'instruction' => array(
                    'type'        => 'textarea',
                    'label'       => __( 'Instructions', 'wte-maya' ),
                    'description' => __( 'Instructions that will be displayed at checkout.', 'wte-maya' ),
                    'default'     => __( 'You will be redirected to Maya to complete your payment securely.', 'wte-maya' ),
                ),
                'test_mode' => array(
                    'type'        => 'checkbox',
                    'label'       => __( 'Enable Test Mode', 'wte-maya' ),
                    'description' => __( 'Enable this to test payments using Maya Sandbox environment.', 'wte-maya' ),
                    'default'     => true,
                ),
                'public_key' => array(
                    'type'        => 'text',
                    'label'       => __( 'Public API Key', 'wte-maya' ),
                    'description' => __( 'Enter your Maya Public API Key (starts with pk-).', 'wte-maya' ),
                    'default'     => '',
                    'attributes'  => array(
                        'placeholder' => 'pk-...',
                    ),
                ),
            ),
        );

        return $fields;
    }
}

// Initialize settings
new WTE_Maya_Settings();
