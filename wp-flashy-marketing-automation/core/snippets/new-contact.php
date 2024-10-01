<?php
function flashy_hook_new_customer($customer_id, $customer_data)
{
	$user = get_userdata($customer_id);

	$meta = get_user_meta($customer_id);

	if( flashy_settings("create_contact") == "purchase" )
		return;

	$customer = array(
		"email" => $user->data->user_email,
		"user_id" => $user->data->ID
	);

	if( isset($meta['first_name'][0]) )
		$customer['first_name'] = $meta['first_name'][0];

	if( isset($meta['last_name'][0]) )
		$customer['last_name'] = $meta['last_name'][0];

	if( isset($meta['billing_phone'][0]) )
		$customer['phone'] = $meta['billing_phone'][0];

	if( isset($meta['shipping_city'][0]) )
		$customer['city'] = $meta['shipping_city'][0];

	if( isset($meta['billing_country'][0]) )
		$customer['country'] = $meta['billing_country'][0];

	if( flashy_settings('add_checkbox') != "yes" )
		$flashy_subscribe = get_option('flashy_subscribe');
	else
		$flashy_subscribe = "flashy_accept_marketing";

	$list_id = get_option('flashy_list_id');

	if( isset($_POST[$flashy_subscribe]) )
	{
		$accept = $_POST[$flashy_subscribe];
	}
	else
	{
		$accept = ( $flashy_subscribe && isset($meta[$flashy_subscribe][0]) ) ? $meta[$flashy_subscribe][0] : false;
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
}
add_action('woocommerce_created_customer', 'flashy_hook_new_customer', 1000, 3);
