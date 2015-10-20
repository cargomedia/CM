<?php

class CM_Service_Manager extends CM_Class_Abstract {

    /** @var array */
    private $_serviceConfigList = array();

    /** @var array */
    private $_serviceInstanceList = array();

    /** @var CM_Service_Manager */
    protected static $instance;

    /**
     * @param string $serviceName
     * @return bool
     */
    public function has($serviceName) {
        $hasConfig = array_key_exists($serviceName, $this->_serviceConfigList);
        $hasInstance = array_key_exists($serviceName, $this->_serviceInstanceList);
        return $hasConfig || $hasInstance;
    }

    /**
     * @param string      $serviceName
     * @param string|null $assertInstanceOf
     * @throws CM_Exception_Invalid
     * @return mixed
     */
    public function get($serviceName, $assertInstanceOf = null) {
        if (!array_key_exists($serviceName, $this->_serviceInstanceList)) {
            $this->_serviceInstanceList[$serviceName] = $this->_instantiateService($serviceName);
        }
        $service = $this->_serviceInstanceList[$serviceName];
        if (null !== $assertInstanceOf && !is_a($service, $assertInstanceOf, true)) {
            throw new CM_Exception_Invalid('Service `' . $serviceName . '` is a `' . get_class($service) . '`, but not `' . $assertInstanceOf . '`.');
        }
        return $service;
    }

    /**
     * @param string      $serviceName
     * @param string      $className
     * @param array|null  $arguments
     * @param string|null $methodName
     * @param array|null  $methodArguments
     * @throws CM_Exception_Invalid
     */
    public function register($serviceName, $className, array $arguments = null, $methodName = null, array $methodArguments = null) {
        $config = array(
            'class'     => $className,
            'arguments' => $arguments,
        );
        if (null !== $methodName) {
            $config['method'] = array(
                'name'      => $methodName,
                'arguments' => $methodArguments
            );
        }
        $this->registerWithArray($serviceName, $config);
    }

    /**
     * @param string $serviceName
     * @param array  $config
     * @throws CM_Exception_Invalid
     */
    public function registerWithArray($serviceName, array $config) {
        if ($this->has($serviceName)) {
            throw new CM_Exception_Invalid('Service `' . $serviceName . '` already registered.');
        }
        $class = (string) $config['class'];
        $arguments = array();
        if (isset($config['arguments'])) {
            $arguments = (array) $config['arguments'];
        }
        $method = null;
        if (isset($config['method'])) {
            $methodName = (string) $config['method']['name'];
            $methodArguments = array();
            if (isset($config['method']['arguments'])) {
                $methodArguments = (array) $config['method']['arguments'];
            }
            $method = array('name' => $methodName, 'arguments' => $methodArguments);
        }

        $this->_serviceConfigList[$serviceName] = array(
            'class'     => $class,
            'arguments' => $arguments,
            'method'    => $method,
        );
    }

    /**
     * @param string $serviceName
     * @param mixed  $instance
     * @throws CM_Exception_Invalid
     */
    public function registerInstance($serviceName, $instance) {
        if ($this->has($serviceName)) {
            throw new CM_Exception_Invalid('Service `' . $serviceName . '` already registered.');
        }
        $serviceName = (string) $serviceName;
        if ($instance instanceof CM_Service_ManagerAwareInterface) {
            $instance->setServiceManager($this);
        }
        $this->_serviceInstanceList[$serviceName] = $instance;
    }

    public function resetServiceInstances() {
        $this->_serviceInstanceList = [];
    }

    /**
     * @param string $serviceName
     */
    public function unregister($serviceName) {
        unset($this->_serviceConfigList[$serviceName]);
        unset($this->_serviceInstanceList[$serviceName]);
    }

    /**
     * Methods in format get[serviceName] returns a instance of a service with given name.
     *
     * @param string $name
     * @param mixed  $parameters
     * @return mixed
     * @throws CM_Exception_Invalid
     */
    public function __call($name, $parameters) {
        if (preg_match('/get(.+)/', $name, $matches)) {
            $serviceName = $matches[1];
            return $this->get($serviceName);
        }
        throw new CM_Exception_Invalid('Cannot extract service name from `' . $name . '`.');
    }

    /**
     * @return CM_Service_Databases
     */
    public function getDatabases() {
        return $this->get('databases', 'CM_Service_Databases');
    }

    /**
     * @param string|null $serviceName
     * @return CM_MongoDb_Client
     */
    public function getMongoDb($serviceName = null) {
        if (null === $serviceName) {
            $serviceName = 'MongoDb';
        }
        return $this->get($serviceName, 'CM_MongoDb_Client');
    }

    /**
     * @return CM_Options
     * @throws CM_Exception_Invalid
     */
    public function getOptions() {
        return $this->get('options', 'CM_Options');
    }

    /**
     * @return CM_Service_Filesystems
     */
    public function getFilesystems() {
        return $this->get('filesystems', 'CM_Service_Filesystems');
    }

    /**
     * @return CM_Debug
     */
    public function getDebug() {
        return $this->get('debug', 'CM_Debug');
    }

    /**
     * @return CM_Service_Trackings
     */
    public function getTrackings() {
        return $this->get('trackings', 'CM_Service_Trackings');
    }

    /**
     * @return CM_Service_UserContent
     */
    public function getUserContent() {
        return $this->get('usercontent', 'CM_Service_UserContent');
    }

    /**
     * @return CM_Wowza_Service
     */
    public function getWowza() {
        return $this->get('wowza', 'CM_Wowza_Service');
    }

    /**
     * @return CM_Memcache_Client
     */
    public function getMemcache() {
        return $this->get('memcache', 'CM_Memcache_Client');
    }

    /**
     * @return CM_MessageStream_Service
     */
    public function getStreamMessage() {
        return $this->get('stream-message', 'CM_MessageStream_Service');
    }

    /**
     * @return CM_Redis_Client
     */
    public function getRedis() {
        return $this->get('redis', 'CM_Redis_Client');
    }

    /**
     * @return CM_Elasticsearch_Cluster
     */
    public function getElasticsearch() {
        return $this->get('elasticsearch', 'CM_Elasticsearch_Cluster');
    }

    /**
     * @return CMService_Newrelic
     */
    public function getNewrelic() {
        return $this->get('newrelic', 'CMService_Newrelic');
    }

    /**
     * @param string $serviceName
     * @throws CM_Exception_Invalid
     * @return mixed
     */
    protected function _instantiateService($serviceName) {
        if (!array_key_exists($serviceName, $this->_serviceConfigList)) {
            throw new CM_Exception_Invalid("Service {$serviceName} has no config.");
        }
        $config = $this->_serviceConfigList[$serviceName];
        $reflection = new ReflectionClass($config['class']);

        $arguments = $config['arguments'];
        if ($constructor = $reflection->getConstructor()) {
            $arguments = $this->_matchNamedArgs($serviceName, $constructor, $arguments);
        }
        $instance = $reflection->newInstanceArgs($arguments);

        if ($instance instanceof CM_Service_ManagerAwareInterface) {
            $instance->setServiceManager($this);
        }

        if (null !== $config['method']) {
            $method = $reflection->getMethod($config['method']['name']);
            $methodArguments = $this->_matchNamedArgs($serviceName, $method, $config['method']['arguments']);
            $instance = $method->invokeArgs($instance, $methodArguments);
        }
        return $instance;
    }

    /**
     * @param string           $serviceName
     * @param ReflectionMethod $method
     * @param array            $arguments
     * @throws CM_Exception_Invalid
     * @return array
     */
    protected function _matchNamedArgs($serviceName, ReflectionMethod $method, array $arguments) {
        $namedArgs = new CM_Util_NamedArgs();
        try {
            return $namedArgs->matchNamedArgs($method, $arguments);
        } catch (CM_Exception_Invalid $e) {
            throw new CM_Exception_Invalid("Cannot match arguments for `{$serviceName}`: {$e->getMessage()}");
        }
    }

    /**
     * @return CM_Service_Manager
     */
    public static function getInstance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
