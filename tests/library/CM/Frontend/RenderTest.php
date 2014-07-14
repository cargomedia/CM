<?php

class CM_Frontend_RenderTest extends CMTest_TestCase {

    protected function setUp() {
        CM_Config::get()->CM_Site_Abstract->url = 'http://www.default.dev';
        CM_Config::get()->CM_Site_Abstract->urlCdn = 'http://cdn.default.dev';
    }

    public function tearDown() {
        CMTest_TH::clearEnv();
    }

    public function testGetSiteName() {
        $render = new CM_Frontend_Render();
        $this->assertSame('Default', $render->getSiteName());
    }

    public function testGetUrl() {
        $render = new CM_Frontend_Render();
        $this->assertSame('http://www.default.dev', $render->getUrl());
        $this->assertSame('http://www.default.dev/foo/bar', $render->getUrl('/foo/bar'));
        $this->assertSame('http://www.default.dev/0', $render->getUrl('/0'));
    }

    public function testGetUrlPage() {
        $render = new CM_Frontend_Render();
        $page = $this->getMockForAbstractClass('CM_Page_Abstract', array(), 'CM_Page_Foo_Bar_FooBar', false);

        $this->assertSame('http://www.default.dev/foo/bar/foo-bar',
            $render->getUrlPage('CM_Page_Foo_Bar_FooBar'));
        $this->assertSame('http://www.default.dev/foo/bar/foo-bar',
            $render->getUrlPage($page));
        $this->assertSame('http://www.default.dev/foo/bar/foo-bar?userId=15&foo=bar',
            $render->getUrlPage('CM_Page_Foo_Bar_FooBar', array('userId' => 15, 'foo' => 'bar')));
        $this->assertSame('http://www.default.dev/foo/bar/foo-bar?userId=15&foo=bar',
            $render->getUrlPage($page, array('userId' => 15, 'foo' => 'bar')));
    }

    public function testGetUrlPageInvalidModule() {
        $render = new CM_Frontend_Render();

        $this->getMockForAbstractClass('CM_Page_Abstract', array(), 'INVALIDMODULE_Page_Test', false);
        try {
            $render->getUrlPage('INVALIDMODULE_Page_Test');
            $this->fail('Can compute path of page with invalid module');
        } catch (CM_Exception_Invalid $ex) {
            $this->assertTrue(true);
        }
    }

    public function testGetUrlPageDifferentSite() {
        $render = new CM_Frontend_Render();
        $page = $this->getMockForAbstractClass('CM_Page_Abstract', array(), 'CM_Page_Foo_Bar_FooBar', false);

        $site = $this->getMockSite(null, null, array(
            'url'          => 'http://www.test.dev',
            'urlCdn'       => 'http://cdn.test.dev',
            'name'         => 'Test',
            'emailAddress' => 'test@test.dev',
        ));
        $renderSite = new CM_Frontend_Render($site);

        $this->assertSame('http://www.test.dev/foo/bar/foo-bar',
            $render->getUrlPage('CM_Page_Foo_Bar_FooBar', null, $site));
        $this->assertSame('http://www.test.dev/foo/bar/foo-bar',
            $render->getUrlPage($page, null, $site));
        $this->assertSame('http://www.test.dev/foo/bar/foo-bar?userId=15&foo=bar',
            $render->getUrlPage('CM_Page_Foo_Bar_FooBar', array('userId' => 15, 'foo' => 'bar'), $site));
        $this->assertSame('http://www.test.dev/foo/bar/foo-bar?userId=15&foo=bar',
            $render->getUrlPage($page, array('userId' => 15, 'foo' => 'bar'), $site));

        $this->assertSame('http://www.test.dev/foo/bar/foo-bar',
            $renderSite->getUrlPage('CM_Page_Foo_Bar_FooBar'));
        $this->assertSame('http://www.test.dev/foo/bar/foo-bar',
            $renderSite->getUrlPage($page));
        $this->assertSame('http://www.test.dev/foo/bar/foo-bar?userId=15&foo=bar',
            $renderSite->getUrlPage('CM_Page_Foo_Bar_FooBar', array('userId' => 15, 'foo' => 'bar')));
        $this->assertSame('http://www.test.dev/foo/bar/foo-bar?userId=15&foo=bar',
            $renderSite->getUrlPage($page, array('userId' => 15, 'foo' => 'bar')));
    }

    public function testGetUrlPageLanguageRewrite() {
        $page = $this->getMockForAbstractClass('CM_Page_Abstract', array(), 'CM_Page_Test', false);
        $baseUrl = 'http://www.default.dev';

        $render = new CM_Frontend_Render();
        $this->assertSame($baseUrl . '/test', $render->getUrlPage($page));
        $render = new CM_Frontend_Render(null, null, null, true); // This should never happen in application, but lets test it
        $this->assertSame($baseUrl . '/test', $render->getUrlPage($page));

        $language = CMTest_TH::createLanguage('en');

        $render = new CM_Frontend_Render();
        $this->assertSame($baseUrl . '/test', $render->getUrlPage($page));
        $render = new CM_Frontend_Render(null, null, null, true); // This should never happen in application, but lets test it
        $this->assertSame($baseUrl . '/en/test', $render->getUrlPage($page));

        $render = new CM_Frontend_Render(null, null, $language, null);
        $this->assertSame($baseUrl . '/test', $render->getUrlPage($page));
        $render = new CM_Frontend_Render(null, null, $language, true);
        $this->assertSame($baseUrl . '/en/test', $render->getUrlPage($page));
    }

    public function testGetUrlResource() {
        $render = new CM_Frontend_Render();
        $siteType = CM_Site_Abstract::factory()->getType();
        $deployVersion = CM_App::getInstance()->getDeployVersion();
        $this->assertSame('http://cdn.default.dev', $render->getUrlResource());
        $this->assertSame('http://cdn.default.dev', $render->getUrlResource('layout'));
        $this->assertSame('http://cdn.default.dev', $render->getUrlResource(null, 'foo/bar.jpg'));
        $this->assertSame(
            'http://cdn.default.dev/layout/' . $siteType . '/' . $deployVersion . '/foo/bar.jpg', $render->getUrlResource('layout', 'foo/bar.jpg'));
        $this->assertSame('http://cdn.default.dev/layout/' . $siteType . '/' . $deployVersion . '/0', $render->getUrlResource('layout', '0'));
        $this->assertSame('http://cdn.default.dev/0/' . $siteType . '/' . $deployVersion . '/foo.jpg', $render->getUrlResource('0', 'foo.jpg'));
    }

    public function testGetUrlStatic() {
        $render = new CM_Frontend_Render();
        $deployVersion = CM_App::getInstance()->getDeployVersion();
        $this->assertSame('http://cdn.default.dev/static', $render->getUrlStatic());
        $this->assertSame('http://cdn.default.dev/static/foo.jpg?' . $deployVersion, $render->getUrlStatic('/foo.jpg'));
        $this->assertSame('http://cdn.default.dev/static/0?' . $deployVersion, $render->getUrlStatic('/0'));
    }

    public function testGetTranslation() {
        $render = new CM_Frontend_Render();
        $this->assertSame('abc', $render->getTranslation('abc'));
        $this->assertSame('abc cool', $render->getTranslation('abc {$variable}', array('variable' => 'cool')));
        $this->assertSame('abc ', $render->getTranslation('abc {$variable}'));
        $this->assertSame('abc ', $render->getTranslation('abc {$variable}', array('foo' => 'bar')));

        /** @var CM_Model_Language $language */
        $language = CM_Model_Language::createStatic(array(
            'name'         => 'Test language',
            'abbreviation' => 'test',
            'enabled'      => true
        ));
        $render = new CM_Frontend_Render(null, null, $language);
        $language->setTranslation('abc {$variable}', 'translated stuff is {$variable}');
        CM_Model_Language::changeAll();
        $this->assertSame('translated stuff is cool', $render->getTranslation('abc {$variable}', array('variable' => 'cool')));
    }

    public function testGetViewer() {
        $viewer = CMTest_TH::createUser();
        $render = new CM_Frontend_Render(null, $viewer);
        $this->assertEquals($viewer, $render->getViewer());

        $render = new CM_Frontend_Render();
        $this->assertNull($render->getViewer());
    }

    public function testParseTemplateContent() {
        $viewer = CM_Model_User::createStatic();
        $render = new CM_Frontend_Render();
        $render->getEnvironment()->setViewer($viewer);

        $content = '{$viewer->getId()} {$foo} normal-text';
        $expected = $viewer->getId() . ' bar normal-text';
        $this->assertSame($expected, $render->parseTemplateContent($content, ['foo' => 'bar']));
    }
}
