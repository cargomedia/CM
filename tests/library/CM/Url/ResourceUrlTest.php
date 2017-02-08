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
        $url = ResourceUrl::create('file.ext', 'resource-type');
        $this->assertSame('/resource-type/file.ext', (string) $url);

        $environment = $this->createEnvironment(['type' => 999], null, 'de');
        $url = ResourceUrl::create('file.ext', 'resource-type', $environment);
        $this->assertSame('http://cdn.example.com/resource-type/de/999/file.ext', (string) $url);

        $url = ResourceUrl::create('file.ext', 'resource-type', $environment, null, 1234);
        $this->assertSame('http://cdn.example.com/resource-type/de/999/1234/file.ext', (string) $url);

        $url = ResourceUrl::create('file.ext', 'resource-type', $environment, ['sameOrigin' => true], 1234);
        $this->assertSame('http://www.example.com/resource-type/de/999/1234/file.ext', (string) $url);
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

        $url = ResourceUrl::create('file.ext', 'resource-type');

        $urlWithSite = $url->withSite($site);
        $this->assertSame('http://cdn.foo.com/resource-type/42/file.ext', (string) $urlWithSite);

        $urlWithSiteSameOrigin = $url->withSite($site, true);
        $this->assertSame('http://foo.com/resource-type/42/file.ext', (string) $urlWithSiteSameOrigin);
    }
}
