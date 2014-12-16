<?php

class CM_Http_SetupScript extends CM_Provision_Script_OptionBased {

    public function load(CM_OutputStream_Interface $output) {
        $client = $this->getServiceManager()->getDatabases()->getMaster();
        $query = new CM_Db_Query_Insert($client, 'cm_requestClientCounter', ['counter' => 0]);
        $query->execute();
        $this->_setLoaded(true);
    }
}
