<?php

namespace Weathermap\Core;

// PHP Weathermap 0.98
// Copyright Howard Jones, 2005-2016 howie@thingy.com
// http://www.network-weathermap.com/
// Released under the GNU Public License


class Map extends MapBase
{
    /** @var MapNode[] $nodes */
    public $nodes = array();
    /** var MapLink[] $links */
    public $links = array();

    // public $texts = array(); // an array containing all the extraneous text bits
    public $used_images = array(); // an array of image filenames referred to (used by editor ONLY)
    public $seen_zlayers = array(0 => array(), 1000 => array()); // 0 is the background, 1000 is the legends, title, etc

    public $next_id;

    public $background;
    public $kilo;
    public $width;
    public $height;
    public $htmlstyle;

    /** var  HTMLImagemap $imap */
    public $imap;

    public $rrdtool;

    public $sizedebug;
    public $widthmod;
    public $debugging;
    public $keyfont;
    public $timefont;

    public $titlefont;
    public $timex;
    public $timey;

    public $keyx;
    public $keyy;

    public $titlex;
    public $titley;
    public $mintimex;
    public $maxtimex;
    public $mintimey;
    public $maxtimey;

    public $min_ds_time;
    public $max_ds_time;
    public $minstamptext;
    public $maxstamptext;
    public $stamptext;
    public $datestamp;
    public $title;

    public $keytext;
    public $htmloutputfile;
    public $imageoutputfile;
    public $dataoutputfile;
    public $htmlstylesheet;
    public $configfile;
    public $imagefile;

    public $imageuri;
    public $keystyle;
    public $keysize;

    public $min_data_time;
    public $max_data_time;
    public $context;

    public $rrdtool_check;

    /** var  ImageLoader $imagecache */
    public $imagecache;
    public $selected;

    public $thumb_width, $thumb_height;
    public $has_includes;
    public $has_overlibs;
    public $node_template_tree;
    public $link_template_tree;
    public $dsinfocache = array();

    public $plugins = array();
    public $included_files = array();

    /** var Colour[] $colourtable  */
    public $colourtable = array();
    public $warncount = 0;

    /** var WeatherMapScale[] $scales */
    public $scales;
    public $fonts;

    /** var Stats $stats - a generic place to keep various statistics about the map */
    public $stats;

    public function __construct()
    {
        parent::__construct();

        $this->inherit_fieldlist = array
        (
            'width' => 800,
            'height' => 600,
            'kilo' => 1000,
            'numscales' => array('DEFAULT' => 0),
            'datasourceclasses' => array(),
            'preprocessclasses' => array(),
            'postprocessclasses' => array(),
            'included_files' => array(),
            'context' => '',
            'dumpconfig' => false,
            'rrdtool_check' => '',
            'background' => '',
            'imageoutputfile' => '',
            'imageuri' => '',
            'htmloutputfile' => '',
            'dataoutputfile' => '',
            'htmlstylesheet' => '',
            'labelstyle' => 'percent', // redundant?
            'htmlstyle' => 'static',
            'keystyle' => array('DEFAULT' => 'classic'),
            'title' => 'Network Weathermap',
            'keytext' => array('DEFAULT' => 'Traffic Load'),
            'keyx' => array('DEFAULT' => -1),
            'keyy' => array('DEFAULT' => -1),
            'keyimage' => array(),
            'keysize' => array('DEFAULT' => 400),
            'stamptext' => 'Created: %b %d %Y %H:%M:%S',
            'keyfont' => 4,
            'titlefont' => 2,
            'timefont' => 2,
            'timex' => 0,
            'timey' => 0,

            'mintimex' => -10000,
            'mintimey' => -10000,
            'maxtimex' => -10000,
            'maxtimey' => -10000,
            'minstamptext' => 'Oldest Data: %b %d %Y %H:%M:%S',
            'maxstamptext' => 'Newest Data: %b %d %Y %H:%M:%S',

            'thumb_width' => 0,
            'thumb_height' => 0,
            'titlex' => -1,
            'titley' => -1,
            'cachefolder' => 'cached',
            'mapcache' => '',
            'sizedebug' => false,
            'debugging' => false,
            'widthmod' => false,
            'has_includes' => false,
            'has_overlibs' => false,
            'name' => 'MAP'
        );

        $this->min_ds_time = null;
        $this->max_ds_time = null;

        $this->scales = array();

        $this->colourtable = array();

        $this->configfile = '';
        $this->imagefile = '';
        $this->imageuri = '';

        $this->fonts = new FontTable();
        $this->fonts->init();

        $this->stats = new Stats();

        $this->Reset();
    }


    public function my_type()
    {
        return "MAP";
    }

    public function __toString()
    {
        return "MAP";
    }

    private function Reset()
    {
        $this->imagecache = new ImageLoader();
        $this->next_id = 100;
        foreach (array_keys($this->inherit_fieldlist) as $fld) {
            $this->$fld = $this->inherit_fieldlist[$fld];
        }

        $this->min_ds_time = null;
        $this->max_ds_time = null;

        $this->nodes = array(); // an array of MapNodes
        $this->links = array(); // an array of MapLinks

        $this->createDefaultLinks();
        $this->createDefaultNodes();

        $this->node_template_tree = array();
        $this->link_template_tree = array();

        $this->node_template_tree['DEFAULT'] = array();
        $this->link_template_tree['DEFAULT'] = array();

        assert('is_object($this->nodes[":: DEFAULT ::"])');
        assert('is_object($this->links[":: DEFAULT ::"])');
        assert('is_object($this->nodes["DEFAULT"])');
        assert('is_object($this->links["DEFAULT"])');

        $this->imap = new HTMLImagemap('weathermap');

        $this->configfile = '';
        $this->imagefile = '';
        $this->imageuri = '';

        $this->loadAllPlugins();

        $this->scales['DEFAULT'] = new MapScale("DEFAULT", $this);
        $this->populateDefaultColours();

        MapUtility::wm_debug("WeatherMap class Reset() complete\n");
    }

    // Simple accessors to stop the editor from reaching inside objects quite so much

    public function getNode($name)
    {
        if (isset($this->nodes[$name])) {
            return $this->nodes[$name];
        }
        throw new WeathermapInternalFail("NoSuchNode");
    }

    public function addNode($newObject)
    {
        if ($this->nodeExists($newObject->name)) {
            throw new WeathermapInternalFail("NodeAlreadyExists");
        }
        $this->nodes[$newObject->name] = $newObject;
        $this->addItemToZLayer($newObject, $newObject->getZIndex());
    }

    public function getLink($name)
    {
        if (isset($this->links[$name])) {
            return $this->links[$name];
        }
        throw new WeathermapInternalFail("NoSuchLink");
    }

    public function addLink($newObject)
    {
        if ($this->linkExists($newObject->name)) {
            throw new WeathermapInternalFail("LinkAlreadyExists");
        }
        $this->links[$newObject->name] = $newObject;
        $this->addItemToZLayer($newObject, $newObject->getZIndex());
    }

    public function getScale($name)
    {
        if (isset($this->scales[$name])) {
            return $this->scales[$name];
        }
        MapUtility::wm_warn("Scale $name doesn't exist. Returning DEFAULT");
        return $this->scales['DEFAULT'];
    }


    private function populateDefaultColours()
    {
        MapUtility::wm_debug("Adding default map colour set.\n");
        $defaults = array(
            'KEYTEXT' => array('bottom' => -2, 'top' => -1, 'red' => 0, 'green' => 0, 'blue' => 0),
            'KEYOUTLINE' => array('bottom' => -2, 'top' => -1, 'red' => 0, 'green' => 0, 'blue' => 0),
            'KEYBG' => array('bottom' => -2, 'top' => -1, 'red' => 255, 'green' => 255, 'blue' => 255),
            'BG' => array('bottom' => -2, 'top' => -1, 'red' => 255, 'green' => 255, 'blue' => 255),
            'TITLE' => array('bottom' => -2, 'top' => -1, 'red' => 0, 'green' => 0, 'blue' => 0),
            'TIME' => array('bottom' => -2, 'top' => -1, 'red' => 0, 'green' => 0, 'blue' => 0)
        );

        foreach ($defaults as $key => $def) {
            $this->colourtable[$key] = new Colour($def['red'], $def['green'], $def['blue']);
        }
    }


    public function ProcessString($input, &$context, $include_notes = true, $multiline = false)
    {
        # debug("ProcessString: input is $input\n");

        if ($input === '') {
            return '';
        }

        // don't bother with all this regexp rubbish if there's nothing to match
        if (false === strpos($input, "{")) {
            return $input;
        }

        $the_item = null;

        assert('is_scalar($input)');

        $context_description = strtolower($context->my_type());
        if ($context_description != "map") {
            $context_description .= ":" . $context->name;
        }

        MapUtility::wm_debug("Trace: ProcessString($input, $context_description)\n");

        if ($multiline == true) {
            $input = str_replace("\\n", "\n", $input);
        }

        if ($context_description === 'node') {
            $input = str_replace("{node:this:graph_id}", $context->get_hint("graph_id"), $input);
            $input = str_replace("{node:this:name}", $context->name, $input);
        }

        if ($context_description === 'link') {
            $input = str_replace("{link:this:graph_id}", $context->get_hint("graph_id"), $input);
        }

        // check if we can now quit early before the regexp stuff
        if (false === strpos($input, "{")) {
            return $input;
        }

        $output = $input;

        while (preg_match('/(\{(?:node|map|link)[^}]+\})/', $input, $matches)) {
            $value = "[UNKNOWN]";
            $format = "";
            $key = $matches[1];
            MapUtility::wm_debug("ProcessString: working on " . $key . "\n");

            if (preg_match('/\{(node|map|link):([^}]+)\}/', $key, $matches)) {
                $type = $matches[1];
                $args = $matches[2];

                if ($type == 'map') {
                    $the_item = $this;
                    if (preg_match('/map:([^:]+):*([^:]*)/', $args, $matches)) {
                        $args = $matches[1];
                        $format = $matches[2];
                    }
                }

                if (($type == 'link') || ($type == 'node')) {
                    if (preg_match('/([^:]+):([^:]+):*([^:]*)/', $args, $matches)) {
                        $itemname = $matches[1];
                        $args = $matches[2];
                        $format = $matches[3];

                        #				debug("ProcessString: item is $itemname, and args are now $args\n");

                        $the_item = null;
                        if (($itemname == "this") && ($type == strtolower($context->my_type()))) {
                            $the_item = $context;
                        } elseif (strtolower($context->my_type()) == "link" && $type == 'node' && ($itemname == '_linkstart_' || $itemname == '_linkend_')) {
                            // this refers to the two nodes at either end of this link
                            if ($itemname == '_linkstart_') {
                                $the_item = $context->a;
                            }

                            if ($itemname == '_linkend_') {
                                $the_item = $context->b;
                            }
                        } elseif (($itemname == "parent") && ($type == strtolower($context->my_type())) && ($type == 'node') && ($context->relative_to != '')) {
                            $the_item = $this->nodes[$context->relative_to];
                        } else {
                            if (($type == 'link') && isset($this->links[$itemname])) {
                                $the_item = $this->links[$itemname];
                            }
                            if (($type == 'node') && isset($this->nodes[$itemname])) {
                                $the_item = $this->nodes[$itemname];
                            }
                        }
                    }
                }

                if (is_null($the_item)) {
                    MapUtility::wm_warn("ProcessString: $key refers to unknown item (context is $context_description) [WMWARN05]\n");
                } else {
                    MapUtility::wm_debug("ProcessString: Found appropriate item: " . get_class($the_item) . " " . $the_item->name . "\n");

                    // SET and notes have precedent over internal properties
                    // this is my laziness - it saves me having a list of reserved words
                    // which are currently used for internal props. You can just 'overwrite' any of them.
                    if (isset($the_item->hints[$args])) {
                        $value = $the_item->hints[$args];
                        MapUtility::wm_debug("ProcessString: used hint\n");
                    } elseif ($include_notes && isset($the_item->notes[$args])) {
                        // for some things, we don't want to allow notes to be considered.
                        // mainly - TARGET (which can define command-lines), shouldn't be
                        // able to get data from uncontrolled sources (i.e. data sources rather than SET in config files).
                        $value = $the_item->notes[$args];
                        MapUtility::wm_debug("ProcessString: used note\n");
                    } elseif (isset($the_item->$args)) {
                        $value = $the_item->$args;
                        MapUtility::wm_debug("ProcessString: used internal property\n");
                    }
                }
            }

            // format, and sanitise the value string here, before returning it
            if ($value === null) {
                $value = 'null';
            }
            MapUtility::wm_debug("ProcessString: replacing " . $key . " with $value\n");

            # if($format != '') $value = sprintf($format,$value);
            if ($format != '') {
                $value = StringUtility::sprintf($format, $value, $this->kilo);
            }

            $input = str_replace($key, '', $input);
            $output = str_replace($key, $value, $output);
        }
        return $output;
    }

    /**
     * @param resource $imageRef
     * @param int $font
     * @param Colour $colour
     * @param string $which
     */
    private function DrawTimestamp($imageRef, $font, $colour, $which = "")
    {
        // add a timestamp to the corner, so we can tell if it's all being updated

        $fontObject = $this->fonts->getFont($font);

        switch ($which) {
            case "MIN":
                $stamp = strftime($this->minstamptext, $this->min_data_time);
                $pos_x = $this->mintimex;
                $pos_y = $this->mintimey;
                break;
            case "MAX":
                $stamp = strftime($this->maxstamptext, $this->max_data_time);
                $pos_x = $this->maxtimex;
                $pos_y = $this->maxtimey;
                break;
            default:
                $stamp = $this->datestamp;
                $pos_x = $this->timex;
                $pos_y = $this->timey;
                break;
        }

        list($boxwidth, $boxheight) = $fontObject->calculateImageStringSize($stamp);

        $x = $this->width - $boxwidth;
        $y = $boxheight;

        if (($pos_x != 0) && ($pos_y != 0)) {
            $x = $pos_x;
            $y = $pos_y;
        }

        $fontObject->drawImageString($imageRef, $x, $y, $stamp, $colour->gdAllocate($imageRef));
        $areaname = $which . "TIMESTAMP";
        $this->imap->addArea("Rectangle", $areaname, '', array($x, $y, $x + $boxwidth, $y - $boxheight));
        $this->imap_areas[] = $areaname;
    }

    /**
     * @param resource $imageRef
     * @param int $font
     * @param Colour $colour
     */
    private function DrawTitle($imageRef, $font, $colour)
    {
        $fontObject = $this->fonts->getFont($font);
        $string = $this->ProcessString($this->title, $this);

        if ($this->get_hint('screenshot_mode') == 1) {
            $string = StringUtility::stringAnonymise($string);
        }

        list($boxwidth, $boxheight) = $fontObject->calculateImageStringSize($string);

        $x = 10;
        $y = $this->titley - $boxheight;

        if (($this->titlex >= 0) && ($this->titley >= 0)) {
            $x = $this->titlex;
            $y = $this->titley;
        }

        $fontObject->drawImageString($imageRef, $x, $y, $string, $colour->gdAllocate($imageRef));

        $this->imap->addArea("Rectangle", "TITLE", '', array($x, $y, $x + $boxwidth, $y - $boxheight));
        $this->imap_areas[] = 'TITLE';
    }


    /**
     * ReadConfig reads in either a file or part of a config and modifies the current map.
     *
     * @param $input string Either a filename or a fragment of config in a string
     * @return bool indicates success or failure     *
     *
     */
    public function ReadConfig($input)
    {
        $reader = new ConfigReader($this);

        // check if $input is more than one line. if it is, it's a text of a config file
        // if it isn't, it's the filename

        if ((strchr($input, "\n") != false) || (strchr($input, "\r") != false)) {
            MapUtility::wm_debug("ReadConfig Detected that this is a config fragment.\n");
            // strip out any Windows line-endings that have gotten in here
            $input = str_replace("\r", "", $input);
            $lines = explode("\n", $input);
            $filename = "{text insert}";

            $reader->readConfigLines($lines);
        } else {
            MapUtility::wm_debug("ReadConfig Detected that this is a config filename.\n");
            $reader->readConfigFile($input);
            $this->configfile = $input;
        }

        $this->postReadConfigTasks();

        return true;
    }

    private function postReadConfigTasks()
    {
        if ($this->has_overlibs && $this->htmlstyle == 'static') {
            MapUtility::wm_warn("OVERLIBGRAPH is used, but HTMLSTYLE is static. This is probably wrong. [WMWARN41]\n");
        }

        $this->populateDefaultScales();
        $this->replicateScaleSettings();
        $this->buildZLayers();
        $this->resolveRelativePositions();
        $this->updateMaxValues();

        $this->initialiseAllPlugins();
        $this->runProcessorPlugins("pre");
    }

    private function populateDefaultScales()
    {
        // load some default colouring, otherwise it all goes wrong

        $did_populate = $this->scales['DEFAULT']->populateDefaultsIfNecessary();

        if ($did_populate) {
            // we have a 0-0 line now, so we need to hide that.
            // (but respect the user's wishes if they defined a scale)
            $this->add_hint("key_hidezero_DEFAULT", 1);
        }

        $this->scales['none'] = new MapScale("none", $this);
    }

    /**
     * Temporary function to bridge between the old and new
     * scale-worlds. Just until the ConfigReader updates these
     * directly.
     */
    private function replicateScaleSettings()
    {
        foreach ($this->scales as $scaleName => $scaleObject) {
            // These are currently global settings for a map, not per-scale
            $scaleObject->keyoutlinecolour = $this->colourtable['KEYOUTLINE'];
            $scaleObject->keytextcolour = $this->colourtable['KEYTEXT'];
            $scaleObject->keybgcolour = $this->colourtable['KEYBG'];
            $scaleObject->keyfont = $this->fonts->getFont($this->keyfont);

            if (isset($this->keyx[$scaleName])) {
                $scaleObject->keypos = new Point($this->keyx[$scaleName], $this->keyy[$scaleName]);
                $scaleObject->keystyle = $this->keystyle[$scaleName];
                $scaleObject->keytitle = $this->keytext[$scaleName];
                if (isset($this->keysize[$scaleName])) {
                    $scaleObject->keysize = $this->keysize[$scaleName];
                }
            }
        }
    }


    private function buildZLayers()
    {
        MapUtility::wm_debug("Building cache of z-layers.\n");

        $allItems = $this->buildAllItemsList();

        foreach ($allItems as $item) {
            $zIndex = $item->getZIndex();
            $this->addItemToZLayer($item, $zIndex);
        }
        MapUtility::wm_debug("Found " . sizeof($this->seen_zlayers) . " z-layers including builtins (0,100).\n");
    }

    private function addItemToZLayer($item, $zIndex)
    {
        if (!isset($this->seen_zlayers[$zIndex]) || !is_array($this->seen_zlayers[$zIndex])) {
            $this->seen_zlayers[$zIndex] = array();
        }
        array_push($this->seen_zlayers[$zIndex], $item);
    }

    private function updateMaxValues()
    {
        MapUtility::wm_debug("Finalising bandwidth.\n");

        $allItems = $this->buildAllItemsList();

        foreach ($allItems as $item) {
            $item->updateMaxValues($this->kilo);
        }
    }

    private function resolveRelativePositions()
    {
        // calculate any relative positions here - that way, nothing else
        // really needs to know about them

        MapUtility::wm_debug("Resolving relative positions for NODEs...\n");
        // safety net for cyclic dependencies
        $maxIterations = 100;
        $iterations = $maxIterations;
        do {
            $nSkipped = 0;
            $nChanged = 0;

            foreach ($this->nodes as $node) {
                // if it's not relative, or already dealt with, skip to the next one
                if (!$node->isRelativePositioned() || $node->isRelativePositionResolved()) {
                    continue;
                }

                $anchorName = $node->getRelativeAnchor();

                MapUtility::wm_debug("Resolving relative position for $node to $anchorName\n");

                if (!$this->nodeExists($anchorName)) {
                    MapUtility::wm_warn("NODE " . $node->name . " has a relative position to an unknown node ($anchorName)! [WMWARN10]\n");
                    continue;
                }

                $anchorNode = $this->getNode($anchorName);
                MapUtility::wm_debug("Found anchor node: $anchorNode\n");

                // check if we are relative to another node which is in turn relative to something
                // we need to resolve that one before we can resolve this one!
                if (($anchorNode->isRelativePositioned()) && (!$anchorNode->isRelativePositionResolved())) {
                    MapUtility::wm_debug("Skipping unresolved relative_to. Let's hope it's not a circular one\n");
                    $nSkipped++;
                    continue;
                }

                if ($node->resolveRelativePosition($anchorNode)) {
                    $nChanged++;
                }
            }
            MapUtility::wm_debug("Relative Positions Cycle $iterations/$maxIterations - set $nChanged and Skipped $nSkipped for unresolved dependencies\n");
            $iterations--;
        } while (($nChanged > 0) && ($iterations > 0));

        if ($nSkipped > 0) {
            MapUtility::wm_warn("There are probably Circular dependencies in relative POSITION lines for $nSkipped nodes (or $maxIterations levels of relative positioning). [WMWARN11]\n");
        }
    }


    public function WriteDataFile($filename)
    {
        if ($filename == "") {
            return;
        }

        $fileHandle = fopen($filename, 'w');
        if (!$fileHandle) {
            return;
        }

        foreach ($this->nodes as $node) {
            if (!preg_match('/^::\s/', $node->name) && sizeof($node->targets) > 0) {
                fputs($fileHandle, sprintf("N_%s\t%f\t%f\r\n", $node->name, $node->absoluteUsages[IN], $node->absoluteUsages[OUT]));
            }
        }
        foreach ($this->links as $link) {
            if (!preg_match('/^::\s/', $link->name) && sizeof($link->targets) > 0) {
                fputs($fileHandle, sprintf("L_%s\t%f\t%f\r\n", $link->name, $link->absoluteUsages[IN], $link->absoluteUsages[OUT]));
            }
        }
        fclose($fileHandle);
    }

    private function getConfigForPosition($keyword, $fieldnames, $object1, $object2)
    {
        $write = false;
        $string = $keyword;

        for ($i = 0; $i < count($fieldnames); $i++) {
            $string .= " " . $object1->{$fieldnames[$i]};

            if ($object1->{$fieldnames[$i]} != $object2[$fieldnames[$i]]) {
                $write = true;
            }
        }
        $string .= "\n";

        if (!$write) {
            return "";
        }
        return $string;
    }


    public function getConfig()
    {
        global $WEATHERMAP_VERSION;

        $output = "";

        $output .= "# Automatically generated by php-weathermap v$WEATHERMAP_VERSION\n\n";

        $output .= $this->fonts->getConfig();
        $output .= "\n";

        $basic_params = array(
            array('title', 'TITLE', self::CONFIG_TYPE_LITERAL),
            array('width', 'WIDTH', self::CONFIG_TYPE_LITERAL),
            array('height', 'HEIGHT', self::CONFIG_TYPE_LITERAL),
            array('background', 'BACKGROUND', self::CONFIG_TYPE_LITERAL),
            array('htmlstyle', 'HTMLSTYLE', self::CONFIG_TYPE_LITERAL),
            array('kilo', 'KILO', self::CONFIG_TYPE_LITERAL),
            array('keyfont', 'KEYFONT', self::CONFIG_TYPE_LITERAL),
            array('timefont', 'TIMEFONT', self::CONFIG_TYPE_LITERAL),
            array('titlefont', 'TITLEFONT', self::CONFIG_TYPE_LITERAL),
            array('htmloutputfile', 'HTMLOUTPUTFILE', self::CONFIG_TYPE_LITERAL),
            array('dataoutputfile', 'DATAOUTPUTFILE', self::CONFIG_TYPE_LITERAL),
            array('htmlstylesheet', 'HTMLSTYLESHEET', self::CONFIG_TYPE_LITERAL),
            array('imageuri', 'IMAGEURI', self::CONFIG_TYPE_LITERAL),
            array('imageoutputfile', 'IMAGEOUTPUTFILE', self::CONFIG_TYPE_LITERAL)
        );

        foreach ($basic_params as $param) {
            $field = $param[0];
            $keyword = $param[1];

            if ($this->$field != $this->inherit_fieldlist[$field]) {
                if ($param[2] == self::CONFIG_TYPE_COLOR) {
                    $output .= "$keyword " . $this->$field->asConfig() . "\n";
                }
                if ($param[2] == self::CONFIG_TYPE_LITERAL) {
                    $output .= "$keyword " . $this->$field . "\n";
                }
            }
        }

        $output .= $this->getConfigForPosition("TIMEPOS", array("timex", "timey", "stamptext"), $this, $this->inherit_fieldlist);
        $output .= $this->getConfigForPosition("MINTIMEPOS", array("mintimex", "mintimey", "minstamptext"), $this, $this->inherit_fieldlist);
        $output .= $this->getConfigForPosition("MAXTIMEPOS", array("maxtimex", "maxtimey", "maxstamptext"), $this, $this->inherit_fieldlist);
        $output .= $this->getConfigForPosition("TITLEPOS", array("titlex", "titley"), $this, $this->inherit_fieldlist);

        $output .= "\n";

        foreach ($this->colourtable as $k => $colour) {
            $output .= sprintf("%sCOLOR %s\n", $k, $colour->asConfig());
        }
        $output .= "\n";

        foreach ($this->scales as $scale_name => $scale) {
            $output .= $scale->getConfig();
        }
        $output .= "\n";

        foreach ($this->hints as $hintname => $hint) {
            $output .= "SET $hintname $hint\n";
        }

        // this doesn't really work right, but let's try anyway
        if ($this->has_includes) {
            $output .= "\n# Included files\n";
            foreach ($this->included_files as $ifile) {
                $output .= "INCLUDE $ifile\n";
            }
        }

        $output .= "\n# End of global section\n\n";

        foreach (array("template", "normal") as $which) {
            if ($which == "template") {
                $output .= "\n# TEMPLATE-only NODEs:\n";
            }
            if ($which == "normal") {
                $output .= "\n# regular NODEs:\n";
            }

            foreach ($this->nodes as $node) {
                if (!preg_match('/^::\s/', $node->name)) {
                    if ($node->defined_in == $this->configfile) {
                        if ($which == "template" && $node->x === null) {
                            MapUtility::wm_debug("TEMPLATE\n");
                            $output .= $node->WriteConfig();
                        }
                        if ($which == "normal" && $node->x !== null) {
                            $output .= $node->WriteConfig();
                        }
                    }
                }
            }

            if ($which == "template") {
                $output .= "\n# TEMPLATE-only LINKs:\n";
            }

            if ($which == "normal") {
                $output .= "\n# regular LINKs:\n";
            }

            foreach ($this->links as $link) {
                if (!preg_match('/^::\s/', $link->name)) {
                    if ($link->defined_in == $this->configfile) {
                        if ($which == "template" && $link->a === null) {
                            $output .= $link->WriteConfig();
                        }
                        if ($which == "normal" && $link->a !== null) {
                            $output .= $link->WriteConfig();
                        }
                    }
                }
            }
        }

        $output .= "\n\n# That's All Folks!\n";

        return $output;
    }

    public function WriteConfig($filename)
    {
        $fileHandle = fopen($filename, "w");

        if ($fileHandle) {
            $output = $this->getConfig();
            fwrite($fileHandle, $output);
            fclose($fileHandle);
        } else {
            MapUtility::wm_warn("Couldn't open config file $filename for writing");
            return false;
        }

        return true;
    }

    /**
     * @return resource
     */
    protected function prepareOutputImage()
    {
        $bgImageRef = $this->loadBackgroundImage();

        $imageRef = imagecreatetruecolor($this->width, $this->height);

        if (!$imageRef) {
            MapUtility::wm_warn("Couldn't create output image in memory (" . $this->width . "x" . $this->height . ").");
        } else {
            imagealphablending($imageRef, true);
            if ($this->get_hint("antialias") == 1) {
                // Turn on anti-aliasing if it exists and it was requested
                if (function_exists("imageantialias")) {
                    imageantialias($imageRef, true);
                }
            }

            // by here, we should have a valid image handle
            $this->selected = ImageUtility::myimagecolorallocate($imageRef, 255, 0, 0); // for selections in the editor

            if ($bgImageRef) {
                imagecopy($imageRef, $bgImageRef, 0, 0, 0, 0, $this->width, $this->height);
                imagedestroy($bgImageRef);
            } else {
                // fill with background colour anyway, in case the background image failed to load
                imagefilledrectangle($imageRef, 0, 0, $this->width, $this->height, $this->colourtable['BG']->gdAllocate($imageRef));
            }
        }
        return $imageRef;
    }

    /**
     * @param $imageRef
     * @param $overlayColor
     */
    protected function drawRelativePositionOverlay($imageRef, $overlayColor)
    {
        foreach ($this->nodes as $node) {
            if ($node->relative_to != '') {
                $parentX = $this->nodes[$node->relative_to]->x;
                $parentY = $this->nodes[$node->relative_to]->y;
                imagearc(
                    $imageRef,
                    $node->x,
                    $node->y,
                    15,
                    15,
                    0,
                    360,
                    $overlayColor
                );
                imagearc(
                    $imageRef,
                    $node->x,
                    $node->y,
                    16,
                    16,
                    0,
                    360,
                    $overlayColor
                );

                imageline($imageRef, $node->x, $node->y, $parentX, $parentY, $overlayColor);
            }
        }
    }

    /**
     * @param $imageRef
     * @param $overlayColor
     */
    protected function drawViaOverlay($imageRef, $overlayColor)
    {
        foreach ($this->links as $link) {
            foreach ($link->vialist as $via) {
                if (isset($via[2])) {
                    $x = $this->nodes[$via[2]]->x + $via[0];
                    $y = $this->nodes[$via[2]]->y + $via[1];
                } else {
                    $x = $via[0];
                    $y = $via[1];
                }
                imagearc($imageRef, $x, $y, 10, 10, 0, 360, $overlayColor);
                imagearc($imageRef, $x, $y, 12, 12, 0, 360, $overlayColor);
            }
        }
    }

    protected function calculateDatestamp()
    {
        // if we're running tests, we force the time to a particular value,
        // so the output can be compared to a reference image more easily
        $testmode = intval($this->get_hint("testmode"));

        if ($testmode == 1) {
            $maptime = 1270813792;
            date_default_timezone_set('UTC');
        } else {
            $maptime = time();
        }
        $this->datestamp = strftime($this->stamptext, $maptime);
    }

    /**
     * @param $showVIAOverlay
     * @param $showRelativeOverlay
     * @param $imageRef
     */
    protected function drawEditorOverlays($showVIAOverlay, $showRelativeOverlay, $imageRef)
    {
        $overlayColor = myimagecolorallocate($imageRef, 200, 0, 0);

        if ($showRelativeOverlay) {
            // first, we can show relatively positioned NODEs
            $this->drawRelativePositionOverlay($imageRef, $overlayColor);
        }

        if ($showVIAOverlay) {
            // then overlay VIAs, so they can be seen
            $this->drawViaOverlay($imageRef, $overlayColor);
        }
    }

    /**
     * @param $imageFileName
     * @param $imageRef
     * @return bool
     */
    protected function writeImageFile($imageFileName, $imageRef)
    {
        $result = false;
        $functions = true;
        if (function_exists('imagejpeg') && preg_match('/\.jpg/i', $imageFileName)) {
            MapUtility::wm_debug("Writing JPEG file to $imageFileName\n");
            $result = imagejpeg($imageRef, $imageFileName);
        } elseif (function_exists('imagegif') && preg_match('/\.gif/i', $imageFileName)) {
            MapUtility::wm_debug("Writing GIF file to $imageFileName\n");
            $result = imagegif($imageRef, $imageFileName);
        } elseif (function_exists('imagepng') && preg_match('/\.png/i', $imageFileName)) {
            MapUtility::wm_debug("Writing PNG file to $imageFileName\n");
            $result = imagepng($imageRef, $imageFileName);
        } else {
            MapUtility::wm_warn("Failed to write map image. No function existed for the image format you requested. [WMWARN12]\n");
            $functions = false;
        }

        if (($result == false) && ($functions == true)) {
            if (file_exists($imageFileName)) {
                MapUtility::wm_warn("Failed to overwrite existing image file $imageFileName - permissions of existing file are wrong? [WMWARN13]");
            } else {
                MapUtility::wm_warn("Failed to create image file $imageFileName - permissions of output directory are wrong? [WMWARN14]");
            }
        }
        return $result;
    }

    /**
     * @param $thumbnailFileName
     * @param $thumbnailMaxSize
     * @param $imageRef
     */
    protected function createThumbnailFile($thumbnailFileName, $thumbnailMaxSize, $imageRef)
    {
        MapUtility::wm_debug("Writing thumbnail to %s\n", $thumbnailFileName);

        if (!function_exists('imagecopyresampled')) {
            MapUtility::wm_warn("Skipping thumbnail creation, since we don't have the necessary function. [WMWARN17]");
            return;
        }

        // if one is specified, and we can, write a thumbnail too
        if ($thumbnailFileName != '') {
            if ($this->width > $this->height) {
                $factor = ($thumbnailMaxSize / $this->width);
            } else {
                $factor = ($thumbnailMaxSize / $this->height);
            }

            $this->thumb_width = $this->width * $factor;
            $this->thumb_height = $this->height * $factor;

            $thumbImageRef = imagecreatetruecolor($this->thumb_width, $this->thumb_height);
            imagecopyresampled(
                $thumbImageRef,
                $imageRef,
                0,
                0,
                0,
                0,
                $this->thumb_width,
                $this->thumb_height,
                $this->width,
                $this->height
            );
            $result = imagepng($thumbImageRef, $thumbnailFileName);
            imagedestroy($thumbImageRef);

            if (($result == false)) {
                if (file_exists($thumbnailFileName)) {
                    MapUtility::wm_warn("Failed to overwrite existing image file $thumbnailFileName - permissions of existing file are wrong? [WMWARN15]");
                } else {
                    MapUtility::wm_warn("Failed to create image file $thumbnailFileName - permissions of output directory are wrong? [WMWARN16]");
                }
            }
        }
    }

    public function preCalculate()
    {
        MapUtility::wm_debug("preCalculating everything\n");

        $allMapItems = $this->buildAllItemsList();

        foreach ($allMapItems as $item) {
            $item->preCalculate($this);
        }
    }

    public function DrawMap($imageFileName = '', $thumbnailFileName = '', $thumbnailMaxSize = 250, $includeNodes = true, $showVIAOverlay = false, $showRelativeOverlay = false)
    {
        MapUtility::wm_debug("Trace: DrawMap()\n");

        MapUtility::wm_debug("=====================================\n");
        MapUtility::wm_debug("Start of Map Drawing\n");

        $this->calculateDatestamp();

        // Create an imageRef to draw into
        $imageRef = $this->prepareOutputImage();

        // Now it's time to draw a map

        // do the node rendering stuff first, regardless of where they are actually drawn.
        // this is so we can get the size of the nodes, which links will need if they use offsets
        // TODO - the geometry part should be in preCalculate()
        foreach ($this->nodes as $node) {
            MapUtility::wm_debug("Pre-rendering " . $node->name . " to get bounding boxes.\n");
            if (!$node->isTemplate()) {
                $node->preCalculate($this);
                $node->preRender($this);
            }
        }

        $this->preCalculate();

        $all_layers = array_keys($this->seen_zlayers);
        sort($all_layers);

        // stuff the scales into the seen-items list, so they are rendered along with everything else
        foreach ($this->scales as $scaleName => $scaleObject) {
            array_push($this->seen_zlayers[1000], $scaleObject);
        }

        foreach ($all_layers as $z) {
            $z_items = $this->seen_zlayers[$z];
            MapUtility::wm_debug("Drawing layer $z\n");
            // all the map 'furniture' is fixed at z=1000
            if ($z == 1000) {
                $this->DrawTimestamp($imageRef, $this->timefont, $this->colourtable['TIME']);
                if (!is_null($this->min_data_time)) {
                    $this->DrawTimestamp($imageRef, $this->timefont, $this->colourtable['TIME'], "MIN");
                    $this->DrawTimestamp($imageRef, $this->timefont, $this->colourtable['TIME'], "MAX");
                }
                $this->DrawTitle($imageRef, $this->titlefont, $this->colourtable['TITLE']);
            }

            if (is_array($z_items)) {
                /** @var WeatherMapDataItem $it */
                foreach ($z_items as $it) {
                    MapUtility::wm_debug("Drawing " . $it->my_type() . " " . $it->name . "\n");
                    $it->Draw($imageRef);
                }
            }
        }

        // for the editor, we can optionally overlay some other stuff
        if ($this->context == 'editor') {
            $this->drawEditorOverlays($showVIAOverlay, $showRelativeOverlay, $imageRef);
        }

        // Ready to output the results...

        if ($imageFileName == 'null') {
            // do nothing at all - we just wanted the HTML AREAs for the editor or HTML output
        } else {
            // write to the standard output (for the editor)
            if ($imageFileName == '') {
                imagepng($imageRef);
            } else {
                $this->writeImageFile($imageFileName, $imageRef);
                $this->createThumbnailFile($thumbnailFileName, $thumbnailMaxSize, $imageRef);
            }
        }

        imagedestroy($imageRef);
    }

    public function CleanUp()
    {
        global $weathermap_error_suppress;

        parent::cleanUp();

        $all_layers = array_keys($this->seen_zlayers);

        foreach ($all_layers as $z) {
            $this->seen_zlayers[$z] = null;
        }

        foreach ($this->links as $link) {
            $link->cleanUp();
            unset($link);
        }

        foreach ($this->nodes as $node) {
            $node->cleanUp();
            unset($node);
        }

        // Clear up the other random hashes of information
        $this->dsinfocache = null;
        $this->colourtable = null;
        $this->scales = null;
        $weathermap_error_suppress = array();
    }

    public function calculateImagemap()
    {
        MapUtility::wm_debug("Trace: calculateImagemap()\n");

        // loop through everything. Figure out along the way if it's a node or a link
        $allItems = $this->buildAllItemsList();

        foreach ($allItems as $mapItem) {
            $type = $mapItem->my_type();
            # $prefix = substr($type, 0, 1);

            $dirs = array();
            if ($type == 'LINK') {
                $dirs = array(IN => array(0, 2), OUT => array(1, 3));
            }
            if ($type == 'NODE') {
                $dirs = array(IN => array(0, 1, 2, 3));
            }

            // check to see if any of the relevant things have a value
            $change = "";
            foreach ($dirs as $d => $parts) {
                $change .= join('', $mapItem->overliburl[$d]);
                $change .= $mapItem->notestext[$d];
            }
            // skip all this if it's a template node
            if ($mapItem->isTemplate()) {
                $change = "";
            }

            if ($this->htmlstyle == "overlib") {
                if ($change != '') {
                    // find the middle of the map
                    $center_x = $this->width / 2;
                    $center_y = $this->height / 2;

                    $type = $mapItem->my_type();

                    if ($type == 'NODE') {
                        $mid_x = $mapItem->x;
                        $mid_y = $mapItem->y;
                    }
                    if ($type == 'LINK') {
                        $a_x = $this->nodes[$mapItem->a->name]->x;
                        $a_y = $this->nodes[$mapItem->a->name]->y;

                        $b_x = $this->nodes[$mapItem->b->name]->x;
                        $b_y = $this->nodes[$mapItem->b->name]->y;

                        $mid_x = ($a_x + $b_x) / 2;
                        $mid_y = ($a_y + $b_y) / 2;
                    }
                    $left = "";
                    $above = "";
                    $img_extra = "";

                    if ($mapItem->overlibwidth != 0) {
                        $left = "WIDTH," . $mapItem->overlibwidth . ",";
                        $img_extra .= " WIDTH=$mapItem->overlibwidth";

                        if ($mid_x > $center_x) {
                            $left .= "LEFT,";
                        }
                    }

                    if ($mapItem->overlibheight != 0) {
                        $above = "HEIGHT," . $mapItem->overlibheight . ",";
                        $img_extra .= " HEIGHT=$mapItem->overlibheight";

                        if ($mid_y > $center_y) {
                            $above .= "ABOVE,";
                        }
                    }

                    foreach ($dirs as $dir => $parts) {
                        $caption = ($mapItem->overlibcaption[$dir] != '' ? $mapItem->overlibcaption[$dir] : $mapItem->name);
                        $caption = $this->ProcessString($caption, $mapItem);

                        $overlibhtml = "onmouseover=\"return overlib('";

                        $n = 0;
                        if (sizeof($mapItem->overliburl[$dir]) > 0) {
                            // print "ARRAY:".is_array($link->overliburl[$dir])."\n";
                            foreach ($mapItem->overliburl[$dir] as $url) {
                                if ($n > 0) {
                                    $overlibhtml .= '&lt;br /&gt;';
                                }
                                $overlibhtml .= "&lt;img $img_extra src=" . $this->ProcessString($url, $mapItem) . "&gt;";
                                $n++;
                            }
                        }
                        # print "Added $n for $dir\n";
                        if (trim($mapItem->notestext[$dir]) != '') {
                            # put in a linebreak if there was an image AND notes
                            if ($n > 0) {
                                $overlibhtml .= '&lt;br /&gt;';
                            }
                            $note = $this->ProcessString($mapItem->notestext[$dir], $mapItem);
                            $note = htmlspecialchars($note, ENT_NOQUOTES);
                            $note = str_replace("'", "\\&apos;", $note);
                            $note = str_replace('"', "&quot;", $note);
                            $overlibhtml .= $note;
                        }
                        $overlibhtml .= "',DELAY,250,${left}${above}CAPTION,'" . $caption
                            . "');\"  onmouseout=\"return nd();\"";

                        foreach ($mapItem->imap_areas as $area) {
                            $area->extrahtml = $overlibhtml;
                        }
                    }
                } // if change
            } // overlib?

            // now look at infourls
            foreach ($dirs as $dir => $parts) {
                foreach ($parts as $part) {
                    # $areaname = $type . ":" . $prefix . $mapItem->id . ":" . $part;

                    if (($this->htmlstyle != 'editor') && ($mapItem->infourl[$dir] != '')) {
                        foreach ($mapItem->imap_areas as $area) {
                            $area->href = $this->ProcessString($mapItem->infourl[$dir], $mapItem);
                        }
                    }
                }
            }
        }
    }

    public function asJS()
    {
        $js = '';

        $js .= "var Links = new Array();\n";
        $js .= "var LinkIDs = new Array();\n";

        foreach ($this->links as $link) {
            $js .= $link->asJS();
        }

        $js .= "var Nodes = new Array();\n";
        $js .= "var NodeIDs = new Array();\n";

        foreach ($this->nodes as $node) {
            $js .= $node->asJS();
        }

        return $js;
    }


    // This method MUST run *after* DrawMap. It relies on DrawMap to call the map-drawing bits
    // which will populate the Imagemap with regions.
    //
    // imagemapname is a parameter, so we can stack up several maps in the Cacti plugin with their own imagemaps
    public function MakeHTML($imagemapname = "weathermap_imap")
    {
        MapUtility::wm_debug("Trace: MakeHTML()\n");
        // PreloadMapHTML fills in the Imagemap info, ready for the HTML to be created.
        $this->calculateImagemap();

        $html = '';

        $html .= '<div class="weathermapimage" style="margin-left: auto; margin-right: auto; width: ' . $this->width . 'px;" >';
        if ($this->imageuri != '') {
            $html .= sprintf(
                '<img id="wmapimage" src="%s" width="%d" height="%d" border="0" usemap="#%s"',
                $this->imageuri,
                $this->width,
                $this->height,
                $imagemapname
            );
            $html .= '/>';
        } else {
            $html .= sprintf(
                '<img id="wmapimage" src="%s" width="%d" height="%d" border="0" usemap="#%s"',
                $this->imagefile,
                $this->width,
                $this->height,
                $imagemapname
            );
            $html .= '/>';
        }
        $html .= '</div>';

        $html .= $this->generateSortedImagemap($imagemapname);

        return $html;
    }

    public function generateSortedImagemap($imagemapname)
    {
        $html = "\n<map name=\"" . $imagemapname . '" id="' . $imagemapname . "\">\n";

        $all_layers = array_keys($this->seen_zlayers);
        rsort($all_layers);

        MapUtility::wm_debug("Starting to dump imagemap in reverse Z-order...\n");
        foreach ($all_layers as $z) {
            MapUtility::wm_debug("Writing HTML for layer $z\n");
            $z_items = $this->seen_zlayers[$z];
            if (is_array($z_items)) {
                MapUtility::wm_debug("   Found things for layer $z\n");

                // at z=1000, the legends and timestamps live
                if ($z == 1000) {
                    MapUtility::wm_debug("     Builtins fit here.\n");

                    foreach ($this->imap_areas as $areaname) {
                        // skip the linkless areas if we are in the editor - they're redundant
                        $html .= "\t" . $this->imap->exactHTML($areaname, true, ($this->context
                                != 'editor'));
                        $html .= "\n";
                    }

                    foreach ($this->scales as $it) {
                        foreach ($it->getImagemapAreas() as $area) {
                            MapUtility::wm_debug("$area\n");
                            // skip the linkless areas if we are in the editor - they're redundant
                            $html .= "\t" . $area->asHTML();
                            $html .= "\n";
                        }
                        $html .= "\n";
                    }
                }

                // we reverse the array for each zlayer so that the imagemap order
                // will match up with the draw order (last drawn should be first hit)
                /** @var WeatherMapDataItem $it */
                foreach (array_reverse($z_items) as $it) {
                    if ($it->name != 'DEFAULT' && $it->name != ":: DEFAULT ::") {
                        foreach ($it->getImagemapAreas() as $area) {
                            MapUtility::wm_debug("$area\n");
                            // skip the linkless areas if we are in the editor - they're redundant
                            $html .= "\t" . $area->asHTML();
                            $html .= "\n";
                        }
                        $html .= "\n";
                    }
                }
            }
        }

        $html .= '</map>';

        return $html;
    }

    public function nodeExists($nodeName)
    {
        return array_key_exists($nodeName, $this->nodes);
    }

    public function linkExists($linkName)
    {
        return array_key_exists($linkName, $this->links);
    }

    /**
     * Create an array of all the nodes and links, mixed together.
     * readData() makes several passes through this list.
     *
     * @return array
     */
    public function buildAllItemsList()
    {
        // TODO - this should probably be a static, or otherwise cached
        $allItems = array();

        $listOfItemLists = array(&$this->nodes, &$this->links);
        reset($listOfItemLists);

        while (list($outerListCount,) = each($listOfItemLists)) {
            unset($itemList);
            $itemList = &$listOfItemLists[$outerListCount];

            reset($itemList);
            while (list($innerListCount,) = each($itemList)) {
                unset($oneMapItem);
                $oneMapItem = &$itemList[$innerListCount];
                $allItems [] = $oneMapItem;
            }
        }
        return $allItems;
    }


    /**
     * For each mapitem, loop through all its targets and find a plugin
     * that recognises them. Then register the target with the plugin
     * so that it can potentially pre-fetch or optimise in some way.
     *
     * @param $itemList
     */
    private function preProcessTargets($itemList)
    {
        MapUtility::wm_debug("Preprocessing targets\n");

        /** @var WeatherMapDataItem $mapItem */
        foreach ($itemList as $mapItem) {
            if ($mapItem->isTemplate()) {
                continue;
            }

            $mapItem->prepareForDataCollection();
        }
    }

    /**
     * Keep track of the current minimum and maximum timestamp for collected data
     *
     * @param $dataTime
     */
    public function registerDataTime($dataTime)
    {
        if ($dataTime == 0) {
            return;
        }

        if ($this->max_data_time == null || $dataTime > $this->max_data_time) {
            $this->max_data_time = $dataTime;
        }

        if ($this->min_data_time == null || $dataTime < $this->min_data_time) {
            $this->min_data_time = $dataTime;
        }
        MapUtility::wm_debug("Current DataTime MINMAX: " . $this->min_data_time . " -> " . $this->max_data_time . "\n");
    }

    private function readDataFromTargets($itemList)
    {
        MapUtility::wm_debug("======================================\n");
        MapUtility::wm_debug("Starting main collection loop\n");

        /** @var WeatherMapDataItem $mapItem */
        foreach ($itemList as $mapItem) {
            if ($mapItem->isTemplate()) {
                MapUtility::wm_debug("ReadData: Skipping $mapItem that looks like a template\n.");
                continue;
            }

            $mapItem->performDataCollection();

            // NOTE - this part still happens even if there were no targets
            $mapItem->aggregateDataResults();
            $mapItem->calculateScaleColours();

            unset($mapItem);
        }
    }

    private function loadAllPlugins()
    {
        $plugin_root = dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'Plugins';

        $this->loadPlugins('data', $plugin_root . DIRECTORY_SEPARATOR . 'Datasources');
        $this->loadPlugins('pre', $plugin_root . DIRECTORY_SEPARATOR . 'Pre');
        $this->loadPlugins('post', $plugin_root . DIRECTORY_SEPARATOR . 'Post');
    }

    /**
     * Search a directory for plugin class files, and load them. Each one is then
     * instantiated once, and saved into the map object.
     *
     * @param string $pluginType - Which kind of plugin are we loading?
     * @param string $searchDirectory - Where to load from?
     */
    private function loadPlugins($pluginType = "data", $searchDirectory = "lib/datasources")
    {
        $type_to_namespace = array(
            'data' => 'Datasources',
            'pre' => 'Pre',
            'post' => 'Post'
        );

        MapUtility::wm_debug("Beginning to load $pluginType plugins from $searchDirectory\n");

        $pluginList = $this->getPluginFileList($pluginType, $searchDirectory);

        foreach ($pluginList as $fullFilePath => $file) {
            MapUtility::wm_debug("Loading $pluginType Plugin class from $file\n");

            $class = preg_replace("/\\.php$/", "", $file);
            // include_once $fullFilePath;

            MapUtility::wm_debug("Loaded $pluginType Plugin class $class from $file\n");

            $class_fullpath = '\\Weathermap\\Plugins\\' . $type_to_namespace[$pluginType] . '\\' . $class;

            MapUtility::wm_debug("full class path is $class_fullpath");

            $this->plugins[$pluginType][$class]['object'] = new $class_fullpath;
            $this->plugins[$pluginType][$class]['active'] = true;

            if (!isset($this->plugins[$pluginType][$class])) {
                MapUtility::wm_debug("** Failed to create an object for plugin $pluginType/$class\n");
                $this->plugins[$pluginType][$class]['active'] = false;
            }
        }
        MapUtility::wm_debug("Finished loading plugins.\n");
    }

    /**
     * @param $pluginType
     * @param $searchDirectory
     * @return array
     */
    private function getPluginFileList($pluginType, $searchDirectory)
    {
        $directoryHandle = $this->resolveDirectoryAndOpen($searchDirectory);

        $pluginList = array();
        if (!$directoryHandle) {
            MapUtility::wm_warn("Couldn't open $pluginType Plugin directory ($searchDirectory). Things will probably go wrong. [WMWARN06]\n");
        }

        while ($file = readdir($directoryHandle)) {
            $fullFilePath = $searchDirectory . DIRECTORY_SEPARATOR . $file;

            if (!is_file($fullFilePath) || !preg_match('/\.php$/', $fullFilePath) || !preg_match('/^WeatherMap/', $file)) {
                continue;
            }

            $pluginList[$fullFilePath] = $file;
        }
        return $pluginList;
    }

    private function resolveDirectoryAndOpen($dir)
    {
        if (!file_exists($dir)) {
            $dir = dirname(__FILE__) . DIRECTORY_SEPARATOR . $dir;
            MapUtility::wm_debug("Relative path didn't exist. Trying $dir\n");
        }
        $directoryHandle = @opendir($dir);

        // XXX - is this ever necessary?
        if (!$directoryHandle) { // try to find it with the script, if the relative path fails
            $srcdir = substr($_SERVER['argv'][0], 0, strrpos($_SERVER['argv'][0], DIRECTORY_SEPARATOR));
            $directoryHandle = opendir($srcdir . DIRECTORY_SEPARATOR . $dir);
            if ($directoryHandle) {
                $dir = $srcdir . DIRECTORY_SEPARATOR . $dir;
            }
        }

        return $directoryHandle;
    }


    /**
     * Loop through the datasource plugins, allowing them to initialise any internals.
     * The plugins can also refuse to run, if resources they need aren't available.
     */
    private function initialiseAllPlugins()
    {
        MapUtility::wm_debug("Running Init() for all Plugins...\n");

        foreach (array('data', 'pre', 'post') as $type) {
            MapUtility::wm_debug("Initialising $type Plugins...\n");

            foreach ($this->plugins[$type] as $name => $pluginEntry) {
                MapUtility::wm_debug("Running $name" . "->Init()\n");

                $ret = $pluginEntry['object']->Init($this);

                if (!$ret) {
                    MapUtility::wm_debug("Marking $name plugin as inactive, since Init() failed\n");
                    $this->plugins[$type][$name]['active'] = false;
                    MapUtility::wm_debug("State is now %s\n", ($this->plugins['data'][$name]['active'] ? "active" : "inactive"));
                }
            }
        }
        MapUtility::wm_debug("Finished Initialising Plugins...\n");
    }

    public function runProcessorPlugins($stage = "pre")
    {
        MapUtility::wm_debug("Running $stage-processing plugins...\n");

        $this->pluginMethod($stage, "run");

        MapUtility::wm_debug("Finished $stage-processing plugins...\n");
    }


    private function prefetchPlugins()
    {
        // give all the plugins a chance to prefetch their results
        MapUtility::wm_debug("======================================\n");
        MapUtility::wm_debug("Starting DS plugin prefetch\n");
        $this->pluginMethod("data", "Prefetch");
    }

    private function pluginMethod($type, $method)
    {
        MapUtility::wm_debug("======================================\n");
        MapUtility::wm_debug("Running $type plugin $method method\n");

        foreach ($this->plugins[$type] as $name => $pluginEntry) {
            if ($pluginEntry['active']) {
                MapUtility::wm_debug("Running $name->$method()\n");
                $pluginEntry['object']->$method($this);
            }
        }
    }

    private function cleanupPlugins($type)
    {
        MapUtility::wm_debug("======================================\n");
        MapUtility::wm_debug("Starting DS plugin cleanup\n");
        $this->pluginMethod($type, "CleanUp");
    }

    public function randomData()
    {
        foreach ($this->links as $link) {
            $this->links[$link->name]->absoluteUsages[IN] = rand(0, $link->maxValues[IN]);
            $this->links[$link->name]->absoluteUsages[OUT] = rand(0, $link->maxValues[OUT]);
        }
    }

    public function zeroData()
    {
        $allMapItems = $this->buildAllItemsList();

        foreach ($allMapItems as $mapItem) {
            if ($mapItem->isTemplate()) {
                MapUtility::wm_debug("zeroData: Skipping $mapItem that looks like a template\n.");
                continue;
            }

            $mapItem->zeroData();

            $mapItem->aggregateDataResults();
            $mapItem->calculateScaleColours();

            unset($mapItem);
        }
    }

    public function readData()
    {
        // we skip readdata completely in sizedebug mode
        if ($this->sizedebug != 0) {
            MapUtility::wm_debug("Size Debugging is on. Skipping readData.\n");
            return;
        }

        MapUtility::wm_debug("======================================\n");
        MapUtility::wm_debug("ReadData: Updating link data for all links and nodes\n");

        $allMapItems = $this->buildAllItemsList();

        // $this->initialiseAllPlugins();

        // process all the targets and find a plugin for them
        $this->preProcessTargets($allMapItems);

        $this->prefetchPlugins();

        $this->readDataFromTargets($allMapItems);

        $this->cleanupPlugins('data');

        $this->runProcessorPlugins("post");

        MapUtility::wm_debug("ReadData Completed.\n");
        MapUtility::wm_debug("------------------------------\n");
    }

    public function createDefaultNodes()
    {
        MapUtility::wm_debug("Creating ':: DEFAULT ::' DEFAULT NODE\n");
        $this->addNode(new MapNode(":: DEFAULT ::", ":: DEFAULT ::", $this));

        MapUtility::wm_debug("Creating actual DEFAULT NODE from :: DEFAULT ::\n");
        $this->addNode(new MapNode("DEFAULT", ":: DEFAULT ::", $this));
    }

    public function createDefaultLinks()
    {
        // these are the default defaults
        // by putting them into a normal object, we can use the
        // same code for writing out LINK DEFAULT as any other link.
        MapUtility::wm_debug("Creating ':: DEFAULT ::' DEFAULT LINK\n");
        // these two are used for default settings
        $this->addLink(new MapLink(":: DEFAULT ::", ":: DEFAULT ::", $this));

        MapUtility::wm_debug("Creating actual DEFAULT LINK from :: DEFAULT ::\n");
        $this->addLink(new MapLink("DEFAULT", ":: DEFAULT ::", $this));
    }

    public function getValue($name)
    {
        MapUtility::wm_debug("Fetching %s\n", $name);
        if (property_exists($this, $name)) {
            return $this->$name;
        }
        throw new WeathermapInternalFail("NoSuchProperty");
    }

    /**
     * @return null|resource
     */
    protected function loadBackgroundImage()
    {
        $bgImageRef = null;

        // do the basic prep work
        if ($this->background != '') {
            if (is_readable($this->background)) {
                $bgImageRef = imagecreatefromfile($this->background);

                if (!$bgImageRef) {
                    MapUtility::wm_warn(
                        "Failed to open background image.  One possible reason: Is your BACKGROUND really a PNG?\n"
                    );
                    return $bgImageRef;
                }

                $this->width = imagesx($bgImageRef);
                $this->height = imagesy($bgImageRef);

                return $bgImageRef;
            }

            MapUtility::wm_warn(
                "Your background image file could not be read. Check the filename, and permissions, for "
                . $this->background . "\n"
            );
        }

        return $bgImageRef;
    }

    public function asConfigData()
    {
        $conf = array();

        $conf['vars'] = $this->hints;
        $conf['fonts'] = $this->fonts->asConfigData();

        // title font, pos
        // time font, pos

        return $conf;
    }

    public function getJSONConfig()
    {
        $conf = array(
            "global" => $this->asConfigData(),
            "scales" => array(),
            "nodes" => array(),
            "links" => array()
        );

        foreach ($this->scales as $scale) {
            $conf['scales'][$scale->name] = $scale->asConfigData();
        }

        foreach ($this->nodes as $node) {
            $conf['nodes'][$node->name] = $node->asConfigData();
        }

        foreach ($this->links as $link) {
            $conf['links'][$link->name] = $link->asConfigData();
        }

        return json_encode($conf, JSON_PRETTY_PRINT);
    }
}
// vim:ts=4:sw=4:
