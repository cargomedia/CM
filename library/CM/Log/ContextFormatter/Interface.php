<?php

interface CM_Log_ContextFormatter_Interface {

    /**
     * @param CM_Log_Record $record
     * @return array
     */
    public function formatRecordContext(CM_Log_Record $record);

    /**
     * @param CM_Log_Context $context
     * @return array
     */
    public function formatContext(CM_Log_Context $context);

    /**
     * @param CM_Log_Context $context
     * @return array
     */
    public function formatAppContext(CM_Log_Context $context);
}
