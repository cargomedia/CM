<?php

class CM_Log_Factory implements CM_Service_ManagerAwareInterface {

    use CM_Service_ManagerAwareTrait;

    /**
     * @param string[] $handlerList
     * @return CM_Log_Logger
     * @throws CM_Exception_Invalid
     */
    public function createLogger(array $handlerList) {
        $handlers = \Functional\map($handlerList, function ($handlerService) {
            return $this->getServiceManager()->get($handlerService);
        });
        return $this->_getLogger($handlers);
    }

    /**
     * @return CM_Log_Logger
     */
    public function createLoggerBasicCli() {
        $formatter = new CM_Log_Formatter_Text();
        $handlerStandardOutput = new CM_Log_Handler_Stream(new CM_OutputStream_Stream_StandardError(), $formatter, CM_Log_Logger::DEBUG, false);
        return $this->_getLogger([$handlerStandardOutput]);
    }

    /**
     * @return CM_Log_Logger
     */
    public function createLoggerBasicHttp() {
        $formatter = new CM_Log_Formatter_Html();
        $handlerOutput = new CM_Log_Handler_Stream(new CM_OutputStream_Stream_Output(), $formatter, CM_Log_Logger::ERROR, false);
        return $this->_getLogger([$handlerOutput]);
    }

    /**
     * @param CM_Log_Handler_HandlerInterface[] $handlers
     * @return CM_Log_Logger
     */
    protected function _getLogger($handlers) {
        $computerInfo = new CM_Log_Context_ComputerInfo(CM_Util::getFqdn(), phpversion());
        $globalContext = new CM_Log_Context(null, null, $computerInfo);
        return new CM_Log_Logger($globalContext, $handlers);
    }
}
