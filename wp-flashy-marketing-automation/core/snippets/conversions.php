<?php

function flashy_purchase($data)
{
    flashy()->add_action_purchase($data);
}

function flashy_order_hook($order_id)
{
	flashy()->add_hook("flashy_purchase", ["order_id" => $order_id]);
}

add_action('woocommerce_new_order', 'flashy_order_hook', 10000);

function flashy_set_customer_on_purchase($order_id)
{
	$order = new WC_Order($order_id);

	$email = $order->get_billing_email();

	if( $email )
	{
		?>
			<script>
				window.addEventListener('onFlashy', function() {
					localStorage.removeItem("flashy_thunder");
				});

				flashy('setCustomer', {
					"email": "<?php echo $email; ?>"
				});
			</script>
		<?php
	}
}
add_action('woocommerce_thankyou', 'flashy_set_customer_on_purchase');

function flashy_amplify_purchase($order_id)
{
	$order = new WC_Order($order_id);
	$currency = $order->get_currency();
	$total = $order->get_total();
	$order_items = $order->get_items();

    $products = [];

	foreach ($order_items as $order_item)
	{
		if( $order_item['variation_id'] == 0  )
		{
			$products[] = $order_item['product_id'];
		}
		else
		{
			$products[] = $order_item['variation_id'];
		}
	}

	if( get_option('flashy_account_id') )  { ?>
            <script>
                flashy('amplify:Purchase', {
                    'content_ids': <?php echo json_encode($products); ?>,
                    'value': <?php echo (int) $total; ?>,
                    'currency': "<?php echo $currency; ?>",
                    'order_id': "<?php echo $order_id; ?>"
                });
            </script>
        <?php
	}
}
add_action('woocommerce_thankyou', 'flashy_amplify_purchase');