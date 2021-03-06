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
  | Authors: Paulo Ferreira <pf@sourcenotes.org>                             |
  +--------------------------------------------------------------------------+
 */

/*
 * PHP EXTENSION Globals Emulation
 * 
 * See:
 * http://php.net/manual/en/internals2.structure.globals.php
 * 
 * See:
 * - exp\php_{extension name}.h
 * - ext\{extension name}.c
 * 
 * To see GLOBALS defined and Initial Values
 */

$_ZEPHIR_GLOBALS = [
  'initialized' => false
];

function _globals_init($settings) {
  global $_ZEPHIR_GLOBALS;

  if (!isset($settings) || !is_array($settings)) { // NO
    throw new \Exception("An array parameter is required for '_globals_init'");
  }

  $_ZEPHIR_GLOBALS = array_merge($_ZEPHIR_GLOBALS, $settings);
  $_ZEPHIR_GLOBALS['initialized'] = true;
}

function _globals_ready() {
  global $_ZEPHIR_GLOBALS;

  if (!isset($_ZEPHIR_GLOBALS)) {
    throw new \Exception("Extension Globals not Defined");
  }

  if (!isset($_ZEPHIR_GLOBALS['initialized']) || !$_ZEPHIR_GLOBALS['initialized']) {
    throw new \Exception("Extension Globals not Initialized");
  }
}

function _globals_is($globalName) {
  // Do we have valid incoming parameters?
  if (!isset($globalName) || !is_string($globalName)) { // NO
    throw new \Exception("A string parameter is required for 'globals_get'");
  }

  // Make sure that Extension Globals are Ready
  _globals_ready();

  return array_key_exists($globalName, $_ZEPHIR_GLOBALS);
}

function globals_get($globalName) {
  global $_ZEPHIR_GLOBALS;

  // Does the Global Name Exist?
  _globals_is($globalName);

  // Return it's value
  return $_ZEPHIR_GLOBALS[$globalName];
}

function globals_set($globalName, $value) {
  global $_ZEPHIR_GLOBALS;

  // Does the Global Name Exist?
  _globals_ready();

  // Set it's Value
  $_ZEPHIR_GLOBALS[$globalName] = $value;
}
