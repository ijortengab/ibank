<?php
/**
 * @file _demo.php
 *
 * File persiapan yang dibutuhkan oleh file demo.php
 */
$autoload = isset($autoload) ? $autoload : false;

$autoload or die('File ini tidak untuk dieksekusi langsung.' . PHP_EOL);

$found = false;
foreach ($autoload as $file) {
    if (file_exists($file)) {
        require $file;
        $found = true;
        break;
    }
}
$found or die('Autoload tidak ditemukan.' . PHP_EOL);

$eol = PHP_SAPI === 'cli' ? PHP_EOL : '<br />' . PHP_EOL;
$_pre = PHP_SAPI === 'cli' ? '' : '<pre>';
$pre_ = PHP_SAPI === 'cli' ? '' : '</pre>';
define('EOL', $eol);
define('_PRE', $_pre);
define('PRE_', $pre_);
