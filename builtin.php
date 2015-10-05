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

function zephir_read_property($object, $property) {
  /* TODO Improve Handling 
   * i.e. if $object is not an object -  "Trying to get property \"%s\" of non-object"
   * etc.
   * look at generated code for zephir_read_property
   */
  if (zephir_isset_property($object, $property)) {
    return $object->$property;
  }
}

function zephir_isset_array($array, $index) {
  if (isset($array) && isset($index)) {
    switch (gettype($index)) {
      case 'double':
        $index = (integer) $index;
      case 'boolean':
      case 'integer':
      case 'resource':
        return isset($a[$index]);
      case 'NULL':
        $index = '';
      case 'string':
        return array_key_exists($index, $array);
      default:
        throw new \Exception('Illegal offset type');
    }
  }
  return false;
}

function zephir_isset_property($object, $property) {
  if (isset($object) && isset($property)) {
    if (is_object($object) && is_string($property)) {
      return property_exists($object, $property);
    }
  }
  return false;
}

function starts_with($str, $compared, $case_sensitive = false) {
  return zephir_starts_with($str, $compared, $case_sensitive);
}

function zephir_starts_with($str, $compared, $case_sensitive = false) {
  if (!isset($str) || !isset($compared)) {
    return false;
  }

  if (!is_string($str) || !isset($compared)) {
    return false;
  }

  $l_str = strlen($str);
  $l_compared = strlen($compared);
  if ($l_str < $l_compared) {
    return false;
  } else if ($l_str === $l_compared) {
    return $case_sensitive ? $l_str === $l_compared : strcasecmp($str, $compared, $l_compared) === 0;
  } else {
    return $case_sensitive ? substr($str, 0, $l_compared) === $compared : strncasecmp($str, $compared, $l_compared) === 0;
  }
}
