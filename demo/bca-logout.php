<?php

use IjorTengab\IBank\IBank;
use IjorTengab\IBank\Log;

// 1. Tentukan lokasi file autoload.php yang dibuat oleh Composer.
$autoload[] =  __DIR__ . '/../vendor/autoload.php';
$autoload[] =  __DIR__ . '/../../../autoload.php';
$autoload[] = '/home/ijortengab/project/github/ibank/vendor/autoload.php';
array_map(function($f) {if (file_exists($f)) {require $f;}}, $autoload);

// 2. Tentukan informasi yang dibutuhkan, penjelasan lengkap lihat README.md.
$information['cwd'] = '/home/ijortengab/ibank'; // Optional.

// 3 Save dan jalankan file script ini di browser atau console.
echo (PHP_SAPI == 'cli') ? '' : '<pre>';

// 4. Execute. 
$result = IBank::BCA('logout', $information);
echo '$result: ', print_r($result, true), PHP_EOL;

// 5. Disarankan untuk mengecek log (error/notice/debug).
$log = Log::get();
echo '$log: ', print_r($log, true), PHP_EOL;
