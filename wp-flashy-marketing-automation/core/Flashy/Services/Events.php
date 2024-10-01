<?php

namespace Flashy\Services;

use Flashy\Exceptions\FlashyAuthenticationException;
use Flashy\Exceptions\FlashyClientException;
use Flashy\Exceptions\FlashyResponseException;
use Flashy\Flashy;
use Flashy\Helper;
use Flashy\Response;

class Events {

    /**
     * @var Flashy
     */
    protected $flashy;

    /**
     * Events constructor.
     * @param $flashy
     */
    public function __construct($flashy)
    {
        $this->flashy = $flashy;
    }

    /**
     * @param $event
     * @param $params
     * @return Response
     * @throws FlashyClientException
     * @throws FlashyResponseException|FlashyAuthenticationException
     */
    public function track($event, $params)
    {
        if( !isset($params['flashy_id']) && !isset($params['contact_id']) && !isset($params['email']) )
        {
            return new Response(array('success' => false, 'errors' => 'email / contact id / flashy id not found'), false);
        }

        $payload = array(
            "event" => $event,
            "body" => $params
        );

        return $this->flashy->client->post("event", $payload);
    }

    /**
     * @param null $contact_id
     * @param string $events_list
     * @param string $identity
     * @return Response
     * @throws FlashyResponseException
     * @throws FlashyClientException|FlashyAuthenticationException
     */
    public function bulk($contact_id = null, $events_list = "cookie", $identity = "contact_id")
    {
        if( $contact_id == null )
        {
            $identity = "flashy_id";

            $contact_id = Helper::getContactId();
        }

        if($contact_id == null)
            return new Response(array('success' => false, 'errors' => 'contact id or flashy id not found'), false);

        $events = array();

        if($events_list == "cookie" && isset($_COOKIE['flashy_thunder']))
        {
            $events = json_decode(base64_decode($_COOKIE['flashy_thunder']), true);

            foreach ($events as &$event)
            {
                $event['body'][$identity] = ( $identity == "contact_id" ) ? $contact_id : base64_encode($contact_id);
            }
        }
        else if( $events_list !== "cookie" )
        {
            $events = $events_list;
        }

        if(count($events) == 0)
            return new Response(array('success' => false, 'errors' => 'events not found'), false);

        $event = $this->flashy->client->post('events/bulk', $events);

        if( $event->success() )
        {
            Helper::clearThunderCookie();
        }

        return $event;
    }

}
