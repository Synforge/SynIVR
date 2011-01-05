#!/usr/bin/php
<?php

require(dirname(__FILE__)."/lib/SynIVR.class.php");
require(dirname(__FILE__)."/lib/phpagi-2.14/phpagi.php");

$menu = (empty($argv[1])) ? 'default' : trim($argv[1]);

$ivr = new SynIVR($menu);
$ivr->run();

?>
