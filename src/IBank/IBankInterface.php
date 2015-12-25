<?php
namespace IjorTengab\IBank;

/**
 * Setiap module yang akan digunakan oleh framework IBank, maka perlu
 * mendefinisikan method berikut.
 */
interface IBankInterface
{
    public function setAction($action);
    public function setInformation($key, $value);
    public function runAction();
    public function getResult();
}
