<?php

namespace Flashy;

use Flashy\Exceptions\FlashyAuthenticationException;
use Flashy\Exceptions\FlashyClientException;
use Flashy\Exceptions\FlashyResponseException;

class Client
{
    /**
     * @var string API Key to authenticate
     */
    protected $apikey;

    /**
     * @var mixed
     */
    protected $ch;

    /**
     * @var string
     */
    protected $base_path = 'https://api.flashy.app/';

    /**
     * @var bool Debug Mode
     */
    protected $debug = false;

    /**
     * @var bool
     */
    protected $verbose = false;

    /**
     * @var int
     */
    protected $connection_timeout = 10;

    /**
     * @var int
     */
    protected $timeout = 600;

    /**
     * Client constructor.
     * @param $api_key
     */
    public function __construct($api_key = null)
    {
        $this->setApikey($api_key);
    }

    /**
     * @param $endpoint
     * @return Response
     * @throws FlashyClientException
     * @throws FlashyResponseException|FlashyAuthenticationException
     */
    public function get($endpoint)
    {
        return $this->call("GET", $endpoint);
    }

    /**
     * @param $endpoint
     * @param array $params
     * @return Response
     * @throws FlashyClientException
     * @throws FlashyResponseException|FlashyAuthenticationException
     */
    public function post($endpoint, $params = [])
    {
        return $this->call("POST", $endpoint, $params);
    }

    /**
     * @param $endpoint
     * @param array $params
     * @return Response
     * @throws FlashyClientException
     * @throws FlashyResponseException|FlashyAuthenticationException
     */
    public function put($endpoint, $params = [])
    {
        return $this->call("PUT", $endpoint, $params);
    }

    /**
     * @param $endpoint
     * @return Response
     * @throws FlashyClientException
     * @throws FlashyResponseException|FlashyAuthenticationException
     */
    public function delete($endpoint)
    {
        return $this->call("DELETE", $endpoint);
    }

    /**
     * @param $method
     * @param $url
     * @param null $payload
     * @return Response
     * @throws FlashyClientException
     * @throws FlashyResponseException
     * @throws FlashyAuthenticationException
     */
    public function call($method, $url, $payload = null)
    {
        $this->ch = curl_init();

        $endpoint = $this->base_path . $url;

        $headers = [];

        curl_setopt($this->ch, CURLOPT_USERAGENT, 'Flashy-PHP/2.0.0');
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->ch, CURLOPT_HEADER, false);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, $this->connection_timeout);
        curl_setopt($this->ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($this->ch, CURLOPT_URL, $endpoint);
        curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, $method);

        if( $this->apikey )
            curl_setopt($this->ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'x-api-key: ' . $this->apikey));

        if( $this->verbose )
            curl_setopt($this->ch, CURLOPT_VERBOSE, $this->verbose);

        if( gettype($payload) == "array" )
        {
            $payload = json_encode($payload);

            curl_setopt($this->ch, CURLOPT_POSTFIELDS, $payload);
        }

        $start = microtime(true);

        $this->log('Call to endpoint: ' . strtoupper($method) . ' ' . $endpoint . ' payload: ' . $payload);

        curl_setopt($this->ch, CURLOPT_HEADERFUNCTION, function($curl, $header) use (&$headers) {
            $len = strlen($header);

            $header = explode(':', $header, 2);

            if (count($header) < 2)
                return $len;

            $headers[strtolower(trim($header[0]))][] = trim($header[1]);

            return $len;
        });

        $response_body = curl_exec($this->ch);

        $this->log($headers);

        $info = curl_getinfo($this->ch);

        if( $info['http_code'] == 401 )
        {
            $decode_body = json_decode($response_body);

            $errCode = isset($decode_body->errorCode) ? $decode_body->errorCode : $info['http_code'];

            throw new FlashyAuthenticationException("Flashy API Key is not authenticated", $errCode);
        }

        $time = microtime(true) - $start;

        $this->log('Completed in ' . number_format($time * 1000, 2) . 'ms');

        $this->log('Got response: ' . $response_body);

        if( curl_error($this->ch) )
        {
            throw new FlashyClientException("API call to $url failed: " . curl_error($this->ch));
        }
        return new Response($response_body, true, $headers);
    }

    /**
     * @param $msg
     */
    public function log($msg)
    {
        if( $this->debug )
        {
            Helper::log($msg);
        }
    }

    /**
     * @param mixed $apikey
     */
    public function setApikey($apikey)
    {
        $this->apikey = $apikey;
    }

    /**
     * @return string
     */
    public function getBasePath()
    {
        return $this->base_path;
    }

    /**
     * @param string $base_path
     */
    public function setBasePath($base_path)
    {
        $this->base_path = $base_path;
    }

    /**
     * @return bool
     */
    public function isDebug()
    {
        return $this->debug;
    }

    /**
     * @param bool $debug
     */
    public function setDebug($debug)
    {
        $this->debug = $debug;
    }

}
