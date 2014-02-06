<?php

class CM_SVM_Model {

	/**
	 * @var SVMModel
	 */
	private $_model;

	/**
	 * @var int
	 */
	private $_id;

	/**
	 * @param int $id
	 */
	public function __construct($id) {
		if (!extension_loaded('svm')) {
			throw new CM_Exception('Extension `svm` not loaded.');
		}
		$this->_id = (int) $id;
		if (!CM_File::exists($this->_getPath())) {
			$this->train();
		}
		$this->_model = new SVMModel($this->_getPath());
	}

	/**
	 * @return int
	 */
	public function getId() {
		return $this->_id;
	}

	/**
	 * @param int   $class
	 * @param array $values Feature=>Value pairs
	 */
	public function addTraining($class, array $values) {
		$class = (int) $class;
		$values = $this->_parseValues($values);
		CM_Db_Db::insert('cm_svmtraining',
			array('svmId' => $this->getId(), 'class' => $class, 'values' => serialize($values), 'createStamp' => time()));
		CM_Db_Db::replace('cm_svm', array('id' => $this->getId(), 'trainingChanges' => 1));
	}

	/**
	 * @param array $values Feature=>Value pairs
	 * @return int
	 */
	public function predict(array $values) {
		$values = $this->_parseValues($values);
		$result = $this->_model->predict($values);
		return (int) $result;
	}

	public function train() {
		$svm = new SVM();
		$svm->setOptions(
			array(
				SVM::OPT_KERNEL_TYPE => SVM::KERNEL_LINEAR,
			)
		);
		$trainings = CM_Db_Db::select('cm_svmtraining', array('class', 'values'), array('svmId' => $this->getId()))->fetchAll();

		$problem = array();
		$classCounts = array();
		foreach ($trainings as $training) {
			$class = $training['class'];
			$values = unserialize($training['values']);
			if (!isset($classCounts[$class])) {
				$classCounts[$class] = 0;
			}
			$classCounts[$class]++;
			$problem[] = array_merge(array(0 => $class), $values);
		}

		$weights = array();
		foreach ($classCounts as $class => $count) {
			$weights[$class] = min($classCounts) / $count;
		}
		if (empty($weights)) {
			$weights = null;
		}

		$this->_model = $svm->train($problem, $weights);
		$this->_model->save($this->_getPath());
		CM_Db_Db::replace('cm_svm', array('id' => $this->getId(), 'trainingChanges' => 0));
	}

	public function flush() {
		CM_Db_Db::delete('cm_svmtraining', array('svmId' => $this->getId()));
		CM_Db_Db::replace('cm_svm', array('id' => $this->getId(), 'trainingChanges' => 1));
		$file = new CM_File($this->_getPath());
		$file->delete();
		$this->__construct($this->_id);
	}

	/**
	 * @return string
	 */
	private function _getPath() {
		$dirDataSvm = CM_Bootloader::getInstance()->getDirData() . 'svm/';
		CM_Util::mkDir($dirDataSvm);
		return $dirDataSvm . $this->getId() . '.svm';
	}

	/**
	 * @param array $values
	 * @return array
	 */
	private function _parseValues(array $values) {
		ksort($values);
		$values = array_values($values);
		if (isset($values[0])) {
			// Cannot have feature `0`
			$values[] = $values[0];
			unset($values[0]);
		}
		foreach ($values as $feature => &$value) {
			// Values between 0 and 1
			$value = (float) max(0, min(1, $value));
		}
		ksort($values);
		return $values;
	}

	/**
	 * @param int $trainingsMax
	 */
	public static function deleteOldTrainings($trainingsMax) {
		$trainingsMax = (int) $trainingsMax;
		$ids = CM_Db_Db::select('cm_svm', 'id')->fetchAllColumn();
		foreach ($ids as $id) {
			$trainingsCount = CM_Db_Db::count('cm_svmtraining', array('svmId' => $id));
			if ($trainingsCount > $trainingsMax) {
				$limit = (int) ($trainingsCount - $trainingsMax);
				$deletedCount = CM_Db_Db::exec(
					'DELETE FROM `cm_svmtraining` WHERE `svmId` = ? ORDER BY `createStamp` LIMIT ' . $limit, array($id))->getAffectedRows();
				if ($deletedCount > 0) {
					CM_Db_Db::replace('cm_svm', array('id' => $id, 'trainingChanges' => 1));
				}
			}
		}
	}

	public static function trainChanged() {
		$ids = CM_Db_Db::select('cm_svm', 'id', array('trainingChanges' => 1))->fetchAllColumn();
		foreach ($ids as $id) {
			$svm = new self($id);
			$svm->train();
		}
	}
}
