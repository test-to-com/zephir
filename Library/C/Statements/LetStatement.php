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

namespace Zephir\C\Statements;

use Zephir\CompilationContext;
use Zephir\CompilerException;
use Zephir\Detectors\ReadDetector;
use Zephir\C\Expression;
use Zephir\C\Statements\Let\Variable as LetVariable;
use Zephir\C\Statements\Let\VariableAppend as LetVariableAppend;
use Zephir\C\Statements\Let\ArrayIndex as LetArrayIndex;
use Zephir\C\Statements\Let\ArrayIndexAppend as LetArrayIndexAppend;
use Zephir\C\Statements\Let\ObjectProperty as LetObjectProperty;
use Zephir\C\Statements\Let\ObjectDynamicProperty as LetObjectDynamicProperty;
use Zephir\C\Statements\Let\ObjectDynamicStringProperty as LetObjectDynamicStringProperty;
use Zephir\C\Statements\Let\ObjectPropertyAppend as LetObjectPropertyAppend;
use Zephir\C\Statements\Let\ObjectPropertyArrayIndex as LetObjectPropertyArrayIndex;
use Zephir\C\Statements\Let\ObjectPropertyArrayIndexAppend as LetObjectPropertyArrayIndexAppend;
use Zephir\C\Statements\Let\ObjectPropertyIncr as LetObjectPropertyIncr;
use Zephir\C\Statements\Let\ObjectPropertyDecr as LetObjectPropertyDecr;
use Zephir\C\Statements\Let\StaticProperty as LetStaticProperty;
use Zephir\C\Statements\Let\StaticPropertyAppend as LetStaticPropertyAppend;
use Zephir\C\Statements\Let\StaticPropertyArrayIndex as LetStaticPropertyArrayIndex;
use Zephir\C\Statements\Let\StaticPropertyArrayIndexAppend as LetStaticPropertyArrayIndexAppend;
use Zephir\C\Statements\Let\Decr as LetDecr;
use Zephir\C\Statements\Let\Incr as LetIncr;
use Zephir\C\Statements\Let\ExportSymbol as LetExportSymbol;
use Zephir\C\Statements\Let\ExportSymbolString as LetExportSymbolString;
use Zephir\Statements\StatementAbstract;

/**
 * LetStatement
 *
 * Let statement is used to assign variables
 */
class LetStatement extends StatementAbstract
{
    /**
     * @param CompilationContext $compilationContext
     * @throws CompilerException
     */
    public function compile(CompilationContext $compilationContext)
    {
        $readDetector = new ReadDetector();

        $statement = $this->_statement;
        foreach ($statement['assignments'] as $assignment) {
            $variable = $assignment['variable'];

            /**
             * Get the symbol from the symbol table if necessary
             */
            switch ($assignment['assign-type']) {
                case 'static-property':
                case 'static-property-append':
                case 'static-property-array-index':
                case 'static-property-array-index-append':
                case 'dynamic-variable-string':
                    $symbolVariable = null;
                    break;

                case 'array-index':
                case 'variable-append':
                case 'object-property':
                case 'array-index-append':
                case 'string-dynamic-object-property':
                case 'variable-dynamic-object-property':
                    $symbolVariable = $compilationContext->symbolTable->getVariableForUpdate($variable, $compilationContext, $assignment);
                    break;

                default:
                    $symbolVariable = $compilationContext->symbolTable->getVariableForWrite($variable, $compilationContext, $assignment);
                    break;
            }

            /**
             * Incr/Decr assignments don't require an expression
             */
            if (isset($assignment['expr'])) {
                $expr = new Expression($assignment['expr']);

                switch ($assignment['assign-type']) {
                    case 'variable':
                        if (!$readDetector->detect($variable, $assignment['expr'])) {
                            if (isset($assignment['operator'])) {
                                if ($assignment['operator'] == 'assign') {
                                    $expr->setExpectReturn(true, $symbolVariable);
                                }
                            } else {
                                $expr->setExpectReturn(true, $symbolVariable);
                            }
                        } else {
                            if (isset($assignment['operator'])) {
                                if ($assignment['operator'] == 'assign') {
                                    $expr->setExpectReturn(true);
                                }
                            } else {
                                $expr->setExpectReturn(true);
                            }
                        }
                        break;
                }

                switch ($assignment['expr']['type']) {
                    case 'property-access':
                    case 'array-access':
                    case 'type-hint':
                        $expr->setReadOnly(true);
                        break;
                }

                $resolvedExpr = $expr->compile($compilationContext);

                /**
                 * Bad implemented operators could return values different than objects
                 */
                if (!is_object($resolvedExpr)) {
                    throw new CompilerException("Resolved expression is not valid", $assignment['expr']);
                }
            }

            /**
             * There are four types of assignments
             */
            switch ($assignment['assign-type']) {
                case 'variable':
                    $let = new LetVariable();
                    $let->assign($variable, $symbolVariable, $resolvedExpr, $readDetector, $compilationContext, $assignment);
                    break;

                case 'variable-append':
                    $let = new LetVariableAppend();
                    $let->assign($variable, $symbolVariable, $resolvedExpr, $compilationContext, $assignment);
                    break;

                case 'object-property':
                    $let = new LetObjectProperty();
                    $let->assign($variable, $symbolVariable, $resolvedExpr, $compilationContext, $assignment);
                    break;

                case 'variable-dynamic-object-property':
                    $let = new LetObjectDynamicProperty();
                    $let->assign($variable, $symbolVariable, $resolvedExpr, $compilationContext, $assignment);
                    break;

                case 'string-dynamic-object-property':
                    $let = new LetObjectDynamicStringProperty();
                    $let->assign($variable, $symbolVariable, $resolvedExpr, $compilationContext, $assignment);
                    break;

                case 'static-property':
                    $let = new LetStaticProperty();
                    $let->assignStatic($variable, $assignment['property'], $resolvedExpr, $compilationContext, $assignment);
                    break;

                case 'static-property-append':
                    $let = new LetStaticPropertyAppend();
                    $let->assignStatic($variable, $assignment['property'], $resolvedExpr, $compilationContext, $assignment);
                    break;

                case 'static-property-array-index':
                    $let = new LetStaticPropertyArrayIndex();
                    $let->assignStatic($variable, $assignment['property'], $resolvedExpr, $compilationContext, $assignment);
                    break;

                case 'static-property-array-index-append':
                    $let = new LetStaticPropertyArrayIndexAppend();
                    $let->assignStatic($variable, $assignment['property'], $resolvedExpr, $compilationContext, $assignment);
                    break;

                case 'array-index':
                    $let = new LetArrayIndex();
                    $let->assign($variable, $symbolVariable, $resolvedExpr, $compilationContext, $assignment);
                    break;

                case 'array-index-append':
                    $let = new LetArrayIndexAppend();
                    $let->assign($variable, $symbolVariable, $resolvedExpr, $compilationContext, $assignment);
                    break;

                case 'object-property-append':
                    $let = new LetObjectPropertyAppend();
                    $let->assign($variable, $symbolVariable, $resolvedExpr, $compilationContext, $assignment);
                    break;

                case 'object-property-array-index':
                    $let = new LetObjectPropertyArrayIndex();
                    $let->assign($variable, $symbolVariable, $resolvedExpr, $compilationContext, $assignment);
                    break;

                case 'object-property-array-index-append':
                    $let = new LetObjectPropertyArrayIndexAppend();
                    $let->assign($variable, $symbolVariable, $resolvedExpr, $compilationContext, $assignment);
                    break;

                case 'incr':
                    $let = new LetIncr();
                    $let->assign($variable, $symbolVariable, $compilationContext, $assignment);
                    break;

                case 'decr':
                    $let = new LetDecr();
                    $let->assign($variable, $symbolVariable, $compilationContext, $assignment);
                    break;

                case 'object-property-incr':
                    $let = new LetObjectPropertyIncr();
                    $let->assign($variable, $assignment['property'], $symbolVariable, $compilationContext, $assignment);
                    break;

                case 'object-property-decr':
                    $let = new LetObjectPropertyDecr();
                    $let->assign($variable, $assignment['property'], $symbolVariable, $compilationContext, $assignment);
                    break;

                case 'dynamic-variable':
                    $let = new LetExportSymbol();
                    $let->assign($variable, $symbolVariable, $resolvedExpr, $compilationContext, $assignment);
                    break;

                case 'dynamic-variable-string':
                    $let = new LetExportSymbolString();
                    $let->assign($variable, $symbolVariable, $resolvedExpr, $compilationContext, $assignment);
                    break;

                default:
                    throw new CompilerException("Unknown assignment: " . $assignment['assign-type'], $assignment);
            }
        }
    }
}
