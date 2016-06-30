<?php

class CM_AdproviderAdapter_Iframe extends CM_AdproviderAdapter_Abstract {

    public function getHtml($zoneName, $zoneData, array $variables) {
        $src = (string) $zoneData['src'];
        $width = (string) $zoneData['width'];
        $height = (string) $zoneData['height'];

        $params = [
            'src'            => $src,
            'width'          => $width,
            'height'         => $height,
            'class'          => 'advertisement-hasContent',
            'data-variables' => json_encode($variables, JSON_FORCE_OBJECT),
            'frameborder'    => 0,
            'scrolling'      => 'no',
        ];
        $params = Functional\map($params, function ($value, $key) {
            return $key . '="' . CM_Util::htmlspecialchars($value) . '"';
        });

        return '<iframe ' . join(' ', $params) . '></iframe>';
    }
}
