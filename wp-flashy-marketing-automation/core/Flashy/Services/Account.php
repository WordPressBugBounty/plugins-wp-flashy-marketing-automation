<?php

namespace Flashy\Services;

use Flashy\Exceptions\FlashyAuthenticationException;
use Flashy\Exceptions\FlashyClientException;
use Flashy\Exceptions\FlashyResponseException;
use Flashy\Flashy;
use Flashy\Response;

class Account {

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
     * @return Response
     * @throws FlashyClientException
     * @throws FlashyResponseException|FlashyAuthenticationException
     */
    public function get()
    {
        return $this->flashy->client->get("account");
    }

}
