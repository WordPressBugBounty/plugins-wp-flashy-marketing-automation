<?php

function flashy_set_customer()
{
	if( is_user_logged_in() )
	{
		$user = wp_get_current_user();

		if( !isset($_COOKIE['flashy_id']) || $_COOKIE['flashy_id'] != base64_encode($user->user_email) ) {
			?>
			<script>
				flashy('setCustomer', {
					"email": "<?php echo $user->user_email; ?>"
				});
			</script>
			<?php
		}
	}
}
add_action('wp_footer', 'flashy_set_customer');