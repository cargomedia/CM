<?php

class CM_ExceptionTest extends CMTest_TestCase {

    public function testConstructor() {
        $user = CMTest_TH::createUser();
        $metaInfo = array('meta' => 'foo', 'user' => $user);
        $severity = CM_Exception::ERROR;
        $exception = new CM_Exception('foo', $severity, $metaInfo, [
            'messagePublic' => new CM_I18n_Phrase('foo {$bar}', ['bar' => 'foo']),
        ]);
        $render = new CM_Frontend_Render();

        $this->assertSame('foo', $exception->getMessage());
        $this->assertSame('foo foo', $exception->getMessagePublic($render));
        $this->assertSame($severity, $exception->getSeverity());
        $this->assertSame($metaInfo, $exception->getMetaInfo());
    }

    public function testGetSetSeverity() {
        $exception = new CM_Exception();
        $this->assertSame(CM_Exception::ERROR, $exception->getSeverity());

        $exception->setSeverity(CM_Exception::WARN);
        $this->assertSame(CM_Exception::WARN, $exception->getSeverity());
    }

    public function testGetSetSeverityInvalid() {
        $exception = new CM_Exception();

        try {
            $exception->setSeverity(9999);
            $this->fail('Could set invalid severity');
        } catch (CM_Exception_Invalid $e) {
            $this->assertSame('Invalid severity `9999`', $e->getMessage());
        }

        try {
            $exception->setSeverity('1');
            $this->fail('Could set invalid severity');
        } catch (CM_Exception_Invalid $e) {
            $this->assertSame('Invalid severity `1`', $e->getMessage());
        }
    }
}
