<?php

class CM_Paging_ContentList_BadwordsTest extends CMTest_TestCase {

	/** @var CM_Paging_ContentList_Badwords */
	private $_paging;

	public function setUp() {
		$this->_paging = new CM_Paging_ContentList_Badwords();
		$this->_paging->add('bad.com');
		$this->_paging->add('superbad');
		$this->_paging->add('foo*bar');
		$this->_paging->add('|bar|');
	}

	protected function tearDown() {
		$this->_paging->removeAll();
	}

	public function testIsMatch(){
		$this->assertTrue($this->_paging->isMatch('bad.com'));
		$this->assertTrue($this->_paging->isMatch('BAD.com'));
		$this->assertTrue($this->_paging->isMatch('sub.bad.com'));
		$this->assertTrue($this->_paging->isMatch('bad.com-foo.de'));
		$this->assertFalse($this->_paging->isMatch('evil.com'));
		$this->assertTrue($this->_paging->isMatch('foo-bar'));
		$this->assertTrue($this->_paging->isMatch('test bar test'));
		$this->assertFalse($this->_paging->isMatch('testbar'));
		$this->assertFalse($this->_paging->isMatch('testbartest'));
		$this->assertFalse($this->_paging->isMatch('test bartest'));

		$this->_paging->add('evil.com');
		$this->assertTrue($this->_paging->isMatch('evil.com'));
	}

	public function testGetMatch(){
		$this->assertSame('bad.com', $this->_paging->getMatch('bad.com'));
		$this->assertSame('bad.com', $this->_paging->getMatch('BAD.com'));
		$this->assertSame('bad.com', $this->_paging->getMatch('sub.bad.com'));
		$this->assertSame('bad.com', $this->_paging->getMatch('bad.com-foo.de'));
		$this->assertFalse($this->_paging->getMatch('evil.com'));
		$this->assertSame('foobar', $this->_paging->getMatch('foo-bar'));
		$this->assertSame('foobar', $this->_paging->getMatch('hallo foo-bar world.'));
		$this->assertSame('bar', $this->_paging->getMatch('test bar test'));
		$this->assertFalse($this->_paging->getMatch('testbar'));
		$this->assertFalse($this->_paging->getMatch('testbartest'));
		$this->assertFalse($this->_paging->getMatch('test bartest'));

		$this->_paging->add('evil.com');
		$this->assertSame('evil.com', $this->_paging->getMatch('evil.com'));
	}

	public function testReplaceMatch(){
		$this->assertSame('hallo … world.', $this->_paging->replaceMatch('hallo foo-bar world.', '…'));
		$this->assertSame('hallo $1 \1 world.', $this->_paging->replaceMatch('hallo foo-bar world.', '$1 \1'));
		$this->assertSame('Hello … world.', $this->_paging->replaceMatch('Hello bar world.', '…'));
		$this->assertSame('Hello … world.', $this->_paging->replaceMatch('Hello sub.bad.com world.', '…'));
	}
}
