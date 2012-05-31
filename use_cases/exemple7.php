<?php

$jobObject = new Job();

$manager = new \ZFPJ\System\Fork\ForkManager();
$manager->doTheJob(array($jobObject, 'doSomething'), 'value');
$manager->doTheJobChild(1, array($jobObject, 'doOtherSomething'), array('value 1', 'value 2'));
$manager->timeout(60);
$manager->createChildren(2);
$manager->wait();

echo intval($manager->isStopped());
echo "\n";