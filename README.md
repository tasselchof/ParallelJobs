ZF2 ParallelJobs module
============

Version 1.5.0 Created by [Vincent Blanchon](http://developpeur-zend-framework.fr/)

Introduction
------------

ZF2 module ParallelJobs provide a fork manager.
Fork manager can create children, run specific jobs and share result.
/!\ To share the results, just download and active the [SimpleMemoryShared](https://github.com/blanchonvincent/SimpleMemoryShared) module 
which provide some storage : segment memory, memcache and file.

Just add "ParallelJobs" (and "SimpleMemoryShared" if you want share the process result) directoy in your ZF2 modules directory.
To run the tests, run "run.sh" in the "tests" directory.

Fork manager usage
------------

Class Job exemple :

```php
<?php

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

class JobObject
{
    public function doSomething($arg)
    {
        sleep(1);
        // complex job
        return new JobObjectString;
    }
}

class JobObjectString
{
    private $attribute = 'nc';
    
    public function __toString()
    {
        return $this->attribute;
    }
}

class JobObjectSimple
{
    public function doSomething($arg)
    {
        $stdClass = new \stdClass();
        $stdClass->key = 'ok';
        return $stdClass;
    }
}
```

1) Exemple with one process for a simple job :
    
```php
<?php

$jobObject = new Job();

$manager = $this->getServiceLocator()->get('ForkManager');
$manager->setShareResult(true);
$manager->doTheJob(array($jobObject, 'doSomething'), 'value');
$manager->createChildren(1);
$manager->wait();
$results = $manager->getSharedResults();

echo $results->getChild(1)->getResult();
```

Run in command line :

    php index.php // display "ok"

2) Exemple with two process for multiple job :

```php
<?php 

$jobObject = new Job();
$job = new \Zend\Stdlib\CallbackHandler(array($jobObject, 'doSomething'));
$job2 = new \Zend\Stdlib\CallbackHandler(array($jobObject, 'doOtherSomething'));

$manager = $this->getServiceLocator()->get('ForkManager');
$manager->setShareResult(true);
$manager->doTheJob($job, 'value');
$manager->doTheJobChild(1, $job2, array('value 1', 'value 2'));
$manager->createChildren(2);
$manager->wait();
$results = $manager->getSharedResults();

echo $results->getChild(1)->getResult();
echo ", ";
echo $results->getChild(2)->getResult();
```
    
Run in command line :

    php index.php // display "ko, ok"

3) Exemple with two process for multiple job with file system shared :

```php
<?php 

$jobObject = new Job();
$jobObjectSimple = new JobObjectSimple();
$job = new \Zend\Stdlib\CallbackHandler(array($jobObject, 'doSomething'));
$job2 = new \Zend\Stdlib\CallbackHandler(array($jobObjectSimple, 'doSomething'));

$manager = $this->getServiceLocator()->get('ForkManager');
$manager->setStorage('file');
$manager->setShareResult(true);
$manager->doTheJob($job, 'value');
$manager->doTheJobChild(1, $job2, array('value 1', 'value 2'));
$manager->createChildren(2);
$manager->wait();
$results = $manager->getSharedResults();

echo get_class($results->getChild(1)->getResult());
echo ", ";
echo $results->getChild(2)->getResult();
```
    
Run in command line :

    php index.php // display "stdClass, ok"

4) Exemple with two process for multiple job with memcache system shared :

```php
<?php 

$jobObject = new Job();
$jobObjectSimple = new JobObjectSimple();
$job = new \Zend\Stdlib\CallbackHandler(array($jobObject, 'doSomething'));
$job2 = new \Zend\Stdlib\CallbackHandler(array($jobObjectSimple, 'doSomething'));

$manager = $this->getServiceLocator()->get('ForkManager');
$manager->setStorage('memcached', array('host' => '127.0.0.1','port' => 11211));
$manager->setShareResult(true);
$manager->doTheJob($job, 'value');
$manager->doTheJobChild(1, $job2, array('value 1', 'value 2'));
$manager->createChildren(2);
$manager->wait();
$results = $manager->getSharedResults();

echo get_class($results->getChild(1)->getResult());
echo ", ";
echo $results->getChild(2)->getResult();
```
    
Run in command line :

    php index.php // display "stdClass, ok"

5) Exemple with several job :

```php
<?php 

$jobObject = new Job();
$job = new \Zend\Stdlib\CallbackHandler(array($jobObject, 'doSomething'));

$manager = $this->getServiceLocator()->get('ForkManager');
$manager->setShareResult(true);
$manager->doTheJob($job, 'value');
$manager->createChildren(8);
$manager->wait();
$results = $manager->getSharedResults();

echo $results->getChild(1)->getResult();
echo ", ";
echo $results->getChild(8)->getResult();
```
    
Run in command line :

    php index.php // display "ok, ok"

6) Exemple with manage start :

```php
<?php

$jobObject = new Job();
$job = new \Zend\Stdlib\CallbackHandler(array($jobObject, 'doSomething'));
$job2 = new \Zend\Stdlib\CallbackHandler(array($jobObject, 'doOtherSomething'));   

$manager = $this->getServiceLocator()->get('ForkManager');
$manager->setShareResult(true);
$manager->setAutoStart(false);
$manager->doTheJob($job, 'value');
$manager->doTheJobChild(1, $job2, array('value 1', 'value 2'));
$manager->createChildren(2);
// do multiple tasks
$manager->start();
$manager->wait();
$results = $manager->getSharedResults();

echo $results->getChild(1)->getPid() . ':' . $results->getChild(1)->getResult();
echo ", ";
echo $results->getChild(2)->getPid() . ':' . $results->getChild(2)->getResult();
```

Run in command line :

    php index.php // display "8139:ko, 8140:ok"

7) Exemple with timeout and unshare :

```php
<?php

$jobObject = new Job();

$manager = $this->getServiceLocator()->get('ForkManager');
$manager->doTheJob(array($jobObject, 'doSomething'), 'value');
$manager->doTheJobChild(1, array($jobObject, 'doOtherSomething'), array('value 1', 'value 2'));
$manager->timeout(60);
$manager->createChildren(2);
$manager->wait();

echo intval($manager->isStopped());
```

Run in command line :

    php index.php // display "0"

8) Exemple with stop children :

```php
<?php

$jobObject = new Job();

$manager = $this->getServiceLocator()->get('ForkManager');
$manager->doTheJob(array($jobObject, 'doSomething'), 'value');
$manager->doTheJobChild(1, array($jobObject, 'doOtherSomething'), array('value 1', 'value 2'));
$manager->timeout(60);
$manager->createChildren(2);
/*
 *  ... do tasks here ...
 */
$manager->closeChildren();
```

Run in command line :

    php index.php

9) Exemple in loop :

```php
<?php

$jobObject = new Job();

$manager = $this->getServiceLocator()->get('ForkManager');
$manager->doTheJob(array($jobObject, 'doSomething'), 'value');
$manager->setAutoStart(false);
$manager->createChildren(2);
for($i = 0; $i < 3; $i++) {
    $manager->start();
    $manager->wait();
    $manager->rewind();
}
```

Run in command line :

    php index.php