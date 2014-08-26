<?php

namespace UniMapper\Nette;

use Nette\Caching;

class Cache extends \UniMapper\Cache
{

    /** @var \Nette\Caching\Cache */
    protected $cache;

    public function __construct(Caching\IStorage $storage)
    {
        $this->cache = new Caching\Cache($storage, "UniMapper.Cache");
    }

    public function load($key)
    {
        return $this->cache->load($key);
    }

    public function remove($key)
    {
        $this->cache->remove($key);
    }

    public function save($key, $data, array $files)
    {
        $this->cache->save($key, $data, [Caching\Cache::FILES => $files]);
    }

}