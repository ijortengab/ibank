<?php
namespace IjorTengab\IBank;

/**
 * Trait khusus bagi class yang mengextends abstract Web Crawler, menyajikan
 * method siap pakai yang diperlukan karena meng-implements interface
 * IBankInterface. Menyediakan method ::action() sebagai sarana mandiri untuk
 * bisa melakukan tugas, tanpa perlu framework IBank.
 */
trait IBankWebCrawlerTrait
{

    protected static $_error;

    /**
     * Create a new instance of object.
     *
     * @param $information mixed
     *   Jika string, maka diasumsikan sebagai json, nantinya akan didecode
     *   sehingga menjadi array.
     */
    protected static function init($information = null)
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

    // public static function getError()
    // {
        // return self::$_error;
    // }

    public static function action($action, $information)
    {
        // list($action, $information) = func_get_args();
        $instance = self::init($information);
        $instance->target = $action;
        $instance->execute();
        // self::$_error = $instance->error;
        return $instance->result;
    }

    public function setAction($action)
    {
        $this->target = $action;
    }
    public function setInformation($key, $value)
    {
        return $this->set($key, $value);
    }
    public function runAction()
    {
        return $this->execute();
    }
    public function getResult()
    {
        return $this->result;
    }

}
