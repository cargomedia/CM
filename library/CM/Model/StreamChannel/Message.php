<?php

class CM_Model_StreamChannel_Message extends CM_Model_StreamChannel_Abstract {

	const TYPE = 18;

	public function onPublish(CM_Model_Stream_Publish $streamPublish) {
	}

	public function onSubscribe(CM_Model_Stream_Subscribe $streamSubscribe) {
	}

	public function onUnpublish(CM_Model_Stream_Publish $streamPublish) {
	}

	public function onUnsubscribe(CM_Model_Stream_Subscribe $streamSubscribe) {
	}

	/**
	 * @param string     $streamChannel
	 * @param string     $namespace
	 * @param mixed|null $data
	 */
	public static function publish($streamChannel, $namespace, $data = null) {
		$streamChannel = $streamChannel . ':' . static::TYPE;
		CM_Stream_Message::getInstance()->publish($streamChannel, array('namespace' => $namespace, 'data' => $data));
	}

	/**
	 * @param string             $streamChannel
	 * @param CM_Action_Abstract $action
	 * @param CM_Model_Abstract  $model
	 * @param mixed|null         $data
	 */
	public static function publishAction($streamChannel, CM_Action_Abstract $action, CM_Model_Abstract $model, $data = null) {
		$namespace = 'CM_Action_Abstract' . ':' . $action->getVerb() . ':' . $model->getType();
		self::publish($streamChannel, $namespace, array('action' => $action, 'model' => $model, 'data' => $data));
	}
}
