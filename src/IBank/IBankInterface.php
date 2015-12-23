<?php
namespace IjorTengab\IBank;  

/**
 * Setiap module yang akan digunakan oleh framework IBank, maka perlu 
 * mendefinisikan setidaknya method ::action() dan ::getError().
 */
interface IBankInterface
{
    public static function action();    
    
    public static function getError();    
}
