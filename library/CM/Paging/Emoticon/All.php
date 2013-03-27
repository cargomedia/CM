<?php

class CM_Paging_Emoticon_All extends CM_Paging_Emoticon_Abstract {

	public function __construct() {
		$source = new CM_PagingSource_Sql('id, code, codeAdditional, file', TBL_CM_EMOTICON, null, '`id`');
		$source->enableCacheLocal();
		parent::__construct($source);
	}
}
