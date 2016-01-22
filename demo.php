<?php
/**
 * @file
 *   demo.php
 *
 * @author
 *   IjorTengab
 *
 * File demonstrasi yang menyajikan langkah cepat siap santap untuk menikmati
 * fitur-fitur di project IBank.
 *
 * Perhatian: Jika Anda memindahkan file ini, sesuaikan kembali value dari
 * variable $autoload untuk lokasi path file autoload.php. 
 */

use IjorTengab\IBank\IBank;
use IjorTengab\IBank\Log;

/**
 * Composer autoloading class.
 */
$autoload = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../../autoload.php',
];

/**
 * Prepare anything.
 */
$found = false;
foreach ($autoload as $file) {
    if (file_exists($file)) {
        require $file;
        $found = true;
        break;
    }
}
$found or die('Composer autoload tidak ditemukan.' . PHP_EOL);

$eol = PHP_SAPI === 'cli' ? PHP_EOL : '<br />' . PHP_EOL;
$_pre = PHP_SAPI === 'cli' ? '' : '<pre>';
$pre_ = PHP_SAPI === 'cli' ? '' : '</pre>';
define('EOL', $eol);
define('_PRE', $_pre);
define('PRE_', $pre_);

################################################################################

/**
 *
 * Table of Contents
 *
 * +-------------------------------------------------------------------+------+
 * | Feature                                                           | Line |
 * +-------------------------------------------------------------------+------+
 * | BNI                                                               |      |
 * |    Get Saldo                                                      |      |
 * |    Transaksi Terbaru                                              |      |
 * |    Mutasi Rekening (limited)                                      |      |
 * |    Mutasi Rekening (unlimited)                                    |      |
 * | BCA                                                               |      |
 * +-------------------------------------------------------------------+------+
 *
 */

################################################################################

/**
 * Module BNI.
 * Fitur: Saldo.
 *
 * Untuk mendapatkan saldo terakhir di bank BNI,
 * isi value berikut sesuaikan dengan account anda.
 */
$information = [
    'username' => '',
    'password' => '',
    'account' => '',
];
/**
 * Hapus karakter '//' di awal baris di bawah ini untuk melakukan eksekusi
 * fungsi dan mencetak hasil dan error log. Kemudian save dan eksekusi
 * script ini di browser atau console.
 */
// $result = IBank::BNI('get_balance', $information);
// echo _PRE, print_r($result, true), PRE_, EOL;
// empty(Log::getError()) or die(print_r(Log::getError()) . EOL);

################################################################################

/**
 * Module BNI.
 * Fitur: Transaksi Terbaru.
 *
 * Untuk mendapatkan transaksi terbaru di bank BNI,
 * isi value berikut sesuaikan dengan account anda.
 */
$information = [
    'username' => '',
    'password' => '',
    'account' => '',
];
/**
 * Hapus karakter '//' di awal baris di bawah ini untuk melakukan eksekusi
 * fungsi dan mencetak hasil dan error log. Kemudian save dan eksekusi
 * script ini di browser atau console.
 */
// $result = IBank::BNI('get_transaction', $information);
// echo _PRE, print_r($result, true), PRE_, EOL;
// empty(Log::getError()) or die(print_r(Log::getError()) . EOL);

################################################################################

/**
 * Module BNI.
 * Fitur: Mutasi Rekening (limited).
 *
 * Untuk mendapatkan mutasi rekening (limited) di bank BNI,
 * isi value berikut sesuaikan dengan account anda.
 * Limit maksimal hanya 200 baris.
 */
$information = [
    'username' => '',
    'password' => '',
    'account' => '',
    'sort' => 'DESC',
    'range' => 'yesterday',
    'limit' => 100,
];
/**
 * Hapus karakter '//' di awal baris di bawah ini untuk melakukan eksekusi
 * fungsi dan mencetak hasil dan error log. Kemudian save dan eksekusi
 * script ini di browser atau console.
 */
// $result = require 'demo-bni-get-transaction-limited.php';
// echo _PRE, print_r($result, true), PRE_, EOL;

################################################################################

/**
 * Module BNI.
 * Fitur: Mutasi Rekening (unlimited).
 *
 * Untuk mendapatkan mutasi rekening (unlimited) di bank BNI,
 * isi value berikut sesuaikan dengan account anda.
 * Peluang gagal lebih besar dibandingkan limited.
 */
$information = [
    'username' => '',
    'password' => '',
    'account' => '',
    'sort' => 'DESC',
    'range' => 'yesterday',
];
/**
 * Hapus karakter '//' di awal baris di bawah ini untuk melakukan eksekusi
 * fungsi dan mencetak hasil dan error log. Kemudian save dan eksekusi
 * script ini di browser atau console.
 */
// $result = require 'demo-bni-get-transaction-unlimited.php';
// echo _PRE, print_r($result, true), PRE_, EOL;

################################################################################

/**
 * Module BCA.
 * Fitur:
 *  - Saldo.
 *  - Mutasi Rekening.
 */


