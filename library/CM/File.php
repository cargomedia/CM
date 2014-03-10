<?php

class CM_File extends CM_Class_Abstract {

    /** @var string */
    private $_path;

    /**
     * @param string|CM_File $file Path to file
     * @throws CM_Exception_Invalid
     */
    public function __construct($file) {
        if ($file instanceof CM_File) {
            $file = $file->getPath();
        }
        $this->_path = (string) $file;
        if (!$this->getExists()) {
            throw new CM_Exception_Invalid('File path `' . $file . '` does not exist or is not a file.');
        }
    }

    /**
     * @return string File path
     */
    public function getPath() {
        return $this->_path;
    }

    /**
     * @return string File name
     */
    public function getFileName() {
        return pathinfo($this->getPath(), PATHINFO_BASENAME);
    }

    /**
     * @return string
     */
    public function getFileNameWithoutExtension() {
        return pathinfo($this->getPath(), PATHINFO_FILENAME);
    }

    /**
     * @return int File size in bytes
     * @throws CM_Exception
     */
    public function getSize() {
        $size = filesize($this->getPath());
        if (false === $size) {
            throw new CM_Exception('Cannot detect filesize of `' . $this->getPath() . '`');
        }
        return $size;
    }

    /**
     * @return string File mime type
     * @throws CM_Exception
     */
    public function getMimeType() {
        $info = new finfo(FILEINFO_MIME);
        $infoFile = $info->file($this->getPath());
        if (false === $infoFile) {
            throw new CM_Exception('Cannot detect FILEINFO_MIME of `' . $this->getPath() . '`');
        }
        $mime = explode(';', $infoFile);
        return $mime[0];
    }

    /**
     * @return string|null
     */
    public function getExtension() {
        $fileName = $this->getFileName();
        if (false === strpos($fileName, '.')) {
            return null;
        }

        return strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    }

    /**
     * @return string MD5-hash of file contents
     * @throws CM_Exception
     */
    public function getHash() {
        $md5 = md5_file($this->getPath());
        if (false === $md5) {
            throw new CM_Exception('Cannot detect md5-sum of `' . $this->getPath() . '`');
        }
        return $md5;
    }

    /**
     * @return bool
     */
    public function getExists() {
        return static::exists($this->getPath());
    }

    /**
     * @return string
     * @throws CM_Exception
     */
    public function read() {
        @$contents = file_get_contents($this->getPath());
        if ($contents === false) {
            throw new CM_Exception('Cannot read contents of `' . $this->getPath() . '`.');
        }
        return $contents;
    }

    /**
     * @param string $content
     * @throws CM_Exception
     */
    public function write($content) {
        if (false === file_put_contents($this->getPath(), $content)) {
            throw new CM_Exception('Could not write ' . strlen($content) . ' bytes to `' . $this->getPath() . '`');
        }
    }

    /**
     * @param string $content
     * @throws CM_Exception
     */
    public function append($content) {
        $resource = $this->_openFileHandle('a');
        if (false === fputs($resource, $content)) {
            throw new CM_Exception('Could not write ' . strlen($content) . ' bytes to `' . $this->getPath() . '`');
        }
        fclose($resource);
    }

    public function truncate() {
        $this->write('');
    }

    /**
     * @param string $path New file path
     * @throws CM_Exception
     */
    public function copy($path) {
        $path = (string) $path;
        if (!@copy($this->getPath(), $path)) {
            throw new CM_Exception('Cannot copy `' . $this->getPath() . '` to `' . $path . '`.');
        }
    }

    /**
     * @param string $path
     * @throws CM_Exception
     */
    public function move($path) {
        $path = (string) $path;
        if (!@rename($this->getPath(), $path)) {
            throw new CM_Exception('Cannot move `' . $this->getPath() . '` to `' . $path . '`.');
        }
        $this->_path = $path;
    }

    /**
     * @throws CM_Exception
     */
    public function delete() {
        if (!$this->getExists()) {
            return;
        }
        if (!@unlink($this->getPath())) {
            throw new CM_Exception_Invalid('Could not delete file `' . $this->getPath() . '`');
        }
    }

    /**
     * @param int $permission
     */
    public function setPermissions($permission) {
        $permission = (int) $permission;
        @chmod($this->getPath(), $permission);
    }

    /**
     * @return string
     */
    public function __toString() {
        return $this->read();
    }

    /**
     * @return string|null
     */
    protected function _readFirstLine() {
        $resource = $this->_openFileHandle('r');
        $firstLine = fgets($resource);
        fclose($resource);
        if (false === $firstLine) {
            return null;
        }
        return $firstLine;
    }

    /**
     * @param string $mode
     * @return resource
     * @throws CM_Exception
     */
    private function _openFileHandle($mode) {
        $resource = fopen($this->getPath(), $mode);
        if (false === $resource) {
            throw new CM_Exception('Could not open file in `' . $mode . '` mode. Path: `' . $this->getPath() . '`');
        }
        return $resource;
    }

    /**
     * @param string      $path
     * @param string|null $content
     * @return CM_File
     * @throws CM_Exception
     */
    public static function create($path, $content = null) {
        $content = (string) $content;
        if (false === @file_put_contents($path, $content)) {
            throw new CM_Exception('Cannot write to `' . $path . '`.');
        }
        $file = new static($path);
        return $file;
    }

    /**
     * @param string|null $content
     * @param string|null $extension
     * @return CM_File
     */
    public static function createTmp($extension = null, $content = null) {
        if (null !== $extension) {
            $extension = '.' . $extension;
        }
        $extension = (string) $extension;
        return static::create(CM_Bootloader::getInstance()->getDirTmp() . uniqid() . $extension, $content);
    }

    /**
     * @param string $path
     * @return bool
     */
    public static function exists($path) {
        return is_file($path);
    }

    /**
     * @param string $path
     * @throws CM_Exception_Invalid
     * @return int
     */
    public static function getModified($path) {
        $createStamp = filemtime($path);
        if (false === $createStamp) {
            throw new CM_Exception_Invalid('Can\'t get modified time of `' . $path . '`');
        }
        return $createStamp;
    }

    /**
     * taken from http://stackoverflow.com/a/2668953
     *
     * @param string $filename
     * @return string
     * @throws CM_Exception_Invalid
     */
    public static function sanitizeFilename($filename) {
        $filename = (string) $filename;

        $strip = array("~", "`", "!", "@", "#", "$", "%", "^", "&", "*", "(", ")", "_", "=", "+", "[", "{", "]", "}", "\\", "|", ";", ":", "\"", "'",
            "&#8216;", "&#8217;", "&#8220;", "&#8221;", "&#8211;", "&#8212;", "â€”", "â€“", ",", "<", ">", "/", "?", "\0");
        $clean = trim(str_replace($strip, '', $filename));
        $clean = preg_replace('/\s+/', "-", $clean);
        if (empty($clean)) {
            throw new CM_Exception_Invalid('Invalid filename.');
        }
        return $clean;
    }

    /**
     * @param string $path
     * @return CM_File
     */
    public static function factory($path) {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        switch ($extension) {
            case 'php':
                return new CM_File_Php($path);
            case 'js':
                return new CM_File_Javascript($path);
            case 'csv':
                return new CM_File_Csv($path);
            default:
                return new CM_File($path);
        }
    }
}
