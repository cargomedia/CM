<?php

namespace CM\Test\Url;

use CMTest_TH;
use CMTest_TestCase;
use CM\Url\ResourceUrl;

class ResourceUrlTest extends CMTest_TestCase {

    public function tearDown() {
        CMTest_TH::clearEnv();
    }

    public function testCreate() {
        $url = ResourceUrl::create('file.ext', 'resource/type');
        $this->assertSame('/resource/type/file.ext', (string) $url);

        $environment = $this->createEnvironment(['type' => 999], null, 'de');
        $url = ResourceUrl::create('file.ext', 'resource/type', $environment);
        $this->assertSame('http://cdn.example.com/language-de/site-999/resource/type/file.ext', (string) $url);

        $url = ResourceUrl::create('file.ext', 'resource/type', $environment, 1234);
        $this->assertSame('http://cdn.example.com/language-de/site-999/version-1234/resource/type/file.ext', (string) $url);
    }

    public function testWithSite() {
        /** @var \PHPUnit_Framework_MockObject_MockObject|\CM_Site_Abstract $site */
        $site = $this
            ->getMockBuilder('CM_Site_Abstract')
            ->setMethods(['getId', 'getUrl', 'getUrlCdn'])
            ->getMockForAbstractClass();

        $site
            ->expects($this->any())
            ->method('getId')
            ->will($this->returnValue(42));

        $site
            ->expects($this->any())
            ->method('getUrl')
            ->will($this->returnValue('http://foo.com/path?param'));

        $site
            ->expects($this->any())
            ->method('getUrlCdn')
            ->will($this->returnValue('http://cdn.foo.com/path?param'));

        $url = ResourceUrl::create('file.ext', 'resource/type');

        $urlWithSite = $url->withSite($site);
        $this->assertSame('http://cdn.foo.com/path/site-42/resource/type/file.ext', (string) $urlWithSite);
    }
}
