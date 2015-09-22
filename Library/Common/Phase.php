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

namespace Zephir\Common;

/**
 * Compiler Stage Definition
 * 
 * @author Paulo Ferreira <pf at sourcenotes.org>
 */
interface Phase extends InjectionAware {
  /**
   * Process the AST
   * 
   * @param array $ast AST to be processed
   * @return array Old or Transformed AST
   */
  public function top($ast);

  /**
   * Process Class or Interface Property
   * 
   * @param array $class Class Definition
   * @param array $property Class Property Definition
   * @return array New Property Definition, 'NULL' if to be removed
   */
  public function property(&$class, $property);

  /**
   * Process Class or Interface Method
   * 
   * @param array $class Class Definition
   * @param array $method Class Method Definition
   * @return array New Property Definition, 'NULL' if to be removed
   */
  public function method(&$class, $method);
}
