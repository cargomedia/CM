<?php

interface CM_Service_Tracking_ClientInterface {

    /**
     * @param CM_Frontend_Environment $environment
     * @return string
     */
    public function getHtml(CM_Frontend_Environment $environment);

    /**
     * @return string
     */
    public function getJs();

    /**
     * @param CM_Action_Abstract $action
     */
    public function trackAction(CM_Action_Abstract $action);

    /**
     * @param CM_Frontend_Environment $environment
     * @param string                  $path
     */
    public function trackPageView(CM_Frontend_Environment $environment, $path);

    /**
     * @param CM_Splittest_Fixture        $fixture
     * @param CM_Model_SplittestVariation $variation
     */
    public function trackSplittest(CM_Splittest_Fixture $fixture, CM_Model_SplittestVariation $variation);
}
