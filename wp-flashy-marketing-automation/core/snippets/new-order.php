<?php
function flashy_hook_new_order($order_id)
{
	flashy_log('flashy_hook_new_order');

	$order = wc_get_order($order_id);

	if( $order === false )
		return;

	$allow_guests = get_option('flashy_allow_guest');

	$guest = false;

	$customer = array();

	$customer['first_name'] = $order->get_billing_first_name();
	
	$customer['last_name'] = $order->get_billing_last_name();
	
	$customer['phone'] = $order->get_billing_phone();
	
	$customer['email'] = $order->get_billing_email();
	
	$customer['city'] = $order->get_billing_city();
	
	$customer['country'] = $order->get_billing_country();

	flashy_log('Contact data: ' . json_encode($customer));
	
	if( $order->get_user() )
	{
		$user = $order->get_user();

		$customer['user_id'] = $user->ID;
	}
	else
	{
		$guest = true;

		$order_meta = get_post_meta($order_id);
	}

	if( $guest == true && $allow_guests == "no" )
	{
		return;
	}

	$meta = ( $guest != true ) ? get_user_meta($customer['user_id']) : array();
	
	if( flashy_settings('add_checkbox') != "yes" )
		$flashy_subscribe = get_option('flashy_subscribe');
	else
		$flashy_subscribe = "flashy_accept_marketing";

	$list_id = get_option('flashy_list_id');

	$accept = ( $flashy_subscribe && isset($meta[$flashy_subscribe][0]) ) ? $meta[$flashy_subscribe][0] : false;

	if( $flashy_subscribe )
	{
		if( isset($_POST[$flashy_subscribe]) )
		{
			$accept = $_POST[$flashy_subscribe];
		}
		else
		{
			if( $guest == true && isset($order_meta[$flashy_subscribe][0]) )
				$accept = $order_meta[$flashy_subscribe][0];
			else if( $guest == false && isset($meta[$flashy_subscribe][0]) )
				$accept = $meta[$flashy_subscribe][0];
			else
				$accept = false;
		}
	}
	else
	{
		$accept = false;
	}

	$subscribe = ( strval($accept) == "1" || strval($accept) == "yes" ) ? true : false;

	if( $flashy_subscribe == false || $flashy_subscribe == "" || $list_id == false || $subscribe == false )
	{
		$create = flashy()->add_action("create", $customer);
	}
	else
	{
		$create = flashy()->add_action("subscribe", $customer);
	}

    // Empty flashy cart
    if( flashy()->getContactId() )
    {
        flashy()->saveContactCart(flashy()->getContactId(), array());
    }
}
add_action('woocommerce_new_order', 'flashy_hook_new_order', 1000, 1);
