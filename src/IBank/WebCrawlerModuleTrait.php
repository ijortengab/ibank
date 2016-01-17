<?php

namespace IjorTengab\IBank;

/**
 * Trait khusus bagi class (extends abstract Web Crawler) yang
 * meng-implements interface ModuleInterface. Membuat method yang dibutuhkan
 * interface tersebut dan disesuaikan dengan method pada abstract Web Crawler.
 */
trait WebCrawlerModuleTrait
{

    /**
     * Implements of ModuleInterface::getLog().
     */
    public function getLog($level = null)
    {
        $log = $this->log->get();
        if ($level === null) {
            return $log;
        }
        return array_key_exists($level, $log) ? $log[$level] : [];
    }

    /**
     * Implements of ModuleInterface::setAction().
     */
    public function setAction($action)
    {
        $this->target = $action;
    }

    /**
     * Implements of ModuleInterface::setInformation().
     */
    public function setInformation($information)
    {
        if (is_string($information)) {
            $information = trim($information);
            $information = json_decode($information, true);
        }
        $information = (array) $information;
        foreach ($information as $key => $value) {
            $this->set($key, $value);
        }
    }

    /**
     * Implements of ModuleInterface::runAction().
     */
    public function runAction()
    {
        return $this->execute();
    }

    /**
     * Implements of ModuleInterface::getResult().
     */
    public function getResult()
    {
        return $this->result;
    }
}
