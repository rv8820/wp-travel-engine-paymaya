<?php
/**
 * Maya Payment Gateway - REST API Integration
 *
 * @package WTE_Maya
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * REST API Integration Class
 */
class WTE_Maya_API {

    /**
     * Plugin settings instance
     *
     * @var object
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
     *
     * @param array  $schema Settings schema
     * @param object $instance Settings instance
     * @return array Modified schema
     */
    public function add_settings_schema( array $schema, $instance ): array {
        $schema['maya'] = array(
            'description' => __( 'Maya Payment Gateway Settings', 'wte-maya' ),
            'type'        => 'object',
            'properties'  => array(
                'gateway_label' => array(
                    'type'        => 'string',
                    'description' => __( 'Gateway label displayed to users', 'wte-maya' ),
                ),
                'description' => array(
                    'type'        => 'string',
                    'description' => __( 'Gateway description', 'wte-maya' ),
                ),
                'instruction' => array(
                    'type'        => 'string',
                    'description' => __( 'Payment instructions', 'wte-maya' ),
                ),
                'public_key' => array(
                    'type'        => 'string',
                    'description' => __( 'Maya Public API Key', 'wte-maya' ),
                ),
                'test_mode' => array(
                    'type'        => 'boolean',
                    'description' => __( 'Enable test mode', 'wte-maya' ),
                    'default'     => true,
                ),
            ),
        );
        return $schema;
    }

    /**
     * Prepare settings for REST response
     *
     * @param array  $settings Current settings
     * @param object $request REST request
     * @param object $instance Settings instance
     * @return array Modified settings
     */
    public function prepare_settings( $settings, $request, $instance ) {
        $this->plugin_settings = $instance->plugin_settings;

        $settings['maya'] = array(
            'gateway_label' => $this->plugin_settings->get( 'maya.gateway_label', '' ),
            'description'   => $this->plugin_settings->get( 'maya.description', '' ),
            'instruction'   => $this->plugin_settings->get( 'maya.instruction', '' ),
            'public_key'    => $this->plugin_settings->get( 'maya.public_key', '' ),
            'test_mode'     => $this->plugin_settings->get( 'maya.test_mode', true ),
        );

        return $settings;
    }

    /**
     * Update settings via REST API
     *
     * @param object $request REST request
     * @param object $instance Settings instance
     */
    public function update_settings( $request, $instance ) {
        $this->plugin_settings = $instance->plugin_settings;

        if ( ! isset( $request['maya'] ) ) {
            return;
        }

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

        $this->plugin_settings->save();
    }
}
