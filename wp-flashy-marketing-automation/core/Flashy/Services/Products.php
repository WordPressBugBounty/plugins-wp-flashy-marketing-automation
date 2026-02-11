<?php

namespace Flashy\Services;

use Flashy\Exceptions\FlashyAuthenticationException;
use Flashy\Exceptions\FlashyClientException;
use Flashy\Exceptions\FlashyResponseException;
use Flashy\Flashy;
use Flashy\Response;

class Products {

    /**
     * @var Flashy
     */
    protected $flashy;

    /**
     * Products constructor.
     * @param $flashy
     */
    public function __construct($flashy)
    {
        $this->flashy = $flashy;
    }

    /**
     * @param int $product_id
     * @return Response
     * @throws FlashyClientException
     * @throws FlashyResponseException|FlashyAuthenticationException
     */
    public function reviews($product_id)
    {
        $account_id = get_option('flashy_account_id');

        return $this->flashy->client->get("thunder/reviews?account_id=" . $account_id . "&item_id=" . $product_id);
    }

}
