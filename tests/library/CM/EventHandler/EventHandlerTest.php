<?php

class CM_EventHandler_EventHandlerTest extends CMTest_TestCase {

    public static $_foo;
    public static $_counter;

    public function testBindJob() {
        $eventHandler = new CM_EventHandler_EventHandler();
        self::$_foo = '';
        $eventHandler->bindJob('foo', CM_JobMock_1::class, array('text' => 'bar'));
        $eventHandler->trigger('foo');
        $this->assertEquals('bar', self::$_foo);

        self::$_counter = 0;
        $eventHandler->bindJob('foo', CM_JobMock_2::class);
        $eventHandler->bindJob('foo', CM_JobMock_3::class, array('i' => 2));
        $eventHandler->bindJob('foo', CM_JobMock_4::class, array('a' => 4));
        $eventHandler->trigger('foo', array('i' => 8));
        $this->assertEquals('barbar', self::$_foo);
        $this->assertEquals(13, self::$_counter);

        $eventHandler->trigger('foo', array('text' => 'eclan'));
        $this->assertEquals(20, self::$_counter);
        $this->assertEquals('barbareclan', self::$_foo);

        try {
            $eventHandler->trigger('nonExistentEvent');
            $this->assertTrue(true);
        } catch (Exception $ex) {
            $this->fail('Cant trigger nonexistent events');
        }
    }
}

class CM_JobMock_1 extends CM_Jobdistribution_Job_Abstract {

    protected function _execute(CM_Params $params) {
        CM_EventHandler_EventHandlerTest::$_foo .= $params->getString('text');
    }
}

class CM_JobMock_2 extends CM_Jobdistribution_Job_Abstract {

    protected function _execute(CM_Params $params) {
        CM_EventHandler_EventHandlerTest::$_counter++;
    }
}

class CM_JobMock_3 extends CM_Jobdistribution_Job_Abstract {

    protected function _execute(CM_Params $params) {
        CM_EventHandler_EventHandlerTest::$_counter += $params->getInt('i');
    }
}

class CM_JobMock_4 extends CM_Jobdistribution_Job_Abstract {

    protected function _execute(CM_Params $params) {
        CM_EventHandler_EventHandlerTest::$_counter += $params->getInt('a');
    }
}
