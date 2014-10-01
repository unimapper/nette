<?php

namespace UniMapper\Nette;

use Nette\Caching,
    UniMapper\Cache\ICache;

class Cache implements ICache
{

    /** @var \Nette\Caching\Cache */
    private $netteCache;

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
        $this->netteCache = new Caching\Cache($storage, "UniMapper.Cache");
    }

    public function load($key)
    {
        return $this->netteCache->load($key);
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

        $this->netteCache->save($key, $data, $netteOptions);
    }

    public function getNetteCache()
    {
        return $this->netteCache;
    }

}