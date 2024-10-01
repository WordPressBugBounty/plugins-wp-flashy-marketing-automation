<?php

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * @throws Exception
 */
function balance_points($meta_id, $user_id = null, $meta_name = null, $points_left = null)
{
    if( $meta_name === '_ywpar_user_total_points' )
    {
        $event = [
            'user_id' => $user_id,
            'points_left' => $points_left
        ];

        $event = flashy()->add_action("custom_event", $event);
    }
}


add_filter( 'updated_user_meta', 'balance_points', 10, 4 );
