<?php

class CM_File_Filesystem_Adapter_LocalTest extends CMTest_TestCase {

    /** @var CM_File_Filesystem_Adapter_Local */
    private $_adapter;

    protected function setUp() {
        $dir = CM_Bootloader::getInstance()->getDirTmp() . 'my-dir';
        $this->_adapter = new CM_File_Filesystem_Adapter_Local($dir);
        $this->_adapter->setup();
    }

    protected function tearDown() {
        CMTest_TH::clearEnv();
    }

    public function testConstructDefaultPrefix() {
        $pathFile = CM_Bootloader::getInstance()->getDirTmp() . 'foo';
        $adapter = new CM_File_Filesystem_Adapter_Local();
        $filesystem = new CM_File_Filesystem($adapter);
        $file = new CM_File($pathFile, $filesystem);
        $file->write('hello');

        $this->assertSame('/', $adapter->getPathPrefix());
        $this->assertSame('hello', $adapter->read($pathFile));
    }

    public function testRead() {
        $this->_adapter->write('foo', 'hello');
        $this->assertSame('hello', $this->_adapter->read('foo'));
    }

    /**
     * @expectedException CM_Exception
     * @expectedExceptionMessage Cannot read
     */
    public function testReadInvalidpath() {
        $this->_adapter->read('foo');
    }

    public function testWrite() {
        $this->_adapter->write('foo', 'hello');
        $this->assertTrue($this->_adapter->exists('foo'));
        $this->assertSame('hello', $this->_adapter->read('foo'));
    }

    /**
     * @expectedException CM_Exception
     * @expectedExceptionMessage Cannot write
     */
    public function testWriteInvalidPath() {
        $this->_adapter->write('/doesnotexist/foo', 'hello');
    }

    /**
     * @expectedException CM_Exception
     * @expectedExceptionMessage Cannot write
     */
    public function testWriteDirectory() {
        $this->_adapter->ensureDirectory('foo');
        $this->_adapter->write('foo', 'hello');
    }

    public function testWriteClearStatCache() {
        $this->_adapter->write('foo', '');
        $this->assertSame(0, $this->_adapter->getSize('foo'));
        $this->_adapter->write('foo', 'hello');
        $this->assertSame(5, $this->_adapter->getSize('foo'));
    }

    public function testAppend() {
        $this->_adapter->append('foo', 'hello');
        $this->_adapter->append('foo', 'world');
        $this->assertSame('helloworld', $this->_adapter->read('foo'));
    }

    /**
     * @expectedException CM_Exception
     * @expectedExceptionMessage Cannot append
     */
    public function testAppendInvalidPath() {
        $this->_adapter->append('/doesnotexist/foo', 'hello');
    }

    public function testExists() {
        $this->assertFalse($this->_adapter->exists('foo'));

        $this->_adapter->write('foo', 'hello');
        $this->assertTrue($this->_adapter->exists('foo'));
    }

    public function testExistsSymlink() {
        $this->_adapter->ensureDirectory('foo');
        symlink($this->_adapter->getPathPrefix() . '/foo', $this->_adapter->getPathPrefix() . '/link');
        $this->assertTrue($this->_adapter->exists('link'));

        $this->_adapter->delete('foo');
        $this->assertFalse($this->_adapter->exists('link'));

        $this->_adapter->ensureDirectory('foo');
        $this->assertTrue($this->_adapter->exists('link'));
    }

    public function testEnsureDirectory() {
        $this->assertFalse($this->_adapter->isDirectory('foo'));

        $this->_adapter->ensureDirectory('foo');
        $this->assertTrue($this->_adapter->isDirectory('foo'));

        $this->_adapter->ensureDirectory('foo');
        $this->assertTrue($this->_adapter->isDirectory('foo'));
    }

    /**
     * @expectedException CM_Exception
     * @expectedExceptionMessage Path exists but is not a directory
     */
    public function testEnsureDirectoryExistsFile() {
        $this->_adapter->write('foo', 'hello');
        $this->_adapter->ensureDirectory('foo');
    }

    public function testGetModified() {
        $this->_adapter->write('foo', 'hello');
        $this->assertSameTime(filemtime($this->_adapter->getPathPrefix() . '/foo'), $this->_adapter->getModified('foo'));
    }

    /**
     * @expectedException CM_Exception
     * @expectedExceptionMessage Cannot get modified time
     */
    public function testGetModifiedInvalidPath() {
        $this->_adapter->getModified('foo');
    }

    public function testDeleteFile() {
        $this->_adapter->delete('my-file');
        $this->_adapter->write('my-file', 'hello');
        $this->assertTrue($this->_adapter->exists('my-file'));

        $this->_adapter->delete('my-file');
        $this->assertFalse($this->_adapter->exists('my-file'));
    }

    public function testDeleteDirectory() {
        $this->_adapter->delete('my-dir');
        $this->_adapter->ensureDirectory('my-dir');
        $this->assertTrue($this->_adapter->exists('my-dir'));

        $this->_adapter->delete('my-dir');
        $this->assertFalse($this->_adapter->exists('my-dir'));
    }

    public function testDeleteLink() {
        $this->_adapter->ensureDirectory('foo');
        symlink($this->_adapter->getPathPrefix() . '/foo', $this->_adapter->getPathPrefix() . '/link');
        $this->assertTrue($this->_adapter->exists('link'));

        $this->_adapter->delete('link');
        $this->assertFalse($this->_adapter->exists('link'));
        $this->assertTrue($this->_adapter->exists('foo'));
    }

    public function testRename() {
        $this->_adapter->write('foo', 'hello');

        $this->_adapter->rename('foo', 'bar');
        $this->assertFalse($this->_adapter->exists('foo'));
        $this->assertTrue($this->_adapter->exists('bar'));
        $this->assertSame('hello', $this->_adapter->read('bar'));
    }

    /**
     * @expectedException CM_Exception
     * @expectedExceptionMessage Cannot rename
     */
    public function testRenameInvalidPath() {
        $this->_adapter->rename('foo', 'bar');
    }

    public function testCopy() {
        $this->_adapter->write('foo', 'hello');

        $this->_adapter->copy('foo', 'bar');
        $this->assertTrue($this->_adapter->exists('foo'));
        $this->assertTrue($this->_adapter->exists('bar'));
        $this->assertSame('hello', $this->_adapter->read('bar'));
    }

    /**
     * @expectedException CM_Exception
     * @expectedExceptionMessage Cannot copy
     */
    public function testCopyInvalidPath() {
        $this->_adapter->copy('foo', 'bar');
    }

    public function testGetSize() {
        $this->_adapter->write('foo', 'hello');
        $this->assertSame(5, $this->_adapter->getSize('foo'));
    }

    /**
     * @expectedException CM_Exception
     * @expectedExceptionMessage Cannot get size
     */
    public function testGetSizeInvalidPath() {
        $this->_adapter->getSize('foo');
    }

    public function testGetChecksum() {
        $this->_adapter->write('foo', 'hello');
        $this->assertSame(md5('hello'), $this->_adapter->getChecksum('foo'));
    }

    /**
     * @expectedException CM_Exception
     * @expectedExceptionMessage Cannot get md5
     */
    public function testGetChecksumInvalidPath() {
        $this->_adapter->getChecksum('foo');
    }

    public function testListByPrefix() {
        $filesystem = new CM_File_Filesystem($this->_adapter);

        $pathList = array(
            'foo/bar2',
            'foo/foobar/bar',
            'foo/bar',
        );
        foreach ($pathList as $path) {
            $file = new CM_File($path, $filesystem);
            $file->ensureParentDirectory();
            $file->write('hello');
        }

        $this->assertSame(array(
            'files' => array(
                'foo/foobar/bar',
                'foo/bar',
                'foo/bar2',
            ),
            'dirs'  => array(
                'foo/foobar',
                'foo',
            ),
        ), $this->_adapter->listByPrefix(''));

        $this->assertSame(array(
            'files' => array(
                'foo/foobar/bar',
                'foo/bar',
                'foo/bar2',
            ),
            'dirs'  => array(
                'foo/foobar',
            ),
        ), $this->_adapter->listByPrefix('/foo'));
    }

    public function testListByPrefixDoNotFollowSymlinks() {
        $filesystem = new CM_File_Filesystem($this->_adapter);
        $file = new CM_File('foo/bar/foo', $filesystem);
        $file->ensureParentDirectory();
        $file->write('hello');
        symlink($filesystem->getAdapter()->getPathPrefix() . '/foo', $filesystem->getAdapter()->getPathPrefix() . '/link');

        $this->assertSame(array(
            'files' => array(
                'foo/bar/foo',
            ),
            'dirs'  => array(
                'foo/bar',
                'foo',
                'link',
            ),
        ), $this->_adapter->listByPrefix(''));
    }

    public function testListByPrefixNonexistent() {
        $this->assertSame(array(
            'files' => array(),
            'dirs'  => array(),
        ), $this->_adapter->listByPrefix('nonexistent'));
    }

    public function testListByPrefixFile() {
        $this->_adapter->write('/foo', 'hello');
        $this->assertSame(array(
            'files' => array(),
            'dirs'  => array(),
        ), $this->_adapter->listByPrefix('/foo'));
    }

    public function testEquals() {
        $adapter1 = new CM_File_Filesystem_Adapter_Local('/');
        $adapter2 = new CM_File_Filesystem_Adapter_Local('/');
        $adapter3 = new CM_File_Filesystem_Adapter_Local('/tmp');

        $this->assertFalse($adapter1->equals(null));
        $this->assertTrue($adapter1->equals($adapter1));
        $this->assertTrue($adapter1->equals($adapter2));
        $this->assertFalse($adapter1->equals($adapter3));
    }
}
