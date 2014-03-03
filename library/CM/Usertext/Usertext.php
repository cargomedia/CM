<?php

class CM_Usertext_Usertext extends CM_Class_Abstract {

  /** @var CM_Render */
  private $_render;

  /**
   * @param CM_Render $render
   */
  function __construct(CM_Render $render) {
    $this->_render = $render;
  }

  /** @var CM_Usertext_Filter_Interface[] */
  private $_filterList = array();

  /**
   * @param CM_Usertext_Filter_Interface $filter
   */
  public function addFilter(CM_Usertext_Filter_Interface $filter) {
    $this->_filterList[] = $filter;
  }

  /**
   * @param string    $mode
   * @param int|null  $maxLength
   * @param bool|null $isMail
   * @param bool|null $skipAnchors
   * @throws CM_Exception_Invalid
   */
  public function setMode($mode, $maxLength = null, $isMail = null, $skipAnchors = null) {
    $acceptedModes = array('escape', 'oneline', 'simple', 'markdown', 'markdownPlain');
    if (!in_array($mode, $acceptedModes)) {
      throw new CM_Exception_Invalid('Invalid mode `' . $mode . '`');
    }
    $mode = (string) $mode;
    $this->_clearFilters();
    $emoticonFixedHeight = null;
    if ($isMail) {
      $emoticonFixedHeight = 16;
    }
    $this->addFilter(new CM_Usertext_Filter_Badwords());
    $this->addFilter(new CM_Usertext_Filter_Escape());
    switch ($mode) {
      case 'escape':
        break;
      case 'oneline':
        $this->addFilter(new CM_Usertext_Filter_MaxLength($maxLength));
        $this->addFilter(new CM_Usertext_Filter_Emoticon($emoticonFixedHeight));
        break;
      case 'simple':
        $this->addFilter(new CM_Usertext_Filter_MaxLength($maxLength));
        $this->addFilter(new CM_Usertext_Filter_NewlineToLinebreak(3));
        $this->addFilter(new CM_Usertext_Filter_Emoticon($emoticonFixedHeight));
        break;
      case 'markdown':
        if (null !== $maxLength) {
          throw new CM_Exception_Invalid('MaxLength is not allowed in mode markdown.');
        }
        $this->addFilter(new CM_Usertext_Filter_Emoticon_EscapeMarkdown());
        $this->addFilter(new CM_Usertext_Filter_Markdown_UnescapeBlockquote());
        $this->addFilter(new CM_Usertext_Filter_Markdown($skipAnchors));
        $this->addFilter(new CM_Usertext_Filter_Emoticon_UnescapeMarkdown());
        $this->addFilter(new CM_Usertext_Filter_Emoticon($emoticonFixedHeight));
        break;
      case 'markdownPlain':
        $this->addFilter(new CM_Usertext_Filter_Emoticon_EscapeMarkdown());
        $this->addFilter(new CM_Usertext_Filter_Markdown($skipAnchors));
        $this->addFilter(new CM_Usertext_Filter_Emoticon_UnescapeMarkdown());
        $this->addFilter(new CM_Usertext_Filter_Striptags());
        $this->addFilter(new CM_Usertext_Filter_MaxLength($maxLength));
        $this->addFilter(new CM_Usertext_Filter_Emoticon($emoticonFixedHeight));
        break;
    }

    if ('markdownPlain' != $mode) {
      $this->addFilter(new CM_Usertext_Filter_CutWhitespace());
    }
  }

  /**
   * @param string $text
   * @return string
   */
  public function transform($text) {
    $cacheKey = CM_CacheConst::Usertext . '_text:' . md5($text);
    $cache = CM_Cache_Local::getInstance();
    if (0 !== count($this->_getFilters())) {
      $cacheKey .= '_filter:' . call_user_func_array(array($cache, 'key'), $this->_getFilters());
    }
    if (($result = $cache->get($cacheKey)) === false) {
      $result = $text;
      foreach ($this->_getFilters() as $filter) {
        $result = $filter->transform($result, $this->_render);
      }
      $cache->set($cacheKey, $result);
    }
    return $result;
  }

  private function _clearFilters() {
    $this->_filterList = array();
  }

  /**
   * @return CM_Usertext_Filter_Interface[]
   */
  private function _getFilters() {
    return $this->_filterList;
  }

  /**
   * @param CM_Render $render
   * @return CM_Usertext_Usertext
   */
  public static function factory(CM_Render $render) {
    $className = self::_getClassName();
    return new $className($render);
  }
}
