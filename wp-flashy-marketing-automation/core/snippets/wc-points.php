<?php

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * @throws Exception
 */
function balance_points($userId)
{

    $updated_points = WC_Points_Rewards_Manager::get_users_points( $userId );

    $logs = WC_Points_Rewards_Points_Log::get_points_log_entries([
        'orderby' => [
            'field' => 'date',
            'order' => 'asc',
        ],
        'user' => $userId
    ]);

    $newest = count($logs) - 1;

    $prev_points = $updated_points - (int)$logs[$newest]->points;

    $user_data = get_user_by( 'id', $userId );

    $email = "";

    if( !empty( $user_data ) )
    {
        $email = $user_data->data->user_email;
    }
    else
    {
        return [];
    }

    $event = [
        "event_name" => "points",
        "email" => $email,
        "contact" => [
            "loyalty_membership_created" => (new DateTime($logs[0]->date))->getTimestamp() ?? 0,
            "total_points" => $updated_points,
        ]
    ];

    if( strpos($logs[$newest]->points, '-') !== false )
    {
        $event['contact']['last_points_used'] = $logs[$newest]->date;

        $added_or_removed = "removed";
    }
    else
    {
        $event['contact']['last_points_earned'] = $logs[$newest]->date;

        $added_or_removed = "added";
    }

    $event['context'] = [
        "previous_points" => $prev_points,
        "current_balance" => $updated_points,
        "difference" => $logs[$newest]->points,
        "reason" => $logs[$newest]->description,
        "added_or_removed" => $added_or_removed,
    ];

    $trackEvent = flashy()->api->events->track("CustomEvent", $event);
}

//add_action('wc_points_rewards_after_set_points_balance', 'balance_points', 10, 1);
add_action('wc_points_rewards_after_increase_points', 'balance_points', 10, 1);
add_action('wc_points_rewards_after_reduce_points', 'balance_points', 10, 1);