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
 * DI capable object
 * 
 * Based Directly on Phalcon\Di\InjectionAwareInterface;
 * 
 * @author Paulo Ferreira <pf at sourcenotes.org>
 */
interface InjectionAware {

  /**
   * Sets the dependency injector
   * 
   * @param \Zephir\Common\DI $dependencyInjector New Dependency Object
   */
  public function setDI(DI $dependencyInjector);

  /**
   * Returns the internal dependency injector
   * 
   * @return \Zephir\Common\DI Current Dependency Injection Object used
   */
  public function getDI();
}
