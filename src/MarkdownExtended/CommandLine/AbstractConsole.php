<?php
/**
 * PHP Markdown Extended
 * Copyright (c) 2008-2013 Pierre Cassat
 *
 * original MultiMarkdown
 * Copyright (c) 2005-2009 Fletcher T. Penney
 * <http://fletcherpenney.net/>
 *
 * original PHP Markdown & Extra
 * Copyright (c) 2004-2012 Michel Fortin  
 * <http://michelf.com/projects/php-markdown/>
 *
 * original Markdown
 * Copyright (c) 2004-2006 John Gruber  
 * <http://daringfireball.net/projects/markdown/>
 */
namespace MarkdownExtended\CommandLine;

use MarkdownExtended\MarkdownExtended,
    MarkdownExtended\Helper as MDE_Helper,
    MarkdownExtended\Exception as MDE_Exception;

/**
 * Command line controller/interface base
 *
 * This base command line class is designed to use options '-x' or '--verbose' to increase
 * script verbosity on STDOUT and '-q' or '--quiet' to decrease it. The idea is quiet simple:
 *
 * -   in "normal" rendering (no "verbose" neither than "quiet" mode), the result of the 
 *     processed content is rendered, with the file name header in case of multi-files input
 *     and command line script's errors are rendered
 * -   in "verbose" mode, some process informations are shown, informing user about what
 *     happening, follow process execution and get some execution informations such as some
 *     some string lengthes ; the command line script errors are rendered
 * -   in "quiet" mode, nothing is written through SDTOUT except PHP process errors and output
 *     rendering of parsed content ; the command line script's errors are not rendered
 *
 * For all of these cases, PHP errors catched during Markdown Extended classes execution are
 * rendered and script execution may stop.
 */
abstract class AbstractConsole 
{

    /**
     * @var STDOUT
     */
    public $stdout;

    /**
     * @var STDIN
     */
    public $stdin;

    /**
     * @var \MarkdownExtended\MarkdownExtended
     */
    protected static $emd_instance;

    /**#@+
     * Command line options values
     */
    protected $input         =array();
    protected $verbose       =false;
    protected $quiet         =false;
    protected $debug         =false;
    /**#@-*/

    /**#@+
     * Command line options
     */
    protected $options;
    static $cli_options = array(
        'x'=>'verbose', 
        'q'=>'quiet', 
        'debug', 
    );
    /**#@-*/

    /**
     * Constructor
     *
     * Setup the input/output and verify that we are in CLI mode
     */
    public function __construct()
    {
        $this->stdout = fopen('php://stdout', 'w');
        $this->stdin = fopen('php://stdin', 'w');
        if (php_sapi_name() != 'cli') {
            exit('<!-- NOT IN CLI -->');
        }
        $this->getOptions();
    }

// -------------------
// Writing methods
// -------------------

    /**
     * Write an info to CLI output
     * @param string $str The information to write
     * @param bool $new_line May we pass a line after writing the info
     */
    public function write($str, $new_line = true)
    {
        fwrite($this->stdout, $str.($new_line===true ? PHP_EOL : ''));
        fflush($this->stdout);
    }
    
    /**
     * Write an info in verbose mode
     * @param string $str The information to write
     * @param bool $new_line May we pass a line after writing the info
     */
    public function info($str, $new_line = true, $leading_dot = true)
    {
        if (!empty($str) && $this->verbose===true) {
            $this->write(($leading_dot ? '. ' : '').$str, $new_line);
        }
    }
    
    /**
     * Write an separator line in verbose mode
     */
    public function separator()
    {
        if ($this->verbose===true) {
            $this->write("  -------------------------------------------  ");
        }
    }

    /**
     * Write an error info and exit
     * @param string $str The information to write
     * @param int $code The error code used to exit the script
     */
    public function error($str, $code = 90, $forced = false)
    {
        if ($this->quiet!==true || $forced===true) {
            $this->write(PHP_EOL.">> ".$str.PHP_EOL);
            $this->write("( run '--help' option to get information )");
        }
        if ($code>0) {
            $this->endRun();
            exit($code);
        }
    }
    
    /**
     * Write an info and exit
     * @param bool $exit May we have to exit the script after writing the info?
     * @param string $str The information to write
     */
    protected function endRun($exit = false, $str = null, $leading_signs = true)
    {
        if ($this->quiet===true) ini_restore('error_reporting'); 
        if (!empty($str)) $this->write(($leading_signs ? '>> ' : '').$str);
        if ($exit==true) exit(0);
    }

    /**
     * Write a catched exception
     * @param object $e Exception thrown
     */
    public function catched($e)
    {
        $str = sprintf(
            'Catched "%s" [file %s - line %d]: "%s"',
            get_class($e),
            str_replace(realpath(__DIR__.'/../../../'), '', $e->getFile()),
            $e->getLine(), $e->getMessage()
        );
        if ($this->verbose===true || $this->debug===true) {
            $str .= PHP_EOL.PHP_EOL.$e->getTraceAsString();
        }
        return $this->error($str, $e->getCode(), true);
    }
    
    /**
     * Exec a command
     * @param string $cmd
     * @return string|array
     */
    public function exec($cmd)
    {
        try {
            exec($cmd, $output, $status);
            if ($status!==0) {
                throw new \RuntimeException(
                    sprintf('Error exit status while executing command : [%s]!', $cmd), $status
                );
            }
        } catch (\RuntimeException $e) {
            $this->catched($e);
        }
        return is_array($output) && count($output)===1 ? $output[0] : $output;
    }
    
// -------------------
// Options
// -------------------

    /**
     * Get the command line user options
     */
    protected function getOptions()
    {
        $this->options = getopt(
            join('', array_keys($this::$cli_options)),
            array_values($this::$cli_options)
        );

        $argv = $_SERVER['argv'];
        $last = array_pop($argv);
        while ($last && count($argv)>=1 && $last[0]!='-' && !in_array($last,$this->options)) {
            $this->input[] = $last;
            $last = array_pop($argv);
        }
        $this->input = array_reverse($this->input);
    }

    /**
     * Run the command line options of the request
     */
    protected function runOptions()
    {
        foreach ($this->options as $_opt_n=>$_opt_v) {
            $opt_torun=false;
            foreach (array($_opt_n, $_opt_n.':', $_opt_n.'::') as $_opt_item) {
                if (array_key_exists($_opt_item, $this::$cli_options)) {
                    $opt_torun = $this::$cli_options[$_opt_item];
                } elseif (in_array($_opt_item, $this::$cli_options)) {
                    $opt_torun = $_opt_n;
                }
            }
            $_opt_method = 'runOption_'.str_replace(':', '', str_replace('-', '_', $opt_torun));
            if (method_exists($this, $_opt_method)) {
                $ok = call_user_func_array(
                    array($this, $_opt_method),
                    array($_opt_v)
                );
            } else {
                if (count($this->options)==1) {
                    $this->error("Unknown option '$_opt_n'!");
                } else {
                    $this->info("Unknown option '$_opt_n'! (argument ignored)");
                }
            }
        }
    }

    /**
     * Run the verbose option
     */
    public function runOption_verbose()
    {
        $this->verbose = true;
        $this->info("Enabling 'verbose' mode");
    }

    /**
     * Run the quiet option
     */
    public function runOption_quiet()
    {
        $this->quiet = true;
        error_reporting(0); 
        $this->info("Enabling 'quiet' mode");
    }

    /**
     * Run the debug option
     */
    public function runOption_debug()
    {
        $this->debug = true;
        error_reporting(E_ALL); 
        $this->info("Enabling 'debug' mode");
    }

// -------------------
// Process methods
// -------------------

    /**
     * Use of the PHP Markdown Extended class as a singleton
     * @param array $config
     * @return \MarkdownExtended\MarkdownExtended instance
     */
    protected function getEmdInstance(array $config = array())
    {
        if (empty(self::$emd_instance)) {
            $this->info("Creating a MarkdownExtended instance with options ["
                .str_replace("\n", '', var_export($_options,1))
                ."]");
            self::$emd_instance = MarkdownExtended::create();
        }
        self::$emd_instance->get('Parser', $config);
        return self::$emd_instance;
    }
    
    /**
     * Writes an output safely for STDOUT (string or arrays)
     *
     * @param misc $content
     * @param int $indent internal indentation flag
     * @return string
     */
    protected function _renderOutput($content, $indent = 0)
    {
        $text = '';
        if (is_string($content) || is_numeric($content)) {
            $text .= $content;
        } elseif (is_array($content)) {
            $max_length = 0;
            foreach ($content as $var=>$val) {
                if (strlen($var)>$max_length) $max_length = strlen($var);
            }
            foreach ($content as $var=>$val) {
                $text .= PHP_EOL
                    .($indent>0 ? str_repeat('    ', $indent) : '')
                    .str_pad($var, $max_length, ' ').' : '
                    .$this->_renderOutput($val, ($indent+1));
            }
        }
        return ($indent===0 ? trim($text, PHP_EOL) : $text);
    }

// -------------------
// CLI methods
// -------------------

    /**
     * Run the whole script depending on options setted
     */
    abstract public function run();

// ----------------------
// Utilities
// ----------------------

    /**
     * Write a result for each processed file or string in a file
     * @param string $output
     * @param string $output_file
     */
    public function writeOutputFile($output, $output_file)
    {
        $fsize=null;
        if (!empty($output) && !empty($output_file)) {
            $this->info("Writing parsed content in output file `$output_file`", false);
            if ($ok = @file_put_contents($output_file, $output)) {
                $fsize = MDE_Helper::getFileSize($output_file);
                $this->info("OK [file size: $fsize]");
            } else {
                $this->error("Can not write output file `$output_file` ! (try to run `sudo ...`)");
            }
        }
        return $fsize;
    }

    /**
     * Write a result for each processed file or string
     * @param string $output
     * @param bool $exit
     */
    public function writeOutput($output, $exit = false)
    {
        $clength=null;
        if (!empty($output)) {
            $clength = strlen($output);
            $this->info("Rendering parsed content [strlen: $clength]");
            $this->separator();
            $this->write($output);
        }
        return $clength;
    }

    /**
     * Write a title for each processed file or string
     * @param string $title
     */
    public function writeInputTitle($title)
    {
        $this->write("==> $title <==");
    }

    protected function _buildOutputFilename($filename)
    {
        if (file_exists($filename)) {
            $ext = strrchr($filename, '.');
            $_f = str_replace($ext, '', $filename);
            return $_f.'_'.self::$parsedfiles_counter.$ext;
        }
        return $filename;
    }

}

// Endfile