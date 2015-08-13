<?php

class CM_Maintenance_Cli extends CM_Cli_Runnable_Abstract {

    /** @var CM_Clockwork_Manager */
    protected $_clockworkManager;

    /**
     * @synchronized
     * @keepalive
     */
    public function start() {
        $this->_clockworkManager = new CM_Clockwork_Manager();
        $storage = new CM_Clockwork_Storage_FileSystem('app-maintenance');
        $storage->setServiceManager(CM_Service_Manager::getInstance());
        $this->_clockworkManager->setStorage($storage);
        $this->_registerCallbacks();
        $this->_clockworkManager->start();
    }

    /**
     * @keepalive
     */
    public function startLocal() {
        $this->_clockworkManager = new CM_Clockwork_Manager();
        $storage = new CM_Clockwork_Storage_FileSystem('app-maintenance-local');
        $storage->setServiceManager(CM_Service_Manager::getInstance());
        $this->_clockworkManager->setStorage($storage);
        $this->_registerCallbacksLocal();
        $this->_clockworkManager->start();
    }

    protected function _registerCallbacks() {
        $this->_registerClockworkCallbacks('10 seconds', array(
            'CM_Model_User::offlineDelayed' => function () {
                CM_Model_User::offlineDelayed();
            }
        ));
        $this->_registerClockworkCallbacks('1 minute', array(
            'CM_Model_User::offlineOld'                 => function () {
                CM_Model_User::offlineOld();
            },
            'CM_ModelAsset_User_Roles::deleteOld'       => function () {
                CM_ModelAsset_User_Roles::deleteOld();
            },
            'CM_Paging_Useragent_Abstract::deleteOlder' => function () {
                CM_Paging_Useragent_Abstract::deleteOlder(100 * 86400);
            },
            'CM_File_UserContent_Temp::deleteOlder'     => function () {
                CM_File_UserContent_Temp::deleteOlder(86400);
            },
            'CM_SVM_Model::deleteOldTrainings'          => function () {
                CM_SVM_Model::deleteOldTrainings(3000);
            },
            'CM_Paging_Ip_Blocked::deleteOlder'         => function () {
                CM_Paging_Ip_Blocked::deleteOld();
            },
            'CM_Captcha::deleteOlder'                   => function () {
                CM_Captcha::deleteOlder(3600);
            },
            'CM_Session::deleteExpired'                 => function () {
                CM_Session::deleteExpired();
            },
            'CM_Stream_Video::synchronize'              => function () {
                CM_Stream_Video::getInstance()->synchronize();
            },
            'CM_Stream_Video::checkStreams'             => function () {
                CM_Stream_Video::getInstance()->checkStreams();
            },
            'CM_MessageStream_Service::synchronize'            => function () {
                CM_Service_Manager::getInstance()->getStreamMessage()->synchronize();
            }
        ));
        $this->_registerClockworkCallbacks('15 minutes', array(
            'CM_Mail::processQueue'                         => function () {
                CM_Mail::processQueue(500);
            },
            'CM_Action_Abstract::aggregate'                 => function () {
                CM_Action_Abstract::aggregate();
            },
            'CM_Action_Abstract::deleteTransgressionsOlder' => function () {
                CM_Action_Abstract::deleteTransgressionsOlder(3 * 31 * 86400);
            },
            'CM_Paging_Log_Abstract::cleanup'               => function () {
                foreach (CM_Paging_Log_Abstract::getClassChildren() as $logClass) {
                    /** @var CM_Paging_Log_Abstract $log */
                    $log = new $logClass();
                    $log->cleanUp();
                }
            }
        ));
        $this->_registerClockworkCallbacks('8 days', array(
            'CMService_MaxMind::upgrade' => function () {
                try {
                    $maxMind = new CMService_MaxMind();
                    $maxMind->upgrade();
                } catch (Exception $exception) {
                    if (!is_a($exception, 'CM_Exception')) {
                        $exception = new CM_Exception($exception->getMessage(), null, [
                            'file'  => $exception->getFile(),
                            'line'  => $exception->getLine(),
                            'trace' => $exception->getTraceAsString(),
                        ]);
                    }
                    $exception->setSeverity(CM_Exception::FATAL);
                    throw $exception;
                }
            }
        ));
    }

    protected function _registerCallbacksLocal() {
        $this->_registerClockworkCallbacks('1 minute', array(
            'CM_Cli_CommandManager::monitorSynchronizedCommands' => function () {
                $commandManager = new CM_Cli_CommandManager();
                $commandManager->monitorSynchronizedCommands();
            },
            'CM_SVM_Model::trainChanged'                         => function () {
                CM_SVM_Model::trainChanged();
            },
        ));
    }

    public static function getPackageName() {
        return 'maintenance';
    }

    /**
     * @param string    $dateTimeString
     * @param Closure[] $callbacks
     */
    protected function _registerClockworkCallbacks($dateTimeString, $callbacks) {
        foreach ($callbacks as $name => $callback) {
            $transactionName = 'cm ' . static::getPackageName() . ' start: ' . $name;
            $this->_clockworkManager->registerCallback($name, $dateTimeString, function () use ($transactionName, $callback) {
                CM_Service_Manager::getInstance()->getNewrelic()->startTransaction($transactionName);
                try {
                    $callback();
                } catch (CM_Exception $e) {
                    CM_Bootloader::getInstance()->getExceptionHandler()->handleException($e);
                }
                CM_Service_Manager::getInstance()->getNewrelic()->endTransaction();
            });
        }
    }
}
