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

/*
 * Zephir Builtin Functions
 */

/**
 * 
 * @param string $version
 * @return boolean
 */
function is_php_version($version) {
  $version = isset($version) && is_string($version) ? trim($version) : null;
  if (isset($version) && count($version)) {
    return substr_compare(phpversion(), $version, 0, count($version)) === 0;
  }
  return false;
}
