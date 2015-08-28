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

use Zephir\Passes\LocalContextPass;
use Zephir\Passes\StaticTypeInference;
use Zephir\Passes\CallGathererPass;
use Zephir\Documentation\Docblock;
use Zephir\Documentation\DocblockParser;
use Zephir\Statements\StatementsBlockAbstract as StatementsBlock;
use Zephir\CompilerException;
use Zephir\CompilationContext;
use Zephir\SymbolTable;

/**
 * ClassMethod
 *
 * Represents a class method
 */
abstract class ClassMethodAbstract
{
    /**
     * @var ClassDefinition
     */
    protected $classDefinition;

    /**
     * @var array
     */
    protected $visibility;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var ClassMethodParameters
     */
    protected $parameters;

    protected $statements;

    /**
     * @var string
     */
    protected $docblock;

    /**
     * @var Documentation\Docblock
     */
    protected $parsedDocblock;

    /**
     * Types returned by the method
     *
     * @var array
     */
    protected $returnTypes = array();

    /**
     * Class type hints returned by the method
     */
    protected $returnClassTypes = array();

    /**
     * Whether the variable is void
     *
     * @var boolean
     */
    protected $void = false;

    /**
     * Whether the method is public or not
     *
     * @var boolean
     */
    protected $isPublic = true;

    /**
     * Whether the method is static or not
     *
     * @var boolean
     */
    protected $isStatic = false;

    /**
     * Whether the method is final or not
     *
     * @var boolean
     */
    protected $isFinal = false;

    /**
     * Whether the method is abstract or not
     *
     * @var boolean
     */
    protected $isAbstract = false;

    /**
     * Whether the method is internal or not
     *
     * @var boolean
     */
    protected $isInternal = false;

    /**
     * Whether the method is bundled with PHP or not
     *
     * @var boolean
     */
    protected $isBundled = false;

    /**
     * Whether the method is an initializer or not
     *
     * @var boolean
     */
    protected $isInitializer = false;

    /**
     * @var array|null
     *
     * @var boolean
     */
    protected $expression;

    /**
     * LocalContextPass
     *
     * @var LocalContextPass
     */
    protected $localContext;

    /**
     * Static Type Inference Pass
     *
     * @var StaticTypeInferencePass
     */
    protected $typeInference;

    /**
     * Call Gatherer Pass
     *
     * @var CallGathererPass
     */
    protected $callGathererPass;

    /**
     * ClassMethod constructor
     *
     * @param ClassDefinition $classDefinition
     * @param array $visibility
     * @param $name
     * @param $parameters
     * @param StatementsBlock $statements
     * @param null $docblock
     * @param null $returnType
     * @param array $original
     */
    public function __construct(ClassDefinitionAbstract $classDefinition, array $visibility, $name, $parameters, StatementsBlock $statements = null, $docblock = null, $returnType = null, array $original = null)
    {
        $this->checkVisibility($visibility, $name, $original);

        $this->classDefinition = $classDefinition;
        $this->visibility = $visibility;
        $this->name = $name;
        $this->parameters = $parameters;
        $this->statements = $statements;
        $this->docblock = $docblock;
        $this->expression = $original;

        if ($returnType['void']) {
            $this->void = true;
            return;
        }

        if (isset($returnType['list'])) {
            $types = array();
            $castTypes = array();
            foreach ($returnType['list'] as $returnTypeItem) {
                if (isset($returnTypeItem['cast'])) {
                    if (isset($returnTypeItem['cast']['collection'])) {
                        continue;
                    }
                    $castTypes[$returnTypeItem['cast']['value']] = $returnTypeItem['cast']['value'];
                } else {
                    $types[$returnTypeItem['data-type']] = $returnTypeItem;
                }
            }
            if (count($castTypes)) {
                $types['object'] = array();
                $this->returnClassTypes = $castTypes;
            }
            if (count($types)) {
                $this->returnTypes = $types;
            }
        }
    }

    /**
     * Getter for statements block
     *
     * @return StatementsBlock $statements Statements block
     */
    public function getStatementsBlock()
    {
        return $this->statements;
    }

    /**
     * Setter for statements block
     *
     * @param StatementsBlock $statementsBlock
     */
    public function setStatementsBlock(StatementsBlock $statementsBlock)
    {
        $this->statements = $statementsBlock;
    }

    /**
     * Checks for visibility congruence
     *
     * @param array $visibility
     * @param string $name
     * @param array $original
     * @throws CompilerException
     */
    public function checkVisibility(array $visibility, $name, array $original = null)
    {
        if (count($visibility) > 1) {
            if (in_array('public', $visibility) && in_array('protected', $visibility)) {
                throw new CompilerException("Method '$name' cannot be 'public' and 'protected' at the same time", $original);
            }

            if (in_array('public', $visibility) && in_array('private', $visibility)) {
                throw new CompilerException("Method '$name' cannot be 'public' and 'private' at the same time", $original);
            }

            if (in_array('private', $visibility) && in_array('protected', $visibility)) {
                throw new CompilerException("Method '$name' cannot be 'protected' and 'private' at the same time", $original);
            }

            if (in_array('private', $visibility) && in_array('internal', $visibility)) {
                throw new CompilerException("Method '$name' cannot be 'internal' and 'private' at the same time", $original);
            }

            if (in_array('protected', $visibility) && in_array('internal', $visibility)) {
                throw new CompilerException("Method '$name' cannot be 'internal' and 'protected' at the same time", $original);
            }

            if (in_array('public', $visibility) && in_array('internal', $visibility)) {
                throw new CompilerException("Method '$name' cannot be 'internal' and 'public' at the same time", $original);
            }
        }

        if ($name == '__construct') {
            if (in_array('static', $visibility)) {
                throw new CompilerException("Constructors cannot be 'static'", $original);
            }
        } else {
            if ($name == '__destruct') {
                if (in_array('static', $visibility)) {
                    throw new CompilerException("Destructors cannot be 'static'", $original);
                }
            }
        }

        if (is_array($visibility)) {
            $this->isAbstract = in_array('abstract', $visibility);
            $this->isStatic = in_array('static', $visibility);
            $this->isFinal = in_array('final', $visibility);
            $this->isPublic = in_array('public', $visibility);
            $this->isInternal = in_array('internal', $visibility);
        }
    }

    /**
     * Sets if the method is internal or not
     *
     * @param boolean $static
     */
    public function setIsStatic($static)
    {
        $this->isStatic = $static;
    }

    /**
     * Sets if the method is internal or not
     *
     * @param boolean $internal
     */
    public function setIsInternal($internal)
    {
        $this->isInternal = $internal;
    }

    /**
     * Sets if the method is bundled or not
     *
     * @param boolean $bundled
     */
    public function setIsBundled($bundled)
    {
        $this->isBundled = $bundled;
    }

    /**
     * Sets if the method is an initializer or not
     *
     * @param boolean $initializer
     */
    public function setIsInitializer($initializer)
    {
        $this->isInitializer = $initializer;
    }

    /**
     * Returns the class definition where the method was declared
     *
     * @return ClassDefinition
     */
    public function getClassDefinition()
    {
        return $this->classDefinition;
    }

    /**
     * Sets the method name
     *
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Returns the method name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns the raw docblock
     *
     * @return string
     */
    public function getDocBlock()
    {
        return $this->docblock;
    }

    /**
     * Returns the parsed docblock
     *
     * @return Docblock
     */
    public function getParsedDocBlock()
    {
        if (!$this->parsedDocblock) {
            if (strlen($this->docblock) > 0) {
                $parser = new DocblockParser("/" . $this->docblock ."/");
                $this->parsedDocblock = $parser->parse();
            } else {
                return null;
            }
        }
        return $this->parsedDocblock;
    }

    /**
     * the starting line of the method in the source file
     * @return mixed
     */
    public function getLine()
    {
        return $this->expression["line"];
    }

    /**
     * the ending line of the method in the source file
     * @return mixed
     */
    public function getLastLine()
    {
        return $this->expression["last-line"];
    }

    /**
     * Returns the parameters
     *
     * @return ClassMethodParameters
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * Checks if the method has return-type or cast hints
     *
     * @return boolean
     */
    public function hasReturnTypes()
    {
        if (count($this->returnTypes)) {
            return true;
        }

        if (count($this->returnClassTypes)) {
            return true;
        }

        return false;
    }

    /**
     * Checks whether at least one return type hint is null compatible
     *
     * @param string $type
     * @return boolean
     */
    public function areReturnTypesNullCompatible($type = null)
    {
        if (count($this->returnTypes)) {
            foreach ($this->returnTypes as $returnType => $definition) {
                switch ($returnType) {
                    case 'null':
                        return true;
                }
            }
        }
        return false;
    }

    /**
     * Checks whether at least one return type hint is integer compatible
     *
     * @param string $type
     * @return boolean
     */
    public function areReturnTypesIntCompatible($type = null)
    {
        if (count($this->returnTypes)) {
            foreach ($this->returnTypes as $returnType => $definition) {
                switch ($returnType) {
                    case 'int':
                    case 'uint':
                    case 'char':
                    case 'uchar':
                    case 'long':
                    case 'ulong':
                        return true;
                }
            }
        }
        return false;
    }

    /**
     * Checks whether at least one return type hint is double compatible
     *
     * @param string $type
     * @return boolean
     */
    public function areReturnTypesDoubleCompatible($type = null)
    {
        if (count($this->returnTypes)) {
            foreach ($this->returnTypes as $returnType => $definition) {
                switch ($returnType) {
                    case 'double':
                        return true;
                }
            }
        }
        return false;
    }

    /**
     * Checks whether at least one return type hint is integer compatible
     *
     * @param string $type
     * @return boolean
     */
    public function areReturnTypesBoolCompatible($type = null)
    {
        if (count($this->returnTypes)) {
            foreach ($this->returnTypes as $returnType => $definition) {
                switch ($returnType) {
                    case 'bool':
                        return true;
                }
            }
        }
        return false;
    }

    /**
     * Checks whether at least one return type hint is integer compatible
     *
     * @param string $type
     * @return boolean
     */
    public function areReturnTypesStringCompatible($type = null)
    {
        if (count($this->returnTypes)) {
            foreach ($this->returnTypes as $returnType => $definition) {
                switch ($returnType) {
                    case 'string':
                        return true;
                }
            }
        }
        return false;
    }

    /**
     * Returned type hints by the method
     *
     * @return array
     */
    public function getReturnTypes()
    {
        return $this->returnTypes;
    }

    /**
     * Returned class-type hints by the method
     *
     * @return array
     */
    public function getReturnClassTypes()
    {
        return $this->returnClassTypes;
    }

    /**
     * Returns the number of parameters the method has
     *
     * @return boolean
     */
    public function hasParameters()
    {
        if (is_object($this->parameters)) {
            return count($this->parameters->getParameters()) > 0;
        }
        return false;
    }

    /**
     * Returns the number of parameters the method has
     *
     * @return int
     */
    public function getNumberOfParameters()
    {
        if (is_object($this->parameters)) {
            return count($this->parameters->getParameters());
        }
        return 0;
    }

    /**
     * Returns the number of required parameters the method has
     *
     * @return int
     */
    public function getNumberOfRequiredParameters()
    {
        if (is_object($this->parameters)) {
            $parameters = $this->parameters->getParameters();
            if (count($parameters)) {
                $required = 0;
                foreach ($parameters as $parameter) {
                    if (!isset($parameter['default'])) {
                        $required++;
                    }
                }
                return $required;
            }
        }
        return 0;
    }

    /**
     * Returns the number of required parameters the method has
     *
     * @return string
     */
    public function getInternalParameters()
    {
        if (is_object($this->parameters)) {
            $parameters = $this->parameters->getParameters();
            if (count($parameters)) {
                return count($parameters) . ', ...';
            }
        }
        return "";
    }

    /**
     * Checks whether the method has a specific modifier
     *
     * @param string $modifier
     * @return boolean
     */
    public function hasModifier($modifier)
    {
        foreach ($this->visibility as $visibility) {
            if ($visibility == $modifier) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns method visibility modifiers
     *
     * @return array
     */
    public function getVisibility()
    {
        return $this->visibility;
    }

    /**
     * Returns the C-modifier flags
     *
     * @return string
     * @throws Exception
     */
    abstract public function getModifiers();

    /**
     * Checks if the method must not return any value
     *
     * @return boolean
     */
    public function isVoid()
    {
        return $this->void;
    }

    /**
     * Checks if the method is inline
     *
     * @return boolean
     */
    public function isInline()
    {
        if (is_array($this->visibility)) {
            return in_array('inline', $this->visibility);
        }
        return false;
    }

    /**
     * Checks if the method is private
     *
     * @return boolean
     */
    public function isPrivate()
    {
        if (is_array($this->visibility)) {
            return in_array('private', $this->visibility);
        }
        return false;
    }

    /**
     * Checks if the method is protected
     *
     * @return boolean
     */
    public function isProtected()
    {
        if (is_array($this->visibility)) {
            return in_array('protected', $this->visibility);
        }
        return false;
    }

    /**
     * Checks if the method is public
     *
     * @return boolean
     */
    public function isPublic()
    {
        return $this->isPublic;
    }

    /**
     * Checks is abstract method?
     *
     * @return bool
     */
    public function isAbstract()
    {
        return $this->isAbstract;
    }

    /**
     * Checks whether the method is static
     *
     * @return boolean
     */
    public function isStatic()
    {
        return $this->isStatic;
    }

    /**
     * Checks whether the method is final
     *
     * @return boolean
     */
    public function isFinal()
    {
        return $this->isFinal;
    }

    /**
     * Checks whether the method is internal
     *
     * @return boolean
     */
    public function isInternal()
    {
        return $this->isInternal;
    }

    /**
     * Checks whether the method is bundled
     *
     * @return boolean
     */
    public function isBundled()
    {
        return $this->isBundled;
    }

    /**
     * Checks whether the method is an initializer
     *
     * @return boolean
     */
    public function isInitializer()
    {
        return $this->isInitializer;
    }

    /**
     * Check whether the current method is a constructor
     *
     * @return boolean
     */
    public function isConstructor()
    {
        return $this->name == '__construct';
    }

    /**
     * Checks if method is a shortcut
     *
     * @return bool
     */
    public function isShortcut()
    {
        return $this->expression && $this->expression['type'] == 'shortcut';
    }

    /**
     * Return shortcut method name
     *
     * @return mixed
     */
    public function getShortcutName()
    {
        return $this->expression['name'];
    }

    /**
     * Returns the local context pass information
     *
     * @return LocalContextPass
     */
    public function getLocalContextPass()
    {
        return $this->localContext;
    }

    /**
     * Returns the static type inference pass information
     *
     * @return StaticTypeInference
     */
    public function getStaticTypeInferencePass()
    {
        return $this->typeInference;
    }

    /**
     * Returns the call gatherer pass information
     *
     * @return CallGathererPass
     */
    public function getCallGathererPass()
    {
        return $this->callGathererPass;
    }

    /**
     * Replace macros
     *
     * @param SymbolTable $symbolTable
     * @param string $containerCode
     */
    abstract public function removeMemoryStackReferences(SymbolTable $symbolTable, $containerCode);

    /**
     * Assigns a default value
     *
     * @param array $parameter
     * @param CompilationContext $compilationContext
     * @return string
     * @throws CompilerException
     */
    abstract public function assignDefaultValue(array $parameter, CompilationContext $compilationContext);

    /**
     * Assigns a zval value to a static low-level type
     *
     * @todo rewrite this to build ifs and throw from builders
     *
     * @param array $parameter
     * @param CompilationContext $compilationContext
     * @return string
     * @throws CompilerException
     */
    abstract public function checkStrictType(array $parameter, CompilationContext $compilationContext);

    /**
     * Assigns a zval value to a static low-level type
     *
     * @param array $parameter
     * @param CompilationContext $compilationContext
     * @return string
     * @throws CompilerException
     */
    abstract public function assignZvalValue(array $parameter, CompilationContext $compilationContext);

    /**
     * Pre-compiles the method making compilation pass data (static inference, local-context-pass) available to other methods
     *
     * @param CompilationContext $compilationContext
     * @return null
     * @throws CompilerException
     */
    public function preCompile(CompilationContext $compilationContext)
    {
        $localContext = null;
        $typeInference = null;
        $callGathererPass = null;

        if (is_object($this->statements)) {
            $compilationContext->currentMethod = $this;

            /**
             * This pass checks for zval variables than can be potentially
             * used without allocating memory and track it
             * these variables are stored in the stack
             */
            if ($compilationContext->config->get('local-context-pass', 'optimizations')) {
                $localContext = new LocalContextPass();
                $localContext->pass($this->statements);
            }

            /**
             * This pass tries to infer types for dynamic variables
             * replacing them by low level variables
             */
            if ($compilationContext->config->get('static-type-inference', 'optimizations')) {
                $typeInference = new StaticTypeInference();
                $typeInference->pass($this->statements);
                if ($compilationContext->config->get('static-type-inference-second-pass', 'optimizations')) {
                    $typeInference->reduce();
                    $typeInference->pass($this->statements);
                }
            }

            /**
             * This pass counts how many times a specific
             */
            if ($compilationContext->config->get('call-gatherer-pass', 'optimizations')) {
                $callGathererPass = new CallGathererPass($compilationContext);
                $callGathererPass->pass($this->statements);
            }
        }

        $this->localContext = $localContext;
        $this->typeInference = $typeInference;
        $this->callGathererPass = $callGathererPass;
    }

    /**
     * Compiles the method
     *
     * @param CompilationContext $compilationContext
     * @return null
     * @throws CompilerException
     */
    abstract public function compile(CompilationContext $compilationContext);

    /**
     * Simple method to check if one of the paths are returning the right expected type
     *
     * @param array $statement
     * @return boolean
     */
    public function hasChildReturnStatementType($statement)
    {
        if (!isset($statement['statements']) || !is_array($statement['statements'])) {
            return false;
        }

        if ($statement['type'] == 'if') {
            $ret = false;

            $statements = $statement['statements'];
            foreach ($statements as $item) {
                $type = isset($item['type']) ? $item['type'] : null;
                if ($type == 'return' || $type == 'throw') {
                    $ret = true;
                } else {
                    $ret = $this->hasChildReturnStatementType($item);
                }
            }

            if (!$ret || !isset($statement['else_statements'])) {
                return false;
            }

            $statements = $statement['else_statements'];
            foreach ($statements as $item) {
                $type = isset($item['type']) ? $item['type'] : null;
                if ($type == 'return' || $type == 'throw') {
                    return true;
                } else {
                    return $this->hasChildReturnStatementType($item);
                }
            }
        } else {
            $statements = $statement['statements'];
            foreach ($statements as $item) {
                $type = isset($item['type']) ? $item['type'] : null;
                if ($type == 'return' || $type == 'throw') {
                    return true;
                } else {
                    return $this->hasChildReturnStatementType($item);
                }
            }
        }

        return false;
    }

    /**
     * @return string
     */
    public function getInternalName()
    {
        $classDefinition = $this->getClassDefinition();
        return 'zep_' . $classDefinition->getCNamespace() . '_' . $classDefinition->getName() . '_' . $this->getName();
    }
}
