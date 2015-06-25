<?php

class CMService_KissMetrics_Client implements CM_Service_Tracking_ClientInterface {

    /** @var string */
    protected $_code;

    /** @var string[] */
    protected $_identityList = array();

    /**
     * @param string $code
     */
    public function __construct($code) {
        $this->_code = (string) $code;
    }

    public function getHtml(CM_Frontend_Environment $environment) {
        $html = '<script type="text/javascript">';
        $html .= 'var _kmq = _kmq || [];';
        $html .= "var _kmk = _kmk || '" . $this->_getCode() . "';";
        $html .= <<<EOF
function _kms(u) {
  setTimeout(function() {
    var d = document, f = d.getElementsByTagName('script')[0], s = d.createElement('script');
    s.type = 'text/javascript';
    s.async = true;
    s.src = u;
    f.parentNode.insertBefore(s, f);
  }, 1);
}
_kms('//i.kissmetrics.com/i.js');
_kms('//doug1izaerwt3.cloudfront.net/' + _kmk + '.1.js');
EOF;
        $html .= $this->getJs();
        $html .= '</script>';
        return $html;
    }

    /**
     * @return string
     */
    public function getJs() {
        $js = '';
        $identityList = $this->_getIdentityList();
        foreach ($identityList as $identity) {
            $js .= "_kmq.push(['identify', '{$identity}']);";
        }
        if (count($identityList) > 1) {
            $identity = array_shift($identityList);
            foreach ($identityList as $identityOld) {
                $js .= "_kmq.push(['alias', '{$identityOld}', '{$identity}']);";
            }
        }
        return $js;
    }

    /**
     * @param string[] $identityList
     */
    public function setIdentityList(array $identityList) {
        $this->_identityList = array();
        foreach ($identityList as $identity) {
            $this->_addIdentity($identity);
        }
    }

    /**
     * @param int $requestClientId
     */
    public function setRequestClientId($requestClientId) {
        $requestClientId = (int) $requestClientId;
        $this->_addIdentity('Guest ' . $requestClientId);
    }

    /**
     * @param int $userId
     */
    public function setUserId($userId) {
        $userId = (int) $userId;
        $this->_addIdentity($userId);
    }

    /**
     * @param CM_Action_Abstract $action
     */
    public function trackAction(CM_Action_Abstract $action) {
        if (0 === count($this->_getIdentityList()) && $actor = $action->getActor()) {
            $this->setUserId($actor->getId());
        }
        $trackEventJob = new CMService_KissMetrics_TrackEventJob();
        $trackEventJob->queue(array(
            'code'         => $this->_getCode(),
            'identityList' => $this->_getIdentityList(),
            'eventName'    => $action->getLabel(),
            'propertyList' => $action->getTrackingPropertyList(),
        ));
    }

    public function trackAffiliate($requestClientId, $affiliateName) {
        $this->setRequestClientId($requestClientId);
        $trackEventJob = new CMService_KissMetrics_TrackPropertyListJob();
        $trackEventJob->queue([
            'code'         => $this->_getCode(),
            'identityList' => $this->_getIdentityList(),
            'propertyList' => ['Affiliate Name' => $affiliateName],
        ]);
    }

    /**
     * @param string $eventName
     * @param array  $propertyList
     */
    public function trackEvent($eventName, array $propertyList) {
        $identityList = $this->_getIdentityList();
        if (empty($identityList)) {
            return;
        }
        $eventName = (string) $eventName;
        $kissMetrics = new \KISSmetrics\Client($this->_getCode(), new CMService_KissMetrics_Transport_GuzzleHttp());
        $identity = array_shift($identityList);
        $kissMetrics->identify($identity);
        foreach ($identityList as $identityOld) {
            $kissMetrics->alias($identityOld);
        }
        $kissMetrics->record($eventName, $propertyList);
        $kissMetrics->submit();
    }

    public function trackPageView(CM_Frontend_Environment $environment, $path) {
        if ($viewer = $environment->getViewer()) {
            $this->setUserId($viewer->getId());
        }
        if (CM_Http_Request_Abstract::hasInstance()) {
            $this->setRequestClientId(CM_Http_Request_Abstract::getInstance()->getClientId());
        }
    }

    /**
     * @param array $propertyList
     */
    public function trackPropertyList(array $propertyList) {
        $identityList = $this->_getIdentityList();
        if (empty($identityList)) {
            return;
        }
        $kissMetrics = new \KISSmetrics\Client($this->_getCode(), new CMService_KissMetrics_Transport_GuzzleHttp());
        $identity = array_shift($identityList);
        $kissMetrics->identify($identity);
        foreach ($identityList as $identityOld) {
            $kissMetrics->alias($identityOld);
        }
        $kissMetrics->set($propertyList);
        $kissMetrics->submit();
    }

    public function trackSplittest(CM_Splittest_Fixture $fixture, CM_Model_SplittestVariation $variation) {
        $nameSplittest = $variation->getSplittest()->getName();
        $nameVariation = $variation->getName();
        switch ($fixture->getFixtureType()) {
            case CM_Splittest_Fixture::TYPE_REQUEST_CLIENT:
                $this->setRequestClientId($fixture->getId());
                break;
            case CM_Splittest_Fixture::TYPE_USER:
                $this->setUserId($fixture->getId());
                break;
        }
        $trackEventJob = new CMService_KissMetrics_TrackPropertyListJob();
        $trackEventJob->queue(array(
            'code'         => $this->_getCode(),
            'identityList' => $this->_getIdentityList(),
            'propertyList' => array('Splittest ' . $nameSplittest => $nameVariation),
        ));
    }

    /**
     * @param string $identity
     */
    protected function _addIdentity($identity) {
        $identity = (string) $identity;
        if (!in_array($identity, $this->_identityList, true)) {
            $this->_identityList[] = $identity;
        }
    }

    /**
     * @return string
     */
    protected function _getCode() {
        return $this->_code;
    }

    /**
     * @return string[]
     */
    protected function _getIdentityList() {
        return $this->_identityList;
    }
}
