<?php

class CM_Paging_ContentList_Badwords extends CM_Paging_ContentList_Abstract {

	const TYPE = 2;

	function __construct() {
		parent::__construct(self::TYPE);
	}

	/**
	 * @param string $userInput
	 * @return bool
	 */
	public function isMatch($userInput) {
		if (preg_match($this->_toRegex(), (string) $userInput)) {
			return true;
		}

		return false;
	}

	/**
	 * @param string $userInput
	 * @return string|false
	 */
	public function getMatch($userInput) {
		$userInput = (string) $userInput;
		foreach ($this->_toRegexList() as $badword => $badwordRegex) {
			if (preg_match($badwordRegex, $userInput)) {
				return str_replace('*', '', $badword);
			}
		}

		return false;
	}

	/**
	 * @param string $userInput
	 * @param string $replacementString
	 * @return string
	 */
	public function replaceMatch($userInput, $replacementString) {
		$userInput = (string) $userInput;
		$replacementString = str_replace('$', '\\$', str_replace('\\', '\\\\', (string) $replacementString));

		do {
			$userInputOld = $userInput;
			$userInput = preg_replace($this->_toRegex(), $replacementString, $userInput);
		} while ($userInputOld !== $userInput);

		return $userInput;
	}

	public function _change() {
		parent::_change();
		CM_Cache::delete(CM_CacheConst::ContentList_BadwordRegex);
	}

	/**
	 * @return string
	 */
	private function _toRegex() {
		$cacheKey = CM_CacheConst::ContentList_BadwordRegex;
		if (false == ($badwordsRegex = CM_Cache::get($cacheKey))) {
			if ($this->isEmpty()) {
				$badwordsRegex = '#\z.#';
			} else {
				$regexList = array();
				foreach ($this as $badword) {
					$badword = preg_quote($badword, '#');
					$badword = str_replace('\*', '[^A-Za-z]*', $badword);
					$regexList[] = '\S*' . $badword . '\S*';
				}
				$badwordsRegex = '#(?:' . implode('|', $regexList) . ')#i';
			}
			CM_Cache::set($cacheKey, $badwordsRegex);
		}

		return $badwordsRegex;
	}

	/**
	 * @return string[]
	 */
	private function _toRegexList() {
		$regexList = array();
		foreach ($this as $badword) {
			$badwordRegex = preg_quote($badword, '#');
			$badwordRegex = str_replace('\*', '[^A-Za-z]*', $badwordRegex);
			$regexList[$badword] = '#\S*' . $badwordRegex . '\S*#i';
		}

		return $regexList;
	}
}
