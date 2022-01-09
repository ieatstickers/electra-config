<?php

namespace Electra\Config;

use Electra\Utility\Arrays;
use Electra\Utility\Strings;
use Symfony\Component\Yaml\Yaml;

class Config
{
  protected $configArray = [];
  protected $configDirs = [];
  protected $mergeRules = [];

  protected function __construct() {}

  /**
   * @param array $configDirs
   * @param array $mergeRules
   *
   * @return Config
   * @throws \Exception
   */
  public static function create(array $configDirs, array $mergeRules): Config
  {
    $config = (new Config())
      ->addConfigDir(...$configDirs)
      ->addMergeRule(...$mergeRules);

    $config->generate();

    return $config;
  }

  /**
   * @param string ...$configDir
   *
   * @return $this
   */
  public function addConfigDir(string ...$configDir): Config
  {
    $this->configDirs = array_merge($this->configDirs, $configDir);
    return $this;
  }

  /**
   * @param array $configArray
   *
   * @return $this
   */
  public function setConfigArray(array $configArray): Config
  {
    $this->configArray = $configArray;
    return $this;
  }

  /**
   * @param string ...$regex
   *
   * @return $this
   */
  public function addMergeRule(string ...$regex): Config
  {
    $this->mergeRules = array_merge($this->mergeRules, $regex);
    return $this;
  }

  /**
   * @return array
   * @throws \Exception
   */
  public function generate()
  {
    // Init config cache
    $configCache = [];
    $mergedConfig = [];

    // For each rule
    foreach ($this->mergeRules as $mergeRuleRegex)
    {
      // For each config directory
      foreach ($this->configDirs as $configDirectory)
      {
        // if there is a cache for the directory
        if (isset($configCache[$configDirectory]))
        {
          // loop through file cache
          foreach ($configCache[$configDirectory] as $filePath => $configArray)
          {
            // For each file, merge if it matches the rule
            if (preg_match($mergeRuleRegex, $filePath))
            {
              $mergedConfig = array_replace_recursive($mergedConfig, $configArray);
            }
          }
        }
        else
        {
          // If directory doesn't exist
          if (!file_exists($configDirectory))
          {
            throw new \Exception("Config directory not found: $configDirectory");
          }

          // Get all files from the directory
          $files = scandir($configDirectory);

          // For each file
          foreach ($files as $file)
          {
            if (
              $file == '.'
              ||
              $file == '..'
              ||
              (
                !Strings::endsWith($file, '.yml')
                &&
                !Strings::endsWith($file, '.yaml')
              )
            )
            {
              continue;
            }

            // Read it in
            $fileContents = file_get_contents(realpath($configDirectory . '/' . $file));

            // Parse it
            $fileAsArray = Yaml::parse($fileContents);

            if (!$fileAsArray)
            {
              continue;
            }

            // Cache it
            $configCache[$configDirectory][$file] = $fileAsArray;

            // For each file, merge if it matches the rule
            if (preg_match($mergeRuleRegex, $file))
            {
              $mergedConfig = array_replace_recursive($mergedConfig, $fileAsArray);
            }
          }
        }
      }
    }

    $this->configArray = $mergedConfig;
    return $mergedConfig;
  }

  /** @return array */
  public function toArray(): array
  {
    return $this->configArray;
  }

  /**
   * @param string $configPath
   *
   * @return mixed|null
   */
  public function getByPath(string $configPath)
  {
    return Arrays::getByKeyPath($configPath, $this->configArray);
  }
}
