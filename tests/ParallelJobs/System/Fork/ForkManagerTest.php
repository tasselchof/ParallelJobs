<?php

/**
 * sudo memcached -d -u nobody -m 128 127.0.0.1 -p 11211 // to run memcached for tests
 */

namespace ParallelJobsTest\System\Fork;

use PHPUnit_Framework_TestCase as TestCase;
use ParallelJobs\System\Fork\ForkManager;
use Zend\Stdlib\CallbackHandler;
use Zend\ServiceManager;

class ManagerTest extends TestCase
{
    protected $sm;
    
    public function setUp()
    {
        require_once realpath(__DIR__.'/TestAsset/Job.php');
        require_once realpath(__DIR__.'/TestAsset/JobObject.php');
        require_once realpath(__DIR__.'/TestAsset/JobLongString.php');
        
        // ZF2 specifics tests
        require_once __DIR__ . '/../../../../Module.php';
        $module = new \ParallelJobs\Module();
        $serviceConfig = $module->getServiceConfig();
        $config = include __DIR__ . '/../../../../config/module.config.php';
        $this->sm = new ServiceManager\ServiceManager(new ServiceManager\Config($serviceConfig));
        $this->sm->setService('Config', $config);
        $this->sm->setAllowOverride(true);
    }
    
    protected function mockHandler()
    {
        $errorH = $this->getMock('ErrorHandler', array ('error_handler'));
        $errorH->expects($this->atLeastOnce())->method ('error_handler');
        set_error_handler (array($errorH, 'error_handler'));
    }
    
    public function testSimpleJob()
    {
        $jobObject = new Job();
        $job = new CallbackHandler(array($jobObject, 'doSomething'));

        $manager = new ForkManager();
        $manager->setShareResult(true);
        $manager->doTheJob($job, 'value');
        $manager->createChildren(2);
        $manager->wait();
        $results = $manager->getSharedResults();
        $manager->getContainer()->close();
        $this->assertEquals('ok', $results->getChild(1)->getResult());
    }
    
    public function testMultipleJob()
    {
        $jobObject = new Job();
        $job = new \Zend\Stdlib\CallbackHandler(array($jobObject, 'doSomething'));

        $manager = new ForkManager();
        $manager->setShareResult(true);
        $manager->doTheJob($job, 'value');
        $manager->doTheJobChild(1, array($jobObject, 'doOtherSomething'), array('value 1', 'value 2'));
        $manager->createChildren(2);
        $manager->wait();
        $results = $manager->getSharedResults();
        $manager->getContainer()->close();
        $this->assertEquals('ko', $results->getChild(1)->getResult());
        $this->assertEquals('ok', $results->getChild(2)->getResult());
    }
    
    public function testMultipleJobStart()
    {
        $jobObject = new Job();
        $job = new \Zend\Stdlib\CallbackHandler(array($jobObject, 'doSomething'));

        $manager = new ForkManager();
        $manager->setShareResult(true);
        $manager->setAutoStart(false);
        $manager->doTheJob($job, 'value');
        $manager->doTheJobChild(1, array($jobObject, 'doOtherSomething'), array('value 1', 'value 2'));
        $manager->createChildren(2);
        $manager->start();
        $manager->wait();
        $results = $manager->getSharedResults();
        $manager->getContainer()->close();
        $this->assertEquals('ko', $results->getChild(1)->getResult());
        $this->assertEquals('ok', $results->getChild(2)->getResult());
    }
    
    public function testMultipleJobBadStart()
    {
        $jobObject = new Job();
        $job = new \Zend\Stdlib\CallbackHandler(array($jobObject, 'doSomething'));

        $manager = new ForkManager();
        $manager->doTheJob($job, 'value');
        $manager->doTheJobChild(1, array($jobObject, 'doOtherSomething'), array('value 1', 'value 2'));
        $manager->createChildren(2);
        $this->setExpectedException('ParallelJobs\System\Fork\Exception\RuntimeException');
        $manager->start();
    }
    
    public function testMultipleJobTimeoutUnshareStopped()
    {
        $jobObject = new Job();
        $job = new \Zend\Stdlib\CallbackHandler(array($jobObject, 'doSomething'));

        $manager = new ForkManager();
        $manager->doTheJob($job, 'value');
        $manager->doTheJobChild(1, array($jobObject, 'doOtherSomething'), array('value 1', 'value 2'));
        $manager->timeout(1);
        $manager->createChildren(2);
        $manager->wait();
        $this->assertEquals(true, $manager->isStopped());
    }
    
    public function testMultipleJobTimeoutUnshareUnStopped()
    {
        $jobObject = new Job();
        $job = new \Zend\Stdlib\CallbackHandler(array($jobObject, 'doSomething'));

        $manager = new ForkManager();
        $manager->doTheJob($job, 'value');
        $manager->doTheJobChild(1, array($jobObject, 'doOtherSomething'), array('value 1', 'value 2'));
        $manager->timeout(30);
        $manager->createChildren(2);
        $manager->wait();
        $this->assertEquals(false, $manager->isStopped());
    }
    
    public function testMultipleJobNotShare()
    {
        $jobObject = new Job();
        $job = new \Zend\Stdlib\CallbackHandler(array($jobObject, 'doSomething'));

        $manager = new ForkManager();
        $manager->setShareResult(true);
        $this->assertEquals(false, $manager->getSharedResults());
        $manager->setShareResult(false);
        $manager->doTheJob($job, 'value');
        $manager->doTheJobChild(1, array($jobObject, 'doOtherSomething'), array('value 1', 'value 2'));
        $manager->createChildren(2);
        $manager->wait();
        $this->assertEquals(false, $manager->getSharedResults());
    }
    
    public function testMultipleJobShareAfterStarted()
    {
        $jobObject = new Job();
        $job = new \Zend\Stdlib\CallbackHandler(array($jobObject, 'doSomething'));

        $manager = new ForkManager();
        $manager->setShareResult(true);
        $manager->doTheJob($job, 'value');
        $manager->doTheJobChild(1, array($jobObject, 'doOtherSomething'), array('value 1', 'value 2'));
        $manager->createChildren(2);
        $this->setExpectedException('ParallelJobs\System\Fork\Exception\RuntimeException');
        $manager->setShareResult(false);
        $manager->wait();
    }
    
    public function testMultipleJobShareNotFinished()
    {
        $this->mockHandler();
        $jobObject = new Job();

        $manager = new ForkManager();
        $manager->setShareResult(true);
        $manager->doTheJob(array($jobObject, 'doSomething'), 'value');
        $manager->doTheJobChild(1, array($jobObject, 'doOtherSomething'), array('value 1', 'value 2'));
        $manager->createChildren(2);
        $this->assertEquals(false, $manager->getSharedResults());
        
        restore_error_handler ();
    }
    
    public function testMultipleJobMainKilled()
    {
        $jobObject = new Job();
        $job = new \Zend\Stdlib\CallbackHandler(array($jobObject, 'doSomething'));

        $manager = new ForkManager();
        $manager->doTheJob(array($jobObject, 'doOtherSomething'), 'value');
        $this->assertEquals(false, $manager->isStopped());
        $manager->broadcast(SIGINT);
        $this->assertEquals(true, $manager->isStopped());
    }
    
    public function testMultipleLimitNumJobsShare()
    {
        $this->setExpectedException('ParallelJobs\System\Fork\Exception\RuntimeException');
        $jobObject = new Job();
        $job = new \Zend\Stdlib\CallbackHandler(array($jobObject, 'doSomething'));

        $manager = new ForkManager();
        $manager->setShareResult(true);
        $manager->doTheJob($job, 'value');
        $this->assertEquals(32, $manager->getContainer()->max());
        $manager->createChildren(40);
        $manager->wait();
    }
    
    public function testMultipleNoLimitNumJobsUnshare()
    {
        $jobObject = new Job();
        $job = new \Zend\Stdlib\CallbackHandler(array($jobObject, 'doSomething'));

        $manager = new ForkManager();
        $manager->doTheJob($job, 'value');
        $manager->createChildren(40);
        $manager->wait();
    }
    
    public function testMultipleIncreaseLimitNumJobs()
    {
        $jobObject = new Job();
        $job = new \Zend\Stdlib\CallbackHandler(array($jobObject, 'doSomething'));

        $manager = new ForkManager();
        $manager->setShareResult(true);
        $manager->doTheJob($job, 'value');
        $manager->getContainer()->setBlocSize(4);
        $manager->getContainer()->setSegmentSize(256);
        $manager->createChildren(40);
        $manager->wait();
        $this->assertEquals(64, $manager->getContainer()->max());
        $results = $manager->getSharedResults();
        $this->assertEquals('ok', $results->getChild(40)->getResult());
        $this->assertEquals(40, $results->getChild(40)->getUid());
    }
    
    public function testMultipleJobsRewind()
    {
        $jobObject = new Job();
        $job = new \Zend\Stdlib\CallbackHandler(array($jobObject, 'doSomething'));
        $job2 = new \Zend\Stdlib\CallbackHandler(array($jobObject, 'doOtherSomething'));

        $manager = new ForkManager();
        $manager->setShareResult(true);
        $manager->doTheJob($job, 'value');
        $manager->createChildren(2);
        $manager->wait();
        $results = $manager->getSharedResults();
        $this->assertEquals('ok', $results->getChild(1)->getResult());
        $this->assertEquals('ok', $results->getChild(2)->getResult());
        $manager->doTheJob($job2, array('value', 'value 2'));
        $manager->rewind()->start();
        $manager->wait();
        $results = $manager->getSharedResults();
        $this->assertEquals(true, $manager->isForkParent());
        $this->assertEquals('ko', $results->getChild(1)->getResult());
        $this->assertEquals('ko', $results->getChild(2)->getResult());
        $manager->doTheJobChild(1, $job, 'value');
        $manager->rewind()->start();
        $manager->wait();
        $results = $manager->getSharedResults();
        $this->assertEquals(true, $manager->isForkParent());
        $this->assertEquals('ok', $results->getChild(1)->getResult());
        $this->assertEquals('ko', $results->getChild(2)->getResult());
    }
    
    public function testMultipleJobsRewindAfterStart()
    {
        $jobObject = new Job();
        $job = new \Zend\Stdlib\CallbackHandler(array($jobObject, 'doSomething'));

        $manager = new ForkManager();
        $manager->doTheJob($job, 'value');
        $manager->createChildren(2);
        $this->setExpectedException('ParallelJobs\System\Fork\Exception\RuntimeException');
        $manager->rewind();
        $manager->wait();
    }
    
    public function testMultipleJobsStartAndRewind()
    {
        $manager = new ForkManager();
        $this->setExpectedException('ParallelJobs\System\Fork\Exception\RuntimeException');
        $manager->rewind();
    }
    
    public function testMultipleJobsRewindWithChangeShare()
    {
        $jobObject = new Job();
        $job = new \Zend\Stdlib\CallbackHandler(array($jobObject, 'doSomething'));
        $job2 = new \Zend\Stdlib\CallbackHandler(array($jobObject, 'doOtherSomething'));

        $manager = new ForkManager();
        $manager->setShareResult(true);
        $manager->doTheJob($job, 'value');
        $manager->createChildren(2);
        $manager->wait();
        $results = $manager->getSharedResults();
        $this->assertEquals('ok', $results->getChild(1)->getResult());
        $this->assertEquals('ok', $results->getChild(2)->getResult());
        $manager->doTheJob($job2, array('value', 'value 2'));
        $manager->rewind()->setShareResult(false)->start();
        $manager->wait();
        $manager->doTheJobChild(1, $job, 'value');
        $manager->rewind()->setShareResult(true)->start();
        $manager->wait();
        $results = $manager->getSharedResults();
        $this->assertEquals('ok', $results->getChild(1)->getResult());
        $this->assertEquals('ko', $results->getChild(2)->getResult());
    }
    
    public function testMultipleJobsRewindWithBadChangeShare()
    {
        $jobObject = new Job();
        $job = new \Zend\Stdlib\CallbackHandler(array($jobObject, 'doSomething'));
        $job2 = new \Zend\Stdlib\CallbackHandler(array($jobObject, 'doOtherSomething'));

        $manager = new ForkManager();
        $manager->setShareResult(true);
        $manager->doTheJob($job, 'value');
        $manager->createChildren(2);
        $manager->wait();
        $results = $manager->getSharedResults();
        $this->assertEquals('ok', $results->getChild(1)->getResult());
        $this->assertEquals('ok', $results->getChild(2)->getResult());
        $manager->doTheJob($job2, array('value', 'value 2'));
        $manager->rewind()->setShareResult(false)->start();
        $manager->wait();
        $this->setExpectedException('ParallelJobs\System\Fork\Exception\RuntimeException');
        $manager->setShareResult(true);
    }
    
    public function testMultipleJobsRewindWithTimeout()
    {
        $jobObject = new Job();
        $job = new \Zend\Stdlib\CallbackHandler(array($jobObject, 'doSomething'));

        $manager = new ForkManager();
        $manager->doTheJob($job, 'value');
        $manager->timeout(3);
        $manager->createChildren(2);
        $manager->wait();
        
        for($i = 0; $i < 3; $i++) {
            $manager->rewind()->start();
            $manager->wait();
            $this->assertEquals(false, $manager->isStopped());
        }
        $this->assertEquals(false, $manager->isStopped());
    }
    
    public function testMultipleJobsInLoop()
    {
        $jobObject = new Job();
        
        $manager = new ForkManager();
        $manager->setAutoStart(false);
        $manager->setShareResult(true);
        $manager->createChildren(2);
        for($i = 0; $i < 3; $i++) {
            if($i%2) {
                $manager->doTheJob(array($jobObject, 'doSomething'), 'value');
            }
            else {
                $manager->doTheJob(array($jobObject, 'doOtherSomething'), array('value', 'value 2'));
            }
            $manager->start();
            $manager->wait();
            $results = $manager->getSharedResults();
            if($i%2) {
                $this->assertEquals('ok', $results->getChild(1)->getResult());
            }
            else {
                $this->assertEquals('ko', $results->getChild(2)->getResult());
            }
            $manager->rewind();
        }
    }
    
    public function testMultipleJobsWhithShareObject()
    {
        $job = new Job();
        $jobObject = new JobObject();

        $manager = new ForkManager();
        $manager->setShareResult(true);
        $manager->doTheJob(array($jobObject, 'doSomething'), 'value');
        $manager->doTheJobChild(2, array($job, 'doSomething'), 'value');
        $manager->createChildren(2);
        $manager->wait();
        $results = $manager->getSharedResults();
        
        $this->assertEquals('nc', $results->getChild(1)->getResult());
        $this->assertEquals('ok', $results->getChild(2)->getResult());
    }
    
    public function testMultipleJobsWhithShareObjectString()
    {
        $jobObject = new JobInvalidObject();

        $manager = new ForkManager();
        $manager->setShareResult(true);
        $manager->doTheJob(array($jobObject, 'doSomething'), 'value');
        $manager->createChildren(2);
        $manager->wait();
        $results = $manager->getSharedResults();
        
        $this->assertEquals('', $results->getChild(1)->getResult());
        $this->assertEquals('', $results->getChild(2)->getResult());
    }
    
    public function testMultipleJobsWhithShareLongString()
    {
        $jobObject = new JobLongString();

        $manager = new ForkManager();
        $manager->setShareResult(true);
        $manager->doTheJob(array($jobObject, 'doSomething'), 'value');
        $manager->createChildren(2);
        $manager->wait();
        $results = $manager->getSharedResults();
        $size = $manager->getContainer()->getBlocSize();
        $this->assertEquals($size, strlen($results->getChild(1)->getResult()));
        $this->assertEquals(substr('azertyuiopazertyuiopazertyuiopazertyuiop', 0, $size), $results->getChild(1)->getResult());
    }
    
    public function testMultipleJobsWhithFileContainer()
    {
        $jobObject = new JobObject();

        $manager = new ForkManager();
        $manager->setContainer(new \ParallelJobs\System\Fork\Storage\File());
        $manager->setShareResult(true);
        $manager->doTheJob(array($jobObject, 'doSomething'), 'value');
        $manager->createChildren(1);
        $manager->wait();
        $results = $manager->getSharedResults();
        
        $this->assertEquals(true, is_object($results->getChild(1)->getResult()));
        $this->assertEquals('ParallelJobs\System\Fork\Storage\File', get_class($manager->getContainer()));
        $this->assertInstanceOf('ParallelJobsTest\System\Fork\JobObjectString', $results->getChild(1)->getResult());
    }
    
    public function testMultipleJobsWhithBadFileContainer()
    {
        $jobObject = new JobObject();

        $manager = new ForkManager();
        $manager->setContainer(new \ParallelJobs\System\Fork\Storage\File('./unknow-directory'));
        $manager->setShareResult(true);
        $manager->doTheJob(array($jobObject, 'doSomething'), 'value');
        $manager->createChildren(1);
        $manager->wait();
        
        $results = $manager->getSharedResults();
        $this->assertEquals(false, $results->getChild(1)->getResult());
    }
    
    public function testMultipleJobsWhithMemcachedContainer()
    {
        $jobObject = new JobObject();

        $manager = new ForkManager();
        $manager->setContainer(new \ParallelJobs\System\Fork\Storage\Memcached());
        $manager->setShareResult(true);
        $manager->doTheJob(array($jobObject, 'doSomething'), 'value');
        $manager->createChildren(1);
        $manager->wait();
        $results = $manager->getSharedResults();
        
        $this->assertEquals(true, is_object($results->getChild(1)->getResult()));
        $this->assertEquals('ParallelJobs\System\Fork\Storage\Memcached', get_class($manager->getContainer()));
        $this->assertInstanceOf('ParallelJobsTest\System\Fork\JobObjectString', $results->getChild(1)->getResult());
    }
    
    public function testWithoutJobRegister()
    {
        $jobObject = new JobObjectReturnParam();

        $manager = new ForkManager();
        $manager->setContainer(new \ParallelJobs\System\Fork\Storage\Memcached());
        $manager->setShareResult(true);
        $object = new \stdClass();
        $object->key = 'value';
        $manager->doTheJob(array($jobObject, 'doSomething'), $object);
        $manager->createChildren(1);
        $manager->wait();
        $results = $manager->getSharedResults();
        
        $child1 = $results->getChild(1)->getResult();
        $this->assertEquals(true, is_object($child1));
        $this->assertEquals('value', $child1->key);
        $this->assertEquals('ParallelJobs\System\Fork\Storage\Memcached', get_class($manager->getContainer()));
    }
    
    public function testCanRegisterObjectInJobParams()
    {
        $manager = new ForkManager();
        $manager->setShareResult(true);
        $manager->createChildren(2);
        $manager->wait();
        $results = $manager->getSharedResults();
        $manager->getContainer()->close();
        $this->assertEquals(null, $results->getChild(1)->getResult());
    }
    
    // ZF2 specifics tests
    public function testCanRetrieveFactory()
    {
        $manager = $this->sm->get('ForkManager');
        $this->assertEquals('ParallelJobs\System\Fork\ForkManager', get_class($manager));
    }
    
    public function testCanCreateForkWithFileContainer()
    {
        $config = $this->sm->get('Config');
        $config['fork_manager']['container'] = 'fork_manager_file_container';
        $this->sm->setService('Config', $config);
        $manager = $this->sm->get('ForkManager');
        $this->assertEquals('ParallelJobs\System\Fork\ForkManager', get_class($manager));
        $this->assertEquals('ParallelJobs\System\Fork\Storage\File', get_class($manager->getContainer()));
        
        $job = new \Zend\Stdlib\CallbackHandler(array(new Job(), 'doSomething'));
        $manager->doTheJob($job, 'value');
        $manager->createChildren(2);
        $manager->wait();
        $results = $manager->getSharedResults();
        $this->assertEquals('ok', $results->getChild(1)->getResult());
        $this->assertEquals('ok', $results->getChild(2)->getResult());
    }
    
    public function testCanCreateForkWithSegmentContainer()
    {
        $config = $this->sm->get('Config');
        $config['fork_manager']['container'] = 'fork_manager_segment_container';
        $this->sm->setService('Config', $config);
        $manager = $this->sm->get('ForkManager');
        $this->assertEquals('ParallelJobs\System\Fork\ForkManager', get_class($manager));
        $this->assertEquals('ParallelJobs\System\Fork\Storage\Segment', get_class($manager->getContainer()));
        
        $job = new \Zend\Stdlib\CallbackHandler(array(new Job(), 'doSomething'));
        $manager->doTheJob($job, 'value');
        $manager->createChildren(2);
        $manager->wait();
        $results = $manager->getSharedResults();
        $this->assertEquals('ok', $results->getChild(1)->getResult());
        $this->assertEquals('ok', $results->getChild(2)->getResult());
    }
    
    public function testCanCreateForkWithMemcachedContainer()
    {
        $config = $this->sm->get('Config');
        $config['fork_manager']['container'] = 'fork_manager_memcached_container';
        $this->sm->setService('Config', $config);
        $manager = $this->sm->get('ForkManager');
        $this->assertEquals('ParallelJobs\System\Fork\ForkManager', get_class($manager));
        $this->assertEquals('ParallelJobs\System\Fork\Storage\Memcached', get_class($manager->getContainer()));
        
        $job = new \Zend\Stdlib\CallbackHandler(array(new Job(), 'doSomething'));
        $manager->doTheJob($job, 'value');
        $manager->createChildren(2);
        $manager->wait();
        $results = $manager->getSharedResults();
        $this->assertEquals('ok', $results->getChild(1)->getResult());
        $this->assertEquals('ok', $results->getChild(2)->getResult());
    }
        
    public function testCanSetTheContainerWithFileFactory()
    {
        $manager = $this->sm->get('ForkManager');
        $memcachedContainer = $this->sm->get('ForkManagerFileContainer');
        $manager->setContainer($memcachedContainer);
        $this->assertEquals('ParallelJobs\System\Fork\ForkManager', get_class($manager));
        $this->assertEquals('ParallelJobs\System\Fork\Storage\File', get_class($manager->getContainer()));
        
        $job = new \Zend\Stdlib\CallbackHandler(array(new Job(), 'doSomething'));
        $manager->doTheJob($job, 'value');
        $manager->createChildren(2);
        $manager->wait();
        $results = $manager->getSharedResults();
        $this->assertEquals('ok', $results->getChild(1)->getResult());
        $this->assertEquals('ok', $results->getChild(2)->getResult());
    }
    
    public function testCanSetTheContainerWithSegmentFactory()
    {
        $manager = $this->sm->get('ForkManager');
        $memcachedContainer = $this->sm->get('ForkManagerSegmentContainer');
        $manager->setContainer($memcachedContainer);
        $this->assertEquals('ParallelJobs\System\Fork\ForkManager', get_class($manager));
        $this->assertEquals('ParallelJobs\System\Fork\Storage\Segment', get_class($manager->getContainer()));
        
        $job = new \Zend\Stdlib\CallbackHandler(array(new Job(), 'doSomething'));
        $manager->doTheJob($job, 'value');
        $manager->createChildren(2);
        $manager->wait();
        $results = $manager->getSharedResults();
        $this->assertEquals('ok', $results->getChild(1)->getResult());
        $this->assertEquals('ok', $results->getChild(2)->getResult());
    }
    
    public function testCanSetTheContainerWithMemcachedFactory()
    {
        $manager = $this->sm->get('ForkManager');
        $memcachedContainer = $this->sm->get('ForkManagerMemcachedContainer');
        $manager->setContainer($memcachedContainer);
        $this->assertEquals('ParallelJobs\System\Fork\ForkManager', get_class($manager));
        $this->assertEquals('ParallelJobs\System\Fork\Storage\Memcached', get_class($manager->getContainer()));
        
        $job = new \Zend\Stdlib\CallbackHandler(array(new Job(), 'doSomething'));
        $manager->doTheJob($job, 'value');
        $manager->createChildren(2);
        $manager->wait();
        $results = $manager->getSharedResults();
        $this->assertEquals('ok', $results->getChild(1)->getResult());
        $this->assertEquals('ok', $results->getChild(2)->getResult());
    }
}