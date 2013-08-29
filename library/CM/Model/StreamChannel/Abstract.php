<?php

abstract class CM_Model_StreamChannel_Abstract extends CM_Model_Abstract {

	/**
	 * @param CM_Model_User $user
	 * @param int           $allowedUntil
	 * @return int
	 */
	public function canPublish(CM_Model_User $user, $allowedUntil) {
		return $allowedUntil + 1000;
	}

	/**
	 * @param CM_Model_User|null $user
	 * @param int                $allowedUntil
	 * @return int
	 */
	public function canSubscribe(CM_Model_User $user = null, $allowedUntil) {
		return $allowedUntil + 1000;
	}

	/**
	 * @param CM_Model_Stream_Publish $streamPublish
	 */
	abstract public function onPublish(CM_Model_Stream_Publish $streamPublish);

	/**
	 * @param CM_Model_Stream_Subscribe $streamSubscribe
	 */
	abstract public function onSubscribe(CM_Model_Stream_Subscribe $streamSubscribe);

	/**
	 * @param CM_Model_Stream_Publish $streamPublish
	 */
	abstract public function onUnpublish(CM_Model_Stream_Publish $streamPublish);

	/**
	 * @param CM_Model_Stream_Subscribe $streamSubscribe
	 */
	abstract public function onUnsubscribe(CM_Model_Stream_Subscribe $streamSubscribe);

	/**
	 * @return string
	 */
	public function getKey() {
		return (string) $this->_get('key');
	}

	/**
	 * @return int
	 */
	public function getAdapterType() {
		return (int) $this->_get('adapterType');
	}

	/**
	 * @return CM_Paging_User_StreamChannelPublisher
	 */
	public function getPublishers() {
		return new CM_Paging_User_StreamChannelPublisher($this);
	}

	/**
	 * @return CM_Paging_StreamPublish_StreamChannel
	 */
	public function getStreamPublishs() {
		return new CM_Paging_StreamPublish_StreamChannel($this);
	}

	/**
	 * @return CM_Paging_StreamSubscribe_StreamChannel
	 */
	public function getStreamSubscribes() {
		return new CM_Paging_StreamSubscribe_StreamChannel($this);
	}

	/**
	 * @return CM_Paging_User_StreamChannelSubscriber
	 */
	public function getSubscribers() {
		return new CM_Paging_User_StreamChannelSubscriber($this);
	}

	/**
	 * @return CM_Paging_User_StreamChannel
	 */
	public function getUsers() {
		return new CM_Paging_User_StreamChannel($this);
	}

	/**
	 * @param CM_Model_User                  $user
	 * @param CM_Model_Stream_Subscribe|null $excludedStreamSubscribe
	 * @return bool
	 */
	public function isSubscriber(CM_Model_User $user, CM_Model_Stream_Subscribe $excludedStreamSubscribe = null) {
		/** @var $streamSubscribeItem CM_Model_Stream_Subscribe */
		foreach ($this->getStreamSubscribes() as $streamSubscribeItem) {
			if (!$streamSubscribeItem->equals($excludedStreamSubscribe) && $streamSubscribeItem->getUserId() == $user->getId()) {
				return true;
			}
		}
		return false;
	}

	protected function _loadData() {
		$data = CM_Db_Db::select('cm_streamChannel', array('key', 'type', 'adapterType'), array('id' => $this->getId()))->fetch();
		if (false !== $data) {
			$type = (int) $data['type'];
			if ($this->getType() !== $type) {
				throw new CM_Exception_Invalid('Invalid type `' . $type . '` for `' . get_class($this) . '` (type: `' . $this->getType() . '`)');
			}
		}
		return $data;
	}

	protected function _onDelete() {
		/** @var CM_Model_Stream_Subscribe $streamSubscribe */
		foreach ($this->getStreamSubscribes() as $streamSubscribe) {
			$streamSubscribe->delete();
		}
		/** @var CM_Model_Stream_Publish $streamPublish */
		foreach ($this->getStreamPublishs() as $streamPublish) {
			$streamPublish->delete();
		}

		$cacheKey = CM_CacheConst::StreamChannel_Id . '_key' . $this->getKey() . '_adapterType:' . $this->getAdapterType();
		CM_Cache::delete($cacheKey);

		CM_Db_Db::delete('cm_streamChannel', array('id' => $this->getId()));
	}

	/**
	 * @param string $encryptionKey
	 * @return string Data
	 */
	protected function _decryptKey($encryptionKey) {
		return rtrim(mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $encryptionKey, base64_decode($this->getKey()), MCRYPT_MODE_ECB), "\0");
	}

	/**
	 * @param int      $id
	 * @param int|null $type
	 * @throws CM_Exception_Invalid|CM_Exception_Nonexistent
	 * @return static
	 */
	public static function factory($id, $type = null) {
		if (null === $type) {
			$cacheKey = CM_CacheConst::StreamChannel_Type . '_id:' . $id;
			if (false === ($type = CM_CacheLocal::get($cacheKey))) {
				$type = CM_Db_Db::select('cm_streamChannel', 'type', array('id' => $id))->fetchColumn();
				if (false === $type) {
					throw new CM_Exception_Invalid('No record found in `cm_streamChannel` for id `' . $id . '`');
				}
				CM_CacheLocal::set($cacheKey, $type);
			}
		}
		$class = self::_getClassName($type);
		$instance = new $class($id);
		if (!$instance instanceof static) {
			throw new CM_Exception_Invalid('Unexpected instance of `' . $class . '`. Expected `' . get_called_class(). '`.');
		}
		return $instance;
	}

	/**
	 * @param string $key
	 * @param int    $adapterType
	 * @return CM_Model_StreamChannel_Abstract|null
	 */
	public static function findByKeyAndAdapter($key, $adapterType) {
		$key = (string) $key;
		$adapterType = (int) $adapterType;

		$cacheKey = CM_CacheConst::StreamChannel_Id . '_key' . $key . '_adapterType:' . $adapterType;
		if (false === ($result = CM_Cache::get($cacheKey))) {
			$result = CM_Db_Db::select('cm_streamChannel', array('id', 'type'), array('key' => $key, 'adapterType' => $adapterType))->fetch();
			if (false === $result) {
				$result = null;
			}
			CM_Cache::set($cacheKey, $result);
		}

		if (!$result) {
			return null;
		}
		return self::factory($result['id'], $result['type']);
	}

	/**
	 * @param string $encryptionKey
	 * @param string $data
	 * @return string Channel-key
	 */
	protected static function _encryptKey($data, $encryptionKey) {
		return base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $encryptionKey, $data, MCRYPT_MODE_ECB));
	}

	protected static function _createStatic(array $data) {
		$key = (string) $data ['key'];
		$adapterType = (int) $data['adapterType'];
		$id = CM_Db_Db::insert('cm_streamChannel', array('key' => $key, 'type' => static::TYPE, 'adapterType' => $adapterType));
		$cacheKey = CM_CacheConst::StreamChannel_Id . '_key' . $key . '_adapterType:' . $adapterType;
		CM_Cache::delete($cacheKey);
		return new static($id);
	}
}
