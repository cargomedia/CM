<?php

class CM_Clockwork_Manager extends CM_Service_ManagerAware {

    /** @var CM_Clockwork_Event[] */
    private $_events;

    /** @var DateTime */
    private $_startTime;

    /** @var CM_Clockwork_Event[] */
    private $_eventsRunning = array();

    /** @var CM_Clockwork_Storage_Abstract */
    private $_storage;

    /** @var DateTimeZone */
    private $_timeZone;

    public function __construct() {
        $this->_events = array();
        $this->_storage = new CM_Clockwork_Storage_Memory();
        $this->_timeZone = CM_Bootloader::getInstance()->getTimeZone();
        $this->_startTime = $this->_getCurrentDateTimeUTC();
    }

    /**
     * @param string      $name
     * @param string      $dateTimeString
     * @param callable    $callback
     */
    public function registerCallback($name, $dateTimeString, $callback) {
        $event = new CM_Clockwork_Event($name, $dateTimeString);
        $event->registerCallback($callback);
        $this->registerEvent($event);
    }

    /**
     * @param CM_Clockwork_Event $event
     */
    public function registerEvent(CM_Clockwork_Event $event) {
        $this->_events[] = $event;
    }

    public function runEvents($noWaitOnEventExecution = null) {
        $process = $this->_getProcess();
        $noWaitOnEventExecution = (boolean) $noWaitOnEventExecution;
        if ($noWaitOnEventExecution) {
            $process->listenForChildren();
        }
        /** @var CM_Clockwork_Event[] $eventsToRun */
        $eventsToRun = array();
        foreach ($this->_events as $event) {
            if (!$this->_isRunning($event) && $this->_shouldRun($event)) {
                $eventsToRun[] = $event;
            }
        }
        foreach ($eventsToRun as $event) {
            $this->_runEvent($event);
        }
        if (!$noWaitOnEventExecution) {
            $process->waitForChildren();
        }
    }

    /**
     * @param CM_Clockwork_Storage_Abstract $storage
     */
    public function setStorage(CM_Clockwork_Storage_Abstract $storage) {
        $this->_storage = $storage;
        $this->_storage->setServiceManager($this->getServiceManager());
    }

    /**
     * @param DateTimeZone $timeZone
     */
    public function setTimeZone(DateTimeZone $timeZone) {
        $this->_timeZone = $timeZone;
    }

    public function start() {
        while (true) {
            $this->runEvents(true);
            sleep(1);
        }
    }

    /**
     * @param CM_Clockwork_Event $event
     * @return boolean
     */
    protected function _shouldRun(CM_Clockwork_Event $event) {
        $lastRuntime = $this->_storage->getLastRuntime($event);
        $base = $lastRuntime ?: clone $this->_startTime;
        $dateTimeString = $event->getDateTimeString();
        if (!$this->_isIntervalEvent($event)) {     // do not set timezone for interval-based events due to buggy behaviour with timezones that use
            $base->setTimezone($this->_timeZone);   // daylight saving time, see https://bugs.php.net/bug.php?id=51051
        }
        $nextExecutionTime = clone $base;
        $nextExecutionTime->modify($dateTimeString);
        if ($lastRuntime) {
            if ($nextExecutionTime <= $base) {
                $nextExecutionTime = $this->_getCurrentDateTime()->modify($dateTimeString);
            }
            $shouldRun = $nextExecutionTime > $base && $this->_getCurrentDateTime() >= $nextExecutionTime;
        } else {
            if ($nextExecutionTime < $base) {
                $nextExecutionTime = $this->_getCurrentDateTime()->modify($dateTimeString);
            }
            $shouldRun = $nextExecutionTime >= $base && $this->_getCurrentDateTime() >= $nextExecutionTime;
        }
        return $shouldRun;
    }

    /**
     * @return DateTime
     */
    protected function _getCurrentDateTime() {
        return $this->_getCurrentDateTimeUTC()->setTimezone($this->_timeZone);
    }

    protected function _getCurrentDateTimeUTC() {
        return new DateTime('now', new DateTimeZone('UTC'));
    }

    /**
     * @return CM_Process
     */
    protected function _getProcess() {
        return CM_Process::getInstance();
    }

    /**
     * @param CM_Clockwork_Event $event
     * @return boolean
     */
    protected function _isIntervalEvent(CM_Clockwork_Event $event) {
        $dateTimeString = $event->getDateTimeString();
        $date = new DateTime();
        $dateModified = new DateTime();
        $dateModified->modify($dateTimeString);
        return $date->modify($dateTimeString) != $dateModified->modify($dateTimeString);
    }

    /**
     * @param CM_Clockwork_Event $event
     * @return boolean
     */
    protected function _isRunning(CM_Clockwork_Event $event) {
        return array_key_exists($event->getName(), $this->_eventsRunning);
    }

    /**
     * @param CM_Clockwork_Event $event
     */
    protected function _markStopped(CM_Clockwork_Event $event) {
        if ($this->_isRunning($event)) {
            unset($this->_eventsRunning[$event->getName()]);
        }
    }

    /**
     * @param CM_Clockwork_Event $event
     */
    protected function _markRunning(CM_Clockwork_Event $event) {
        if (!$this->_isRunning($event)) {
            $this->_eventsRunning[$event->getName()] = $event;
        }
    }

    /**
     * @param CM_Clockwork_Event $event
     */
    protected function _runEvent(CM_Clockwork_Event $event) {
        $process = $this->_getProcess();
        $this->_markRunning($event);
        $process->fork(function () use ($event) {
            $event->run();
        }, function (CM_Process_WorkloadResult $result) use ($event) {
            $this->_markStopped($event);
            /** @var DateTime $runtime */
            $runtime = $this->_getCurrentDateTime();
            $this->_storage->setRuntime($event, $runtime);
        });
    }
}
