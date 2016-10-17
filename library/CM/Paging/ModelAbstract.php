<?php

abstract class CM_Paging_ModelAbstract extends CM_Paging_Abstract {

    /** @var array|null */
    protected $_modelList = null;

    public function _change() {
        parent::_change();
        $this->_modelList = null;
    }

    protected function _clearItems() {
        parent::_clearItems();
        $this->_modelList = null;
    }

    /**
     * @return int|null
     *
     * Overwrite this method if the paging source contains only ids and no type.
     * Must return null if type is contained in the source, else it will be treated as part of the id.
     */
    protected function _getModelType() {
        return null;
    }

    protected function _populateModelList(array $itemsRaw) {
        $modelList = CM_Model_Abstract::factoryGenericMultiple($itemsRaw, $this->_getModelType());
        $this->_modelList = [];
        foreach ($itemsRaw as $index => $itemRaw) {
            $this->_modelList[serialize($itemRaw)] = $modelList[$index];
        }
    }

    protected function _processItem($itemRaw) {
        if (null === $this->_modelList) {
            $this->_populateModelList($this->_getItemsRaw());
        }
        $index = serialize($itemRaw);
        return isset($this->_modelList[$index]) ? $this->_modelList[$index] : null;
    }
}
