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

use Zephir\Config;
use Zephir\Logger;
use Zephir\CompilerException;
use Zephir\CompilationContext;
use Zephir\StringsManager;
use Zephir\Utils;

/**
 * CompilerFile
 *
 * This class represents every file compiled in a project
 * Every file may contain a class or an interface
 */
abstract class CompilerFileAbstract
{
    /**
     * Namespace of the
     */
    protected $_namespace;

    protected $_className;

    protected $_filePath;

    protected $_external = false;

    /**
     * Original internal representation (IR) of the file
     */
    protected $_ir;

    protected $_originalNode;

    protected $_compiledFile;

    /**
     * @var ClassDefinition
     */
    protected $_classDefinition;

    /**
     * @var FunctionDefinition[]
     */
    protected $_functionDefinitions = array();

    /**
     * @var array
     */
    protected $_headerCBlocks;

    /**
     * @var Config
     */
    protected $_config = null;

    /**
     * @var Logger
     */
    protected $_logger = null;

    /**
     * CompilerFile constructor
     *
     * @param string $className
     * @param string $filePath
     * @param Config $config
     * @param Logger $logger
     */
    public function __construct($className, $filePath, Config $config, Logger $logger)
    {
        $this->_className = $className;
        $this->_filePath = $filePath;
        $this->_headerCBlocks = array();
        $this->_config = $config;
        $this->_logger = $logger;
    }

    /**
     * Returns the class definition related to the compiled file
     *
     * @return ClassDefinition
     */
    public function getClassDefinition()
    {
        $this->_classDefinition->setAliasManager($this->_aliasManager);
        return $this->_classDefinition;
    }

    public function getFunctionDefinitions()
    {
        return $this->_functionDefinitions;
    }

    /**
     * Sets if the class belongs to an external dependency or not
     *
     * @param boolean $external
     */
    public function setIsExternal($external)
    {
        $this->_external = (bool) $external;
    }

    /**
     * Checks if the class file belongs to an external dependency or not
     *
     * @return bool
     */
    public function isExternal()
    {
        return $this->_external;
    }

    /**
     * Adds a function to the function definitions
     *
     * @param FunctionDefinition $func
     * @param array $statement
     */
    public function addFunction(CompilerAbstract $compiler, FunctionDefinitionAbstract $func, $statement = null)
    {
        $compiler->addFunction($func, $statement);
        $funcName = strtolower($func->getInternalName());
        if (isset($this->_functionDefinitions[$funcName])) {
            throw new CompilerException("Function '" . $func->getName() . "' was defined more than one time (in the same file)", $statement);
        }
        $this->_functionDefinitions[$funcName] = $func;
    }

    /**
     * Compiles the file generating a JSON intermediate representation
     *
     * @param Compiler $compiler
     * @return array
     */
    public function genIR(Compiler $compiler)
    {
        $normalizedPath = str_replace(array(DIRECTORY_SEPARATOR, ":", '/'), '_', realpath($this->_filePath));
        $compilePath = DIRECTORY_SEPARATOR . Compiler::VERSION . DIRECTORY_SEPARATOR . $normalizedPath . ".js";
        $zepRealPath = realpath($this->_filePath);

        if (PHP_OS == "WINNT") {
            $zephirParserBinary = ZEPHIRPATH . 'bin\zephir-parser.exe';
        } else {
            $zephirParserBinary = ZEPHIRPATH . 'bin/zephir-parser';
        }

        if (!file_exists($zephirParserBinary)) {
            throw new \Exception($zephirParserBinary . ' was not found');
        }

        $changed = false;
        $fileSystem = $compiler->getFileSystem();
        if ($fileSystem->exists($compilePath)) {
            $modificationTime = $fileSystem->modificationTime($compilePath);
            if ($modificationTime < filemtime($zepRealPath) || $modificationTime < filemtime($zephirParserBinary)) {
                $fileSystem->system($zephirParserBinary . ' ' . $zepRealPath, 'stdout', $compilePath);
                $changed = true;
            }
        } else {
            $fileSystem->system($zephirParserBinary . ' ' . $zepRealPath, 'stdout', $compilePath);
            $changed = true;
        }

        if ($changed || !$fileSystem->exists($compilePath . '.php')) {
            $json = json_decode($fileSystem->read($compilePath), true);
            $data = '<?php return ' . var_export($json, true) . ';';
            $fileSystem->write($compilePath . '.php', $data);
        }

        return $fileSystem->requireFile($compilePath . '.php');
    }

    /**
     * Compiles the class/interface contained in the file
     *
     * @param CompilationContext $compilationContext
     * @param string $namespace
     * @param array $classStatement
     */
    public function compileClass(CompilationContext $compilationContext, $namespace, $classStatement)
    {
        $classDefinition = $this->_classDefinition;

        /**
         * Do the compilation
         */
        $classDefinition->compile($compilationContext);
    }

    /**
     * Compiles a function
     *
     * @param CompilationContext $compilationContext
     * @param FunctionDefinition $functionDefinition
     */
    abstract public function compileFunction(CompilationContext $compilationContext, FunctionDefinition $functionDefinition);

    /**
     * Compiles a comment as a top-level statement
     *
     * @param CompilationContext $compilationContext
     * @param array $topStatement
     */
    public function compileComment(CompilationContext $compilationContext, $topStatement)
    {
        $compilationContext->codePrinter->output('/' . $topStatement['value'] . '/');
    }

    /**
     * Creates a definition for an interface
     *
     * @param string $namespace
     * @param array $topStatement
     * @param array $docblock
     */
    abstract public function preCompileInterface($namespace, $topStatement, $docblock);

    /**
     * Creates a definition for a class
     *
     * @param CompilationContext $compilationContext
     * @param string $namespace
     * @param array $topStatement
     * @param array $docblock
     */
    abstract public function preCompileClass(CompilationContext $compilationContext, $namespace, $topStatement, $docblock);

    /**
     * Pre-compiles a Zephir file. Generates the IR and perform basic validations
     *
     * @param Compiler $compiler
     * @throws ParseException
     * @throws CompilerException
     * @throws Exception
     */
    abstract public function preCompile(Compiler $compiler);

    /**
     * Returns the path to the compiled file
     *
     * @return string
     */
    public function getCompiledFile()
    {
        return $this->_compiledFile;
    }

    /**
     * Check dependencies
     *
     * @param Compiler $compiler
     */
    public function checkDependencies(CompilerAbstract $compiler)
    {
        $classDefinition = $this->_classDefinition;

        $extendedClass = $classDefinition->getExtendsClass();
        if ($extendedClass) {
            if ($classDefinition->getType() == 'class') {
                if ($compiler->isClass($extendedClass)) {
                    $extendedDefinition = $compiler->getClassDefinition($extendedClass);
                    $classDefinition->setExtendsClassDefinition($extendedDefinition);
                } else {
                    if ($compiler->isBundledClass($extendedClass)) {
                        $extendedDefinition = $compiler->getInternalClassDefinition($extendedClass);
                        $classDefinition->setExtendsClassDefinition($extendedDefinition);
                    } else {
                        $extendedDefinition = new ClassDefinitionRuntime($extendedClass);
                        $classDefinition->setExtendsClassDefinition($extendedDefinition);
                        $this->_logger->warning('Cannot locate class "' . $extendedClass . '" when extending class "' . $classDefinition->getCompleteName() . '"', 'nonexistent-class', $this->_originalNode);
                    }
                }
            } else {
                if ($compiler->isInterface($extendedClass)) {
                    $extendedDefinition = $compiler->getClassDefinition($extendedClass);
                    $classDefinition->setExtendsClassDefinition($extendedDefinition);
                } else {
                    if ($compiler->isBundledInterface($extendedClass)) {
                        $extendedDefinition = $compiler->getInternalClassDefinition($extendedClass);
                        $classDefinition->setExtendsClassDefinition($extendedDefinition);
                    } else {
                        $extendedDefinition = new ClassDefinitionRuntime($extendedClass);
                        $classDefinition->setExtendsClassDefinition($extendedDefinition);
                        $this->_logger->warning('Cannot locate class "' . $extendedClass . '" when extending interface "' . $classDefinition->getCompleteName() . '"', 'nonexistent-class', $this->_originalNode);
                    }
                }
            }
        }

        $implementedInterfaces = $classDefinition->getImplementedInterfaces();
        if ($implementedInterfaces) {
            $interfaceDefinitions = array();

            foreach ($implementedInterfaces as $interface) {
                if ($compiler->isInterface($interface)) {
                    $interfaceDefinitions[$interface] = $compiler->getClassDefinition($interface);
                } else {
                    if ($compiler->isBundledInterface($interface)) {
                        $interfaceDefinitions[$interface] = $compiler->getInternalClassDefinition($interface);
                    } else {
                        $extendedDefinition = new ClassDefinitionRuntime($extendedClass);
                        $classDefinition->setExtendsClassDefinition($extendedDefinition);
                        $this->_logger->warning('Cannot locate class "' . $interface . '" when extending interface "' . $classDefinition->getCompleteName() . '"', 'nonexistent-class', $this->_originalNode);
                    }
                }
            }

            if ($interfaceDefinitions) {
                $classDefinition->setImplementedInterfaceDefinitions($interfaceDefinitions);
            }
        }
    }

    /**
     * Compiles the file
     *
     * @param Compiler $compiler
     * @param StringsManager $stringsManager
     */
    abstract public function compile(CompilerAbstract $compiler, StringsManager $stringsManager);

    /**
     * Transform class/interface name to FQN format
     *
     * @param string $name
     * @return string
     */
    protected function getFullName($name)
    {
        return Utils::getFullName($name, $this->_namespace, $this->_aliasManager);
    }

    /**
     *
     * Returns the path to the source file
     *
     * @return type
     */
    public function getFilePath()
    {
        return $this->_filePath;
    }

    /**
     * @param array $types
     * @return array|null
     */
    protected function createReturnsType(Array $types)
    {
        if (empty($types)) {
            return null;
        }

        $list = array();

        foreach ($types as $type) {
            $list[] = array(
                'type' => 'return-type-parameter',
                'data-type' => $type == 'mixed' ? 'variable' : $type,
                'mandatory' => false
            );
        }

        return array(
            'type' => 'return-type',
            'list' => $list,
            'void' => empty($list),
        );
    }
}
