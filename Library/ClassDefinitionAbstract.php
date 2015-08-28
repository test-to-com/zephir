<?php

/*
 +----------------------------------------------------------------------+
 | Zephir Language                                                      |
 +----------------------------------------------------------------------+
 | Copyright (c) 2013-2015 Zephir Team                                  |
 +----------------------------------------------------------------------+
 | This source file is subject to version 1.0 of the MIT license,       |
 | that is bundled with this package in the file LICENSE, and is        |
 | available through the world-wide-web at the following url:           |
 | http://www.zephir-lang.com/license                                   |
 |                                                                      |
 | If you did not receive a copy of the MIT license and are unable      |
 | to obtain it through the world-wide-web, please send a note to       |
 | license@zephir-lang.com so we can mail you a copy immediately.       |
 +----------------------------------------------------------------------+
*/

namespace Zephir;

use Zephir\Documentation\Docblock;
use Zephir\Documentation\DocblockParser;
use Zephir\EventsManager;
use Zephir\CompilerException;
use Zephir\CompilationContext;
use Zephir\AliasManager;
use Zephir\Statements\StatementsBlockAbstract;

/**
 * ClassDefinition
 *
 * Represents a class/interface and their properties and methods
 */
abstract class ClassDefinitionAbstract
{
    /**
     * @var string
     */
    protected $namespace;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $type = 'class';

    /**
     * @var string
     */
    protected $extendsClass;

    /**
     * @var array
     */
    protected $interfaces;

    /**
     * @var bool
     */
    protected $final;

    /**
     * @var bool
     */
    protected $abstract;

    /**
     * @var bool
     */
    protected $external = false;

    /**
     * @var ClassDefinition
     */
    protected $extendsClassDefinition;

    /**
     * @var ClassDefinition[]
     */
    protected $implementedInterfaceDefinitions;

    /**
     * @var ClassProperty[]
     */
    protected $properties = array();

    /**
     * @var ClassConstant[]
     */
    protected $constants = array();

    /**
     * @var ClassMethod[]
     */
    protected $methods = array();

    /**
     * @var array
     */
    protected $docBlock;

    /**
     * @var Docblock
     */
    protected $parsedDocblock;

    /**
     * @var int
     */
    protected $dependencyRank = 0;

    /**
     * @var array
     */
    protected $originalNode;

    /**
     * @var EventsManager
     */
    protected $eventsManager;

    /**
     * @var bool
     */
    protected $isBundled = false;

    /**
     * @var AliasManager
     */
    protected $_aliasManager = null;

    /**
     * Whether the constructor was generated by zephir
     * (-> no constructor existed previously)
     * @var bool
     */
    protected $isGeneratedConstructor = false;

    /**
     * ClassDefinition
     *
     * @param string $namespace
     * @param string $name
     */
    public function __construct($namespace, $name)
    {
        $this->namespace = $namespace;
        $this->name = $name;

        $this->eventsManager = new EventsManager();
    }

    /**
     * Sets if the class is internal or not
     *
     * @param boolean $isBundled
     */
    public function setIsBundled($isBundled)
    {
        $this->isBundled = $isBundled;
    }

    /**
     * Returns whether the class is bundled or not
     *
     * @return bool
     */
    public function isBundled()
    {
        return $this->isBundled;
    }


    /**
     * Sets if the class constructor was generated by zephir
     *
     * @param boolean $isGeneratedConstructor
     */
    public function setIsGeneratedConstructor($isGeneratedConstructor)
    {
        $this->isGeneratedConstructor = $isGeneratedConstructor;
    }

    /**
     * Returns whether the constructor was generated by zephir
     *
     * @return bool
     */
    public function isGeneratedConstructor()
    {
        return $this->isGeneratedConstructor;
    }

    /**
     * Sets whether the class is external or not
     *
     * @param boolean $isExternal
     */
    public function setIsExternal($isExternal)
    {
        $this->external = $isExternal;
    }

    /**
     * Returns whether the class is internal or not
     *
     * @return bool
     */
    public function isExternal()
    {
        return $this->external;
    }

    /**
     * Get eventsManager for class definition
     *
     * @return EventsManager
     */
    public function getEventsManager()
    {
        return $this->eventsManager;
    }

    /**
     * Set the class' type (class/interface)
     *
     * @param string $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * Returns the class type
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Returns the class name without namespace
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Check if the class definition correspond to an interface
     *
     * @return boolean
     */
    public function isInterface()
    {
        return $this->type == 'interface';
    }

    /**
     * Sets if the class is final
     *
     * @param boolean $final
     */
    public function setIsFinal($final)
    {
        $this->final = (bool) $final;
    }

    /**
     * Sets if the class is final
     *
     * @param boolean $abstract
     */
    public function setIsAbstract($abstract)
    {
        $this->abstract = (bool) $abstract;
    }

    /**
     * Checks whether the class is abstract or not
     *
     * @return boolean
     */
    public function isAbstract()
    {
        return $this->abstract;
    }

    /**
     * Checks whether the class is abstract or not
     *
     * @return boolean
     */
    public function isFinal()
    {
        return $this->final;
    }

    /**
     * Returns the class name including its namespace
     *
     * @return string
     */
    public function getCompleteName()
    {
        return $this->namespace . '\\' . $this->name;
    }

    /**
     * Return the class namespace
     *
     * @return string
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * Set the original node where the class was declared
     *
     * @param array $originalNode
     */
    public function setOriginalNode(array $originalNode)
    {
        $this->originalNode = $originalNode;
    }

    /**
     * Sets the extended class
     *
     * @param string $extendsClass
     */
    public function setExtendsClass($extendsClass)
    {
        $this->extendsClass = $extendsClass;
    }

    /**
     * Sets the implemented interfaces
     *
     * @param array $implementedInterfaces
     */
    public function setImplementsInterfaces(array $implementedInterfaces)
    {
        $interfaces = array();
        foreach ($implementedInterfaces as $implementedInterface) {
            $interfaces[] = $implementedInterface['value'];
        }

        $this->interfaces = $interfaces;
    }

    /**
     * Returns the extended class
     *
     * @return string
     */
    public function getExtendsClass()
    {
        return $this->extendsClass;
    }

    /**
     * Returns the implemented interfaces
     *
     * @return array
     */
    public function getImplementedInterfaces()
    {
        return $this->interfaces;
    }

    /**
     * Sets the class definition for the extended class
     *
     * @param $classDefinition
     */
    public function setExtendsClassDefinition(ClassDefinitionAbstract $classDefinition)
    {
        $this->extendsClassDefinition = $classDefinition;
    }

    /**
     * Returns the class definition related to the extended class
     *
     * @return ClassDefinition
     */
    public function getExtendsClassDefinition()
    {
        return $this->extendsClassDefinition;
    }

    /**
     * Sets the class definition for the implemented interfaces
     *
     * @param ClassDefinition[] $implementedInterfaceDefinitions
     */
    public function setImplementedInterfaceDefinitions(array $implementedInterfaceDefinitions)
    {
        $this->implementedInterfaceDefinitions = $implementedInterfaceDefinitions;
    }

    /**
     * Returns the class definition for the implemented interfaces
     *
     * @return ClassDefinition[]
     */
    public function getImplementedInterfaceDefinitions()
    {
        return $this->implementedInterfaceDefinitions;
    }

    /**
     * Calculate the dependency rank of the class based on its dependencies
     *
     */
    public function getDependencies()
    {
        $dependencies = array();
        if ($this->extendsClassDefinition) {
            $classDefinition = $this->extendsClassDefinition;
            if (method_exists($classDefinition, 'increaseDependencyRank')) {
                $dependencies[] = $classDefinition;
            }
        }

        if ($this->implementedInterfaceDefinitions) {
            foreach ($this->implementedInterfaceDefinitions as $interfaceDefinition) {
                if (method_exists($interfaceDefinition, 'increaseDependencyRank')) {
                    $dependencies[] = $interfaceDefinition;
                }
            }
        }
        return $dependencies;
    }

    /**
     * A class definition calls this method to mark this class as a dependency of another
     *
     * @param int $rank
     */
    public function increaseDependencyRank($rank)
    {
        $this->dependencyRank += ($rank + 1);
    }

    /**
     * Returns the dependency rank for this class
     *
     * @return int
     */
    public function getDependencyRank()
    {
        return $this->dependencyRank;
    }

    /**
     * Sets the class/interface docBlock
     *
     * @param array $docBlock
     */
    public function setDocBlock($docBlock)
    {
        $this->docBlock = $docBlock;
    }

    /**
     * Returns the class/interface docBlock
     *
     * @return array
     */
    public function getDocBlock()
    {
        return $this->docBlock;
    }

    /**
     * Returns the parsed docBlock
     *
     * @return DocBlock
     */
    public function getParsedDocBlock()
    {
        if (!$this->parsedDocblock) {
            if (strlen($this->docBlock) > 0) {
                $parser = new DocblockParser("/" . $this->docBlock ."/");
                $this->parsedDocblock = $parser->parse();
            } else {
                return null;
            }
        }
        return $this->parsedDocblock;
    }


    /**
     * Adds a property to the definition
     *
     * @param ClassProperty $property
     * @throws CompilerException
     */
    public function addProperty(ClassPropertyAbstract $property)
    {
        if (isset($this->properties[$property->getName()])) {
            throw new CompilerException("Property '" . $property->getName() . "' was defined more than one time", $property->getOriginal());
        }

        $this->properties[$property->getName()] = $property;
    }

    /**
     * Adds a constant to the definition
     *
     * @param ClassConstant $constant
     * @throws CompilerException
     */
    public function addConstant(ClassConstantAbstract $constant)
    {
        if (isset($this->constants[$constant->getName()])) {
            throw new CompilerException("Constant '" . $constant->getName() . "' was defined more than one time");
        }

        $this->constants[$constant->getName()] = $constant;
    }

    /**
     * Checks if a class definition has a property
     *
     * @param string $name
     * @return boolean
     */
    public function hasProperty($name)
    {
        if (isset($this->properties[$name])) {
            return true;
        } else {
            $extendsClassDefinition = $this->extendsClassDefinition;
            if ($extendsClassDefinition) {
                if ($extendsClassDefinition->hasProperty($name)) {
                    return true;
                }
            }
            return false;
        }
    }

    /**
     * Returns a method definition by its name
     *
     * @param string string
     * @return boolean|ClassProperty
     */
    public function getProperty($propertyName)
    {
        if (isset($this->properties[$propertyName])) {
            return $this->properties[$propertyName];
        }

        $extendsClassDefinition = $this->extendsClassDefinition;
        if ($extendsClassDefinition) {
            if ($extendsClassDefinition->hasProperty($propertyName)) {
                return $extendsClassDefinition->getProperty($propertyName);
            }
        }
        return false;
    }

    /**
     * Checks if class definition has a property
     *
     * @param string $name
     */
    public function hasConstant($name)
    {
        if (isset($this->constants[$name])) {
            return true;
        }

        /**
         * @todo add code to check if constant is defined in interfaces
         */
        return false;
    }

    /**
     * Returns a constant definition by its name
     *
     * @param string $constantName
     * @return bool|ClassConstant
     */
    public function getConstant($constantName)
    {
        if (!is_string($constantName)) {
            throw new \InvalidArgumentException('$constantName must be string type');
        }

        if (empty($constantName)) {
            throw new \InvalidArgumentException('$constantName must not be empty: ' . $constantName);
        }

        if (isset($this->constants[$constantName])) {
            return $this->constants[$constantName];
        }

        /**
         * @todo add code to get constant from interfaces
         */
        return false;
    }

    /**
     * Adds a method to the class definition
     *
     * @param ClassMethod $method
     * @param array $statement
     */
    public function addMethod(ClassMethodAbstract $method, $statement = null)
    {
        $methodName = strtolower($method->getName());
        if (isset($this->methods[$methodName])) {
            throw new CompilerException("Method '" . $method->getName() . "' was defined more than one time", $statement);
        }

        $this->methods[$methodName] = $method;
    }

    /**
     * Updates an existing method definition
     *
     * @param ClassMethod $method
     * @param array $statement
     */
    public function updateMethod(ClassMethodAbstract $method, $statement = null)
    {
        $methodName = strtolower($method->getName());
        if (!isset($this->methods[$methodName])) {
            throw new CompilerException("Method '" . $method->getName() . "' does not exist", $statement);
        }

        $this->methods[$methodName] = $method;
    }

    /**
     * Returns all properties defined in the class
     *
     * @return ClassProperty[]
     */
    public function getProperties()
    {
        return $this->properties;
    }

    /**
     * Returns all constants defined in the class
     *
     * @return ClassConstant[]
     */
    public function getConstants()
    {
        return $this->constants;
    }

    /**
     * Returns all methods defined in the class
     * @return ClassMethod[]
     */
    public function getMethods()
    {
        return $this->methods;
    }

    /**
     * Checks if the class implements an specific name
     *
     * @param string string
     * @return boolean
     */
    public function hasMethod($methodName)
    {
        $methodNameLower = strtolower($methodName);
        foreach ($this->methods as $name => $method) {
            if ($methodNameLower == $name) {
                return true;
            }
        }

        $extendsClassDefinition = $this->extendsClassDefinition;
        if ($extendsClassDefinition) {
            if ($extendsClassDefinition->hasMethod($methodName)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns a method by its name
     *
     * @param string string
     * @return boolean|ClassMethod
     */
    public function getMethod($methodName)
    {
        $methodNameLower = strtolower($methodName);
        foreach ($this->methods as $name => $method) {
            if ($methodNameLower == $name) {
                return $method;
            }
        }

        $extendsClassDefinition = $this->extendsClassDefinition;
        if ($extendsClassDefinition) {
            if ($extendsClassDefinition->hasMethod($methodName)) {
                return $extendsClassDefinition->getMethod($methodName);
            }
        }
        return false;
    }

    /**
     * Set a method and its body
     *
     * @param $methodName
     * @param ClassMethod $method
     */
    public function setMethod($methodName, ClassMethod $method)
    {
        $this->methods[$methodName] = $method;
    }

    /**
     * Sets class methods externally
     *
     * @param array $methods
     */
    public function setMethods($methods)
    {
        $this->methods = $methods;
    }

    /**
     * Tries to find the most similar name
     *
     * @param string $methodName
     * @return string|boolean
     */
    public function getPossibleMethodName($methodName)
    {
        $methodNameLower = strtolower($methodName);

        foreach ($this->methods as $name => $method) {
            if (metaphone($methodNameLower) == metaphone($name)) {
                return $method->getName();
            }
        }

        $extendsClassDefinition = $this->extendsClassDefinition;
        if ($extendsClassDefinition) {
            return $extendsClassDefinition->getPossibleMethodName($methodName);
        }

        return false;
    }

    /**
     * Returns the name of the zend_class_entry according to the class name
     *
     * @param CompilationContext $compilationContext
     * @return string
     */
    abstract public function getClassEntry(CompilationContext $compilationContext = null);

    /**
     * Returns a valid namespace to be used in C-sources
     *
     * @return string
     */
    public function getCNamespace()
    {
        return str_replace('\\', '_', $this->namespace);
    }

    /**
     * Returns a valid namespace to be used in C-sources
     *
     * @return string
     */
    public function getNCNamespace()
    {
        return str_replace('\\', '\\\\', $this->namespace);
    }

    /**
     * Class name without namespace prefix for class registration
     *
     * @param string $namespace
     * @return string
     */
    public function getSCName($namespace)
    {
        return str_replace($namespace . "_", "", strtolower(str_replace('\\', '_', $this->namespace) . '_' . $this->name));
    }

    /**
     * Returns an absolute location to the class header
     *
     * @return string
     */
    abstract public function getExternalHeader();

    /**
     * Checks if a class implements an interface
     *
     * @param ClassDefinition $classDefinition
     * @param ClassDefinition $interfaceDefinition
     * @throws CompilerException
     */
    public function checkInterfaceImplements(ClassDefinitionAbstract $classDefinition, ClassDefinitionAbstract $interfaceDefinition)
    {
        foreach ($interfaceDefinition->getMethods() as $method) {
            if (!$classDefinition->hasMethod($method->getName())) {
                throw new CompilerException("Class " . $classDefinition->getCompleteName() . " must implement method: " . $method->getName() . " defined on interface: " . $interfaceDefinition->getCompleteName());
            }

            if ($method->hasParameters()) {
                $implementedMethod = $classDefinition->getMethod($method->getName());
                if ($implementedMethod->getNumberOfRequiredParameters() > $method->getNumberOfRequiredParameters() || $implementedMethod->getNumberOfParameters() < $method->getNumberOfParameters()) {
                    throw new CompilerException("Class " . $classDefinition->getCompleteName() . "::" . $method->getName() . "() does not have the same number of required parameters in interface: " . $interfaceDefinition->getCompleteName());
                }
            }
        }
    }

    /**
     * Pre-compiles a class/interface gathering method information required by other methods
     *
     * @param CompilationContext $compilationContext
     * @throws CompilerException
     */
    public function preCompile(CompilationContext $compilationContext)
    {
        /**
         * Pre-Compile methods
         */
        foreach ($this->methods as $method) {
            if ($this->getType() == 'class' && !$method->isAbstract()) {
                $method->preCompile($compilationContext);
            }
        }
    }

    /**
     * Returns the initialization method if any does exist
     *
     * @return ClassMethod
     */
    abstract public function getInitMethod();

    /**
     * Returns the initialization method if any does exist
     *
     * @return ClassMethod
     */
    abstract public function getStaticInitMethod();

    /**
     * Returns the initialization method if any does exist
     *
     * @return ClassMethod
     */
    public function getLocalOrParentInitMethod()
    {
        $method = $this->getInitMethod();
        if (!$method) {
            $parentClassDefinition = $this->getExtendsClassDefinition();
            if ($parentClassDefinition) {
                $method = $parentClassDefinition->getInitMethod();
                if ($method) {
                    $this->addInitMethod($method->getStatementsBlock());
                }
            }
        }
        return $method;
    }

    /**
     * Creates the initialization method
     *
     * @param StatementsBlockAbstract $statementsBlock
     */
    abstract public function addInitMethod(StatementsBlockAbstract $statementsBlock);

    /**
     * Creates the static initialization method
     *
     * @param StatementsBlockAbstract $statementsBlock
     */
    abstract public function addStaticInitMethod(StatementsBlockAbstract $statementsBlock);

    /**
     * Compiles a class/interface
     *
     * @param CompilationContext $compilationContext
     * @throws CompilerException
     */
    abstract public function compile(CompilationContext $compilationContext);

    /**
     * @return AliasManager
     */
    public function getAliasManager()
    {
        return $this->_aliasManager;
    }

    /**
     * @param AliasManager $aliasManager
     */
    public function setAliasManager(AliasManager $aliasManager)
    {
        $this->_aliasManager = $aliasManager;
    }

    /**
     * Convert Class/Interface name to C ClassEntry
     *
     * @param  string $className
     * @param  CompilationContext $compilationContext
     * @param  boolean $check
     * @return string
     * @throws CompilerException
     */
    abstract public function getClassEntryByClassName($className, CompilationContext $compilationContext, $check = true);
}