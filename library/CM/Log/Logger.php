<?php

class CM_Log_Logger {

    const DEBUG = 100;
    const INFO = 200;
    const WARNING = 300;
    const ERROR = 400;
    const CRITICAL = 500;

    /**
     * @var array $levels Logging levels
     */
    protected static $_levels = array(
        self::DEBUG    => 'DEBUG',
        self::INFO     => 'INFO',
        self::WARNING  => 'WARNING',
        self::ERROR    => 'ERROR',
        self::CRITICAL => 'CRITICAL',
    );

    /** @var array */
    private $_handlersLayerList;

    /** @var CM_Log_Context */
    private $_contextGlobal;

    /**
     * @param CM_Log_Context $contextGlobal
     * @param array          $handlersLayerList
     */
    public function __construct(CM_Log_Context $contextGlobal, array $handlersLayerList) {
        $this->_contextGlobal = $contextGlobal;
        foreach ($handlersLayerList as $handlersLayer) {
            $this->_addHandlersLayer($handlersLayer);
        }
    }

    /**
     * @param string              $message
     * @param int                 $level
     * @param CM_Log_Context|null $context
     * @return CM_Log_Logger
     * @throws CM_Log_HandlingException
     */
    public function addMessage($message, $level, CM_Log_Context $context = null) {
        $message = (string) $message;
        $level = (int) $level;
        $context = $this->_mergeWithGlobalContext($context);
        return $this->_addRecord(new CM_Log_Record($level, $message, $context));
    }

    /**
     * @param Exception           $exception
     * @param CM_Log_Context|null $context
     * @param int|null            $logLevel
     * @return CM_Log_Logger
     */
    public function addException(Exception $exception, CM_Log_Context $context = null, $logLevel = null) {
        $context = $this->_mergeWithGlobalContext($context);
        return $this->_addRecord(new CM_Log_Record_Exception($exception, $context, $logLevel));
    }

    /**
     * @param string              $message
     * @param CM_Log_Context|null $context
     * @return CM_Log_Logger
     */
    public function debug($message, CM_Log_Context $context = null) {
        return $this->addMessage($message, self::DEBUG, $context);
    }

    /**
     * @param string              $message
     * @param CM_Log_Context|null $context
     * @return CM_Log_Logger
     */
    public function info($message, CM_Log_Context $context = null) {
        return $this->addMessage($message, self::INFO, $context);
    }

    /**
     * @param string              $message
     * @param CM_Log_Context|null $context
     * @return CM_Log_Logger
     */
    public function warning($message, CM_Log_Context $context = null) {
        return $this->addMessage($message, self::WARNING, $context);
    }

    /**
     * @param string              $message
     * @param CM_Log_Context|null $context
     * @return CM_Log_Logger
     */
    public function error($message, CM_Log_Context $context = null) {
        return $this->addMessage($message, self::ERROR, $context);
    }

    /**
     * @param string              $message
     * @param CM_Log_Context|null $context
     * @return CM_Log_Logger
     */
    public function critical($message, CM_Log_Context $context = null) {
        return $this->addMessage($message, self::CRITICAL, $context);
    }

    /**
     * @param array $handlersLayer
     * @throws CM_Exception_Invalid
     */
    protected function _addHandlersLayer(array $handlersLayer) {
        $currentLayerIdx = sizeof($handlersLayer);
        foreach ($handlersLayer as $handler) {
            if (!$handler instanceof CM_Log_Handler_HandlerInterface) {
                throw new CM_Exception_Invalid('Not logger handler instance');
            }
            $this->_handlersLayerList[$currentLayerIdx][] = $handler;
        }
    }

    /**
     * @param CM_Log_Record $record
     * @return CM_Log_Logger
     */
    protected function _addRecord(CM_Log_Record $record) {
        $this->_addRecordToLayer($record, 0);
        return $this;
    }

    protected function _addRecordToLayer(CM_Log_Record $record, $layerIdx) {
        $layersNumber = sizeof($this->_handlersLayerList);
        if ($layerIdx >= $layersNumber) {
            throw new CM_Exception_Invalid('Wrong offset for log layer');
        }
        $handlerList = $this->_handlersLayerList[$layerIdx];
        $exceptionList = [];
        $failedHandlerIdx = [];

        $layerSize = sizeof($handlerList);
        for ($i = 0, $n = $layerSize; $i < $n; $i++) {
            /** @var CM_Log_Handler_HandlerInterface $handler */
            $handler = $handlerList[$i];
            try {
                $handler->handleRecord($record);
            } catch (Exception $e) {
                $exceptionList[] = $e;
                $failedHandlerIdx[] = $i;
            }
        }

        if (!empty($exceptionList)) {
            if ($layerSize === sizeof($exceptionList)) { //all handlers failed use next layer
                if ($layerIdx + 1 < sizeof($this->_handlersLayerList)) {
                    return; //no chances
                }
                else {
                    //use next layer
                    $this->_addRecordToLayer($record, ++$layerIdx);
                }
            } else {
                try {
                    for ($i = 0, $n = $layerSize; $i < $n; $i++) {
                        if (!in_array($i, $failedHandlerIdx)) {
                            /** @var CM_Log_Handler_HandlerInterface $handler */
                            $handler = $handlerList[$i];
                            foreach ($exceptionList as $exception) {
                                $handler->handleRecord(new CM_Log_Record_Exception($exception));
                            }
                        }
                    }
                } catch (Exception $e) {
                }
            }
        }
    }

    /**
     * @param CM_Log_Context|null $context
     * @return CM_Log_Context
     */
    protected function _mergeWithGlobalContext(CM_Log_Context $context = null) {
        if (null === $context) {
            $context = new CM_Log_Context();
        }
        return $this->_contextGlobal->merge($context);
    }

    /**
     * Gets all supported logging levels.
     *
     * @return array Assoc array with human-readable level names => level codes.
     */
    public static function getLevels() {
        return array_flip(self::$_levels);
    }

    /**
     * Gets the name of the logging level.
     *
     * @param  int $level
     * @return string
     * @throws CM_Exception_Invalid
     */
    public static function getLevelName($level) {
        $level = (int) $level;
        if (!isset(self::$_levels[$level])) {
            throw new CM_Exception_Invalid('Level `' . $level . '` is not defined, use one of: ' . implode(', ', array_keys(self::$_levels)));
        }
        return self::$_levels[$level];
    }

    /**
     * @param int $level
     * @return bool
     */
    public static function hasLevel($level) {
        $level = (int) $level;
        return isset(self::$_levels[$level]);
    }
}
