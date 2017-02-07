<?php

class CM_File_UserContent_Temp extends CM_File_UserContent {

    /** @var string */
    private $_uniqid;

    /** @var string */
    private $_filenameLabel;

    /**
     * @param string                  $uniqid
     * @param CM_Service_Manager|null $serviceManager
     * @throws CM_Exception_Nonexistent
     */
    public function __construct($uniqid, CM_Service_Manager $serviceManager = null) {
        $data = CM_Db_Db::select('cm_tmp_userfile', '*', array('uniqid' => $uniqid))->fetch();
        if (!$data) {
            throw new CM_Exception_Nonexistent('Uniqid for file does not exist', null, ['uniqid' => $uniqid]);
        }
        $this->_uniqid = $data['uniqid'];
        $this->_filenameLabel = $data['filename'];

        $filenameParts = array($this->getUniqid());
        if (false !== strpos($this->getFilenameLabel(), '.')) {
            $filenameParts[] = strtolower(pathinfo($this->getFilenameLabel(), PATHINFO_EXTENSION));
        }

        parent::__construct('tmp', implode('.', $filenameParts), null, $serviceManager);
    }

    /**
     * @param string                  $filename
     * @param string|null             $content
     * @param CM_File_Filesystem|null $filesystem
     * @throws CM_Exception_Invalid
     * @return CM_File_UserContent_Temp
     */
    public static function create($filename, $content = null, CM_File_Filesystem $filesystem = null) {
        if ($filesystem) {
            throw new CM_Exception_Invalid('Temporary user-content file cannot handle filesystem');
        }
        $filename = (string) $filename;
        if (strlen($filename) > 100) {
            $filename = substr($filename, -100, 100);
        }
        $uniqid = md5(rand() . uniqid());
        CM_Db_Db::insert('cm_tmp_userfile', array('uniqid' => $uniqid, 'filename' => $filename, 'createStamp' => time()));

        $file = new self($uniqid);
        $file->ensureParentDirectory();
        if (null !== $content) {
            $file->write($content);
        }
        return $file;
    }

    /**
     * @return string
     */
    public function getUniqid() {
        return $this->_uniqid;
    }

    /**
     * @return string
     */
    public function getFilenameLabel() {
        return $this->_filenameLabel;
    }

    public function delete($recursive = null) {
        CM_Db_Db::delete('cm_tmp_userfile', array('uniqid' => $this->getUniqid()));
        parent::delete();
    }

    /**
     * @param int $age
     */
    public static function deleteOlder($age) {
        $age = (int) $age;
        $result = CM_Db_Db::select('cm_tmp_userfile', 'uniqid', '`createStamp` < ' . (time() - $age));
        foreach ($result->fetchAllColumn() as $uniqid) {
            $tmpFile = new CM_File_UserContent_Temp($uniqid);
            $tmpFile->delete();
        }
    }
}
