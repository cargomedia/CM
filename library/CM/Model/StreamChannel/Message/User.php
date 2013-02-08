<?php

class CM_Model_StreamChannel_Message_User extends CM_Model_StreamChannel_Message {
	const SALT = 'd98*2jflq74fçr8gföqwm&dsöwrds93"2d93tp+ihwd.20trl';

	const TYPE = 29;

	public function onPublish(CM_Model_Stream_Publish $streamPublish) {
	}

	public function onSubscribe(CM_Model_Stream_Subscribe $streamSubscribe) {
	}

	public function onUnpublish(CM_Model_Stream_Publish $streamPublish) {
	}

	public function onUnsubscribe(CM_Model_Stream_Subscribe $streamSubscribe) {
	}

	/**
	 * @param CM_Model_User $user
	 * @return string
	 */
	public static function getKeyByUser(CM_Model_User $user) {
		return hash('md5', self::SALT . ':' . $user->getId());
	}

}
