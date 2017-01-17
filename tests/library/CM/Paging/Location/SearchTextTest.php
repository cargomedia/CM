<?php

class CM_Paging_Location_SearchTextTest extends CMTest_TestCase {

    /** @var CM_Elasticsearch_Client */
    private $_elasticsearchClient;

    public function setUp() {
        $elasticCluster = CMTest_TH::getServiceManager()->getElasticsearch();
        $elasticCluster->setEnabled(true);
        $this->_elasticsearchClient = $elasticCluster->getClient();
    }

    public function tearDown() {
        (new CM_Elasticsearch_Type_Location($this->_elasticsearchClient))->deleteIndex();
        CMTest_TH::getServiceManager()->getElasticsearch()->setEnabled(false);
        CMTest_TH::clearEnv();
    }

    public function testSearch() {
        $country = CM_Model_Location::createCountry('Spain', 'ES');
        CM_Model_Location::createCity($country, 'York', 0, 0);
        CM_Model_Location::createCity($country, 'New York', 10, 10);
        CM_Model_Location::createCity($country, 'Basel', 10, 10);
        $this->_recreateLocationIndex();

        $source = new CM_Paging_Location_SearchText('', CM_Model_Location::LEVEL_COUNTRY, CM_Model_Location::LEVEL_CITY);
        $this->assertEquals(4, $source->getCount());

        $source = new CM_Paging_Location_SearchText('', CM_Model_Location::LEVEL_CITY, CM_Model_Location::LEVEL_CITY);
        $this->assertEquals(3, $source->getCount());

        $source = new CM_Paging_Location_SearchText('York', CM_Model_Location::LEVEL_CITY, CM_Model_Location::LEVEL_CITY);
        $this->assertEquals(2, $source->getCount());
    }

    public function testSearchDistance() {
        $country = CM_Model_Location::createCountry('Country', 'CR');
        $source = CM_Model_Location::createCity($country, 'Source', 0, 0);
        $locationMiddle = CM_Model_Location::createCity($country, 'City', 0, 10);
        $locationFar = CM_Model_Location::createCity($country, 'City', 0, 100);
        $locationClose = CM_Model_Location::createCity($country, 'City', 0, 0);
        $this->_recreateLocationIndex();

        $paging = new CM_Paging_Location_SearchText('City', CM_Model_Location::LEVEL_CITY, CM_Model_Location::LEVEL_CITY, $source);
        $expected = array(
            $locationClose,
            $locationMiddle,
            $locationFar,
        );
        $this->assertEquals($expected, $paging->getItems());
    }

    public function testSearchScope() {
        $country1 = CM_Model_Location::createCountry('United States', 'US');
        $city1 = CM_Model_Location::createCity($country1, 'New York', 10, 10);
        $country2 = CM_Model_Location::createCountry('United Kingdom', 'UK');
        $city2 = CM_Model_Location::createCity($country2, 'York', 20, 20);
        $country3 = CM_Model_Location::createCountry('Canada', 'CA');
        $city3 = CM_Model_Location::createCity($country3, 'Montréal', 30, 30);
        $this->_recreateLocationIndex();

        $source = new CM_Paging_Location_SearchText('', CM_Model_Location::LEVEL_COUNTRY, CM_Model_Location::LEVEL_CITY);
        $this->assertEquals([$country1, $country2, $country3, $city1, $city2, $city3], $source->getItems());

        $source = new CM_Paging_Location_SearchText('', CM_Model_Location::LEVEL_COUNTRY, CM_Model_Location::LEVEL_CITY, null, $country3);
        $this->assertEquals([$country3, $city3], $source->getItems());

        $source = new CM_Paging_Location_SearchText('York', CM_Model_Location::LEVEL_COUNTRY, CM_Model_Location::LEVEL_CITY);
        $this->assertEquals([$city2, $city1], $source->getItems());

        $source = new CM_Paging_Location_SearchText('York', CM_Model_Location::LEVEL_COUNTRY, CM_Model_Location::LEVEL_CITY, null, $country1);
        $this->assertEquals([$city1], $source->getItems());

        $source = new CM_Paging_Location_SearchText('York', CM_Model_Location::LEVEL_COUNTRY, CM_Model_Location::LEVEL_CITY, null, $country2);
        $this->assertEquals([$city2], $source->getItems());

        $source = new CM_Paging_Location_SearchText('York', CM_Model_Location::LEVEL_COUNTRY, CM_Model_Location::LEVEL_CITY, null, $country3);
        $this->assertEquals([], $source->getItems());
    }

    private function _recreateLocationIndex() {
        CM_Model_Location::createAggregation();
        $searchIndexCli = new CM_Elasticsearch_Index_Cli();
        $searchIndexCli->create((new CM_Elasticsearch_Type_Location($this->_elasticsearchClient))->getIndexName());
    }
}
