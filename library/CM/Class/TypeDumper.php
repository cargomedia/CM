<?php

class CM_Class_TypeDumper extends CM_Provision_Script_Abstract {

    public function load(CM_OutputStream_Interface $output) {
        $dbClient = $this->getServiceManager()->getDatabases()->getMaster();
        $values = Functional\map($this->_getAllTypes(), function ($className, $type) {
            return [$type, $className];
        });
        $query = new CM_Db_Query_Truncate($dbClient, 'cm_tmp_classType');
        $query->execute();
        $query = new CM_Db_Query_Insert($dbClient, 'cm_tmp_classType', ['type', 'className'], $values, null, 'REPLACE');
        $query->execute();
    }

    public function shouldBeLoaded() {
        return true;
    }

    public function getRunLevel() {
        return 1;
    }

    /**
     * @return array
     */
    protected function _getAllTypes() {
        $config = CM_Config::get();
        $typeList = [];
        foreach (get_object_vars($config) as $key => $value) {
            if (isset($value->types)) {
                $typeList += $value->types;
            }
        }
        return $typeList;
    }

}
