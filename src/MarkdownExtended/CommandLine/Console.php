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
    MarkdownExtended\CommandLine\AbstractConsole,
    MarkdownExtended\Helper as MDE_Helper,
    MarkdownExtended\Exception as MDE_Exception;

/**
 * Command line controller/interface for MarkdownExtended
 */
class Console extends AbstractConsole
{

    /**
     * @var string
     */
    protected $md_content='';

    /**
     * @var string
     */
    protected $md_parsed_content='';

    /**#@+
     * Command line options values
     */
    protected $output        =false;
    protected $multi         =false;
    protected $config        =false;
    protected $filter_html   =false;
    protected $filter_styles =false;
    protected $nofilter      =false;
    protected $extract       =false;
    protected $format        ='HTML';
    /**#@-*/

    /**
     * Command line options
     */
    static $cli_options = array(
        'x'=>'verbose', 
        'q'=>'quiet', 
        'debug', 
        'v'=>'version', 
        'h'=>'help', 
        'o:'=>'output:', 
        'm'=>'multi', 
        'c:'=>'config:', 
        'f:'=>'format:', 
        'g:'=>'gamuts::', 
        'n:'=>'nofilter:', 
        'e::'=>'extract::',
        'man',
//      'filter-html', 
//      'filter-styles', 
        // aliases
        's'=>'simple',
        'b'=>'body',
    );

    /**
     * @static array
     */
    public static $extract_presets = array(
        'body'=>array(
            'getter'=>'getBody',
            'gamuts'=>null
        ),
        'meta'=>array(
            'getter'=>'getMetadata',
            'gamuts'=>array('filter:MetaData:strip'=>1)
        ),
        'notes'=>array(
            'getter'=>'getNotes',
            'gamuts'=>null
        ),
        'footnotes'=>array(
            'getter'=>'getFootnotes',
            'gamuts'=>null
        ),
        'glossary'=>array(
            'getter'=>'getGlossaries',
            'gamuts'=>null
        ),
        'citations'=>array(
            'getter'=>'getCitations',
            'gamuts'=>null
        ),
        'urls'=>array(
            'getter'=>'getUrls',
            'gamuts'=>null
        ),
        'menu'=>array(
            'getter'=>'getMenu',
            'gamuts'=>null
        ),
    );
    
    /**
     * Internal counter
     */
    static $parsedfiles_counter=1;

    /**
     * Constructor
     *
     * Setup the input/output, verify that we are in CLI mode and that something is requested
     * @see self::runOptions()
     */
    public function __construct()
    {
        parent::__construct();
        if (empty($this->options) && empty($this->input)) {
            $this->error("No argument found - nothing to do!");
        }
        $this->runOption_config(MarkdownExtended::FULL_CONFIGFILE);
        $this->runOptions();
    }

// -------------------
// Options methods
// -------------------

    /**
     * Get the help string
     */
    public function runOption_help()
    {
        $class_name = MarkdownExtended::MDE_NAME;
        $class_version = MarkdownExtended::MDE_VERSION;
        $class_sources = MarkdownExtended::MDE_SOURCES;
        $help_str = <<<EOT
[ {$class_name} {$class_version} - CLI interface ]

Converts text(s) in specified file(s) (or stdin) from markdown syntax source(s).
The rendering can be the full parsed content or just a part of this content.
By default, result is written through stdout in HTML format.

Usage:
    ~$ php path/to/markdown_extended [OPTIONS ...] [INPUT FILE(S) OR STRING(S)]

Options:
    -v | --version             get Markdown version information
    -h | --help                get this help information
    -x | --verbose             increase verbosity of the script
    -q | --quiet               do not write Markdown Parser or PHP error messages
    -m | --multi               multi-files input (automatic if multiple file names found)
    -o | --output    = FILE    specify a file (or a file mask) to write generated content in
    -c | --config    = FILE    configuration file to use for Markdown instance (INI format)
    -f | --format    = NAME    format of the output (default is HTML)
    -e | --extract  [= META]   extract some data (the meta data array by default) from the Markdown input
    -g | --gamuts   [= NAME]   get the list of gamuts (or just one if specified) processed on Markdown input
    -n | --nofilter  = A,B     specify a list of filters that will be ignored during Markdown parsing

Aliases:
    -b | --body                get only the body part from parsed content (alias of '-e=body')
    -s | --simple              use the simple pre-defined configuration file ; preset for input fields

For a full manual, try `~$ man ./path/to/markdown_extended.man` if the file exists ;
if it doesn't, you can try option '--man' of this script to generate it if possible.

More infos at <{$class_sources}>.
EOT;
        $this->write($help_str);
        $this->endRun();
        exit(0);
    }

    /**
     * Run the version option
     */
    public function runOption_version()
    {
        $info = MDE_Helper::info();
        $git_ok = $this->exec("which git");
        $git_dir = getcwd() . '/.git';
        if (!empty($git_ok) && file_exists($git_dir) && is_dir($git_dir)) {
            $remote = $this->exec("git config --get remote.origin.url");
            if (!empty($remote) && (
                strstr($remote, MarkdownExtended::MDE_SOURCES) ||
                strstr($remote, str_replace('http', 'https', MarkdownExtended::MDE_SOURCES))
            )) {
                $versions = $this->exec("git rev-parse --abbrev-ref HEAD && git rev-parse HEAD && git log -1 --format='%ci' --date=short | cut -s -f 1 -d ' '");
                if (!empty($versions)) {
                    $info .= ' '.implode(' ', $versions);
                }
            }
        }
        $this->write($info);
        $this->endRun();
        exit(0);
    }

    /**
     * Run the manual option
     */
    public function runOption_man()
    {
        $info = '';
        $man_ok = $this->exec("which man");
        $man_path = getcwd() . '/bin/markdown_extended.man';
        if (!empty($man_ok)) {
            if (!file_exists($man_path)) {
                $ok = $this->exec("php bin/markdown_extended -f man -o bin/markdown_extended.man docs/MANPAGE.md");
            }
            if (file_exists($man_path)) {
                $info = 'OK, you can now run "man ./bin/markdown_extended.man"';
            } else {
                $info = 'Can not launch "man" command, file not found or command not accessible ... Try to run "man ./bin/markdown_extended.man".';
            }
        }
        $this->write($info);
        $this->endRun();
        exit(0);
    }

    /**
     * Run the multi option
     */
    public function runOption_multi()
    {
        $this->multi = true;
        $this->info("Enabling 'multi' input mode");
    }

    /**
     * Run the output option
     * @param string $file The command line option argument
     */
    public function runOption_output($file)
    {
        $this->output = $file;
        $this->info("Setting output to `$this->output`, parsed content will be written in file(s)");
    }

    /**
     * Run the config file option
     * @param string $file The command line option argument
     */
    protected function runOption_config($file)
    {
        $this->config = $file;
        $this->info("Setting configuration file to `$this->config`");
    }

    /**
     * Run the HTML filter option
     */
    public function runOption_filter_html()
    {
        $this->filter_html = true;
        $this->info("Enabling HTML filter, all HTML will be parsed");
    }

    /**
     * Run the styles filter option
     */
    public function runOption_filter_styles()
    {
        $this->filter_styles = true;
        $this->info("Enabling HTML styles filter, will try to parse styles");
    }

    /**
     * Run the extract option
     * @param string $type The command line option argument
     */
    public function runOption_extract($type)
    {
        if (empty($type)) $type = 'meta';
        if (!array_key_exists($type, self::$extract_presets)) {
            $this->error("Unknown extract option '$type'!");
        }
        $this->extract = $type;
        $this->info("Setting 'extract' to `$this->extract`, only this part will be extracted");
    }

    /**
     * Run the no-filter option
     * @param string $str The command line option argument
     */
    public function runOption_nofilter($str)
    {
        $this->nofilter = explode(',', $str);
        $this->info("Setting 'nofilter' to `".join(', ', $this->nofilter)."`, these will be ignored during parsing");
    }

    /**
     * Run the format option
     * @param string $str The command line option argument
     */
    public function runOption_format($str)
    {
        $this->format = $str;
        $this->info("Setting parser format to `".$this->format."`");
    }

    /**
     * Run the gamuts option : list gamuts pile of the parser
     * @param string $name The command line option argument
     */
    protected function runOption_gamuts($name = null)
    {
        $_emd = $this->getEmdInstance();
        if (empty($name)) {
            $this->info("Getting lists of Gamuts from Markdown parser with current config");
        } else {
            $this->info("Getting '$name' list of Gamuts from Markdown parser with current config");
        }
        $str='';
        $gamuts = array();
        if (!empty($name)) {
            $gamuts[$name] = MarkdownExtended::getConfig($name);
            if (empty($gamuts[$name])) {
                unset($gamuts[$name]);
                $name .= '_gamut';
                $gamuts[$name] = MarkdownExtended::getConfig($name);
                if (empty($gamuts[$name])) {
                    unset($gamuts[$name]);
                    if ($this->verbose===true) {
                        $this->error("Unknown Gamut '$name'!");
                    }
                }
            }
        } else {
            $gamuts['initial_gamut'] = MarkdownExtended::getConfig('initial_gamut');
            $gamuts['transform_gamut'] = MarkdownExtended::getConfig('transform_gamut');
            $gamuts['document_gamut'] = MarkdownExtended::getConfig('document_gamut');
            $gamuts['span_gamut'] = MarkdownExtended::getConfig('span_gamut');
            $gamuts['block_gamut'] = MarkdownExtended::getConfig('block_gamut');
        }
        if (!empty($gamuts)) {
            $str = $this->_renderOutput($gamuts);
        } else {
            $this->info('Empty gamuts stack');
        }
        $this->write($str);
        $this->endRun();
        exit(0);
    }

    /**
     * Run the 'body' alias
     */
    public function runOption_body()
    {
        $this->runOption_extract('body');
    }

    /**
     * Run the 'simple' alias
     */
    public function runOption_simple()
    {
        $this->runOption_config(MarkdownExtended::SIMPLE_CONFIGFILE);
    }

// -------------------
// CLI methods
// -------------------

    /**
     * Run the command line options of the request
     */
    protected function runOptions()
    {
        parent::runOptions();
        if (!empty($this->input)) {
            if (count($this->input)>1 && $this->multi!==true) {
                $this->runOption_multi();
            }
            if ($this->multi===true) {
                $this->info("Multi-input is set to `".join('`, `', $this->input)."`");
            } else {
                $this->info("Input is set to `{$this->input[0]}`");
            }
        }
    }

    /**
     * Run the whole script depending on options setted
     */
    public function run()
    {
        $this->info(PHP_EOL.">>>> let's go for the parsing ...".PHP_EOL, true, false);
        if (!empty($this->input)) {
            if ($this->multi===true) {
                $myoutput = $this->output;
                foreach ($this->input as $_input) {
                    if (!empty($this->output) && count($this->input)>1) {
                        $this->output = $this->_buildOutputFilename($myoutput);
                    }
                    $_ok = $this->runStoryOnOneFile($_input, true);
                }
                $this->separator();
            } else {
                $_ok = $this->runStoryOnOneFile($this->input[0]);
            }
        } else {
            $this->error("No input markdown file or string entered!");
        }
        $this->info(PHP_EOL.">>>> the parsing is complete.".PHP_EOL, true, false);
        $this->endRun(1);
    }

    /**
     * Run the MDE process on one file or input
     *
     * @param string $input
     * @param bool $title Set on `true` in case of multi-input
     *
     * @return string
     */
    public function runStoryOnOneFile($input, $title = false)
    {
        if ($this->extract!==false) {
            $infos = $this->runOneFile($input, null, $this->extract, $title);
            if ($this->verbose===true) {
                $this->endRun(false, "Infos extracted from input `$input`"
                    .(is_string($this->extract) ? " for tag `$this->extract`" : '')
                    .' : '.PHP_EOL.$infos);
            } else {
                $this->endRun(false, $infos, false);
            }
            return $infos;
        } elseif (!empty($this->output)) {
            $fsize = $this->runOneFile($input, $this->output, null, $title);
            if ($this->quiet!==true)
                $this->endRun(0, "OK - File `$this->output` ($fsize) written with parsed content from file `$input`");
            return $fsize;
        } else {
            $clength = $this->runOneFile($input, null, null, $title);
            return $clength;
        }
    }

    /**
     * Actually run the MDE process on a file or string
     *
     * @param string $input
     * @param string $output An optional output filename to write result in
     * @param bool $extract An extractor tagname
     * @param bool $title Set to `true` to add title string in case of multi-input
     *
     * @return string
     */
    public function runOneFile($input, $output = null, $extract = null, $title = false)
    {
        $return=null;
        if (!empty($input)) {
            $num = self::$parsedfiles_counter;
            $this->separator();
            $this->info( "[$num] >> parsing file `$input`" );
            if ($md_content = $this->getInput($input, $title)) {
                if (!is_null($extract)) {
                    $return = $this->extractContent($md_content, $extract);
                } else {
                    $md_parsed_content = $this->parseContent($md_content);
                    if (!empty($output)) {
                        $return = $this->writeOutputFile($md_parsed_content, $output);
                    } else {
                        $return = $this->writeOutput($md_parsed_content);
                    }
                }
            }
            self::$parsedfiles_counter++;
        }
        return $return;
    }

// -------------------
// Process
// -------------------

    /**
     * Use of the PHP Markdown Extended class as a singleton
     */
    protected function getEmdInstance(array $config = array())
    {
        $config['skip_filters'] = $this->nofilter;
        if (false!==$this->config) {
            $config['config_file'] = $this->config;
        }
        if (!empty($this->format)) {
            $config['output_format'] = $this->format;
        }           
        return parent::getEmdInstance($config);
    }
    
    /**
     * Creates a `\MarkdownExtended\Content` object from filename or string
     *
     * @param string $input
     * @param bool $title Set to `true` to add title string in case of multi-input
     *
     * @return \MarkdownExtended\Content
     * @throws any catched exception
     */
    public function getInput($input, $title = false)
    {
        $md_content=null;
        if (!empty($input)) {
            if (@file_exists($input)) {
                $this->info("Loading input file `$input` ... ");
                if ($title===true) {
                    $this->writeInputTitle($input);
                }
                try {
                    $md_content = new \MarkdownExtended\Content(null, $input);
                } catch (\MarkdownExtended\Exception\DomainException $e) {
                    $this->catched($e);
                } catch (\MarkdownExtended\Exception\RuntimeException $e) {
                    $this->catched($e);
                } catch (\MarkdownExtended\Exception\UnexpectedValueException $e) {
                    $this->catched($e);
                } catch (\MarkdownExtended\Exception\InvalidArgumentException $e) {
                    $this->catched($e);
                } catch (\MarkdownExtended\Exception\Exception $e) {
                    $this->catched($e);
                } catch (\Exception $e) {
                    $this->catched($e);
                }
            } elseif (!empty($input) && is_string($input)) {
                $this->info("Loading Markdown string from STDIN [strlen: ".strlen($input)."] ... ");
                if ($title===true) {
                    $this->writeInputTitle('STDIN input');
                }
                try {
                    $md_content = new \MarkdownExtended\Content($input);
                } catch (\MarkdownExtended\Exception\DomainException $e) {
                    $this->catched($e);
                } catch (\MarkdownExtended\Exception\RuntimeException $e) {
                    $this->catched($e);
                } catch (\MarkdownExtended\Exception\UnexpectedValueException $e) {
                    $this->catched($e);
                } catch (\MarkdownExtended\Exception\InvalidArgumentException $e) {
                    $this->catched($e);
                } catch (\MarkdownExtended\Exception\Exception $e) {
                    $this->catched($e);
                } catch (\Exception $e) {
                    $this->catched($e);
                }
            } else {
                $this->error("Entered input seems to be neither a file (not found) nor a well-formed string!");
            }
        }
        return $md_content;
    }

    /**
     * Process a Content parsing
     *
     * @param object $md_content \MarkdownExtended\Content instance
     *
     * @return string
     * @throws any catched exception
     */
    public function parseContent(\MarkdownExtended\Content $md_content)
    {
        $md_output=null;
        if (!empty($md_content)) {
            $_emd = $this->getEmdInstance();
            $this->info("Parsing Mardkown content ... ", false);
            try {
                $md_output = $_emd->get('Parser')
                    ->parse($md_content)
                    ->getFullContent();
            } catch (\MarkdownExtended\Exception\DomainException $e) {
                $this->catched($e);
            } catch (\MarkdownExtended\Exception\RuntimeException $e) {
                $this->catched($e);
            } catch (\MarkdownExtended\Exception\UnexpectedValueException $e) {
                $this->catched($e);
            } catch (\MarkdownExtended\Exception\InvalidArgumentException $e) {
                $this->catched($e);
            } catch (\MarkdownExtended\Exception\Exception $e) {
                $this->catched($e);
            } catch (\Exception $e) {
                $this->catched($e);
            }
            if ($md_output) {
                $this->md_parsed_content .= $md_output;
                $this->info("OK", true, false);
            } else {
                $this->error("An error occured while trying to parse Markdown content ! (try to run `cd dir/to/markdown_extended ...`)");
            }
        }
        return $md_output;
    }

    /**
     * Process a Content parsing just for special gamuts
     *
     * @param object $md_content \MarkdownExtended\Content instance
     * @param string $extract
     *
     * @return string
     * @throws any catched exception
     */
    public function extractContent(\MarkdownExtended\Content $md_content, $extract)
    {
        $md_output = '';
        $preset = self::$extract_presets[$extract];
        if (!empty($preset) && !empty($md_content)) {
            $options = array();
            if (!empty($preset['gamuts'])) {
                $options['special_gamut'] = $preset['gamuts'];
            }
            $_emd = $this->getEmdInstance($options);
            $this->info("Extracting Mardkown $extract ... ", false);
            try {
                $md_content_parsed = $_emd->get('Parser')
                    ->parse($md_content)
                    ->getContent();
                $output = call_user_func(
                    array($md_content_parsed, ucfirst($preset['getter']))
                );
                $md_output = $this->_renderOutput($output);
            } catch (\MarkdownExtended\Exception\DomainException $e) {
                $this->catched($e);
            } catch (\MarkdownExtended\Exception\RuntimeException $e) {
                $this->catched($e);
            } catch (\MarkdownExtended\Exception\UnexpectedValueException $e) {
                $this->catched($e);
            } catch (\MarkdownExtended\Exception\InvalidArgumentException $e) {
                $this->catched($e);
            } catch (\MarkdownExtended\Exception\Exception $e) {
                $this->catched($e);
            } catch (\Exception $e) {
                $this->catched($e);
            }
            if ($output) {
                if (is_string($output)) {
                    $length = strlen($output);
                } elseif (is_array($output)) {
                    $length = count($output);
                }
                $this->info("OK [entries: ".$length."]", true, false);
            } else {
                $this->error("An error occured while trying to extract data form Markdown content ! (try to run `cd dir/to/markdown_extended ...`)");
            }
        }
        return $md_output;
    }

}

// Endfile