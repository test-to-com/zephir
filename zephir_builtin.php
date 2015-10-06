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
 * Zephir Compiler Functions
 */

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

/**
 * 
 * @param mixed $var
 * @return boolean
 */
function zephir_isempty($var) {
  if (isset($var) && ($var !== null)) {
    if (is_bool($var)) {
      return $var === FALSE;
    } else if (is_string($var)) {
      return strlen($var) > 0;
    }

    // equivalent !zend_is_true($var)
    return ((bool) $var) === FALSE;
  }

  return true;
}