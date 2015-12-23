<?php
namespace IjorTengab\IBank;

/**
 * IBank Framework.
 */
class IBank {

    /**
     * Daftar bank-bank yang didukung oleh class ini.
     * Property ini secara core akan diisi oleh method ::loadInternal()
     * dan secara extended akan diisi melalui method ::register().
     */
    protected static $bank;

    /**
     * Daftar interface yang dibutuhkan oleh class ini.
     */
    protected static $interface_require = [
        __NAMESPACE__ . '\\' . 'IBankInterface',
    ];

    /**
     * Property tempat menampung error yang terjadi selama proses.
     */
    protected static $error;

    /**
     * Mendaftarkan module external untuk bisa terintegrasi dengan framework
     * IBank ini.
     */
    public static function register($name, $callback)
    {
        $bank = self::getBank();
        $callback = '\\' . ltrim($callback, '\\');
        $bank[$name] = $callback;
        self::$bank = $bank;
    }

    /**
     * Mendapatkan property $error.
     */
    public static function getError()
    {
        return self::$error;
    }

    /**
     * Mendapatkan property $bank.
     */
    protected static function getBank()
    {
        if (self::$bank === null) {
            // Get default.
            self::$bank = self::loadInternal();
        }
        return self::$bank;
    }

    /**
     * Dynamically calling static method. Menangkap semua method static
     * yang belum didefinisikan dan diasumsikan sebagai method untuk melakukan
     * action terkait yang terdaftar pada property $bank. 
     */
    public static function __callStatic($name, $arguments)
    {
        try {
            // Start verify.
            $bank = self::getBank();
            if (!array_key_exists($name, $bank)) {
                throw new \Exception(str_replace('@name', $name, 'Callback for "@name" not registered.'));
            }
            if (empty(count($arguments))) {
                throw new \Exception('Action has not been defined.');
            }
            $class = $bank[$name];
            if (!class_exists($class)) {
                throw new \Exception('Class has not been defined.');
            }
            $interface_require = self::$interface_require;
            $diff = array_diff($interface_require, class_implements($class));
            if (!empty($diff)) {
                throw new \Exception(str_replace('@diff', implode(', ', $diff), 'Class has not implements required interface: @diff.'));
            }
            // Finish verify.
            $action = array_shift($arguments);
            $information = array_shift($arguments);
            $result = call_user_func_array(array($class, 'action'), array($action, $information));
            self::$error = call_user_func(array($class, 'getError'));
            return $result;
        }
        catch (\Exception $e) {
            self::$error = $e->getMessage();
        }
    }

    /**
     * Memuat daftar bank yang sudah didefinisikan oleh core IBank.
     */
    protected static function loadInternal()
    {
        static $storage;
        if (null === $storage) {
            $list = scandir(__DIR__);
            $list = array_diff($list, array('.', '..'));
            while ($each = array_shift($list)) {
                $test = __DIR__ . DIRECTORY_SEPARATOR . $each;
                is_dir($test) === false or $storage[$each] = '\\' . __NAMESPACE__ . '\\' . $each . '\\' . $each;
            }
        }
        return $storage;
    }
}
