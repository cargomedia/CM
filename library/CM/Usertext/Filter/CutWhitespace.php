<?php

class CM_Usertext_Filter_CutWhitespace extends CM_Usertext_Filter_Abstract {

    public function transform($text, CM_Frontend_Render $render) {
        $text = (string) $text;
        $text = str_replace("\r", '', $text);
        $text = str_replace("\t", " ", $text);
        $text = preg_replace('# {1,}#', ' ', $text);
        $text = str_replace(array(" \n", ' </p>', ' <br />'), array("\n", '</p>', '<br />'), $text);
        $text = trim($text);
        return $text;
    }
}
