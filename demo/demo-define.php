<?php
$eol = PHP_SAPI === 'cli' ? PHP_EOL : '<br />' . PHP_EOL;
$_pre = PHP_SAPI === 'cli' ? '' : '<pre>';
$pre_ = PHP_SAPI === 'cli' ? '' : '</pre>';
define('EOL', $eol);
define('_PRE', $_pre);
define('PRE_', $pre_);
define('IBANK_DEMO', true);
