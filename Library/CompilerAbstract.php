<?php

/*
  +--------------------------------------------------------------------------+
  | Zephir Language                                                          |
  +--------------------------------------------------------------------------+
  | Copyright (c) 2013-2015 Zephir Team and contributors                     |
  +--------------------------------------------------------------------------+
  | This source file is subject the MIT license, that is bundled with        |
  | this package in the file LICENSE, and is available through the           |
  | world-wide-web at the following url:                                     |
  | http://zephir-lang.com/license.html                                      |
  |                                                                          |
  | If you did not receive a copy of the MIT license and are unable          |
  | to obtain it through the world-wide-web, please send a note to           |
  | license@zephir-lang.com so we can mail you a copy immediately.           |
  +--------------------------------------------------------------------------+
 */

namespace Zephir;

use Zephir\Commands\CommandInterface;
use Zephir\FileSystem\HardDisk as FileSystem;
use Zephir\Config;
use Zephir\Logger;
use Zephir\BaseBackend;
use Zephir\CompilerException;
use Zephir\Utils;

/**
 * Compiler
 *
 * Main compiler
 */
abstract class CompilerAbstract {

  const VERSION = '0.7.1b';

  /**
   * @var CompilerFile[]
   */
  protected $files = array();

  /**
   * @var array|string[]
   */
  protected $anonymousFiles = array();

  /**
   * Additional initializer code
   * used for static property initialization
   * @var array
   */
  protected $internalInitializers = array();

  /*
   * @var ClassDefinition[]
   */
  protected $definitions = array();

  /**
   * @var FunctionDefinition[]
   */
  public $functionDefinitions = array();

  /**
   * @var array|string[]
   */
  protected $compiledFiles = array();

  /**
   *
   */
  protected $constants = array();

  /**
   * Extension globals
   *
   * @var array
   */
  protected $globals = array();

  /**
   * External dependencies
   *
   * @var array
   */
  protected $externalDependencies = array();

  /**
   * @var StringsManager
   */
  protected $stringManager;

  /**
   * @var Config
   */
  protected $config;

  /**
   * @var Logger
   */
  protected $logger;

  /**
   * @var \ReflectionClass[]
   */
  protected static $internalDefinitions = array();

  /**
   * @var boolean
   */
  protected static $loadedPrototypes = false;

  /**
   * @var array
   */
  protected $extraFiles = array();

  /**
   * @var BaseBackend
   */
  protected $backend;

  /**
   * Compiler constructor
   *
   * @param Config $config
   * @param Logger $logger
   */
  public function __construct(Config $config, Logger $logger, BaseBackend $backend) {
    $this->config = $config;
    $this->logger = $logger;
    $this->stringManager = new \Zephir\StringsManager();
    $this->fileSystem = new FileSystem();
    $this->backend = $backend;
    $this->checkRequires();
  }

  /**
   * Check require extensions orther when build your extension
   */
  protected function checkRequires() {
    $extension_requires = $this->config->get("requires");
    $extension_requires = $extension_requires["extensions"];
    if ($extension_requires) {
      $collection_error = PHP_EOL . "\tCould not load Extension : ";
      foreach ($extension_requires as $key => $value) {
        if (!extension_loaded($value)) {
          $collection_error .= $value . ", ";
        }
      }

      if ($collection_error != PHP_EOL . "\tCould not load Extension : ") {
        $collection_error .= PHP_EOL . "\tYou must add extensions above before build this extension!";
        throw new \Exception($collection_error);
      }
    }
  }

  /**
   * Adds a function to the function definitions
   *
   * @param FunctionDefinition $func
   * @param array $statement
   */
  public function addFunction(FunctionDefinitionAbstract $func, $statement = null) {
    $funcName = strtolower($func->getInternalName());
    if (isset($this->functionDefinitions[$funcName])) {
      throw new CompilerException(
      "Function '" . $func->getCompleteName() . "' was defined more than one time", $statement
      );
    }

    $this->functionDefinitions[$funcName] = $func;
  }

  /**
   * Pre-compiles classes creating a CompilerFile definition
   *
   * @param string $filePath
   */
  abstract protected function preCompile($filePath);

  /**
   * Recursively pre-compiles all sources found in the given path
   *
   * @param string $path
   *
   * @throws CompilerException
   */
  protected function recursivePreCompile($path) {
    if (!is_string($path)) {
      throw new CompilerException('Invalid compilation path' . var_export($path, true));
    }

    /**
     * Pre compile all files
     */
    $files = array();
    $iterator = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($path), \RecursiveIteratorIterator::SELF_FIRST
    );

    /**
     * @var $item \SplFileInfo
     */
    foreach ($iterator as $item) {
      if (!$item->isDir()) {
        $files[] = $item->getPathname();
      }
    }

    sort($files, SORT_STRING);
    foreach ($files as $file) {
      $this->preCompile($file);
    }
  }

  /**
   * Loads a class definition in an external dependency
   *
   * @param string $className
   * @param string $location
   */
  abstract public function loadExternalClass($className, $location);

  /**
   * Allows to check if a class is part of the compiled extension
   *
   * @param string $className
   * @return boolean
   */
  public function isClass($className) {
    foreach ($this->definitions as $key => $value) {
      if (!strcasecmp($key, $className)) {
        if ($value->getType() == 'class') {
          return true;
        }
      }
    }

    /**
     * Try to autoload the class from an external dependency
     */
    if (count($this->externalDependencies)) {
      foreach ($this->externalDependencies as $namespace => $location) {
        if (preg_match('#^' . $namespace . '\\\\#i', $className)) {
          $this->loadExternalClass($className, $location);
          return true;
        }
      }
    }

    return false;
  }

  /**
   * Allows to check if an interface is part of the compiled extension
   *
   * @param string $className
   *
   * @return boolean
   */
  public function isInterface($className) {
    foreach ($this->definitions as $key => $value) {
      if (!strcasecmp($key, $className)) {
        if ($value->getType() == 'interface') {
          return true;
        }
      }
    }

    /**
     * Try to autoload the class from an external dependency
     */
    if (count($this->externalDependencies)) {
      foreach ($this->externalDependencies as $namespace => $location) {
        if (preg_match('#^' . $namespace . '\\\\#i', $className)) {
          $this->loadExternalClass($className, $location);
          return true;
        }
      }
    }

    return false;
  }

  /**
   * Allows to check if a class is part of PHP
   *
   * @param string $className
   *
   * @return boolean
   */
  public function isBundledClass($className) {
    return class_exists($className, false);
  }

  /**
   * Allows to check if a interface is part of PHP
   *
   * @param string $className
   *
   * @return boolean
   */
  public function isBundledInterface($className) {
    return interface_exists($className, false);
  }

  /**
   * Returns class the class definition from a given class name
   *
   * @param string $className
   *
   * @return ClassDefinition
   */
  public function getClassDefinition($className) {
    foreach ($this->definitions as $key => $value) {
      if (!strcasecmp($key, $className)) {
        return $value;
      }
    }

    return false;
  }

  /**
   * Inserts an anonymous class definition in the compiler
   *
   * @param CompilerFileAnonymous $file
   * @param ClassDefinition $classDefinition
   */
  public function addClassDefinition(CompilerFileAnonymous $file, ClassDefinitionAbstract $classDefinition) {
    $this->definitions[$classDefinition->getCompleteName()] = $classDefinition;
    $this->anonymousFiles[$classDefinition->getCompleteName()] = $file;
  }

  /**
   * Returns class the class definition from a given class name
   *
   * @param string $className
   *
   * @return ClassDefinition
   */
  abstract public function getInternalClassDefinition($className);

  /**
   * Copies the base kernel to the extension destination
   *
   * @param        $src
   * @param        $dest
   * @param string $pattern
   * @param mixed  $callback
   *
   * @return bool
   */
  protected function recursiveProcess($src, $dest, $pattern = null, $callback = "copy") {
    $success = true;
    $iterator = new \DirectoryIterator($src);
    foreach ($iterator as $item) {
      $pathName = $item->getPathname();
      if (!is_readable($pathName)) {
        $this->logger->output('File is not readable :' . $pathName);
        continue;
      }

      $fileName = $item->getFileName();

      if ($item->isDir()) {
        if ($fileName != '.' && $fileName != '..' && $fileName != '.libs') {
          if (!is_dir($dest . DIRECTORY_SEPARATOR . $fileName)) {
            mkdir($dest . DIRECTORY_SEPARATOR . $fileName, 0755, true);
          }
          $this->recursiveProcess($pathName, $dest . DIRECTORY_SEPARATOR . $fileName, $pattern, $callback);
        }
      } else {
        if (!$pattern || ($pattern && preg_match($pattern, $fileName) === 1)) {
          $path = $dest . DIRECTORY_SEPARATOR . $fileName;
          $success = $success && call_user_func($callback, $pathName, $path);
        }
      }
    }

    return $success;
  }

  /**
   * Recursively deletes files in a specified location
   *
   * @param string $path
   * @param string $mask
   */
  protected function recursiveDeletePath($path, $mask) {
    $objects = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($path), \RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($objects as $name => $object) {
      if (preg_match($mask, $name)) {
        @unlink($name);
      }
    }
  }

  /**
   * Registers C-constants as PHP constants from a C-file
   *
   * @param array $constantsSources
   *
   * @throws Exception
   */
  protected function loadConstantsSources($constantsSources) {
    foreach ($constantsSources as $constantsSource) {
      if (!file_exists($constantsSource)) {
        throw new \Exception("File '" . $constantsSource . "' with constants definitions");
      }

      foreach (file($constantsSource) as $line) {
        if (preg_match('/^\#define[ \t]+([A-Z0-9\_]+)[ \t]+([0-9]+)/', $line, $matches)) {
          $this->constants[$matches[1]] = array('int', $matches[2]);
          continue;
        }
        if (preg_match('/^\#define[ \t]+([A-Z0-9\_]+)[ \t]+(\'(.){1}\')/', $line, $matches)) {
          $this->constants[$matches[1]] = array('char', $matches[3]);
        }
      }
    }
  }

  /**
   * Checks if $name is a Zephir constant
   *
   * @param string $name
   *
   * @return boolean
   */
  public function isConstant($name) {
    return isset($this->constants[$name]);
  }

  /**
   * Returns a Zephir Constant by its name
   *
   * @param string $name
   */
  public function getConstant($name) {
    return $this->constants[$name];
  }

  /**
   * Sets extensions globals
   *
   * @param array $globals
   */
  public function setExtensionGlobals(array $globals) {
    foreach ($globals as $key => $value) {
      $this->globals[$key] = $value;
    }
  }

  /**
   * Checks if a specific extension global is defined
   *
   * @param string $name
   *
   * @return boolean
   */
  public function isExtensionGlobal($name) {
    return isset($this->globals[$name]);
  }

  /**
   * Returns a extension global by its name
   *
   * @param string $name
   *
   * @return boolean
   */
  public function getExtensionGlobal($name) {
    return $this->globals[$name];
  }

  /**
   * Checks if the current directory is a valid Zephir project
   *
   * @return string
   * @throws Exception
   */
  abstract protected function checkDirectory();

  public function getPhpIncludeDirs() {
    if (!Utils::isWindows()) {
      $this->fileSystem->system('php-config --includes', 'stdout', self::VERSION . '/php-includes');
    }
    return trim($this->fileSystem->read(self::VERSION . '/php-includes'));
  }

  /**
   * Initializes a Zephir extension
   *
   * @param CommandInterface $command
   *
   * @throws Exception
   */
  abstract public function init(CommandInterface $command);

  /**
   * Generates the C sources from Zephir without compiling them
   *
   * @param CommandInterface $command
   * @return bool
   * @throws Exception
   */
  abstract public function generate(CommandInterface $command);

  /**
   * Compiles the extension without installing it
   *
   * @param CommandInterface $command
   * @param boolean $development
   */
  abstract public function compile(CommandInterface $command, $development = false);

  /**
   * Generate a HTML API
   *
   * @param CommandInterface $command
   * @param bool             $fromGenerate
   */
  public function api(CommandInterface $command, $fromGenerate = false) {
    if (!$fromGenerate) {
      $this->generate($command);
    }

    $documentator = new Documentation($this->files, $this->config, $this->logger, $command);
    $this->logger->output('Generating API into ' . $documentator->getOutputDirectory());
    $documentator->build();
  }

  /**
   * Generate IDE stubs
   *
   * @param CommandInterface $command
   * @param bool             $fromGenerate
   */
  public function stubs(CommandInterface $command, $fromGenerate = false) {
    if (!$fromGenerate) {
      $this->generate($command);
    }

    $this->logger->output('Generating stubs...');
    $stubsGenerator = new Stubs\Generator($this->files, $this->config);

    $path = $this->config->get('path', 'stubs');
    $path = str_replace('%version%', $this->config->get('version'), $path);
    $path = str_replace('%namespace%', ucfirst($this->config->get('namespace')), $path);

    $stubsGenerator->generate($path);
  }

  /**
   * Compiles and installs the extension
   *
   * @param CommandInterface $command
   * @param boolean $development
   *
   * @throws Exception
   */
  abstract public function install(CommandInterface $command, $development = false);

  /**
   * Run tests
   *
   * @param CommandInterface $command
   */
  abstract public function test(CommandInterface $command);

  /**
   * Clean the extension directory
   *
   * @param CommandInterface $command
   */
  abstract public function clean(CommandInterface $command);

  
  /**
   * Clean the extension directory
   *
   * @param CommandInterface $command
   */
  abstract public function fullClean(CommandInterface $command);

  /**
   * Compiles and installs the extension
   *
   * @param CommandInterface $command
   */
  public function build(CommandInterface $command) {
    $this->install($command, false);
  }

  /**
   * Compiles and installs the extension in development mode (debug symbols and no optimizations)
   *
   * @param CommandInterface $command
   */
  public function buildDev(CommandInterface $command) {
    $this->install($command, true);
  }

  /**
   * Adds an external dependency to the compiler
   *
   * @param string $namespace
   * @param string $location
   */
  public function addExternalDependency($namespace, $location) {
    $this->externalDependencies[$namespace] = $location;
  }

  public function calculateDependencies($files, $_dependency = null) {
    /**
     * Classes are ordered according to a dependency ranking
     * Classes with higher rank, need to be initialized first
     * We first build a dependency tree and then set the rank accordingly
     */
    if ($_dependency == null) {
      $dependencyTree = array();
      foreach ($files as $file) {
        if (!$file->isExternal()) {
          $classDefinition = $file->getClassDefinition();
          $dependencyTree[$classDefinition->getCompleteName()] = $classDefinition->getDependencies();
        }
      }

      /* Make sure the dependencies are loaded first (recursively) */
      foreach ($dependencyTree as $className => $dependencies) {
        foreach ($dependencies as $dependency) {
          $dependency->increaseDependencyRank(0);
          $this->calculateDependencies($dependencyTree, $dependency);
        }
      }
      return;
    }

    $dependencyTree = $files;
    if (isset($dependencyTree[$_dependency->getCompleteName()])) {
      foreach ($dependencyTree[$_dependency->getCompleteName()] as $dependency) {
        $dependency->increaseDependencyRank(0);
        $this->calculateDependencies($dependencyTree, $dependency);
      }
    }
  }

  /**
   * Returns the internal logger
   *
   * @return Logger
   */
  public function getLogger() {
    return $this->logger;
  }

  /**
   * Returns the internal config
   *
   * @return Config
   */
  public function getConfig() {
    return $this->config;
  }

  /**
   * Returns the internal filesystem handler
   *
   * @return FileSystem
   */
  public function getFileSystem() {
    return $this->fileSystem;
  }

  /**
   * Returns a short path
   *
   * @param string $path
   *
   * @return string
   */
  public static function getShortPath($path) {
    return str_replace(ZEPHIRPATH . DIRECTORY_SEPARATOR, '', $path);
  }

  /**
   * Returns a short user path
   *
   * @param string $path
   *
   * @return string
   */
  public static function getShortUserPath($path) {
    return str_replace('\\', '/', str_replace(getcwd() . DIRECTORY_SEPARATOR, '', $path));
  }
}
