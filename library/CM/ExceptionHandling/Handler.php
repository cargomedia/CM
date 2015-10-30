<?php

class CM_ExceptionHandling_Handler implements CM_Service_ManagerAwareInterface {

    use CM_Service_ManagerAwareTrait;

    /** @var CM_Log_Factory */
    private $_loggerFactory;

    private $_errorCodes = array(
        E_ERROR             => 'E_ERROR',
        E_WARNING           => 'E_WARNING',
        E_PARSE             => 'E_PARSE',
        E_NOTICE            => 'E_NOTICE',
        E_CORE_ERROR        => 'E_CORE_ERROR',
        E_CORE_WARNING      => 'E_CORE_WARNING',
        E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
        E_COMPILE_WARNING   => 'E_COMPILE_WARNING',
        E_USER_ERROR        => 'E_USER_ERROR',
        E_USER_WARNING      => 'E_USER_WARNING',
        E_USER_NOTICE       => 'E_USER_NOTICE',
        E_STRICT            => 'E_STRICT',
        E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
        E_DEPRECATED        => 'E_DEPRECATED',
        E_USER_DEPRECATED   => 'E_USER_DEPRECATED',
        E_ALL               => 'E_ALL',
    );

    /**
     * @param CM_Log_Factory $loggerFactory
     */
    public function __construct(CM_Log_Factory $loggerFactory) {
        $this->_loggerFactory = $loggerFactory;
    }

    public function handleErrorFatal() {
        if ($error = error_get_last()) {
            $code = isset($error['type']) ? $error['type'] : E_CORE_ERROR;
            if (0 === ($code & error_reporting())) {
                return;
            }
            $message = isset($error['message']) ? $error['message'] : '';
            $file = isset($error['file']) ? $error['file'] : 'unknown file';
            $line = isset($error['line']) ? $error['line'] : 0;
            if (isset($this->_errorCodes[$code])) {
                $message = $this->_errorCodes[$code] . ': ' . $message;
            }
            $exception = new ErrorException($message, 0, $code, $file, $line);
            $this->handleException($exception);
        }
    }

    /**
     * @param int    $code
     * @param string $message
     * @param string $file
     * @param int    $line
     * @return bool
     * @throws ErrorException
     */
    public function handleErrorRaw($code, $message, $file, $line) {
        if (0 === ($code & error_reporting())) {
            return;
        }
        if (isset($this->_errorCodes[$code])) {
            $message = $this->_errorCodes[$code] . ': ' . $message;
        }
        throw new ErrorException($message, 0, $code, $file, $line);
    }

    /**
     * Implemented for retro compatibility
     * @param Exception $exception
     */
    public function logException(Exception $exception) {
        $this->handleException($exception);
    }

    /**
     * @param Exception $exception
     */
    public function handleException(Exception $exception) {
        try {
            $this->getServiceManager()->getLogger()->addException($exception);
        } catch (CM_Log_HandlingException $loggerException) {
            $backupLogger = $this->_getBackupLogger();
            $backupLogger
                ->error('Origin exception:')->addException($exception)
                ->error('Handlers exception:')
                ->error($loggerException->getMessage());

            foreach ($loggerException->getExceptionList() as $handlerException) {
                $backupLogger->addException($handlerException);
            }
        } catch (Exception $loggerException) {
            $this->_getBackupLogger()
                ->error('Origin exception:')->addException($exception)
                ->error('Logger exception:')->addException($loggerException);
        }
    }

    /**
     * @return CM_Log_Logger
     */
    protected function _getBackupLogger() {
        return $this->_loggerFactory->createBackupLogger();
    }
}
