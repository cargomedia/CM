<?php

class CM_Paging_StreamChannelArchiveVideo_User extends CM_Paging_StreamChannelArchiveVideo_Abstract {

    /**
     * @param CM_Model_User $user
     */
    public function __construct(CM_Model_User $user) {
        $source = new CM_PagingSource_Sql('id', 'cm_streamChannelArchive_video', '`userId` = ' . $user->getId(), 'createStamp DESC');
        parent::__construct($source);
    }
}
