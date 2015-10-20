<?php

class CM_Wowza_ServiceTest extends CMTest_TestCase {

    public function tearDown() {
        CMTest_TH::clearEnv();
    }

    public function testSynchronizeMissingInWowza() {
        $streamChannel = CMTest_TH::createStreamChannel();
        $streamPublish = CMTest_TH::createStreamPublish(null, $streamChannel);
        $streamSubscribe = CMTest_TH::createStreamSubscribe(null, $streamChannel);

        $server = $this->mockClass('CM_Wowza_Server')->newInstanceWithoutConstructor();
        $configuration = $this->mockObject('CM_Wowza_Configuration');
        $configuration->mockMethod('getServers')->set([$server]);
        /** @var CM_Wowza_Configuration $configuration */

        $httpApiClient = $this->mockClass('CM_Wowza_HttpApiClient')->newInstanceWithoutConstructor();
        $httpApiClient->mockMethod('fetchStatus')->set(function (CM_Wowza_Server $passedServer) use ($server) {
            $this->assertSame($server, $passedServer);
            return $this->_generateWowzaData([]);
        });
        /** @var CM_Wowza_HttpApiClient $httpApiClient */

        $wowza = new CM_Wowza_Service($configuration, $httpApiClient);
        $wowza->synchronize();

        $this->assertEquals($streamChannel, CM_Model_StreamChannel_Abstract::findByKeyAndAdapter($streamChannel->getKey(), $wowza->getType()));
        $this->assertEquals($streamPublish, CM_Model_Stream_Publish::findByKeyAndChannel($streamPublish->getKey(), $streamChannel));
        $this->assertEquals($streamSubscribe, CM_Model_Stream_Subscribe::findByKeyAndChannel($streamSubscribe->getKey(), $streamChannel));

        CMTest_TH::timeForward(5);
        $wowza->synchronize();
        $this->assertNull(CM_Model_StreamChannel_Abstract::findByKeyAndAdapter($streamChannel->getKey(), $wowza->getType()));
        $this->assertNull(CM_Model_Stream_Publish::findByKeyAndChannel($streamPublish->getKey(), $streamChannel));
        $this->assertNull(CM_Model_Stream_Subscribe::findByKeyAndChannel($streamSubscribe->getKey(), $streamChannel));
    }

    public function testSynchronizeMissingInPhp() {
        $streamChannel = CMTest_TH::createStreamChannel();
        $streamPublish = CMTest_TH::createStreamPublish(null, $streamChannel);
        $streamPublishKey = $streamPublish->getKey();
        $streamSubscribe = CMTest_TH::createStreamSubscribe(null, $streamChannel);
        $streamSubscribeKey = $streamSubscribe->getKey();

        $server = $this->mockClass('CM_Wowza_Server')->newInstanceWithoutConstructor();
        $configuration = $this->mockObject('CM_Wowza_Configuration');
        $configuration->mockMethod('getServers')->set([$server]);
        /** @var CM_Wowza_Configuration $configuration */

        $status = $this->_generateWowzaData([$streamChannel]);
        $httpApiClient = $this->mockClass('CM_Wowza_HttpApiClient')->newInstanceWithoutConstructor();
        $httpApiClient->mockMethod('fetchStatus')->set(function (CM_Wowza_Server $passedServer) use ($server, $status) {
            $this->assertSame($server, $passedServer);
            return $status;
        });
        $stopClientMethod = $httpApiClient->mockMethod('stopClient')
            ->at(0, function ($server, $clientKey) use ($streamPublishKey) {
                $this->assertSame($streamPublishKey, $clientKey);
            })
            ->at(1, function ($server, $clientKey) use ($streamSubscribeKey) {
                $this->assertSame($streamSubscribeKey, $clientKey);
            });
        /** @var CM_Wowza_HttpApiClient $httpApiClient */

        $wowza = new CM_Wowza_Service($configuration, $httpApiClient);
        $wowza->getStreamRepository()->removeStream($streamPublish);
        $wowza->getStreamRepository()->removeStream($streamSubscribe);
        $wowza->synchronize();
        $this->assertSame(2, $stopClientMethod->getCallCount());
    }

    public function testStopStream() {
        $streamChannel = $this->mockClass('CM_Model_StreamChannel_Abstract')->newInstanceWithoutConstructor();
        $streamChannel->mockMethod('getServerId')->set(5);
        /** @var CM_Model_StreamChannel_Abstract $streamChannel */

        $stream = $this->mockObject('CM_Model_Stream_Abstract');
        $stream->mockMethod('getStreamChannel')->set($streamChannel);
        $stream->mockMethod('getKey')->set('foo');
        /** @var CM_Model_Stream_Abstract $stream */

        $server = $this->mockClass('CM_Wowza_Server')->newInstanceWithoutConstructor();
        $configuration = $this->mockObject('CM_Wowza_Configuration');
        $configuration->mockMethod('getServer')->set(function ($serverId) use ($server) {
            $this->assertSame(5, $serverId);
            return $server;
        });
        /** @var CM_Wowza_Configuration $configuration */

        $httpClient = $this->mockClass('CM_Wowza_HttpApiClient')->newInstanceWithoutConstructor();
        $stopClientMethod = $httpClient->mockMethod('stopClient')->set(function ($passedServer, $clientKey) use ($server) {
            $this->assertSame('foo', $clientKey);
            $this->assertSame($server, $passedServer);
        });
        /** @var CM_Wowza_HttpApiClient $httpClient */

        $wowza = new CM_Wowza_Service($configuration, $httpClient);
        $this->callProtectedMethod($wowza, '_stopStream', [$stream]);
        $this->assertSame(1, $stopClientMethod->getCallCount());
    }

    /**
     * @param CM_Model_StreamChannel_Media[] $streamChannels
     * @return string
     */
    private function _generateWowzaData(array $streamChannels) {
        $status = array();
        foreach ($streamChannels as $streamChannel) {
            $subscribes = array();
            /** @var CM_Model_Stream_Publish $streamPublish */
            $streamPublish = $streamChannel->getStreamPublish();
            /** @var CM_Model_Stream_Subscribe $streamSubscribe */
            foreach ($streamChannel->getStreamSubscribes() as $streamSubscribe) {
                $session = CMTest_TH::createSession($streamSubscribe->getUser());
                $subscribes[$streamSubscribe->getKey()] = array(
                    'startTimeStamp' => $streamSubscribe->getStart(),
                    'clientId'       => $streamSubscribe->getKey(),
                    'data'           => json_encode(array('sessionId' => $session->getId())),
                );
            }
            $session = CMTest_TH::createSession($streamPublish->getUser());
            $status[$streamChannel->getKey()] = array(
                'startTimeStamp' => $streamPublish->getStart(),
                'clientId'       => $streamPublish->getKey(),
                'data'           => json_encode(array('sessionId' => $session->getId(), 'streamChannelType' => $streamChannel->getType())),
                'subscribers'    => $subscribes,
                'thumbnailCount' => 2,
                'wowzaIp'        => ip2long('192.168.0.1'));
        }
        return $status;
    }
}
