<?php

interface CM_InputStream_Interface {

    /**
     * @param string|null  $hint
     * @param string|null  $default
     * @param Closure|null $validateCallback
     * @return string
     */
    public function read($hint = null, $default = null, Closure $validateCallback = null);

    /**
     * @param string      $hint
     * @param string[]    $values
     * @param string|null $default
     * @return string
     */
    public function select($hint, array $values, $default = null);

    /**
     * @param string|null $hint
     * @param string|null $default
     * @return boolean
     */
    public function confirm($hint = null, $default = null);
}
