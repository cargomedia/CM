<?php

class CM_Paging_StreamChannelArchiveMedia_TypeTest extends CMTest_TestCase {

    public function testPaging() {
        CMTest_TH::createStreamChannelVideoArchive();
        $archive = CMTest_TH::createStreamChannelVideoArchive();
        CMTest_TH::timeForward(30);
        CMTest_TH::createStreamChannelVideoArchive();
        /** @var CM_Model_StreamChannel_Media $streamChannel */
        $streamChannel = CMTest_TH::createStreamChannel();
        $streamChannel = $this->getMock('CM_Model_StreamChannel_Media', array('getType'), array($streamChannel->getId()));
        $streamChannel->expects($this->any())->method('getType')->will($this->returnValue(3));
        CMTest_TH::createStreamChannelVideoArchive($streamChannel);

        $paging = new CM_Paging_StreamChannelArchiveMedia_Type(CM_Model_StreamChannel_Media::getTypeStatic());
        $this->assertSame(3, $paging->getCount());

        $paging = new CM_Paging_StreamChannelArchiveMedia_Type($streamChannel->getType());
        $this->assertSame(1, $paging->getCount());

        $paging = new CM_Paging_StreamChannelArchiveMedia_Type(CM_Model_StreamChannel_Media::getTypeStatic(), $archive->getCreated());
        $this->assertSame(2, $paging->getCount());
    }
}
