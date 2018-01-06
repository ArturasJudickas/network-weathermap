<?php

namespace Weathermap\CLI;

use GetOpt\GetOpt;
use GetOpt\Option;
use GetOpt\ArgumentException;
use Weathermap\Core\Map;
use Weathermap\Core\MapUtility;

class Runner
{
    /** @var GetOpt $getOpt */
    private $getOpt;
    private $defines = array();
    private $options_output = array();

    /** @var Map $map */
    private $map;
    private $configfile;
    private $imagefile;
    private $htmlfile;

    public function run()
    {
        $rrdtool = "/usr/bin/rrdtool";

        $this->getOptions();
        $this->translateOptionsToSettings();

        $this->map = new Map();
        $this->map->rrdtool = $rrdtool;
        $this->map->context = "cli";

        if ($this->makeMap()) {

            $this->postRun();
        }
    }

    private function getOptions()
    {
        $this->getOpt = new \GetOpt\GetOpt(null, [\GetOpt\GetOpt::SETTING_STRICT_OPERANDS => true]);

        $this->getOpt->addOptions(array(
                Option::create(null, 'version', GetOpt::NO_ARGUMENT)
                    ->setDescription('Show version info and quit'),
                Option::create('h', 'help', GetOpt::NO_ARGUMENT)
                    ->setDescription('Show this help and quit'),

                Option::create(null, 'config', GetOpt::REQUIRED_ARGUMENT)
                    ->setDescription('filename to read from. Default weathermap.conf')
                    ->setArgumentName('filename')
                    ->setDefaultValue('weathermap.conf'),
                Option::create(null, 'output', GetOpt::REQUIRED_ARGUMENT)
                    ->setDescription('filename to write image. Default weathermap.png')
                    ->setArgumentName('filename')
                    ->setDefaultValue('weathermap.png'),
                Option::create(null, 'htmloutput', GetOpt::REQUIRED_ARGUMENT)
                    ->setDescription('filename to write HTML. Default weathermap.html')
                    ->setArgumentName('filename')
                    ->setDefaultValue('weathermap.html'),

                Option::create(null, 'image-uri', GetOpt::REQUIRED_ARGUMENT)
                    ->setArgumentName('uri')
                    ->setDescription('URI to prefix <img> tags in HTML output'),

                Option::create(null, 'define', GetOpt::MULTIPLE_ARGUMENT)
                    ->setArgumentName('name=value')
                    ->setDescription('Define internal variables (equivalent to global SET in config file)'),
                Option::create(null, 'stats', GetOpt::NO_ARGUMENT)
                    ->setDescription('produce statistics for map after completion'),

                Option::create(null, 'bulge', GetOpt::NO_ARGUMENT)
                    ->setDescription('Enable link-bulging mode. See manual.'),
                Option::create(null, 'no-data', GetOpt::NO_ARGUMENT)
                    ->setDescription('skip the data-reading process (just a \'grey\' map)'),
                Option::create(null, 'randomdata', GetOpt::NO_ARGUMENT)
                    ->setDescription('skip the data-reading process, generate random data'),

                Option::create(null, 'debug', GetOpt::NO_ARGUMENT)
                    ->setDescription('produce (LOTS) of debugging information during run'),
                Option::create(null, 'no-warn', GetOpt::REQUIRED_ARGUMENT)
                    ->setDescription('suppress warnings with listed errorcodes (comma-separated)')
                    ->setArgumentName('WMxxx_errorcode'),
                Option::create(null, 'dump-after', GetOpt::NO_ARGUMENT)
                    ->setDescription('(development) dump all internal PHP structures (HUGE)'),
                Option::create(null, 'uberdebug', GetOpt::NO_ARGUMENT)
                    ->setDescription('produce even more debug information'),
                Option::create(null, 'setdebug', GetOpt::NO_ARGUMENT)
                    ->setDescription('produce debug information related to map variables (SET)'),

                Option::create(null, 'dump-config', GetOpt::REQUIRED_ARGUMENT)
                    ->setArgumentName('filename')
                    ->setDescription('(development) dump config to a new file (testing editor)'),
                Option::create(null, 'dump-json', GetOpt::REQUIRED_ARGUMENT)
                    ->setArgumentName('filename')
                    ->setDescription('(development) dump JSON config to a new file'),
            )
        );

        // process arguments and catch user errors
        try {
            $this->getOpt->process();
        } catch (ArgumentException $exception) {
            file_put_contents('php://stderr', $exception->getMessage() . PHP_EOL);
            echo PHP_EOL . $this->getOpt->getHelpText();
            exit;
        }

        // show version and quit
        if ($this->getOpt->getOption('version')) {
            echo sprintf('PHP Network Weathermap: %s' . PHP_EOL, \Weathermap\Core\WEATHERMAP_VERSION);
            exit;
        }

        // show help and quit
        if ($this->getOpt->getOption('help')) {
            echo $this->getOpt->getHelpText();
            exit;
        }
    }

    private function translateOptionsToSettings()
    {
        global $weathermap_debug_suppress;
        global $weathermap_error_suppress;
        global $weathermap_debugging;

        $this->configfile = $this->getOpt->getOption('config');
        $this->htmlfile = $this->getOpt->getOption('htmloutput');
        $this->imagefile = $this->getOpt->getOption('output');
        $this->options_output['imageuri'] = $this->getOpt->getOption('image-uri');

        if ($this->getOpt->getOption('bulge') === 1) {
            $this->options_output['widthmod'] = true;
        }
        if ($this->getOpt->getOption('sizedebug') === 1) {
            $this->options_output['sizedebug'] = true;
        }
        if ($this->getOpt->getOption('no-data') === 1) {
            $this->options_output['sizedebug'] = true;
        }
        if ($this->getOpt->getOption('debug') === 1) {
            $this->options_output['debugging'] = true;
        }
        if ($this->getOpt->getOption('uberdebug') === 1) {
            $this->options_output['debugging'] = true;
            // allow ALL trace messages (normally we block some of the chatty ones)
            $weathermap_debug_suppress = array();
        }

        if ($this->getOpt->getOption('no-warn') != '') {
            // allow disabling of warnings from the command-line, too (mainly for the rrdtool warning)
            $suppress_warnings = explode(",", $this->getOpt->getOption('no-warn'));
            foreach ($suppress_warnings as $s) {
                $weathermap_error_suppress[] = strtoupper($s);
            }
        }

        $define_array = $this->getOpt->getOption('define');
        foreach ($define_array as $define) {
            preg_match("/^([^=]+)=(.*)\s*$/", $define, $matches);
            if (isset($matches[2])) {
                $varname = $matches[1];
                $value = $matches[2];
                MapUtility::debug(">> $varname = '$value'\n");
                // save this for later, so that when the map object exists, it can be defined
                $this->defines[$varname] = $value;
            } else {
                print "WARNING: --define format is:  --define name=value\n";
            }
        }

        // set this BEFORE we create the map object, so we get the debug output from Reset(), as well
        if (isset($this->options_output['debugging']) && $this->options_output['debugging']) {
            $weathermap_debugging = true;

            $weathermap_debugging = true;
            // enable assertion handling
            assert_options(ASSERT_ACTIVE, 1);
            assert_options(ASSERT_WARNING, 0);
            assert_options(ASSERT_QUIET_EVAL, 1);

            // Set up the callback
            assert_options(ASSERT_CALLBACK, 'my_assert_handler');

            MapUtility::debug("------------------------------------\n");
            MapUtility::debug("Starting PHP-Weathermap run, with config: $configfile\n");
            MapUtility::debug("------------------------------------\n");
        }

    }

    private function makeMap()
    {
        // now stuff in all the others, that we got from getopts
        foreach ($this->options_output as $key => $value) {
            $this->map->$key = $value;
        }

        if ($this->map->readConfig($this->configfile)) {
            $this->mapSettingsPostConfig();
            $this->getMapData();

            if ($this->imagefile != '') {
                $this->map->drawMap($this->imagefile);
                $this->map->imagefile = $this->imagefile;
            }

            $this->outputHTML();

            return true;
        }
        return false;
    }

    private function mapSettingsPostConfig()
    {
        // allow command-lines to override the config file, but provide a default if neither are present
        if ($this->imagefile == '') {
            if ($this->map->imageoutputfile == '') {
                $this->imagefile = "weathermap.png";
            } else {
                $this->imagefile = $this->map->imageoutputfile;
            }
        }

        if ($this->htmlfile == '') {
            if ($this->map->htmloutputfile != '') {
                $this->htmlfile = $this->map->htmloutputfile;
            }
        }

        // feed in any command-line defaults, so that they appear as if SET lines in the config

        // XXX FIXME
        foreach ($this->defines as $hintname => $hint) {
            $this->map->addHint($hintname, $hint);
        }

        // now stuff in all the others, that we got from getopts
        foreach ($this->options_output as $key => $value) {
            // $map->$key = $value;
            $this->map->addHint($key, $value);
        }
    }

    private function getMapData()
    {
        if ((isset($this->options_output['sizedebug']) && !$this->options_output['sizedebug']) || (!isset($this->options_output['sizedebug']))) {
            if ($this->getOpt->getOption('randomdata') === 1) {
                $this->map->randomData();
            } else {
                $this->map->ReadData();
            }
        }
    }

    private function outputHTML()
    {
        if ($this->htmlfile != '') {
            MapUtility::debug("Writing HTML to $this->htmlfile\n");

            $fd = fopen($this->htmlfile, 'w');
            fwrite($fd,
                '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"><html xmlns="http://www.w3.org/1999/xhtml"><head>');
            if ($this->map->htmlstylesheet != '') {
                fwrite($fd, '<link rel="stylesheet" type="text/css" href="' . $this->map->htmlstylesheet . '" />');
            }
            fwrite($fd,
                '<meta http-equiv="refresh" content="300" /><title>' . $this->map->processString($this->map->title,
                    $this->map) . '</title></head><body>');

            if ($this->map->htmlstyle == "overlib") {
                fwrite($fd,
                    "<div id=\"overDiv\" style=\"position:absolute; visibility:hidden; z-index:1000;\"></div>\n");
                fwrite($fd,
                    "<script type=\"text/javascript\" src=\"overlib.js\"><!-- overLIB (c) Erik Bosrup --></script> \n");
            }

            fwrite($fd, $this->map->makeHTML());
            fwrite($fd,
                '<hr /><span id="byline">Network Map created with <a href="http://www.network-weathermap.com/?vs=' . WEATHERMAP_VERSION . '">PHP Network Weathermap v' . WEATHERMAP_VERSION . '</a></span></body></html>');
            fclose($fd);
        }
    }

    private function postRun()
    {
        if ($this->getOpt->getOption('dump-config') != '') {
            $this->map->writeConfig($this->getOpt->getOption('dump-config'));
        }

        if ($this->getOpt->getOption('dump-json') != '') {
            $fd = fopen($this->getOpt->getOption('dump-json'), "w");
            fputs($fd, $this->map->getJSONConfig());
            fclose($fd);
        }

        if ($this->getOpt->getOption('stats') === 1) {
            $this->map->DumpStats();
        }

        if ($this->map->dataoutputfile != '') {
            $this->map->writeDataFile($this->map->dataoutputfile);
        }

        if ($this->getOpt->getOption('dump-after') === 1) {
            print_r($this->map);
        }

        if ($this->getOpt->getOption('setdebug') === 1) {
            foreach ($this->map->buildAllItemsList() as $item) {
                print "$item->name :\n";
                foreach ($item->hints as $n => $v) {
                    print "  SET $n = $v\n";
                }
                foreach ($item->notes as $n => $v) {
                    print "  -> $n = $v\n";
                }
                print "\n";
            }
        }
    }
}