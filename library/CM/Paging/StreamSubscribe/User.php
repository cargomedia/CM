<?php

class CM_Paging_StreamSubscribe_User extends CM_Paging_StreamSubscribe_Abstract {

  /**
   * @param CM_Model_User $user
   */
  public function __construct(CM_Model_User $user) {
    $source = new CM_PagingSource_Sql('`id`', 'cm_stream_subscribe', '`userId` = ' . $user->getId());
    parent::__construct($source);
  }
}
