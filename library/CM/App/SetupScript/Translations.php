<?php

class CM_App_SetupScript_Translations extends CM_Provision_Script_OptionBased {

    public function load(CM_OutputStream_Interface $output) {
        /** @var CM_Model_Language $language */
        foreach (new CM_Paging_Language_All() as $language) {
            $path = 'translations/' . $language->getAbbreviation() . '.php';
            foreach (CM_Util::getResourceFiles($path) as $translationsFile) {
                $translationsSetter = require $translationsFile->getPath();
                if (!$translationsSetter instanceof Closure) {
                    throw new CM_Exception_Invalid('Invalid translation file. Path must return callable', null, ['path' => $translationsFile->getPath()]);
                }
                $translationsSetter($language);
            }
        }
        $this->_setLoaded(true);
    }

    public function getRunLevel() {
        return 10;
    }
}
