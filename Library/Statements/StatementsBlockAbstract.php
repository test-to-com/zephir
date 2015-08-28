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

namespace Zephir\Statements;

use Zephir\Exception;
use Zephir\CompilationContext;
use Zephir\Passes\MutateGathererPass;

/**
 * StatementsBlock
 *
 * This represents a single basic block in Zephir. A statements block is simply a container of instructions that execute sequentially.
 */
abstract class StatementsBlockAbstract {

  protected $statements;
  protected $unreachable;
  protected $debug = false;
  protected $loop = false;
  protected $mutateGatherer;
  protected $lastStatement;

  /**
   * StatementsBlock constructor
   *
   * @param array $statements
   */
  public function __construct(array $statements) {
    $this->statements = $statements;
  }

  /**
   * Sets whether the statements blocks belongs to a loop
   *
   * @param boolean $loop
   * @return StatementsBlock
   */
  public function isLoop($loop) {
    $this->loop = $loop;
    return $this;
  }

  /**
   * @param CompilationContext $compilationContext
   * @param boolean $unreachable
   * @param int $branchType
   * @return Branch
   */
  abstract public function compile(CompilationContext $compilationContext, $unreachable = false, $branchType = Branch::TYPE_UNKNOWN);

  /**
   * Returns the statements in the block
   *
   * @return array
   */
  public function getStatements() {
    return $this->statements;
  }

  /**
   * Setter for statements
   *
   * @param array $statements
   */
  public function setStatements(array $statements) {
    $this->statements = $statements;
  }

  /**
   * Returns the type of the last statement executed
   *
   * @return string
   */
  public function getLastStatementType() {
    return $this->lastStatement['type'];
  }

  /**
   * Returns the last statement executed
   *
   * @return array
   */
  public function getLastStatement() {
    return $this->lastStatement;
  }

  /**
   * Returns the last line in the last statement
   */
  public function getLastLine() {
    if (!$this->lastStatement) {
      $this->lastStatement = $this->statements[count($this->statements) - 1];
    }
  }

  /**
   * Create/Returns a mutate gatherer pass for this block
   *
   * @param boolean $pass
   * @return MutateGathererPass
   */
  public function getMutateGatherer($pass = false) {
    if (!$this->mutateGatherer) {
      $this->mutateGatherer = new MutateGathererPass;
    }
    if ($pass) {
      $this->mutateGatherer->pass($this);
    }
    return $this->mutateGatherer;
  }

}
