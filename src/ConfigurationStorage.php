<?php

namespace Localhook\Localhook;

use Localhook\Localhook\Exceptions\NoConfigurationException;
use Symfony\Component\Filesystem\Filesystem;

class ConfigurationStorage
{
    private $homePath;

    private $basePath;

    private $baseConfigFilePath;

    /** @var bool */
    private $dryRun;

    /** @var Filesystem */
    private $fs;

    /** @var array */
    private $configuration = [];

    public function __construct($dryRun = false)
    {
        $this->fs = new Filesystem();
        $this->initDirectoryPaths();
        $this->dryRun = $dryRun;
    }

    private function initDirectoryPaths()
    {
        $posix = posix_getpwuid(posix_getuid());
        $this->homePath = $posix['dir'];
        $this->basePath = $this->homePath . '/.localhook';
        $this->baseConfigFilePath = $this->basePath . '/config.json';

        return $this;
    }

    public function save()
    {
        if (!$this->dryRun) {
            if (!$this->fs->exists($this->basePath)) {
                $this->fs->mkdir($this->basePath);
            }

            $fileContent = json_encode($this->configuration, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            $this->fs->dumpFile($this->baseConfigFilePath, $fileContent);
        }

        return $this;
    }

    public function loadFromFile()
    {
        if ($this->dryRun) {
            return $this;
        }
        if (!$this->fs->exists($this->baseConfigFilePath)) {
            throw new NoConfigurationException('No configuration file found at "' . $this->baseConfigFilePath . '" .');
        }
        $json = file_get_contents($this->baseConfigFilePath);

        $this->configuration = json_decode($json, true);

        return $this;
    }

    public function deleteFile()
    {
        if (!$this->dryRun) {
            if (!$this->fs->exists($this->baseConfigFilePath)) {
                throw new NoConfigurationException('No configuration file found at "' . $this->baseConfigFilePath . '" .');
            }
            $this->fs->remove($this->baseConfigFilePath);
        }
        $this->configuration = [];

        return $this;
    }

    /**
     * @return array
     */
    public function get()
    {
        return $this->configuration;
    }

    /**
     * @param array $configuration
     *
     * @return $this
     */
    public function merge($configuration)
    {
        $this->configuration = array_replace_recursive($this->configuration, $configuration);

        return $this;
    }

    /**
     * @param array $configuration
     *
     * @return $this
     */
    public function replaceConfiguration($configuration)
    {
        $this->configuration = $configuration;

        return $this;
    }
}