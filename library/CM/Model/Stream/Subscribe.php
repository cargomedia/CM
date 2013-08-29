<?php

class CM_Model_Stream_Subscribe extends CM_Model_Stream_Abstract {

	const TYPE = 22;

	public function setAllowedUntil($timeStamp) {
		CM_Db_Db::update('cm_stream_subscribe', array('allowedUntil' => $timeStamp), array('id' => $this->getId()));
		$this->_change();
	}

	public function unsetUser() {
		CM_Db_Db::update('cm_stream_subscribe', array('userId' => null), array('id' => $this->getId()));
		$this->_change();
	}

	protected function _getContainingCacheables() {
		$cacheables = parent::_getContainingCacheables();
		$cacheables[] = $this->getStreamChannel()->getStreamSubscribes();
		$cacheables[] = $this->getStreamChannel()->getSubscribers();
		return $cacheables;
	}

	protected function _loadData() {
		return CM_Db_Db::select('cm_stream_subscribe', '*', array('id' => $this->getId()))->fetch();
	}

	protected function _onDelete() {
		$this->getStreamChannel()->onUnsubscribe($this);
		CM_Db_Db::delete('cm_stream_subscribe', array('id' => $this->getId()));
	}

	/**
	 * @param string                          $key
	 * @param CM_Model_StreamChannel_Abstract $channel
	 * @return CM_Model_Stream_Subscribe|null
	 */
	public static function findByKeyAndChannel($key, CM_Model_StreamChannel_Abstract $channel) {
		$id = CM_Db_Db::select('cm_stream_subscribe', 'id', array('key' => (string) $key, 'channelId' => $channel->getId()))->fetchColumn();
		if (!$id) {
			return null;
		}
		return new static($id);
	}

	protected static function _createStatic(array $data) {
		$userId = null;
		if (isset($data['user'])) {
			/** @var CM_Model_User $user */
			$user = $data['user'];
			$userId = $user->getId();
		}
		$start = (int) $data['start'];
		$allowedUntil = null;
		if (null !== $data['allowedUntil']) {
			$allowedUntil = (int) $data['allowedUntil'];
		}
		/** @var CM_Model_StreamChannel_Abstract $streamChannel */
		$streamChannel = $data['streamChannel'];
		$key = (string) $data['key'];
		$id = CM_Db_Db::insert('cm_stream_subscribe', array('userId' => $userId, 'start' => $start, 'allowedUntil' => $allowedUntil,
			'channelId' => $streamChannel->getId(), 'key' => $key));
		$streamSubscribe = new self($id);
		$streamChannel->onSubscribe($streamSubscribe);
		return $streamSubscribe;
	}

}
