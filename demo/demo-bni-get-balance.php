<?php
use IjorTengab\IBank\IBank;

// Prepare.
defined('IBANK_DEMO') or die('File ini tidak untuk dieksekusi langsung.' . PHP_EOL);
$information = isset($information) ? $information : false;
$information or die('Variable $information dibutuhkan.' . EOL);

// Execute.
$result = IBank::BNI('get_balancex', $information);
// $result = true;
// Cek error.
$error = IBank::getError(true);

empty($error) or die($error . EOL);

// Return.
return $result;
