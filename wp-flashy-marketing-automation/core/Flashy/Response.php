<?php

namespace Flashy;

use Flashy\Exceptions\FlashyResponseException;

class Response implements \ArrayAccess
{

    /**
     * @var object|null
     */
    private $body;

    /**
     * @var array
     */
    private $headers;

    /**
     * Response constructor.
     * @param $response
     * @param bool $decode
     * @param $headers
     * @throws FlashyResponseException
     */
    public function __construct($response, $decode = true, $headers = [])
    {
        if( $decode === true )
        {
            $this->body = $this->decode($response);
        }
        else
        {
            $this->body = $response;
        }

        $this->headers = $headers;
    }

    /**
     * @param $response
     * @return array|bool|float|int|mixed
     * @throws FlashyResponseException
     */
    public function decode($response)
    {
        $result = json_decode($response, true);

        if ($result === null && json_last_error() !== JSON_ERROR_NONE)
        {
            throw new FlashyResponseException('Unable to decode json string.');
        }

        return $result;
    }

    /**
     * @return array|mixed
     */
    public function getErrors()
    {
        if( isset($this->body['errors']) )
            return $this->body['errors'];

        return array();
    }

    /**
     * @return bool
     */
    public function hasErrors()
    {
        return count($this->getErrors()) > 0;
    }

    /**
     * @return bool
     */
    public function success()
    {
        return ( isset($this->body['success']) ) ? $this->body['success'] : false;
    }

    /**
     * @return array|mixed
     */
    public function getData()
    {
        if( isset($this->body['data']) )
            return $this->body['data'];

        return array();
    }

    /**
     * @return array|bool|float|int|mixed|object|null
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * @param $key
     * @return mixed|null
     */
    public function __get($key)
    {
        if( ! isset($this->body['data'][$key]) )
            return null;

        return $this->body['data'][$key];
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->body['data'][$offset]);
    }

    /**
     * @param mixed $offset
     * @return mixed|null
     */
    public function offsetGet($offset)
    {
        return $this->$offset;
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        $this->body['data'][$offset] = $value;
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        unset($this->body['data'][$offset]);
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->body;
    }

}
