<?php

class CM_Model_Location_Zip extends CM_Model_Location_Abstract {

    /**
     * @return CM_Model_Location_City
     */
    public function getCity() {
        return $this->_get('cityId');
    }

    /**
     * @param CM_Model_Location_City $city
     */
    public function setCity($city) {
        $this->_set('cityId', $city);
    }

    /**
     * @return array|null
     */
    public function getCoordinates() {
        $coordinates = parent::getCoordinates();
        if (null === $coordinates) {
            $coordinates = $this->getCity()->getCoordinates();
        }
        return $coordinates;
    }

    public function getLevel() {
        return CM_Model_Location::LEVEL_ZIP;
    }

    public function getParent($level = null) {
        if (null === $level) {
            $level = CM_Model_Location::LEVEL_CITY;
        }
        $level = (int) $level;
        switch ($level) {
            case CM_Model_Location::LEVEL_COUNTRY:
                return $this->getCity()->getCountry();
            case CM_Model_Location::LEVEL_STATE:
                return $this->getCity()->getState();
            case CM_Model_Location::LEVEL_CITY:
                return $this->getCity();
            case CM_Model_Location::LEVEL_ZIP:
                return $this;
        }
        throw new CM_Exception_Invalid('Invalid location level', null, ['level' => $level]);
    }

    public function _getSchema() {
        return new CM_Model_Schema_Definition(array(
            'cityId' => array('type' => 'CM_Model_Location_City'),
            'name'   => array('type' => 'string'),
            'lat'    => array('type' => 'float', 'optional' => true),
            'lon'    => array('type' => 'float', 'optional' => true),
        ));
    }

    /**
     * @param CM_Model_Location_City $city
     * @param string                 $name
     * @param float|null             $lat
     * @param float|null             $lon
     * @return CM_Model_Location_Zip
     */
    public static function create(CM_Model_Location_City $city, $name, $lat = null, $lon = null) {
        $zip = new self();
        $zip->_set(array(
            'cityId' => $city,
            'name'   => $name,
            'lat'    => $lat,
            'lon'    => $lon,
        ));
        $zip->commit();
        return $zip;
    }
}
