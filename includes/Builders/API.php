<?php
namespace WPTravelEngineMaya\Builders;

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
        $schema['maya'] = array(
            'description' => __( 'Maya Payment Gateway Settings', 'wptravelengine-maya-payment' ),
            'type'        => 'object',
            'properties'  => array(
                'gateway_label' => array(
                    'type'        => 'string',
                    'description' => __( 'Gateway label displayed to users', 'wptravelengine-maya-payment' ),
                ),
                'description' => array(
                    'type'        => 'string',
                    'description' => __( 'Gateway description', 'wptravelengine-maya-payment' ),
                ),
                'instruction' => array(
                    'type'        => 'string',
                    'description' => __( 'Payment instructions', 'wptravelengine-maya-payment' ),
                ),
                'public_key' => array(
                    'type'        => 'string',
                    'description' => __( 'Maya Public API Key', 'wptravelengine-maya-payment' ),
                ),
                'test_mode' => array(
                    'type'        => 'boolean',
                    'description' => __( 'Enable test mode', 'wptravelengine-maya-payment' ),
                    'default'     => true,
                ),
            ),
        );

        $schema['maya_enable'] = array(
            'description' => __( 'Enable Maya Payment Gateway', 'wptravelengine-maya-payment' ),
            'type'        => 'boolean',
            'default'     => false,
        );

        return $schema;
    }

    /**
     * Prepare settings for REST response
     */
    public function prepare_settings( $settings, WP_REST_Request $request, $instance ) {
        $this->plugin_settings = $instance->plugin_settings;

        $settings['maya'] = array(
            'gateway_label' => $this->plugin_settings->get( 'maya.gateway_label', '' ),
            'description'   => $this->plugin_settings->get( 'maya.description', '' ),
            'instruction'   => $this->plugin_settings->get( 'maya.instruction', '' ),
            'public_key'    => $this->plugin_settings->get( 'maya.public_key', '' ),
            'test_mode'     => $this->plugin_settings->get( 'maya.test_mode', true ),
        );

        $settings['maya_enable'] = $this->plugin_settings->get( 'maya_enable', false );

        return $settings;
    }

    /**
     * Update settings via REST API
     */
    public function update_settings( $request, $instance ) {
        $this->plugin_settings = $instance->plugin_settings;

        // Handle maya_enable setting
        if ( isset( $request['maya_enable'] ) ) {
            $this->plugin_settings->set( 'maya_enable', (bool) $request['maya_enable'] );
        }

        // Handle maya settings group
        if ( isset( $request['maya'] ) ) {
            $maya = $request['maya'];

            if ( isset( $maya['gateway_label'] ) ) {
                $this->plugin_settings->set( 'maya.gateway_label', sanitize_text_field( $maya['gateway_label'] ) );
            }

            if ( isset( $maya['description'] ) ) {
                $this->plugin_settings->set( 'maya.description', sanitize_textarea_field( $maya['description'] ) );
            }

            if ( isset( $maya['instruction'] ) ) {
                $this->plugin_settings->set( 'maya.instruction', sanitize_textarea_field( $maya['instruction'] ) );
            }

            if ( isset( $maya['public_key'] ) ) {
                $this->plugin_settings->set( 'maya.public_key', sanitize_text_field( $maya['public_key'] ) );
            }

            if ( isset( $maya['test_mode'] ) ) {
                $this->plugin_settings->set( 'maya.test_mode', (bool) $maya['test_mode'] );
            }
        }

        $this->plugin_settings->save();
    }
}
