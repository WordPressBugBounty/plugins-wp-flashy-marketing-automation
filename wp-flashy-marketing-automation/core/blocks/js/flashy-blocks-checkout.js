/**
 * Flashy Marketing Checkbox for WooCommerce Blocks Checkout
 *
 * Adds a marketing consent checkbox to the WooCommerce Block-based checkout
 * using the SlotFill system for automatic display.
 */
( function() {
    'use strict';

    // Wait for WooCommerce Blocks to be ready
    if ( typeof wc === 'undefined' || typeof wc.blocksCheckout === 'undefined' ) {
        console.log( 'Flashy: WooCommerce Blocks not available' );
        return;
    }

    if ( typeof wp === 'undefined' || typeof wp.plugins === 'undefined' ) {
        console.log( 'Flashy: WordPress plugins not available' );
        return;
    }

    const { registerPlugin } = wp.plugins;
    const { ExperimentalOrderMeta } = wc.blocksCheckout;
    const { CheckboxControl } = wp.components;
    const { useState, useEffect, useCallback } = wp.element;
    const { getSetting } = wc.wcSettings;
    const { dispatch, select } = wp.data;

    // Get settings from PHP
    const settings = getSetting( 'flashy-marketing_data', {} );
    const checkboxLabel = settings.checkboxLabel || 'I agree to receive promotional emails and text messages.';
    const defaultChecked = settings.defaultChecked || false;
    const alreadySubscribed = settings.alreadySubscribed || false;

    /**
     * Set extension data using available method
     */
    const setFlashyExtensionData = ( value ) => {
        const checkoutStore = dispatch( 'wc/store/checkout' );

        // Try the public method first, then fall back to internal
        if ( checkoutStore.setExtensionData ) {
            checkoutStore.setExtensionData( 'flashy-marketing', { accept_marketing: value } );
        } else if ( checkoutStore.__internalSetExtensionData ) {
            checkoutStore.__internalSetExtensionData( 'flashy-marketing', { accept_marketing: value } );
        }
    };

    /**
     * Flashy Marketing Checkbox Component
     */
    const FlashyMarketingCheckbox = () => {
        const [ isChecked, setIsChecked ] = useState( defaultChecked );

        // Don't render if user is already subscribed
        if ( alreadySubscribed ) {
            return null;
        }

        // Send the value through Store API extension data
        useEffect( () => {
            setFlashyExtensionData( isChecked );
        }, [ isChecked ] );

        // Set initial value on mount
        useEffect( () => {
            setFlashyExtensionData( defaultChecked );
        }, [] );

        const handleChange = useCallback( ( checked ) => {
            setIsChecked( checked );
        }, [] );

        return wp.element.createElement(
            'div',
            {
                className: 'flashy-marketing-checkbox-wrapper wc-block-components-checkbox',
                style: {
                    marginTop: '16px',
                    marginBottom: '8px'
                }
            },
            wp.element.createElement(
                'label',
                {
                    style: {
                        display: 'flex',
                        alignItems: 'flex-start',
                        gap: '12px',
                        cursor: 'pointer',
                        fontSize: '14px',
                        lineHeight: '1.5'
                    }
                },
                wp.element.createElement(
                    'input',
                    {
                        type: 'checkbox',
                        id: 'flashy-accept-marketing',
                        checked: isChecked,
                        onChange: function( e ) { handleChange( e.target.checked ); },
                        style: {
                            width: '18px',
                            height: '18px',
                            marginTop: '2px',
                            cursor: 'pointer',
                            accentColor: '#000'
                        }
                    }
                ),
                wp.element.createElement(
                    'span',
                    null,
                    checkboxLabel
                )
            )
        );
    };

    /**
     * Render component in the ExperimentalOrderMeta slot
     * This automatically appears in the checkout without manual block placement
     */
    const FlashyCheckoutIntegration = () => {
        return wp.element.createElement(
            ExperimentalOrderMeta,
            null,
            wp.element.createElement( FlashyMarketingCheckbox, null )
        );
    };

    // Register the plugin with WordPress - this makes it automatic
    registerPlugin( 'flashy-marketing-checkout', {
        render: FlashyCheckoutIntegration,
        scope: 'woocommerce-checkout',
    } );

} )();
