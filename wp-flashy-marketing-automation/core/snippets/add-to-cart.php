<?php

function flashy_save_cookie($key, $data, $time = true)
{
    $data = base64_encode(json_encode($data));

    if( $time == true )
        $days = time() + 3600 * 24 * 365;
    else
        $days = time() - 10;

    setcookie($key, $data, $days, "/");
}

function flashy_cart_manager()
{
    ?>
        <script>
            function getFlashyCookie(cname) {
                var name = cname + "=";
                var decodedCookie = decodeURIComponent(document.cookie);
                var ca = decodedCookie.split(';');
                for(var i = 0; i <ca.length; i++) {
                    var c = ca[i];
                    while (c.charAt(0) == ' ') {
                        c = c.substring(1);
                    }
                    if (c.indexOf(name) == 0) {
                        return c.substring(name.length, c.length);
                    }
                }
                return "";
            }

            function setFlashyCookie(cname, cvalue) {
                var d = new Date();
                d.setTime(d.getTime() + (365*24*60*60*1000));
                var expires = "expires="+ d.toUTCString();
                document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
            }

            window.addEventListener('onFlashy', function() {

                function flashyCartManager() {
                    var flashyCache = window.atob(getFlashyCookie("flashy_cache"));

                    var flashyCart = window.atob(getFlashyCookie("flashy_cart"));

                    if (flashyCache != flashyCart)
                    {
                        setFlashyCookie("flashy_cache", window.btoa(flashyCart));

                        flashyCart = JSON.parse(flashyCart);

                        if( flashyCart.value && flashyCart.value > 0 )
                            flashy('UpdateCart', flashyCart);

                        console.log("Flashy Update Cart:", flashyCart);
                    }
                };

                flashyCartManager();

                window.setInterval(function() {
                    flashyCartManager();
                }, 1200);


            });
        </script>
    <?php
}
add_action('wp_footer', "flashy_cart_manager");

function flashy_cart_updated()
{
    global $woocommerce;

    $items = $woocommerce->cart->get_cart();

    $data = [];

    if( is_callable(array($woocommerce->cart, "get_cart_contents_total")) )
    {
        $calculate_total = false;

        $data['value'] = $woocommerce->cart->get_cart_contents_total();
    }
    else
    {
        $calculate_total = true;

        $data['value'] = 0;
    }

	$child_bundles = [];

    foreach ($items as $item)
    {
        if( isset($item['line_subtotal']) )
        {
            if( $calculate_total )
                $data['value'] += $item['line_subtotal'];

			$product_obj = wc_get_product($item['product_id']);

            if( !$product_obj )
                continue;

			if( $product_obj->get_type() === "bundle" )
			{
				$product_bundle = new WC_Product_Bundle( $item['product_id'] );

				if( $product_bundle )
				{
					$child_items = $product_bundle->get_bundled_items();

					foreach($child_items as $bundle_child)
					{
						$prd_data = $bundle_child->get_data();

						$_product = wc_get_product( $prd_data['product_id'] );

						if( $_product->get_type() == "variable" )
						{
							if( !empty($_product->get_available_variations()) )
							{
								$product_vars = $_product->get_available_variations();

								foreach( $product_vars as $variable )
								{
									$child_bundles[] = $variable['variation_id'];
								}

							}
						}

						$child_bundles[] = $bundle_child->get_data()['product_id'];
					}
				}

				$data['content_ids'][] = $item['product_id'];
			}

			if( $item['variation_id'] == 0 )
            {
                $data['content_ids'][] = $item['product_id'];
            }
            else
            {
                $data['content_ids'][] = $item['variation_id'];
            }
        }
    }

	if( !empty($child_bundles) )
	{
		foreach($data['content_ids'] as $key_id => $bundled)
		{
			if( in_array( $bundled, $child_bundles ) )
			{
				unset($data['content_ids'][$key_id]);
			}
		}

		$data['content_ids'] = array_values(array_unique($data['content_ids']));
	}

    $data['currency'] = get_woocommerce_currency();

    if( !headers_sent() )
        flashy_save_cookie("flashy_cart", $data);

     if( flashy()->getContactId() && count($items) > 0 )
     {
         flashy()->saveContactCart(flashy()->getContactId(), $items);
     }
}
add_action('woocommerce_cart_updated', "flashy_cart_updated", 10, 2);

add_action( 'wp_footer', 'flashy_product_ajax_add_to_cart_js_script' );
function flashy_product_ajax_add_to_cart_js_script() {
    ?>
    <script>
        window.FlashyAddToCart = function FlashyAddToCart(products_id, popup_id = null, callback = null) {
            if( products_id && products_id.toString().length > 0 ) {
                jQuery.ajax({
                    type: 'POST',
                    url: wc_add_to_cart_params.wc_ajax_url.toString().replace('%%endpoint%%', 'flashy_function_add_to_cart'),
                    data: {
                        products_id,
                    },
                    success: function (response) {
                        jQuery(document.body).trigger("wc_fragment_refresh");

                        if( callback !== null )
			                callback();
                    },
                    error: function (error) {
                        console.log(error);
                    }
                });
            }
        }
    </script>
    <?php
}

add_action( 'wc_ajax_flashy_function_add_to_cart', 'flashy_function_add_to_cart_handler' );
add_action( 'wc_ajax_nopriv_flashy_function_add_to_cart', 'flashy_function_add_to_cart_handler' );

function flashy_function_add_to_cart_handler() {

    if( isset($_POST['products_id']) )
    {
        $products = [];

        if( strpos($_POST['products_id'], ',') !== false )
        {
            $products = explode(',', $_POST['products_id']);
        }
        else
        {
            $products[] = $_POST['products_id'];
        }

        foreach( $products as $product_id )
        {
            $product = wc_get_product( $product_id );

            if( !$product )
                return wp_send_json(["success" => false, "error" => "Product not found."]);

            //variation product else product
            if( wp_get_post_parent_id($product->get_id()) != 0 )
            {
                $variation_id = $product_id;
                $product_id = wp_get_post_parent_id($product->get_id());
                $variation = $product->get_variation_attributes();
            }
            else
            {
                $variation_id = 0;
                $variation = [];
            }

            // Add to cart
            $cart_item_key = WC()->cart->add_to_cart( $product_id, 1, $variation_id, $variation, [] );
        }

        if(!$cart_item_key) wp_send_json(["success" => false, "error" => "Something went wrong."]);

        wp_send_json(["success" => true, "token" => $cart_item_key]);
        wp_die();
    }
}

