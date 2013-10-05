<?php

class CM_ExceptionHandling_HandlerTest extends CMTest_TestCase {

	public function testLogException() {
		$log = $this->getMockBuilder('CM_Paging_Log_Error')->setMethods(array('add'))->disableOriginalConstructor()->getMock();
		$log->expects($this->once())->method('add')->will($this->returnValue(null));

		$exception = $this->getMockBuilder('CM_Exception')->setMethods(array('getLog'))->disableOriginalConstructor()->getMock();
		$exception->expects($this->any())->method('getLog')->will($this->returnValue($log));

		$method = CMTest_TH::getProtectedMethod('CM_ExceptionHandling_Handler', '_logException');
		$exceptionHandler = new CM_ExceptionHandling_Handler();
		$method->invoke($exceptionHandler, $exception);
	}

	public function testLogExceptionFileLog() {
		$log = $this->getMockBuilder('CM_Paging_Log_Error')->setMethods(array('add'))->disableOriginalConstructor()->getMock();
		$log->expects($this->any())->method('add')->will($this->throwException(new Exception('foo')));

		$exception = $this->getMockBuilder('CM_Exception')->setMethods(array('getLog'))->disableOriginalConstructor()->getMock();
		$exception->expects($this->any())->method('getLog')->will($this->returnValue($log));

		CM_Util::mkDir(DIR_DATA_LOG);
		file_put_contents(DIR_DATA_LOG . 'error.log', '');

		$method = CMTest_TH::getProtectedMethod('CM_ExceptionHandling_Handler', '_logException');
		$exceptionHandler = new CM_ExceptionHandling_Handler();
		$method->invoke($exceptionHandler, $exception);

		$logContents = file_get_contents(DIR_DATA_LOG . 'error.log');
		$this->assertNotEmpty($logContents);
		$this->assertContains('### Cannot log error: ', $logContents);
		$this->assertContains('### Original Exception: ', $logContents);
	}

	public function testPrintException() {
		$exception = new CM_Exception('foo');

		$formatter = $this->getMockBuilder('CM_ExceptionHandling_Formatter_Abstract')->setMethods(array('formatException'))->getMockForAbstractClass();
		$formatter->expects($this->once())->method('formatException')->with($exception)->will($this->returnValue('bar'));

		$output = $this->getMockBuilder('CM_OutputStream_Abstract')->setMethods(array('writeln'))->getMockForAbstractClass();
		$output->expects($this->once())->method('writeln')->with('bar');

		$method = CMTest_TH::getProtectedMethod('CM_ExceptionHandling_Handler', '_printException');
		$exceptionHandler = new CM_ExceptionHandling_Handler();
		$method->invoke($exceptionHandler, $exception, $output, $formatter);
	}

	/**
	 * @expectedException ErrorException
	 * @expectedExceptionMessage E_USER_ERROR: Raw error message
	 */
	public function testHandleErrorRaw() {
		trigger_error('Raw error message', E_USER_ERROR);
	}
}
