<?php

class CM_Jobdistribution_JobWorker extends CM_Class_Abstract implements CM_Service_ManagerAwareInterface {

    use CM_Service_ManagerAwareTrait;

    /** @var GearmanWorker */
    private $_gearmanWorker;

    public function __construct() {
        $worker = $this->_getGearmanWorker();
        $config = self::_getConfig();
        foreach ($config->servers as $server) {
            $worker->addServer($server['host'], $server['port']);
        }
    }

    /**
     * @param CM_Jobdistribution_Job_Abstract $job
     */
    public function registerJob(CM_Jobdistribution_Job_Abstract $job) {
        $this->_gearmanWorker->addFunction(get_class($job), array($job, '__executeGearman'));
    }

    public function run() {
        while (true) {
            $workFailed = false;
            try {
                CM_Cache_Storage_Runtime::getInstance()->flush();
                $workFailed = !$this->_getGearmanWorker()->work();
            } catch (Exception $ex) {
                $this->getServiceManager()->getLogger()->addMessage('Worker failed', CM_Log_Logger::exceptionToLevel($ex), (new CM_Log_Context())->setException($ex));
            }
            if ($workFailed) {
                throw new CM_Exception_Invalid('Worker failed');
            }
        }
    }

    /**
     * @return GearmanWorker
     * @throws CM_Exception
     */
    protected function _getGearmanWorker() {
        if (!$this->_gearmanWorker) {
            if (!extension_loaded('gearman')) {
                throw new CM_Exception('Missing `gearman` extension');
            }
            $this->_gearmanWorker = new GearmanWorker();
        }
        return $this->_gearmanWorker;
    }
}
