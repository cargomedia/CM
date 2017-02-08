<?php

namespace CM\Test\Url;

use CMTest_TH;
use CMTest_TestCase;
use CM_Model_Language;
use CM_Frontend_Environment;
use CM\Url\UrlInterface;
use CM\Url\BaseUrl;
use CM\Url\AbstractUrl;
use League\Uri\Components\HierarchicalPath;

class AbstractUrlTest extends CMTest_TestCase {

    public function tearDown() {
        CMTest_TH::clearEnv();
    }

    public function testCreateFromString() {
        $url = CM_Url_AbstractMockUrl::createFromString('/bar?foobar=1');
        $this->assertInstanceOf('CM\Url\AbstractUrl', $url);
        $this->assertSame(null, $url->getPrefix());
        $this->assertSame(null, $url->getLanguage());
        $this->assertSame('/bar?foobar=1', (string) $url);

        $url = CM_Url_AbstractMockUrl::createFromString('http://스타벅스코리아.com/path/../foo/./bar');
        $this->assertInstanceOf('CM\Url\AbstractUrl', $url);
        $this->assertSame('http://스타벅스코리아.com/path/../foo/./bar', (string) $url);
    }

    public function testCreate() {
        $url = CM_Url_AbstractMockUrl::create('bar');
        $this->assertInstanceOf('CM\Url\AbstractUrl', $url);
        $this->assertSame(null, $url->getPrefix());
        $this->assertSame(null, $url->getLanguage());
        $this->assertSame('/bar', (string) $url);

        $url = CM_Url_AbstractMockUrl::create('/bar?foobar=1');
        $this->assertInstanceOf('CM\Url\AbstractUrl', $url);
        $this->assertSame(null, $url->getPrefix());
        $this->assertSame(null, $url->getLanguage());
        $this->assertSame('/bar?foobar=1', (string) $url);

        $url = CM_Url_AbstractMockUrl::create('http://스타벅스코리아.com/path/../foo/./bar');
        $this->assertInstanceOf('CM\Url\AbstractUrl', $url);
        $this->assertSame(null, $url->getPrefix());
        $this->assertSame(null, $url->getLanguage());
        $this->assertSame('http://xn--oy2b35ckwhba574atvuzkc.com/foo/bar', (string) $url);

        $baseUrl = BaseUrl::create('http://foo/?param');
        $environment = $this->createEnvironment();
        $url = CM_Url_AbstractMockUrl::create('/bar?foobar=1', $environment);
        $this->assertSame(null, $url->getPrefix());
        $this->assertSame(null, $url->getLanguage());
        $this->assertSame('http://www.example.com/bar?foobar=1', (string) $url);

        $environment = $this->createEnvironment(['url' => 'http://www.example.com/prefix?param']);
        $url = CM_Url_AbstractMockUrl::create('/bar?foobar=1', $environment);
        $this->assertSame('prefix', $url->getPrefix());
        $this->assertSame(null, $url->getLanguage());
        $this->assertSame('http://www.example.com/prefix/bar?foobar=1', (string) $url);

        $environment = $this->createEnvironment(['url' => 'http://www.example.com/prefix?param'], null, 'de');
        $url = CM_Url_AbstractMockUrl::create('/bar?foobar=1', $environment);
        $this->assertSame('prefix', $url->getPrefix());
        $this->assertSame($environment->getLanguage(), $url->getLanguage());
        $this->assertSame('http://www.example.com/prefix/bar/de?foobar=1', (string) $url);
    }

    public function testWithPrefix() {
        $url = CM_Url_AbstractMockUrl::create('/path?foo=1#bar');
        $this->assertSame(null, $url->getPrefix());

        $urlWithPrefix = $url->withPrefix('prefix');
        $this->assertSame('prefix', $urlWithPrefix->getPrefix());
        $this->assertSame('/prefix/path?foo=1#bar', (string) $urlWithPrefix);

        $urlWithPrefix = $url->withPrefix('/prefix/');
        $this->assertSame('prefix', $urlWithPrefix->getPrefix());
        $this->assertSame('/prefix/path?foo=1#bar', (string) $urlWithPrefix);

        $urlWithPrefix = $url->withPrefix('prefix/foo');
        $this->assertSame('prefix/foo', $urlWithPrefix->getPrefix());
        $this->assertSame('/prefix/foo/path?foo=1#bar', (string) $urlWithPrefix);

        $urlWithPrefix = $url->withPrefix(new HierarchicalPath('prefix'));
        $this->assertSame('prefix', $urlWithPrefix->getPrefix());
        $this->assertSame('/prefix/path?foo=1#bar', (string) $urlWithPrefix);

        $urlWithPrefix = $url->withPrefix(new HierarchicalPath('/'));
        $this->assertSame(null, $urlWithPrefix->getPrefix());
        $this->assertSame('/path?foo=1#bar', (string) $urlWithPrefix);

        $urlWithPrefix = $url->withPrefix('/');
        $this->assertSame(null, $urlWithPrefix->getPrefix());
        $this->assertSame('/path?foo=1#bar', (string) $urlWithPrefix);

        $urlWithPrefix = $url->withPrefix(new HierarchicalPath(''));
        $this->assertSame(null, $urlWithPrefix->getPrefix());
        $this->assertSame('/path?foo=1#bar', (string) $urlWithPrefix);

        $urlWithPrefix = $url->withPrefix('');
        $this->assertSame(null, $urlWithPrefix->getPrefix());
        $this->assertSame('/path?foo=1#bar', (string) $urlWithPrefix);

        $urlWithPrefix = $url->withPrefix(null);
        $this->assertSame(null, $urlWithPrefix->getPrefix());
        $this->assertSame('/path?foo=1#bar', (string) $urlWithPrefix);
    }

    public function testWithLanguage() {
        $language = CMTest_TH::createLanguage('de');
        $url = CM_Url_AbstractMockUrl::create('/bar?foobar=1');
        $urlWithLanguage = $url->withLanguage($language);

        $this->assertNotEquals($url, $urlWithLanguage);
        $this->assertSame(null, $url->getLanguage());
        $this->assertSame('/bar?foobar=1', (string) $url);
        $this->assertSame($language, $urlWithLanguage->getLanguage());
        $this->assertSame('/bar/de?foobar=1', (string) $urlWithLanguage);
    }

    public function testWithBaseUrl() {
        $baseUrl = BaseUrl::create('http://foo/?param');
        $url = CM_Url_AbstractMockUrl::create('/bar?foobar=1');
        $urlWithBaseUrl = $url->withBaseUrl($baseUrl);

        $this->assertSame(null, $url->getPrefix());
        $this->assertSame('/bar?foobar=1', (string) $url);
        $this->assertSame(null, $urlWithBaseUrl->getPrefix());
        $this->assertSame('http://foo/bar?foobar=1', (string) $urlWithBaseUrl);

        $baseUrlWithPrefix = $baseUrl->withPrefix('prefix');
        $urlWithBaseUrlAndPrefix = $url->withBaseUrl($baseUrlWithPrefix);

        $this->assertSame('prefix', $baseUrlWithPrefix->getPrefix());
        $this->assertSame('http://foo/prefix', (string) $baseUrlWithPrefix);
        $this->assertSame('prefix', $urlWithBaseUrlAndPrefix->getPrefix());
        $this->assertSame('http://foo/prefix/bar?foobar=1', (string) $urlWithBaseUrlAndPrefix);

        $urlWithPrefixPreserved = $urlWithBaseUrlAndPrefix->withPath('/baz');
        $this->assertSame('prefix', $urlWithPrefixPreserved->getPrefix());
        $this->assertSame('http://foo/prefix/baz?foobar=1', (string) $urlWithPrefixPreserved);

        /** @var \CM_Exception_Invalid $exception */
        $exception = $this->catchException(function () {
            $baseUrl = BaseUrl::create('/path?param');
            CM_Url_AbstractMockUrl::create('/bar?foobar=1', $baseUrl);
        });
        $this->assertInstanceOf('CM_Exception_Invalid', $exception);
        $this->assertSame('BaseUrl::create argument must be an absolute Url', $exception->getMessage());
        $this->assertSame([
            'url' => '/path?param',
        ], $exception->getMetaInfo());
    }

    public function testWithEnvironment() {
        $url = CM_Url_AbstractMockUrl::create('/bar?foobar=1');

        $environment = $this->createEnvironment();
        $urlWithEnvironment = $url->withEnvironment($environment);
        $this->assertSame(null, $url->getLanguage());
        $this->assertSame('/bar?foobar=1', (string) $url);
        $this->assertSame($environment->getSite(), $urlWithEnvironment->getSite());
        $this->assertSame(null, $urlWithEnvironment->getLanguage());
        $this->assertSame('http://www.example.com/bar?foobar=1', (string) $urlWithEnvironment);

        $environment = $this->createEnvironment(null, null, 'de');
        $urlWithEnvironmentAndLanguage = $url->withEnvironment($environment);
        $this->assertSame($environment->getSite(), $urlWithEnvironmentAndLanguage->getSite());
        $this->assertSame($environment->getLanguage(), $urlWithEnvironmentAndLanguage->getLanguage());
        $this->assertSame('http://www.example.com/bar/de?foobar=1', (string) $urlWithEnvironmentAndLanguage);

        $urlWithEnvironmentPreserved = $urlWithEnvironmentAndLanguage->withPath('/baz');
        $this->assertSame($environment->getSite(), $urlWithEnvironmentPreserved->getSite());
        $this->assertSame($environment->getLanguage(), $urlWithEnvironmentPreserved->getLanguage());
        $this->assertSame('http://www.example.com/baz/de?foobar=1', (string) $urlWithEnvironmentPreserved);
    }

    public function testWithRelativeComponentsFrom() {
        $url1 = CM_Url_AbstractMockUrl::createFromString('http://foo/path?foo=1');
        $url2 = CM_Url_AbstractMockUrl::createFromString('http://bar/path?bar=1');

        $this->assertSame('http://foo/path?bar=1', (string) $url1->withRelativeComponentsFrom($url2));
    }

    public function testWithoutRelativeComponents() {
        $url = CM_Url_AbstractMockUrl::createFromString('/path?foo=1');
        $this->assertSame('/', (string) $url->withoutRelativeComponents());

        $url = CM_Url_AbstractMockUrl::createFromString('http://foo/path?foo=1');
        $this->assertSame('http://foo/', (string) $url->withoutRelativeComponents());
    }

    public function testIsAbsolute() {
        $url = CM_Url_AbstractMockUrl::createFromString('/bar?foobar=1');
        $this->assertSame(false, $url->isAbsolute());

        $url = CM_Url_AbstractMockUrl::createFromString('http://foo/bar?foobar=1');
        $this->assertSame(true, $url->isAbsolute());
    }
}

class CM_Url_AbstractMockUrl extends AbstractUrl {

    public function getUriRelativeComponents() {
        $path = $this->path;
        if ($prefix = $this->getPrefix()) {
            $path = $path->prepend($prefix);
        }
        if ($language = $this->getLanguage()) {
            $path = $path->append($language->getAbbreviation());
        }
        return ''
            . $path->getUriComponent()
            . $this->query->getUriComponent()
            . $this->fragment->getUriComponent();
    }

    public static function create($url, CM_Frontend_Environment $environment = null) {
        return parent::_create($url, $environment);
    }
}
