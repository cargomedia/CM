<?php

class CM_Config_Node {

    /**
     * @param string $name
     * @return CM_Config_Node
     */
    public function __get($name) {
        return $this->$name = new self();
    }

    /**
     * @return stdClass
     */
    public function export() {
        $object = new stdClass();
        foreach (get_object_vars($this) as $key => $value) {
            if ($value instanceof self) {
                $object->$key = $value->export();
            } else {
                $object->$key = $value;
            }
        }
        return $object;
    }

    /**
     * @param string $configBasename
     * @throws CM_Exception_Invalid
     */
    public function extend($configBasename) {
        foreach (CM_Util::getResourceFiles('config/' . $configBasename) as $configFile) {
            $this->extendWithFile($configFile);
        }
    }

    /**
     * @param CM_File $configFile
     * @throws CM_Exception_Invalid
     */
    public function extendWithFile(CM_File $configFile) {
        $configSetter = require $configFile->getPath();
        if (!$configSetter instanceof Closure) {
            throw new CM_Exception_Invalid('Invalid config file. `' . $configFile->getPath() . '` must return closure');
        }
        $configSetter($this);
    }
}
