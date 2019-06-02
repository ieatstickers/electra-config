<?php

namespace Electra\Config;

use Electra\Utility\Strings;
use Symfony\Component\Yaml\Yaml;

class Config
{
  protected static $configArray = [];
  protected static $configDirs = [];
  protected static $mergeRules = [];

  /**
   * @param string $configDir
   */
  public static function addConfigDir(string $configDir)
  {
    self::$configDirs[] = $configDir;
  }

  /**
   * @param string $regex
   */
  public static function addMergeRule(string $regex)
  {
    self::$mergeRules[] = $regex;
  }

  /**
   * @param array $mergeRules
   */
  public static function setMergeRules(array $mergeRules)
  {
    self::$mergeRules = $mergeRules;
  }

  /**
   * @return array
   * @throws \Exception
   */
  public static function generate()
  {
    // Init config cache
    $configCache = [];
    $mergedConfig = [];

    // For each rule
    foreach (self::$mergeRules as $mergeRuleRegex)
    {
      // For each config directory
      foreach (self::$configDirs as $configDirectory)
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
              array_merge_recursive($mergedConfig, $configArray);
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
            $fileContents = file_get_contents($configDirectory . '/' . $file);

            // Parse it
            $fileAsArray = Yaml::parse($fileContents);

            // Cache it
            $configCache[$configDirectory][$file] = $fileAsArray;

            // Merge if regex matches

            // For each file, merge if it matches the rule
            if (preg_match($mergeRuleRegex, $file))
            {
              array_merge_recursive($mergedConfig, $fileAsArray);
            }
          }
        }
      }
    }

    self::$configArray = $mergedConfig;
    return $mergedConfig;
  }

  /**
   * @return array
   */
  public static function toArray()
  {
    return self::$configArray;
  }

  /**
   * @param string $configPath
   * @return Config | mixed | null
   */
  public static function getByPath(string $configPath)
  {
    // Split config path into an array of keys
    $configPathArray = explode(':', $configPath);

    return self::getByPathArray($configPathArray);
  }

  /**
   * @param array $pathArray
   * @param array | null $configArray
   * @return Config|mixed|null
   */
  private static function getByPathArray(array $pathArray, array $configArray = null)
  {
    if (!$configArray)
    {
      $configArray = self::$configArray;
    }

    foreach ($pathArray as $key)
    {
      array_shift($pathArray);

      if (isset($configArray[$key]))
      {
        // Search finished
        if (empty($pathArray))
        {
          // Search finished
          if (!isset($configArray[$key]))
          {
            return null;
          }

          return $configArray[$key];
        }
        else
        {
          return self::getByPathArray($pathArray, $configArray[$key]);
        }
      }
      else
      {
        return null;
      }
    }

    return null;
  }
}
