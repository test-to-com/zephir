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

namespace Zephir\C;

use Zephir\HeadersManager;
use Zephir\CompilerException;
use Zephir\CompilationContext;
use Zephir\Utils;
use Zephir\CodePrinter;
use Zephir\Statements\StatementsBlockAbstract;
/**
 * ClassDefinition
 *
 * Represents a class/interface and their properties and methods
 */
class ClassDefinition extends \Zephir\ClassDefinitionAbstract
{
    /**
     * Returns the name of the zend_class_entry according to the class name
     *
     * @param CompilationContext $compilationContext
     * @return string
     */
    public function getClassEntry(CompilationContext $compilationContext = null)
    {
        if ($this->external) {
            if (!is_object($compilationContext)) {
                throw new \Exception('A compilation context is required');
            }

            /**
             * Automatically add the external header
             */
            $compilationContext->headersManager->add($this->getExternalHeader(), HeadersManager::POSITION_LAST);
        }
        return strtolower(str_replace('\\', '_', $this->namespace) . '_' . $this->name) . '_ce';
    }


    /**
     * Returns an absolute location to the class header
     *
     * @return string
     */
    public function getExternalHeader()
    {
        $parts = explode('\\', $this->namespace);
        return 'ext/' . strtolower($parts[0] . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $this->namespace) . DIRECTORY_SEPARATOR . $this->name) . '.zep';
    }

    /**
     * Returns the signature of an internal method
     *
     * @return string
     */
    private function getInternalSignature(ClassMethod $method)
    {
        if ($method->isInitializer() && !$method->isStatic()) {
            return 'static zend_object_value ' . $method->getName() . '(zend_class_entry *class_type TSRMLS_DC)';
        }

        if ($method->isInitializer() && $method->isStatic()) {
            return 'void ' . $method->getName() . '(TSRMLS_D)';
        }

        $signatureParameters = array();
        $parameters = $method->getParameters();
        if (is_object($parameters)) {
            foreach ($parameters->getParameters() as $parameter) {
                switch ($parameter['data-type']) {
                    case 'int':
                    case 'uint':
                    case 'long':
                    case 'double':
                    case 'bool':
                    case 'char':
                    case 'uchar':
                        $signatureParameters[] = 'zval *' . $parameter['name'] . '_param_ext';
                        break;

                    default:
                        $signatureParameters[] = 'zval *' . $parameter['name'] . '_ext';
                        break;
                }
            }
        }

        if (count($signatureParameters)) {
            return 'static void ' . $method->getInternalName() . '(int ht, zval *return_value, zval **return_value_ptr, zval *this_ptr, int return_value_used, ' . join(', ', $signatureParameters) . ' TSRMLS_DC)';
        }

        return 'static void ' . $method->getInternalName() . '(int ht, zval *return_value, zval **return_value_ptr, zval *this_ptr, int return_value_used TSRMLS_DC)';
    }

    /**
     * Returns the initialization method if any does exist
     *
     * @return ClassMethod
     */
    public function getInitMethod()
    {
        $initClassName = $this->getCNamespace() . '_' . $this->getName();
        return $this->getMethod('zephir_init_properties_' . $initClassName);
    }

    /**
     * Returns the initialization method if any does exist
     *
     * @return ClassMethod
     */
    public function getStaticInitMethod()
    {
        $initClassName = $this->getCNamespace() . '_' . $this->getName();
        return $this->getMethod('zephir_init_static_properties_' . $initClassName);
    }

    /**
     * Creates the initialization method
     *
     * @param StatementsBlockAbstract $statementsBlock
     */
    public function addInitMethod(StatementsBlockAbstract $statementsBlock)
    {
        $initClassName = $this->getCNamespace() . '_' . $this->getName();

        $classMethod = new ClassMethod(
            $this,
            array('internal'),
            'zephir_init_properties_' . $initClassName,
            null,
            $statementsBlock
        );

        $classMethod->setIsInitializer(true);
        $this->addMethod($classMethod);
    }

    /**
     * Creates the static initialization method
     *
     * @param StatementsBlockAbstract $statementsBlock
     */
    public function addStaticInitMethod(StatementsBlockAbstract $statementsBlock)
    {
        $initClassName = $this->getCNamespace() . '_' . $this->getName();

        $classMethod = new ClassMethod(
            $this,
            array('internal'),
            'zephir_init_static_properties_' . $initClassName,
            null,
            $statementsBlock
        );

        $classMethod->setIsInitializer(true);
        $classMethod->setIsStatic(true);
        $this->addMethod($classMethod);
    }

    /**
     * Compiles a class/interface
     *
     * @param CompilationContext $compilationContext
     * @throws CompilerException
     */
    public function compile(CompilationContext $compilationContext)
    {
        /**
         * Sets the current object as global class definition
         */
        $compilationContext->classDefinition = $this;

        /**
         * Get the global codePrinter
         */
        $codePrinter = $compilationContext->codePrinter;

        /**
         * The ZEPHIR_INIT_CLASS defines properties and constants exported by the class
         */
        $initClassName = $this->getCNamespace() . '_' . $this->getName();
        $codePrinter->output('ZEPHIR_INIT_CLASS(' . $initClassName . ') {');
        $codePrinter->outputBlankLine();

        $codePrinter->increaseLevel();

        /**
         * Method entry
         */
        $methods = &$this->methods;
        $initMethod = $this->getLocalOrParentInitMethod();

        if (count($methods) || $initMethod) {
            $methodEntry = strtolower($this->getCNamespace()) . '_' . strtolower($this->getName()) . '_method_entry';
        } else {
            $methodEntry = 'NULL';
        }

        $namespace = str_replace('\\', '_', $compilationContext->config->get('namespace'));

        $flags = '0';
        if ($this->isAbstract()) {
            $flags = 'ZEND_ACC_EXPLICIT_ABSTRACT_CLASS';
        }
        if ($this->isFinal()) {
            if ($flags == '0') {
                $flags = 'ZEND_ACC_FINAL_CLASS';
            } else {
                $flags .= '|ZEND_ACC_FINAL_CLASS';
            }
        }

        /**
         * Register the class with extends + interfaces
         */
        $classExtendsDefinition = null;
        if ($this->extendsClass) {
            $classExtendsDefinition = $this->extendsClassDefinition;
            if (!$classExtendsDefinition->isBundled()) {
                $classEntry = $classExtendsDefinition->getClassEntry($compilationContext);
            } else {
                $classEntry = $this->getClassEntryByClassName($classExtendsDefinition->getName(), $compilationContext);
            }

            if ($this->getType() == 'class') {
                $codePrinter->output('ZEPHIR_REGISTER_CLASS_EX(' . $this->getNCNamespace() . ', ' . $this->getName() . ', ' . $namespace . ', ' . strtolower($this->getSCName($namespace)) . ', ' . $classEntry . ', ' . $methodEntry . ', ' . $flags . ');');
                $codePrinter->outputBlankLine();
            } else {
                $codePrinter->output('ZEPHIR_REGISTER_INTERFACE_EX(' . $this->getNCNamespace() . ', ' . $this->getName() . ', ' . $namespace . ', ' . strtolower($this->getSCName($namespace)) . ', ' . $classEntry . ', ' . $methodEntry . ');');
                $codePrinter->outputBlankLine();
            }
        } else {
            if ($this->getType() == 'class') {
                $codePrinter->output('ZEPHIR_REGISTER_CLASS(' . $this->getNCNamespace() . ', ' . $this->getName() . ', ' . $namespace . ', ' . strtolower($this->getSCName($namespace)) . ', ' . $methodEntry . ', ' . $flags . ');');
            } else {
                $codePrinter->output('ZEPHIR_REGISTER_INTERFACE(' . $this->getNCNamespace() . ', ' . $this->getName() . ', ' . $namespace . ', ' . strtolower($this->getSCName($namespace)) . ', ' . $methodEntry . ');');
            }
            $codePrinter->outputBlankLine();
        }

        /**
         * Compile properties
         * @var $property ClassProperty
         */
        foreach ($this->getProperties() as $property) {
            $docBlock = $property->getDocBlock();
            if ($docBlock) {
                $codePrinter->outputDocBlock($docBlock, true);
            }

            $property->compile($compilationContext);
            $codePrinter->outputBlankLine();
        }

        $initMethod = $this->getInitMethod();
        if ($initMethod) {
            $codePrinter->output($namespace . '_' . strtolower($this->getSCName($namespace)) . '_ce->create_object = ' . $initMethod->getName() . ';');
        }

        /**
         * Compile constants
         * @var $constant ClassConstant
         */
        foreach ($this->getConstants() as $constant) {
            $docBlock = $constant->getDocBlock();
            if ($docBlock) {
                $codePrinter->outputDocBlock($docBlock, true);
            }

            $constant->compile($compilationContext);
            $codePrinter->outputBlankLine();
        }

        /**
         * Implemented interfaces
         */
        $interfaces = $this->interfaces;
        $compiler = $compilationContext->compiler;

        if (is_array($interfaces)) {
            $codePrinter->outputBlankLine(true);

            foreach ($interfaces as $interface) {
                /**
                 * Try to find the interface
                 */
                $classEntry = false;

                if ($compiler->isInterface($interface)) {
                    $classInterfaceDefinition = $compiler->getClassDefinition($interface);
                    $classEntry = $classInterfaceDefinition->getClassEntry($compilationContext);
                } else {
                    if ($compiler->isBundledInterface($interface)) {
                        $classInterfaceDefinition = $compiler->getInternalClassDefinition($interface);
                        $classEntry = $this->getClassEntryByClassName($classInterfaceDefinition->getName(), $compilationContext);
                    }
                }

                if (!$classEntry) {
                    if ($compiler->isClass($interface)) {
                        throw new CompilerException("Cannot locate interface " . $interface . " when implementing interfaces on " . $this->getCompleteName() . '. ' . $interface . ' is currently a class', $this->originalNode);
                    } else {
                        throw new CompilerException("Cannot locate interface " . $interface . " when implementing interfaces on " . $this->getCompleteName(), $this->originalNode);
                    }
                }

                /**
                 * We don't check if abstract classes implement the methods in their interfaces
                 */
                if (!$this->isAbstract() && !$this->isInterface()) {
                    $this->checkInterfaceImplements($this, $classInterfaceDefinition);
                }

                $codePrinter->output('zend_class_implements(' . $this->getClassEntry() . ' TSRMLS_CC, 1, ' . $classEntry . ');');
            }
        }

        if (!$this->isAbstract() && !$this->isInterface()) {
            /**
             * Interfaces in extended classes may have
             */
            if ($classExtendsDefinition) {
                if (!$classExtendsDefinition->isBundled()) {
                    $interfaces = $classExtendsDefinition->getImplementedInterfaces();
                    if (is_array($interfaces)) {
                        foreach ($interfaces as $interface) {
                            $classInterfaceDefinition = null;
                            if ($compiler->isInterface($interface)) {
                                $classInterfaceDefinition = $compiler->getClassDefinition($interface);
                            } else {
                                if ($compiler->isBundledInterface($interface)) {
                                    $classInterfaceDefinition = $compiler->getInternalClassDefinition($interface);
                                }
                            }

                            if ($classInterfaceDefinition) {
                                $this->checkInterfaceImplements($this, $classInterfaceDefinition);
                            }
                        }
                    }
                }
            }
        }

        $codePrinter->output('return SUCCESS;');

        $codePrinter->outputBlankLine();
        $codePrinter->decreaseLevel();

        $codePrinter->output('}');
        $codePrinter->outputBlankLine();

        /**
         * Compile methods
         */
        foreach ($methods as $method) {
            $docBlock = $method->getDocBlock();
            if ($docBlock) {
                $codePrinter->outputDocBlock($docBlock);
            }

            if ($this->getType() == 'class') {
                if (!$method->isInternal()) {
                    $codePrinter->output('PHP_METHOD(' . $this->getCNamespace() . '_' . $this->getName() . ', ' . $method->getName() . ') {');
                } else {
                    $codePrinter->output($this->getInternalSignature($method) . ' {');
                }
                $codePrinter->outputBlankLine();

                if (!$method->isAbstract()) {
                    $method->compile($compilationContext);
                }

                $codePrinter->output('}');
                $codePrinter->outputBlankLine();
            } else {
                $codePrinter->output('ZEPHIR_DOC_METHOD(' . $this->getCNamespace() . '_' . $this->getName() . ', ' . $method->getName() . ');');
                $codePrinter->outputBlankLine();
            }
        }

        /**
         * Check whether classes must be exported
         */
        $exportClasses = $compilationContext->config->get('export-classes', 'extra');
        if ($exportClasses) {
            $exportAPI = 'extern ZEPHIR_API';
        } else {
            $exportAPI = 'extern';
        }

        /**
         * Create a code printer for the header file
         */
        $codePrinter = new CodePrinter();

        $codePrinter->outputBlankLine();
        $codePrinter->output($exportAPI . ' zend_class_entry *' . $this->getClassEntry() . ';');
        $codePrinter->outputBlankLine();

        $codePrinter->output('ZEPHIR_INIT_CLASS(' . $this->getCNamespace() . '_' . $this->getName() . ');');
        $codePrinter->outputBlankLine();

        if ($this->getType() == 'class') {
            if (count($methods)) {
                foreach ($methods as $method) {
                    if (!$method->isInternal()) {
                        $codePrinter->output('PHP_METHOD(' . $this->getCNamespace() . '_' . $this->getName() . ', ' . $method->getName() . ');');
                    } else {
                        $codePrinter->output($this->getInternalSignature($method) . ';');
                    }
                }
                $codePrinter->outputBlankLine();
            }
        }

        /**
         * Create argument info
         */
        foreach ($methods as $method) {
            $parameters = $method->getParameters();
            if (count($parameters)) {
                $codePrinter->output('ZEND_BEGIN_ARG_INFO_EX(arginfo_' . strtolower($this->getCNamespace() . '_' . $this->getName() . '_' . $method->getName()) . ', 0, 0, ' . $method->getNumberOfRequiredParameters() . ')');
                foreach ($parameters->getParameters() as $parameter) {
                    switch ($parameter['data-type']) {
                        case 'array':
                            $codePrinter->output("\t" . 'ZEND_ARG_ARRAY_INFO(0, ' . $parameter['name'] . ', ' . (isset($parameter['default']) ? 1 : 0) . ')');
                            break;

                        case 'variable':
                            if (isset($parameter['cast'])) {
                                switch ($parameter['cast']['type']) {
                                    case 'variable':
                                        $value = $parameter['cast']['value'];
                                        $codePrinter->output("\t" . 'ZEND_ARG_OBJ_INFO(0, ' . $parameter['name'] . ', ' . Utils::escapeClassName($compilationContext->getFullName($value)) . ', ' . (isset($parameter['default']) ? 1 : 0) . ')');
                                        break;

                                    default:
                                        throw new \Exception('Unexpected exception');
                                }
                            } else {
                                $codePrinter->output("\t" . 'ZEND_ARG_INFO(0, ' . $parameter['name'] . ')');
                            }
                            break;

                        default:
                            $codePrinter->output("\t" . 'ZEND_ARG_INFO(0, ' . $parameter['name'] . ')');
                            break;
                    }
                }
                $codePrinter->output('ZEND_END_ARG_INFO()');
                $codePrinter->outputBlankLine();
            }
        }

        if (count($methods)) {
            $codePrinter->output('ZEPHIR_INIT_FUNCS(' . strtolower($this->getCNamespace() . '_' . $this->getName()) . '_method_entry) {');
            foreach ($methods as $method) {
                $parameters = $method->getParameters();
                if ($this->getType() == 'class') {
                    if (!$method->isInternal()) {
                        if (count($parameters)) {
                            $codePrinter->output("\t" . 'PHP_ME(' . $this->getCNamespace() . '_' . $this->getName() . ', ' . $method->getName() . ', arginfo_' . strtolower($this->getCNamespace() . '_' . $this->getName() . '_' . $method->getName()) . ', ' . $method->getModifiers() . ')');
                        } else {
                            $codePrinter->output("\t" . 'PHP_ME(' . $this->getCNamespace() . '_' . $this->getName() . ', ' . $method->getName() . ', NULL, ' . $method->getModifiers() . ')');
                        }
                    }
                } else {
                    if ($method->isStatic()) {
                        if (count($parameters)) {
                            $codePrinter->output("\t" . 'ZEND_FENTRY(' . $method->getName() . ', NULL, arginfo_' . strtolower($this->getCNamespace() . '_' . $this->getName() . '_' . $method->getName()) . ', ZEND_ACC_STATIC|ZEND_ACC_ABSTRACT|ZEND_ACC_PUBLIC)');
                        } else {
                            $codePrinter->output("\t" . 'ZEND_FENTRY(' . $method->getName() . ', NULL, NULL, ZEND_ACC_STATIC|ZEND_ACC_ABSTRACT|ZEND_ACC_PUBLIC)');
                        }
                    } else {
                        if (count($parameters)) {
                            $codePrinter->output("\t" . 'PHP_ABSTRACT_ME(' . $this->getCNamespace() . '_' . $this->getName() . ', ' . $method->getName() . ', arginfo_' . strtolower($this->getCNamespace() . '_' . $this->getName() . '_' . $method->getName()) . ')');
                        } else {
                            $codePrinter->output("\t" . 'PHP_ABSTRACT_ME(' . $this->getCNamespace() . '_' . $this->getName() . ', ' . $method->getName() . ', NULL)');
                        }
                    }
                }
            }
            $codePrinter->output("\t" . 'PHP_FE_END');
            $codePrinter->output('};');
        }

        $compilationContext->headerPrinter = $codePrinter;
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
    public function getClassEntryByClassName($className, CompilationContext $compilationContext, $check = true)
    {
        switch (strtolower($className)) {
            /**
             * Zend classes
             */
            case 'exception':
                $classEntry = 'zend_exception_get_default(TSRMLS_C)';
                break;

            /**
             * Zend interfaces (Zend/zend_interfaces.h)
             */
            case 'iterator':
                $classEntry = 'zend_ce_iterator';
                break;

            case 'arrayaccess':
                $classEntry = 'zend_ce_arrayaccess';
                break;

            case 'serializable':
                $classEntry = 'zend_ce_serializable';
                break;

            case 'iteratoraggregate':
                $classEntry = 'zend_ce_aggregate';
                break;

            /**
             * SPL Exceptions
             */
            case 'logicexception':
                $compilationContext->headersManager->add('ext/spl/spl_exceptions');
                $classEntry = 'spl_ce_LogicException';
                break;

            case 'badfunctioncallexception':
                $compilationContext->headersManager->add('ext/spl/spl_exceptions');
                $classEntry = 'spl_ce_BadFunctionCallException';
                break;

            case 'badmethodcallexception':
                $compilationContext->headersManager->add('ext/spl/spl_exceptions');
                $classEntry = 'spl_ce_BadMethodCallException';
                break;

            case 'domainexception':
                $compilationContext->headersManager->add('ext/spl/spl_exceptions');
                $classEntry = 'spl_ce_DomainException';
                break;

            case 'invalidargumentexception':
                $compilationContext->headersManager->add('ext/spl/spl_exceptions');
                $classEntry = 'spl_ce_InvalidArgumentException';
                break;

            case 'lengthexception':
                $compilationContext->headersManager->add('ext/spl/spl_exceptions');
                $classEntry = 'spl_ce_LengthException';
                break;

            case 'outofrangeexception':
                $compilationContext->headersManager->add('ext/spl/spl_exceptions');
                $classEntry = 'spl_ce_OutOfRangeException';
                break;

            case 'runtimeexception':
                $compilationContext->headersManager->add('ext/spl/spl_exceptions');
                $classEntry = 'spl_ce_RuntimeException';
                break;

            case 'outofboundsexception':
                $compilationContext->headersManager->add('ext/spl/spl_exceptions');
                $classEntry = 'spl_ce_OutOfBoundsException';
                break;

            case 'overflowexception':
                $compilationContext->headersManager->add('ext/spl/spl_exceptions');
                $classEntry = 'spl_ce_OverflowException';
                break;

            case 'rangeexception':
                $compilationContext->headersManager->add('ext/spl/spl_exceptions');
                $classEntry = 'spl_ce_RangeException';
                break;

            case 'underflowexception':
                $compilationContext->headersManager->add('ext/spl/spl_exceptions');
                $classEntry = 'spl_ce_UnderflowException';
                break;

            case 'unexpectedvalueexception':
                $compilationContext->headersManager->add('ext/spl/spl_exceptions');
                $classEntry = 'spl_ce_UnexpectedValueException';
                break;

            /**
             * SPL Iterators Interfaces (spl/spl_iterators.h)
             */
            case 'recursiveiterator':
                $compilationContext->headersManager->add('ext/spl/spl_iterators');
                $classEntry = 'spl_ce_RecursiveIterator';
                break;

            case 'recursiveiteratoriterator':
                $compilationContext->headersManager->add('ext/spl/spl_iterators');
                $classEntry = 'spl_ce_RecursiveIteratorIterator';
                break;

            case 'recursivetreeiterator':
                $compilationContext->headersManager->add('ext/spl/spl_iterators');
                $classEntry = 'spl_ce_RecursiveTreeIterator';
                break;

            case 'filteriterator':
                $compilationContext->headersManager->add('ext/spl/spl_iterators');
                $classEntry = 'spl_ce_FilterIterator';
                break;

            case 'recursivefilteriterator':
                $compilationContext->headersManager->add('ext/spl/spl_iterators');
                $classEntry = 'spl_ce_RecursiveFilterIterator';
                break;

            case 'parentiterator':
                $compilationContext->headersManager->add('ext/spl/spl_iterators');
                $classEntry = 'spl_ce_ParentIterator';
                break;

            case 'seekableiterator':
                $compilationContext->headersManager->add('ext/spl/spl_iterators');
                $classEntry = 'spl_ce_SeekableIterator';
                break;

            case 'limititerator':
                $compilationContext->headersManager->add('ext/spl/spl_iterators');
                $classEntry = 'spl_ce_LimitIterator';
                break;

            case 'cachingiterator':
                $compilationContext->headersManager->add('ext/spl/spl_iterators');
                $classEntry = 'spl_ce_CachingIterator';
                break;

            case 'recursivecachingiterator':
                $compilationContext->headersManager->add('ext/spl/spl_iterators');
                $classEntry = 'spl_ce_RecursiveCachingIterator';
                break;

            case 'outeriterator':
                $compilationContext->headersManager->add('ext/spl/spl_iterators');
                $classEntry = 'spl_ce_OuterIterator';
                break;

            case 'iteratoriterator':
                $compilationContext->headersManager->add('ext/spl/spl_iterators');
                $classEntry = 'spl_ce_IteratorIterator';
                break;

            case 'norewinditerator':
                $compilationContext->headersManager->add('ext/spl/spl_iterators');
                $classEntry = 'spl_ce_NoRewindIterator';
                break;

            case 'infiniteiterator':
                $compilationContext->headersManager->add('ext/spl/spl_iterators');
                $classEntry = 'spl_ce_InfiniteIterator';
                break;

            case 'emptyiterator':
                $compilationContext->headersManager->add('ext/spl/spl_iterators');
                $classEntry = 'spl_ce_EmptyIterator';
                break;

            case 'appenditerator':
                $compilationContext->headersManager->add('ext/spl/spl_iterators');
                $classEntry = 'spl_ce_AppendIterator';
                break;

            case 'regexiterator':
                $compilationContext->headersManager->add('ext/spl/spl_iterators');
                $classEntry = 'spl_ce_RegexIterator';
                break;

            case 'recursiveregexiterator':
                $compilationContext->headersManager->add('ext/spl/spl_iterators');
                $classEntry = 'spl_ce_RecursiveRegexIterator';
                break;

            case 'directoryiterator':
                $compilationContext->headersManager->add('ext/spl/spl_directory');
                $classEntry = 'spl_ce_DirectoryIterator';
                break;

            case 'filesystemiterator':
                $compilationContext->headersManager->add('ext/spl/spl_directory');
                $classEntry = 'spl_ce_FilesystemIterator';
                break;

            case 'recursivedirectoryiterator':
                $compilationContext->headersManager->add('ext/spl/spl_directory');
                $classEntry = 'spl_ce_RecursiveDirectoryIterator';
                break;

            case 'globiterator':
                $compilationContext->headersManager->add('ext/spl/spl_directory');
                $classEntry = 'spl_ce_GlobIterator';
                break;

            case 'splfileobject':
                $compilationContext->headersManager->add('ext/spl/spl_directory');
                $classEntry = 'spl_ce_SplFileObject';
                break;

            case 'spltempfileobject':
                $compilationContext->headersManager->add('ext/spl/spl_directory');
                $classEntry = 'spl_ce_SplTempFileObject';
                break;

            case 'countable':
                $compilationContext->headersManager->add('ext/spl/spl_iterators');
                $classEntry = 'spl_ce_Countable';
                break;

            case 'callbackfilteriterator':
                $compilationContext->headersManager->add('ext/spl/spl_iterators');
                $classEntry = 'spl_ce_CallbackFilterIterator';
                break;

            case 'recursivecallbackfilteriterator':
                $compilationContext->headersManager->add('ext/spl/spl_iterators');
                $classEntry = 'spl_ce_RecursiveCallbackFilterIterator';
                break;

            case 'arrayobject':
                $compilationContext->headersManager->add('ext/spl/spl_array');
                $classEntry = 'spl_ce_ArrayObject';
                break;

            case 'splfixedarray':
                $compilationContext->headersManager->add('ext/spl/spl_fixedarray');
                $classEntry = 'spl_ce_SplFixedArray';
                break;

            case 'splpriorityqueue':
                $compilationContext->headersManager->add('ext/spl/spl_heap');
                $classEntry = 'spl_ce_SplPriorityQueue';
                break;

            case 'splfileinfo':
                $compilationContext->headersManager->add('ext/spl/spl_directory');
                $classEntry = 'spl_ce_SplFileInfo';
                break;

            case 'splheap':
                $compilationContext->headersManager->add('ext/spl/spl_heap');
                $classEntry = 'spl_ce_SplHeap';
                break;

            case 'splminheap':
                $compilationContext->headersManager->add('ext/spl/spl_heap');
                $classEntry = 'spl_ce_SplMinHeap';
                break;

            case 'splmaxheap':
                $compilationContext->headersManager->add('ext/spl/spl_heap');
                $classEntry = 'spl_ce_SplMaxHeap';
                break;

            case 'splstack':
                $compilationContext->headersManager->add('ext/spl/spl_dllist');
                $classEntry = 'spl_ce_SplStack';
                break;

            case 'splqueue':
                $compilationContext->headersManager->add('ext/spl/spl_dllist');
                $classEntry = 'spl_ce_SplQueue';
                break;

            case 'spldoublylinkedlist':
                $compilationContext->headersManager->add('ext/spl/spl_dllist');
                $classEntry = 'spl_ce_SplDoublyLinkedList';
                break;

            case 'stdclass':
                $classEntry = 'zend_standard_class_def';
                break;

            case 'closure':
                $compilationContext->headersManager->add('Zend/zend_closures');
                $classEntry = 'zend_ce_closure';
                break;

            case 'pdo':
                $compilationContext->headersManager->add('ext/pdo/php_pdo_driver');
                $classEntry = 'php_pdo_get_dbh_ce()';
                break;

            case 'pdostatement':
                $compilationContext->headersManager->add('kernel/main');
                $classEntry = 'zephir_get_internal_ce(SS("pdostatement") TSRMLS_CC)';
                break;

            case 'pdoexception':
                $compilationContext->headersManager->add('ext/pdo/php_pdo_driver');
                $classEntry = 'php_pdo_get_exception()';
                break;

            case 'datetime':
                $compilationContext->headersManager->add('ext/date/php_date');
                $classEntry = 'php_date_get_date_ce()';
                break;

            case 'datetimezone':
                $compilationContext->headersManager->add('ext/date/php_date');
                $classEntry = 'php_date_get_timezone_ce()';
                break;

            // Reflection
            /*case 'reflector':
                $compilationContext->headersManager->add('ext/reflection/php_reflection');
                $classEntry = 'reflector_ptr';
                break;
            case 'reflectionexception':
                $compilationContext->headersManager->add('ext/reflection/php_reflection');
                $classEntry = 'reflection_exception_ptr';
                break;
            case 'reflection':
                $compilationContext->headersManager->add('ext/reflection/php_reflection');
                $classEntry = 'reflection_ptr';
                break;
            case 'reflectionfunctionabstract':
                $compilationContext->headersManager->add('ext/reflection/php_reflection');
                $classEntry = 'reflection_function_abstract_ptr';
                break;
            case 'reflectionfunction':
                $compilationContext->headersManager->add('ext/reflection/php_reflection');
                $classEntry = 'reflection_function_ptr';
                break;
            case 'reflectionparameter':
                $compilationContext->headersManager->add('ext/reflection/php_reflection');
                $classEntry = 'reflection_parameter_ptr';
                break;
            case 'reflectionclass':
                $compilationContext->headersManager->add('ext/reflection/php_reflection');
                $classEntry = 'reflection_class_ptr';
                break;
            case 'reflectionobject':
                $compilationContext->headersManager->add('ext/reflection/php_reflection');
                $classEntry = 'reflection_object_ptr';
                break;
            case 'reflectionmethod':
                $compilationContext->headersManager->add('ext/reflection/php_reflection');
                $classEntry = 'reflection_method_ptr';
                break;
            case 'reflectionproperty':
                $compilationContext->headersManager->add('ext/reflection/php_reflection');
                $classEntry = 'reflection_property_ptr';
                break;
            case 'reflectionextension':
                $compilationContext->headersManager->add('ext/reflection/php_reflection');
                $classEntry = 'reflection_extension_ptr';
                break;
            case 'reflectionzendextension':
                $compilationContext->headersManager->add('ext/reflection/php_reflection');
                $classEntry = 'reflection_zend_extension_ptr';
                break;*/

            default:
                if (!$check) {
                    throw new CompilerException('Unknown class entry for "' . $className . '"');
                } else {
                    $classEntry = 'zephir_get_internal_ce(SS("' . Utils::escapeClassName(strtolower($className)) . '") TSRMLS_CC)';
                }
        }

        return $classEntry;
    }

    /**
     * Builds a class definition from reflection
     *
     * @param \ReflectionClass $class
     */
    public static function buildFromReflection(\ReflectionClass $class)
    {
        $classDefinition = new ClassDefinition($class->getNamespaceName(), $class->getName());

        $methods = $class->getMethods();
        if (count($methods) > 0) {
            foreach ($methods as $method) {
                $parameters = array();

                foreach ($method->getParameters() as $row) {
                    $params = array(
                        'type' => 'parameter',
                        'name' => $row->getName(),
                        'const' => 0,
                        'data-type' => 'variable',
                        'mandatory' => !$row->isOptional()
                    );
                    if (!$params['mandatory']) {
                        try {
                            $params['default'] = $row->getDefaultValue();
                        } catch (\ReflectionException $e) {
                            // TODO: dummy default value
                            $params['default'] = true;
                        }
                    };
                    $parameters[] = $params;
                }

                $classMethod = new ClassMethod($classDefinition, array(), $method->getName(), new ClassMethodParameters(
                    $parameters
                ));
                $classMethod->setIsStatic($method->isStatic());
                $classMethod->setIsBundled(true);
                $classDefinition->addMethod($classMethod);
            }
        }

        $constants = $class->getConstants();
        if (count($constants) > 0) {
            foreach ($constants as $constantName => $constantValue) {
                $type = self::_convertPhpConstantType(gettype($constantValue));
                $classConstant = new ClassConstant($constantName, array('value' => $constantValue, 'type' => $type), null);
                $classDefinition->addConstant($classConstant);
            }
        }

        $properties = $class->getProperties();
        if (count($properties) > 0) {
            foreach ($properties as $property) {
                $visibility = array();

                if ($property->isPublic()) {
                    $visibility[] = 'public';
                }

                if ($property->isPrivate()) {
                    $visibility[] = 'private';
                }

                if ($property->isProtected()) {
                    $visibility[] = 'protected';
                }

                if ($property->isStatic()) {
                    $visibility[] = 'static';
                }

                $classProperty = new ClassProperty(
                    $classDefinition,
                    $visibility,
                    $property->getName(),
                    null,
                    null,
                    null
                );
                $classDefinition->addProperty($classProperty);
            }
        }

        $classDefinition->setIsBundled(true);

        return $classDefinition;
    }

    private static function _convertPhpConstantType($phpType)
    {
        $map = array(
            'boolean' => 'bool',
            'integer' => 'int',
            'double' => 'double',
            'string' => 'string',
            'NULL' => 'null',
        );

        if (!isset($map[$phpType])) {
            throw new CompilerException("Cannot parse constant type '$phpType'");
        }

        return $map[$phpType];
    }
}
