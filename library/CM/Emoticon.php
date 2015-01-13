<?php

class CM_Emoticon extends CM_Class_Abstract {

    /** @var string[] */
    private $_codes;

    /** @var string */
    private $_fileName;

    /** @var string */
    private $_name;

    /**
     * @param string $name
     */
    public function __construct($name) {
        $this->_name = (string) $name;
        $this->_load();
    }

    /**
     * @return string[]
     */
    public function getCodes() {
        return $this->_codes;
    }

    /**
     * @return string
     */
    public function getFileName() {
        return $this->_fileName;
    }

    /**
     * @return string
     */
    public function getName() {
        return $this->_name;
    }

    /**
     * @throws CM_Exception_Nonexistent
     */
    protected function _load() {
        $data = self::getEmoticonData();
        if (empty($data[$this->getName()])) {
            throw new CM_Exception_Nonexistent();
        }
        $this->_fileName = $data[$this->getName()]['fileName'];
        $this->_codes = $data[$this->getName()]['codes'];
    }

    /**
     * @return array[]
     */
    public static function getEmoticonData() {
        $cache = CM_Cache_Local::getInstance();
        $cacheKey = CM_CacheConst::Emoticons;
        if (false === $emoticonData = $cache->get($cacheKey)) {
            $emoticonData = self::_buildEmoticonData();
            $cache->set($cacheKey, $emoticonData, 0);
        }
        return $emoticonData;
    }

    /**
     * @return array[]
     * @throws CM_Exception_Invalid
     */
    private static function _buildEmoticonData() {
        /** @var CM_File[] $configurationFiles */
        $configurationFiles = [];
        /** @var CM_File[] $imageFiles */
        $imageFiles = [];
        $bootloader = CM_Bootloader::getInstance();
        foreach ($bootloader->getModules() as $namespace) {
            $emoticonPath = CM_Util::getModulePath($namespace) . 'layout/default/resource/img/emoticon/';
            $emoticonDir = new CM_File($emoticonPath);
            foreach ($emoticonDir->listFiles(true) as $file) {
                $name = strtolower($file->getFileNameWithoutExtension());
                if ('json' === $file->getExtension()) {
                    $configurationFiles[$name] = $file;
                } else {
                    $imageFiles[$name] = $file;
                }
            }
        }
        $emoticonData = [];
        $codeList = [];
        foreach ($imageFiles as $name => $file) {
            $emoticonData[$name] = ['name' => $name, 'fileName' => $file->getFileName(), 'codes' => [":{$name}:"]];
            $codeList[":{$name}:"] = $name;
        }
        foreach ($configurationFiles as $name => $file) {
            $additionalCodes = CM_Params::jsonDecode($file->read())['additionalCodes'];
            foreach ($additionalCodes as $code) {
                if (!array_key_exists($code, $codeList)) {
                    $codeList[$code] = $name;
                    $emoticonData[$name]['codes'][] = $code;
                } else {
                    $warning = new CM_Exception("Emoticon codes overlap",
                        [
                            'overlapping emoticons' => [$name, $codeList[$code]],
                            'code'                  => $code
                        ],
                        ['severity' => CM_Exception::WARN]);
                    CM_Bootloader::getInstance()->getExceptionHandler()->logException($warning);
                }
            }
        }
        return $emoticonData;
    }
}
