<?php

class CM_ExceptionHandling_Handler_AbstractTest extends CMTest_TestCase {

    public function testHandleException() {
        $expectedException = new Exception('foo');
        $handlerMock = $this->mockClass('CM_Log_Handler_Abstract')->newInstanceWithoutConstructor();

        /** @var CM_Log_Logger|\Mocka\ClassMock $logger */
        $logger = $this->mockClass('CM_Log_Logger')->newInstance([
            new CM_Log_Context(),
            new CM_Log_Handler_Layered([
                new CM_Log_Handler_Layered_Layer([$handlerMock])
            ])
        ]);
        $methodAddMessage = $logger->mockMethod('addMessage');
        $methodAddMessage->set(
            function ($message, $level, CM_Log_Context $context) use ($expectedException) {
                $this->assertSame('Application error', $message);
                $this->assertSame(CM_Log_Logger::ERROR, $level);
                $this->assertEquals($expectedException, $context->getException());
            }
        );

        /** @var CM_Service_Manager|\Mocka\ClassMock $serviceManager */
        $serviceManager = $this->mockClass('CM_Service_Manager')->newInstance();
        $serviceManager->mockMethod('getLogger')->set($logger);

        /** @var CM_ExceptionHandling_Handler_Abstract $exceptionHandler */
        $exceptionHandler = $this->mockClass('CM_ExceptionHandling_Handler_Abstract')->newInstance();
        $exceptionHandler->setServiceManager($serviceManager);
        $exceptionHandler->handleException($expectedException);
        $this->assertSame(1, $methodAddMessage->getCallCount());
    }

    public function testPrintException() {
        $errorException = new CM_Exception();
        $nativeException = new Exception();
        $fatalException = new CM_Exception(null, CM_Exception::FATAL);

        $exceptionHandler = $this->mockClass('CM_ExceptionHandling_Handler_Abstract')->newInstanceWithoutConstructor();
        $exceptionHandler->mockMethod('_logException')->set(function () {
        });

        $printExceptionMock = $exceptionHandler->mockMethod('_printException')
            ->at(0, function (Exception $ex) use ($errorException) {
                $this->assertEquals($errorException, $ex);
            })
            ->at(1, function (Exception $ex) use ($nativeException) {
                $this->assertEquals($nativeException, $ex);
            })
            ->at(2, function (Exception $ex) use ($fatalException) {
                $this->assertEquals($fatalException, $ex);
            });

        /** @var CM_ExceptionHandling_Handler_Abstract $exceptionHandler */
        $exceptionHandler->handleException($errorException);
        $exceptionHandler->handleException($nativeException);
        $exceptionHandler->handleException($fatalException);

        $this->assertSame(3, $printExceptionMock->getCallCount());
    }

    public function testPrintExceptionPrintSeverity() {
        $errorException = new CM_Exception();
        $nativeException = new Exception();
        $fatalException = new CM_Exception(null, CM_Exception::FATAL);

        /** @var CM_ExceptionHandling_Handler_Abstract|\Mocka\AbstractClassTrait $exceptionHandler */
        $exceptionHandler = $this->mockClass('CM_ExceptionHandling_Handler_Abstract')->newInstanceWithoutConstructor();

        $logExceptionMock = $exceptionHandler->mockMethod('_logException');
        $logExceptionMock->set(function () {
        });

        $printExceptionMock = $exceptionHandler->mockMethod('_printException');
        $printExceptionMock
            ->at(0, function (Exception $ex) use ($nativeException) {
                $this->assertEquals($nativeException, $ex);
            })
            ->at(1, function (Exception $ex) use ($fatalException) {
                $this->assertEquals($fatalException, $ex);
            });

        $exceptionHandler->setPrintSeverityMin(CM_Exception::FATAL);

        $exceptionHandler->handleException($errorException);
        $exceptionHandler->handleException($nativeException);
        $exceptionHandler->handleException($fatalException);

        $this->assertSame(2, $printExceptionMock->getCallCount());
        $this->assertSame(3, $logExceptionMock->getCallCount());
    }
}
