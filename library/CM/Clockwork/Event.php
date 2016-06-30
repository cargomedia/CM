<?php

class CM_Clockwork_Event {

    /** @var callable[] */
    private $_callbacks;

    /** @var string */
    private $_dateTimeString;

    /** @var string */
    private $_name;

    /**
     * @param string $name
     * @param string $dateTimeString see http://php.net/manual/en/datetime.formats.php
     */
    public function __construct($name, $dateTimeString) {
        $this->_name = (string) $name;
        $this->_dateTimeString = (string) $dateTimeString;
        $this->_callbacks = [];
    }

    /**
     * @return string
     */
    public function getDateTimeString() {
        return $this->_dateTimeString;
    }

    /**
     * @return string
     */
    public function getName() {
        return $this->_name;
    }

    /**
     * @param callable $callback
     * @throws CM_Exception_Invalid
     */
    public function registerCallback($callback) {
        if (!is_callable($callback)) {
            throw new CM_Exception_Invalid('$callback needs to be callable');
        }
        $this->_callbacks[] = $callback;
    }

    /**
     * @param DateTime|null $lastRuntime
     */
    public function run(DateTime $lastRuntime = null) {
        foreach ($this->_callbacks as $callback) {
            call_user_func($callback, $lastRuntime);
        }
    }

    /**
     * @return DateTime
     */
    protected function _getCurrentDateTime() {
        return new DateTime();
    }
}
