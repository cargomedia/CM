<?php

class CM_Http_Response_Resource_Css_LibraryTest extends CMTest_TestCase {

    public function testProcess() {
        $site = CM_Site_Abstract::factory();
        $render = new CM_Frontend_Render(new CM_Frontend_Environment());
        $request = new CM_Http_Request_Get($render->getUrlResource('library-css', 'all.css'));
        $response = CM_Http_Response_Resource_Css_Library::createFromRequest($request, $site, $this->getServiceManager());
        $response->process();

        $this->assertContains('Cache-Control: max-age=31536000', $response->getHeaders());
        $this->assertContains('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 31536000), $response->getHeaders());
        $this->assertContains('body{', $response->getContent());
    }
}
