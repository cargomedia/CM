<?php

class CM_Log_Handler_FluentdTest extends CMTest_TestCase {

    protected function tearDown() {
        CMTest_TH::clearEnv();
    }

    public function testConstructor() {
        $handler = new CM_Log_Handler_Fluentd('localhost', 12223, 'tag', 'appName');
        $this->assertInstanceOf('CM_Log_Handler_Fluentd', $handler);
    }

    public function testFormatting() {
        $level = CM_Log_Logger::DEBUG;
        $message = 'foo';
        $user = CMTest_TH::createUser();
        $httpRequest = CM_Http_Request_Abstract::factory(
            'post',
            '/foo?bar=1&baz=quux',
            ['bar' => 'baz'],
            [
                'http_referrer'   => 'http://bar/baz',
                'http_user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_10)',
                'foo'             => 'quux'
            ]
        );
        $clientId = $httpRequest->getClientId();
        $computerInfo = new CM_Log_Context_ComputerInfo('www.example.com', 'v7.0.1');

        $appContext = new CM_Log_Context_App(['bar' => 'baz', 'baz' => 'quux'], $user);
        $record = new CM_Log_Record($level, $message, new CM_Log_Context($httpRequest, $computerInfo, $appContext));

        $handler = new CM_Log_Handler_Fluentd('localhost', 25555, 'tag', 'appName');
        $formattedRecord = $this->callProtectedMethod($handler, '_formatRecord', [$record]);

        $this->assertSame($message, $formattedRecord['message']);
        $this->assertSame('debug', $formattedRecord['level']);
        $this->assertArrayHasKey('timestamp', $formattedRecord);
        $this->assertSame('www.example.com', $formattedRecord['computerInfo']['fqdn']);
        $this->assertSame('v7.0.1', $formattedRecord['computerInfo']['phpVersion']);
        $this->assertSame('/foo?bar=1&baz=quux', $formattedRecord['request']['uri']);

        $this->assertSame('POST', $formattedRecord['request']['method']);
        $this->assertSame('http://bar/baz', $formattedRecord['request']['referrer']);
        $this->assertSame('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_10)', $formattedRecord['request']['user_agent']);
        $this->assertSame($user->getId(), $formattedRecord['appName']['user']);
        $this->assertSame($clientId, $formattedRecord['appName']['clientId']);
        $this->assertSame('baz', $formattedRecord['appName']['bar']);
        $this->assertSame('quux', $formattedRecord['appName']['baz']);

        $exception = new CM_Exception_Invalid('Bad');
        $recordWithException = new CM_Log_Record_Exception($level, new CM_Log_Context($httpRequest, $computerInfo, $appContext), $exception);
        $formattedRecord = $this->callProtectedMethod($handler, '_formatRecord', [$recordWithException]);

        $this->assertSame($recordWithException->getMessage(), $formattedRecord['message']);
        $this->assertSame('CM_Exception_Invalid', $formattedRecord['exception']['type']);
        $this->assertSame('Bad', $formattedRecord['exception']['message']);
        $this->assertArrayHasKey('stack', $formattedRecord['exception']);
    }
}
