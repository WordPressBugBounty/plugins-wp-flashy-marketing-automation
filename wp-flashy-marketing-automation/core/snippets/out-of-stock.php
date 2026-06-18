<?php

/**
 * Render the <flashy-back-in-stock> placeholder. Hooked to the single-product
 * position chosen in the Flashy settings page only when the feature is enabled.
 */
function flashy_render_back_in_stock_element()
{
    if( !function_exists('is_product') || !is_product() )
        return;

    $display = ( flashy_settings('back_in_stock_display') === 'button' ) ? 'button' : 'popup';

    // Button mode: render ONLY the button — no popup is injected, so no popup id is
    // required. It is hidden by default; the footer script reveals it only when out
    // of stock, so it never shows on in-stock products (where that script returns
    // early). The .flashy-back-in-stock class is exposed for theming and for wiring
    // the popup however the store prefers (e.g. Flashy's own click targeting).
    if( $display === 'button' )
    {
        $button_text = flashy_settings('back_in_stock_button_text');

        if( empty($button_text) )
            $button_text = __( 'Notify me when available', 'flashy' );

        printf(
            '<button type="button" class="flashy-back-in-stock" data-flashy-back-in-stock-trigger style="display:none;">%s</button>',
            esc_html( $button_text )
        );

        return;
    }

    // Popup mode: needs a popup id to inject into the placeholder.
    $popup_id = flashy_settings('back_in_stock_popup_id');

    if( empty($popup_id) )
        return;

    $conditions = ( flashy_settings('back_in_stock_conditions') === 'yes' ) ? 'true' : 'false';

    printf(
        '<flashy-back-in-stock data-popup-id="%s" data-conditions="%s"></flashy-back-in-stock>',
        esc_attr( $popup_id ),
        esc_attr( $conditions )
    );
}

if( flashy_settings('back_in_stock') === 'yes' )
{
    // Popup mode needs a popup id to inject; button mode renders only the button
    // and therefore doesn't require one.
    $flashy_bis_button = ( flashy_settings('back_in_stock_display') === 'button' );

    if( $flashy_bis_button || flashy_settings('back_in_stock_popup_id') )
    {
        $flashy_back_in_stock_position = flashy_settings('back_in_stock_position');

        if( empty($flashy_back_in_stock_position) )
            $flashy_back_in_stock_position = 'woocommerce_single_product_summary';

        add_action( $flashy_back_in_stock_position, 'flashy_render_back_in_stock_element', 35 );
    }
}

/**
 * Default styling for the back-in-stock button (button display mode only).
 *
 * Printed late on wp_head so it lands after theme stylesheets — the single
 * .flashy-back-in-stock class then reliably beats generic `button` rules and the
 * button renders black by default — but before the Customizer's "Additional CSS"
 * (priority 101), which an admin can therefore use to restyle it without resorting
 * to !important.
 */
function flashy_back_in_stock_button_styles()
{
    if( !function_exists('is_product') || !is_product() )
        return;

    if( flashy_settings('back_in_stock') !== 'yes' )
        return;

    if( flashy_settings('back_in_stock_display') !== 'button' )
        return;
    ?>
    <style>
        .flashy-back-in-stock {
            display: inline-block;
            background: #000;
            color: #fff;
            border: 0;
            border-radius: 4px;
            padding: 12px 24px;
            font-size: 16px;
            font-weight: 600;
            line-height: 1.2;
            text-align: center;
            cursor: pointer;
            transition: background-color .2s ease, box-shadow .2s ease, transform .1s ease;
        }
        .flashy-back-in-stock:hover {
            background: #1a1a1a;
            box-shadow: 0 6px 18px rgba(0, 0, 0, .25);
            transform: translateY(-1px);
        }
        .flashy-back-in-stock:active {
            transform: translateY(0);
            box-shadow: 0 3px 10px rgba(0, 0, 0, .2);
        }
        .flashy-back-in-stock:focus-visible {
            outline: 2px solid #000;
            outline-offset: 2px;
        }
    </style>
    <?php
}
add_action('wp_head', 'flashy_back_in_stock_button_styles', 90);

/**
 * Output the out-of-stock detection script in the footer of single product pages.
 */
function flashy_out_of_stock_tracking()
{
    if( !function_exists('is_product') || !is_product() )
        return;

    $product = wc_get_product();

    if( !$product )
        return;

    $is_variable = $product->is_type( array( 'variable', 'variable-subscription' ) );
    $product_id  = $product->get_id();

    // Whether stock (quantity) is actually tracked. Mirrors Shopify, where a variant
    // with inventory tracking off is *always* "available": with no quantity there is
    // no signal that could ever drive a restock, so a "notify me when back in stock"
    // prompt would be meaningless. WooCommerce, unlike Shopify, still lets a merchant
    // flip an untracked product to "Out of stock" manually — we must not treat that
    // as back-in-stock-eligible. managing_stock() respects both the global "Manage
    // stock" option and the per-product setting.
    $manages_stock = $product->managing_stock();

    // For variable products use the first variation id as the content id (matches
    // the ViewContent event), falling back to the parent product id.
    $on_load_id = $product_id;

    // Per-variation tracking flags ({ variationId: bool }), consulted client-side on
    // "found_variation" since WooCommerce's variation payload omits this. A variation
    // is eligible only when it manages its own stock (or inherits a stock-managing
    // parent, which managing_stock() resolves).
    $variation_manages_stock = array();

    if( $is_variable )
    {
        foreach( $product->get_children() as $child_id )
        {
            $child = wc_get_product( $child_id );

            if( $child )
                $variation_manages_stock[ (int) $child_id ] = (bool) $child->managing_stock();
        }

        $child_ids = array_keys( $variation_manages_stock );

        if( !empty($child_ids) )
            $on_load_id = $child_ids[0];

        // A variable product is tracked if the parent or *any* variation tracks
        // stock — variations commonly manage stock while the parent does not.
        $manages_stock = $manages_stock || in_array( true, $variation_manages_stock, true );
    }

    // Untracked stock is always "available": nothing to detect, fire or show. The
    // button placeholder stays hidden (it is display:none until this script reveals
    // it), exactly as it does for in-stock products.
    if( !$manages_stock )
        return;

    $out_of_stock_on_load = !$product->is_in_stock();

    // A fully out-of-stock variable product never fires WooCommerce's
    // "found_variation" event (its variations form is empty), so we rely on the
    // server-side stock status for the initial state. We still attach variation
    // listeners for variable products to catch per-variation selections.
    if( !$out_of_stock_on_load && !$is_variable )
        return;

    $display_mode = ( flashy_settings('back_in_stock_display') === 'button' ) ? 'button' : 'popup';
    ?>
    <script>
        (function() {
            var flashyDisplayMode = '<?php echo esc_js( $display_mode ); ?>'; // 'popup' | 'button'

            var flashyLastVariant = null; // mirrors Shopify's lastCheckedVariantId

            // Stock state, and whether the popup is currently injected. Tracking
            // "injected" separately keeps injection idempotent, so re-asserting the
            // same state (e.g. WooCommerce firing reset_data during variation-form
            // init) never clears + re-injects and never causes a visible flash.
            var flashyOOS           = false; // is the current product/variation out of stock
            var flashyPopupInjected = false; // popup mode: is the popup in the DOM

            // Server-side stock status with no variation selected. For variable
            // products this is the parent status (out of stock only when every
            // variation is) and is the correct fallback whenever WooCommerce resets
            // back to the "no variation selected" state.
            var flashyBaselineOutOfStock = <?php echo $out_of_stock_on_load ? 'true' : 'false'; ?>;

            // Per-variation stock-tracking map ({ variationId: bool }), empty for
            // simple products. Consulted on "found_variation" so untracked variations
            // are treated as always available (never out of stock).
            var flashyVariationManagesStock = <?php echo wp_json_encode( (object) $variation_manages_stock ); ?>;

            function flashyInjectReady() {
                return window.flashy && typeof flashy.inject === 'function';
            }

            function flashyContainers() {
                return document.querySelectorAll('flashy-back-in-stock');
            }

            function flashyDoInject() {
                var els = flashyContainers();

                if( els.length === 0 )
                    return;

                var popupId = els[0].getAttribute('data-popup-id');

                if( !popupId )
                    return;

                flashy.inject({
                    selector: 'flashy-back-in-stock',
                    popup: parseInt(popupId, 10),
                    conditions: els[0].getAttribute('data-conditions') === 'true'
                });
            }

            function flashyClearPopup() {
                flashyContainers().forEach(function(el) {
                    el.innerHTML = '';
                });
            }

            function flashyToggleButton(show) {
                document.querySelectorAll('[data-flashy-back-in-stock-trigger]').forEach(function(btn) {
                    btn.style.display = show ? '' : 'none';
                });
            }

            // Reconcile the DOM with the current stock state. In button mode we only
            // show/hide the button — no popup is ever injected. In popup mode we
            // inject/clear the popup inline.
            function flashyReconcile() {
                if( flashyDisplayMode === 'button' ) {
                    flashyToggleButton( flashyOOS );
                    return;
                }

                if( flashyContainers().length === 0 )
                    return; // popup feature disabled / no placeholder on the page

                if( flashyOOS ) {
                    if( !flashyPopupInjected && flashyInjectReady() ) {
                        flashyDoInject();
                        flashyPopupInjected = true;
                    }
                } else if( flashyPopupInjected ) {
                    flashyClearPopup();
                    flashyPopupInjected = false;
                }
            }

            function flashyApplyBackInStock(isOutOfStock) {
                flashyOOS = isOutOfStock;
                flashyReconcile();
            }

            // Once Flashy has loaded, reconcile in case the state was set before
            // thunder.js was ready (covers the initial out-of-stock state in popup
            // mode).
            window.addEventListener('onFlashy', function() {
                flashyReconcile();
            });

            // Whether a given variant tracks stock. The initial/baseline state passes
            // this explicitly (tracking was already confirmed server-side); a
            // "found_variation" selection looks the variation up in the map. Anything
            // not in the map (e.g. a simple product's own id) defaults to tracked,
            // since the script is only emitted for tracked products.
            function flashyManagesStock(variantId) {
                return flashyVariationManagesStock.hasOwnProperty(variantId)
                    ? flashyVariationManagesStock[variantId]
                    : true;
            }

            function flashyCheckVariant(variantId, isInStock, managesStock) {
                if( !variantId )
                    return;

                variantId = variantId.toString();

                if( variantId === flashyLastVariant )
                    return;

                flashyLastVariant = variantId;

                if( managesStock === undefined )
                    managesStock = flashyManagesStock(variantId);

                // Untracked stock is always "available" — never out of stock, so no
                // event fires and the button/popup stays hidden (Shopify parity).
                var outOfStock = managesStock && ( isInStock === false );

                if( outOfStock ) {
                    flashy('CustomEvent', {
                        'event_name': 'OutOfStock',
                        'variant_id': variantId
                    });
                    console.log("FlashyOutOfStock", {
                        'event_name': 'OutOfStock',
                        'variant_id': variantId
                    })
                }

                flashyApplyBackInStock(outOfStock);
            }

            <?php if( $out_of_stock_on_load ) : ?>
            // Tracking confirmed server-side, so force managesStock = true: for a
            // fully out-of-stock variable product this is the parent aggregate, not a
            // single variation, and must not be gated by one child's map entry.
            flashyCheckVariant('<?php echo esc_js( $on_load_id ); ?>', false, true);
            <?php endif; ?>

            <?php if( $is_variable ) : ?>
            if( window.jQuery ) {
                jQuery(function($) {
                    $('.variations_form').on('found_variation', function(event, variation) {
                        if( variation )
                            flashyCheckVariant(variation.variation_id, variation.is_in_stock);
                    });

                    // WooCommerce fires reset_data on genuine resets AND once
                    // during variation-form init (~100ms after load, via its
                    // check_variations setTimeout). Re-assert the server-side
                    // baseline rather than forcing "in stock" — otherwise a fully
                    // out-of-stock variable product injects its popup on load and
                    // then has it wiped on init, the "shows then disappears" flash.
                    // Nulling lastVariant still re-arms the guard so re-selecting
                    // the same out-of-stock variant fires the event again.
                    $('.variations_form').on('reset_data', function() {
                        flashyLastVariant = null;
                        flashyApplyBackInStock(flashyBaselineOutOfStock);
                    });
                });
            }
            <?php endif; ?>
        })();
    </script>
    <?php
}
add_action('wp_footer', 'flashy_out_of_stock_tracking');
