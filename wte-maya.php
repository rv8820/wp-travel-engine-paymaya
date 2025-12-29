<?php
/**
 * Plugin Name: WTE-Maya
 * Plugin URI: https://github.com/rv8820/wp-travel-engine-paymaya
 * Description: Maya Payment Gateway integration for WP Travel Engine
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://github.com/rv8820
 * Text Domain: wte-maya
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'WTE_MAYA_VERSION', '1.0.0' );
define( 'WTE_MAYA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WTE_MAYA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WTE_MAYA_PLUGIN_FILE', __FILE__ );

/**
 * Main WTE Maya Plugin Class
 */
class WTE_Maya_Plugin {

    /**
     * Single instance of the class
     *
     * @var WTE_Maya_Plugin
     */
    private static $instance = null;

    /**
     * Get single instance
     *
     * @return WTE_Maya_Plugin
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
        $this->load_dependencies();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Load plugin text domain
        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

        // Check if WP Travel Engine is active
        add_action( 'admin_init', array( $this, 'check_dependencies' ) );

        // Register the payment gateway
        add_filter( 'wptravelengine_registering_payment_gateways', array( $this, 'register_gateway' ) );

        // Add settings tab to WP Travel Engine
        add_filter( 'wptravelengine_settings:tabs:payments', array( $this, 'register_settings_tab' ) );

        // Handle webhook callback
        add_action( 'init', array( $this, 'handle_webhook' ) );
    }

    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        require_once WTE_MAYA_PLUGIN_DIR . 'includes/class-maya-api-client.php';
        require_once WTE_MAYA_PLUGIN_DIR . 'includes/class-maya-gateway.php';

        // Load REST API integration if exists
        $api_file = WTE_MAYA_PLUGIN_DIR . 'includes/Builders/API.php';
        if ( file_exists( $api_file ) ) {
            require_once $api_file;
            new WTE_Maya_API();
        }
    }

    /**
     * Register Maya settings tab
     *
     * @param array $settings Existing settings tabs
     * @return array Modified settings tabs
     */
    public function register_settings_tab( $settings ) {
        $settings_file = WTE_MAYA_PLUGIN_DIR . 'includes/Builders/global-settings.php';
        if ( file_exists( $settings_file ) ) {
            $maya_settings = include $settings_file;
            if ( is_array( $maya_settings ) ) {
                $settings['maya_payment'] = $maya_settings;
            }
        }
        return $settings;
    }

    /**
     * Load plugin text domain for translations
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'wte-maya',
            false,
            dirname( plugin_basename( WTE_MAYA_PLUGIN_FILE ) ) . '/languages'
        );
    }

    /**
     * Check if WP Travel Engine is active
     */
    public function check_dependencies() {
        if ( ! class_exists( 'WP_Travel_Engine' ) ) {
            add_action( 'admin_notices', array( $this, 'wte_missing_notice' ) );
            deactivate_plugins( plugin_basename( WTE_MAYA_PLUGIN_FILE ) );
        }
    }

    /**
     * Display notice if WP Travel Engine is not active
     */
    public function wte_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <?php
                esc_html_e(
                    'WTE-Maya requires WP Travel Engine to be installed and activated.',
                    'wte-maya'
                );
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * Register Maya payment gateway
     *
     * @param array $payment_gateways Existing payment gateways
     * @return array Modified payment gateways array
     */
    public function register_gateway( $payment_gateways ) {
        $payment_gateways['maya'] = new WTE_Maya_Gateway();
        return $payment_gateways;
    }

    /**
     * Handle Maya webhook callbacks
     */
    public function handle_webhook() {
        // Check if this is a Maya webhook request
        if ( ! isset( $_GET['wte-maya-webhook'] ) ) {
            return;
        }

        // Get the payment gateway instance
        $gateway = new WTE_Maya_Gateway();
        $gateway->process_webhook();
    }
}

/**
 * Initialize the plugin
 */
function wte_maya_init() {
    return WTE_Maya_Plugin::instance();
}

// Start the plugin
wte_maya_init();
