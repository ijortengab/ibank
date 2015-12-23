<?php
namespace IjorTengab\IBank;

/**
 * Trait yang menyajikan fungsi cepat membuat instance dan menyediakan method
 * yang dibutuhkan oleh interface IBankInterface. Trait ini khusus digunakan
 * bagi class yang meng-extends abstract Web Crawler. 
 */
trait IBankTrait
{

    protected static $_error;

    /**
     * Create a new instance of object.
     *
     * @param $information mixed
     *   Jika string, maka diasumsikan sebagai json, nantinya akan didecode
     *   sehingga menjadi array.
     */
    public static function init($information = null)
    {        
        $instance = new self;
        if (is_string($information)) {
            $information = trim($information);
            $information = json_decode($information, true);
        }
        $information = (array) $information;        
        
        foreach ($information as $key => $value) {
            $instance->set($key, $value);
        }        
        return $instance;
    }

    public static function getError()
    {
        return self::$_error;
    }

    public static function action()
    {
        list($action, $information) = func_get_args();        
        $instance = self::init($information);
        $instance->target = $action;
        $instance->execute();
        self::$_error = $instance->error;
        return $instance->result;
    }

}
