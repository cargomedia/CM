<?php

class CMService_MaxMind extends CM_Class_Abstract {

    const COUNTRY_URL = 'https://raw.github.com/lukes/ISO-3166-Countries-with-Regional-Codes/master/all/all.csv';
    const REGION_URL = 'http://www.maxmind.com/download/geoip/misc/region_codes.csv';
    const GEO_LITE_CITY_URL = 'http://geolite.maxmind.com/download/geoip/database/GeoLiteCity_CSV/GeoLiteCity-latest.zip';

    const CACHE_LIFETIME = 604800; // Keep downloaded files for one week (MaxMind update period)

    /** @var CM_File|null */
    protected $_geoIpFile;

    /** @var CM_OutputStream_Interface */
    protected $_streamError, $_streamOutput;

    /** @var bool */
    protected $_verbose, $_withoutIpBlocks;

    /** @var array */
    protected
        $_countryList, $_countryListOld, $_countryIdList, $_countryCodeListByMaxMind,
        $_countryListAdded, $_countryListRemoved, $_countryListRenamed,
        $_regionListByCountry, $_regionListByCountryOld, $_regionIdListByCountry, $_regionIdListByMaxMind,
        $_regionListByCountryAdded, $_regionListByCountryRemoved, $_regionListByCountryRenamed, $_regionListByCountryUpdatedCode, $_regionListByCountryRemovedCodeInUse,
        $_cityListByRegion, $_cityListByRegionOld, $_cityIdList,
        $_cityListByRegionAdded, $_cityListByRegionRemoved, $_cityListByRegionRenamed, $_cityListByRegionUpdatedCode, $_cityListUpdatedRegion,
        $_locationTree, $_locationTreeOld,
        $_zipCodeListByCityAdded, $_zipCodeListByCityRemoved, $_zipCodeIdListByMaxMind,
        $_ipBlockListByLocation;

    /**
     * @param CM_File|null                   $geoIpFile
     * @param CM_OutputStream_Interface|null $streamOutput
     * @param CM_OutputStream_Interface|null $streamError
     * @param bool|null                      $withoutIpBlocks
     * @param bool|null                      $verbose
     */
    public function __construct(CM_File $geoIpFile = null, CM_OutputStream_Interface $streamOutput = null, CM_OutputStream_Interface $streamError = null, $withoutIpBlocks = null, $verbose = null) {
        $this->_setGeoIpFile($geoIpFile);
        if (null === $streamOutput) {
            $streamOutput = new CM_OutputStream_Null();
        }
        if (null === $streamError) {
            $streamError = new CM_OutputStream_Null();
        }
        $this->_streamOutput = $streamOutput;
        $this->_streamError = $streamError;
        $this->_withoutIpBlocks = (bool) $withoutIpBlocks;
        $this->_verbose = (bool) $verbose;
    }

    public function outdated() {
        $this->_streamOutput->writeln('Updating locations database…');
        $this->_readCountryListOld();
        $this->_updateCountryList();
        $this->_compareCountryLists();
        $this->_readRegionListOld();
        $this->_updateRegionList();
        $this->_readLocationTreeOld();
        $this->_updateLocationTree();
        $this->_compareRegionLists();
        $this->_compareLocationTrees();
    }

    public function upgrade() {
        $this->outdated();
        $this->_upgradeCountryList();
        $this->_upgradeRegionList();
        $this->_upgradeCityList();
        $this->_upgradeZipCodeList();
        $this->_upgradeIpBlocks();
        CM_Model_Location::createAggregation();
        $type = new CM_Elasticsearch_Type_Location();
        $searchIndexCli = new CM_Elasticsearch_Index_Cli(null, $this->_streamOutput, $this->_streamError);
        $searchIndexCli->create($type->getIndex()->getName());
    }

    protected function _compareCountryLists() {
        $this->_streamOutput->writeln('Comparing both country listings…');

        $infoListAdded = array();
        $infoListUpdated = array();
        $infoListRemoved = array();

        $this->_countryListAdded = array_diff_key($this->_countryList, $this->_countryListOld);
        asort($this->_countryListAdded);

        $this->_countryListRemoved = array_diff_key($this->_countryListOld, $this->_countryList);
        asort($this->_countryListRemoved);

        $this->_countryListRenamed = array();
        foreach ($this->_countryListOld as $countryCode => $countryNameOld) {
            if (isset($this->_countryList[$countryCode]) && ($this->_countryList[$countryCode] !== $countryNameOld)) {
                $this->_countryListRenamed[$countryCode] = array(
                    'name'    => $this->_countryList[$countryCode],
                    'nameOld' => $countryNameOld,
                );
            }
        }

        foreach ($this->_countryListAdded as $countryCode => $countryName) {
            $infoListAdded['Countries added'][] = $countryName . ' (' . $countryCode . ')';
        }

        foreach ($this->_countryListRemoved as $countryCode => $countryName) {
            $infoListRemoved['Countries removed'][] = $countryName . ' (' . $countryCode . ')';
        }

        foreach ($this->_countryListRenamed as $countryCode => $countryNames) {
            $infoListUpdated['Countries renamed'][] = $countryNames['nameOld'] . ' => ' . $countryNames['name'] . ' (' . $countryCode . ')';
        }

        $this->_printInfoList($infoListAdded, '+');
        $this->_printInfoList($infoListUpdated, '~');
        $this->_printInfoList($infoListRemoved, '-');
    }

    /**
     * @throws CM_Exception
     */
    protected function _compareRegionLists() {
        $this->_streamOutput->writeln('Comparing both region listings…');

        $this->_regionListByCountryUpdatedCode = array();
        $this->_regionListByCountryRemovedCodeInUse = array();
        foreach ($this->_regionListByCountryOld as $countryCode => $regionListOld) {
            if (!isset($this->_regionListByCountry[$countryCode])) {
                continue;
            }
            $regionList = $this->_regionListByCountry[$countryCode];
            $regionCodeList = array_flip($regionList);
            $regionIdListUpdatedCode = array();
            foreach ($regionListOld as $regionCodeOld => $regionNameOld) {
                if (isset($regionList[$regionCodeOld]) && ($regionNameOld === $regionList[$regionCodeOld])) {
                    continue;
                }
                if (isset($regionCodeList[$regionNameOld])) {
                    $regionCode = $regionCodeList[$regionNameOld];
                    $this->_regionListByCountryUpdatedCode[$countryCode][$regionCodeOld] = $regionCode;
                    $regionIdListUpdatedCode[$regionCode] = $this->_regionIdListByCountry[$countryCode][$regionCodeOld];
                }
            }
            if (isset($this->_regionListByCountryUpdatedCode[$countryCode])) {
                $regionListRemovedCodeInUse = array();
                foreach ($regionListOld as $regionCodeOld => $regionNameOld) {
                    if (in_array($regionCodeOld, $this->_regionListByCountryUpdatedCode[$countryCode])
                        && !isset($this->_regionListByCountryUpdatedCode[$countryCode][$regionCodeOld])
                    ) {
                        $regionId = $this->_regionIdListByCountry[$countryCode][$regionCodeOld];
                        $regionListRemovedCodeInUse[$regionId] = $regionCodeOld;
                    }
                }
                asort($regionListRemovedCodeInUse);
                if (!empty($regionListRemovedCodeInUse)) {
                    $this->_regionListByCountryRemovedCodeInUse[$countryCode] = $regionListRemovedCodeInUse;
                }
            }
            foreach ($regionIdListUpdatedCode as $regionCode => $regionId) {
                $this->_regionIdListByCountry[$countryCode][$regionCode] = $regionId;
            }
        }

        $this->_regionListByCountryAdded = array();
        foreach ($this->_regionListByCountry as $countryCode => $regionList) {
            $regionListOld = isset($this->_regionListByCountryOld[$countryCode]) ? $this->_regionListByCountryOld[$countryCode] : array();
            $regionListAdded = array_diff_key($regionList, $regionListOld);
            if (isset($this->_regionListByCountryUpdatedCode[$countryCode])) {
                foreach ($this->_regionListByCountryUpdatedCode[$countryCode] as $regionCodeUpdated) {
                    unset($regionListAdded[$regionCodeUpdated]);
                }
            }
            asort($regionListAdded);
            if (!empty($regionListAdded)) {
                $this->_regionListByCountryAdded[$countryCode] = $regionListAdded;
            }
        }

        $this->_regionListByCountryRemoved = array();
        foreach ($this->_regionListByCountryOld as $countryCode => $regionListOld) {
            $regionList = isset($this->_regionListByCountry[$countryCode]) ? $this->_regionListByCountry[$countryCode] : array();
            $regionListRemoved = array_diff_key($regionListOld, $regionList);
            if (isset($this->_regionListByCountryUpdatedCode[$countryCode])) {
                foreach (array_keys($this->_regionListByCountryUpdatedCode[$countryCode]) as $regionCodeOld) {
                    unset($regionListRemoved[$regionCodeOld]);
                }
            }
            asort($regionListRemoved);
            if (!empty($regionListRemoved)) {
                $this->_regionListByCountryRemoved[$countryCode] = $regionListRemoved;
            }
        }

        $this->_regionListByCountryRenamed = array();
        foreach ($this->_regionListByCountryOld as $countryCode => $regionListOld) {
            if (!isset($this->_regionListByCountry[$countryCode])) {
                continue;
            }
            foreach ($regionListOld as $regionCode => $regionNameOld) {
                if (isset($this->_regionListByCountry[$countryCode][$regionCode]) &&
                    ($this->_regionListByCountry[$countryCode][$regionCode] !== $regionNameOld)
                ) {
                    $this->_regionListByCountryRenamed[$countryCode][$regionCode] = array(
                        'name'    => $this->_regionListByCountry[$countryCode][$regionCode],
                        'nameOld' => $regionNameOld,
                    );
                }
            }
            if (isset($this->_regionListByCountryUpdatedCode[$countryCode])) {
                foreach ($this->_regionListByCountryUpdatedCode[$countryCode] as $regionCodeOld => $regionCodeUpdated) {
                    unset($this->_regionListByCountryRenamed[$countryCode][$regionCodeOld]);
                    unset($this->_regionListByCountryRenamed[$countryCode][$regionCodeUpdated]);
                }
            }
        }

        $infoListWarning = array();
        $infoListAdded = array();
        $infoListUpdated = array();
        $infoListRemoved = array();

        foreach ($this->_regionListByCountryAdded as $countryCode => $regionListAdded) {
            if (!isset($this->_countryList[$countryCode])) {
                throw new CM_Exception('Unknown country code `' . $countryCode . '`');
            }
            $countryName = $this->_countryList[$countryCode];
            foreach ($regionListAdded as $regionCode => $regionName) {
                $infoListAdded['Regions added'][$countryName][] = $regionName . ' (' . $regionCode . ')';
            }
        }

        foreach ($this->_regionListByCountryRemoved as $countryCode => $regionListRemoved) {
            if (!isset($this->_countryListOld[$countryCode])) {
                throw new CM_Exception('Unknown country code `' . $countryCode . '`');
            }
            $countryName = $this->_countryListOld[$countryCode];
            foreach ($regionListRemoved as $regionCode => $regionName) {
                $infoListRemoved['Regions removed'][$countryName][] = $regionName . ' (' . $regionCode . ')';
            }
        }

        foreach ($this->_regionListByCountryRemovedCodeInUse as $countryCode => $regionListRemovedCodeInUse) {
            if (!isset($this->_countryListOld[$countryCode])) {
                throw new CM_Exception('Unknown country code `' . $countryCode . '`');
            }
            $countryName = $this->_countryListOld[$countryCode];
            foreach ($regionListRemovedCodeInUse as $regionCode) {
                $regionNameOld = $this->_regionListByCountryOld[$countryCode][$regionCode];
                $infoListWarning['Regions to be deleted'][$countryName][] = $regionNameOld . ' (' . $regionCode . ')';
            }
        }

        foreach ($this->_regionListByCountryRenamed as $countryCode => $regionListRenamed) {
            if (!isset($this->_countryList[$countryCode])) {
                throw new CM_Exception('Unknown country code `' . $countryCode . '`');
            }
            $countryName = $this->_countryList[$countryCode];
            foreach ($regionListRenamed as $regionCode => $regionNames) {
                $infoListUpdated['Regions renamed'][$countryName][] =
                    $regionNames['nameOld'] . ' => ' . $regionNames['name'] . ' (' . $regionCode . ')';
            }
        }

        foreach ($this->_regionListByCountryUpdatedCode as $countryCode => $regionListUpdatedCode) {
            if (!isset($this->_countryList[$countryCode])) {
                throw new CM_Exception('Unknown country code `' . $countryCode . '`');
            }
            $countryName = $this->_countryList[$countryCode];
            foreach ($regionListUpdatedCode as $regionCodeOld => $regionCode) {
                $infoListUpdated['Regions with updated code'][$countryName][] =
                    $this->_regionListByCountry[$countryCode][$regionCode] . ' (' . $regionCodeOld . ' => ' . $regionCode . ')';
            }
        }

        $this->_printInfoList($infoListWarning, '!');
        $this->_printInfoList($infoListAdded, '+');
        $this->_printInfoList($infoListUpdated, '~');
        $this->_printInfoList($infoListRemoved, '-');
    }

    protected function _compareLocationTrees() {
        $this->_streamOutput->writeln('Comparing both location trees…');

        $infoListWarning = array();
        $infoListAdded = array();
        $infoListUpdated = array();
        $infoListRemoved = array();

        // Check for missing data
        foreach ($this->_locationTree as $countryCode => $countryData) {
            if (isset($this->_countryList[$countryCode])) {
                $countryName = $this->_countryList[$countryCode];
            } elseif (isset($countryData['location']['name'])) {
                $countryName = $countryData['location']['name'];
            } else {
                $countryName = $countryCode;
            }
            if (!isset($countryData['regions'])) {
                $infoListWarning['Countries without locations'][] = $countryName;
            } else {
                $regionCount = count($countryData['regions']);
                if (!isset($countryData['location'])) {
                    $s = $regionCount > 1 ? 's' : '';
                    $infoListWarning['Countries without location data'][] = $countryName . ' (' . $regionCount . ' region' . $s . ')';
                }
                if (1 === $regionCount) {
                    $regionCode = array_keys($countryData['regions']);
                    if ('' === reset($regionCode)) {
                        $infoListWarning['Countries without regions'][] = $countryName;
                    }
                }
                foreach ($countryData['regions'] as $regionCode => $regionData) {
                    $regionName = $this->_getRegionName($countryCode, $regionCode);
                    if (!isset($regionData['cities'])) {
                        $infoListWarning['Regions without cities'][$countryName][] = $regionName;
                    } else {
                        if (!isset($regionData['location']) && !isset($this->_regionListByCountry[$countryCode][$regionCode])) {
                            $cityCount = count($regionData['cities']);
                            $s = $cityCount > 1 ? 'ies' : 'y';
                            foreach ($regionData['cities'] as $cityName => $cityData) {
                                $cityCode = $cityData['location']['maxMind'];
                                $infoListWarning['Cities without region'][$countryName . ', ' . $cityCount . ' cit' . $s][] =
                                    $cityName . ' (' . $cityCode . ')';
                            }
                        }
                        foreach ($regionData['cities'] as $cityName => $cityData) {
                            if (isset($cityData['zipCodes'])) {
                                if (!isset($cityData['location'])) {
                                    $zipCodeCount = count($cityData['zipCodes']);
                                    $s = $zipCodeCount > 1 ? 's' : '';
                                    $infoListWarning['Cities without location data'][$regionName . ' (' . $countryName . ')'][] =
                                        $cityName . ' (' . $zipCodeCount . ' zip code' . $s . ')';
                                }
                            }
                        }
                    }
                }
            }
        }

        $this->_cityListByRegionAdded = array();
        $this->_cityListByRegionRemoved = array();
        $this->_cityListByRegionRenamed = array();
        $this->_cityListByRegionUpdatedCode = array();
        $this->_cityListUpdatedRegion = array();
        $this->_zipCodeListByCityAdded = array();
        $this->_zipCodeListByCityRemoved = array();
        $cityIdListUpdatedCode = array();

        // Merge contents of deleted regions into the regions that are going to replace them
        foreach ($this->_regionListByCountryRemovedCodeInUse as $countryCode => $regionListRemovedCodeInUse) {
            foreach ($regionListRemovedCodeInUse as $regionIdOld => $regionCodeOld) {
                $regionCode = null;
                foreach ($this->_regionListByCountryUpdatedCode[$countryCode] as $regionCode => $regionCodeUpdated) {
                    if ($regionCodeUpdated == $regionCodeOld) {
                        break;
                    }
                }
                foreach ($this->_locationTreeOld[$countryCode]['regions'][$regionCodeOld]['cities'] as $cityName => $cityData) {
                    if (!isset($this->_locationTreeOld[$countryCode]['regions'][$regionCode]['cities'][$cityName])) {
                        $this->_locationTreeOld[$countryCode]['regions'][$regionCode]['cities'][$cityName] = $cityData;
                    }
                }
                foreach ($this->_cityListByRegionOld[$countryCode][$regionCodeOld] as $cityCode => $cityName) {
                    if (!isset($this->_cityListByRegionOld[$countryCode][$regionCode][$cityCode])) {
                        $this->_cityListByRegionOld[$countryCode][$regionCode][$cityCode] = $cityName;
                    }
                }
                unset($this->_locationTreeOld[$countryCode]['regions'][$regionCodeOld]);
                unset($this->_cityListByRegionOld[$countryCode][$regionCodeOld]);
                unset($this->_regionListByCountryOld[$countryCode][$regionCodeOld]);
            }
        }

        // Look for cities without region in countries that have been added
        foreach ($this->_countryListAdded as $countryCode => $countryName) {
            $regionCode = null;
            $cityListAdded = isset($this->_cityListByRegion[$countryCode][$regionCode]) ? $this->_cityListByRegion[$countryCode][$regionCode] : array();
            asort($cityListAdded);
            if (!empty($cityListAdded)) {
                $this->_cityListByRegionAdded[$countryCode][$regionCode] = $cityListAdded;
            }
        }

        // Look for cities in regions that have been added
        foreach ($this->_regionListByCountryAdded as $countryCode => $regionListAdded) {
            foreach ($regionListAdded as $regionCode => $regionName) {
                $cityListAdded = isset($this->_cityListByRegion[$countryCode][$regionCode]) ? $this->_cityListByRegion[$countryCode][$regionCode] : array();
                asort($cityListAdded);
                if (!empty($cityListAdded)) {
                    $this->_cityListByRegionAdded[$countryCode][$regionCode] = $cityListAdded;
                }
            }
        }

        // Look for cities without region in countries that have been removed
        foreach ($this->_countryListRemoved as $countryCode => $countryName) {
            $regionCodeOld = null;
            $cityListRemoved = isset($this->_cityListByRegionOld[$countryCode][$regionCodeOld]) ? $this->_cityListByRegionOld[$countryCode][$regionCodeOld] : array();
            asort($cityListRemoved);
            if (!empty($cityListRemoved)) {
                $this->_cityListByRegionRemoved[$countryCode][$regionCodeOld] = $cityListRemoved;
            }
        }

        // Look for cities in regions that have been removed
        foreach ($this->_regionListByCountryRemoved as $countryCode => $regionListRemoved) {
            foreach ($regionListRemoved as $regionCodeOld => $regionName) {
                $cityListRemoved = isset($this->_cityListByRegionOld[$countryCode][$regionCodeOld]) ? $this->_cityListByRegionOld[$countryCode][$regionCodeOld] : array();
                asort($cityListRemoved);
                if (!empty($cityListRemoved)) {
                    $this->_cityListByRegionRemoved[$countryCode][$regionCodeOld] = $cityListRemoved;
                }
            }
        }

        // Look for changes in countries that have been kept
        $countryCodeList = array_keys($this->_countryList);
        $countryCodeListOld = array_keys($this->_countryListOld);
        $countryCodeListKept = array_intersect($countryCodeList, $countryCodeListOld);
        foreach ($countryCodeListKept as $countryCode) {
            if (isset($this->_countryList[$countryCode])) {
                $countryName = $this->_countryList[$countryCode];
            } else {
                $countryName = $countryCode;
            }

            // Look for changes in regions that have been kept

            // Retrieve the right region if its code has been updated
            $regionCodeList = isset($this->_regionListByCountry[$countryCode]) ? array_keys($this->_regionListByCountry[$countryCode]) : array();
            $regionCodeListOld = isset($this->_regionListByCountryOld[$countryCode]) ? array_keys($this->_regionListByCountryOld[$countryCode]) : array();
            $regionCodeList[] = null;
            $regionCodeListOld[] = null;
            $regionCodeListOldByNewCode = array();
            foreach ($regionCodeListOld as $regionCodeOld) {
                $regionCode = $regionCodeOld;
                if (isset($this->_regionListByCountryUpdatedCode[$countryCode][$regionCodeOld])) {
                    $regionCode = $this->_regionListByCountryUpdatedCode[$countryCode][$regionCodeOld];
                }
                $regionCodeListOldByNewCode[$regionCode] = $regionCodeOld;
            }

            $regionCodeListKept = array_intersect($regionCodeList, array_keys($regionCodeListOldByNewCode));
            foreach ($regionCodeListKept as $regionCode) {
                $regionName = $this->_getRegionName($countryCode, $regionCode);
                $regionCodeOld = $regionCodeListOldByNewCode[$regionCode];
                $cityList = isset($this->_cityListByRegion[$countryCode][$regionCode]) ? $this->_cityListByRegion[$countryCode][$regionCode] : array();
                $cityListOld = isset($this->_cityListByRegionOld[$countryCode][$regionCodeOld]) ? $this->_cityListByRegionOld[$countryCode][$regionCodeOld] : array();

                // Cities with updated code (name lookup within the region)
                foreach ($cityListOld as $cityCodeOld => $cityNameOld) {
                    if (isset($cityList[$cityCodeOld]) && ($cityList[$cityCodeOld] === $cityNameOld)) {
                        continue;
                    }
                    $cityCodeListNew = array();
                    foreach ($cityList as $cityCodeNew => $cityName) {
                        if ($cityName === $cityNameOld) {
                            $cityCodeListNew[] = $cityCodeNew;
                        }
                    }
                    if (empty($cityCodeListNew)) {
                        continue;
                    }
                    if (1 === count($cityCodeListNew)) {
                        $cityCode = reset($cityCodeListNew);
                        $this->_cityListByRegionUpdatedCode[$countryCode][$regionCode][$cityCodeOld] = $cityCode;
                        $cityIdListUpdatedCode[$cityCode] = $this->_cityIdList[$cityCodeOld];
                    } else {
                        $infoListWarning['Cities with ambiguous updated code'][$countryName . ' / ' . $regionName][] =
                            $cityNameOld . ' (' . $cityCodeOld . ' => ' . implode(', ', $cityCodeListNew) . ')';
                    }
                }

                // Cities added (new code that doesn't come from a code update)
                $cityListAdded = array_diff_key($cityList, $cityListOld);
                if (isset($this->_cityListByRegionUpdatedCode[$countryCode][$regionCode])) {
                    foreach ($this->_cityListByRegionUpdatedCode[$countryCode][$regionCode] as $cityCodeUpdated) {
                        unset($cityListAdded[$cityCodeUpdated]);
                    }
                }
                asort($cityListAdded);
                if (!empty($cityListAdded)) {
                    $this->_cityListByRegionAdded[$countryCode][$regionCode] = $cityListAdded;
                }

                // Cities removed (missing code that hasn't been updated)
                $cityListRemoved = array_diff_key($cityListOld, $cityList);
                if (isset($this->_cityListByRegionUpdatedCode[$countryCode][$regionCode])) {
                    foreach (array_keys($this->_cityListByRegionUpdatedCode[$countryCode][$regionCode]) as $cityCodeOld) {
                        unset($cityListRemoved[$cityCodeOld]);
                    }
                }
                asort($cityListRemoved);
                if (!empty($cityListRemoved)) {
                    $this->_cityListByRegionRemoved[$countryCode][$regionCode] = $cityListRemoved;
                }

                // Cities renamed (same code if it hasn't been updated and doesn't come from a code update)
                foreach ($cityListOld as $cityCode => $cityNameOld) {
                    if (!isset($cityList[$cityCode])) {
                        continue;
                    }
                    $cityName = $cityList[$cityCode];
                    if ($cityName !== $cityNameOld) {
                        $this->_cityListByRegionRenamed[$countryCode][$regionCode][$cityCode] = array(
                            'name'    => $cityName,
                            'nameOld' => $cityNameOld,
                        );
                    }
                }
                if (isset($this->_cityListByRegionUpdatedCode[$countryCode][$regionCode])) {
                    foreach ($this->_cityListByRegionUpdatedCode[$countryCode][$regionCode] as $cityCodeOld => $cityCodeUpdated) {
                        unset($this->_cityListByRegionRenamed[$countryCode][$regionCode][$cityCodeOld]);
                        unset($this->_cityListByRegionRenamed[$countryCode][$regionCode][$cityCodeUpdated]);
                    }
                }

                // Look for changes in cities that have been kept

                // Retrieve the right city if its code has been updated
                $cityCodeListOldByNewCode = array();
                foreach (array_keys($cityListOld) as $cityCodeOld) {
                    $cityCode = $cityCodeOld;
                    if (isset($this->_cityListByRegionUpdatedCode[$countryCode][$regionCode][$cityCodeOld])) {
                        $cityCode = $this->_cityListByRegionUpdatedCode[$countryCode][$regionCode][$cityCodeOld];
                    }
                    $cityCodeListOldByNewCode[$cityCode] = $cityCodeOld;
                }

                $cityCodeListKept = array_intersect(array_keys($cityList), array_keys($cityCodeListOldByNewCode));
                foreach ($cityCodeListKept as $cityCode) {
                    $cityCodeOld = $cityCodeListOldByNewCode[$cityCode];
                    $cityName = $cityList[$cityCode];
                    $cityNameOld = $cityListOld[$cityCodeOld];
                    $zipCodeList = isset($this->_locationTree[$countryCode]['regions'][$regionCode]['cities'][$cityName]['zipCodes']) ? $this->_locationTree[$countryCode]['regions'][$regionCode]['cities'][$cityName]['zipCodes'] : array();
                    $zipCodeListOld = isset($this->_locationTreeOld[$countryCode]['regions'][$regionCodeOld]['cities'][$cityNameOld]['zipCodes']) ? $this->_locationTreeOld[$countryCode]['regions'][$regionCodeOld]['cities'][$cityNameOld]['zipCodes'] : array();

                    // Zip codes added
                    $zipCodeListAdded = array_diff_key($zipCodeList, $zipCodeListOld);
                    ksort($zipCodeListAdded);
                    if (!empty($zipCodeListAdded)) {
                        $this->_zipCodeListByCityAdded[$countryCode][$regionCode][$cityCode] = $zipCodeListAdded;
                    }

                    // Zip codes removed
                    $zipCodeListRemoved = array_diff_key($zipCodeListOld, $zipCodeList);
                    ksort($zipCodeListRemoved);
                    if (!empty($zipCodeListRemoved)) {
                        $this->_zipCodeListByCityRemoved[$countryCode][$regionCode][$cityCode] = $zipCodeListRemoved;
                    }

                    // Store ID of kept zip codes
                    foreach ($zipCodeListOld as $zipCode => $zipCodeData) {
                        if (isset($zipCodeList[$zipCode])) {
                            $zipCodeId = $zipCodeData['id'];
                            $maxMind = $zipCodeList[$zipCode]['maxMind'];
                            $this->_zipCodeIdListByMaxMind[$maxMind] = $zipCodeId;
                        }
                    }

                    // Info
                    $zipCodeCountOld = count($zipCodeListOld);
                    $zipCodeCountAdded = count($zipCodeListAdded);
                    if ($zipCodeCountAdded) {
                        $zipCodeRateAdded = $zipCodeCountOld ? $zipCodeCountAdded / $zipCodeCountOld : null;
                        $zipCodeRateAddedInfo = $zipCodeCountOld ? ' (' . round($zipCodeRateAdded * 100) . '%)' : '';
                        if ($zipCodeRateAdded < 1.0) {
                            $infoListAdded['Zip codes added'][$countryName . ' / ' . $regionName][] =
                                $cityName . ', ' . $zipCodeCountAdded . $zipCodeRateAddedInfo;
                        } else {
                            foreach (array_keys($zipCodeListAdded) as $zipCode) {
                                $infoTitle = $countryName . ' / ' . $regionName . ' / ' . $cityName . ', ' . $zipCodeCountAdded . ' zip codes' .
                                    $zipCodeRateAddedInfo;
                                $infoListAdded['Zip codes added'][$infoTitle][] = $zipCode;
                            }
                        }
                    }

                    $zipCodeCountRemoved = count($zipCodeListRemoved);
                    if ($zipCodeCountRemoved) {
                        $zipCodeRateRemoved = $zipCodeCountRemoved / $zipCodeCountOld;
                        $zipCodeRateRemovedInfo = ' (' . round($zipCodeRateRemoved * 100) . '%)';
                        if ($zipCodeRateRemoved < 0.2) {
                            $infoListRemoved['Zip codes removed'][$countryName . ' / ' . $regionName][] =
                                $cityName . ', ' . $zipCodeCountRemoved . $zipCodeRateRemovedInfo;
                        } else {
                            foreach (array_keys($zipCodeListRemoved) as $zipCode) {
                                $infoTitle = $countryName . ' / ' . $regionName . ' / ' . $cityName . ', ' . $zipCodeCountRemoved . ' zip codes' .
                                    $zipCodeRateRemovedInfo;
                                $infoListRemoved['Zip codes removed'][$infoTitle][] = $zipCode;
                            }
                        }
                    }
                }

                // Store ID of kept regions
                if (isset($this->_regionIdListByCountry[$countryCode][$regionCode]) &&
                    isset($this->_locationTree[$countryCode]['regions'][$regionCode]['location']['maxMind'])
                ) {
                    $regionId = $this->_regionIdListByCountry[$countryCode][$regionCode];
                    $maxMind = $this->_locationTree[$countryCode]['regions'][$regionCode]['location']['maxMind'];
                    $this->_regionIdListByMaxMind[$maxMind] = $regionId;
                }
            }

            // Look for cities with updated region

            $regionCodeListByCityOld = array();
            foreach ($regionCodeListOldByNewCode as $regionCode => $regionCodeOld) {
                $cityListOld = isset($this->_cityListByRegionOld[$countryCode][$regionCodeOld]) ? $this->_cityListByRegionOld[$countryCode][$regionCodeOld] : array();
                // Retrieve the right city if its code has been updated
                foreach ($cityListOld as $cityCodeOld => $cityNameOld) {
                    $cityCode = $cityCodeOld;
                    if (isset($this->_cityListByRegionUpdatedCode[$countryCode][$regionCode][$cityCodeOld])) {
                        $cityCode = $this->_cityListByRegionUpdatedCode[$countryCode][$regionCode][$cityCodeOld];
                    }
                    $regionCodeListByCityOld[$cityCode] = $regionCode;
                }
            }
            foreach ($regionCodeList as $regionCode) {
                $cityList = isset($this->_cityListByRegion[$countryCode][$regionCode]) ? $this->_cityListByRegion[$countryCode][$regionCode] : array();
                foreach ($cityList as $cityCode => $cityName) {
                    if (isset($regionCodeListByCityOld[$cityCode]) && ($regionCode != $regionCodeListByCityOld[$cityCode])) {
                        $regionCodeOld = $regionCodeListByCityOld[$cityCode];
                        $this->_cityListUpdatedRegion[$countryCode][$cityCode] = array(
                            'regionCode'    => $regionCode,
                            'regionCodeOld' => $regionCodeListOldByNewCode[$regionCodeOld],
                        );
                        unset($this->_cityListByRegionAdded[$countryCode][$regionCode][$cityCode]);
                        unset($this->_cityListByRegionRemoved[$countryCode][$regionCodeOld][$cityCode]);
                    }
                }
            }

            // Info
            foreach ($regionCodeListKept as $regionCode) {
                $regionName = $this->_getRegionName($countryCode, $regionCode);
                $regionCodeOld = $regionCodeListOldByNewCode[$regionCode];
                $cityListOld = isset($this->_cityListByRegionOld[$countryCode][$regionCodeOld]) ? $this->_cityListByRegionOld[$countryCode][$regionCodeOld] : array();
                $cityCountOld = count($cityListOld);

                $cityListAdded = isset($this->_cityListByRegionAdded[$countryCode][$regionCode]) ? $this->_cityListByRegionAdded[$countryCode][$regionCode] : array();
                $cityListRemoved = isset($this->_cityListByRegionRemoved[$countryCode][$regionCode]) ? $this->_cityListByRegionRemoved[$countryCode][$regionCode] : array();

                $cityCountAdded = count($cityListAdded);
                if ($cityCountAdded) {
                    $cityRateAdded = $cityCountOld ? $cityCountAdded / $cityCountOld : null;
                    $cityRateAddedInfo = $cityCountOld ? ' (' . round($cityRateAdded * 100) . '%)' : '';
                    if ($cityRateAdded < 1.0) {
                        $infoListAdded['Cities added'][$countryName][] = $regionName . ', ' . $cityCountAdded . $cityRateAddedInfo;
                    } else {
                        foreach ($cityListAdded as $cityCode => $cityName) {
                            $infoTitle = $countryName . ' / ' . $regionName . ', ' . $cityCountAdded . ' cities' . $cityRateAddedInfo;
                            $infoListAdded['Cities added'][$infoTitle][] = $cityName . ' (' . $cityCode . ')';
                        }
                    }
                }

                $cityCountRemoved = count($cityListRemoved);
                if ($cityCountRemoved) {
                    $cityRateRemoved = $cityCountRemoved / $cityCountOld;
                    $cityRateRemovedInfo = ' (' . round($cityRateRemoved * 100) . '%)';
                    foreach ($cityListRemoved as $cityCode => $cityName) {
                        $infoTitle = $countryName . ' / ' . $regionName . ', ' . $cityCountRemoved . ' cities' . $cityRateRemovedInfo;
                        $infoListRemoved['Cities removed'][$infoTitle][] = $cityName . ' (' . $cityCode . ')';
                    }
                }

                if (!empty($this->_cityListByRegionUpdatedCode[$countryCode][$regionCode])) {
                    foreach ($this->_cityListByRegionUpdatedCode[$countryCode][$regionCode] as $cityCodeOld => $cityCode) {
                        $cityName = $this->_cityListByRegion[$countryCode][$regionCode][$cityCode];
                        $infoListUpdated['Cities with updated code'][$countryName . ' / ' . $regionName][] =
                            $cityName . ' (' . $cityCodeOld . ' => ' . $cityCode . ')';
                    }
                }

                if (!empty($this->_cityListByRegionRenamed[$countryCode][$regionCode])) {
                    foreach ($this->_cityListByRegionRenamed[$countryCode][$regionCode] as $cityCode => $cityNames) {
                        $infoListUpdated['Cities renamed'][$countryName . ' / ' . $regionName][] =
                            $cityNames['nameOld'] . ' => ' . $cityNames['name'] . ' (' . $cityCode . ')';
                    }
                }
            }

            if (isset($this->_cityListUpdatedRegion[$countryCode])) {
                foreach ($this->_cityListUpdatedRegion[$countryCode] as $cityCode => $regionCodes) {
                    $regionCode = $regionCodes['regionCode'];
                    $regionCodeOld = $regionCodes['regionCodeOld'];
                    $regionName = $this->_getRegionName($countryCode, $regionCode, true);
                    $regionNameOld = $this->_getRegionNameOld($countryCode, $regionCodeOld, true);
                    $cityName = $this->_cityListByRegion[$countryCode][$regionCode][$cityCode];
                    if ($regionName === 'Unknown region') {
                        $infoListUpdated['Region unset for cities'][$countryName . ' / ' . $regionNameOld][] =
                            $cityName . ' (' . $cityCode . ')';
                    } elseif ($regionNameOld === 'Unknown region') {
                        $infoListUpdated['Region set for cities'][$countryName . ' / ' . $regionName][] =
                            $cityName . ' (' . $cityCode . ')';
                    } else {
                        $infoListUpdated['Cities with updated region'][$countryName . ' / ' . $regionNameOld . ' => ' . $regionName][] =
                            $cityName . ' (' . $cityCode . ')';
                    }
                }
            }
        }
        foreach ($cityIdListUpdatedCode as $cityCode => $cityId) {
            $this->_cityIdList[$cityCode] = $cityId;
        }

        // Look for zip codes in cities that have been added
        foreach ($this->_cityListByRegionAdded as $countryCode => $cityListByRegionAdded) {
            foreach ($cityListByRegionAdded as $regionCode => $cityListAdded) {
                foreach ($cityListAdded as $cityCode => $cityName) {
                    $zipCodeListAdded = isset($this->_locationTree[$countryCode]['regions'][$regionCode]['cities'][$cityName]['zipCodes']) ? $this->_locationTree[$countryCode]['regions'][$regionCode]['cities'][$cityName]['zipCodes'] : array();
                    ksort($zipCodeListAdded);
                    if (!empty($zipCodeListAdded)) {
                        $this->_zipCodeListByCityAdded[$countryCode][$regionCode][$cityCode] = $zipCodeListAdded;
                    }
                }
            }
        }

        // Look for zip codes in cities that have been removed
        foreach ($this->_cityListByRegionRemoved as $countryCode => $cityListByRegionRemoved) {
            foreach ($cityListByRegionRemoved as $regionCodeOld => $cityListRemoved) {
                foreach ($cityListRemoved as $cityCode => $cityName) {
                    $zipCodeListRemoved = isset($this->_locationTreeOld[$countryCode]['regions'][$regionCodeOld]['cities'][$cityName]['zipCodes']) ? $this->_locationTreeOld[$countryCode]['regions'][$regionCodeOld]['cities'][$cityName]['zipCodes'] : array();
                    ksort($zipCodeListRemoved);
                    if (!empty($zipCodeListRemoved)) {
                        $this->_zipCodeListByCityRemoved[$countryCode][$regionCodeOld][$cityCode] = $zipCodeListRemoved;
                    }
                }
            }
        }

        $this->_printInfoList($infoListWarning, '!');
        $this->_printInfoList($infoListAdded, '+');
        $this->_printInfoList($infoListUpdated, '~');
        $this->_printInfoList($infoListRemoved, '-');
    }

    /**
     * @param CM_File     $file
     * @param string|null $url
     * @return string
     * @throws CM_Exception
     * @codeCoverageIgnore
     */
    protected function _download(CM_File $file, $url = null) {
        if ($file->exists()) {
            $modificationTime = $file->getModified();
            if (time() - $modificationTime > self::CACHE_LIFETIME) {
                $file->delete();
            }
        }
        if ($file->exists()) {
            $contents = $file->read();
        } else {
            if (null === $url) {
                throw new CM_Exception('File not found: `' . $file->getPath() . '`');
            }
            $contents = CM_Util::getContents($url);
            if (false === $contents) {
                throw new CM_Exception('Download of `' . $url . '` failed');
            }
            $file->write($contents);
        }
        return $contents;
    }

    /**
     * Download ISO-3166-2 countries listing (from a handy but unofficial source)
     *
     * @return array List of array($countryName, $countryCode)
     * @codeCoverageIgnore
     */
    protected function _getCountryData() {
        $this->_streamOutput->writeln('Downloading new country listing…');
        $countriesPath = CM_Bootloader::getInstance()->getDirTmp() . 'countries.csv';
        $countriesFile = new CM_File($countriesPath);
        $countriesContents = $this->_download($countriesFile, self::COUNTRY_URL);

        $this->_streamOutput->writeln('Reading new country listing…');
        $countryData = array();
        $countryData[] = array('Netherlands Antilles', 'AN'); // Adding missing records
        $rows = preg_split('#[\r\n]++#', $countriesContents);
        foreach ($rows as $i => $row) {
            if ($i === 0) {
                continue; // Skip column names
            }
            $row = trim($row);
            $row = str_replace('\\,', ',',
                '"' . preg_replace('#([^\\\\]),#', '$1",', $row, 1)); // Hack: Add delimiters in country name (first column) for str_getcsv()
            $csv = str_getcsv($row);
            if (count($csv) <= 1) {
                continue; // Skip empty lines
            }
            $countryData[] = $csv;
        }
        return $countryData;
    }

    /**
     * Download MaxMind GeoIP data
     *
     * @return array List of array($ipStart, $ipEnd, $maxMind)
     * @codeCoverageIgnore
     */
    protected function _getIpData() {
        $this->_streamOutput->writeln('Reading new IP blocks…');
        if (null !== $this->_geoIpFile) {
            $blocksFileContents = $this->_readBlocksData($this->_geoIpFile);
        } else {
            $geoLiteCityPath = CM_Bootloader::getInstance()->getDirTmp() . 'GeoLiteCity.zip';
            $geoLiteCityFile = new CM_File($geoLiteCityPath);
            $this->_download($geoLiteCityFile, self::GEO_LITE_CITY_URL);
            $blocksFileContents = $this->_readBlocksData($geoLiteCityFile);
        }
        $ipData = array();
        $rows = preg_split('#[\r\n]++#', $blocksFileContents);
        foreach ($rows as $i => $row) {
            if ($i < 2) {
                continue; // Skip column names and examples
            }
            $csv = str_getcsv(trim($row));
            if (count($csv) <= 1) {
                continue; // Skip empty lines
            }
            $ipData[] = $csv;
        }
        return $ipData;
    }

    /**
     * Download MaxMind location data
     *
     * @return array List of array($maxMind, $countryCode, $regionCode, $cityName, $zipCode, $latitude, $longitude)
     * @codeCoverageIgnore
     */
    protected function _getLocationData() {
        $this->_streamOutput->writeln('Reading new location tree…');
        if (null !== $this->_geoIpFile) {
            $citiesFileContents = $this->_readLocationData($this->_geoIpFile);
        } else {
            $geoLiteCityPath = CM_Bootloader::getInstance()->getDirTmp() . 'GeoLiteCity.zip';
            $geoLiteCityFile = new CM_File($geoLiteCityPath);
            $this->_download($geoLiteCityFile, self::GEO_LITE_CITY_URL);
            $citiesFileContents = $this->_readLocationData($geoLiteCityFile);
        }
        $locationData = array();
        $rows = preg_split('#[\r\n]++#', $citiesFileContents);
        foreach ($rows as $i => $row) {
            if ($i < 3) {
                continue; // Skip column names and examples
            }
            $csv = str_getcsv(trim($row));
            if (count($csv) <= 1) {
                continue; // Skip empty lines
            }
            $locationData[] = $csv;
        }
        return $locationData;
    }

    /**
     * Download mixed FIPS 10-4 / ISO-3166-2 / proprietary region listing from MaxMind
     *
     * @return array List of array($countryCode, $regionCode, $regionName)
     * @codeCoverageIgnore
     */
    protected function _getRegionData() {
        $this->_streamOutput->writeln('Downloading new region listing…');
        $regionsPath = CM_Bootloader::getInstance()->getDirTmp() . 'region.csv';
        $regionsFile = new CM_File($regionsPath);
        $regionsContents = $this->_download($regionsFile, self::REGION_URL);

        $this->_streamOutput->writeln('Reading new region listing…');
        $regionData = array();
        $rows = preg_split('#[\r\n]++#', $regionsContents);
        foreach ($rows as $row) {
            $csv = str_getcsv(trim($row));
            if (count($csv) <= 1) {
                continue; // Skip empty lines
            }
            $regionData[] = $csv;
        }
        return $regionData;
    }

    /**
     * Returns an old version of MaxMind's region listing, containing entries for
     * regions without FIPS 10-4 and ISO-3166-2 codes, which are missing in newer versions.
     *
     * @return array $countryCode => $regionCode => $regionName
     * @codeCoverageIgnore
     */
    protected function _getRegionListLegacy() {
        return include __DIR__ . '/MaxMind/region_codes_legacy.php';
    }

    protected function _readCountryListOld() {
        $this->_streamOutput->writeln('Reading old country listing…');
        $this->_countryListOld = array();
        $this->_countryIdList = array();
        $result = CM_Db_Db::exec('SELECT `id`, `abbreviation`, `name` FROM `cm_model_location_country`');
        while (false !== ($row = $result->fetch())) {
            list($countryId, $countryCode, $countryName) = array_values($row);
            $this->_countryListOld[$countryCode] = $countryName;
            $this->_countryIdList[$countryCode] = $countryId;
        }
    }

    protected function _readRegionListOld() {
        $this->_streamOutput->writeln('Reading old region listing…');
        $this->_regionListByCountryOld = array();
        $result = CM_Db_Db::exec('SELECT `state`.`id`, `country`.`abbreviation` AS `countryCode`, `state`.`_maxmind`, `state`.`abbreviation`, `state`.`name` FROM `cm_model_location_state` `state` LEFT JOIN `cm_model_location_country` `country` ON `country`.`id` = `state`.`countryId`');
        while (false !== ($row = $result->fetch())) {
            list($regionId, $countryCode, $maxMindRegion, $regionAbbreviation, $regionName) = array_values($row);
            $regionCode = $this->_getRegionCode($regionAbbreviation, $maxMindRegion, $countryCode, $regionId, $regionName);
            $this->_regionListByCountryOld[$countryCode][$regionCode] = $regionName;
            $this->_regionIdListByCountry[$countryCode][$regionCode] = $regionId;
        }
    }

    /**
     * @throws CM_Exception
     */
    protected function _readLocationTreeOld() {
        $this->_streamOutput->writeln('Reading old location tree…');
        $this->_locationTreeOld = array();
        $result = CM_Db_Db::exec('
			SELECT
				`city`.`id` AS `cityId`,
				`city`.`_maxmind` AS `maxMind`,
				`city`.`name` AS `cityName`,
				`city`.`lat` AS `lat`,
				`city`.`lon` AS `lon`,
				`state`.`id` AS `regionId`,
				`state`.`_maxmind` AS `maxMindRegion`,
				`state`.`abbreviation` AS `regionAbbreviation`,
				`state`.`name` AS `regionName`,
				`country`.`abbreviation` AS `countryCode`,
				`country`.`name` AS `countryName`
			FROM `cm_model_location_city` `city`
			LEFT JOIN `cm_model_location_state` `state` ON `state`.`id` = `city`.`stateId`
			LEFT JOIN `cm_model_location_country` `country` ON `country`.`id` = `city`.`countryId`');
        while (false !== ($row = $result->fetch())) {
            list($cityId, $cityCode, $cityName, $latitude, $longitude, $regionId, $maxMindRegion, $regionAbbreviation, $regionName, $countryCode, $countryName) = array_values($row);
            if (null === $cityCode) {
                throw new CM_Exception('City `' . $cityName . '` (' . $cityId . ') has no MaxMind code');
            }
            if (null === $regionId) {
                $regionCode = null;
            } else {
                $regionCode = $this->_getRegionCode($regionAbbreviation, $maxMindRegion, $countryCode, $regionId, $regionName);
            }
            if (isset($this->_locationTreeOld[$countryCode]['regions'][$regionCode]['cities'][$cityName]['location'])) {
                $region = isset($regionName) ? $regionName . ', ' . $countryName : $countryName;
                throw new CM_Exception('City `' . $cityName . '` (' . $cityCode . ') found twice in ' . $region);
            }
            $this->_locationTreeOld[$countryCode]['regions'][$regionCode]['cities'][$cityName]['location'] = array(
                'name'      => (string) $cityName,
                'latitude'  => (float) $latitude,
                'longitude' => (float) $longitude,
                'maxMind'   => (int) $cityCode,
            );
            $this->_cityListByRegionOld[$countryCode][$regionCode][$cityCode] = (string) $cityName;
            $this->_cityIdList[$cityCode] = $cityId;
        }
        $result = CM_Db_Db::exec('
			SELECT
				`zip`.`id` AS `zipCodeId`,
				`zip`.`name` AS `zipCode`,
				`zip`.`cityId` AS `cityId`,
				`zip`.`lat` AS `lat`,
				`zip`.`lon` AS `lon`,
				`city`.`name` AS `cityName`,
				`state`.`id` AS `regionId`,
				`state`.`_maxmind` AS `maxMindRegion`,
				`state`.`abbreviation` AS `regionAbbreviation`,
				`state`.`name` AS `regionName`,
				`country`.`abbreviation` AS `countryCode`
			FROM `cm_model_location_zip` `zip`
			LEFT JOIN `cm_model_location_city` `city` ON `city`.`id` = `zip`.`cityId`
			LEFT JOIN `cm_model_location_state` `state` ON `state`.`id` = `city`.`stateId`
			LEFT JOIN `cm_model_location_country` `country` ON `country`.`id` = `city`.`countryId`');
        while (false !== ($row = $result->fetch())) {
            list($zipCodeId, $zipCode, $cityId, $latitude, $longitude, $cityName, $regionId, $maxMindRegion, $regionAbbreviation, $regionName, $countryCode) = array_values($row);
            if (null === $cityId) {
                throw new CM_Exception('Zip code `' . $zipCode . '` is not associated with any city');
            }
            if (null === $cityName) {
                throw new CM_Exception('Zip code `' . $zipCode . '` is associated with a non existent city (' . $cityId . ')');
            }
            if (null === $regionId) {
                $regionCode = null;
            } else {
                $regionCode = $this->_getRegionCode($regionAbbreviation, $maxMindRegion, $countryCode, $regionId, $regionName);
            }
            if (isset($this->_locationTreeOld[$countryCode]['regions'][$regionCode]['cities'][$cityName]['zipCodes'][$zipCode])) {
                $city = strlen($cityName) ? $cityName . '(' . $cityId . ')' : 'city ' . $cityId;
                throw new CM_Exception('Zip code `' . $zipCode . '` found twice in ' . $city);
            }
            $this->_locationTreeOld[$countryCode]['regions'][$regionCode]['cities'][$cityName]['zipCodes'][$zipCode] = array(
                'name'      => (string) $zipCode,
                'latitude'  => (float) $latitude,
                'longitude' => (float) $longitude,
                'id'        => (int) $zipCodeId,
            );
        }
    }

    protected function _updateCountryList() {
        $this->_countryList = array();
        $countryData = $this->_getCountryData();
        foreach ($countryData as $row) {
            list($countryName, $countryCode) = $row;
            $this->_countryList[$countryCode] = $this->_normalizeCountryName($countryName);
        }
    }

    protected function _updateRegionList() {
        $this->_regionListByCountry = array();
        $regionData = $this->_getRegionData();
        foreach ($regionData as $row) {
            list($countryCode, $regionCode, $regionName) = $row;
            $this->_regionListByCountry[$countryCode][$regionCode] = $this->_normalizeRegionName($regionName);
        }
    }

    protected function _updateLocationTree() {
        $locationData = $this->_getLocationData();
        $regionListByCountryLegacy = $this->_getRegionListLegacy();
        $this->_locationTree = array();
        $this->_countryCodeListByMaxMind = array();
        $infoListWarning = array();
        $count = count($locationData);
        $item = 0;
        foreach ($locationData as $row) {
            list($maxMind, $countryCode, $regionCode, $cityName, $zipCode, $latitude, $longitude) = $row;
            $maxMind = (int) $maxMind;
            $latitude = (float) $latitude;
            $longitude = (float) $longitude;
            if (strlen($regionCode) && !isset($this->_regionListByCountry[$countryCode][$regionCode])) {
                if (isset($this->_regionListByCountryOld[$countryCode][$regionCode])) { // Keep missing regions
                    $this->_regionListByCountry[$countryCode][$regionCode] = $this->_regionListByCountryOld[$countryCode][$regionCode];
                } elseif (isset($regionListByCountryLegacy[$countryCode][$regionCode])) { // Use legacy data for missing regions
                    $this->_regionListByCountry[$countryCode][$regionCode] = $regionListByCountryLegacy[$countryCode][$regionCode];
                }
            }
            if (strlen($zipCode)) { // ZIP code record
                if (!isset($this->_regionListByCountry[$countryCode][$regionCode])) {
                    $regionCode = null;
                }
                if (!isset($this->_locationTree[$countryCode]['regions'][$regionCode]['cities'][$cityName]['zipCodes'][$zipCode])) {
                    $this->_locationTree[$countryCode]['regions'][$regionCode]['cities'][$cityName]['zipCodes'][$zipCode] = array(
                        'name'      => $zipCode,
                        'latitude'  => $latitude,
                        'longitude' => $longitude,
                        'maxMind'   => $maxMind,
                    );
                }
                // Generate city record from zip code when missing
                if (
                    !isset($this->_locationTree[$countryCode]['regions'][$regionCode]['cities'][$cityName]['location'])
                    || !empty($this->_locationTree[$countryCode]['regions'][$regionCode]['cities'][$cityName]['location']['fromZipCode'])
                ) {
                    $name = $this->_normalizeCityName($cityName);
                    // Keep old city record if possible
                    if (
                        !isset($this->_locationTree[$countryCode]['regions'][$regionCode]['cities'][$cityName]['location'])
                        || isset($this->_cityListByRegionOld[$countryCode][$regionCode][$maxMind])
                    ) {
                        if (strlen($name)) {
                            $this->_locationTree[$countryCode]['regions'][$regionCode]['cities'][$cityName]['location'] = array(
                                'name'        => $name,
                                'latitude'    => $latitude,
                                'longitude'   => $longitude,
                                'maxMind'     => $maxMind,
                                'fromZipCode' => true,
                            );
                        }
                    }
                }
            } elseif (strlen($cityName)) { // City record
                if (!isset($this->_regionListByCountry[$countryCode][$regionCode])) {
                    $regionCode = null;
                }
                // a) Overwrite record created from a zip code
                // b) Keep old city record if possible
                if (
                    !isset($this->_locationTree[$countryCode]['regions'][$regionCode]['cities'][$cityName]['location'])
                    || !empty($this->_locationTree[$countryCode]['regions'][$regionCode]['cities'][$cityName]['location']['fromZipCode'])
                    || isset($this->_cityListByRegionOld[$countryCode][$regionCode][$maxMind])
                ) {
                    $name = $this->_normalizeCityName($cityName);
                    if (strlen($name)) {
                        $this->_locationTree[$countryCode]['regions'][$regionCode]['cities'][$cityName]['location'] = array(
                            'name'      => $name,
                            'latitude'  => $latitude,
                            'longitude' => $longitude,
                            'maxMind'   => $maxMind,
                        );
                    }
                }
            } elseif (strlen($regionCode)) { // Region record
                if (!isset($this->_locationTree[$countryCode]['regions'][$regionCode]['location'])) {
                    if (!isset($this->_regionListByCountry[$countryCode][$regionCode])) {
                        if (isset($this->_countryList[$countryCode])) {
                            $countryName = $this->_countryList[$countryCode];
                        } else {
                            $countryName = $countryCode;
                        }
                        $infoListWarning['Ignoring unknown regions'][$countryName][] = $regionCode . ' (' . implode(', ', $row) . ')';
                    } else {
                        $name = $this->_regionListByCountry[$countryCode][$regionCode];
                        if (strlen($name)) {
                            $this->_locationTree[$countryCode]['regions'][$regionCode]['location'] = array(
                                'name'      => $name,
                                'latitude'  => $latitude,
                                'longitude' => $longitude,
                                'maxMind'   => $maxMind,
                            );
                        }
                    }
                }
            } elseif (strlen($countryCode)) { // Country record
                if (!isset($this->_locationTree[$countryCode]['location'])) {
                    if (in_array($countryCode, array('A1', 'A2', 'AP', 'EU'), true)) {
                        $infoListWarning['Ignoring proprietary MaxMind country codes'][] = $countryCode;
                    } elseif (!isset($this->_countryList[$countryCode])) {
                        $infoListWarning['Ignoring unknown countries'][] = $countryCode . ' (' . implode(', ', $row) . ')';
                    } else {
                        $name = $this->_countryList[$countryCode];
                        if (strlen($name)) {
                            $this->_locationTree[$countryCode]['location'] = array(
                                'name'      => $name,
                                'latitude'  => $latitude,
                                'longitude' => $longitude,
                                'maxMind'   => $maxMind,
                            );
                            $this->_countryCodeListByMaxMind[$maxMind] = $countryCode;
                        }
                    }
                }
            }
            $this->_printProgressCounter(++$item, $count);
        }

        // Create city name lookup table
        foreach ($this->_locationTree as $countryCode => $countryData) {
            if (empty($countryData['regions'])) {
                continue;
            }
            foreach ($countryData['regions'] as $regionCode => $regionData) {
                if (empty($regionData['cities'])) {
                    continue;
                }
                foreach ($regionData['cities'] as $cityName => $cityData) {
                    if (isset($cityData['location'])) {
                        $cityCode = $cityData['location']['maxMind'];
                        $this->_cityListByRegion[$countryCode][$regionCode][$cityCode] = $cityName;
                    }
                }
            }
        }

        $this->_printInfoList($infoListWarning, '!');
    }

    protected function _updateIpBlocks() {
        $ipData = $this->_getIpData();
        $ipBlockList = array();
        $this->_ipBlockListByLocation = array();
        $infoListWarning = array();
        $count = count($ipData);
        $item = 0;
        foreach ($ipData as $row) {
            list($ipStart, $ipEnd, $maxMind) = $row;
            $ipStart = (int) $ipStart;
            $ipEnd = (int) $ipEnd;
            $maxMind = (int) $maxMind;
            if (isset($ipBlockList[$ipStart])) {
                $infoListWarning['Overlapping IP blocks'][] = "$ipStart-{$ipBlockList[$ipStart]} and $ipStart-$ipEnd";
            }
            $ipBlockList[$ipStart] = $ipEnd;
            $level = null;
            $id = null;
            if (isset($this->_zipCodeIdListByMaxMind[$maxMind])) {
                $level = CM_Model_Location::LEVEL_ZIP;
                $id = $this->_zipCodeIdListByMaxMind[$maxMind];
            } elseif (isset($this->_cityIdList[$maxMind])) {
                $level = CM_Model_Location::LEVEL_CITY;
                $id = $this->_cityIdList[$maxMind];
            } elseif (isset($this->_regionIdListByMaxMind[$maxMind])) {
                $level = CM_Model_Location::LEVEL_STATE;
                $id = $this->_regionIdListByMaxMind[$maxMind];
            } elseif (isset($this->_countryCodeListByMaxMind[$maxMind])) {
                $level = CM_Model_Location::LEVEL_COUNTRY;
                $countryCode = $this->_countryCodeListByMaxMind[$maxMind];
                if (isset($this->_countryIdList[$countryCode])) {
                    $id = $this->_countryIdList[$countryCode];
                }
            }
            if ($level && $id) {
                $this->_ipBlockListByLocation[$level][$id][$ipEnd] = $ipStart;
            } else {
                $infoListWarning['Ignoring unknown locations'][] = $maxMind;
            }
            $this->_printProgressCounter(++$item, $count);
        }
        $this->_streamOutput->writeln('Checking overlapping of IP blocks…');
        ksort($ipBlockList);
        $ipStartPrevious = $ipEndPrevious = 0;
        $count = count($ipBlockList);
        $item = 0;
        foreach ($ipBlockList as $ipStart => $ipEnd) {
            if ($ipStart <= $ipEndPrevious) {
                $infoListWarning['Overlapping IP blocks'][] = "$ipStartPrevious-$ipEndPrevious and $ipStart-$ipEnd";
            }
            $ipStartPrevious = $ipStart;
            $ipEndPrevious = $ipEnd;
            $this->_printProgressCounter(++$item, $count);
        }

        $this->_printInfoList($infoListWarning, '!');
    }

    protected function _upgradeCountryList() {
        $this->_streamOutput->writeln('Updating countries database…');
        $count = $this->_count(array($this->_countryListRenamed, $this->_countryListAdded), 2);
        $item = 0;
        foreach ($this->_countryListRenamed as $countryCode => $countryNames) {
            $countryName = $countryNames['name'];
            CM_Db_Db::update('cm_model_location_country', array('name' => $countryName), array('abbreviation' => $countryCode));
            $this->_printProgressCounter(++$item, $count);
        }
        foreach ($this->_countryListAdded as $countryCode => $countryName) {
            $country = CM_Model_Location::createCountry($countryName, $countryCode);
            $countryId = $country->getId();
            $this->_countryIdList[$countryCode] = $countryId;
            $this->_printProgressCounter(++$item, $count);
        }
    }

    protected function _upgradeRegionList() {
        $this->_streamOutput->writeln('Updating regions database…');
        $count = $this->_count(array($this->_regionListByCountryRenamed, $this->_regionListByCountryUpdatedCode,
            $this->_regionListByCountryAdded, $this->_regionListByCountryRemovedCodeInUse), 3);
        $item = 0;
        foreach ($this->_regionListByCountryRenamed as $countryCode => $regionListRenamed) {
            foreach ($regionListRenamed as $regionCode => $regionNames) {
                $regionName = $regionNames['name'];
                $maxMindRegion = $countryCode . $regionCode;
                if (!CM_Db_Db::update('cm_model_location_state', array('name' => $regionName), array('_maxmind' => $maxMindRegion))) {
                    // For the USA, where the old numeric region codes in _maxmind have been removed from MaxMind's newer region databases
                    $countryId = $this->_countryIdList[$countryCode];
                    CM_Db_Db::update('cm_model_location_state', array('name' => $regionName), array(
                        'countryId'    => $countryId,
                        'abbreviation' => $regionCode
                    ));
                }
                $this->_printProgressCounter(++$item, $count);
            }
        }
        foreach ($this->_regionListByCountryUpdatedCode as $countryCode => $regionListUpdatedCode) {
            foreach ($regionListUpdatedCode as $regionCode) {
                $regionId = $this->_regionIdListByCountry[$countryCode][$regionCode];
                $maxMindRegion = $countryCode . $regionCode;
                $abbreviationRegion = ('US' === $countryCode) ? $regionCode : null;
                CM_Db_Db::update('cm_model_location_state',
                    array('_maxmind' => $maxMindRegion, 'abbreviation' => $abbreviationRegion),
                    array('id' => $regionId));
                $this->_printProgressCounter(++$item, $count);
            }
        }
        foreach ($this->_regionListByCountryAdded as $countryCode => $regionListAdded) {
            $countryId = $this->_countryIdList[$countryCode];
            $country = new CM_Model_Location(CM_Model_Location::LEVEL_COUNTRY, $countryId);
            foreach ($regionListAdded as $regionCode => $regionName) {
                $abbreviationRegion = ('US' === $countryCode) ? $regionCode : null;
                $maxMindRegion = $countryCode . $regionCode;
                $region = CM_Model_Location::createState($country, $regionName, $abbreviationRegion, $maxMindRegion);
                $regionId = $region->getId();
                $this->_regionIdListByCountry[$countryCode][$regionCode] = $regionId;
                if (isset($this->_locationTree[$countryCode]['regions'][$regionCode]['location']['maxMind'])) {
                    $maxMind = $this->_locationTree[$countryCode]['regions'][$regionCode]['location']['maxMind'];
                    $this->_regionIdListByMaxMind[$maxMind] = $regionId;
                }
                $this->_printProgressCounter(++$item, $count);
            }
        }
        foreach ($this->_regionListByCountryRemovedCodeInUse as $countryCode => $regionListRemovedCodeInUse) {
            foreach ($regionListRemovedCodeInUse as $regionIdOld => $regionCode) {
                CM_Db_Db::delete('cm_model_location_state', array('id' => $regionIdOld));
                $regionId = $this->_regionIdListByCountry[$countryCode][$regionCode];
                CM_Db_Db::update('cm_model_location_city', array('stateId' => $regionId), array('stateId' => $regionIdOld));
                $this->_printProgressCounter(++$item, $count);
            }
        }
    }

    protected function _upgradeCityList() {
        $this->_streamOutput->writeln('Updating cities database…');
        $count = $this->_count(array($this->_cityListByRegionRenamed, $this->_cityListByRegionUpdatedCode,
                $this->_cityListByRegionAdded), 4) + $this->_count($this->_cityListUpdatedRegion, 2);
        $item = 0;
        foreach ($this->_cityListByRegionRenamed as $cityListByRegionRenamed) {
            foreach ($cityListByRegionRenamed as $cityListRenamed) {
                foreach ($cityListRenamed as $cityCode => $cityNames) {
                    $cityName = $cityNames['name'];
                    CM_Db_Db::update('cm_model_location_city', array('name' => $cityName), array('_maxmind' => $cityCode));
                    $this->_printProgressCounter(++$item, $count);
                }
            }
        }
        foreach ($this->_cityListByRegionUpdatedCode as $cityListByRegionUpdatedCode) {
            foreach ($cityListByRegionUpdatedCode as $cityListUpdatedCode) {
                foreach ($cityListUpdatedCode as $cityCode) {
                    $cityId = $this->_cityIdList[$cityCode];
                    CM_Db_Db::update('cm_model_location_city', array('_maxmind' => $cityCode), array('id' => $cityId));
                    $this->_printProgressCounter(++$item, $count);
                }
            }
        }
        foreach ($this->_cityListUpdatedRegion as $countryCode => $cityListUpdatedRegion) {
            foreach ($cityListUpdatedRegion as $cityCode => $regionCodes) {
                $cityId = $this->_cityIdList[$cityCode];
                $regionCode = $regionCodes['regionCode'];
                $regionName = $this->_getRegionName($countryCode, $regionCode);
                $cityName = $this->_cityListByRegion[$countryCode][$regionCode][$cityCode];
                if ($regionName === 'Unknown region') {
                    CM_Db_Db::update('cm_model_location_city', array('stateId' => null, 'name' => $cityName), array('id' => $cityId));
                } else {
                    $regionId = $this->_regionIdListByCountry[$countryCode][$regionCode];
                    CM_Db_Db::update('cm_model_location_city', array('stateId' => $regionId, 'name' => $cityName), array('id' => $cityId));
                }
                $this->_printProgressCounter(++$item, $count);
            }
        }
        foreach ($this->_cityListByRegionAdded as $countryCode => $cityListByRegionAdded) {
            foreach ($cityListByRegionAdded as $regionCode => $cityListAdded) {
                if (isset($this->_regionIdListByCountry[$countryCode][$regionCode])) {
                    $regionId = $this->_regionIdListByCountry[$countryCode][$regionCode];
                    $parentLocation = new CM_Model_Location(CM_Model_Location::LEVEL_STATE, $regionId);
                } else {
                    $countryId = $this->_countryIdList[$countryCode];
                    $parentLocation = new CM_Model_Location(CM_Model_Location::LEVEL_COUNTRY, $countryId);
                }
                foreach ($cityListAdded as $cityCode => $cityName) {
                    $cityData = $this->_locationTree[$countryCode]['regions'][$regionCode]['cities'][$cityName]['location'];
                    $city = CM_Model_Location::createCity($parentLocation, $cityName, $cityData['latitude'], $cityData['longitude'], $cityData['maxMind']);
                    $cityId = $city->getId();
                    $this->_cityIdList[$cityCode] = $cityId;
                    $this->_printProgressCounter(++$item, $count);
                }
            }
        }
    }

    protected function _upgradeZipCodeList() {
        $this->_streamOutput->writeln('Updating zip codes database…');
        $count = $this->_count($this->_zipCodeListByCityAdded, 4);
        $item = 0;
        foreach ($this->_zipCodeListByCityAdded as $countryCode => $zipCodeListByRegionAdded) {
            foreach ($zipCodeListByRegionAdded as $regionCode => $zipCodeListByCityAdded) {
                foreach ($zipCodeListByCityAdded as $cityCode => $zipCodeListAdded) {
                    $cityId = $this->_cityIdList[$cityCode];
                    $city = new CM_Model_Location(CM_Model_Location::LEVEL_CITY, $cityId);
                    foreach ($zipCodeListAdded as $zipCode => $zipCodeData) {
                        $zip = CM_Model_Location::createZip($city, $zipCodeData['name'], $zipCodeData['latitude'], $zipCodeData['longitude']);
                        $zipCodeId = $zip->getId();
                        $maxMind = $zipCodeData['maxMind'];
                        $this->_zipCodeIdListByMaxMind[$maxMind] = $zipCodeId;
                        $this->_printProgressCounter(++$item, $count);
                    }
                }
            }
        }
    }

    protected function _upgradeIpBlocks() {
        if ($this->_withoutIpBlocks) {
            return;
        }
        $this->_updateIpBlocks();
        $this->_streamOutput->writeln('Updating IP blocks database…');
        $count = $this->_count($this->_ipBlockListByLocation, 3);
        $item = 0;
        CM_Db_Db::truncate('cm_model_location_ip');
        foreach ($this->_ipBlockListByLocation as $level => $ipBlockListByLocation) {
            foreach ($ipBlockListByLocation as $id => $ipBlockList) {
                foreach ($ipBlockList as $ipEnd => $ipStart) {
                    CM_Db_Db::insertIgnore('cm_model_location_ip', array('id' => $id, 'level' => $level, 'ipStart' => $ipStart, 'ipEnd' => $ipEnd));
                    $this->_printProgressCounter(++$item, $count);
                }
            }
        }
    }

    /**
     * @param array    $array
     * @param int|null $depth
     * @return int
     */
    private function _count($array, $depth = null) {
        if (null === $depth) {
            $depth = 1;
        }
        if ($depth > 1) {
            $count = 0;
            foreach ($array as $arrayNested) {
                $count += $this->_count($arrayNested, $depth - 1);
            }
        } else {
            $count = count($array);
        }
        return $count;
    }

    /**
     * @param int $color
     * @return string
     * @codeCoverageIgnore
     */
    private function _getEscapeSequenceHighlighted($color) {
        static $escapeSequence = array();
        if (!isset($escapeSequence[$color])) {
            $escapeSequence[$color] = system('tput setab ' . $color) . system('tput setaf 15');
            system('tput sgr0');
        }
        return $escapeSequence[$color];
    }

    /**
     * @return string
     * @codeCoverageIgnore
     */
    private function _getEscapeSequenceNormal() {
        static $escapeSequence = null;
        if (null === $escapeSequence) {
            $escapeSequence = system('tput sgr0');
        }
        return $escapeSequence;
    }

    /**
     * @param string $regionAbbreviation
     * @param string $maxMindRegion
     * @param string $countryCode
     * @param string $regionId
     * @param string $regionName
     * @return string
     * @throws CM_Exception
     */
    private function _getRegionCode($regionAbbreviation, $maxMindRegion, $countryCode, $regionId, $regionName) {
        $regionCode = null;
        if (strlen($regionAbbreviation)) {
            $regionCode = $regionAbbreviation;
        } elseif (strlen($maxMindRegion)) {
            if (!strlen($countryCode)) {
                throw new CM_Exception('The region `' . $regionName . '` (' . $regionId . ') has no country code');
            }
            if (0 !== strpos($maxMindRegion, $countryCode)) {
                throw new CM_Exception('The region `' . $regionName . '` (' . $regionId . ') has an invalid region code `' . $maxMindRegion .
                    '`, which should start with the country code `' . $countryCode . '`');
            }
            $regionCode = substr($maxMindRegion, strlen($countryCode));
        } else {
            throw new CM_Exception('The region `' . $regionName . '` (' . $regionId . ') has no region code');
        }
        return $regionCode;
    }

    /**
     * @param string     $countryCode
     * @param string|int $regionCode
     * @param bool|null  $explicit
     * @return string
     */
    private function _getRegionName($countryCode, $regionCode, $explicit = null) {
        if (isset($this->_regionListByCountry[$countryCode][$regionCode])) {
            $regionName = $this->_regionListByCountry[$countryCode][$regionCode];
            if ($explicit) {
                $regionName .= ' (' . $regionCode . ')';
            }
            return $regionName;
        } elseif (strlen($regionCode)) {
            return 'Region ' . $regionCode;
        } else {
            return 'Unknown region';
        }
    }

    /**
     * @param string     $countryCode
     * @param string|int $regionCodeOld
     * @param bool|null  $explicit
     * @return string
     */
    private function _getRegionNameOld($countryCode, $regionCodeOld, $explicit = null) {
        if (isset($this->_regionListByCountryOld[$countryCode][$regionCodeOld])) {
            $regionNameOld = $this->_regionListByCountryOld[$countryCode][$regionCodeOld];
            if ($explicit) {
                $regionNameOld .= ' (' . $regionCodeOld . ')';
            }
            return $regionNameOld;
        } elseif (strlen($regionCodeOld)) {
            return 'Region ' . $regionCodeOld;
        } else {
            return 'Unknown region';
        }
    }

    /**
     * @param string $fullName
     * @return string
     */
    private function _normalizeCountryName($fullName) {
        switch ($fullName) {
            // Avoid ambiguity
            case 'Korea, Democratic People\'s Republic of':
                $name = 'North Korea';
                break;
            case 'Virgin Islands, British':
                $name = 'British Virgin Islands';
                break;
            default:
                $name = preg_replace('#\s*\([^)]*\)#', '', $fullName); // Remove details, like in "Saint Martin (French part)"
                $name = preg_replace('#, [^&,][^,]*\z#', '', $name); // Remove suffix, like in "Congo, The Democratic Republic of the"
        }
        return trim($name);
    }

    /**
     * @param string $fullName
     * @return string
     */
    private function _normalizeRegionName($fullName) {
        $name = preg_replace('#, [^&,][^,]*\z#', '', $fullName); // Remove suffix, like in "London, City of". Considering the special case of "Armed Forces Europe, Middle East, & Canada".
        return trim($name);
    }

    /**
     * @param string $fullName
     * @return string
     */
    private function _normalizeCityName($fullName) {
        $name = trim($fullName);
        if (preg_match('#\A\(\( .* \)\)\z#', $name) || preg_match('#\(\d++\)\z#', $name)) {
            return ''; // Remove non-existent cities, like "(( Mantjurgiai ))" and "Erhchiehtsun (1)"
        }
        return $name;
    }

    /**
     * @param array       $infoList
     * @param string|null $symbol
     * @codeCoverageIgnore
     */
    private function _printInfoList($infoList, $symbol = null) {
        if (!$this->_verbose) {
            return;
        }
        if (empty($infoList)) {
            return;
        }
        if (null === $symbol) {
            $symbol = '•';
        }
        switch ($symbol) {
            case '-':
                $color = 1;
                break;
            case '+':
                $color = 2;
                break;
            case '!':
                $color = 3;
                break;
            default:
                $color = 4;
        }
        ksort($infoList);
        foreach ($infoList as $info => $items) {
            $this->_streamOutput->writeln($info . ':');
            foreach ($items as $key => $item) {
                if (is_array($item)) {
                    if (count($item) <= 10) {
                        $items[$key] = 'In ' . $key . ': ' . implode(', ', $item);
                    } else {
                        $items[$key] = 'In ' . $this->_getEscapeSequenceHighlighted($color) . $key . $this->_getEscapeSequenceNormal() . ': ' .
                            implode(', ', array_slice($item, 0, 10)) . ', … (' . (count($item) - 10) . ' more)';
                    }
                }
            }
            asort($items);
            foreach ($items as $item) {
                $this->_streamOutput->writeln(' ' . $symbol . ' ' . $item);
            }
            $this->_printSeparator();
        }
    }

    /**
     * @param int $item
     * @param int $count
     * @codeCoverageIgnore
     */
    private function _printProgressCounter($item, $count) {
        $item = (int) $item;
        $count = (int) $count;
        $this->_streamError->write("\r$item/$count (" . round($item / $count * 100) . "%)");
        if ($item == $count) {
            $this->_streamError->writeln('');
        }
    }

    /**
     * @codeCoverageIgnore
     */
    private function _printSeparator() {
        $this->_streamOutput->writeln('');
        $this->_streamOutput->writeln(str_repeat(' *', 10));
        $this->_streamOutput->writeln('');
    }

    /**
     * @param CM_File $geoIpZipFile
     * @return string
     * @throws CM_Exception_Invalid
     * @codeCoverageIgnore
     */
    private function _readBlocksData($geoIpZipFile) {
        $zip = zip_open($geoIpZipFile->getPath());
        if (!is_resource($zip)) {
            throw new CM_Exception_Invalid('Could not read zip file `' . $geoIpZipFile->getPath() . '`');
        }
        do {
            $entry = zip_read($zip);
        } while ($entry && !preg_match('#Blocks\\.csv\\z#', zip_entry_name($entry)));
        if (!$entry) {
            throw new CM_Exception_Invalid('Could not find blocks file in `' . $geoIpZipFile->getPath() . '`');
        }
        zip_entry_open($zip, $entry, 'r');
        $contents = zip_entry_read($entry, zip_entry_filesize($entry));
        zip_close($zip);
        return $contents;
    }

    /**
     * @param CM_File $geoIpZipFile
     * @return string
     * @throws CM_Exception_Invalid
     * @codeCoverageIgnore
     */
    private function _readLocationData($geoIpZipFile) {
        $zip = zip_open($geoIpZipFile->getPath());
        if (!is_resource($zip)) {
            throw new CM_Exception_Invalid('Could not read zip file `' . $geoIpZipFile->getPath() . '`');
        }
        do {
            $entry = zip_read($zip);
        } while ($entry && !preg_match('#Location\\.csv\\z#', zip_entry_name($entry)));
        if (!$entry) {
            throw new CM_Exception_Invalid('Could not find location file in `' . $geoIpZipFile->getPath() . '`');
        }
        zip_entry_open($zip, $entry, 'r');
        $contents = zip_entry_read($entry, zip_entry_filesize($entry));
        zip_close($zip);
        $contents = iconv('ISO-8859-1', 'UTF-8', $contents);
        return $contents;
    }

    /**
     * @param CM_File|null $geoIpFile
     * @throws CM_Exception_Invalid
     * @codeCoverageIgnore
     */
    private function _setGeoIpFile(CM_File $geoIpFile = null) {
        if (null !== $geoIpFile) {
            if (!$geoIpFile->exists()) {
                throw new CM_Exception_Invalid('GeoIP file not found: ' . $geoIpFile->getPath());
            }
        }
        $this->_geoIpFile = $geoIpFile;
    }
}
