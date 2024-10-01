<?php

namespace Flashy\Services;

use Flashy\Exceptions\FlashyAuthenticationException;
use Flashy\Exceptions\FlashyClientException;
use Flashy\Exceptions\FlashyException;
use Flashy\Exceptions\FlashyResponseException;
use Flashy\Flashy;
use Flashy\Response;

class Messages {

    /**
     * @var Flashy
     */
    protected $flashy;

    /**
     * Lists constructor.
     * @param $flashy
     */
    public function __construct($flashy)
    {
        $this->flashy = $flashy;
    }

    /**
     * @param array $message
     * @return Response
     * @throws FlashyClientException|FlashyResponseException|FlashyAuthenticationException|FlashyException
     */
    public function email($message = [])
    {
        $this->validateMessage($message);

        if( !isset($message['template']) && !isset($message['subject']) )
            throw new FlashyException("Message [subject] is required if not using template");

        if( !isset($message['template']) && !isset($message['html']) )
            throw new FlashyException("Message [template or html] is required");

        if( !isset($message['from']) )
            throw new FlashyException("Message [from] is required");

        if( !isset($message['to']) )
            throw new FlashyException("Message [message] is required");

        return $this->flashy->client->post("messages/email", ['message' => $message]);
    }

    /**
     * @param array $message
     * @return Response
     * @throws FlashyClientException|FlashyResponseException|FlashyAuthenticationException|FlashyException
     */
    public function sms($message = [])
    {
        $this->validateMessage($message);

        if( !isset($message['to']) )
            throw new FlashyException("Message [to] is required");

        if( !isset($message['from']) )
            throw new FlashyException("Message [from] is required");

        if( !isset($message['message']) )
            throw new FlashyException("Message [message] is required");

        return $this->flashy->client->post("messages/sms", ["message" => $message]);
    }

    /**
     * @param $message
     * @throws FlashyException
     */
    public function validateMessage($message)
    {
        if( count($message) === 0 || gettype($message) != "array" )
            throw new FlashyException("Message body is invalid");
    }

}
