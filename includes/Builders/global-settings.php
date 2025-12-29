<?php
/**
 * Maya Payment Gateway - Global Settings Configuration
 *
 * @package WTE_Maya
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get webhook URL for display
$webhook_url = add_query_arg(
    array(
        'wte-maya-webhook' => '1',
        'payment_key'      => '{payment_key}',
    ),
    home_url( '/' )
);

return array(
    'title'  => __( 'Maya', 'wte-maya' ),
    'order'  => 10,
    'id'     => 'maya_payment',
    'fields' => array(

        // Gateway Label
        array(
            'field_type'  => 'TEXT',
            'name'        => 'maya.gateway_label',
            'label'       => __( 'Gateway Label', 'wte-maya' ),
            'placeholder' => __( 'Maya Payment Gateway', 'wte-maya' ),
            'default'     => __( 'Maya', 'wte-maya' ),
            'help'        => __( 'This is the name shown to customers at checkout.', 'wte-maya' ),
        ),

        // Description
        array(
            'field_type'  => 'TEXTAREA',
            'name'        => 'maya.description',
            'label'       => __( 'Description', 'wte-maya' ),
            'placeholder' => __( 'Pay securely using Maya', 'wte-maya' ),
            'default'     => __( 'Secure payment processing through Maya Payment Gateway. Accept credit cards, debit cards, QRPh, and Maya Wallet.', 'wte-maya' ),
            'help'        => __( 'Additional description shown to customers during checkout.', 'wte-maya' ),
        ),

        // Instructions
        array(
            'field_type'  => 'TEXTAREA',
            'name'        => 'maya.instruction',
            'label'       => __( 'Instructions', 'wte-maya' ),
            'placeholder' => __( 'You will be redirected to Maya...', 'wte-maya' ),
            'default'     => __( 'You will be redirected to Maya to complete your payment securely. You can pay using credit/debit cards, QRPh, Maya Wallet, and other payment methods.', 'wte-maya' ),
            'help'        => __( 'Instructions displayed at checkout before payment.', 'wte-maya' ),
        ),

        // Webhook URL Instructions
        array(
            'field_type' => 'ALERT',
            'type'       => 'info',
            'name'       => 'maya_webhook_instructions',
            'content'    => sprintf(
                __( 'Configure this webhook URL in your Maya Manager account to receive payment notifications: %s', 'wte-maya' ),
                '<br><code>' . esc_html( $webhook_url ) . '</code>'
            ),
        ),

        // Webhook URL (Copy)
        array(
            'field_type' => 'COPY_CODE',
            'label'      => __( 'Webhook URL', 'wte-maya' ),
            'code'       => $webhook_url,
        ),

        // Test Mode
        array(
            'field_type' => 'TOGGLE',
            'name'       => 'maya.test_mode',
            'label'      => __( 'Enable Test Mode', 'wte-maya' ),
            'default'    => true,
            'help'       => __( 'Enable this to use Maya Sandbox environment for testing. Disable for live payments.', 'wte-maya' ),
        ),

        // Sandbox API Alert
        array(
            'field_type'  => 'ALERT',
            'type'        => 'warning',
            'name'        => 'maya_sandbox_alert',
            'content'     => __( '<strong>Sandbox Mode:</strong> When test mode is enabled, use your Sandbox Public API Key. Get one from <a href="https://manager-sandbox.paymaya.com" target="_blank">Maya Manager Sandbox</a>.', 'wte-maya' ),
            'conditional' => array(
                'field' => 'maya.test_mode',
                'value' => true,
            ),
        ),

        // Production API Alert
        array(
            'field_type'  => 'ALERT',
            'type'        => 'danger',
            'name'        => 'maya_production_alert',
            'content'     => __( '<strong>Production Mode:</strong> You are in LIVE mode. Use your Production Public API Key. Real charges will be processed!', 'wte-maya' ),
            'conditional' => array(
                'field' => 'maya.test_mode',
                'value' => false,
            ),
        ),

        // Public API Key
        array(
            'field_type'  => 'TEXT',
            'name'        => 'maya.public_key',
            'label'       => __( 'Public API Key', 'wte-maya' ),
            'placeholder' => __( 'pk-...', 'wte-maya' ),
            'help'        => sprintf(
                __( 'Enter your Maya Public API Key (starts with pk-). Get your keys from %s', 'wte-maya' ),
                '<a href="https://manager.paymaya.com" target="_blank">Maya Manager</a>'
            ),
        ),

        // API Key Help
        array(
            'field_type' => 'ALERT',
            'type'       => 'info',
            'name'       => 'maya_api_key_help',
            'content'    => sprintf(
                __( '<strong>How to get API Keys:</strong><br>
                    • <strong>Sandbox:</strong> Login to <a href="https://manager-sandbox.paymaya.com" target="_blank">Maya Manager Sandbox</a><br>
                    • <strong>Production:</strong> Contact Maya at business.signup@maya.ph or your Relationship Manager<br>
                    • Navigate to Developer → API Keys<br>
                    • Copy your Public API Key (pk-...)<br>
                    • See <a href="https://developers.maya.ph/docs/maya-checkout" target="_blank">Maya Documentation</a> for more details.', 'wte-maya' )
            ),
        ),

        // Supported Payment Methods
        array(
            'field_type' => 'ALERT',
            'type'       => 'success',
            'name'       => 'maya_payment_methods',
            'content'    => __( '<strong>Supported Payment Methods:</strong><br>
                ✓ Credit Cards (Visa, Mastercard, JCB, Amex)<br>
                ✓ Debit Cards<br>
                ✓ QRPh (InstaPay)<br>
                ✓ Maya Wallet<br>
                ✓ Other payment channels (based on your merchant setup)', 'wte-maya' ),
        ),

        // Debug Mode
        array(
            'field_type' => 'DEBUG_MODE',
            'name'       => 'debug_mode',
        ),
    ),
);
