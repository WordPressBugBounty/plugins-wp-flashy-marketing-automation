<?php

namespace Flashy\Services;

use Flashy\Exceptions\FlashyAuthenticationException;
use Flashy\Exceptions\FlashyClientException;
use Flashy\Exceptions\FlashyException;
use Flashy\Exceptions\FlashyResponseException;
use Flashy\Flashy;
use Flashy\Helper;
use Flashy\Response;

class Contacts {

    //TODO Search Contacts - "search_by_email_or_phone"

    /**
     * @var Flashy
     */
    protected $flashy;

    public function __construct($flashy)
    {
        $this->flashy = $flashy;
    }

    /**
     * Create a contact
     * @param array $contact Contact information array
     * @param string $primary_key Default is email but any field can be used
     * @param bool $tracking Set cookie to remember the contact
     * @param null $overwrite Update contact properties if exists
     * @return Response
     * @throws FlashyResponseException|FlashyAuthenticationException|FlashyClientException
     */
    public function create($contact, $primary_key = "email", $tracking = true, $overwrite = null)
    {
        $payload = array(
            "primary_key" => $primary_key,
            "contact" => $contact
        );

        if( Helper::getAttribution() )
        {
            $payload["contact"]["attribution"] = Helper::getAttribution();
        }

        if( $overwrite )
        {
            $overwrite = "?overwrite=true";
        }

        $request = $this->flashy->client->post("contact" . $overwrite, $payload);

        if( $request->hasErrors() )
            return $request;

        if( $tracking === true )
        {
            Helper::setContact($request->getData());
        }

        return $request;
    }

    /**
     * Update a contact
     * @param array $contact Contact information array
     * @param string $primary_key Default is email but any field can be used
     * @return Response
     * @throws FlashyResponseException|FlashyException|FlashyAuthenticationException|FlashyClientException
     */
    public function update($contact, $primary_key = "email")
    {
        if( !isset($contact[$primary_key]) )
            throw new FlashyException("Contact primary key is missing from contact array");

        $payload = array(
            "primary_key" => $primary_key,
            "contact" => $contact
        );

        return $this->flashy->client->put('contact/' . $contact[$primary_key], $payload);
    }

    /**
     * Get a contact
     * @param mixed $identifier The identifier to find the contact
     * @param string $primary_key Default is email but any field can be used (phone, token)
     * @return Response
     * @throws FlashyClientException|FlashyResponseException|FlashyAuthenticationException
     */
    public function get($identifier, $primary_key = "email")
    {
        return $this->flashy->client->get("contact/" . $identifier . "?primary_key=" . $primary_key);
    }

    /**
     * Delete a contact
     * @param $identifier
     * @param string $primary_key
     * @return Response
     * @throws FlashyClientException
     * @throws FlashyResponseException|FlashyAuthenticationException
     */
    public function delete($identifier, $primary_key = "email")
    {
        return $this->flashy->client->delete("contact/" . $identifier . "?primary_key=" . $primary_key);
    }

    /**
     * Create or update contact with lists subscriptions
     * @param mixed $contact Contact array or identifier to create / update the contact
     * @param array|int $lists Can be array of lists ids or single list ID
     * @param string $primary_key
     * @param bool $tracking
     * @return Response
     * @throws FlashyClientException|FlashyResponseException|FlashyAuthenticationException
     */
    public function subscribe($contact, $lists, $primary_key = "email", $tracking = true)
    {
        if( gettype($contact) !== 'array' )
        {
            $contact = [$primary_key => $contact];
        }

        if( gettype($lists) !== "array" )
        {
            $lists = [$lists];
        }

        foreach( $lists as $list )
        {
            $contact["lists"][$list] = 1;
        }

        return $this->create($contact, $primary_key, $tracking, true);
    }

    /**
     * Unsubscribe contact from specific lists
     * @param mixed $contact Contact array or identifier to update the contact
     * @param array|int $lists Can be array of lists ids or single list ID
     * @param string $primary_key
     * @param bool $tracking
     * @return Response
     * @throws FlashyResponseException|FlashyAuthenticationException|FlashyClientException
     */
    public function unsubscribe($contact, $lists, $primary_key = "email", $tracking = true)
    {
        if( gettype($contact) !== 'array' )
        {
            $contact = [$primary_key => $contact];
        }

        if( gettype($lists) !== "array" )
        {
            $lists = [$lists];
        }

        foreach( $lists as $list )
        {
            $contact["lists"][$list] = 0;
        }

        return $this->create($contact, $primary_key, $tracking, true);
    }

    /**
     * Delete a contact
     * @param $identifier
     * @param string $primary_key
     * @return Response
     * @throws FlashyClientException|FlashyResponseException|FlashyAuthenticationException
     */
    public function block($identifier, $primary_key = "email")
    {
        return $this->flashy->client->post("contact/" . $identifier . "/block?primary_key=" . $primary_key);
    }

    /**
     * Get all lists of a contact
     * @param $identifier
     * @param string $primary_key
     * @return Response
     * @throws FlashyClientException
     * @throws FlashyClientException|FlashyResponseException|FlashyAuthenticationException
     */
    public function lists($identifier, $primary_key = "email")
    {
        return $this->flashy->client->get("contact/" . $identifier . "/lists?primary_key=" . $primary_key);
    }

    /**
     * @param bool $flat
     * @return Response
     * @throws FlashyClientException|FlashyResponseException|FlashyAuthenticationException
     */
    public function properties($flat = true)
    {
        return $this->flashy->client->get('properties?property=contact&flat=' . $flat);
    }

}
