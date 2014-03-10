<?php

class CM_Model_Language extends CM_Model_Abstract {

    /** @var CM_Model_Language|null $_backup */
    private $_backup;

    /**
     * @return string
     */
    public function getName() {
        return (string) $this->_get('name');
    }

    /**
     * @return string
     */
    public function getAbbreviation() {
        return (string) $this->_get('abbreviation');
    }

    /**
     * @return bool
     */
    public function getEnabled() {
        return (bool) $this->_get('enabled');
    }

    /**
     * @return CM_Paging_Translation_Language
     */
    public function getTranslations() {
        return new CM_Paging_Translation_Language($this);
    }

    /**
     * @param string     $key
     * @param array|null $variableNames
     * @param bool|null  $skipCacheLocal
     * @return string
     */
    public function getTranslation($key, array $variableNames = null, $skipCacheLocal = null) {
        $key = (string) $key;
        $cacheKey = CM_CacheConst::Language_Translations . '_languageId:' . $this->getId();
        $cache = CM_Cache_Local::getInstance();
        if ($skipCacheLocal || false === ($translations = $cache->get($cacheKey))) {
            $translations = $this->getTranslations()->getAssociativeArray();

            if (!$skipCacheLocal) {
                $cache->set($cacheKey, $translations);
            }
        }

        // Check if translation exists and if variables provided match the ones in database
        if (!array_key_exists($key, $translations)) {
            static::_setKey($key, $variableNames);
            $this->_change();
        } elseif ($variableNames !== null) {
            sort($variableNames);
            if ($variableNames !== $translations[$key]['variables']) {
                static::_setKey($key, $variableNames);
                $this->_change();
            }
        }
        // Getting value from backup language if backup is present and value does not exist
        if (!isset($translations[$key]['value'])) {
            if (!$this->getBackup()) {
                return $key;
            }
            return $this->getBackup()->getTranslation($key, $variableNames, $skipCacheLocal);
        }
        return $translations[$key]['value'];
    }

    /**
     * @param string     $key
     * @param string     $value
     * @param array|null $variables
     */
    public function setTranslation($key, $value, array $variables = null) {
        $languageKeyId = static::_setKey($key, $variables);

        CM_Db_Db::insert('cm_languageValue', array('value'      => $value, 'languageKeyId' => $languageKeyId,
                                                   'languageId' => $this->getId()), null, array('value' => $value));
        $this->_change();
    }

    /**
     * @param string $key
     */
    public function unsetTranslation($key) {
        $languageKeyId = static::_setKey($key);
        CM_Db_Db::delete('cm_languageValue', array('languageKeyId' => $languageKeyId, 'languageId' => $this->getId()));
        $this->_change();
    }

    /**
     * @param string                 $name
     * @param string                 $abbreviation
     * @param bool|null              $enabled
     * @param CM_Model_Language|null $backup
     */
    public function setData($name, $abbreviation, $enabled = null, CM_Model_Language $backup = null) {
        $name = (string) $name;
        $abbreviation = (string) $abbreviation;
        $enabled = (bool) $enabled;
        $backupId = ($backup) ? $backup->getId() : null;
        CM_Db_Db::update('cm_language', array('name'     => $name, 'abbreviation' => $abbreviation, 'enabled' => $enabled,
                                              'backupId' => $backupId), array('id' => $this->getId()));
        $this->_change();
    }

    /**
     * @return CM_Model_Language|null
     */
    public function getBackup() {
        if (!$this->_backup && $this->_get('backupId')) {
            $this->_backup = new CM_Model_Language($this->_get('backupId'));
        }
        return $this->_backup;
    }

    /**
     * @param CM_Model_Language $language
     * @return bool
     */
    public function isBackingUp(CM_Model_Language $language) {
        while (!is_null($language)) {
            if ($this->equals($language)) {
                return true;
            }
            $language = $language->getBackup();
        }
        return false;
    }

    public function toArray() {
        $array = parent::toArray();
        $array['abbreviation'] = $this->getAbbreviation();
        return $array;
    }

    protected function _loadData() {
        return CM_Db_Db::select('cm_language', '*', array('id' => $this->getId()))->fetch();
    }

    protected function _onChange() {
        $cacheKey = CM_CacheConst::Language_Translations . '_languageId:' . $this->getId();
        CM_Cache_Local::getInstance()->delete($cacheKey);
    }

    protected function _getContainingCacheables() {
        $cacheables = parent::_getContainingCacheables();
        $cacheables[] = new CM_Paging_Language_All();
        $cacheables[] = new CM_Paging_Language_Enabled();
        return $cacheables;
    }

    protected function _onDeleteBefore() {
        CM_Db_Db::delete('cm_languageValue', array('languageId' => $this->getId()));
        CM_Db_Db::update('cm_language', array('backupId' => null), array('backupId' => $this->getId()));
        CM_Db_Db::update('cm_user', array('languageId' => null), array('languageId' => $this->getId()));
    }

    protected function _onDelete() {
        CM_Db_Db::delete('cm_language', array('id' => $this->getId()));
    }

    protected function _onDeleteAfter() {
        self::changeAll();
    }

    /**
     * @param string $name
     * @param string $abbreviation
     * @param bool   $enabled
     * @return static
     */
    public static function create($name, $abbreviation, $enabled) {
        return CM_Model_Language::createStatic(array(
            'name'         => (string) $name,
            'abbreviation' => (string) $abbreviation,
            'enabled'      => (bool) $enabled,
        ));
    }

    public static function changeAll() {
        /** @var CM_Model_Language $language */
        foreach (new CM_Paging_Language_All() as $language) {
            $language->_change();
        }
    }

    /**
     * @param string $abbreviation
     * @return CM_Model_Language|null
     */
    public static function findByAbbreviation($abbreviation) {
        $abbreviation = (string) $abbreviation;
        $languageId = CM_Db_Db::select('cm_language', 'id', array('abbreviation' => $abbreviation))->fetchColumn();
        if (!$languageId) {
            return null;
        }
        return new static($languageId);
    }

    /**
     * @return CM_Model_Language|null
     */
    public static function findDefault() {
        $cacheKey = CM_CacheConst::Language_Default;
        $cache = CM_Cache_Local::getInstance();
        if (false === ($languageId = $cache->get($cacheKey))) {
            $languageId = CM_Db_Db::select('cm_language', 'id', array('enabled' => true, 'backupId' => null))->fetchColumn();
            $cache->set($cacheKey, $languageId);
        }
        if (!$languageId) {
            return null;
        }
        return new static($languageId);
    }

    /**
     * @param string $name
     */
    public static function deleteKey($name) {
        $name = (string) $name;
        $languageKeyId = CM_Db_Db::select('cm_languageKey', 'id', array('name' => $name))->fetchColumn();
        if (!$languageKeyId) {
            return;
        }
        CM_Db_Db::delete('cm_languageValue', array('languageKeyId' => $languageKeyId));
        CM_Db_Db::delete('cm_languageKey', array('id' => $languageKeyId));
        self::changeAll();
    }

    /**
     * @return CM_Tree_Language
     */
    public static function getTree() {
        $cacheKey = CM_CacheConst::Language_Tree;
        $cache = CM_Cache_Local::getInstance();
        if (false === ($tree = $cache->get($cacheKey))) {
            $tree = new CM_Tree_Language();
            $cache->set($cacheKey, $tree);
        }
        return $tree;
    }

    /**
     * @param string $name
     * @return boolean
     */
    public static function hasKey($name) {
        $name = (string) $name;
        return (boolean) CM_Db_Db::count('cm_languageKey', array('name' => $name));
    }

    /**
     * @param string      $name
     * @param string|null $nameNew
     * @param array|null  $variableNamesNew
     * @throws CM_Exception_Nonexistent
     * @throws CM_Exception_Duplicate
     */
    public static function updateKey($name, $nameNew = null, array $variableNamesNew = null) {
        if ($variableNamesNew !== null) {
            self::_setKeyVariables($name, $variableNamesNew);
        }
        if ($nameNew !== null) {
            if (!CM_Db_Db::count('cm_languageKey', array('name' => $name))) {
                throw new CM_Exception_Nonexistent('LanguageKey `' . $name . '` does not exist');
            }
            if (CM_Db_Db::count('cm_languageKey', array('name' => $nameNew))) {
                throw new CM_Exception_Duplicate('LanguageKey `' . $nameNew . '` already exists');
            }
            CM_Db_Db::update('cm_languageKey', array('name' => $nameNew), array('name' => $name));
            self::changeAll();
        }
    }

    /**
     * @return int
     */
    public static function getVersionJavascript() {
        return (int) CM_Option::getInstance()->get('language.javascript.version');
    }

    public static function updateVersionJavascript() {
        CM_Option::getInstance()->set('language.javascript.version', time());
    }

    /**
     * @param string $languageKey
     * @throws CM_Exception_Invalid
     */
    public static function rpc_requestTranslationJs($languageKey) {
        $javascript = CM_Db_Db::select('cm_languageKey', 'javascript', array('name' => $languageKey))->fetchColumn();
        if ($javascript === false) {
            throw new CM_Exception_Invalid('Language key `' . $languageKey . '` not found');
        }
        if ($javascript == 0) {
            CM_Db_Db::update('cm_languageKey', array('javascript' => 1), array('name' => $languageKey));
            self::updateVersionJavascript();
        }
    }

    protected static function _createStatic(array $data) {
        $params = CM_Params::factory($data);
        $backupId = ($params->has('backup')) ? $params->getLanguage('backup')->getId() : null;
        $id = CM_Db_Db::insert('cm_language', array('name'    => $params->getString('name'), 'abbreviation' => $params->getString('abbreviation'),
                                                    'enabled' => $params->getBoolean('enabled'), 'backupId' => $backupId));
        return new static($id);
    }

    /**
     * @param string     $name
     * @param array|null $variableNames
     * @return int
     */
    private static function _setKey($name, array $variableNames = null) {
        $name = (string) $name;
        $languageKeyId = CM_Db_Db::select('cm_languageKey', 'id', array('name' => $name), 'id ASC')->fetchColumn();
        if (!$languageKeyId) {
            $languageKeyId = CM_Db_Db::insert('cm_languageKey', array('name' => $name));

            // check if the language Key is double inserted because of high load
            $languageKeyIdList = CM_Db_Db::select('cm_languageKey', 'id', array('name' => $name), 'id ASC')->fetchAllColumn();
            if (1 < count($languageKeyIdList)) {
                $languageKeyId = array_shift($languageKeyIdList);
                CM_Db_Db::exec("DELETE FROM `cm_languageKey` WHERE `name` = ? AND `id` != ?", array($name, $languageKeyId));
            }

            self::changeAll();
        }
        if ($variableNames !== null) {
            self::_setKeyVariables($name, $variableNames);
        }
        return $languageKeyId;
    }

    /**
     * @param string $name
     * @param array  $variableNames
     * @throws CM_Exception_Invalid
     */
    private static function _setKeyVariables($name, array $variableNames) {
        $languageKeyParams = CM_Db_Db::select('cm_languageKey', array('id', 'updateCountResetVersion',
            'updateCount'), array('name' => $name))->fetch();
        if (!$languageKeyParams) {
            throw new CM_Exception_Invalid('Language key `' . $name . '` was not found');
        }
        $languageKeyId = $languageKeyParams['id'];
        $updateCount = $languageKeyParams['updateCount'] + 1;
        $deployVersion = CM_App::getInstance()->getDeployVersion();
        if ($deployVersion > $languageKeyParams['updateCountResetVersion']) {
            $updateCount = 1;
        }
        CM_Db_Db::update('cm_languageKey', array('updateCountResetVersion' => $deployVersion, 'updateCount' => $updateCount), array('name' => $name));
        if ($updateCount > 50) {
            throw new CM_Exception_Invalid('Variables for languageKey `' . $name . '` have been already updated over 50 times since release');
        }

        CM_Db_Db::delete('cm_languageKey_variable', array('languageKeyId' => $languageKeyId));
        foreach ($variableNames as $variableName) {
            CM_Db_Db::insert('cm_languageKey_variable', array('languageKeyId' => $languageKeyId, 'name' => $variableName));
        }
        self::changeAll();
    }
}
