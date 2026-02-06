<?php
/**
 * Flashy Store API Extension
 *
 * Handles the WooCommerce Store API extension for the marketing checkbox.
 *
 * @package Flashy
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema;

/**
 * Class Flashy_Store_Api
 *
 * Extends the WooCommerce Store API to handle marketing consent data.
 */
class Flashy_Store_Api {

    /**
     * Initialize the Store API extension.
     */
    public static function init() {
        // Extend Store API - check if blocks already loaded
        if ( did_action( 'woocommerce_blocks_loaded' ) ) {
            self::extend_store_api();
        } else {
            add_action( 'woocommerce_blocks_loaded', array( __CLASS__, 'extend_store_api' ) );
        }

        // Handle the data when order is processed
        add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( __CLASS__, 'process_checkout_data' ), 10, 2 );
    }

    /**
     * Extend the Store API with our custom data.
     */
    public static function extend_store_api() {
        if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
            return;
        }

        woocommerce_store_api_register_endpoint_data(
            array(
                'endpoint'        => CheckoutSchema::IDENTIFIER,
                'namespace'       => 'flashy-marketing',
                'data_callback'   => array( __CLASS__, 'data_callback' ),
                'schema_callback' => array( __CLASS__, 'schema_callback' ),
                'schema_type'     => ARRAY_A,
            )
        );
    }

    /**
     * Data callback for the Store API.
     *
     * @return array
     */
    public static function data_callback() {
        return array(
            'accept_marketing' => false,
        );
    }

    /**
     * Schema callback for the Store API.
     *
     * @return array
     */
    public static function schema_callback() {
        return array(
            'accept_marketing' => array(
                'description' => __( 'Whether the customer accepts marketing communications.', 'flashy' ),
                'type'        => 'boolean',
                'context'     => array( 'view', 'edit' ),
                'readonly'    => false,
                'optional'    => true,
            ),
        );
    }

    /**
     * Process the checkout data and save marketing consent.
     *
     * @param WC_Order        $order   The order object.
     * @param WP_REST_Request $request The request object.
     */
    public static function process_checkout_data( $order, $request ) {
        $extensions = $request->get_param( 'extensions' );

        if ( isset( $extensions['flashy-marketing']['accept_marketing'] ) ) {
            $accept_marketing = (bool) $extensions['flashy-marketing']['accept_marketing'];

            // Save to order meta
            $order->update_meta_data( 'flashy_accept_marketing', $accept_marketing ? '1' : '0' );
            $order->save();

            // Save to user meta if logged in
            if ( $order->get_customer_id() ) {
                update_user_meta( $order->get_customer_id(), 'flashy_accept_marketing', $accept_marketing ? '1' : '0' );
            }
        }
    }
}
