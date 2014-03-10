<?php

abstract class CM_Paging_ContentList_Abstract extends CM_Paging_Abstract implements CM_Typed {

    /**
     * @var int
     */
    private $_type;

    /**
     * @param int $type
     */
    public function __construct($type) {
        $this->_type = (int) $type;
        $source = new CM_PagingSource_Sql_Deferred('string', 'cm_string', '`type`=' . $this->_type, 'string ASC');
        $source->enableCache();
        parent::__construct($source);
    }

    /**
     * @param string $string
     */
    public function add($string) {
        CM_Db_Db::replace('cm_string', array('type' => $this->_type, 'string' => $string));
        $this->_change();
    }

    /**
     * @param string $string
     */
    public function remove($string) {
        CM_Db_Db::delete('cm_string', array('type' => $this->_type, 'string' => $string));
        $this->_change();
    }

    public function removeAll() {
        CM_Db_Db::delete('cm_string', array('type' => $this->_type));
        $this->_change();
    }

    /**
     * @param string $string
     * @param string $pattern OPTIONAL
     * @return boolean
     */
    public function contains($string, $pattern = '/^\Q$item\E$/i') {
        foreach ($this as $item) {
            if (preg_match(str_replace('$item', $item, $pattern), $string)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param int $type
     * @return CM_Paging_ContentList_Abstract
     */
    static public function factory($type) {
        $className = self::_getClassName($type);
        return new $className();
    }
}
