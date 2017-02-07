<?php

class CM_Site_SiteFactory {

    /** @var CM_Site_Abstract[] */
    private $_siteList;

    /**
     * @param CM_Site_Abstract[]|null $siteList
     */
    public function __construct(array $siteList = null) {
        if (null === $siteList) {
            $siteList = CM_Site_Abstract::getAll();
        }

        usort($siteList, function (CM_Site_Abstract $site1, CM_Site_Abstract $site2) {
            $length1 = mb_strlen($site1->getUrl());
            $length2 = mb_strlen($site2->getUrl());
            if ($length1 == $length2) {
                return 0;
            }
            return $length1 > $length2 ? -1 : 1;
        });

        $this->_siteList = $siteList;
    }

    /**
     * @param CM_Http_Request_Abstract $request
     * @return CM_Site_Abstract|null
     */
    public function findSite(CM_Http_Request_Abstract $request) {
        $request = clone $request;
        foreach ($this->_siteList as $site) {
            if ($site->match($request)) {
                return $site;
            }
        }
        return null;
    }

    /**
     * @param CM_Http_Request_Abstract $request
     * @return CM_Site_Abstract
     * @throws CM_Exception
     */
    public function getSite(CM_Http_Request_Abstract $request) {
        $site = $this->findSite($request);
        if (null === $site) {
            throw new CM_Exception('No suitable site found for request.', null, ['request' => $request]);
        }
        return $site;
    }

}
