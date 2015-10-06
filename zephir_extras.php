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
 * Zephir Included User Functions
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

/**
 * 
 * @param string $haystack
 * @param string $needle
 * @param boolean $case_sensitive
 * @return boolean
 */
function starts_with($haystack, $needle, $case_sensitive = false) {
  if (!isset($haystack) && is_string($haystack)) {
    return false;
  }
  if (!isset($needle) && is_string($needle)) {
    return false;
  }

  $l_haystack = strlen($haystack);
  $l_needle = strlen($needle);
  if ($l_haystack < $l_needle) {
    return false;
  } else if ($l_haystack === $l_needle) {
    return $case_sensitive ? $l_haystack === $l_needle : strcasecmp($haystack, $needle, $l_needle) === 0;
  } else {
    return $case_sensitive ? substr($haystack, 0, $l_needle) === $needle : strncasecmp($haystack, $needle, $l_needle) === 0;
  }
}

/**
 * 
 * @param string $haystack
 * @param string $needle
 * @return boolean
 */
function memstr($haystack, $needle) {
  if (!isset($haystack) && is_string($haystack)) {
    return false;
  }
  if (!isset($needle) && is_string($needle)) {
    return false;
  }

  return !(strpos($haystack, $needle) === FALSE);
}

/**
 * 
 * @param string $class
 * @return \class
 * @throws \Exception
 */
function create_instance($class) {
  // Is 'class' a valid string?
  if (!isset($class) || !is_string($class)) { // YES
    throw new \Exception("Invalid class name");
  }

  // Does class exist?
  if (!class_exists($class)) { // YES
    throw new \Exception("Class [{$class}] does not exist");
  }

  // Create Class Instance
  return new $class;
}

/**
 * 
 * @param string $class
 * @param array $parameters
 * @return type
 * @throws \Exception
 */
function create_instance_params($class, $parameters) {
  // Is 'class' a valid string?
  if (!isset($class) || !is_string($class)) { // YES
    throw new \Exception("Invalid class name");
  }

  // Is 'parameters' a valid array?
  if (!isset($parameters) || !is_array($parameters)) { // YES
    throw new \Exception("Instantiation parameters must be an array");
  }

  // Does class exist?
  if (!class_exists($class)) { // YES
    throw new \Exception("Class [{$class}] does not exist");
  }

  // Build Constructor Parameters
  $re_args = [];
  $refMethod = new ReflectionMethod($class, '__construct');
  foreach ($refMethod->getParameters() as $key => $param) {
    if ($param->isPassedByReference()) {
      $re_args[$key] = &$parameters[$key];
    } else {
      $re_args[$key] = $parameters[$key];
    }
  }

  // Create Class Instance
  $refClass = new ReflectionClass('class_name_here');
  return $refClass->newInstanceArgs((array) $re_args);
}
