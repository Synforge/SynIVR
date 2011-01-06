#!/usr/bin/php
<?php

require(dirname(__FILE__)."/lib/SynIVR.class.php");
require(dirname(__FILE__)."/lib/phpagi-2.14/phpagi.php");

//Configuration file
$config = (empty($argv[1])) ? 'synivr.conf' : trim($argv[1]);

//Menu Name
$menu = (empty($argv[2])) ? 'default' : trim($argv[2]);

$ivr = new SynIVR($config, $menu);
$ivr->run();

?>
