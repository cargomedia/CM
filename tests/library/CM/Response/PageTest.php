<?php
require_once __DIR__ . '/../../../TestCase.php';

class CM_Response_PageTest extends TestCase {

	public function tearDown() {
		TH::clearEnv();
	}

	public function testProcessLanguageRedirect() {
		TH::createLanguage('en');
		$user = TH::createUser();
		$response = TH::createResponsePage('/en/mock5', null, $user);
		try {
			$response->process();
			$this->fail('Language redirect doesn\'t work');
		} catch (CM_Exception_Redirect $e) {
			$this->assertSame(CM_Config::get()->CM_Site_CM->url . '/mock5', $e->getUri());
		}
	}

	public function testProcessLanguageNoRedirect() {
		$language = TH::createLanguage('en');
		$user = TH::createUser();
		try {
			$response = TH::createResponsePage('/en/mock5');
			$response->process();
			$this->assertModelEquals($language, $response->getRequest()->getLanguageUrl());

			$response = TH::createResponsePage('/mock5');
			$response->process();
			$this->assertNull($response->getRequest()->getLanguageUrl());
			
			$response = TH::createResponsePage('/mock5', null, $user);
			$response->process();
			$this->assertNull($response->getRequest()->getLanguageUrl());
		} catch (CM_Exception_Redirect $e) {
			$this->fail('Should not be redirected');
		}
	}
}

class CM_Page_Mock5 extends CM_Page_Abstract {

	public function getLayout() {
		$layoutname = 'Mock';
		$classname = self::_getClassNamespace() . '_Layout_' . $layoutname;
		return new $classname($this);
	}

}

class CM_Layout_Mock extends CM_Layout_Abstract {

}
