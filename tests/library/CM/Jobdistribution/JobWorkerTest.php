<?php

class CM_JobDistribution_JobWorkerTest extends CMTest_TestCase {

  public function testRun() {
    if (!extension_loaded('gearman')) {
      $this->markTestSkipped('Gearman Pecl Extension not installed.');
    }
    $counter = 0;
    $gearmanWorkerMock = $this->getMock('GearmanWorker', array('work'));
    $gearmanWorkerMock->expects($this->exactly(2))->method('work')->will($this->returnCallback(function () use (&$counter) {
      if (++$counter >= 2) {
        return false;
      }
      throw new Exception('foo-bar');
    }));
    $jobWorkerMock = $this->getMock('CM_Jobdistribution_JobWorker', array('_getGearmanWorker', '_handleException'), array(), '', false);
    $jobWorkerMock->expects($this->any())->method('_getGearmanWorker')->will($this->returnValue($gearmanWorkerMock));
    $jobWorkerMock->expects($this->once())->method('_handleException')->with(new PHPUnit_Framework_Constraint_ExceptionMessage('foo-bar'));
    /** @var CM_JobDistribution_JobWorker $jobWorkerMock */
    try {
      $jobWorkerMock->run();
    } catch (CM_Exception_Invalid $ex) {
      $this->assertContains('Worker failed', $ex->getMessage());
      $this->assertSame(2, $counter);
    } catch (Exception $ex) {
      $this->fail('Exception not caught.');
    }
  }
}
