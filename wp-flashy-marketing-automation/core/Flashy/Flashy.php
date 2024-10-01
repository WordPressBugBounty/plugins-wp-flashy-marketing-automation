<?php

namespace Flashy;

use Flashy\Exceptions\FlashyException;
use Flashy\Services\Account;
use Flashy\Services\Contacts;
use Flashy\Services\Events;
use Flashy\Services\Lists;
use Flashy\Services\Messages;
use Flashy\Services\Platforms;

/**
 * @property Account account
 * @property Platforms platforms
 * @property Contacts contacts
 * @property Lists lists
 * @property Events events
 * @property Messages messages
 */
class Flashy
{
    /**
     * @var array
     */
    protected $config = [];

    /**
     * @var Client
     */
    public $client;

    /**
     * @var array
     */
    protected $services = [];

    /**
     * Flashy constructor.
     * @param $config
     * @throws FlashyException
     */
    public function __construct($config)
    {
        $this->loadDependencies();

        $this->config = array_merge($this->config, $config);

        if( !isset($this->config['api_key']) )
        {
            throw new FlashyException("Flashy API Key missing");
        }

        $this->loadConfig();

        $this->client = new Client($this->config['api_key']);
    }

    /**
     * Set Config Options
     */
    private function loadConfig()
    {
        if( isset($this->config['log_path']) )
        {
            Helper::$path = $this->config['log_path'];
        }
    }

    /**
     * @param $service
     * @return mixed
     * @throws FlashyException
     */
    public function __get($service)
    {
        $service = strtolower($service);

        if( isset($this->services[$service]) )
        {
            return $this->getService($service);
        }

        if( !class_exists($this->getServiceNamespace($service)) && file_exists($this->getServicePath($service)) )
        {
            require_once($this->getServicePath($service));
        }

        if( class_exists($this->getServiceNamespace($service)) )
        {
            $serviceName = $this->getServiceNamespace($service);

            $this->services[$service] = new $serviceName($this);

            return $this->getService($service);
        }

        throw new FlashyException("Service " . $this->getServiceName($service) . " not exists");
    }

    /**
     * @param $service
     * @return string
     */
    private function getServiceNamespace($service)
    {
        return "Flashy\\Services\\" . $this->getServiceName($service);
    }

    /**
     * @param $service
     * @return string
     */
    public function getServiceName($service)
    {
        return ucfirst($service);
    }

    /**
     * @param $service
     * @return mixed
     */
    private function getService($service)
    {
        return $this->services[$service];
    }

    /**
     * @param $service
     * @return string
     */
    public function getServicePath($service)
    {
        return __DIR__ . "/Services/" . $this->getServiceName($service) . ".php";
    }

    /**
     * Load Flashy Dependencies - auto loader for non composer projects
     */
    private function loadDependencies()
    {
        $dependencies = ['Helper', 'Response', 'Client'];

        foreach( $dependencies as $dependency )
        {
            if( !class_exists('Flashy\\' . $dependency) )
			{
				if( $dependency === 'Response' && version_compare(PHP_VERSION, '8', '>=') )
				{
					require_once(__DIR__ . '/Response8.php');
				}
				else
				{
					require_once(__DIR__ . '/' . $dependency . '.php');
				}
			}
        }

        if( !class_exists('Flashy\\FlashyException') )
        {
            $path = __DIR__ . "/Exceptions/";

            foreach(scandir($path) as $filename)
            {
                if ( is_file($path . $filename) )
                {
                    require_once $path . $filename;
                }
            }
        }
    }

}
