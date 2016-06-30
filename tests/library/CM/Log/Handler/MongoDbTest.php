<?php

class CM_Log_Handler_MongoDbTest extends CMTest_TestCase {

    protected function tearDown() {
        CMTest_TH::clearEnv();
    }

    public function testConstructor() {
        $handler = new CM_Log_Handler_MongoDb('foo');
        $this->assertInstanceOf('CM_Log_Handler_MongoDb', $handler);
    }

    public function testFailWithWrongTtl() {
        $collection = 'cm_event_log';
        $mongoClient = $this->getServiceManager()->getMongoDb();
        $mongoClient->createIndex($collection, ['expireAt' => 1], ['expireAfterSeconds' => 0]);
        $exception = $this->catchException(function () use ($collection) {
            new CM_Log_Handler_MongoDb($collection, -10);
        });

        $this->assertInstanceOf('CM_Exception_Invalid', $exception);
        $this->assertSame('TTL should be positive value', $exception->getMessage());
    }

    public function testWriting() {
        $collection = 'cm_event_log';
        $level = CM_Log_Logger::DEBUG;
        $message = 'foo';
        $ttl = 30;
        $user = CMTest_TH::createUser();
        $httpRequest = CM_Http_Request_Abstract::factory('post', '/foo?bar=1&baz=quux', ['bar' => 'baz'], ['foo' => 'quux'], '{"bar":"2", "quux":"baz"}');
        $clientId = $httpRequest->getClientId();
        $computerInfo = new CM_Log_Context_ComputerInfo('www.example.com', 'v7.0.1');

        $mongoClient = $this->getServiceManager()->getMongoDb();
        $this->assertSame(0, $mongoClient->count($collection));

        $mongoClient->createIndex($collection, ['expireAt' => 1], ['expireAfterSeconds' => 0]);
        $recordContext = new CM_Log_Context();
        $recordContext->setExtra(['bar' => ['baz' => 'quux']]);
        $recordContext->setUser($user);
        $recordContext->setHttpRequest($httpRequest);
        $recordContext->setComputerInfo($computerInfo);
        $record = new CM_Log_Record($level, $message, $recordContext);

        $handler = new CM_Log_Handler_MongoDb($collection, $ttl, ['w' => 0], $level);
        $this->callProtectedMethod($handler, '_writeRecord', [$record]);
        $this->assertSame(1, $mongoClient->count($collection));

        $savedRecord = $mongoClient->findOne($collection);

        $this->assertSame($level, $savedRecord['level']);
        $this->assertSame($message, $savedRecord['message']);

        /** @var MongoDate $createdAt */
        $createdAt = $savedRecord['createdAt'];
        /** @var MongoDate $expireAt */
        $expireAt = $savedRecord['expireAt'];

        $this->assertInstanceOf('MongoDate', $createdAt);
        $this->assertInstanceOf('MongoDate', $expireAt);

        $this->assertSame($ttl, $expireAt->sec - $createdAt->sec);

        $context = $savedRecord['context'];
        $this->assertSame(['id' => $user->getId(), 'name' => $user->getDisplayName()], $context['user']);
        $this->assertSame('POST', $context['httpRequest']['method']);
        $this->assertSame('/foo?bar=1&baz=quux', $context['httpRequest']['uri']);
        $this->assertSame(['bar' => '2', 'baz' => 'quux', 'quux' => 'baz'], $context['httpRequest']['query']);
        $this->assertSame(['bar' => 'baz'], $context['httpRequest']['headers']);
        $this->assertSame(['foo' => 'quux'], $context['httpRequest']['server']);
        $this->assertSame($clientId, $context['httpRequest']['clientId']);
        $this->assertSame('www.example.com', $context['computerInfo']['fqdn']);
        $this->assertSame('v7.0.1', $context['computerInfo']['phpVersion']);
        $this->assertSame(['bar' => ['baz' => 'quux']], $context['extra']);
        $this->assertSame('{"bar":"2", "quux":"baz"}', $context['httpRequest']['body']);
    }

    public function testSanitizeRecord() {
        $handler = new CM_Log_Handler_MongoDb('foo');
        $record = [
            'foo'  => [
                'baz' => 'quux',
                'bar' => pack("H*", 'c32e')
            ],
            'foo2' => 2,
        ];
        $sanitizedRecord = $this->callProtectedMethod($handler, '_sanitizeRecord', [$record]);

        $this->assertSame([
            'foo'     => [
                'baz' => 'quux',
                'bar' => 'SANITIZED: ?.',
            ],
            'foo2'    => 2,
            'context' => [
                'extra' => [
                    'sanitized' => true,
                    'bar-UTF'   => 'c32e',
                ]
            ],
        ], $sanitizedRecord);
    }
}
