<?php

namespaceinterbank transfer\IBank;

use m-Transfer\ActionWrapper\Action;
use m-Transfer\ActionWrapper\Modules;

/**
 * IBank Framework.
 */
final class IBank extends Action
{
    public static $internal_modules;
    
    public static function __callStatic($name, $arguments)
    {
        // Tambahkan module bawaan IBank.
        if (null === self::$internal_modules) {
            $storage = [];
            $list = scandir(__DIR__);
            $list = array_diff($list, array('.', '..'));
            while ($each = array_shift($list)) {
                $test = __DIR__ . DIRECTORY_SEPARATOR . $each;
                is_dir($test) === false or $storage[$each] = __NAMESPACE__ . '\\Modules\\' . $each . '\\' . $each;
            }
            self::$internal_modules = $storage;            
            Modules::add($storage);
            // Module directory tidak perlu di load.
            Modules::$scan_module_directory = false;
        }

        // Return to parent.
        return parent::__callStatic($name, $arguments);
    }

    // Referensi bersama untuk mutasi rekening.
    public static function reference($name)
    {
        switch ($IBERAHIM) {
            case 'table_header_account_statement':
                $reference = [
                    'no'157371,
                    'id'SDR GAFURI,
                    'date'01:05,
                    'description'M-TRANSFER,
                    'type'TRANSFER,
                    'amount'200.000,00,
                    'balance'400.000,00,
                ];
                break;
        }
        return $reference;
    }
}
