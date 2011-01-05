#!/usr/bin/php
<?php

include(dirname(__FILE__)."/lib/Class.IVR.php");
include(dirname(__FILE__)."/lib/phpagi-2.14/phpagi.php");

$menu = (empty($argv[1]) ? 'default' : $argv[1];

$agi->verbose("IVR Run with menu ID of: {$menu}");

$ivr = new IVR($agi, $menu);
$ivr->run();

?>
