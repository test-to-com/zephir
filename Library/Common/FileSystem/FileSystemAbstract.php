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

namespace Zephir\Common\FileSystem;

use Zephir\Common\FileSystem as IFileSystem;

/**
 * HardDisk
 *
 * Uses the standard hard-disk as filesystem for temporary operations
 */
abstract class FileSystemAbstract implements IFileSystem {

  protected $initialized = false;

  /**
   * Checks if the filesystem is initialized
   *
   * @return boolean 'true' if initialized, 'false' otherwise
   */
  public function isInitialized() {
    return $this->initialized;
  }

  /**
   * Initialize the filesystem
   * 
   * @return boolean 'true' if initialized, 'false' otherwise
   */
  public function initialize() {
    if (!$this->isInitialized()) {
      $this->initialized = $this->_initialize();
    }

    return $this->initialized;
  }

  /**
   * Perform the Actual FileSystem Initialization
   * 
   * @return boolean 'true' if initialized, 'false' otherwise
   */
  abstract protected function _initialize();
}
