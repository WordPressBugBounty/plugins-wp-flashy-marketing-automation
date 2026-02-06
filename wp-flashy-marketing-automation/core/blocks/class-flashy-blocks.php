<?php
/**
 * Flashy WooCommerce Blocks Support
 *
 * Main loader for WooCommerce Blocks integration.
 *
 * @package Flashy
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Flashy_Blocks
 *
 * Handles initialization of WooCommerce Blocks support for Flashy.
 */
class Flashy_Blocks {

    /**
     * Initialize Blocks support.
     */
    public static function init() {
        // Only load if WooCommerce Blocks is available
        if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Package' ) ) {
            return;
        }

        // Check if marketing checkbox is enabled for checkout
        if ( flashy_settings( 'add_checkbox' ) !== 'yes' ) {
            return;
        }

        $accept_marketing = flashy_settings( 'accept_marketing' );
        if ( ! isset( $accept_marketing['checkout'] ) || $accept_marketing['checkout'] !== 'yes' ) {
            return;
        }

        // Load Store API extension
        require_once __DIR__ . '/class-flashy-store-api.php';
        Flashy_Store_Api::init();

        // Register integration with WooCommerce Blocks
        add_action( 'woocommerce_blocks_checkout_block_registration', array( __CLASS__, 'register_checkout_block_integration' ) );

        // Register block integration for older versions
        add_action( 'woocommerce_blocks_loaded', array( __CLASS__, 'register_block_integration' ) );
    }

    /**
     * Register checkout block integration (WooCommerce 8.9+).
     *
     * @param Automattic\WooCommerce\Blocks\Integrations\IntegrationRegistry $integration_registry The integration registry.
     */
    public static function register_checkout_block_integration( $integration_registry ) {
        require_once __DIR__ . '/class-flashy-blocks-integration.php';
        $integration_registry->register( new Flashy_Blocks_Integration() );
    }

    /**
     * Register block integration for older WooCommerce Blocks versions.
     */
    public static function register_block_integration() {
        if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Integrations\IntegrationRegistry' ) ) {
            return;
        }

        add_action(
            'woocommerce_blocks_checkout_block_registration',
            function( $integration_registry ) {
                require_once __DIR__ . '/class-flashy-blocks-integration.php';
                $integration_registry->register( new Flashy_Blocks_Integration() );
            }
        );
    }

    /**
     * Check if the current checkout is using blocks.
     *
     * @return bool
     */
    public static function is_checkout_block() {
        if ( ! function_exists( 'has_block' ) ) {
            return false;
        }

        // Get checkout page ID
        $checkout_page_id = wc_get_page_id( 'checkout' );

        if ( ! $checkout_page_id ) {
            return false;
        }

        // Check if the checkout page has the checkout block
        $checkout_page = get_post( $checkout_page_id );

        if ( ! $checkout_page ) {
            return false;
        }

        return has_block( 'woocommerce/checkout', $checkout_page );
    }
}
