<?php

// BASE Path for Zephir
define('VERSION', '0.7.1b');

// Initialize Autoloader
require __DIR__ . '/bootstrap.php';

use GetOptionKit\OptionCollection;
use GetOptionKit\OptionParser;
use GetOptionKit\OptionPrinter\ConsoleOptionPrinter;
use Zephir\PHP\DI;

$di = new DI;

// Set the File System
$di->set("fileSystem", "\Zephir\Common\FileSystem\HardDisk", true);
$di->setShared('emitter', "\Zephir\PHP\Emitters\File");
//$di->setShared('emitter', "\Zephir\PHP\Emitters\Console");
/*
 * commands
 * api - generate documentation
 * stubs - generate stubs
 * build -
 * 
 * 
 * 
 * 
 */

// Define Command Line Options
$specs = new OptionCollection;
// Output Directory (STRING Optional - DEFAULT output goes to ./output)
$specs->add('o|output?', 'Output directory.')
  ->isa('String');

// Cache Directory (STRING Optional - DEFAULT cache goes to ./cache)
$specs->add('c|cache?', 'Cache directory.')
  ->isa('String');

// Temporary Directory (STRING Optional - DEFAULT output goes to system temporary directory\zephir)
$specs->add('t|tmp?', 'Temporary directory.')
  ->isa('String');


// Verbose Output (FLAG Option)
$specs->add('v', 'verbose');

// Output Command Line Options
echo "Command Line Options:\n";
$printer = new ConsoleOptionPrinter;
echo $printer->render($specs);

// Parse Command Line
$parser = new OptionParser($specs);
try {
  $result = $parser->parse($argv);
} catch (Exception $e) {
  echo $e->getMessage();
  return 1;
}

// Display Command Line Options Used
echo "Enabled options:\n";
foreach ($result as $key => $spec) {
  echo $spec . "\n";
}

// Display Extra Arguments
echo "Extra Arguments:\n";
$arguments = $result->getArguments();
for ($i = 1; $i < count($arguments); $i++) {
  echo $arguments[$i] . "\n";
}

if (count($arguments) < 2) {
  echo "Missing Source [File or Directory]";
  return 2;
}

$fs = $di['fileSystem'];

$cwd = getcwd();

$output_dir = $result->output;
$fs->setOutputPath(isset($output_dir) && is_dir($output_dir) ? $output_dir : './output');

$cache_dir = $result->cache;
$fs->setCachePath(isset($cache_dir) && is_dir($cache_dir) ? $cache_dir : './cache');

$tmp_dir = $result->tmp;
if (isset($tmp_dir) && is_dir($tmp_dir)) {
  $fs->setOutputPath($output_dir);
}

$input_dir = null;
$input_file = null;
$input = $arguments[1];
if (file_exists($input)) {
  if (is_file($input)) {
    echo "Source is File [{$input}]\n";
    if (!is_readable($input)) {
      echo "Source [{$input}] is not Readable by the Current User";
      return 3;
    }
    $input_file = basename($input);
    $input_dir = dirname($input);
    $fs->setInputPath($input_dir);
  } else {
    echo "Source is Dir [{$input}]\n";
    $input_dir = $input;
    $fs->setInputPath($input);
  }
} else {
  echo "Invalid Source [{$input}]\n";
  return 2;
}

$fs->initialize();

echo "Current Working Directory [{$cwd}]\n";
echo "Input Directory [{$input_dir}]\n";
if (isset($input_file)) {
  echo "Input File [{$input_file}]\n";
}
echo "Output Directory [{$output_dir}]\n";
echo "Temporary Directory [{$tmp_dir}]\n";

$di->set("compiler", "\Zephir\PHP\Compiler", true);
$di->set("compiler-stages", function() {
  return [
    "\Zephir\PHP\Stages\Compact"
    , "\Zephir\PHP\Stages\Process"
    , "\Zephir\PHP\Stages\EmitCode"
  ];
}, true);

// Initialize the Compiler
$compiler = $di['compiler'];
$compiler->initialize();

// Are we parsing a Single File?
if (isset($input_file)) { // YES
//  var_dump($compiler->file($input_file));
  $compiler->file($input_file);
} else { // NO: Parsing Entire Directory
  $compiler->files($input_dir);
}
