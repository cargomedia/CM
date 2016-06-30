<?php

class CM_Clockwork_Manager {

    /** @var CM_Clockwork_Event[] */
    private $_events;

    /** @var DateTime */
    private $_startTime;

    /** @var CM_Clockwork_Storage_Abstract */
    private $_storage;

    /** @var DateTimeZone */
    private $_timeZone;

    /** @var array */
    private $_eventsRunning = [];

    public function __construct() {
        $this->_events = array();
        $this->_storage = new CM_Clockwork_Storage_Memory();
        $this->_timeZone = CM_Bootloader::getInstance()->getTimeZone();
        $this->_startTime = $this->_getCurrentDateTimeUTC();
    }

    /**
     * @param string   $name
     * @param string   $dateTimeString
     * @param callable $callback
     */
    public function registerCallback($name, $dateTimeString, $callback) {
        $event = new CM_Clockwork_Event($name, $dateTimeString);
        $event->registerCallback($callback);
        $this->registerEvent($event);
    }

    /**
     * @param CM_Clockwork_Event $event
     * @throws CM_Exception
     */
    public function registerEvent(CM_Clockwork_Event $event) {
        $eventName = $event->getName();
        $duplicateEventName = \Functional\some($this->_events, function (CM_Clockwork_Event $event) use ($eventName) {
            return $event->getName() == $eventName;
        });
        if ($duplicateEventName) {
            throw new CM_Exception('Duplicate event-name', null, ['name' => $eventName]);
        }
        $this->_events[] = $event;
    }

    public function runEvents() {
        foreach ($this->_events as $event) {
            if (!$this->_isRunning($event)) {
                if ($this->_shouldRun($event)) {
                    $this->_runEvent($event);
                } elseif (null === $this->_storage->getLastRuntime($event) && $this->_isIntervalEvent($event)) {
                    $this->_storage->setRuntime($event, $this->_startTime);
                }
            }
        }
        $this->handleCompletedEvents();
    }

    public function handleCompletedEvents() {
        $resultList = $this->_getProcess()->listenForChildren();
        foreach ($resultList as $identifier => $result) {
            $event = $this->_getRunningEvent($identifier);
            $this->handleEventResult($event, $result);
        }
    }

    /**
     * @param CM_Clockwork_Event        $event
     * @param CM_Process_WorkloadResult $result
     * @throws CM_Exception_Invalid
     */
    public function handleEventResult(CM_Clockwork_Event $event, CM_Process_WorkloadResult $result) {
        if ($result->isSuccess()) {
            $this->_markCompleted($event);
        }
        $this->_markStopped($event);
    }

    /**
     * @param CM_Clockwork_Storage_Abstract $storage
     */
    public function setStorage(CM_Clockwork_Storage_Abstract $storage) {
        $this->_storage = $storage;
    }

    /**
     * @param DateTimeZone $timeZone
     */
    public function setTimeZone(DateTimeZone $timeZone) {
        $this->_timeZone = $timeZone;
    }

    public function start() {
        while (true) {
            $this->runEvents();
            sleep(1);
            $this->_getProcess()->handleSignals();
        }
    }

    /**
     * @param int $identifier
     * @return CM_Clockwork_Event
     * @throws CM_Exception
     */
    protected function _getRunningEvent($identifier) {
        $eventName = array_search($identifier, \Functional\pluck($this->_eventsRunning, 'identifier'));
        if (false === $eventName) {
            throw new CM_Exception('Could not find event', null, ['identifier' => $identifier]);
        }
        return $this->_eventsRunning[$eventName]['event'];
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
     * @param int                $identifier
     * @param DateTime           $startTime
     * @throws CM_Exception_Invalid
     */
    protected function _markRunning(CM_Clockwork_Event $event, $identifier, DateTime $startTime) {
        if ($this->_isRunning($event)) {
            throw new CM_Exception_Invalid("Event `{$event->getName()}` is already running");
        }
        $this->_eventsRunning[$event->getName()] = ['event' => $event, 'identifier' => $identifier, 'startTime' => $startTime];
    }

    /**
     * @param CM_Clockwork_Event $event
     * @throws CM_Exception_Invalid
     */
    protected function _markStopped(CM_Clockwork_Event $event) {
        if (!$this->_isRunning($event)) {
            throw new CM_Exception_Invalid("Cannot stop event. `{$event->getName()}` is already running");
        }
        unset($this->_eventsRunning[$event->getName()]);
    }

    /**
     * @param CM_Clockwork_Event $event
     */
    protected function _markCompleted(CM_Clockwork_Event $event) {
        $startTime = $this->_eventsRunning[$event->getName()]['startTime'];
        $this->_storage->setRuntime($event, $startTime);
    }

    /**
     * @param CM_Clockwork_Event $event
     */
    protected function _runEvent(CM_Clockwork_Event $event) {
        $process = $this->_getProcess();
        $lastRuntime = $this->_storage->getLastRuntime($event);
        $startTime = $this->_getCurrentDateTime();
        $forkHandler = $process->fork(function () use ($event, $lastRuntime) {
            $event->run($lastRuntime);
        });
        $this->_markRunning($event, $forkHandler->getIdentifier(), $startTime);
    }
}
