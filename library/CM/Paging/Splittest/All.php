<?php

class CM_Paging_Splittest_All extends CM_Paging_Splittest_Abstract {

	public function __construct() {
		$source = new CM_PagingSource_Sql('name', 'cm_splittest', null, 'createStamp');
		parent::__construct($source);
	}
}
