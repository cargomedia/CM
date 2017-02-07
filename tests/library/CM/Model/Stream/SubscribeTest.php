<?php

class CM_Model_Stream_SubscribeTest extends CMTest_TestCase {

    public function tearDown() {
        CMTest_TH::clearEnv();
    }

    public function testConstructor() {
        $id = CM_Model_Stream_Subscribe::createStatic(array('user'          => CMTest_TH::createUser(), 'start' => time(),
                                                            'streamChannel' => CMTest_TH::createStreamChannel(), 'key' => '13215231_1'))->getId();
        $streamSubscribe = new CM_Model_Stream_Subscribe($id);
        $this->assertGreaterThan(0, $streamSubscribe->getId());
        try {
            new CM_Model_Stream_Subscribe(22467);
            $this->fail('Can instantiate nonexistent VideoStream_Subscribe');
        } catch (CM_Exception_Nonexistent $ex) {
            $this->assertTrue(true);
        }
    }

    public function testDuplicateKeys() {
        $data = array('user'          => CMTest_TH::createUser(), 'start' => time(),
                      'streamChannel' => CMTest_TH::createStreamChannel(), 'key' => '13215231_1');
        CM_Model_Stream_Subscribe::createStatic($data);
        try {
            CM_Model_Stream_Subscribe::createStatic($data);
            $this->fail('Should not be able to create duplicate key instance');
        } catch (CM_Exception $e) {
            $this->assertContains('Duplicate entry', $e->getMetaInfo()['originalExceptionMessage']);
        }
        $data['streamChannel'] = CMTest_TH::createStreamChannel();
        CM_Model_Stream_Subscribe::createStatic($data);
    }

    public function testSetAllowedUntil() {
        $videoStreamSubscribe = CMTest_TH::createStreamSubscribe(CMTest_TH::createUser());
        $videoStreamSubscribe->setAllowedUntil(234234);
        $this->assertSame(234234, $videoStreamSubscribe->getAllowedUntil());
        $videoStreamSubscribe->setAllowedUntil(2342367);
        $this->assertSame(2342367, $videoStreamSubscribe->getAllowedUntil());
    }

    public function testCreate() {
        $user = CMTest_TH::createUser();
        $streamChannel = CMTest_TH::createStreamChannel();
        $this->assertEquals(0, $streamChannel->getStreamSubscribes()->getCount());
        $subscriber = CM_Model_Stream_Subscribe::createStatic(array('user'          => $user, 'start' => 123123,
                                                                    'streamChannel' => $streamChannel, 'key' => '123123_2'));
        $this->assertEquals($user, $subscriber->getUser());
        $this->assertSame(123123, $subscriber->getStart());
        $this->assertEquals($streamChannel, $subscriber->getStreamChannel());
        $this->assertSame('123123_2', $subscriber->getKey());
        $this->assertNull($subscriber->getAllowedUntil());
        $this->assertEquals(1, $streamChannel->getStreamSubscribes()->getCount());
    }

    public function testCreateDisallowInterface() {
        $streamChannel = CMTest_TH::createStreamChannel();
        $viewer = CMTest_TH::createUser();
        /** @var CM_Site_Abstract|\Mocka\AbstractClassTrait $streamChannel */
        $streamChannel = $this->mockClass(get_class($streamChannel), ['CM_StreamChannel_DisallowInterface'])->newInstance([$streamChannel->getId()]);
        $streamChannel->mockMethod('isValid')->set(true);
        $streamChannel->mockMethod('canSubscribe')->set(function (CM_Model_User $user = null, $allowedUntil) use ($viewer) {
            $this->assertEquals($viewer, $user);
            return $allowedUntil + 100;
        });
        $subscriber = CMTest_TH::createStreamSubscribe($viewer, $streamChannel);
        $this->assertSame(time() + 100, $subscriber->getAllowedUntil());

        // not allowed
        $streamChannel->mockMethod('canSubscribe')->set(function (CM_Model_User $user = null, $allowedUntil) use ($viewer) {
            return $allowedUntil;
        });
        $exception = $this->catchException(function () use ($streamChannel, $viewer) {
            CMTest_TH::createStreamSubscribe($viewer, $streamChannel);
        });
        $this->assertInstanceOf('CM_Exception_NotAllowed', $exception);
        $this->assertSame('Not allowed to subscribe', $exception->getMessage());

        // streamchannel invalid
        $streamChannel->mockMethod('isValid')->set(false);
        $exception = $this->catchException(function () use ($streamChannel, $viewer) {
            CMTest_TH::createStreamSubscribe($viewer, $streamChannel);
        });
        $this->assertInstanceOf('CM_Exception_Invalid', $exception);
        $this->assertSame('Stream channel not valid', $exception->getMessage());
    }

    public function testCreateWithoutUser() {
        $streamChannel = CMTest_TH::createStreamChannel();
        $this->assertEquals(0, $streamChannel->getStreamSubscribes()->getCount());
        $videoStream = CM_Model_Stream_Subscribe::createStatic(array('user'          => null, 'start' => 123123,
                                                                     'streamChannel' => $streamChannel, 'key' => '123123_2'));
        $this->assertRow('cm_stream_subscribe', array('id'        => $videoStream->getId(), 'userId' => null, 'start' => 123123,
                                                      'channelId' => $streamChannel->getId(), 'key' => '123123_2'));
        $this->assertEquals(1, $streamChannel->getStreamSubscribes()->getCount());
    }

    public function testGetUser() {
        $streamChannel = CMTest_TH::createStreamChannel();
        /** @var CM_Model_Stream_Subscribe $streamSubscribeWithoutUser */
        $streamSubscribeWithoutUser = CM_Model_Stream_Subscribe::createStatic(array('user'          => null, 'start' => 123123,
                                                                                    'streamChannel' => $streamChannel, 'key' => '123123_2'));
        $this->assertNull($streamSubscribeWithoutUser->getUser());
        $this->assertNull($streamSubscribeWithoutUser->getUserId());

        $user = CMTest_TH::createUser();
        /** @var CM_Model_Stream_Subscribe $streamSubscribe */
        $streamSubscribe = CM_Model_Stream_Subscribe::createStatic(array('user'          => $user, 'start' => 123123,
                                                                         'streamChannel' => $streamChannel, 'key' => '123123_3'));
        $this->assertEquals($user, $streamSubscribe->getUser());
        $this->assertSame($user->getId(), $streamSubscribe->getUserId());
    }

    public function testDelete() {
        $streamChannel = CMTest_TH::createStreamChannel();
        $videoStreamSubscribe = CM_Model_Stream_Subscribe::createStatic(array('user'          => CMTest_TH::createUser(), 'start' => time(),
                                                                              'streamChannel' => $streamChannel, 'key' => '13215231_2'));
        $this->assertEquals(1, $streamChannel->getStreamSubscribes()->getCount());
        $videoStreamSubscribe->delete();
        try {
            new CM_Model_Stream_Subscribe($videoStreamSubscribe->getId());
            $this->fail('videoStream_susbcribe not deleted.');
        } catch (CM_Exception_Nonexistent $ex) {
            $this->assertTrue(true);
        }
        $this->assertEquals(0, $streamChannel->getStreamSubscribes()->getCount());
    }

    public function testFindByKeyAndChannel() {
        $videoStreamSubscribeOrig = CMTest_TH::createStreamSubscribe(CMTest_TH::createUser());
        $videoStreamSubscribe = CM_Model_Stream_Subscribe::findByKeyAndChannel($videoStreamSubscribeOrig->getKey(), $videoStreamSubscribeOrig->getStreamChannel());
        $this->assertEquals($videoStreamSubscribe, $videoStreamSubscribeOrig);
    }

    public function testFindKeyNonexistent() {
        $streamChannel = CMTest_TH::createStreamChannel();
        $videoStreamSubscribe = CM_Model_Stream_Subscribe::findByKeyAndChannel('doesnotexist', $streamChannel);
        $this->assertNull($videoStreamSubscribe);
    }

    public function testGetKey() {
        $user = CMTest_TH::createUser();
        $streamChannel = CMTest_TH::createStreamChannel();
        /** @var CM_Model_Stream_Subscribe $streamSubscribe */
        $streamSubscribe = CM_Model_Stream_Subscribe::createStatic(array('streamChannel' => $streamChannel, 'user' => $user, 'start' => time(),
                                                                         'key'           => 'foo'));
        $this->assertSame('foo', $streamSubscribe->getKey());
    }

    public function testGetKeyMaxLength() {
        $user = CMTest_TH::createUser();
        $streamChannel = CMTest_TH::createStreamChannel();
        /** @var CM_Model_Stream_Subscribe $streamSubscribe */
        $streamSubscribe = CM_Model_Stream_Subscribe::createStatic(array('streamChannel' => $streamChannel, 'user' => $user, 'start' => time(),
                                                                         'key'           => str_repeat('a', 100)));
        $this->assertSame(str_repeat('a', 36), $streamSubscribe->getKey());
    }

    public function testGetChannel() {
        $streamChannel = CMTest_TH::createStreamChannel();
        $streamPublish = CMTest_TH::createStreamSubscribe(CMTest_TH::createUser(), $streamChannel);
        $this->assertEquals($streamChannel, $streamPublish->getStreamChannel());
    }

    public function testUnsetUser() {
        $user = CMTest_TH::createUser();
        $streamChannel = CMTest_TH::createStreamChannel();
        /** @var CM_Model_Stream_Subscribe $streamSubscribe */
        $streamSubscribe = CM_Model_Stream_Subscribe::createStatic(array('streamChannel' => $streamChannel, 'user' => $user, 'start' => time(),
                                                                         'key'           => str_repeat('a', 100)));
        $this->assertEquals($user, $streamSubscribe->getUser());

        $streamSubscribe->unsetUser();
        $this->assertNull($streamSubscribe->getUser());
    }

    public function testDeleteOnUnsubscribe() {
        $streamSubscribe = $this->getMockBuilder('CM_Model_Stream_Subscribe')
            ->setMethods(array('getStreamChannel', 'getId'))->getMock();

        $streamChannel = $this->getMockBuilder('CM_Model_StreamChannel_Media')
            ->setMethods(array('isValid', 'onUnsubscribe'))->getMock();

        $streamSubscribe->expects($this->any())->method('getStreamChannel')->will($this->returnValue($streamChannel));

        $streamChannel->expects($this->any())->method('isValid')->will($this->returnValue(true));
        $streamChannel->expects($this->once())->method('onUnsubscribe')->with($streamSubscribe);

        /** @var CM_Model_StreamChannel_Media $streamChannel */
        /** @var CM_Model_Stream_Subscribe $streamSubscribe */

        $onDeleteBefore = CMTest_TH::getProtectedMethod('CM_Model_Stream_Subscribe', '_onDeleteBefore');
        $onDeleteBefore->invoke($streamSubscribe);
        $onDelete = CMTest_TH::getProtectedMethod('CM_Model_Stream_Subscribe', '_onDelete');
        $onDelete->invoke($streamSubscribe);
    }

    public function testDeleteOnUnsubscribeInvalid() {
        $streamSubscribe = $this->getMockBuilder('CM_Model_Stream_Subscribe')
            ->setMethods(array('getStreamChannel', 'getId'))->getMock();

        $streamChannel = $this->getMockBuilder('CM_Model_StreamChannel_Media')
            ->setMethods(array('isValid', 'onUnsubscribe'))->getMock();

        $streamSubscribe->expects($this->any())->method('getStreamChannel')->will($this->returnValue($streamChannel));

        $streamChannel->expects($this->any())->method('isValid')->will($this->returnValue(false));
        $streamChannel->expects($this->never())->method('onUnsubscribe');

        /** @var CM_Model_StreamChannel_Media $streamChannel */
        /** @var CM_Model_Stream_Subscribe $streamSubscribe */

        $onDelete = CMTest_TH::getProtectedMethod('CM_Model_Stream_Subscribe', '_onDelete');
        $onDelete->invoke($streamSubscribe);
    }
}
