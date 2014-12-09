<?php

class CM_Request_AbstractTest extends CMTest_TestCase {

    public function tearDown() {
        CMTest_TH::clearEnv();
    }

    public function testGetViewer() {
        $user = CMTest_TH::createUser();
        $uri = '/';
        $headers = array('Host' => 'example.ch', 'Connection' => 'keep-alive');
        $mock = $this->getMockForAbstractClass('CM_Request_Abstract', array($uri, $headers));
        /** @var CM_Request_Abstract $mock */
        $this->assertNull($mock->getViewer());

        $headers = array('Host' => 'example.ch', 'Connection' => 'keep-alive', 'Cookie' => 'sessionId=a1d2726e5b3801226aafd12fd62496c8');
        $mock = $this->getMockForAbstractClass('CM_Request_Abstract', array($uri, $headers));
        try {
            $mock->getViewer(true);
            $this->fail();
        } catch (CM_Exception_AuthRequired $ex) {
            $this->assertTrue(true);
        }

        $session = new CM_Session();
        $session->setUser($user);
        $headers = array('Host' => 'example.ch', 'Connection' => 'keep-alive', 'Cookie' => 'sessionId=' . $session->getId());
        unset($session);

        $mock = $this->getMockForAbstractClass('CM_Request_Abstract', array($uri, $headers));
        $this->assertEquals($user, $mock->getViewer(true));

        $user2 = CMTest_TH::createUser();
        $mock = $this->getMockForAbstractClass('CM_Request_Abstract', array($uri, $headers, null, $user2));
        $this->assertEquals($user2, $mock->getViewer(true));
    }

    public function testGetCookie() {
        $uri = '/';
        $headers = array('Host'   => 'example.ch', 'Connection' => 'keep-alive',
                         'Cookie' => ';213q;213;=foo=hello;bar=tender;  adkhfa ; asdkf===fsdaf');
        $mock = $this->getMockForAbstractClass('CM_Request_Abstract', array($uri, $headers));
        $this->assertEquals('hello', $mock->getCookie('foo'));
        $this->assertEquals('tender', $mock->getCookie('bar'));
        $this->assertNull($mock->getCookie('asdkf'));
    }

    public function testGetLanguageLoggedUser() {
        $user = CMTest_TH::createUser();
        $request = $this->_prepareRequest('/', null, $user);
        // No language at all
        $this->assertSame($request->getLanguage(), null);

        // Getting default language, user has no language
        $defaultLanguage = CMTest_TH::createLanguage();
        $this->assertEquals($request->getLanguage(), $defaultLanguage);

        // Getting user language
        $anotherUserLanguage = CMTest_TH::createLanguage();
        $user->setLanguage($anotherUserLanguage);
        $this->assertEquals($request->getLanguage(), $anotherUserLanguage);
    }

    public function testGetLanguageGuest() {
        $request = $this->_prepareRequest('/');
        // No language at all
        $this->assertSame($request->getLanguage(), null);

        // Getting default language (guest has no language)
        $defaultLanguage = CMTest_TH::createLanguage();
        $this->assertEquals($request->getLanguage(), $defaultLanguage);
    }

    public function testGetLanguageByUrl() {
        $request = $this->_prepareRequest('/de/home');
        CM_Response_Abstract::factory($request);
        $this->assertNull($request->getLanguage());

        CMTest_TH::createLanguage('en'); // default language
        $urlLanguage = CMTest_TH::createLanguage('de');
        $request = $this->_prepareRequest('/de/home');
        CM_Response_Abstract::factory($request);
        $this->assertEquals($request->getLanguage(), $urlLanguage);
    }

    public function testGetLanguageByBrowser() {
        $defaultLanguage = CMTest_TH::createLanguage('en');
        $browserLanguage = CMTest_TH::createLanguage('de');
        $this->assertEquals(CM_Model_Language::findDefault(), $defaultLanguage);
        $request = $this->_prepareRequest('/', array('Accept-Language' => 'de'));
        $this->assertEquals($request->getLanguage(), $browserLanguage);
        $request = $this->_prepareRequest('/', array('Accept-Language' => 'pl'));
        $this->assertEquals($request->getLanguage(), $defaultLanguage);
    }

    public function testFactory() {
        $this->assertInstanceOf('CM_Request_Get', CM_Request_Abstract::factory('GET', '/test'));
        $this->assertInstanceOf('CM_Request_Post', CM_Request_Abstract::factory('POST', '/test'));
    }

    public function testSetUri() {
        $language = CM_Model_Language::create('english', 'en', true);
        $uri = '/en/foo/bar?foo1=bar1';
        $headers = array('Host' => 'example.ch', 'Connection' => 'keep-alive');
        /** @var CM_Request_Abstract $mock */
        $mock = $this->getMockForAbstractClass('CM_Request_Abstract', array($uri, $headers));
        $this->assertEquals($language, $mock->popPathLanguage());
        $this->assertSame('/foo/bar', $mock->getPath());
        $this->assertSame(array('foo', 'bar'), $mock->getPathParts());
        $this->assertSame(array('foo1' => 'bar1'), $mock->getQuery());
        $this->assertEquals($language, $mock->getLanguageUrl());
        $this->assertSame($uri, $mock->getUri());

        $mock->setUri('/foo1/bar1?foo=bar');
        $this->assertSame('/foo1/bar1', $mock->getPath());
        $this->assertSame(array('foo1', 'bar1'), $mock->getPathParts());
        $this->assertSame(array('foo' => 'bar'), $mock->getQuery());
        $this->assertNull($mock->getLanguageUrl());
        $this->assertSame('/foo1/bar1?foo=bar', $mock->getUri());
    }

    public function testSetUriRelativeAndColon() {
        $uri = '/foo/bar?foo1=bar1:80';
        /** @var CM_Request_Abstract $mock */
        $mock = $this->getMockForAbstractClass('CM_Request_Abstract', array($uri));
        $this->assertSame('/foo/bar', $mock->getPath());
        $this->assertSame(array('foo1' => 'bar1:80'), $mock->getQuery());
    }

    public function testGetUri() {
        $request = $this->getMockForAbstractClass('CM_Request_Abstract', array('/foo/bar?hello=world'));
        /** @var CM_Request_Abstract $request */

        $this->assertSame('/foo/bar?hello=world', $request->getUri());

        $this->assertSame('foo', $request->popPathPart());
        $this->assertSame('/foo/bar?hello=world', $request->getUri());

        $request->setPath('/hello');
        $this->assertSame('/foo/bar?hello=world', $request->getUri());

        $request->setUri('/world');
        $this->assertSame('/world', $request->getUri());
    }

    public function testGetClientId() {
        $uri = '/en/foo/bar?foo1=bar1';
        $headers = array('Host' => 'example.ch', 'Connection' => 'keep-alive');
        /** @var CM_Request_Abstract $mock */
        $mock = $this->getMockForAbstractClass('CM_Request_Abstract', array($uri, $headers));
        $this->assertFalse($mock->hasClientId());
        $clientId = $mock->getClientId();
        $this->assertInternalType('int', $clientId);
        $this->assertTrue($mock->hasClientId());

        $uri = '/';
        $headers = array('Host' => 'example.ch', 'Connection' => 'keep-alive', 'Cookie' => ';213q;213;=clientId=WRONG;');
        /** @var CM_Request_Abstract $mock */
        $mock = $this->getMockForAbstractClass('CM_Request_Abstract', array($uri, $headers));
        $this->assertFalse($mock->hasClientId());
        $this->assertSame($clientId + 1, $mock->getClientId());
        $this->assertTrue($mock->hasClientId());
        $id = $mock->getClientId();
        $this->assertSame($id, $mock->getClientId());

        $uri = '/';
        $headers = array('Host' => 'example.ch', 'Connection' => 'keep-alive', 'Cookie' => ';213q;213;=clientId=' . $id . ';');
        /** @var CM_Request_Abstract $mock */
        $mock = $this->getMockForAbstractClass('CM_Request_Abstract', array($uri, $headers));
        $this->assertFalse($mock->hasClientId());
        $this->assertSame($id, $mock->getClientId());
        $this->assertTrue($mock->hasClientId());
    }

    public function testGetClientIdSetCookie() {
        $request = new CM_Request_Post('/foo/null/');
        $clientId = $request->getClientId();
        /** @var CM_Response_Abstract $response */
        $response = $this->getMock('CM_Response_Abstract', array('_process', 'setCookie'), array($request));
        $response->expects($this->once())->method('setCookie')->with('clientId', (string) $clientId);
        $response->process();
    }

    public function testIsBotCrawler() {
        $useragents = array(
            'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'   => true,
            'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.1.4322)' => false,
            'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)'    => true,
        );
        foreach ($useragents as $useragent => $expected) {
            $request = new CM_Request_Get('/foo', array('user-agent' => $useragent));
            $this->assertSame($expected, $request->isBotCrawler());
        }
    }

    public function testIsBotCrawlerWithoutUseragent() {
        $request = new CM_Request_Get('/foo');
        $this->assertFalse($request->isBotCrawler());
    }

    public function testGetHost() {
        $request = new CM_Request_Get('/', array('host' => 'www.example.com'));
        $this->assertSame('www.example.com', $request->getHost());
    }

    public function testGetHostWithPort() {
        $request = new CM_Request_Get('/', array('host' => 'www.example.com:80'));
        $this->assertSame('www.example.com', $request->getHost());
    }

    /**
     * @expectedException CM_Exception_Invalid
     */
    public function testGetHostWithoutHeader() {
        $request = new CM_Request_Get('/');
        $request->getHost();
    }

    public function testIsSupported() {
        $userAgentList = [
            'MSIE 6.0'                                            => false,
            'MSIE 9.0'                                            => false,
            'MSIE 9.1'                                            => false,
            'MSIE 10.0'                                           => true,
            'Mozilla/5.0 (Windows NT 6.3; Trident/7.0; rv:11.0)'  => true,
            'Mozilla/5.0 (Linux; U; Android 2.3.3; zh-tw; HTC)'   => false,
            'Mozilla/5.0 (Linux; Android 4.0.4; Galaxy Nexus)'    => true,
            'Opera/9.80 (Android; Opera Mini/7.6.35766/35.5706;)' => false,
        ];
        foreach ($userAgentList as $userAgent => $isSupported) {
            $request = new CM_Request_Get('/', ['user-agent' => $userAgent]);
            $this->assertSame($isSupported, $request->isSupported(), 'Mismatch for UA: `' . $userAgent . '`.');
        }
    }

    public function testIsSupportedWithoutUserAgent() {
        $request = new CM_Request_Get('/', []);
        $this->assertSame(true, $request->isSupported());
    }

    /**
     * @param string             $uri
     * @param array|null         $additionalHeaders
     * @param CM_Model_User|null $user
     * @return CM_Request_Abstract
     */
    private function _prepareRequest($uri, array $additionalHeaders = null, CM_Model_User $user = null) {
        $headers = array('Host' => 'example.com', 'Connection' => 'keep-alive');
        if ($additionalHeaders) {
            $headers = array_merge($headers, $additionalHeaders);
        }
        /** @var CM_Request_Abstract $request */
        $request = $this->getMockForAbstractClass('CM_Request_Abstract', array($uri, $headers), '', true, true, true, array('getViewer'));
        $request->expects($this->any())->method('getViewer')->will($this->returnValue($user));
        $this->assertSame($request->getViewer(), $user);
        return $request;
    }
}
