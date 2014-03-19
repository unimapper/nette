<?php

namespace UniMapper\Nette;

class Cache implements \UniMapper\Cache\ICache
{

    /** @var \Nette\Caching\Cache */
    protected $cache;

    public function __construct(\Nette\Caching\IStorage $storage)
    {
        $this->cache = new \Nette\Caching\Cache($storage, "UniMapper.Cache");
    }

    public function load($key)
    {
        return $this->cache->load($key);
    }

    public function remove($key)
    {
        $this->cache->remove($key);
    }

    public function save($key, $data, $file = null)
    {
        $dependencies = array();
        if ($file !== null) {
            $dependencies[\Nette\Caching\Cache::FILES] = $file;
        }
        $this->cache->save($key, $data, $dependencies);
    }

}