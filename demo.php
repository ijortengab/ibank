<?php
/**
 * @file
 *   demo.php
 *
 * @author
 *   IjorTengab
 *
 * File eksekusi yang menyajikan langkah cepat siap santap untuk menikmati
 * fitur-fitur di project IBank.
 *
 * Perhatian: Jika Anda memindahkan file ini, sesuaikan kembali value dari
 * variable $autoload dan $demo.
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

/**
 * Composer autoloading class.
 */
$autoload = __DIR__ . '/vendor/autoload.php';

/**
 * Demo directory.
 */
$demo = __DIR__ . '/demo';

/**
 * Prepare.
 */
set_include_path(rtrim($demo, '\\/') . PATH_SEPARATOR . get_include_path());
require $autoload;
require 'demo-define.php';

/**
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
 * fungsi dan mencetak hasil (variable $result). Kemudian save dan eksekusi
 * script ini di browser atau console.
 */
// $result = require 'demo-bni-get-balance.php';
// echo _PRE, print_r($result, true), PRE_, EOL;


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
 * fungsi dan mencetak hasil (variable $result). Kemudian save dan eksekusi
 * script ini di browser atau console.
 */
// $result = require 'demo-bni-get-transaction-limited.php';
// echo _PRE, print_r($result, true), PRE_, EOL;


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
 * fungsi dan mencetak hasil (variable $result). Kemudian save dan eksekusi
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
 * fungsi dan mencetak hasil (variable $result). Kemudian save dan eksekusi
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


