<?php

namespace IjorTengab\IBank;

use IjorTengab\ActionWrapper\Action;
use IjorTengab\ActionWrapper\Modules;

/**
 * IBank Framework.
 */
final class IBank extends Action
{
    public static function __callStatic($name, $arguments)
    {
        // Tambahkan module bawaan IBank.
        static $storage;
        if (null === $storage) {
            $list = scandir(__DIR__);
            $list = array_diff($list, array('.', '..'));
            while ($each = array_shift($list)) {
                $test = __DIR__ . DIRECTORY_SEPARATOR . $each;
                is_dir($test) === false or $storage[$each] = __NAMESPACE__ . '\\' . $each . '\\' . $each;
            }
        }
        Modules::add($storage);
        // Module directory tidak perlu di load.
        Modules::$scan_module_directory = false;

        // Return to parent.
        return parent::__callStatic($name, $arguments);
    }

    // Referensi bersama untuk mutasi rekening.
    public static function reference($name)
    {
        switch ($name) {
            case 'table_header_account_statement':
                $return = [
                    'no',
                    'id',
                    'date',
                    'description',
                    'type',
                    'amount',
                    'balance',
                ];
                break;
        }
        return $return;
    }
}
