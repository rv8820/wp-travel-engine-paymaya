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

        // Add debug admin page
        add_action( 'admin_menu', array( $this, 'add_debug_page' ) );
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
                $settings['maya'] = $maya_settings;
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
     * Add debug admin page
     */
    public function add_debug_page() {
        add_submenu_page(
            null, // No parent menu - hidden from menu
            __( 'Maya Gateway Debug', 'wte-maya' ),
            __( 'Maya Debug', 'wte-maya' ),
            'manage_options',
            'wte-maya-debug',
            array( $this, 'render_debug_page' )
        );
    }

    /**
     * Render debug page
     */
    public function render_debug_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'wte-maya' ) );
        }

        echo '<div class="wrap">';
        echo '<h1>WTE-Maya Gateway Debug</h1>';

        // Check if WP Travel Engine is active
        echo '<h2>1. WP Travel Engine Status</h2>';
        if ( class_exists( 'WP_Travel_Engine' ) ) {
            echo '<p>✅ WP Travel Engine is active</p>';
        } else {
            echo '<p>❌ WP Travel Engine is NOT active - Plugin will not work!</p>';
        }

        // Check if BaseGateway class exists
        echo '<h2>2. BaseGateway Class</h2>';
        if ( class_exists( 'WPTravelEngine\\PaymentGateways\\BaseGateway' ) ) {
            echo '<p>✅ BaseGateway class exists</p>';
        } else {
            echo '<p>❌ BaseGateway class NOT found - WTE version might be too old</p>';
        }

        // Check if our classes are loaded
        echo '<h2>3. WTE-Maya Classes</h2>';
        echo '<p>WTE_Maya_Plugin: ' . ( class_exists( 'WTE_Maya_Plugin' ) ? '✅' : '❌' ) . '</p>';
        echo '<p>WTE_Maya_Gateway: ' . ( class_exists( 'WTE_Maya_Gateway' ) ? '✅' : '❌' ) . '</p>';
        echo '<p>WTE_Maya_API_Client: ' . ( class_exists( 'WTE_Maya_API_Client' ) ? '✅' : '❌' ) . '</p>';

        // Check registered payment gateways
        echo '<h2>4. Registered Payment Gateways</h2>';
        if ( function_exists( 'wptravelengine_payment_gateways' ) ) {
            $gateways = wptravelengine_payment_gateways();
            echo '<p>Found ' . count( $gateways ) . ' gateways:</p>';
            echo '<ul>';
            foreach ( $gateways as $id => $gateway ) {
                $class = get_class( $gateway );
                echo "<li><strong>$id</strong>: $class";
                if ( $id === 'maya' ) {
                    echo ' ⭐ <strong>FOUND!</strong>';
                }
                echo '</li>';
            }
            echo '</ul>';

            if ( isset( $gateways['maya'] ) ) {
                echo '<p>✅ Maya gateway is registered!</p>';

                // Get gateway details
                $maya_gateway = $gateways['maya'];
                echo '<h3>Maya Gateway Details:</h3>';
                echo '<ul>';
                echo '<li>Gateway ID: ' . $maya_gateway->get_gateway_id() . '</li>';
                echo '<li>Label: ' . $maya_gateway->get_label() . '</li>';
                echo '<li>Public Label: ' . $maya_gateway->get_public_label() . '</li>';
                echo '</ul>';
            } else {
                echo '<p>❌ Maya gateway is NOT registered</p>';
            }
        } else {
            echo '<p>❌ wptravelengine_payment_gateways() function not found</p>';
        }

        // Check WTE version
        echo '<h2>5. WP Travel Engine Version</h2>';
        if ( defined( 'WP_TRAVEL_ENGINE_VERSION' ) ) {
            echo '<p>Version: ' . WP_TRAVEL_ENGINE_VERSION . '</p>';
        } else {
            echo '<p>Version: Unknown</p>';
        }

        // Check settings tabs
        echo '<h2>6. Settings Tabs Registration</h2>';
        $settings_tabs_filter = 'wptravelengine_settings:tabs:payments';
        echo '<p>Filter being used: <code>' . $settings_tabs_filter . '</code></p>';
        $test_tabs = apply_filters( $settings_tabs_filter, array() );
        echo '<p>Tabs registered: ' . count( $test_tabs ) . '</p>';
        if ( ! empty( $test_tabs ) ) {
            echo '<p>Tab keys:</p><ul>';
            foreach ( array_keys( $test_tabs ) as $tab_key ) {
                echo '<li>' . esc_html( $tab_key );
                if ( $tab_key === 'maya' ) {
                    echo ' ⭐ <strong>FOUND!</strong>';
                }
                echo '</li>';
            }
            echo '</ul>';
        }

        // Check if settings file exists
        echo '<h2>7. Settings File</h2>';
        $settings_file = WTE_MAYA_PLUGIN_DIR . 'includes/Builders/global-settings.php';
        echo '<p>Settings file path: <code>' . esc_html( $settings_file ) . '</code></p>';
        echo '<p>File exists: ' . ( file_exists( $settings_file ) ? '✅ Yes' : '❌ No' ) . '</p>';
        if ( file_exists( $settings_file ) ) {
            $settings_data = include $settings_file;
            echo '<p>Settings data type: ' . gettype( $settings_data ) . '</p>';
            if ( is_array( $settings_data ) ) {
                echo '<p>Settings ID: ' . ( $settings_data['id'] ?? 'not set' ) . '</p>';
                echo '<p>Settings title: ' . ( $settings_data['title'] ?? 'not set' ) . '</p>';
                echo '<p>Number of fields: ' . ( isset( $settings_data['fields'] ) ? count( $settings_data['fields'] ) : 0 ) . '</p>';
            }
        }

        // Plugin file path
        echo '<h2>8. Plugin File Paths</h2>';
        if ( defined( 'WTE_MAYA_PLUGIN_DIR' ) ) {
            echo '<p>Plugin Dir: ' . WTE_MAYA_PLUGIN_DIR . '</p>';
            echo '<p>Exists: ' . ( file_exists( WTE_MAYA_PLUGIN_DIR ) ? '✅ Yes' : '❌ No' ) . '</p>';
        } else {
            echo '<p>❌ WTE_MAYA_PLUGIN_DIR not defined</p>';
        }

        echo '</div>';
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
