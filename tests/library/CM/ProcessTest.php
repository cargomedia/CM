<?php

require_once dirname(dirname(__DIR__)) . '/bootstrap.php'; // Bootstrap the test explicitly when running in a separate process

class CM_ProcessTest extends CMTest_TestCase {

    /** @var resource */
    protected static $_file;

    public static function setupBeforeClass() {
        parent::setUpBeforeClass();
        self::$_file = tmpfile();
    }

    public static function tearDownAfterClass() {
        fclose(self::$_file);
        parent::tearDownAfterClass();
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testFork() {
        $process = CM_Process::getInstance();
        $seq1 = $process->fork(function() {});
        $seq2 = $process->fork(function() {});
        $seq3 = $process->fork(function() {});
        $this->assertSame(1, $seq2 - $seq1);
        $this->assertSame(1, $seq3 - $seq2);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testForkAndWaitForChildren() {
        $process = CM_Process::getInstance();
        for ($i = 1; $i <= 4; $i++) {
            CM_ProcessTest::writeln("Child $i forked.");
            $process->fork(function () use ($i) {
                $ms = 100 * $i;
                usleep($ms * 1000);
                CM_ProcessTest::writeln("Child $i terminated after $ms ms.");
            });
        }
        CM_ProcessTest::writeln('Parent waiting for 250 ms...');
        usleep(250000);
        CM_ProcessTest::writeln('Parent listening to children...');
        $process->waitForChildren(null, function () {
            CM_ProcessTest::writeln('All children terminated.');
        });
        CM_ProcessTest::writeln('Parent terminated.');

        $outputFileExpected = 'Child 1 forked.
Child 2 forked.
Child 3 forked.
Child 4 forked.
Parent waiting for 250 ms...
Child 1 terminated after 100 ms.
Child 2 terminated after 200 ms.
Parent listening to children...
Child 3 terminated after 300 ms.
Child 4 terminated after 400 ms.
All children terminated.
Parent terminated.
';
        rewind(self::$_file);
        $outputFileActual = fread(self::$_file, 8192);
        $this->assertEquals($outputFileExpected, $outputFileActual);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testForkAndListenForChildren() {
        $process = CM_Process::getInstance();
        for ($i = 1; $i <= 4; $i++) {
            CM_ProcessTest::writeln("Child $i forked.");
            $process->fork(function () use ($i) {
                $ms = 100 * $i - 25;
                usleep($ms * 1000);
                CM_ProcessTest::writeln("Child $i terminated after $ms ms.");
                ob_clean(); // Remove any test output buffered by phpUnit, which uses STDOUT itself to return test results from isolated processes
                return "Child $i heard by parent...";
            });
        }
        $terminationCallback = function() {
            CM_ProcessTest::writeln('Parent stops listening to children...');
        };
        $handleChildrenResponses = function(CM_Process_WorkloadResult $result) {
            CM_ProcessTest::writeln($result->getResult());
        };
        CM_ProcessTest::writeln('Parent waiting for 250 ms...');
        usleep(250000);
        CM_ProcessTest::writeln('Parent listening for children...');
        $responseList = $process->listenForChildren(null, $terminationCallback);
        \Functional\each($responseList, $handleChildrenResponses);
        CM_ProcessTest::writeln('Parent waiting for 100 ms...');
        usleep(100000);
        CM_ProcessTest::writeln('Parent listening for children...');
        $responseList = $process->listenForChildren(null, $terminationCallback);
        \Functional\each($responseList, $handleChildrenResponses);
        CM_ProcessTest::writeln('Parent waiting for 100 ms...');
        usleep(100000);
        CM_ProcessTest::writeln('Parent listening for children...');
        $responseList = $process->listenForChildren(null, $terminationCallback);
        \Functional\each($responseList, $handleChildrenResponses);
        CM_ProcessTest::writeln('Parent waiting for 100 ms...');
        usleep(100000);
        CM_ProcessTest::writeln('Parent listening for children...');
        $responseList = $process->listenForChildren(null, $terminationCallback);
        \Functional\each($responseList, $handleChildrenResponses);
        CM_ProcessTest::writeln('Parent terminated.');

        $outputFileExpected = 'Child 1 forked.
Child 2 forked.
Child 3 forked.
Child 4 forked.
Parent waiting for 250 ms...
Child 1 terminated after 75 ms.
Child 2 terminated after 175 ms.
Parent listening for children...
Parent stops listening to children...
Child 1 heard by parent...
Child 2 heard by parent...
Parent waiting for 100 ms...
Child 3 terminated after 275 ms.
Parent listening for children...
Parent stops listening to children...
Child 3 heard by parent...
Parent waiting for 100 ms...
Child 4 terminated after 375 ms.
Parent listening for children...
Parent stops listening to children...
Child 4 heard by parent...
Parent waiting for 100 ms...
Parent listening for children...
Parent stops listening to children...
Parent terminated.
';

        rewind(self::$_file);
        $outputFileActual = fread(self::$_file, 8192);
        $this->assertEquals($outputFileExpected, $outputFileActual);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testForkAndWaitForChildrenWithResults() {
        $bootloader = CM_Bootloader::getInstance();
        $exceptionHandlerBackup = $bootloader->getExceptionHandler();

        /**
         * Increase print-severity to make sure "child 3"'s exception output doesn't disturb phpunit
         */
        $exceptionHandler = new CM_ExceptionHandling_Handler_Cli();
        $exceptionHandler->setPrintSeverityMin(CM_Exception::FATAL);
        $bootloader->setExceptionHandler($exceptionHandler);

        $process = CM_Process::getInstance();
        $process->fork(function () {
            usleep(100 * 1000);
            return 'Child 1 finished';
        });
        $process->fork(function () {
            usleep(50 * 1000);
            return array('msg' => 'Child 2 finished');
        });
        $process->fork(function () {
            usleep(150 * 1000);
            throw new CM_Exception('Child 3 finished');
        });

        $workloadResultList = $process->waitForChildren();
        $this->assertCount(3, $workloadResultList);

        $this->assertSame('Child 1 finished', $workloadResultList[1]->getResult());
        $this->assertSame(null, $workloadResultList[1]->getException());

        $this->assertSame(array('msg' => 'Child 2 finished'), $workloadResultList[2]->getResult());
        $this->assertSame(null, $workloadResultList[2]->getException());

        $this->assertSame(null, $workloadResultList[3]->getResult());
        $this->assertSame('Child 3 finished', $workloadResultList[3]->getException()->getMessage());
        $errorLog = new CM_Paging_Log_Error();
        $this->assertSame(1, $errorLog->getCount());
        $this->assertContains('Child 3 finished', $errorLog->getItem(0)['msg']);

        $bootloader->setExceptionHandler($exceptionHandlerBackup);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testForkAndWaitForChildrenWithResultsAndExiting() {
        $process = CM_Process::getInstance();
        $process->fork(function () {
            usleep(50 * 1000);
            return 'Child 1 finished';
        });
        $process->fork(function () {
            exit(0);
        });
        $process->fork(function () {
            posix_kill(posix_getpid(), SIGTERM);
        });
        $process->fork(function () {
            posix_kill(posix_getpid(), SIGKILL);
        });

        $workloadResultList = $process->waitForChildren();
        $this->assertCount(4, $workloadResultList);

        $this->assertSame('Child 1 finished', $workloadResultList[1]->getResult());
        $this->assertSame(null, $workloadResultList[1]->getException());

        $this->assertSame(null, $workloadResultList[2]->getResult());
        $this->assertSame('Received no data from IPC stream.', $workloadResultList[2]->getException()->getMessage());

        $this->assertSame(null, $workloadResultList[3]->getResult());
        $this->assertSame('Received no data from IPC stream.', $workloadResultList[3]->getException()->getMessage());

        $this->assertSame(null, $workloadResultList[4]->getResult());
        $this->assertSame('Received no data from IPC stream.', $workloadResultList[4]->getException()->getMessage());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testKillChildren() {
        $loopEcho = function () {
            while (true) {
                usleep(50 * 1000);
                echo "hello\n";
            }
        };
        $process = CM_Process::getInstance();
        $process->fork($loopEcho);
        $process->fork($loopEcho);

        $pidListBefore = $this->_getChildrenPidList();
        $process->killChildren();
        $this->assertCount(2, $pidListBefore);
        $this->assertCount(0, $this->_getChildrenPidList());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testKillChildrenSigKill() {
        $loopEcho = function () {
            while (true) {
                usleep(50 * 1000);
                echo "hello\n";
            }
        };
        $loopEchoStayinAlive = function () {
            pcntl_signal(SIGTERM, function () {
                echo "Well, you can tell by the way I use my walk\n I'm a woman's man, no time to talk\n";
            }, false);
            while (true) {
                usleep(50 * 1000);
                echo "hello\n";
            }
        };

        $process = CM_Process::getInstance();
        $process->fork($loopEcho);
        $process->fork($loopEchoStayinAlive);
        $pidListBefore = $this->_getChildrenPidList();

        $timeStart = microtime(true);
        $process->killChildren(0.5);

        $this->assertCount(2, $pidListBefore);
        $this->assertCount(0, $this->_getChildrenPidList());
        $this->assertSameTime(0.5, microtime(true) - $timeStart, 0.15);

        $logError = new CM_Paging_Log_Error();
        $this->assertSame(1, $logError->getCount());
        $this->assertContains('killing with signal `9`', $logError->getItem(0)['msg']);
    }

    /**
     * @param string $message
     */
    public static function writeln($message) {
        fwrite(self::$_file, "$message\n");
    }

    /**
     * @return int[]
     * @throws CM_Exception
     */
    private function _getChildrenPidList() {
        $psCommand = 'ps axo pid,ppid,args';
        $psOutput = CM_Util::exec($psCommand);
        if (false === preg_match_all('/^\s*(?<pid>\d+)\s+(?<ppid>\d+)\s+(?<args>.+)$/m', $psOutput, $matches, PREG_SET_ORDER)) {
            throw new CM_Exception('Cannot parse ps output `' . $psOutput . '`.');
        }
        $pid = CM_Process::getInstance()->getProcessId();
        $pidList = array();
        foreach ($matches as $match) {
            if ($match['ppid'] == $pid && false === strpos($match['args'], $psCommand)) {
                $pidList[] = (int) $match['pid'];
            }
        }
        return $pidList;
    }
}
