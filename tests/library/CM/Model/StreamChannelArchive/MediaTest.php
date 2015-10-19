<?php

class CM_Model_StreamChannelArchive_MediaTest extends CMTest_TestCase {

    public function tearDown() {
        CMTest_TH::clearEnv();
    }

    public function testCreate() {
        /** @var CM_Model_StreamChannel_Media $streamChannel */
        $streamChannel = CMTest_TH::createStreamChannel();
        $user = CMTest_TH::createUser();
        $streamPublish = CMTest_TH::createStreamPublish($user, $streamChannel);
        CMTest_TH::timeForward(10);
        /** @var CM_Model_StreamChannelArchive_Media $archive */
        $archive = CM_Model_StreamChannelArchive_Media::createStatic(array('streamChannel' => $streamChannel));
        $this->assertInstanceOf('CM_Model_StreamChannelArchive_Media', $archive);
        $this->assertSame($streamChannel->getId(), $archive->getId());
        $this->assertSame($user->getId(), $archive->getUserId());
        $this->assertEquals($user, $archive->getUser());
        $this->assertSame($streamPublish->getStart(), $archive->getCreated());
        $this->assertEquals(10, $archive->getDuration(), '', 1);
        $this->assertSame($streamChannel->getThumbnailCount(), $archive->getThumbnailCount());
        $this->assertSame(md5($streamPublish->getKey()), $archive->getHash());
        $this->assertSame($streamChannel->getType(), $archive->getStreamChannelType());

        $streamChannel = CMTest_TH::createStreamChannel();
        try {
            CM_Model_StreamChannelArchive_Media::createStatic(array('streamChannel' => $streamChannel));
            $this->fail('StreamChannelArchive_Media created without StreamPublish.');
        } catch (CM_Exception_Invalid $ex) {
            $this->assertTrue(true);
        }
    }

    public function testNoUser() {
        /** @var CM_Model_StreamChannel_Media $streamChannel */
        $streamChannel = CMTest_TH::createStreamChannel();
        $user = CMTest_TH::createUser();
        $streamPublish = CMTest_TH::createStreamPublish($user, $streamChannel);
        $streamPublish->unsetUser();

        /** @var CM_Model_StreamChannelArchive_Media $archive */
        $archive = CM_Model_StreamChannelArchive_Media::createStatic(array('streamChannel' => $streamChannel));

        $this->assertNull($archive->getUser());
        $this->assertNull($archive->getUserId());
    }

    public function testGetUser() {
        $user = CMTest_TH::createUser();
        $streamChannel = CMTest_TH::createStreamChannel();
        CMTest_TH::createStreamPublish($user, $streamChannel);
        $archive = CMTest_TH::createStreamChannelVideoArchive($streamChannel);
        $this->assertEquals($user, $archive->getUser());
        $user->delete();
        $this->assertNull($archive->getUser());
    }

    public function testGetVideo() {
        $archive = CMTest_TH::createStreamChannelVideoArchive();
        $videoFile = $archive->getVideo();
        $this->assertSame('streamChannels/' . $archive->getId() . '/' . $archive->getId() . '-' . $archive->getHash() .
            '-original.mp4', $videoFile->getPathRelative());
    }

    public function testGetThumbnails() {
        $archive = CMTest_TH::createStreamChannelVideoArchive();
        $this->assertSame(array(), $archive->getThumbnails()->getItems());

        /** @var CM_Model_StreamChannel_Media $streamChannel */
        $streamChannel = CMTest_TH::createStreamChannel();
        $streamChannel->setThumbnailCount(2);
        $archive = CMTest_TH::createStreamChannelVideoArchive($streamChannel);
        $thumb1 = new CM_File_UserContent('streamChannels', $archive->getId() . '-' . $archive->getHash() . '-thumbs/1.png', $streamChannel->getId());
        $thumb2 = new CM_File_UserContent('streamChannels', $archive->getId() . '-' . $archive->getHash() . '-thumbs/2.png', $streamChannel->getId());
        $this->assertEquals(array($thumb1, $thumb2), $archive->getThumbnails()->getItems());
    }

    public function testGetThumbnail() {
        $archive = CMTest_TH::createStreamChannelVideoArchive();
        $thumbnail = $archive->getThumbnail(3);
        $this->assertInstanceOf('CM_File_UserContent', $thumbnail);
        $this->assertSame(
            'streamChannels/' . $archive->getId() . '/' . $archive->getId() . '-' . $archive->getHash() . '-thumbs/3.png',
            $thumbnail->getPathRelative());
    }

    public function testOnDelete() {
        /** @var CM_Model_StreamChannel_Media $streamChannel */
        $streamChannel = CMTest_TH::createStreamChannel();
        $streamChannel->setThumbnailCount(3);
        $archive = CMTest_TH::createStreamChannelVideoArchive($streamChannel);
        $files = $this->_createArchiveFiles($archive);
        foreach ($files as $file) {
            $this->assertTrue($file->exists());
        }

        $archive->delete();
        foreach ($files as $file) {
            $this->assertFalse($file->exists());
        }
        try {
            new CM_Model_StreamChannelArchive_Media($archive->getId());
            $this->fail('StreamChannelArchive not deleted.');
        } catch (CM_Exception_Nonexistent $ex) {
            $this->assertTrue(true);
        }
    }

    public function testDeleteOlder() {
        $time = time();
        /** @var CM_Model_StreamChannel_Media $streamChannel */
        $streamChannelsDeleted = array();
        $archivesDeleted = array();
        /** @var $filesDeleted CM_File[] */
        $filesDeleted = array();
        for ($i = 0; $i < 2; $i++) {
            $streamChannel = CMTest_TH::createStreamChannel();
            $streamChannel->setThumbnailCount(4);
            $streamChannelsDeleted[] = $streamChannel;
            $archive = CMTest_TH::createStreamChannelVideoArchive($streamChannel);
            $archivesDeleted[] = $archive;
            $filesDeleted = array_merge($filesDeleted, $this->_createArchiveFiles($archive));
        }

        $streamChannelsNotDeleted = array();
        $archivesNotDeleted = array();
        /** @var $filesNotDeleted CM_File[] */
        $filesNotDeleted = array();
        $streamChannel = CMTest_TH::createStreamChannel();
        $streamChannel = $this->getMock('CM_Model_StreamChannel_Media', array('getType'), array($streamChannel->getId()));
        $streamChannel->expects($this->any())->method('getType')->will($this->returnValue(3));
        $streamChannelsNotDeleted[] = $streamChannel;
        $archive = CMTest_TH::createStreamChannelVideoArchive($streamChannel);
        $archivesNotDeleted[] = $archive;
        $filesNotDeleted = array_merge($filesNotDeleted, $this->_createArchiveFiles($archive));

        CMTest_TH::timeForward(20);
        for ($i = 0; $i < 3; $i++) {
            $streamChannel = CMTest_TH::createStreamChannel();
            $streamChannel->setThumbnailCount(4);
            $streamChannelsNotDeleted[] = $streamChannel;
            $archive = CMTest_TH::createStreamChannelVideoArchive($streamChannel);
            $archivesNotDeleted[] = $archive;
            $filesNotDeleted = array_merge($filesNotDeleted, $this->_createArchiveFiles($archive));
        }

        foreach ($filesNotDeleted as $file) {
            $this->assertTrue($file->exists());
        }
        foreach ($filesDeleted as $file) {
            $this->assertTrue($file->exists());
        }
        CM_Model_StreamChannelArchive_Media::deleteOlder(10, CM_Model_StreamChannel_Media::getTypeStatic());
        foreach ($filesNotDeleted as $file) {
            $this->assertTrue($file->exists());
        }
        foreach ($filesDeleted as $file) {
            $this->assertFalse($file->exists());
        }
        foreach ($archivesNotDeleted as $archive) {
            try {
                CMTest_TH::reinstantiateModel($archive);
                $this->assertTrue(true);
            } catch (CM_Exception_Nonexistent $ex) {
                $this->fail('Young streamchannelArchive deleted');
            }
        }
        foreach ($archivesDeleted as $archive) {
            try {
                CMTest_TH::reinstantiateModel($archive);
                $this->fail('Old streamchannelArchive not deleted');
            } catch (CM_Exception_Nonexistent $ex) {
                $this->assertTrue(true);
            }
        }
    }

    public function testFindById() {
        $streamChannel = $streamChannel = CMTest_TH::createStreamChannel();
        $this->assertNull(CM_Model_StreamChannelArchive_Media::findById($streamChannel->getId()));

        CMTest_TH::createStreamPublish(null, $streamChannel);
        CM_Model_StreamChannelArchive_Media::createStatic(array('streamChannel' => $streamChannel));
        $this->assertInstanceOf('CM_Model_StreamChannelArchive_Media', CM_Model_StreamChannelArchive_Media::findById($streamChannel->getId()));
    }

    /**
     * @param CM_Model_StreamChannelArchive_Media $archive
     * @return CM_File[]
     */
    private function _createArchiveFiles(CM_Model_StreamChannelArchive_Media $archive) {
        $files = array();
        if ($archive->getThumbnailCount() > 0) {
            /** @var CM_File_UserContent $thumbnailFirst */
            $thumbnailFirst = $archive->getThumbnails()->getItem(0);
            $thumbnailFirst->ensureParentDirectory();
            $files[] = $thumbnailFirst->getParentDirectory();
        }
        for ($i = 0; $i < $archive->getThumbnailCount(); $i++) {
            /** @var CM_File_UserContent $file */
            $file = $archive->getThumbnails()->getItem($i);
            $file->write('');
            $files[] = $file;
        }
        $video = $archive->getVideo();
        $video->ensureParentDirectory();
        $video->write('');
        $files[] = $video;
        return $files;
    }
}
