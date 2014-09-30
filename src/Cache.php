<?php

namespace UniMapper\Nette;

use Nette\Caching,
    UniMapper\Cache\ICache;

class Cache implements ICache
{

    /** @var \Nette\Caching\Cache */
    private $cache;

    private $options = [
        self::CALLBACKS => Caching\Cache::CALLBACKS,
        self::EXPIRE => Caching\Cache::EXPIRE,
        self::FILES => Caching\Cache::FILES,
        self::ITEMS => Caching\Cache::ITEMS,
        self::PRIORITY => Caching\Cache::PRIORITY,
        self::SLIDING => Caching\Cache::SLIDING,
        self::TAGS => Caching\Cache::TAGS
    ];

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

    public function save($key, $data, array $options = [])
    {
        $netteOptions = [];
        foreach ($options as $type => $option) {
            if (!isset($this->options[$type])) {
                throw new \Exception("Unsupported cache option " . $type . "!");
            }
            $netteOptions[$this->options[$type]] = $option;
        }

        $this->cache->save($key, $data, $netteOptions);
    }

}