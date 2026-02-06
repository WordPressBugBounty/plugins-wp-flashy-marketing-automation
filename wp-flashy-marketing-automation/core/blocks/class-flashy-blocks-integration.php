<?php
/**
 * Flashy WooCommerce Blocks Integration
 *
 * Adds marketing consent checkbox to WooCommerce Block-based checkout.
 *
 * @package Flashy
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

/**
 * Class Flashy_Blocks_Integration
 *
 * Integrates Flashy marketing checkbox with WooCommerce Blocks checkout.
 */
class Flashy_Blocks_Integration implements IntegrationInterface {

    /**
     * The name of the integration.
     *
     * @return string
     */
    public function get_name() {
        return 'flashy-marketing';
    }

    /**
     * Initialize the integration.
     */
    public function initialize() {
        $this->register_block_frontend_scripts();
    }

    /**
     * Get the script handles for the frontend.
     *
     * @return array
     */
    public function get_script_handles() {
        return array( 'flashy-blocks-frontend' );
    }

    /**
     * Get the script handles for the editor.
     *
     * @return array
     */
    public function get_editor_script_handles() {
        return array( 'flashy-blocks-frontend' );
    }

    /**
     * Get script data to pass to frontend scripts.
     *
     * @return array
     */
    public function get_script_data() {
        $checkbox_marked = flashy_settings( 'checkbox_marked' ) === 'yes';
        $label = flashy_settings( 'allow_text' );

        if ( empty( $label ) ) {
            $label = __( 'I agree to receive promotional emails and text messages.', 'flashy' );
        }

        // Check if user already subscribed
        $already_subscribed = false;
        if ( is_user_logged_in() ) {
            $status = get_user_meta( get_current_user_id(), 'flashy_accept_marketing', true );
            $already_subscribed = (bool) $status;
        }

        return array(
            'checkboxLabel'     => $label,
            'defaultChecked'    => $checkbox_marked,
            'alreadySubscribed' => $already_subscribed,
        );
    }

    /**
     * Register frontend scripts.
     */
    private function register_block_frontend_scripts() {
        $script_path = plugin_dir_path( __FILE__ ) . 'js/flashy-blocks-checkout.js';
        $script_url = plugins_url( 'js/flashy-blocks-checkout.js', __FILE__ );

        // Check if file exists
        if ( ! file_exists( $script_path ) ) {
            return;
        }

        $script_asset = array(
            'dependencies' => array(
                'wp-element',
                'wp-plugins',
                'wp-components',
                'wc-blocks-checkout',
                'wc-settings',
            ),
            'version' => filemtime( $script_path ),
        );

        wp_register_script(
            'flashy-blocks-frontend',
            $script_url,
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );
    }
}
