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

namespace Zephir\C\Optimizers\FunctionCall;

use Zephir\C\Optimizers\IsTypeOptimizerAbstract;

/**
 * IsObjectOptimizer
 *
 * Optimizes calls to 'is_array' using internal function
 */
class IsObjectOptimizer extends IsTypeOptimizerAbstract
{
    protected function getType()
    {
        return 'IS_OBJECT';
    }
}