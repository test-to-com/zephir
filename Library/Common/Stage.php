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
interface Stage extends InjectionAware {

  /**
   * Initialize the Stage Instance
   * 
   * @return self Return instance of stage for Function Linking.
   */
  public function initialize();

  /**
   * Reset the Stage Instance (set the default state, if a stage is to
   * be re-used)
   * 
   * @return self Return instance of stage for Function Linking.
   */
  public function reset();
  
  /**
   * Compile or Transform the AST.
   * 
   * @param array $ast AST to be compiled/transformed
   * @return array Old or Transformed AST
   */
  public function compile($ast);
}
