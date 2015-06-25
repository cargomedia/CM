<?php

class CM_Frontend_JavascriptContainer {

    /** @var string[] */
    protected $_operations = array();

    /**
     * @param string $code
     */
    public function append($code) {
        $code = (string) $code;
        array_push($this->_operations, $code);
    }

    /**
     * @param string $code
     */
    public function prepend($code) {
        $code = (string) $code;
        array_unshift($this->_operations, $code);
    }

    /**
     * @param string|null $scope
     * @return string
     */
    public function compile($scope = null) {
        $code = '';
        if (!$this->_operations) {
            return $code;
        }
        $operations = array_filter($this->_operations);
        if (null !== $scope) {
            $operations = array_map(function ($operation) {
                return '  ' . $operation;
            }, $operations);
        }
        $code = implode("\n", $operations);
        if (null !== $scope) {
            $code = "(function () { \n{$code}}).call({$scope});";
        }
        return $code;
    }

    public function clear() {
        $this->_operations = array();
    }
}
