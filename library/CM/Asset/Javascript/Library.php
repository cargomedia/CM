<?php

class CM_Asset_Javascript_Library extends CM_Asset_Javascript_Abstract {

    /**
     * @param CM_Site_Abstract $site
     */
    public function __construct(CM_Site_Abstract $site) {
        $content = '';
        foreach (self::getIncludedPaths($site) as $path) {
            $content .= (new CM_File($path))->read();
        }
        $internal = new CM_Asset_Javascript_Internal($site);
        $content .= $internal->get();
        $this->_content = $content;
    }

    /**
     * @param CM_Site_Abstract $site
     * @return string[]
     */
    public static function getIncludedPaths(CM_Site_Abstract $site) {
        $pathsUnsorted = CM_Util::rglobLibraries('*.js', $site);
        return array_keys(CM_Util::getClasses($pathsUnsorted));
    }
}
