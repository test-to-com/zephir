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

namespace Zephir\API;

/**
 * Code Emitter
 * 
 * @author Paulo Ferreira <pf at sourcenotes.org>
 */
interface Emitter extends \Zephir\API\DI\InjectionAware {

  /**
   * Emit Code for a Single File
   * 
   * @param type $path
   * @throws \Exception
   */
  public function file($filepath);

  /**
   * Emit Code for a Single File
   * 
   * @param mixed $path
   * @throws \Exception
   */
  public function files($files);
  
  /**
   * Emit Code for all Files in a Project
   * 
   * @param type $path
   * @throws \Exception
   */
  public function project($path);
}
