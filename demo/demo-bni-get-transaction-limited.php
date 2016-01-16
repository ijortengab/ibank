<?php
use IjorTengab\IBank\IBank;

// Prepare.
defined('IBANK_DEMO') or die('File ini tidak untuk dieksekusi langsung.' . PHP_EOL);
$information = isset($information) ? $information : false;
$information or die('Variable $information dibutuhkan.' . PHP_EOL);

// Execute.
$result = IBank::BNI('get_transaction', $information);

// Todo, cek error.

// Return.
return $result;
