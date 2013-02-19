<?php

class CM_Usertext_Cli extends CM_Cli_Runnable_Abstract {

	public function emojiUpdate() {

		$smileys = array();

		foreach (CM_Bootloader::getInstance()->getNamespaces() as $namespace) {
			$smileyPath = CM_Util::getNamespacePath($namespace) . 'layout/default/img/smiley/';
			$files = glob($smileyPath . '*');
			foreach($files as $file){
				$file = str_replace($smileyPath,'',$file);
				$file = explode('.', $file);
				$smileys[$file[0]] = array('name' => $file[0], 'extension' => $file[1]);
			}
		}

		$insertSmileys = array();
		$counter = 0;
		foreach ($smileys as $smiley) {
			$counter++;
			$insertSmileys[] = array(':'.$smiley['name'].':', $smiley['name'] . "." . $smiley['extension']);
		}

		CM_Mysql::insertIgnore(TBL_CM_SMILEY, array('code', 'file'), $insertSmileys);
		$this->_getOutput()->writeln('Insert ' . $counter . ' smileys');
	}

	public static function getPackageName() {
		return 'usertext';
	}

}
