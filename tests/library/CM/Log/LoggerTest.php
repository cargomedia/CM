<?php

class CM_Log_LoggerTest extends CMTest_TestCase {

    public function testAddRecord() {
        $mockLogHandler = $this->mockInterface('CM_Log_Handler_HandlerInterface')->newInstance();
        $mockHandleRecord = $mockLogHandler->mockMethod('handleRecord');

        $logger = new CM_Log_Logger(new CM_Log_Context(), [$mockLogHandler]);

        $expectedRecord = new CM_Log_Record(CM_Log_Logger::INFO, 'foo', new CM_Log_Context());
        $mockHandleRecord->set(function (CM_Log_Record $record) use ($expectedRecord) {
            $this->assertSame($expectedRecord, $record);
        });

        $logger->addRecord($expectedRecord);
        $this->assertSame(1, $mockHandleRecord->getCallCount());
    }

    public function testHandlerBubbling() {
        $mockLogHandlerFoo = $this->mockInterface('CM_Log_Handler_HandlerInterface')->newInstance();
        $mockLogHandlerBar = $this->mockInterface('CM_Log_Handler_HandlerInterface')->newInstance();
        $mockHandleRecordFoo = $mockLogHandlerFoo->mockMethod('handleRecord');
        $mockHandleRecordBar = $mockLogHandlerBar->mockMethod('handleRecord');

        $logger = new CM_Log_Logger(new CM_Log_Context(), [$mockLogHandlerFoo, $mockLogHandlerBar]);

        $mockLogHandlerFoo->mockMethod('isBubbling')->set(true);
        $mockLogHandlerBar->mockMethod('isBubbling')->set(true);
        $mockLogHandlerFoo->mockMethod('isHandling')->set(true);
        $mockLogHandlerBar->mockMethod('isHandling')->set(true);
        $expectedRecord = new CM_Log_Record(CM_Log_Logger::INFO, 'foo', new CM_Log_Context());
        $logger->addRecord($expectedRecord);
        $this->assertSame(1, $mockHandleRecordFoo->getCallCount());
        $this->assertSame(1, $mockHandleRecordBar->getCallCount());
    }

    public function testHandlerNotBubbling() {
        $mockLogHandlerFoo = $this->mockInterface('CM_Log_Handler_HandlerInterface')->newInstance();
        $mockLogHandlerBar = $this->mockInterface('CM_Log_Handler_HandlerInterface')->newInstance();
        $mockHandleRecordFoo = $mockLogHandlerFoo->mockMethod('handleRecord');
        $mockHandleRecordBar = $mockLogHandlerBar->mockMethod('handleRecord');

        $logger = new CM_Log_Logger(new CM_Log_Context(), [$mockLogHandlerFoo, $mockLogHandlerBar]);

        $mockLogHandlerFoo->mockMethod('isBubbling')->set(false);
        $mockLogHandlerBar->mockMethod('isBubbling')->set(false);
        $mockLogHandlerFoo->mockMethod('isHandling')->set(true);
        $mockLogHandlerBar->mockMethod('isHandling')->set(true);
        $expectedRecord = new CM_Log_Record(CM_Log_Logger::INFO, 'foo', new CM_Log_Context());
        $logger->addRecord($expectedRecord);
        $this->assertSame(1, $mockHandleRecordFoo->getCallCount());
        $this->assertSame(0, $mockHandleRecordBar->getCallCount());
    }

    public function testHandlerNotBubblingWithNotHandledLevel() {
        $mockLogHandlerFoo = $this->mockInterface('CM_Log_Handler_HandlerInterface')->newInstance();
        $mockLogHandlerBar = $this->mockInterface('CM_Log_Handler_HandlerInterface')->newInstance();
        $mockHandleRecordFoo = $mockLogHandlerFoo->mockMethod('handleRecord');
        $mockHandleRecordBar = $mockLogHandlerBar->mockMethod('handleRecord');

        $logger = new CM_Log_Logger(new CM_Log_Context(), [$mockLogHandlerFoo, $mockLogHandlerBar]);

        $mockLogHandlerFoo->mockMethod('isBubbling')->set(false);
        $mockLogHandlerBar->mockMethod('isBubbling')->set(false);
        $mockLogHandlerFoo->mockMethod('isHandling')->set(false);
        $mockLogHandlerBar->mockMethod('isHandling')->set(false);
        $expectedRecord = new CM_Log_Record(CM_Log_Logger::INFO, 'foo', new CM_Log_Context());
        $logger->addRecord($expectedRecord);
        $this->assertSame(1, $mockHandleRecordFoo->getCallCount());
        $this->assertSame(1, $mockHandleRecordBar->getCallCount());
    }

    public function testHandlerBubblingForcedOnError() {
        $mockLogHandlerFoo = $this->mockInterface('CM_Log_Handler_HandlerInterface')->newInstance();
        $mockLogHandlerBar = $this->mockInterface('CM_Log_Handler_HandlerInterface')->newInstance();
        $mockLogHandlerFooBar = $this->mockInterface('CM_Log_Handler_HandlerInterface')->newInstance();
        $mockHandleRecordFoo = $mockLogHandlerFoo->mockMethod('handleRecord');
        $mockHandleRecordBar = $mockLogHandlerBar->mockMethod('handleRecord');
        $mockHandleRecordFooBar = $mockLogHandlerFooBar->mockMethod('handleRecord');

        $logger = new CM_Log_Logger(new CM_Log_Context(), [$mockLogHandlerFoo, $mockLogHandlerBar, $mockLogHandlerFooBar]);

        $mockLogHandlerFoo->mockMethod('isBubbling')->set(false);
        $mockLogHandlerBar->mockMethod('isBubbling')->set(false);
        $mockLogHandlerFooBar->mockMethod('isBubbling')->set(false);
        $mockLogHandlerFoo->mockMethod('isHandling')->set(true);
        $mockLogHandlerBar->mockMethod('isHandling')->set(true);
        $mockLogHandlerFooBar->mockMethod('isHandling')->set(true);

        $mockHandleRecordFoo->set(function () {
            throw new Exception('handler failed.');
        });

        $expectedRecord = new CM_Log_Record(CM_Log_Logger::INFO, 'foo', new CM_Log_Context());
        try {
            $logger->addRecord($expectedRecord);
            $this->fail('CM_Log_HandlingException not thrown.');
        } catch (CM_Log_HandlingException $e) {
            $this->assertSame('1 handler(s) failed to process a record.', $e->getMessage());
        }
        $this->assertSame(1, $mockHandleRecordFoo->getCallCount());
        $this->assertSame(1, $mockHandleRecordBar->getCallCount());
        $this->assertSame(0, $mockHandleRecordFooBar->getCallCount());
    }

    public function testLoggingWithContext() {
        $mockLogHandler = $this->mockInterface('CM_Log_Handler_HandlerInterface')->newInstance();
        $mockHandleRecord = $mockLogHandler->mockMethod('handleRecord');

        // without any context
        $logger = new CM_Log_Logger(new CM_Log_Context(), [$mockLogHandler]);
        $mockHandleRecord->set(function (CM_Log_Record $record) {
            $context = $record->getContext();
            $this->assertNull($context->getComputerInfo());
            $this->assertNull($context->getUser());
            $this->assertNull($context->getHttpRequest());
            $this->assertSame([], $context->getExtra());
        });
        $logger->addMessage('foo', CM_Log_Logger::INFO);
        $this->assertSame(1, $mockHandleRecord->getCallCount());

        // with a global context
        $computerInfo = new CM_Log_Context_ComputerInfo('foo.dev', '42.0');
        $contextGlobal = new CM_Log_Context(null, null, $computerInfo);
        $logger = new CM_Log_Logger($contextGlobal, [$mockLogHandler], []);

        $mockHandleRecord->set(function (CM_Log_Record $record) use ($computerInfo) {
            $context = $record->getContext();
            $this->assertEquals($computerInfo, $context->getComputerInfo());
            $this->assertNull($context->getUser());
            $this->assertNull($context->getHttpRequest());
            $this->assertSame([], $context->getExtra());
        });
        $logger->addMessage('foo', CM_Log_Logger::INFO);
        $this->assertSame(2, $mockHandleRecord->getCallCount());

        // with a global context + log context
        $computerInfo = new CM_Log_Context_ComputerInfo('foo.dev', '42.0');
        $contextGlobal = new CM_Log_Context(null, null, $computerInfo);
        $logger = new CM_Log_Logger($contextGlobal, [$mockLogHandler], []);

        $mockHandleRecord->set(function (CM_Log_Record $record) use ($computerInfo) {
            $context = $record->getContext();
            $this->assertEquals($computerInfo, $context->getComputerInfo());
            $this->assertNull($context->getUser());
            $this->assertNull($context->getHttpRequest());
            $this->assertSame(['foo' => 10], $context->getExtra());
        });
        $logger->addMessage('foo', CM_Log_Logger::INFO, new CM_Log_Context(null, null, null, ['foo' => 10]));
        $this->assertSame(3, $mockHandleRecord->getCallCount());
    }

    public function testLogHelpers() {
        $mockLogHandler = $this->mockInterface('CM_Log_Handler_HandlerInterface')->newInstance();
        $mockHandleRecord = $mockLogHandler->mockMethod('handleRecord');

        $logger = new CM_Log_Logger(new CM_Log_Context(), [$mockLogHandler]);

        $mockHandleRecord->set(function (CM_Log_Record $record) {
            $this->assertSame('message sent using debug method', $record->getMessage());
            $this->assertSame(CM_Log_Logger::DEBUG, $record->getLevel());
        });
        $logger->debug('message sent using debug method');
        $this->assertSame(1, $mockHandleRecord->getCallCount());

        $mockHandleRecord->set(function (CM_Log_Record $record) {
            $this->assertSame('message sent using info method', $record->getMessage());
            $this->assertSame(CM_Log_Logger::INFO, $record->getLevel());
        });
        $logger->info('message sent using info method');
        $this->assertSame(2, $mockHandleRecord->getCallCount());

        $mockHandleRecord->set(function (CM_Log_Record $record) {
            $this->assertSame('message sent using warning method', $record->getMessage());
            $this->assertSame(CM_Log_Logger::WARNING, $record->getLevel());
        });
        $logger->warning('message sent using warning method');
        $this->assertSame(3, $mockHandleRecord->getCallCount());

        $mockHandleRecord->set(function (CM_Log_Record $record) {
            $this->assertSame('message sent using error method', $record->getMessage());
            $this->assertSame(CM_Log_Logger::ERROR, $record->getLevel());
        });
        $logger->error('message sent using error method');
        $this->assertSame(4, $mockHandleRecord->getCallCount());

        $mockHandleRecord->set(function (CM_Log_Record $record) {
            $this->assertSame('message sent using critical method', $record->getMessage());
            $this->assertSame(CM_Log_Logger::CRITICAL, $record->getLevel());
        });
        $logger->critical('message sent using critical method');
        $this->assertSame(5, $mockHandleRecord->getCallCount());
    }

    public function testHandlingException() {
        $mockLogHandlerFoo = $this->mockClass('CM_Log_Handler_Abstract')->newInstance();
        $mockLogHandlerFoo->mockMethod('handleRecord')->set(function () {
            throw new Exception('exception from foo');
        });

        $mockLogHandlerBar = $this->mockClass('CM_Log_Handler_Abstract')->newInstance();
        $mockLogHandlerBar->mockMethod('handleRecord')->set(function () {
            throw new Exception('exception from bar');
        });

        $logger = new CM_Log_Logger(new CM_Log_Context(), [$mockLogHandlerFoo, $mockLogHandlerBar]);

        try {
            $logger->info('test');
            $this->fail('CM_Log_HandlingException not thrown.');
        } catch (CM_Log_HandlingException $e) {
            $exceptionList = $e->getExceptionList();
            $this->assertSame('2 handler(s) failed to process a record.', $e->getMessage());
            $this->assertSame('exception from foo', $exceptionList[0]->getMessage());
            $this->assertSame('exception from bar', $exceptionList[1]->getMessage());
        }
    }

    public function testLogException() {
        $mockLogHandler = $this->mockInterface('CM_Log_Handler_HandlerInterface')->newInstance();
        $mockHandleRecord = $mockLogHandler->mockMethod('handleRecord');

        $logger = new CM_Log_Logger(new CM_Log_Context(), [$mockLogHandler]);

        $exception = new Exception('foo');
        $mockHandleRecord->set(function (CM_Log_Record_Exception $record) use ($exception) {
            $recordException = $record->getException();
            $this->assertSame($exception->getMessage(), $recordException->getMessage());
            $this->assertSame($exception->getLine(), $recordException->getLine());
            $this->assertSame($exception->getFile(), $recordException->getFile());
            $this->assertSame('Exception: foo', $record->getMessage());
            $this->assertSame(CM_Log_Logger::ERROR, $record->getLevel());
        });
        $logger->addException($exception);
        $this->assertSame(1, $mockHandleRecord->getCallCount());

        $exception = new CM_Exception('bar');
        $exception->setSeverity(CM_Exception::WARN);
        $mockHandleRecord->set(function (CM_Log_Record_Exception $record) use ($exception) {
            $recordException = $record->getException();
            $this->assertSame($exception->getMessage(), $recordException->getMessage());
            $this->assertSame($exception->getLine(), $recordException->getLine());
            $this->assertSame($exception->getFile(), $recordException->getFile());
            $this->assertSame('CM_Exception: bar', $record->getMessage());
            $this->assertSame(CM_Log_Logger::WARNING, $record->getLevel());
        });
        $logger->addException($exception);
        $this->assertSame(2, $mockHandleRecord->getCallCount());

        $exception = new CM_Exception('foobar');
        $exception->setSeverity(CM_Exception::ERROR);
        $mockHandleRecord->set(function (CM_Log_Record_Exception $record) use ($exception) {
            $recordException = $record->getException();
            $this->assertSame($exception->getMessage(), $recordException->getMessage());
            $this->assertSame($exception->getLine(), $recordException->getLine());
            $this->assertSame($exception->getFile(), $recordException->getFile());
            $this->assertSame('CM_Exception: foobar', $record->getMessage());
            $this->assertSame(CM_Log_Logger::ERROR, $record->getLevel());
        });
        $logger->addException($exception);
        $this->assertSame(3, $mockHandleRecord->getCallCount());

        $exception = new CM_Exception('test');
        $exception->setSeverity(CM_Exception::FATAL);
        $mockHandleRecord->set(function (CM_Log_Record_Exception $record) use ($exception) {
            $recordException = $record->getException();
            $this->assertSame($exception->getMessage(), $recordException->getMessage());
            $this->assertSame($exception->getLine(), $recordException->getLine());
            $this->assertSame($exception->getFile(), $recordException->getFile());
            $this->assertSame('CM_Exception: test', $record->getMessage());
            $this->assertSame(CM_Log_Logger::CRITICAL, $record->getLevel());
        });
        $logger->addException($exception);
        $this->assertSame(4, $mockHandleRecord->getCallCount());
    }

    public function testStaticLogLevelMethods() {
        $this->assertSame('INFO', CM_Log_Logger::getLevelName(CM_Log_Logger::INFO));
        $this->assertNotEmpty(CM_Log_Logger::getLevels());
        $this->assertTrue(CM_Log_Logger::hasLevel(CM_Log_Logger::INFO));
        $this->assertFalse(CM_Log_Logger::hasLevel(666));
    }

    /**
     * @expectedException CM_Exception_Invalid
     * @expectedExceptionMessage is not defined, use one of
     */
    public function testStaticGetLevelNameException() {
        CM_Log_Logger::getLevelName(666);
    }
}
