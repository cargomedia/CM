<?php

abstract class CM_QueueAdapter_Abstract {

    /**
     * @param string $key
     * @param string $value
     */
    abstract public function push($key, $value);

    /**
     * @param string $key
     * @param string $value
     * @param int    $timestamp
     */
    abstract public function pushDelayed($key, $value, $timestamp);

    /**
     * @param string $key
     * @return string|null
     */
    abstract public function pop($key);

    /**
     * @param string $key
     * @param int    $timestampMax
     * @return String[]
     */
    abstract public function popDelayed($key, $timestampMax);

    /**
     * Updates the time to live of the whole queue, not of single entries
     *
     * @param string $key
     * @param int    $ttl
     */
    abstract public function setTtl($key, $ttl);
}
