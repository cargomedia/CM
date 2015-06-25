<?php

class CM_Model_CurrencyTest extends CMTest_TestCase {

    public function tearDown() {
        CMTest_TH::clearEnv();
    }

    public function testCreate() {
        $code = '840';
        $abbreviation = 'USD';
        $currency = CM_Model_Currency::create($code, $abbreviation);
        $this->assertSame($code, $currency->getCode());
        $this->assertSame($abbreviation, $currency->getAbbreviation());
    }

    public function testFindByAbbreviation() {
        $code = '840';
        $abbreviation = 'USD';
        $currency1 = CM_Model_Currency::create($code, $abbreviation);

        $this->assertEquals($currency1, CM_Model_Currency::findByAbbreviation($abbreviation));

        $currency1->delete();

        $this->assertNull(CM_Model_Currency::findByAbbreviation($abbreviation));

        $currency2 = CM_Model_Currency::create('999', $abbreviation);
        $this->assertEquals($currency2, CM_Model_Currency::findByAbbreviation($abbreviation));
    }

    public function testGetByAbbreviation() {
        $currency = CM_Model_Currency::create('840', 'USD');
        $this->assertEquals($currency, CM_Model_Currency::getByAbbreviation('USD'));
    }

    /**
     * @expectedException CM_Exception_Invalid
     * @expectedExceptionMessageRegexp No currency with abbreviation `\w+` set
     */
    public function testGetByAbbreviationThrows() {
        CM_Model_Currency::getByAbbreviation('XYZ');
    }

    public function testGetDefaultCurrency() {
        CM_Model_Currency::create('780', 'EUR');
        $currency = CM_Model_Currency::create('840', 'USD');
        $this->assertEquals($currency, CM_Model_Currency::getDefaultCurrency());
    }

    /**
     * @expectedException CM_Exception_Invalid
     * @expectedExceptionMessage No default currency set
     */
    public function testGetDefaultCurrencyThrows() {
        CM_Model_Currency::getDefaultCurrency();
    }

    /**
     * @expectedException CM_Exception_Invalid
     * @expectedExceptionMessage No default currency set
     */
    public function testGetDefaultCurrencyThrowsNoUSD() {
        CM_Model_Currency::create('978', 'EUR');
        CM_Model_Currency::getDefaultCurrency();
    }

    public function testFindByLocation() {
        $currency = CM_Model_Currency::create('978', 'EUR');
        $countryId = CM_Db_Db::insert('cm_model_location_country', array('abbreviation' => 'DE', 'name' => 'Germany'));
        $country = new CM_Model_Location(CM_Model_Location::LEVEL_COUNTRY, $countryId);

        $cache = CM_Cache_Local::getInstance();
        $cacheKey = CM_CacheConst::Currency_CountryId . '_countryId:' . $country->getId();

        $this->assertNull(CM_Model_Currency::findByLocation($country));
        $this->assertNull($cache->get($cacheKey));

        $currency->setCountryMapping($country);
        $cache->delete($cacheKey);

        $this->assertEquals($currency, CM_Model_Currency::findByLocation($country));
        $this->assertSame($currency->getId(), $cache->get($cacheKey));
    }

    public function testFindByLocationUseCityWithCountry() {
        $countryId = CM_Db_Db::insert('cm_model_location_country', array('abbreviation' => 'DE', 'name' => 'Germany'));
        $country = new CM_Model_Location(CM_Model_Location::LEVEL_COUNTRY, $countryId);

        $cityId = CM_Db_Db::insert('cm_model_location_city', array(
            'stateId'   => null,
            'countryId' => $countryId,
            'name'      => 'Berlin',
            'lat'       => 12.345678,
            'lon'       => 12.345678,
        ));
        $city = new CM_Model_Location(CM_Model_Location::LEVEL_CITY, $cityId);
        $this->assertNull(CM_Model_Currency::findByLocation($city));

        $currency = CM_Model_Currency::create('978', 'EUR');
        $cache = CM_Cache_Local::getInstance();
        $cacheKey = CM_CacheConst::Currency_CountryId . '_countryId:' . $country->getId();
        $currency->setCountryMapping($country);
        $cache->delete($cacheKey);

        $this->assertEquals($currency, CM_Model_Currency::findByLocation($city));
    }

    public function testGetByLocation() {
        $currencyDefault = CMTest_TH::createDefaultCurrency();
        $currencyEUR = CM_Model_Currency::create('978', 'EUR');
        $this->assertEquals($currencyDefault, CM_Model_Currency::getByLocation(null));

        $countryId = CM_Db_Db::insert('cm_model_location_country', array('abbreviation' => 'DE', 'name' => 'Germany'));
        $country = new CM_Model_Location(CM_Model_Location::LEVEL_COUNTRY, $countryId);

        $this->assertEquals($currencyDefault, CM_Model_Currency::getByLocation($country));

        $currencyEUR->setCountryMapping($country);
        $cache = CM_Cache_Local::getInstance();
        $cacheKey = CM_CacheConst::Currency_CountryId . '_countryId:' . $country->getId();
        $cache->delete($cacheKey);

        $this->assertEquals($currencyEUR, CM_Model_Currency::getByLocation($country));
    }

    public function testDelete() {
        $currencyDefault = CMTest_TH::createDefaultCurrency();
        $currencyEUR = CM_Model_Currency::create('978', 'EUR');

        $paging = new CM_Paging_Currency_All();
        $this->assertCount(2, $paging);
        $this->assertContainsAll([$currencyDefault, $currencyEUR], $paging->getItems());

        $currencyEUR->delete();

        $paging = new CM_Paging_Currency_All();
        $this->assertCount(1, $paging);
        $this->assertContains($currencyDefault, $paging->getItems());
        $this->assertNotContains($currencyEUR, $paging->getItems());
    }
}
