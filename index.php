<?php

chdir(dirname(__DIR__));
require_once (getenv('ZF2_PATH') ?: 'vendor/ZendFramework/library') . '/Zend/Loader/AutoloaderFactory.php';
Zend\Loader\AutoloaderFactory::factory();
Zend\Loader\AutoloaderFactory::factory(array('Zend\Loader\ClassMapAutoloader' => array(include 'config/autoload_classmap.php')));

$jobObject = new Job();
$job = new \Zend\Stdlib\CallbackHandler(array($jobObject, 'doSomething'));

$manager = new \ZFPL\System\Fork\ForkManager();
$manager->setShareResult(true);
$manager->setAutoStart(false);
$manager->doTheJob($job, 'value');
$manager->doTheJobChild(1, array($jobObject, 'doOtherSomething'), array('value 1', 'value 2'));
$manager->createChildren(2);
// do multiple tasks
$manager->start();
$manager->wait();
$results = $manager->getSharedResults();
$manager->getContainer()->close();

echo $results->getChild(1)->getResult();
echo ", ";
echo $results->getChild(2)->getResult();


class Job
{
    public function doSomething($arg)
    {
        sleep(2);
        // complex job
        return 'ok';
    }

    public function doOtherSomething($arg1, $arg2)
    {
        sleep(1);
        // bad job
        return 'ko';
    }
}