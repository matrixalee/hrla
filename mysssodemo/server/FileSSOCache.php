<?php

class FileSSOCache
{
    const CACHE_FILE_PREFIX = '__';

    const CACHE_FILE_SUBFIX = '.php.cache';
    protected $ttl = 3600;
//    protected $ttl = 0;

    /**
     * @var string
     */
    protected $prefix = '';

    /**
     * @var string
     */
    protected $cacheDir;

    /**
     * @param string $cacheDir
     *
     * @throws Exception
     */
    public function __construct($cacheDir = null)
    {
        if (!$cacheDir) {
            $cacheDir = realpath(sys_get_temp_dir()).'/cache';
        }

        $this->cacheDir = (string) $cacheDir;

        $this->createCacheDirectory($cacheDir);
    }

    /**
     * {@inheritdoc}
     */
    public function del($key)
    {
        $tKey = $this->getKey($key);
        $cacheFile = $this->getFileName($tKey);
        $this->deleteFile($cacheFile);
    }

    /**
     * {@inheritdoc}
     */
    public function get($key)
    {
        return $this->getValueFromCache($key);
    }

    /**
     * {@inheritdoc}
     */
    public function has($key)
    {
        return !is_null($this->getValueFromCache($key));
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $ttl = null)
    {
        $cacheFile = $this->getFileName($key);
        if (!$ttl) {
            $ttl = $this->ttl;
        }
        $item = $this->pack(
            [
                'value' => $value,
                'ttl' => (int) $ttl + time(),
            ]
        );
        if (!file_put_contents($cacheFile, $item)) {
            throw new Exception(sprintf('Error saving data with the key "%s" to the cache file.', $key));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setOption($key, $value)
    {
        switch ($key) {
            case 'ttl':
                $value = (int) $value;
                if ($value < 1) {
                    throw new Exception('ttl cant be lower than 1');
                }
                $this->ttl = $value;
                break;
            default:
                throw new Exception('option not valid '.$key);
        }

        return true;
    }

    protected function createCacheDirectory($path)
    {
//        echo $path;
        if (!is_dir($path)) {
            if (!mkdir($path, 0777, true)) {
                throw new Exception($path.' is not writable');
            }
        }

        if (!is_writable($path)) {
            throw new Exception($path.' is not writable');
        }
    }

    protected function deleteFile($cacheFile)
    {
        if (is_file($cacheFile)) {
            return unlink($cacheFile);
        }

        return false;
    }

    protected function getFileName($key)
    {
        return $this->cacheDir.
        DIRECTORY_SEPARATOR.
        self::CACHE_FILE_PREFIX.
        $this->getKey($key).
        self::CACHE_FILE_SUBFIX;
    }

    protected function getValueFromCache($key)
    {
        $path = $this->getFileName($this->getKey($key));

        if (!file_exists($path)) {
            return;
        }

        $data = $this->unPack(file_get_contents($path));
        if (!$data || !$this->validateDataFromCache($data) || $this->ttlHasExpired($data['ttl'])) {
            return;
        }

        return $data['value'];
    }

    protected function validateDataFromCache($data)
    {
        if (!is_array($data)) {
            return false;
        }
        foreach (['value', 'ttl'] as $missing) {
            if (!array_key_exists($missing, $data)) {
                return false;
            }
        }

        return true;
    }

    protected function ttlHasExpired($ttl)
    {
        return (time() > $ttl);
    }



    protected function getKey($key)
    {
        return sprintf('%s%s', $this->prefix, $key);
    }

    protected function pack($value)
    {
        return serialize($value);
    }

    protected function unPack($value)
    {
        return unserialize($value);
    }
}

//------------------

/*$options = ['files_cache_directory' => '/tmp', 'files_cache_ttl' => 36000];
$adapter = new FileSSOCache($options['files_cache_directory']);
$adapter->setOption('ttl', $options['files_cache_ttl']);
$adapter->set('11',2);
echo $adapter->get('11');
$adapter->del('11');*/


