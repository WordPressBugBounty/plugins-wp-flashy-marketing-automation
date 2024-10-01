<?php

namespace Flashy\Services;

use Flashy\Exceptions\FlashyClientException;
use Flashy\Exceptions\FlashyResponseException;
use Flashy\Flashy;
use Flashy\Response;

class Lists {

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
     * @throws FlashyResponseException
     */
    public function get()
    {
        return $this->flashy->client->get("lists");
    }

}
