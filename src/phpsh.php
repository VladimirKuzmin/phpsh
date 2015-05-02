#!/usr/bin/env php
<?php
// Copyright 2004-2007 Facebook. All Rights Reserved.
// this is used by phpsh.py to exec php commands and maintain state
// @author  ccheever
// @author  dcorson
// @author  warman (added multiline input support \ing)
// @date    Thu Jun 15 22:27:46 PDT 2006
//
// usage: this is only called from phpsh (the python end), as:
// phpsh.php <comm-file> <codebase-mode> [-c]
//
// use '' for default codebase-mode, define others in /etc/phpsh/rc.php
// -c turns off color

namespace __phpsh__;

// This function is here just so that the debug proxy can set a
// breakpoint on something that executes right after the function being
// debugged has been evaluated. Hitting this breakpoint makes debug
// proxy remove the breakpoint it previously set on the function under
// debugging.
function ___phpsh___eval_completed() {
}

/**
 * An instance of a phpsh interactive loop
 *
 * @author     ccheever
 * @author     dcorson
 *
 * This class mostly exists as a proxy for a namespace
 */
class ___Phpsh___ {
  var $_handle = STDIN;
  var $_comm_handle;
  var $_MAX_LINE_SIZE = 262144;

    /**
     * Constructor - actually runs the interactive loop so that all we have to do
     * is construct it to run
     * @param string $output_from_includes
     * @param $do_color
     * @param $do_autocomplete
     * @param $do_undefined_function_check
     * @param $fork_every_command
     * @param $comm_filename
     * @internal param \__phpsh__\list $extra_include Extra files that we want to include
     *
     * @author   ccheever
     * @author   dcorson
     */
  public function __construct(
      $output_from_includes='', $do_color, $do_autocomplete,
      $do_undefined_function_check, $fork_every_command, $comm_filename
  ) {
    $this->_comm_handle = fopen($comm_filename, 'w');
    $this->__send_autocomplete_identifiers($do_autocomplete);
    $this->do_color = $do_color;
    $this->do_undefined_function_check = $do_undefined_function_check;
    if (!PCNTL_EXISTS && $fork_every_command) {
      $fork_every_command = false;
      fwrite(STDERR,
             "Install pcntl to enable forking on every command.\n");
    }
    $this->fork_every_command = $fork_every_command;

    // now it's safe to send any output the includes generated
    echo $output_from_includes;
    fwrite($this->_comm_handle, "ready\n");
  }

  /**
   * Destructor - just closes the handle to STDIN
   *
   * @author    ccheever
   */
  function __destruct() {
    fclose($this->_handle);
  }

  /**
   * Sends the list of identifiers that phpsh should know to tab-complete to
   * python
   *
   * @author    ccheever
   */
  function __send_autocomplete_identifiers($do_autocomplete) {
    // send special string to signal that we're sending the autocomplete
    // identifiers
    echo "#start_autocomplete_identifiers\n";

    if ($do_autocomplete) {
      // send function names -- both user defined and built-in
      // globals, constants, classes, interfaces
      $defined_functions = get_defined_functions();
      $methods = array();
      foreach (($classes = get_declared_classes()) as $class) {
        foreach (get_class_methods($class) as $class_method) {
          $methods[] = $class_method;
        }
      }
      foreach (array_merge($defined_functions['user'],
                           $defined_functions['internal'],
                           array_keys($GLOBALS),
                           array_keys(get_defined_constants()),
                           $classes,
                           get_declared_interfaces(),
                           $methods,
                           array('instanceof')) as $identifier) {
        // exclude the phpsh internal variables from the autocomplete list
        if (strtolower(substr($identifier, 0, 11)) != '___phpsh___') {
          echo "$identifier\n";
        } else {
          unset($$identifier);
        }
      }
    }

    // string signalling the end of autocmplete identifiers
    echo "#end_autocomplete_identifiers\n";
  }

  /**
   * @param   string  $buffer  phpsh input to check function calls in
   * @return  string  name of first undefined function,
   *                  or '' if all functions exist
   */
  function undefined_function_check($buffer) {
    $toks = token_get_all('<?php '.$buffer);
    $cur_func = null;
    $ignore_next_func = false;
    foreach ($toks as $tok) {
      if (is_string($tok)) {
        if ($tok === '(') {
          if ($cur_func !== null) {
            if (!function_exists($cur_func)) {
              return $cur_func;
            }
          }
        }
        $cur_func = null;
      } elseif (is_array($tok)) {
        list($tok_type, $tok_val, $tok_line) = $tok;
        if ($tok_type === T_STRING) {
          if ($ignore_next_func) {
            $cur_func = null;
            $ignore_next_func = false;
          } else {
            $cur_func = $tok_val;
          }
        } else if (
            $tok_type === T_FUNCTION ||
            $tok_type === T_NEW ||
            $tok_type === T_OBJECT_OPERATOR ||
            $tok_type === T_DOUBLE_COLON) {
          $ignore_next_func = true;
        } else if (
            $tok_type !== T_WHITESPACE &&
            $tok_type !== T_COMMENT) {
          $cur_func = null;
          $ignore_next_func = false;
        }
      }
    }
    return '';
  }

  /**
   * The main interactive loop
   *
   * @author    ccheever
   * @author    dcorson
   */
    public function interactive_loop() {
        extract($GLOBALS);

        if (PCNTL_EXISTS) {
            // python spawned-processes ignore SIGPIPE by default, this makes sure
            //  the php process exits when the terminal is closed
            pcntl_signal(SIGPIPE, SIG_DFL);
        }

        while (!feof($this->_handle)) {
            // indicate to phpsh (parent process) that we are ready for more input
            fwrite($this->_comm_handle, "ready\n");

            // multiline inputs are encoded to one line
            $buffer_enc = fgets($this->_handle, $this->_MAX_LINE_SIZE);
            $buffer = stripcslashes($buffer_enc);

            $err_msg = '';
            if ($this->do_undefined_function_check) {
                $undefd_func = $this->undefined_function_check($buffer);
                if ($undefd_func) {
                    $err_msg = 'Not executing input: Possible call to undefined function ' .
                        $undefd_func . "()\n" .
                        'See /etc/phpsh/config.sample to disable UndefinedFunctionCheck.';
                }
            }
            if ($err_msg) {
                if ($this->do_color) {
                    echo "\033[31m"; // red
                }
                echo $err_msg;
                if ($this->do_color) {
                    echo "\033[0m";  // reset color
                }
                echo "\n";
                continue;
            }

            // evaluate what the user entered
            if ($this->do_color) {
                echo "\033[33m"; // yellow
            }

            if ($this->fork_every_command) {
                $pid = pcntl_fork();
                $evalue = null;
                if ($pid) {
                    pcntl_wait($status);
                } else {
                    try {
                        $evalue = eval($buffer);
                    } catch (\Exception $e) {
                        // unfortunately, almost all exceptions that aren't explicitly
                        // thrown by users are uncatchable :(
                        fwrite(STDERR, 'Uncaught exception: ' . get_class($e) . ': ' .
                            $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
                        $evalue = null;
                    }

                    // if we are still alive..
                    $childpid = getmypid();
                    fwrite($this->_comm_handle, "child $childpid\n");
                }
            } else {
                try {
                    $evalue = eval($buffer);
                } catch (\Exception $e) {
                    // unfortunately, almost all exceptions that aren't explicitly thrown
                    // by users are uncatchable :(
                    fwrite(STDERR, 'Uncaught exception: ' . get_class($e) . ': ' .
                        $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
                    $evalue = null;
                }
            }

            if ($buffer != "xdebug_break();\n") {
                ___phpsh___eval_completed();
            }

            // if any value was returned by the evaluated code, echo it
            if (isset($evalue)) {
                if ($this->do_color) {
                    echo "\033[36m"; // cyan
                }
                echo pretty_print($evalue);
            }
            // set $_ to be the value of the last evaluated expression
            $_ = $evalue;
            // back to normal for prompt
            if ($this->do_color) {
                echo "\033[0m";
            }
            // newline so we end cleanly
            echo "\n";
        }
    }
}

$__init__ = function () {

    // set the TFBENV to script
    $_SERVER['TFBENV'] = 16777216;

    $argv = $GLOBALS['argv'];

    // FIXME: www/lib/thrift/packages/falcon/falcon.php is huge
    //  this is probably not the right fix, but we need it for now
    $memory_limit = ini_get('memory_limit');

    switch(strtolower($memory_limit[strlen($memory_limit) - 1])) {
        case 'g':
            $memory_limit *= 1024;
        case 'm':
            $memory_limit *= 1024;
        case 'k':
            $memory_limit *= 1024;
    }
    ini_set('memory_limit', $memory_limit * 2);

    if (version_compare(PHP_VERSION, '5.4.0', '<')) {
        fwrite(STDERR, 'Fatal error: phpsh requires PHP 5.4 or greater');
        exit;
    }

    $missing = array_diff(['pcre','tokenizer'], get_loaded_extensions());
    if ($missing) {
        fwrite(
            STDERR,
            'Fatal error: phpsh requires the following extensions: '.implode(', ', $missing));
        exit;
    }

    define('PCNTL_EXISTS', in_array('pcntl', get_loaded_extensions()));

    // we buffer the output on includes so that output that gets generated by
    // includes doesn't interfere with the secret messages we pass between php and
    // python we'll capture any output and show it when we construct the shell
    // object
    ob_start();

    $___phpsh___codebase_mode = $argv[2];
    $homerc = getenv('HOME').'/.phpsh/rc.php';
    if (file_exists($homerc)) {
        require_once $homerc;
    } else {
        require_once '/etc/phpsh/rc.php';
    }

    $do_color = true;
    $do_autocomplete = true;
    $do_undefined_function_check = true;
    $options_possible = true;
    $fork_every_command = false;
    foreach (array_slice($GLOBALS['argv'], 3) as $arg) {
        $did_arg = true;
        if ($options_possible) {
            switch ($arg) {
                case '-c':
                    $do_color = false;
                    break;
                case '-A':
                    $do_autocomplete = false;
                    break;
                case '-u':
                    $do_undefined_function_check = false;
                    break;
                case '-f':
                    $fork_every_command = true;
                    break;
                case '--':
                    $options_possible = false;
                    break;
                default:
                    $did_arg = false;
            }
            if ($did_arg) {
                continue;
            }
        }
        include_once $arg;
    }

    $output_from_includes = ob_get_contents();
    ob_end_clean();

    // We make our pretty-printer override-able in rc.php just in case anyone cares
    // enough to tweak it.
    if (!function_exists('\__phpsh__\pretty_print')) {
        if (function_exists('xdebug_var_dump')) {
            function pretty_print($x) {
                xdebug_var_dump($x);
            }
        } else {
            require_once __DIR__ . '/php/pretty_print.php';
        }
    }

    return new ___Phpsh___(
        $output_from_includes, $do_color, $do_autocomplete, $do_undefined_function_check,
        $fork_every_command, $argv[1]);
};

/** @var ___Phpsh___ $___phpsh___ */
$___phpsh___ = $__init__();
unset($__init__);
$___phpsh___->interactive_loop();
