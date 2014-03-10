<?php

abstract class CM_Paging_Emoticon_Abstract extends CM_Paging_Abstract {

    protected function _processItem($itemRaw) {
        $item = array();
        $item['id'] = (int) $itemRaw['id'];
        $item['code'] = (string) $itemRaw['code'];
        $item['codes'] = array($itemRaw['code']);
        if ($itemRaw['codeAdditional']) {
            $item['codes'] = array_merge($item['codes'], explode(',', $itemRaw['codeAdditional']));
        }
        $item['file'] = $itemRaw['file'];
        return $item;
    }
}
