<?php

class CM_Splittest_Fixture extends CM_Class_Abstract {

    const TYPE_REQUEST_CLIENT = 1;
    const TYPE_USER = 2;

    /** @var int */
    protected $_type;

    /** @var  int */
    protected $_id;

    /**
     * @param CM_Http_Request_Abstract|CM_Model_User|int $fixture
     * @param int|null                              $type
     * @throws CM_Exception_Invalid
     */
    public function __construct($fixture, $type = null) {
        if (is_int($fixture)) {
            if (!in_array($type, array(self::TYPE_REQUEST_CLIENT, self::TYPE_USER), true)) {
                throw new CM_Exception_Invalid('Invalid fixture type');
            }
            $this->_id = (int) $fixture;
            $this->_type = (int) $type;
        } elseif ($fixture instanceof CM_Http_Request_Abstract) {
            $this->_id = $fixture->getClientId();
            $this->_type = self::TYPE_REQUEST_CLIENT;
        } elseif ($fixture instanceof CM_Model_User) {
            $this->_id = (int) $fixture->getId();
            $this->_type = self::TYPE_USER;
        } else {
            throw new CM_Exception_Invalid('Invalid fixture type');
        }
    }

    /**
     * @return int
     */
    public function getFixtureType() {
        return $this->_type;
    }

    public function getColumnId() {
        $columnIdList = array(CM_Splittest_Fixture::TYPE_REQUEST_CLIENT => 'requestClientId', CM_Splittest_Fixture::TYPE_USER => 'userId');
        return $columnIdList[$this->getFixtureType()];
    }

    /**
     * @return int
     */
    public function getId() {
        return $this->_id;
    }

    /**
     * @param CM_Http_Request_Abstract $request
     * @param CM_Model_User       $user
     */
    public static function setUserForRequestClient(CM_Http_Request_Abstract $request, CM_Model_User $user) {
        $requestClientId = $request->getClientId();
        $userId = $user->getId();
        CM_Db_Db::updateIgnore('cm_splittestVariation_fixture', array('userId' => $userId), array('requestClientId' => $requestClientId));
    }
}
