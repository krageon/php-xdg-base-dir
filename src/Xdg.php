<?php

namespace XdgBaseDir;

/**
 * Simple implementation of the XDG standard http://standards.freedesktop.org/basedir-spec/basedir-spec-latest.html
 *
 * Based on the python implementation https://github.com/takluyver/pyxdg/blob/master/xdg/BaseDirectory.py
 */
class Xdg
{
    const S_IFDIR = 040000; // directory
    const S_IRWXO = 00007;  // rwx other
    const S_IRWXG = 00056;  // rwx group
    const RUNTIME_DIR_FALLBACK = 'php-xdg-runtime-dir-fallback-';

    /**
     * @var array
     */
    private static $envKeys = array(
        'USER',
        'HOME',
        'HOMEDRIVE',
        'HOMEPATH',
        'XDG_CONFIG_HOME',
        'XDG_DATA_HOME',
        'XDG_CACHE_HOME',
        'XDG_CONFIG_DIRS',
        'XDG_DATA_DIRS',
        'XDG_RUNTIME_DIR',
    );

    /**
     * @var array
     */
    private $env = array();

    /**
     * @param array $env
     */
    public function __construct(array $env = array())
    {
        foreach (self::$envKeys as $key) {
            $this->env[$key] = array_key_exists($key, $env) ? (string)$env[$key] : getenv($key);
        }
    }

    /**
     * @return string
     */
    public function getHomeDir()
    {
        return $this->getEnv('HOME') ?: ($this->getEnv('HOMEDRIVE') . DIRECTORY_SEPARATOR . $this->getEnv('HOMEPATH'));
    }

    /**
     * @return string
     */
    public function getHomeConfigDir()
    {
        $path = $this->getEnv('XDG_CONFIG_HOME') ?: $this->getHomeDir() . DIRECTORY_SEPARATOR . '.config';

        return $path;
    }

    /**
     * @return string
     */
    public function getHomeDataDir()
    {
        $path = $this->getEnv('XDG_DATA_HOME') ?: $this->getHomeDir() . DIRECTORY_SEPARATOR . '.local' . DIRECTORY_SEPARATOR . 'share';

        return $path;
    }

    /**
     * @return array
     */
    public function getConfigDirs()
    {
        $configDirs = $this->getEnv('XDG_CONFIG_DIRS') ? explode(':', $this->getEnv('XDG_CONFIG_DIRS')) : array('/etc/xdg');

        $paths = array_merge(array($this->getHomeConfigDir()), $configDirs);

        return $paths;
    }

    /**
     * @return array
     */
    public function getDataDirs()
    {
        $dataDirs = $this->getEnv('XDG_DATA_DIRS') ? explode(':', $this->getEnv('XDG_DATA_DIRS')) : array('/usr/local/share', '/usr/share');

        $paths = array_merge(array($this->getHomeDataDir()), $dataDirs);

        return $paths;
    }

    /**
     * @return string
     */
    public function getHomeCacheDir()
    {
        $path = $this->getEnv('XDG_CACHE_HOME') ?: $this->getHomeDir() . DIRECTORY_SEPARATOR . '.cache';

        return $path;
    }

    /**
     * @param $name
     * @return bool
     */
    private function getEnv($name)
    {
        return isset($this->env[$name]) ? $this->env[$name] : false;
    }

    /**
     * @param bool $strict
     * @return string
     * @throws \RuntimeException
     */
    public function getRuntimeDir($strict = true)
    {
        if ($runtimeDir = $this->getEnv('XDG_RUNTIME_DIR')) {
            return $runtimeDir;
        }

        if ($strict) {
            throw new \RuntimeException('XDG_RUNTIME_DIR was not set');
        }

        $fallback = sys_get_temp_dir() . DIRECTORY_SEPARATOR . self::RUNTIME_DIR_FALLBACK . $this->getEnv('USER');

        $create = false;

        if (!is_dir($fallback)) {
            mkdir($fallback, 0700, true);
        }

        $st = lstat($fallback);

        # The fallback must be a directory
        if (!$st['mode'] & self::S_IFDIR) {
            rmdir($fallback);
            $create = true;
        } elseif ($st['uid'] != $this->getUid() ||
            $st['mode'] & (self::S_IRWXG | self::S_IRWXO)
        ) {
            rmdir($fallback);
            $create = true;
        }

        if ($create) {
            mkdir($fallback, 0700, true);
        }

        return $fallback;
    }
    
    private function getUid()
    {
        if (function_exists('posix_getuid')) {
            return posix_getuid();
        }

        return getmyuid();
    }
}
