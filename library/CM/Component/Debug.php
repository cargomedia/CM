<?php
class CM_Component_Debug extends CM_Component_Abstract {

	public function checkAccessible() {
		if (!IS_DEBUG) {
			throw new CM_Exception_NotAllowed();
		}
	}

	public function prepare() {
		$debug = CM_Debug::get();
		$stats = $debug->getStats();
		ksort($stats);
		$this->setTplParam('stats', $stats);
		$cacheNames = array('CM_Cache_Storage_Memcache', 'CM_Cache_Storage_Apc', 'CM_Cache_Storage_File');
		$this->_setJsParam('cacheNames', $cacheNames);
		$this->setTplParam('cacheNames', $cacheNames);
	}

	public function ajax_clearCache(CM_Params $params, CM_ComponentFrontendHandler $handler, CM_Response_View_Ajax $response) {
		$cachesCleared = array();
		if ($params->getBoolean('CM_Cache_Storage_Memcache', false)) {
			$cache = new CM_Cache_Storage_Memcache();
			$cache->flush();
			$cachesCleared[] = 'CM_Cache_Storage_Memcache';
		}
		if ($params->getBoolean('CM_Cache_Storage_Apc', false)) {
			$cache = new CM_Cache_Storage_Apc();
			$cache->flush();
			$cachesCleared[] = 'CM_Cache_Storage_Apc';
		}
		if ($params->getBoolean('CM_Cache_Storage_File', false)) {
			$cache = new CM_Cache_Storage_File();
			$cache->flush();
			$cachesCleared[] = 'CM_Cache_Storage_File';
		}
		$handler->message('Cleared: ' . implode(', ', $cachesCleared));
	}

}
