<?php

class CM_Cache_Service implements CM_Service_ManagerAwareInterface {

    use CM_Service_ManagerAwareTrait;

    /**
     * @return CM_Cache_Storage_Runtime
     */
    public function getRuntime() {
        return $this->getServiceManager()->get('cache-runtime', 'CM_Cache_Storage_Runtime');
    }

    /**
     * @return CM_Cache_Storage_File
     */
    public function getFile() {
        return $this->getServiceManager()->get('cache-file', 'CM_Cache_Storage_File');
    }

    /**
     * @return CM_Cache_Cache
     */
    public function getShared() {
        return $this->getServiceManager()->get('cache-shared', 'CM_Cache_Cache');
    }

    /**
     * @return CM_Cache_Cache
     */
    public function getLocal() {
        return $this->getServiceManager()->get('cache-local', 'CM_Cache_Cache');
    }
}
