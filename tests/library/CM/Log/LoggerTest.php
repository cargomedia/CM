<?php

class CM_Log_LoggerTest extends CMTest_TestCase {

    public function testAddRecord() {
        $mockLogHandler = $this->mockInterface('CM_Log_Handler_HandlerInterface')->newInstance();
        $mockHandleRecord = $mockLogHandler->mockMethod('handleRecord');

        $logger = new CM_Log_Logger([$mockLogHandler]);

        $expectedRecord = new CM_Log_Record(CM_Log_Logger::INFO, 'foo', new CM_Log_Context());
        $mockHandleRecord->set(function (CM_Log_Record $record) use ($expectedRecord) {
            $this->assertSame($expectedRecord, $record);
        });

        $logger->addRecord($expectedRecord);
        $this->assertSame(1, $mockHandleRecord->getCallCount());
    }

    public function testLoggingWithContext() {
        $mockLogHandler = $this->mockInterface('CM_Log_Handler_HandlerInterface')->newInstance();
        $mockHandleRecord = $mockLogHandler->mockMethod('handleRecord');

        // without any context
        $logger = new CM_Log_Logger([$mockLogHandler]);
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
        $computerInfo = new CM_Log_Context_ComputerInfo();
        $globalContext = new CM_Log_Context(null, null, $computerInfo);
        $logger = new CM_Log_Logger([$mockLogHandler], [], $globalContext);

        $mockHandleRecord->set(function (CM_Log_Record $record) use ($computerInfo) {
            $context = $record->getContext();
            $this->assertSame($computerInfo, $context->getComputerInfo());
            $this->assertNull($context->getUser());
            $this->assertNull($context->getHttpRequest());
            $this->assertSame([], $context->getExtra());
        });
        $logger->addMessage('foo', CM_Log_Logger::INFO);
        $this->assertSame(2, $mockHandleRecord->getCallCount());

        // with a global context + log context
        $computerInfo = new CM_Log_Context_ComputerInfo();
        $globalContext = new CM_Log_Context(null, null, $computerInfo);
        $logger = new CM_Log_Logger([$mockLogHandler], [], $globalContext);

        $mockHandleRecord->set(function (CM_Log_Record $record) use ($computerInfo) {
            $context = $record->getContext();
            $this->assertSame($computerInfo, $context->getComputerInfo());
            $this->assertNull($context->getUser());
            $this->assertNull($context->getHttpRequest());
            $this->assertSame(['foo' => 10], $context->getExtra());
        });
        $logger->addMessage('foo', CM_Log_Logger::INFO, new CM_Log_Context(null, null, null, ['foo' => 10]));
        $this->assertSame(3, $mockHandleRecord->getCallCount());
    }

    public function testLoggerFallbacks() {
        /** @var CM_Log_Handler_Abstract|Mocka\AbstractClassTrait $mockHandlerFoo */
        $mockHandlerFoo = $this->mockObject('CM_Log_Handler_Abstract');
        /** @var CM_Log_Handler_Abstract|Mocka\FunctionMock $mockHandleRecordFoo */
        $mockHandleRecordFoo = $mockHandlerFoo->mockMethod('handleRecord');

        /** @var CM_Log_Handler_Abstract|Mocka\AbstractClassTrait $mockHandlerBar */
        $mockHandlerBar = $this->mockObject('CM_Log_Handler_Abstract');
        /** @var CM_Log_Handler_Abstract|Mocka\FunctionMock $mockHandleRecordBar */
        $mockHandleRecordBar = $mockHandlerBar->mockMethod('handleRecord');

        /** @var CM_Log_Handler_Abstract|Mocka\AbstractClassTrait $mockHandlerFallbackFoo */
        $mockHandlerFallbackFoo = $this->mockObject('CM_Log_Handler_Abstract');
        /** @var CM_Log_Handler_Abstract|Mocka\FunctionMock $mockHandleRecordFallbackFoo */
        $mockHandleRecordFallbackFoo = $mockHandlerFallbackFoo->mockMethod('handleRecord');

        /** @var CM_Log_Handler_Abstract|Mocka\AbstractClassTrait $mockHandlerFallbackBar */
        $mockHandlerFallbackBar = $this->mockObject('CM_Log_Handler_Abstract');
        /** @var CM_Log_Handler_Abstract|Mocka\FunctionMock $mockHandleRecordFallbackBar */
        $mockHandleRecordFallbackBar = $mockHandlerFallbackBar->mockMethod('handleRecord');

        $logger = new CM_Log_Logger([$mockHandlerFoo, $mockHandlerBar], [$mockHandlerFallbackFoo, $mockHandlerFallbackBar]);

        $mockHandleRecordFoo->set(true);
        $mockHandleRecordBar->set(true);
        $mockHandleRecordFallbackFoo->set(true);
        $mockHandleRecordFallbackBar->set(true);
        $logger->info('foo');
        $this->assertSame(1, $mockHandleRecordFoo->getCallCount());
        $this->assertSame(1, $mockHandleRecordBar->getCallCount());
        $this->assertSame(0, $mockHandleRecordFallbackFoo->getCallCount());
        $this->assertSame(0, $mockHandleRecordFallbackBar->getCallCount());

        $mockHandleRecordFoo->set(false);
        $mockHandleRecordBar->set(false);
        $mockHandleRecordFallbackFoo->set(true);
        $mockHandleRecordFallbackBar->set(true);
        $logger->info('foo');
        $this->assertSame(2, $mockHandleRecordFoo->getCallCount());
        $this->assertSame(2, $mockHandleRecordBar->getCallCount());
        $this->assertSame(1, $mockHandleRecordFallbackFoo->getCallCount());
        $this->assertSame(0, $mockHandleRecordFallbackBar->getCallCount());

        $mockHandleRecordFoo->set(false);
        $mockHandleRecordBar->set(false);
        $mockHandleRecordFallbackFoo->set(false);
        $mockHandleRecordFallbackBar->set(true);
        $logger->info('foo');
        $this->assertSame(3, $mockHandleRecordFoo->getCallCount());
        $this->assertSame(3, $mockHandleRecordBar->getCallCount());
        $this->assertSame(2, $mockHandleRecordFallbackFoo->getCallCount());
        $this->assertSame(1, $mockHandleRecordFallbackBar->getCallCount());

    }

    public function testLogHelpers() {
        $mockLogHandler = $this->mockInterface('CM_Log_Handler_HandlerInterface')->newInstance();
        $mockHandleRecord = $mockLogHandler->mockMethod('handleRecord');

        $logger = new CM_Log_Logger([$mockLogHandler]);

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

    public function testLogException() {
        $mockLogHandler = $this->mockInterface('CM_Log_Handler_HandlerInterface')->newInstance();
        $mockHandleRecord = $mockLogHandler->mockMethod('handleRecord');

        $logger = new CM_Log_Logger([$mockLogHandler]);

        $exception = new Exception('foo');
        $mockHandleRecord->set(function (CM_Log_Record_Exception $record) use ($exception) {
            $contextException = $record->getException();
            $this->assertSame($exception->getMessage(), $contextException->getMessage());
            $this->assertSame($exception->getLine(), $contextException->getLine());
            $this->assertSame($exception->getFile(), $contextException->getFile());
            $this->assertSame('foo', $record->getMessage());
            $this->assertSame(CM_Log_Logger::ERROR, $record->getLevel());
        });
        $logger->addException($exception);
        $this->assertSame(1, $mockHandleRecord->getCallCount());

        $exception = new CM_Exception('bar');
        $exception->setSeverity(CM_Exception::WARN);
        $mockHandleRecord->set(function (CM_Log_Record_Exception $record) use ($exception) {
            $contextException = $record->getException();
            $this->assertSame($exception->getMessage(), $contextException->getMessage());
            $this->assertSame($exception->getLine(), $contextException->getLine());
            $this->assertSame($exception->getFile(), $contextException->getFile());
            $this->assertSame('bar', $record->getMessage());
            $this->assertSame(CM_Log_Logger::WARNING, $record->getLevel());
        });
        $logger->addException($exception);
        $this->assertSame(2, $mockHandleRecord->getCallCount());

        $exception = new CM_Exception('foobar');
        $exception->setSeverity(CM_Exception::ERROR);
        $mockHandleRecord->set(function (CM_Log_Record_Exception $record) use ($exception) {
            $contextException = $record->getException();
            $this->assertSame($exception->getMessage(), $contextException->getMessage());
            $this->assertSame($exception->getLine(), $contextException->getLine());
            $this->assertSame($exception->getFile(), $contextException->getFile());
            $this->assertSame('foobar', $record->getMessage());
            $this->assertSame(CM_Log_Logger::ERROR, $record->getLevel());
        });
        $logger->addException($exception);
        $this->assertSame(3, $mockHandleRecord->getCallCount());

        $exception = new CM_Exception('barfoo');
        $exception->setSeverity(CM_Exception::FATAL);
        $mockHandleRecord->set(function (CM_Log_Record_Exception $record) use ($exception) {
            $contextException = $record->getException();
            $this->assertSame($exception->getMessage(), $contextException->getMessage());
            $this->assertSame($exception->getLine(), $contextException->getLine());
            $this->assertSame($exception->getFile(), $contextException->getFile());
            $this->assertSame('barfoo', $record->getMessage());
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