<?php
namespace WPTravelEngineMaya;

use WPTravelEngineMaya\Builders\API;

/**
 * Main Plugin Class
 */
class Plugin {
    /**
     * Singleton instance
     */
    protected static $instance = null;

    /**
     * Get singleton instance
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
        $this->init_api();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Load text domain for translations
        add_action( 'init', array( $this, 'load_textdomain' ) );

        // Add settings tab to WP Travel Engine
        add_filter( 'wptravelengine_settings:tabs:payments', array( $this, 'add_global_settings' ) );

        // Register payment gateway
        add_action( 'wptravelengine_registering_payment_gateways', array( $this, 'register_payment_gateway' ) );

        // Add gateway to REST API list
        add_filter( 'wptravelengine_rest_payment_gateways', array( $this, 'maya_gateway_list' ), 10, 2 );
    }

    /**
     * Initialize REST API integration
     */
    private function init_api() {
        new API();
    }

    /**
     * Load plugin text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'wptravelengine-maya-payment',
            false,
            WPTRAVELENGINE_MAYA_BASE_NAME . '/languages'
        );
    }

    /**
     * Add global settings tab
     */
    public function add_global_settings( $settings ) {
        $settings['maya_payment'] = require_once WPTRAVELENGINE_MAYA_ABSPATH . 'includes/Builders/global-settings.php';
        return $settings;
    }

    /**
     * Register payment gateway with WP Travel Engine
     */
    public function register_payment_gateway( $gateways ) {
        $gateways['maya'] = new Payment();
        return $gateways;
    }

    /**
     * Add gateway to REST API payment gateways list
     */
    public function maya_gateway_list( $gateways, $plugin_settings ) {
        $gateways[] = array(
            'id'     => 'maya_enable',
            'name'   => __( 'Maya', 'wptravelengine-maya-payment' ),
            'enable' => wptravelengine_toggled( $plugin_settings->get( 'maya_enable' ) ),
            'icon'   => plugin_dir_url( WPTRAVELENGINE_MAYA_FILE_PATH ) . 'src/images/maya.png',
        );
        return $gateways;
    }
}
