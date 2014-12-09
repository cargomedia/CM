<?php

class CM_Model_Splittest extends CM_Model_Abstract implements CM_Service_ManagerAwareInterface {

    use CM_Service_ManagerAwareTrait;

    /** @var array|null */
    private $_variationWeightList;

    /**
     * @param string             $name
     * @param CM_Service_Manager $serviceManager
     */
    public function __construct($name, CM_Service_Manager $serviceManager = null) {
        $this->_construct(array('name' => $name));
        if (null === $serviceManager) {
            $serviceManager = CM_Service_Manager::getInstance();
        }
        $this->setServiceManager($serviceManager);
    }

    /**
     * @return string
     */
    public function getName() {
        return $this->_getIdKey('name');
    }

    public function getId() {
        return (int) $this->_get('id');
    }

    /**
     * @return int
     */
    public function getCreated() {
        return (int) $this->_get('createStamp');
    }

    /**
     * @return CM_Paging_SplittestVariation_Splittest
     */
    public function getVariations() {
        return new CM_Paging_SplittestVariation_Splittest($this);
    }

    /**
     * @return CM_Paging_SplittestVariation_SplittestEnabled
     */
    public function getVariationsEnabled() {
        return new CM_Paging_SplittestVariation_SplittestEnabled($this);
    }

    /**
     * @return int
     */
    public function getVariationFixtureCreatedMin() {
        return (int) CM_Db_Db::exec(
            'SELECT MIN(`createStamp`) FROM `cm_splittestVariation_fixture` WHERE `splittestId` = ?', array($this->getId()))->fetchColumn();
    }

    /**
     * @throws CM_Exception
     * @return CM_Model_SplittestVariation
     */
    public function getVariationBest() {
        $variationBest = null;
        $variationBestRate = 0;
        /** @var CM_Model_SplittestVariation $variation */
        foreach ($this->getVariations() as $variation) {
            $variationRate = $variation->getConversionRate();
            if (null === $variationBest || $variationRate > $variationBestRate) {
                $variationBest = $variation;
                $variationBestRate = $variationRate;
            }
        }
        if (!$variationBest) {
            throw new CM_Exception('Splittest `' . $this->getId() . '` has no variations');
        }
        return $variationBest;
    }

    /**
     * @param array $variationWeightList
     * @throws CM_Exception_Invalid
     */
    public function setVariationWeightList(array $variationWeightList) {
        if (empty($variationWeightList)) {
            throw new CM_Exception_Invalid('Empty variation weight list');
        }
        $variationList = $this->getVariations();
        $this->_variationWeightList = array();
        foreach ($variationWeightList as $variationName => $variationWeight) {
            $variationName = (string) $variationName;
            $variationWeight = (float) $variationWeight;
            $variation = $variationList->findByName($variationName);
            if (!$variation) {
                throw new CM_Exception_Invalid('There is no variation `' . $variationName . '` in split test `' . $this->getName() . '`');
            }
            if ($variationWeight < 0) {
                throw new CM_Exception_Invalid('Split test variation weight `' . $variationWeight . '` should be positive');
            }
            if ($variation->getEnabled() && ($variationWeight > 0)) {
                $this->_variationWeightList[$variationName] = $variationWeight;
            }
        }
        if (empty($this->_variationWeightList)) {
            throw new CM_Exception_Invalid('At least one enabled split test variation should have a positive weight');
        }
    }

    public function flush() {
        CM_Db_Db::delete('cm_splittestVariation_fixture', array('splittestId' => $this->getId()));
    }

    /**
     * @param int $id
     * @return CM_Model_Splittest
     * @throws CM_Exception_Nonexistent
     */
    public static function findId($id) {
        $id = (int) $id;
        $name = CM_Db_Db::select('cm_splittest', 'name', array('id' => $id))->fetchColumn();
        if (false === $name) {
            throw new CM_Exception_Nonexistent('Cannot find splittest with id `' . $id . '`');
        }
        return new static($name);
    }

    protected function _loadData() {
        $data = CM_Db_Db::select('cm_splittest', '*', array('name' => $this->getName()))->fetch();
        if ($data) {
            $data['variations'] = CM_Db_Db::select('cm_splittestVariation',
                array('id', 'name'), array('splittestId' => $data['id']))->fetchAllTree();
        }
        return $data;
    }

    protected static function _createStatic(array $data) {
        $name = (string) $data['name'];
        $variations = array_unique($data['variations']);
        if (empty($variations)) {
            throw new CM_Exception('Cannot create splittest without variations');
        }

        $id = CM_Db_Db::insert('cm_splittest', array('name' => $name, 'createStamp' => time()));
        try {
            foreach ($variations as $variation) {
                CM_Db_Db::insert('cm_splittestVariation', array('splittestId' => $id, 'name' => $variation));
            }
        } catch (CM_Exception $e) {
            CM_Db_Db::delete('cm_splittest', array('id' => $id));
            CM_Db_Db::delete('cm_splittestVariation', array('splittestId' => $id));
            throw $e;
        }
        return new static($name);
    }

    protected function _onDeleteBefore() {
        CM_Db_Db::delete('cm_splittestVariation', array('splittestId' => $this->getId()));
        CM_Db_Db::delete('cm_splittestVariation_fixture', array('splittestId' => $this->getId()));
    }

    protected function _onDelete() {
        CM_Db_Db::delete('cm_splittest', array('id' => $this->getId()));
    }

    protected function _getContainingCacheables() {
        $containingCacheables = parent::_getContainingCacheables();
        $containingCacheables[] = new CM_Paging_Splittest_All();
        return $containingCacheables;
    }

    /**
     * @param CM_Splittest_Fixture $fixture
     * @param float|null           $weight
     * @throws CM_Exception_Invalid
     */
    protected function _setConversion(CM_Splittest_Fixture $fixture, $weight = null) {
        $columnId = $fixture->getColumnId();
        $fixtureId = $fixture->getId();
        if (null === $weight) {
            CM_Db_Db::update('cm_splittestVariation_fixture',
                array('conversionStamp' => time(), 'conversionWeight' => 1),
                array('splittestId' => $this->getId(), $columnId => $fixtureId, 'conversionStamp' => null));
        } else {
            $weight = (float) $weight;
            $columnIdQuoted = CM_Db_Db::getClient()->quoteIdentifier($columnId);
            CM_Db_Db::exec('UPDATE `cm_splittestVariation_fixture`
                SET `conversionStamp` = COALESCE(`conversionStamp`, ?),
                `conversionWeight` = `conversionWeight` + ?
                WHERE `splittestId` = ? AND ' . $columnIdQuoted . ' = ?',
                array(time(), $weight, $this->getId(), $fixtureId));
        }
    }

    /**
     * @param CM_Splittest_Fixture $fixture
     * @param string               $variationName
     * @return bool
     */
    protected function _isVariationFixture(CM_Splittest_Fixture $fixture, $variationName) {
        return ($variationName == $this->_getVariationFixture($fixture));
    }

    /**
     * @param CM_Splittest_Fixture $fixture
     * @throws CM_Db_Exception
     * @throws CM_Exception_Invalid
     * @return string
     */
    protected function _getVariationFixture(CM_Splittest_Fixture $fixture) {
        $columnId = $fixture->getColumnId();
        $fixtureId = $fixture->getId();

        $variationListFixture = $this->_getVariationListFixture($fixture);
        if (!array_key_exists($this->getId(), $variationListFixture)) {
            $variation = $this->_getVariationRandom();
            try {
                CM_Db_Db::insert('cm_splittestVariation_fixture',
                    array('splittestId' => $this->getId(), $columnId => $fixtureId, 'variationId' => $variation->getId(), 'createStamp' => time()));
                $variationListFixture[$this->getId()] = $variation->getName();
                $cacheKey = CM_CacheConst::Splittest_VariationFixtures . '_id:' . $fixture->getId() . '_type:' . $fixture->getFixtureType();
                $cache = CM_Cache_Local::getInstance();
                $cache->set($cacheKey, $variationListFixture);
                $this->getServiceManager()->getTrackings()->trackSplittest($fixture, $variation);
            } catch (CM_Db_Exception $exception) {
                $variationListFixture = $this->_getVariationListFixture($fixture, true);
                if (!array_key_exists($this->getId(), $variationListFixture)) {
                    throw $exception;
                }
            }
        }

        return $variationListFixture[$this->getId()];
    }

    /**
     * @param CM_Splittest_Fixture $fixture
     * @param bool|null            $updateCache
     * @return array
     */
    protected function _getVariationListFixture(CM_Splittest_Fixture $fixture, $updateCache = null) {
        $columnId = $fixture->getColumnId();
        $columnIdQuoted = CM_Db_Db::getClient()->quoteIdentifier($columnId);
        $fixtureId = $fixture->getId();
        $updateCache = (bool) $updateCache;

        $cacheKey = CM_CacheConst::Splittest_VariationFixtures . '_id:' . $fixture->getId() . '_type:' . $fixture->getFixtureType();
        $cache = CM_Cache_Local::getInstance();
        if ($updateCache || (($variationListFixture = $cache->get($cacheKey)) === false)) {
            $variationListFixture = CM_Db_Db::exec('
				SELECT `variation`.`splittestId`, `variation`.`name`
					FROM `cm_splittestVariation_fixture` `fixture`
					JOIN `cm_splittestVariation` `variation` ON(`variation`.`id` = `fixture`.`variationId`)
					WHERE `fixture`.' . $columnIdQuoted . ' = ?', array($fixtureId))->fetchAllTree();
            $cache->set($cacheKey, $variationListFixture);
        }
        return $variationListFixture;
    }

    /**
     * @throws CM_Exception_Invalid
     * @return CM_Model_SplittestVariation
     */
    protected function _getVariationRandom() {
        if (!isset($this->_variationWeightList)) {
            $variation = $this->getVariationsEnabled()->getItemRand();
        } else {
            $variationList = array();
            $variationWeightList = array();
            /** @var CM_Model_SplittestVariation $variation */
            foreach ($this->getVariationsEnabled()->getItems() as $variation) {
                $variationName = $variation->getName();
                if (isset($this->_variationWeightList[$variationName])) {
                    $variationList[] = $variation;
                    $variationWeightList[] = $this->_variationWeightList[$variationName];
                }
            }
            if (empty($variationList)) {
                $variation = null;
            } else {
                $weightedRandom = new CM_WeightedRandom($variationList, $variationWeightList);
                $variation = $weightedRandom->lookup();
            }
        }
        if (!$variation) {
            throw new CM_Exception_Invalid('Splittest `' . $this->getId() . '` has no enabled variations.');
        }
        return $variation;
    }

    /**
     * @param string   $name
     * @param string[] $variations
     * @return static
     */
    public static function create($name, array $variations) {
        return static::createStatic(['name' => (string) $name, 'variations' => (array) $variations]);
    }

    /**
     * @param string $name
     * @return bool
     */
    public static function exists($name) {
        $paging = new CM_Paging_Splittest_All();
        return $paging->contains($name);
    }

    /**
     * @param string $name
     * @return static|null
     */
    public static function find($name) {
        if (!self::exists($name)) {
            return null;
        }
        $className = get_called_class();
        return new $className($name);
    }
}
