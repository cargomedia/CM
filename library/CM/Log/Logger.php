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
    protected static $_levels = [
        self::DEBUG    => 'DEBUG',
        self::INFO     => 'INFO',
        self::WARNING  => 'WARNING',
        self::ERROR    => 'ERROR',
        self::CRITICAL => 'CRITICAL',
    ];

    /** @var  CM_Log_Handler_HandlerInterface */
    private $_handler;

    /** @var CM_Log_Context */
    private $_context;

    /**
     * @param CM_Log_Context|null                  $context
     * @param CM_Log_Handler_HandlerInterface|null $handler
     * @throws CM_Exception_Invalid
     */
    public function __construct(CM_Log_Context $context = null, CM_Log_Handler_HandlerInterface $handler = null) {
        if (null !== $context) {
            $this->setContext($context);
        }
        if (null !== $handler) {
            $this->setHandler($handler);
        }
    }

    /**
     * @param CM_Log_Handler_HandlerInterface $handler
     */
    public function setHandler($handler) {
        $this->_handler = $handler;
    }

    /**
     * @return CM_Log_Handler_HandlerInterface
     * @throws CM_Exception
     */
    public function getHandler() {
        if (null === $this->_handler) {
            throw new CM_Exception('Handler not set');
        }
        return $this->_handler;
    }

    /**
     * @param CM_Log_Context $context
     */
    public function setContext($context) {
        $this->_context = $context;
    }

    /**
     * @return CM_Log_Context
     * @throws CM_Exception
     */
    public function getContext() {
        if (null === $this->_context) {
            throw new CM_Exception('Context not set');
        }
        return $this->_context;
    }

    /**
     * @param string              $message
     * @param int                 $level
     * @param CM_Log_Context|null $context
     * @return CM_Log_Logger
     */
    public function addMessage($message, $level, CM_Log_Context $context = null) {
        $message = (string) $message;
        $level = (int) $level;

        $recordContext = clone $this->_context;
        if ($context) {
            $recordContext->merge($context);
        }
        return $this->_addRecord(new CM_Log_Record($level, $message, $recordContext));
    }

    /**
     * @param string                  $message
     * @param CM_Log_Context|null $context
     * @return CM_Log_Logger
     */
    public function debug($message, CM_Log_Context $context = null) {
        return $this->addMessage($message, self::DEBUG, $context);
    }

    /**
     * @param string                  $message
     * @param CM_Log_Context|null $context
     * @return CM_Log_Logger
     */
    public function info($message, CM_Log_Context $context = null) {
        return $this->addMessage($message, self::INFO, $context);
    }

    /**
     * @param string                  $message
     * @param CM_Log_Context|null $context
     * @return CM_Log_Logger
     */
    public function warning($message, CM_Log_Context $context = null) {
        return $this->addMessage($message, self::WARNING, $context);
    }

    /**
     * @param string                  $message
     * @param CM_Log_Context|null $context
     * @return CM_Log_Logger
     */
    public function error($message, CM_Log_Context $context = null) {
        return $this->addMessage($message, self::ERROR, $context);
    }

    /**
     * @param string                  $message
     * @param CM_Log_Context|null $context
     * @return CM_Log_Logger
     */
    public function critical($message, CM_Log_Context $context = null) {
        return $this->addMessage($message, self::CRITICAL, $context);
    }

    /**
     * @param CM_Log_Record $record
     * @return CM_Log_Logger
     */
    protected function _addRecord(CM_Log_Record $record) {
        $this->getHandler()->handleRecord($record);
        return $this;
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

    /**
     * @param Exception $exception
     * @return int
     */
    public static function exceptionSeverityToLevel(Exception $exception) {
        $severity = $exception instanceof CM_Exception ? $exception->getSeverity() : null;
        $map = [
            CM_Exception::WARN  => CM_Log_Logger::WARNING,
            CM_Exception::ERROR => CM_Log_Logger::ERROR,
            CM_Exception::FATAL => CM_Log_Logger::CRITICAL,
        ];
        return isset($map[$severity]) ? $map[$severity] : CM_Log_Logger::ERROR;
    }
}
