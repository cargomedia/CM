<?php

class CM_Log_Handler_Stream extends CM_Log_Handler_Abstract {

    /** @var  CM_Log_Formatter_Interface */
    private $_formatter;

    /** @var CM_OutputStream_Interface */
    protected $_stream;

    /**
     * @param CM_OutputStream_Interface  $stream
     * @param CM_Log_Formatter_Interface $formatter
     * @param int|null                   $level
     * @param bool|null                  $bubble
     */
    public function __construct(CM_OutputStream_Interface $stream, CM_Log_Formatter_Interface $formatter, $level = null, $bubble = null) {
        $this->_stream = $stream;
        $this->_formatter = $formatter;
        parent::__construct($level, $bubble);
    }

    /**
     * @param CM_Log_Record $record
     */
    protected function _writeRecord(CM_Log_Record $record) {
        $this->_stream->writeln($this->_formatter->render($record));
    }
}
