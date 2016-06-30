<?php

class CM_MessageStream_Adapter_SocketRedisTest extends CMTest_TestCase {

    public function tearDown() {
        CMTest_TH::clearEnv();
    }

    public function testGetOptions() {
        $adapter = new CM_MessageStream_Adapter_SocketRedis(array(
            array('httpHost' => 'foo', 'httpPort' => 8085, 'sockjsUrls' => array('http://stream:8090'))
        ));
        $options = $adapter->getOptions();
        $this->assertArrayHasKey('sockjsUrl', $options);
        $this->assertSame('http://stream:8090', $options['sockjsUrl']);
    }

    public function testOnRedisMessageSubscribe() {
        $adapter = new CM_MessageStream_Adapter_SocketRedis([]);
        $message = array('type' => 'subscribe',
                         'data' => array('channel' => 'foo:' . CM_Model_StreamChannel_Message::getTypeStatic(), 'clientKey' => 'bar',
                                         'data'    => array()));
        $adapter->onRedisMessage(json_encode($message));
        $timeStarted = time();

        $streamChannel = CM_Model_StreamChannel_Message::findByKeyAndAdapter('foo', $adapter->getType());
        $this->assertNotNull($streamChannel);
        $streamChannels = new CM_Paging_StreamChannel_AdapterType($adapter->getType());
        $this->assertSame(1, $streamChannels->getCount());
        $streamSubscribe = CM_Model_Stream_Subscribe::findByKeyAndChannel('bar', $streamChannel);
        $this->assertNotNull($streamSubscribe);
        $this->assertSame(1, $streamChannel->getStreamSubscribes()->getCount());
        $this->assertSameTime($timeStarted, $streamSubscribe->getStart());
        $this->assertNull($streamSubscribe->getUser());

        CMTest_TH::timeForward(CM_MessageStream_Adapter_SocketRedis::SYNCHRONIZE_DELAY);
        $adapter->onRedisMessage(json_encode($message));
        $streamChannels = new CM_Paging_StreamChannel_AdapterType($adapter->getType());
        $this->assertSame(1, $streamChannels->getCount());
        $this->assertSame(1, $streamChannel->getStreamSubscribes()->getCount());
        CMTest_TH::reinstantiateModel($streamSubscribe);
        $this->assertSameTime($timeStarted, $streamSubscribe->getStart());
    }

    public function testOnRedisMessageSubscribeUser() {
        $adapter = new CM_MessageStream_Adapter_SocketRedis([]);
        $user = CMTest_TH::createUser();
        $session = new CM_Session();
        $session->setUser($user);
        $session->write();
        $message = array('type' => 'subscribe',
                         'data' => array('channel' => 'foo:' . CM_Model_StreamChannel_Message::getTypeStatic(), 'clientKey' => 'bar',
                                         'data'    => array('sessionId' => $session->getId())));
        $adapter->onRedisMessage(json_encode($message));

        $streamChannel = CM_Model_StreamChannel_Message::findByKeyAndAdapter('foo', $adapter->getType());
        $streamSubscribe = CM_Model_Stream_Subscribe::findByKeyAndChannel('bar', $streamChannel);
        $this->assertEquals($user, $streamSubscribe->getUser());
    }

    public function testOnRedisMessageSubscribeSessionInvalid() {
        $adapter = new CM_MessageStream_Adapter_SocketRedis([]);
        $message = array('type' => 'subscribe',
                         'data' => array('channel' => 'foo:' . CM_Model_StreamChannel_Message::getTypeStatic(), 'clientKey' => 'bar',
                                         'data'    => array('sessionId' => 'foo')));
        $adapter->onRedisMessage(json_encode($message));

        $streamChannel = CM_Model_StreamChannel_Message::findByKeyAndAdapter('foo', $adapter->getType());
        $streamSubscribe = CM_Model_Stream_Subscribe::findByKeyAndChannel('bar', $streamChannel);
        $this->assertNull($streamSubscribe->getUser());
    }

    public function testOnRedisMessageUnsubscribe() {
        $adapter = new CM_MessageStream_Adapter_SocketRedis([]);
        $streamChannel = CM_Model_StreamChannel_Message::createStatic(array('key' => 'foo', 'adapterType' => $adapter->getType()));
        CM_Model_Stream_Subscribe::createStatic(array('key' => 'foo', 'streamChannel' => $streamChannel, 'start' => time()));
        CM_Model_Stream_Subscribe::createStatic(array('key' => 'bar', 'streamChannel' => $streamChannel, 'start' => time()));

        $message = array('type' => 'unsubscribe',
                         'data' => array('channel' => 'foo:' . CM_Model_StreamChannel_Message::getTypeStatic(), 'clientKey' => 'foo'));
        $adapter->onRedisMessage(json_encode($message));
        $streamChannel = CM_Model_StreamChannel_Message::findByKeyAndAdapter('foo', $adapter->getType());
        $this->assertNotNull($streamChannel);
        $streamSubscribe = CM_Model_Stream_Subscribe::findByKeyAndChannel('foo', $streamChannel);
        $this->assertNull($streamSubscribe);

        $message = array('type' => 'unsubscribe',
                         'data' => array('channel' => 'foo:' . CM_Model_StreamChannel_Message::getTypeStatic(), 'clientKey' => 'bar'));
        $adapter->onRedisMessage(json_encode($message));
        $streamChannel = CM_Model_StreamChannel_Message::findByKeyAndAdapter('foo', $adapter->getType());
        $this->assertNull($streamChannel);
    }

    public function testSynchronize() {
        $jsTime = (time() - CM_MessageStream_Adapter_SocketRedis::SYNCHRONIZE_DELAY - 1) * 1000;
        for ($i = 0; $i < 2; $i++) {
            $status = array(
                'channel-foo:' . CM_Model_StreamChannel_Message::getTypeStatic() => array('subscribers' => array(
                    'foo' => array('clientKey' => 'foo', 'subscribeStamp' => $jsTime, 'data' => array()),
                    'bar' => array('clientKey' => 'bar', 'subscribeStamp' => $jsTime, 'data' => array()),
                )),
                'channel-bar:' . CM_Model_StreamChannel_Message::getTypeStatic() => array('subscribers' => array(
                    'foo' => array('clientKey' => 'foo', 'subscribeStamp' => $jsTime, 'data' => array()),
                    'bar' => array('clientKey' => 'bar', 'subscribeStamp' => $jsTime, 'data' => array()),
                )),
            );
            $this->_testSynchronize($status);
        }
        $status = array(
            'channel-foo:' . CM_Model_StreamChannel_Message::getTypeStatic() => array('subscribers' => array(
                'foo' => array('clientKey' => 'foo', 'subscribeStamp' => $jsTime, 'data' => array()),
            ))
        );
        $this->_testSynchronize($status);
    }

    public function testSynchronizeIgnoreNewSubscribers() {
        $jsTime = time() * 1000;
        $status = array(
            'channel-foo:' . CM_Model_StreamChannel_Message::getTypeStatic() => array('subscribers' => array(
                'foo' => array('clientKey' => 'foo', 'subscribeStamp' => $jsTime, 'data' => array()),
            ))
        );
        $adapter = $this->mockClass('CM_MessageStream_Adapter_SocketRedis')->newInstanceWithoutConstructor();
        $adapter->mockMethod('_fetchStatus')->set($status);
        /** @var $adapter CM_MessageStream_Adapter_SocketRedis */
        $adapter->synchronize();

        $this->assertNull(CM_Model_StreamChannel_Message::findByKeyAndAdapter('channel-foo', $adapter->getType()));
        $subscribes = new CM_Paging_StreamSubscribe_AdapterType($adapter->getType());
        $this->assertSame(0, $subscribes->getCount());
    }

    public function testSynchronizeInvalidType() {
        $jsTime = (time() - CM_MessageStream_Adapter_SocketRedis::SYNCHRONIZE_DELAY - 1) * 1000;
        $status = array(
            'channel-foo:invalid-type' => array('subscribers' => array(
                'foo' => array('clientKey' => 'foo', 'subscribeStamp' => $jsTime, 'data' => array()),
            ))
        );

        $adapter = $this->mockClass('CM_MessageStream_Adapter_SocketRedis')->newInstanceWithoutConstructor();
        $adapter->mockMethod('_fetchStatus')->set($status);
        $handleExceptionMethod = $adapter->mockMethod('_handleException')->set(function (Exception $exception) {
            $this->assertSame('Type `0` not configured for class `CM_Model_StreamChannel_Message`.', $exception->getMessage());
        });
        /** @var $adapter CM_MessageStream_Adapter_SocketRedis */
        $adapter->synchronize();
        $this->assertSame(1, $handleExceptionMethod->getCallCount());
    }

    /**
     * @param array $status
     */
    private function _testSynchronize($status) {
        $adapter = $this->getMockBuilder('CM_MessageStream_Adapter_SocketRedis')->disableOriginalConstructor()->setMethods(array('_fetchStatus'))->getMock();
        $adapter->expects($this->any())->method('_fetchStatus')->will($this->returnValue($status));
        /** @var $adapter CM_MessageStream_Adapter_SocketRedis */
        $adapter->synchronize();

        $streamChannels = new CM_Paging_StreamChannel_AdapterType($adapter->getType());
        $this->assertSame(count($status), $streamChannels->getCount());
        /** @var $streamChannel CM_Model_StreamChannel_Message */
        foreach ($streamChannels as $streamChannel) {
            $this->assertInstanceOf('CM_Model_StreamChannel_Message', $streamChannel);
            $channel = $streamChannel->getKey() . ':' . CM_Model_StreamChannel_Message::getTypeStatic();
            $this->assertSame(count($status[$channel]['subscribers']), $streamChannel->getStreamSubscribes()->getCount());
        }
        foreach ($status as $channel => $channelData) {
            list ($channelKey, $channelType) = explode(':', $channel);
            $streamChannel = CM_Model_StreamChannel_Message::findByKeyAndAdapter($channelKey, $adapter->getType());
            $this->assertInstanceOf('CM_Model_StreamChannel_Message', $streamChannel);
            foreach ($channelData['subscribers'] as $clientKey => $subscriberData) {
                $subscribe = CM_Model_Stream_Subscribe::findByKeyAndChannel($clientKey, $streamChannel);
                $this->assertInstanceOf('CM_Model_Stream_Subscribe', $subscribe);
                $this->assertSameTime(time() - CM_MessageStream_Adapter_SocketRedis::SYNCHRONIZE_DELAY - 1, $subscribe->getStart());
            }
        }
    }
}
