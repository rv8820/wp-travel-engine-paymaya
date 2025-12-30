<?php
namespace WPTravelEngineMaya;

/**
 * Admin Settings Page
 * Traditional WordPress settings page for Maya configuration
 */
class AdminSettings {
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
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 100 );
        add_action( 'admin_init', array( $this, 'handle_form_submission' ) );
        add_action( 'admin_notices', array( $this, 'show_notices' ) );
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=booking',
            __( 'Maya Payment Settings', 'wptravelengine-maya-payment' ),
            __( 'Maya Payment', 'wptravelengine-maya-payment' ),
            'manage_options',
            'wte-maya-settings',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Handle form submission
     */
    public function handle_form_submission() {
        // Check if our form was submitted
        if ( ! isset( $_POST['wte_maya_save_settings'] ) ) {
            return;
        }

        // Verify nonce
        if ( ! isset( $_POST['wte_maya_nonce'] ) || ! wp_verify_nonce( $_POST['wte_maya_nonce'], 'wte_maya_settings' ) ) {
            return;
        }

        // Check user permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Get WP Travel Engine settings object
        $settings = wptravelengine_settings();

        // Save maya_enable
        $maya_enable = isset( $_POST['maya_enable'] ) ? true : false;
        $settings->set( 'maya_enable', $maya_enable );

        // Save gateway label
        if ( isset( $_POST['gateway_label'] ) ) {
            $settings->set( 'maya.gateway_label', sanitize_text_field( $_POST['gateway_label'] ) );
        }

        // Save description
        if ( isset( $_POST['description'] ) ) {
            $settings->set( 'maya.description', sanitize_textarea_field( $_POST['description'] ) );
        }

        // Save instruction
        if ( isset( $_POST['instruction'] ) ) {
            $settings->set( 'maya.instruction', sanitize_textarea_field( $_POST['instruction'] ) );
        }

        // Save public key
        if ( isset( $_POST['public_key'] ) ) {
            $settings->set( 'maya.public_key', sanitize_text_field( $_POST['public_key'] ) );
        }

        // Save test mode
        $test_mode = isset( $_POST['test_mode'] ) ? true : false;
        $settings->set( 'maya.test_mode', $test_mode );

        // Save all settings
        $settings->save();

        // Set transient for success message
        set_transient( 'wte_maya_settings_saved', true, 30 );

        // Redirect to avoid form resubmission
        wp_redirect( add_query_arg( 'settings-updated', 'true', wp_get_referer() ) );
        exit;
    }

    /**
     * Show admin notices
     */
    public function show_notices() {
        if ( get_transient( 'wte_maya_settings_saved' ) ) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e( 'Maya Payment settings saved successfully!', 'wptravelengine-maya-payment' ); ?></p>
            </div>
            <?php
            delete_transient( 'wte_maya_settings_saved' );
        }
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        // Get current settings
        $settings = wptravelengine_settings()->get();
        $maya_enable = $settings['maya_enable'] ?? false;
        $gateway_label = $settings['maya']['gateway_label'] ?? 'Maya';
        $description = $settings['maya']['description'] ?? '';
        $instruction = $settings['maya']['instruction'] ?? '';
        $public_key = $settings['maya']['public_key'] ?? '';
        $test_mode = $settings['maya']['test_mode'] ?? true;

        // Webhook URL
        $webhook_url = add_query_arg(
            array(
                'payment_key' => '{payment_key}',
                'callback_type' => 'notification',
            ),
            home_url( '/' )
        );

        ?>
        <div class="wrap">
            <h1><?php _e( 'Maya Payment Gateway Settings', 'wptravelengine-maya-payment' ); ?></h1>

            <form method="post" action="">
                <?php wp_nonce_field( 'wte_maya_settings', 'wte_maya_nonce' ); ?>
                <input type="hidden" name="wte_maya_save_settings" value="1">

                <table class="form-table">
                    <!-- Enable/Disable -->
                    <tr>
                        <th scope="row">
                            <label for="maya_enable"><?php _e( 'Enable Maya Payment Gateway', 'wptravelengine-maya-payment' ); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="maya_enable" id="maya_enable" value="1" <?php checked( $maya_enable, true ); ?>>
                                <?php _e( 'Enable Maya as a payment option', 'wptravelengine-maya-payment' ); ?>
                            </label>
                        </td>
                    </tr>

                    <!-- Gateway Label -->
                    <tr>
                        <th scope="row">
                            <label for="gateway_label"><?php _e( 'Gateway Label', 'wptravelengine-maya-payment' ); ?></label>
                        </th>
                        <td>
                            <input type="text" name="gateway_label" id="gateway_label" value="<?php echo esc_attr( $gateway_label ); ?>" class="regular-text">
                            <p class="description"><?php _e( 'This is the name shown to customers at checkout.', 'wptravelengine-maya-payment' ); ?></p>
                        </td>
                    </tr>

                    <!-- Description -->
                    <tr>
                        <th scope="row">
                            <label for="description"><?php _e( 'Description', 'wptravelengine-maya-payment' ); ?></label>
                        </th>
                        <td>
                            <textarea name="description" id="description" rows="3" class="large-text"><?php echo esc_textarea( $description ); ?></textarea>
                            <p class="description"><?php _e( 'Additional description shown to customers during checkout.', 'wptravelengine-maya-payment' ); ?></p>
                        </td>
                    </tr>

                    <!-- Instructions -->
                    <tr>
                        <th scope="row">
                            <label for="instruction"><?php _e( 'Instructions', 'wptravelengine-maya-payment' ); ?></label>
                        </th>
                        <td>
                            <textarea name="instruction" id="instruction" rows="3" class="large-text"><?php echo esc_textarea( $instruction ); ?></textarea>
                            <p class="description"><?php _e( 'Instructions displayed at checkout before payment.', 'wptravelengine-maya-payment' ); ?></p>
                        </td>
                    </tr>

                    <!-- Test Mode -->
                    <tr>
                        <th scope="row">
                            <label for="test_mode"><?php _e( 'Test Mode', 'wptravelengine-maya-payment' ); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="test_mode" id="test_mode" value="1" <?php checked( $test_mode, true ); ?>>
                                <?php _e( 'Enable test mode (use Sandbox environment)', 'wptravelengine-maya-payment' ); ?>
                            </label>
                            <p class="description">
                                <?php if ( $test_mode ) : ?>
                                    <strong style="color: #d63638;"><?php _e( 'Sandbox Mode:', 'wptravelengine-maya-payment' ); ?></strong>
                                    <?php _e( 'Use your Sandbox Public API Key from', 'wptravelengine-maya-payment' ); ?>
                                    <a href="https://manager-sandbox.paymaya.com" target="_blank">Maya Manager Sandbox</a>
                                <?php else : ?>
                                    <strong style="color: #d63638;"><?php _e( 'Production Mode:', 'wptravelengine-maya-payment' ); ?></strong>
                                    <?php _e( 'Real charges will be processed!', 'wptravelengine-maya-payment' ); ?>
                                <?php endif; ?>
                            </p>
                        </td>
                    </tr>

                    <!-- Public API Key -->
                    <tr>
                        <th scope="row">
                            <label for="public_key"><?php _e( 'Public API Key', 'wptravelengine-maya-payment' ); ?></label>
                        </th>
                        <td>
                            <input type="text" name="public_key" id="public_key" value="<?php echo esc_attr( $public_key ); ?>" class="large-text" placeholder="pk-...">
                            <p class="description">
                                <?php _e( 'Enter your Maya Public API Key (starts with pk-).', 'wptravelengine-maya-payment' ); ?>
                                <?php _e( 'Get your keys from', 'wptravelengine-maya-payment' ); ?>
                                <a href="https://manager.paymaya.com" target="_blank">Maya Manager</a>
                            </p>
                        </td>
                    </tr>

                    <!-- Webhook URL -->
                    <tr>
                        <th scope="row">
                            <label><?php _e( 'Webhook URL', 'wptravelengine-maya-payment' ); ?></label>
                        </th>
                        <td>
                            <input type="text" value="<?php echo esc_attr( $webhook_url ); ?>" class="large-text" readonly onclick="this.select();">
                            <p class="description">
                                <?php _e( 'Configure this URL in your Maya Manager account to receive payment notifications.', 'wptravelengine-maya-payment' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <!-- Info Boxes -->
                <div style="background: #f0f6fc; border-left: 4px solid #2271b1; padding: 12px; margin: 20px 0;">
                    <h3 style="margin-top: 0;"><?php _e( 'How to get API Keys', 'wptravelengine-maya-payment' ); ?></h3>
                    <ul>
                        <li><strong><?php _e( 'Sandbox:', 'wptravelengine-maya-payment' ); ?></strong> <?php _e( 'Login to', 'wptravelengine-maya-payment' ); ?> <a href="https://manager-sandbox.paymaya.com" target="_blank">Maya Manager Sandbox</a></li>
                        <li><strong><?php _e( 'Production:', 'wptravelengine-maya-payment' ); ?></strong> <?php _e( 'Contact Maya at business.signup@maya.ph or your Relationship Manager', 'wptravelengine-maya-payment' ); ?></li>
                        <li><?php _e( 'Navigate to Developer → API Keys', 'wptravelengine-maya-payment' ); ?></li>
                        <li><?php _e( 'Copy your Public API Key (pk-...)', 'wptravelengine-maya-payment' ); ?></li>
                        <li><?php _e( 'See', 'wptravelengine-maya-payment' ); ?> <a href="https://developers.maya.ph/docs/maya-checkout" target="_blank"><?php _e( 'Maya Documentation', 'wptravelengine-maya-payment' ); ?></a></li>
                    </ul>
                </div>

                <div style="background: #ecf7ed; border-left: 4px solid #46b450; padding: 12px; margin: 20px 0;">
                    <h3 style="margin-top: 0;"><?php _e( 'Supported Payment Methods', 'wptravelengine-maya-payment' ); ?></h3>
                    <ul>
                        <li>✓ <?php _e( 'Credit Cards (Visa, Mastercard, JCB, Amex)', 'wptravelengine-maya-payment' ); ?></li>
                        <li>✓ <?php _e( 'Debit Cards', 'wptravelengine-maya-payment' ); ?></li>
                        <li>✓ <?php _e( 'QRPh (InstaPay)', 'wptravelengine-maya-payment' ); ?></li>
                        <li>✓ <?php _e( 'Maya Wallet', 'wptravelengine-maya-payment' ); ?></li>
                        <li>✓ <?php _e( 'Other payment channels (based on your merchant setup)', 'wptravelengine-maya-payment' ); ?></li>
                    </ul>
                </div>

                <?php submit_button( __( 'Save Maya Settings', 'wptravelengine-maya-payment' ) ); ?>
            </form>
        </div>

        <style>
            .wrap h1 {
                margin-bottom: 20px;
            }
            .form-table th {
                width: 200px;
            }
        </style>
        <?php
    }
}
