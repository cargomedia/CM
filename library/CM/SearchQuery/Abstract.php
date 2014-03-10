<?php

class CM_SearchQuery_Abstract {

    private $_queries = array();
    private $_filters = array();
    private $_sorts = array();
    private $_mode, $_filterMode;

    /**
     * @param string|null $mode       must,must_not,should
     * @param string|null $filterMode or, and, not
     */
    function __construct($mode = null, $filterMode = null) {
        if (is_null($mode)) {
            $mode = 'must';
        }
        if (is_null($filterMode)) {
            $filterMode = 'and';
        }
        $this->_mode = (string) $mode;
        $this->_filterMode = (string) $filterMode;
    }

    public function query($query) {
        if ($query instanceof CM_SearchQuery_Abstract) {
            $query = $query->getQuery();
        }
        $this->_queries[] = $query;
    }

    /**
     * @param string $field
     * @param string $value
     */
    public function queryField($field, $value) {
        $this->_queries[] = array('field' => array($field => $value));
    }

    /**
     * @param string[]    $fields
     * @param string      $value
     * @param string|null $operator  'or' / 'and'
     * @param float|null  $fuzziness 0 - 1
     */
    public function queryMatch($fields, $value, $operator = null, $fuzziness = null) {
        $query = array('multi_match' => array(
            'query'  => $value,
            'fields' => $fields,
        ));
        if (null !== $operator) {
            $query['operator'] = (string) $operator;
        }
        if (null !== $fuzziness) {
            $query['fuzziness'] = (float) $fuzziness;
        }
        $this->query($query);
    }

    /**
     * @param array $filter
     */
    protected function _filter(array $filter) {
        $this->_filters[] = $filter;
    }

    /**
     * @param array $filter
     */
    protected function _filterNot(array $filter) {
        $this->_filters[] = array('not' => array('filter' => $filter));
    }

    /**
     * @param string $field
     */
    public function filterExists($field) {
        $this->_filter(array('exists' => array('field' => (string) $field)));
    }

    /**
     * @param string $field
     * @param string $value
     */
    public function filterPrefix($field, $value) {
        $this->_filter(array('prefix' => array($field => $value)));
    }

    /**
     * @param string $field
     * @param string $value
     */
    public function filterTerm($field, $value) {
        if (is_array($value)) {
            $this->_filter(array('terms' => array($field => $value)));
        } else {
            $this->_filter(array('term' => array($field => $value)));
        }
    }

    /**
     * @param string $field
     * @param string $value
     */
    public function filterTermNot($field, $value) {
        if (is_array($value)) {
            $this->_filterNot(array('terms' => array($field => $value)));
        } else {
            $this->_filterNot(array('term' => array($field => $value)));
        }
    }

    /**
     * @param string    $field
     * @param int|null  $from
     * @param int|null  $to
     * @param bool|null $openIntervalFrom
     * @param bool|null $openIntervalTo
     */
    public function filterRange($field, $from = null, $to = null, $openIntervalFrom = null, $openIntervalTo = null) {
        $range = array();
        if ($from !== null) {
            $operand = 'gte';
            if ($openIntervalFrom) {
                $operand = 'gt';
            }
            $range[$operand] = $from;
        }
        if ($to !== null) {
            $operand = 'lte';
            if ($openIntervalTo) {
                $operand = 'lt';
            }
            $range[$operand] = $to;
        }
        if (!empty($range)) {
            $this->_filter(array('range' => array($field => $range)));
        }
    }

    /**
     * @param string $field
     */
    public function filterMissing($field) {
        $this->_filter(array('missing' => array('field' => (string) $field, 'existence' => true, 'null_value' => true)));
    }

    /**
     * @param string            $field
     * @param CM_Model_Location $location
     * @param int               $distance
     */
    public function filterGeoDistance($field, CM_Model_Location $location, $distance) {
        if (!$location->getCoordinates()) {
            return;
        }
        $distance = ($distance / 1000) . 'km';
        $this->_filter(array('geo_distance' => array($field => $location->getCoordinates(), 'distance' => $distance, 'distance_type' => 'plane',)));
    }

    /**
     * @param string            $field
     * @param CM_Model_Location $location
     */
    public function sortGeoDistance($field, CM_Model_Location $location) {
        if (!$location->getCoordinates()) {
            return;
        }
        $this->_sort(array('_geo_distance' => array($field => $location->getCoordinates())));
    }

    public function sortScore() {
        $this->_sort(array('_score' => 'desc'));
    }

    public function getQuery() {
        if (count($this->_queries) == 0) {
            $query = array('match_all' => new stdClass());
        } elseif (count($this->_queries) == 1) {
            $query = reset($this->_queries);
        } else {
            $query = array('bool' => array($this->_mode => $this->_queries));
        }
        if (!empty($this->_filters)) {
            $query = array('filtered' => array('query' => $query, 'filter' => array($this->_filterMode => $this->_filters)));
        }
        return $query;
    }

    public function getSort() {
        if (empty($this->_sorts)) {
            $this->_sortDefault();
        }
        return $this->_sorts;
    }

    /**
     * @param array $sort
     */
    protected function _sort(array $sort) {
        $sortNew = array();
        foreach ($sort as $key => $value) {
            $key = (string) $key;
            if (null === $value) {
                $value = 'desc';
            }
            $sortNew[$key] = $value;
        }
        $this->_sorts[] = $sortNew;
    }

    protected function _sortDefault() {
    }

    /**
     * @param int $timestamp
     * @return string
     */
    public static function formatDate($timestamp) {
        return date('Y-m-d', $timestamp);
    }

    /**
     * @param int $timestamp Timestamp to return as date
     * @param int $round     OPTIONAL Number of seconds the result should be rounded (floor) (default = 1)
     * @return string Date in format Y-m-d\TH:i:s\Z
     */
    public static function formatDateTime($timestamp, $round = 1) {
        $timestamp = $timestamp - ($timestamp % $round);
        return date('Y-m-d\TH:i:s\Z', $timestamp);
    }

    /**
     * @param string $term
     * @param array  $chars OPTIONAL
     * @return string
     */
    public static function escape($term, array $chars = array('\\', '+', '-', '&&', '||', '!', '(', ')', '{', '}', '[', ']', '^', '"', '~', '*', '?',
        ':')) {
        foreach ($chars as $char) {
            $term = str_replace($char, '\\' . $char, $term);
        }
        return $term;
    }
}
