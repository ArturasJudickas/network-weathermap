<?php
// PHP Weathermap 0.98
// Copyright Howard Jones, 2005-2016 howie@thingy.com
// http://www.network-weathermap.com/
// Released under the GNU Public License


namespace Weathermap\Core;


class MapLink extends MapDataItem
{
    const FMT_BITS_IN = "{link:this:bandwidth_in:%2k}";
    const FMT_BITS_OUT = "{link:this:bandwidth_out:%2k}";
    const FMT_UNFORM_IN = "{link:this:bandwidth_in}";
    const FMT_UNFORM_OUT = "{link:this:bandwidth_out}";
    const FMT_PERC_IN = "{link:this:inpercent:%.2f}%";
    const FMT_PERC_OUT = "{link:this:outpercent:%.2f}%";


//	public $maphtml;
    /** @var  MapNode $a */
    /** @var  MapNode $b */
    public $a;
    public $b; // the ends - references to nodes
    public $a_offset;
    public $b_offset;
    public $a_offset_dx;
    public $a_offset_dy;
    public $a_offset_resolved;
    public $b_offset_dx;
    public $b_offset_dy;
    public $b_offset_resolved;

    public $labeloffset_in;
    public $labeloffset_out;
    public $commentoffset_in;
    public $commentoffset_out;

    public $width;
    public $arrowstyle;
    public $linkstyle;
    public $labelstyle;
    public $labelboxstyle;
    public $selected; //TODO - can a link even BE selected?
    public $vialist = array();
    public $viastyle;
    public $commentstyle;
    public $splitpos;

    public $bwfont;
    public $commentfont;

    /** @var Colour $outlinecolour */
    public $outlinecolour;
    /** @var  Colour $bwoutlinecolour */
    public $bwoutlinecolour;
    /** @var  Colour $bwboxcolour */
    public $bwboxcolour;
    /** @var  Colour $commentfontcolour */
    public $commentfontcolour;
    /** @var  Colour $bwfontcolour */
    public $bwfontcolour;

    public $bwlabelformats = array();
    public $comments = array();

    /** @var  LinkGeometry $geometry */
    public $geometry;  // contains all the spine-related data (WMLinkGeometry)

    /**
     * WeatherMapLink constructor.
     * @param string $name
     * @param string $template
     * @param Map $owner
     */
    public function __construct($name, $template, $owner)
    {
        parent::__construct();

        $this->name = $name;
        $this->owner = $owner;
        $this->template = $template;

        $this->inherit_fieldlist = array(
            'my_default' => null,
            'width' => 7,
            'commentfont' => 1,
            'bwfont' => 2,
            'template' => ':: DEFAULT ::',
            'splitpos' => 50,
            'labeloffset_out' => 25,
            'labeloffset_in' => 75,
            'commentoffset_out' => 5,
            'commentoffset_in' => 95,
            'commentstyle' => 'edge',
            'arrowstyle' => 'classic',
            'viastyle' => 'curved',
            'usescale' => 'DEFAULT',
            'scaletype' => 'percent',
            'targets' => array(),
            'duplex' => 'full',
            'infourl' => array('', ''),
            'notes' => array(),
            'hints' => array(),
            'comments' => array('', ''),
            'bwlabelformats' => array(self::FMT_PERC_IN, self::FMT_PERC_OUT),
            'overliburl' => array(array(), array()),
            'notestext' => array(IN => '', OUT => ''),
            'maxValuesConfigured' => array(IN => "100M", OUT => "100M"),
            'maxValues' => array(IN => null, OUT => null),
            'labelstyle' => 'percent',
            'labelboxstyle' => 'classic',
            'linkstyle' => 'twoway',
            'overlibwidth' => 0,
            'overlibheight' => 0,
            'outlinecolour' => new Colour(0, 0, 0),
            'bwoutlinecolour' => new Colour(0, 0, 0),
            'bwfontcolour' => new Colour(0, 0, 0),
            'bwboxcolour' => new Colour(255, 255, 255),
            'commentfontcolour' => new Colour(192, 192, 192),
            'inscalekey' => '',
            'outscalekey' => '',
            'a_offset' => 'C',
            'b_offset' => 'C',
            'a_offset_dx' => 0,
            'a_offset_dy' => 0,
            'b_offset_dx' => 0,
            'b_offset_dy' => 0,
            'a_offset_resolved' => false,
            'b_offset_resolved' => false,
            'zorder' => 300,
            'overlibcaption' => array('', '')
        );

        $this->reset($owner);
    }

    public function my_type()
    {
        return "LINK";
    }

    public function getTemplateObject()
    {
        return $this->owner->getLink($this->template);
    }

    public function isTemplate()
    {
        return !isset($this->a);
    }

    private function getDirectionList()
    {
        if ($this->linkstyle == "oneway") {
            return array(OUT);
        }

        return array(IN, OUT);
    }

    private function drawComments($gdImage)
    {
        wm_debug("Link " . $this->name . ": Drawing comments.\n");

        $directions = $this->getDirectionList();
        $commentPositions = array();

        $commentColours = array();
        $gdCommentColours = array();

        $commentPositions[OUT] = $this->commentoffset_out;
        $commentPositions[IN] = $this->commentoffset_in;

        $widthList = $this->geometry->getWidths();

        $fontObject = $this->owner->fonts->getFont($this->commentfont);

        foreach ($directions as $direction) {
            wm_debug("Link " . $this->name . ": Drawing comments for direction $direction\n");

            $widthList[$direction] *= 1.1;

            // Time to deal with Link Comments, if any
            $comment = $this->owner->ProcessString($this->comments[$direction], $this);

            if ($this->owner->get_hint('screenshot_mode') == 1) {
                $comment = Utility::stringAnonymise($comment);
            }

            if ($comment == '') {
                wm_debug("Link " . $this->name . " no text for direction $direction\n");
                break;
            }

            $commentColours[$direction] = $this->commentfontcolour;

            if ($this->commentfontcolour->isContrast()) {
                $commentColours[$direction] = $this->colours[$direction]->getContrastingColour();
            }

            $gdCommentColours[$direction] = $commentColours[$direction]->gdAllocate($gdImage);

            # list($textWidth, $textHeight) = $this->owner->myimagestringsize($this->commentfont, $comment);
            list($textWidth, $textHeight) = $fontObject->calculateImageStringSize($comment);

            // nudge pushes the comment out along the link arrow a little bit
            // (otherwise there are more problems with text disappearing underneath links)
            $nudgeAlong = intval($this->get_hint("comment_nudgealong"));
            $nudgeOut = intval($this->get_hint("comment_nudgeout"));

            /** @var WMPoint $position */
            list ($position, $comment_index, $angle, $distance) = $this->geometry->findPointAndAngleAtPercentageDistance($commentPositions[$direction]);

            $tangent = $this->geometry->findTangentAtIndex($comment_index);
            $tangent->normalise();

            $centreDistance = $widthList[$direction] + 4 + $nudgeOut;

            if ($this->commentstyle == 'center') {
                $centreDistance = $nudgeOut - ($textHeight / 2);
            }
            // find the normal to our link, so we can get outside the arrow
            $normal = $tangent->getNormal();

            $flipped = false;

            $edge = $position;

            // if the text will be upside-down, rotate it, flip it, and right-justify it
            // not quite as catchy as Missy's version
            if (abs($angle) > 90) {
                $angle -= 180;
                if ($angle < -180) {
                    $angle += 360;
                }
                $edge->addVector($tangent, $nudgeAlong);
                $edge->addVector($normal, -$centreDistance);
                $flipped = true;
            } else {
                $edge->addVector($tangent, $nudgeAlong);
                $edge->addVector($normal, $centreDistance);
            }

            $maxLength = $this->geometry->totalDistance();

            if (!$flipped && ($distance + $textWidth) > $maxLength) {
                $edge->addVector($tangent, -$textWidth);
            }

            if ($flipped && ($distance - $textWidth) < 0) {
                $edge->addVector($tangent, $textWidth);
            }

            wm_debug("Link " . $this->name . " writing $comment at $edge and angle $angle for direction $direction\n");

            // FINALLY, draw the text!
            $fontObject->drawImageString($gdImage, $edge->x, $edge->y, $comment, $gdCommentColours[$direction], $angle);
        }
    }

    /***
     * @param Map $map
     * @throws WeathermapInternalFail
     */
    public function preCalculate(&$map)
    {
        wm_debug("Link " . $this->name . ": Calculating geometry.\n");

        // don't bother doing anything if it's a template
        if ($this->isTemplate()) {
            return;
        }

        $points = array();

        wm_debug("Offsets are %s and %s\n", $this->a_offset, $this->b_offset);
        wm_debug("A node is %sx%s\n", $this->a->width, $this->a->height);

        if ($this->a_offset_dx != 0 || $this->a_offset_dy != 0) {
            wm_debug("Using offsets from earlier\n");
            $dx = $this->a_offset_dx;
            $dy = $this->a_offset_dy;
        } else {
            list($dx, $dy) = Utility::calculateOffset($this->a_offset, $this->a->width, $this->a->height);
        }

        wm_debug("A offset: $dx, $dy\n");
        $points[] = new WMPoint($this->a->x + $dx, $this->a->y + $dy);

        wm_debug("POINTS SO FAR:" . join(" ", $points) . "\n");

        foreach ($this->vialist as $via) {
            wm_debug("VIALIST...\n");
            // if the via has a third element, the first two are relative to that node
            if (isset($via[2])) {
                $relativeTo = $map->getNode($via[2]);
                wm_debug("Relative to $relativeTo\n");
                $point = new WMPoint($relativeTo->x + $via[0], $relativeTo->y + $via[1]);
            } else {
                $point = new WMPoint($via[0], $via[1]);
            }
            wm_debug("Adding $point\n");
            $points[] = $point;
        }
        wm_debug("POINTS SO FAR:" . join(" ", $points) . "\n");

        wm_debug("B node is %sx%s\n", $this->b->width, $this->b->height);
        if ($this->b_offset_dx != 0 || $this->b_offset_dy != 0) {
            wm_debug("Using offsets from earlier\n");
            $dx = $this->b_offset_dx;
            $dy = $this->b_offset_dy;
        } else {
            list($dx, $dy) = Utility::calculateOffset($this->b_offset, $this->b->width, $this->b->height);
        }
        wm_debug("B offset: $dx, $dy\n");
        $points[] = new WMPoint($this->b->x + $dx, $this->b->y + $dy);

        wm_debug("POINTS SO FAR:" . join(" ", $points) . "\n");

        if ($points[0]->closeEnough($points[1]) && sizeof($this->vialist) == 0) {
            wm_warn("Zero-length link " . $this->name . " skipped. [WMWARN45]");
            $this->geometry = null;
            return;
        }

        $widths = array($this->width, $this->width);

        // for bulging animations, modulate the width with the percentage value
        if (($map->widthmod) || ($map->get_hint('link_bulge') == 1)) {
            // a few 0.1s and +1s to fix div-by-zero, and invisible links

            $widths[IN] = (($widths[IN] * $this->percentUsages[IN] * 1.5 + 0.1) / 100) + 1;
            $widths[OUT] = (($widths[OUT] * $this->percentUsages[OUT] * 1.5 + 0.1) / 100) + 1;
        }

        $style = $this->viastyle;

        // don't bother with any curve stuff if there aren't any Vias defined, even if the style is 'curved'
        if (count($this->vialist) == 0) {
            wm_debug("Forcing to angled (no vias)\n");
            $style = "angled";
        }

        $this->geometry = LinkGeometryFactory::create($style);
        $this->geometry->Init($this, $points, $widths, ($this->linkstyle == 'oneway' ? 1 : 2), $this->splitpos, $this->arrowstyle);
    }

    public function Draw($imageRef)
    {
        wm_debug("Link " . $this->name . ": Drawing.\n");
        // If there is geometry to draw, draw it
        if (!is_null($this->geometry)) {
            wm_debug(get_class($this->geometry) . "\n");

            $this->geometry->setOutlineColour($this->outlinecolour);
            $this->geometry->setFillColours(array($this->colours[IN], $this->colours[OUT]));

            $this->geometry->draw($imageRef);

            if (!$this->commentfontcolour->isNone()) {
                $this->drawComments($imageRef);
            }

            $this->drawBandwidthLabels($imageRef);
        } else {
            wm_debug("Skipping link with no geometry attached\n");
        }

        $this->makeImagemapAreas();
    }

    private function makeImagemapAreas()
    {
        if (!isset($this->geometry)) {
            return;
        }

        foreach ($this->getDirectionList() as $direction) {
            $areaName = "LINK:L" . $this->id . ":$direction";

            $polyPoints = $this->geometry->getDrawnPolygon($direction);

            $newArea = new HTMLImagemapAreaPolygon($areaName, "", array($polyPoints));
            wm_debug("Adding Poly imagemap for %s\n", $areaName);

            $this->imap_areas[] = $newArea;
        }
    }

    private function drawBandwidthLabels($gdImage)
    {
        wm_debug("Link " . $this->name . ": Drawing bwlabels.\n");

        $directions = $this->getDirectionList();
        $labelOffsets = array();

        // TODO - this stuff should all be in arrays already!
        $labelOffsets[IN] = $this->labeloffset_in;
        $labelOffsets[OUT] = $this->labeloffset_out;

        foreach ($directions as $direction) {
            list ($position, $index, $angle, $distance) = $this->geometry->findPointAndAngleAtPercentageDistance($labelOffsets[$direction]);

            $percentage = $this->percentUsages[$direction];
            $bandwidth = $this->absoluteUsages[$direction];

            if ($this->owner->sizedebug) {
                $bandwidth = $this->maxValues[$direction];
            }

            $label_text = $this->owner->ProcessString($this->bwlabelformats[$direction], $this);
            if ($label_text != '') {
                wm_debug("Bandwidth for label is " . Utility::valueOrNull($bandwidth) . " (label is '$label_text')\n");
                $padding = intval($this->get_hint('bwlabel_padding'));

                // if screenshot_mode is enabled, wipe any letters to X and wipe any IP address to 127.0.0.1
                // hopefully that will preserve enough information to show cool stuff without leaking info
                if ($this->owner->get_hint('screenshot_mode') == 1) {
                    $label_text = Utility::stringAnonymise($label_text);
                }

                if ($this->labelboxstyle != 'angled') {
                    $angle = 0;
                }

                $this->drawLabelRotated(
                    $gdImage,
                    $position,
                    $angle,
                    $label_text,
                    $padding,
                    $direction
                );
            }
        }
    }

    private function normaliseAngle($angle)
    {
        $out = $angle;

        if (abs($out) > 90) {
            $out -= 180;
        }
        if ($out < -180) {
            $out += 360;
        }

        return $out;
    }

    private function drawLabelRotated($imageRef, $centre, $angle, $text, $padding, $direction)
    {
        $fontObject = $this->owner->fonts->getFont($this->bwfont);
        list($strWidth, $strHeight) = $fontObject->calculateImageStringSize($text);

        $angle = $this->normaliseAngle($angle);
        $radianAngle = -deg2rad($angle);

        $extra = 3;

        $topleft_x = $centre->x - ($strWidth / 2) - $padding - $extra;
        $topleft_y = $centre->y - ($strHeight / 2) - $padding - $extra;

        $botright_x = $centre->x + ($strWidth / 2) + $padding + $extra;
        $botright_y = $centre->y + ($strHeight / 2) + $padding + $extra;

        // a box. the last point is the start point for the text.
        $points = array($topleft_x, $topleft_y, $topleft_x, $botright_y, $botright_x, $botright_y, $botright_x, $topleft_y, $centre->x - $strWidth / 2, $centre->y + $strHeight / 2 + 1);

        if ($radianAngle != 0) {
            rotateAboutPoint($points, $centre->x, $centre->y, $radianAngle);
        }

        $textY = array_pop($points);
        $textX = array_pop($points);

        if ($this->bwboxcolour->isRealColour()) {
            imagefilledpolygon($imageRef, $points, 4, $this->bwboxcolour->gdAllocate($imageRef));
        }

        if ($this->bwoutlinecolour->isRealColour()) {
            imagepolygon($imageRef, $points, 4, $this->bwoutlinecolour->gdAllocate($imageRef));
        }

        $fontObject->drawImageString($imageRef, $textX, $textY, $text, $this->bwfontcolour->gdallocate($imageRef), $angle);

        // ------

        $areaName = "LINK:L" . $this->id . ':' . ($direction + 2);

        // the rectangle is about half the size in the HTML, and easier to optimise/detect in the browser
        if (($angle % 90) == 0) {
            // We optimise for 0, 90, 180, 270 degrees - find the rectangle from the rotated points
            $rectanglePoints = array();
            $rectanglePoints[] = min($points[0], $points[2]);
            $rectanglePoints[] = min($points[1], $points[3]);
            $rectanglePoints[] = max($points[0], $points[2]);
            $rectanglePoints[] = max($points[1], $points[3]);
            $newArea = new HTMLImagemapAreaRectangle($areaName, "", array($rectanglePoints));
            wm_debug("Adding Rectangle imagemap for $areaName\n");
        } else {
            $newArea = new HTMLImagemapAreaPolygon($areaName, "", array($points));
            wm_debug("Adding Poly imagemap for $areaName\n");
        }
        // Make a note that we added this area
        $this->imap_areas[] = $newArea;
        // $this->imageMapAreas[] = $newArea;
        $this->owner->imap->addArea($newArea);
    }

    public function WriteConfig()
    {
        if ($this->config_override != '') {
            return $this->config_override . "\n";
        }

        $output = '';

        $template_item = $this->owner->links[$this->template];

        wm_debug("Writing config for LINK $this->name against $this->template\n");

        $basic_params = array(
            array('width', 'WIDTH', self::CONFIG_TYPE_LITERAL),
            array('zorder', 'ZORDER', self::CONFIG_TYPE_LITERAL),
            array('overlibwidth', 'OVERLIBWIDTH', self::CONFIG_TYPE_LITERAL),
            array('overlibheight', 'OVERLIBHEIGHT', self::CONFIG_TYPE_LITERAL),
            array('arrowstyle', 'ARROWSTYLE', self::CONFIG_TYPE_LITERAL),
            array('viastyle', 'VIASTYLE', self::CONFIG_TYPE_LITERAL),
            array('linkstyle', 'LINKSTYLE', self::CONFIG_TYPE_LITERAL),
            array('splitpos', 'SPLITPOS', self::CONFIG_TYPE_LITERAL),
            array('duplex', 'DUPLEX', self::CONFIG_TYPE_LITERAL),
            array('commentstyle', 'COMMENTSTYLE', self::CONFIG_TYPE_LITERAL),
            array('labelboxstyle', 'BWSTYLE', self::CONFIG_TYPE_LITERAL),
            //		array('usescale','USESCALE',self::CONFIG_TYPE_LITERAL),

            array('bwfont', 'BWFONT', self::CONFIG_TYPE_LITERAL),
            array('commentfont', 'COMMENTFONT', self::CONFIG_TYPE_LITERAL),

            array('bwoutlinecolour', 'BWOUTLINECOLOR', self::CONFIG_TYPE_COLOR),
            array('bwboxcolour', 'BWBOXCOLOR', self::CONFIG_TYPE_COLOR),
            array('outlinecolour', 'OUTLINECOLOR', self::CONFIG_TYPE_COLOR),
            array('commentfontcolour', 'COMMENTFONTCOLOR', self::CONFIG_TYPE_COLOR),
            array('bwfontcolour', 'BWFONTCOLOR', self::CONFIG_TYPE_COLOR)
        );

        # TEMPLATE must come first. DEFAULT
        if ($this->template != 'DEFAULT' && $this->template != ':: DEFAULT ::') {
            $output .= "\tTEMPLATE " . $this->template . "\n";
        }

        $output .= $this->getSimpleConfig($basic_params, $template_item);

        $val = $this->usescale . " " . $this->scaletype;
        $comparison = $template_item->usescale . " " . $template_item->scaletype;

        if (($val != $comparison)) {
            $output .= "\tUSESCALE " . $val . "\n";
        }

        if ($this->infourl[IN] == $this->infourl[OUT]) {
            $dirs = array(IN => ""); // only use the IN value, since they're both the same, but don't prefix the output keyword
        } else {
            $dirs = array(IN => "IN", OUT => "OUT");// the full monty two-keyword version
        }

        foreach ($dirs as $dir => $tdir) {
            if ($this->infourl[$dir] != $template_item->infourl[$dir]) {
                $output .= "\t" . $tdir . "INFOURL " . $this->infourl[$dir] . "\n";
            }
        }

        if ($this->overlibcaption[IN] == $this->overlibcaption[OUT]) {
            $dirs = array(IN => ""); // only use the IN value, since they're both the same, but don't prefix the output keyword
        } else {
            $dirs = array(IN => "IN", OUT => "OUT");// the full monty two-keyword version
        }

        foreach ($dirs as $dir => $tdir) {
            if ($this->overlibcaption[$dir] != $template_item->overlibcaption[$dir]) {
                $output .= "\t" . $tdir . "OVERLIBCAPTION " . $this->overlibcaption[$dir] . "\n";
            }
        }

        if ($this->notestext[IN] == $this->notestext[OUT]) {
            $dirs = array(IN => ""); // only use the IN value, since they're both the same, but don't prefix the output keyword
        } else {
            $dirs = array(IN => "IN", OUT => "OUT");// the full monty two-keyword version
        }

        foreach ($dirs as $dir => $tdir) {
            if ($this->notestext[$dir] != $template_item->notestext[$dir]) {
                $output .= "\t" . $tdir . "NOTES " . $this->notestext[$dir] . "\n";
            }
        }

        if ($this->overliburl[IN] == $this->overliburl[OUT]) {
            $dirs = array(IN => ""); // only use the IN value, since they're both the same, but don't prefix the output keyword
        } else {
            $dirs = array(IN => "IN", OUT => "OUT");// the full monty two-keyword version
        }

        foreach ($dirs as $dir => $tdir) {
            if ($this->overliburl[$dir] != $template_item->overliburl[$dir]) {
                $output .= "\t" . $tdir . "OVERLIBGRAPH " . join(" ", $this->overliburl[$dir]) . "\n";
            }
        }

        // if formats have been set, but they're just the longform of the built-in styles, set them back to the built-in styles
        if ($this->labelstyle == '--' && $this->bwlabelformats[IN] == self::FMT_PERC_IN && $this->bwlabelformats[OUT] == self::FMT_PERC_OUT) {
            $this->labelstyle = 'percent';
        }
        if ($this->labelstyle == '--' && $this->bwlabelformats[IN] == self::FMT_BITS_IN && $this->bwlabelformats[OUT] == self::FMT_BITS_OUT) {
            $this->labelstyle = 'bits';
        }
        if ($this->labelstyle == '--' && $this->bwlabelformats[IN] == self::FMT_UNFORM_IN && $this->bwlabelformats[OUT] == self::FMT_UNFORM_OUT) {
            $this->labelstyle = 'unformatted';
        }

        // if specific formats have been set, then the style will be '--'
        // if it isn't then use the named style
        if (($this->labelstyle != $template_item->labelstyle) && ($this->labelstyle != '--')) {
            $output .= "\tBWLABEL " . $this->labelstyle . "\n";
        }

        // if either IN or OUT field changes, then both must be written because a regular BWLABEL can't do it
        // XXX this looks wrong
        $comparison = $template_item->bwlabelformats[IN];
        $comparison2 = $template_item->bwlabelformats[OUT];

        if (($this->labelstyle == '--') && (($this->bwlabelformats[IN] != $comparison) || ($this->bwlabelformats[OUT] != '--'))) {
            $output .= "\tINBWFORMAT " . $this->bwlabelformats[IN] . "\n";
            $output .= "\tOUTBWFORMAT " . $this->bwlabelformats[OUT] . "\n";
        }

        $comparison = $template_item->labeloffset_in;
        $comparison2 = $template_item->labeloffset_out;

        if (($this->labeloffset_in != $comparison) || ($this->labeloffset_out != $comparison2)) {
            $output .= "\tBWLABELPOS " . $this->labeloffset_in . " " . $this->labeloffset_out . "\n";
        }

        $comparison = $template_item->commentoffset_in . ":" . $template_item->commentoffset_out;
        $mine = $this->commentoffset_in . ":" . $this->commentoffset_out;
        if ($mine != $comparison) {
            $output .= "\tCOMMENTPOS " . $this->commentoffset_in . " " . $this->commentoffset_out . "\n";
        }

        $comparison = $template_item->targets;

        if ($this->targets != $comparison) {
            $output .= "\tTARGET";

            foreach ($this->targets as $target) {
                $output .= " " . $target->asConfig();
            }
            $output .= "\n";
        }

        foreach (array("IN", "OUT") as $tdir) {
            $dir = constant($tdir);

            $comparison = $template_item->comments[$dir];
            if ($this->comments[$dir] != $comparison) {
                $output .= "\t" . $tdir . "COMMENT " . $this->comments[$dir] . "\n";
            }
        }

        if (isset($this->a) && isset($this->b)) {
            $output .= "\tNODES " . $this->a->name;

            if ($this->a_offset != 'C') {
                $output .= ":" . $this->a_offset;
            }

            $output .= " " . $this->b->name;

            if ($this->b_offset != 'C') {
                $output .= ":" . $this->b_offset;
            }

            $output .= "\n";
        }

        if (count($this->vialist) > 0) {
            foreach ($this->vialist as $via) {
                if (isset($via[2])) {
                    $output .= sprintf("\tVIA %s %d %d\n", $via[2], $via[0], $via[1]);
                } else {
                    $output .= sprintf("\tVIA %d %d\n", $via[0], $via[1]);
                }
            }
        }

        $output .= $this->getMaxValueConfig($template_item, "BANDWIDTH");
        $output .= $this->getHintConfig($template_item);

        if ($output != '') {
            $output = "LINK " . $this->name . "\n" . $output . "\n";
        }

        return $output;
    }

    protected function asJSCore()
    {
        $output = "";

        $output .= "\"id\":" . $this->id . ", ";
        if (isset($this->a)) {
            $output .= "a:'" . $this->a->name . "', ";
            $output .= "b:'" . $this->b->name . "', ";
        }

        $output .= "width:'" . $this->width . "', ";
        $output .= "target:";

        $tgt = '';

        $i = 0;
        foreach ($this->targets as $target) {
            if ($i > 0) {
                $tgt .= " ";
            }
            $tgt .= $target->asConfig();
            $i++;
        }

        $output .= Utility::jsEscape(trim($tgt));
        $output .= ",";

        $output .= "bw_in:" . Utility::jsEscape($this->maxValuesConfigured[IN]) . ", ";
        $output .= "bw_out:" . Utility::jsEscape($this->maxValuesConfigured[OUT]) . ", ";

        $output .= "name:" . Utility::jsEscape($this->name) . ", ";
        $output .= "overlibwidth:'" . $this->overlibheight . "', ";
        $output .= "overlibheight:'" . $this->overlibwidth . "', ";
        $output .= "overlibcaption:" . Utility::jsEscape($this->overlibcaption[IN]) . ", ";

        $output .= "commentin:" . Utility::jsEscape($this->comments[IN]) . ", ";
        $output .= "commentposin:" . intval($this->commentoffset_in) . ", ";

        $output .= "commentout:" . Utility::jsEscape($this->comments[OUT]) . ", ";
        $output .= "commentposout:" . intval($this->commentoffset_out) . ", ";

        $output .= "infourl:" . Utility::jsEscape($this->infourl[IN]) . ", ";
        $output .= "overliburl:" . Utility::jsEscape(join(" ", $this->overliburl[IN])) . ", ";

        $output .= "via: [";
        $nItem = 0;
        foreach ($this->vialist as $via) {
            if ($nItem > 0) {
                $output .= ", ";
            }
            $output .= sprintf("[%d,%d", $via[0], $via[1]);
            if (isset($via[2])) {
                $output .= "," . Utility::jsEscape($via[2]);
            }
            $output .= "]";

            $nItem++;
        }

        $output .= "]";

        return $output;
    }

    public function asJS($type = "Link", $prefix = "L")
    {
        return parent::asJS($type, $prefix);
    }

    public function cleanUp()
    {
        parent::cleanUp();

        $this->owner = null;
        $this->a = null;
        $this->b = null;
        // $this->parent = null;
        $this->descendents = null;
    }

    public function getValue($name)
    {
        wm_debug("Fetching %s\n", $name);
        if (property_exists($this, $name)) {
            return $this->$name;
        }
        throw new WeathermapInternalFail("NoSuchProperty");
    }

    /**
     * Set the new ends for a link.
     *
     * @param MapNode $node1
     * @param MapNode $node2
     * @throws WeathermapInternalFail if passed any nulls (should never happen)
     */
    public function setEndNodes($node1, $node2)
    {
        if (null !== $node1 && null === $node2) {
            throw new WeathermapInternalFail("PartiallyRealLink");
        }

        if (null !== $node2 && null === $node1) {
            throw new WeathermapInternalFail("PartiallyRealLink");
        }

        if (null !== $this->a) {
            $this->a->removeDependency($this);
        }
        if (null !== $this->b) {
            $this->b->removeDependency($this);
        }
        $this->a = $node1;
        $this->b = $node2;

        if (null !== $this->a) {
            $this->a->addDependency($this);
        }
        if (null !== $this->b) {
            $this->b->addDependency($this);
        }
    }

    public function asConfigData()
    {
        $config = parent::asConfigData();

        $config['a'] = $this->a->name;
        $config['b'] = $this->b->name;
        $config['width'] = $this->width;

        return $config;
    }
}

// vim:ts=4:sw=4:
