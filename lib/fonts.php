<?php

class WMFont
{
    public $type;
    public $file;
    public $gdnumber;
    public $size;
    public $loaded;

    public function __construct()
    {
        $this->loaded = false;
    }

    public function isLoaded()
    {
        return $this->loaded;
    }

    /**
     * @param $lines
     * @return int
     */
    protected function calculateMaxLineLength($lines)
    {
        $maxLineLength = 0;

        foreach ($lines as $line) {
            $lineLength = strlen($line);
            if ($lineLength > $maxLineLength) {
                $maxLineLength = $lineLength;
            }
        }
        return $maxLineLength;
    }

    public function drawImageString($gdImage, $x, $y, $string, $colour, $angle = 0)
    {
    }

    public function calculateImageStringSize($string)
    {
        return array(0, 0);
    }

}

class WMTrueTypeFont extends WMFont
{
    public $file;
    public $size;
    public $v_offset;

    public function __construct($filename, $size, $v_offset = 0)
    {
        parent::__construct();

        $this->loaded = $this->initTTF($filename, $size);
        $this->v_offset = $v_offset;
    }

    public function drawImageString($gdImage, $x, $y, $string, $colour, $angle = 0)
    {
        imagettftext($gdImage, $this->size, $angle, $x, $y, $colour, $this->file, $string);
    }

    public function getConfig($fontNumber)
    {
        if ($this->v_offset != 0) {
            return sprintf("FONTDEFINE %d %s %d %d\n", $fontNumber, $this->file, $this->size, $this->v_offset);
        } else {
            return sprintf("FONTDEFINE %d %s %d\n", $fontNumber, $this->file, $this->size);
        }
    }

    public function asConfigData($fontNumber)
    {
        return array(
            "number" => $fontNumber,
            "type" => $this->type,
            "file" => $this->file,
            "size" => $this->size,
            "vertical_offset" => $this->v_offset
        );
    }

    private function initTTF($filename, $size)
    {
        if (!function_exists("imagettfbbox")) {
            wm_warn("Truetype support not available in GD. Unable to load font.");
            return false;
        }

        // test if this font is valid, before adding it to the font table...
        $bounds = @imagettfbbox($size, 0, $filename, "Ignore me");
        if (isset($bounds[0])) {
            $this->file = $filename;
            $this->size = $size;
            $this->type = "truetype";

            return true;
        }
        wm_warn("Could not load font - $filename");
        return false;
    }

    public function calculateImageStringSize($string)
    {
        $lines = explode("\n", $string);

        $height = 0;
        $width = 0;
        foreach ($lines as $line) {
            $bounds = imagettfbbox($this->size, 0, $this->file, $line);
            $charWidth = $bounds[4] - $bounds[0];
            $charHeight = $bounds[1] - $bounds[5];
            if ($charWidth > $width) {
                $width = $charWidth;
            }
            $height += ($charHeight * 1.2) - $this->v_offset;  # subtract v_offset, due to coordinate system
        }

        return array($width, $height);
    }
}

class WMGDFont extends WMFont
{
    public $gdnumber;

    /**
     * WMGDFont constructor.
     * @param int|string $filename
     */
    public function __construct($filename)
    {
        parent::__construct();

        if (is_numeric($filename)) {
            $this->loaded = $this->initGDBuiltin(intval($filename));
        } else {
            $this->loaded = $this->initGD($filename);
        }
    }

    public function drawImageString($gdImage, $x, $y, $string, $colour, $angle = 0)
    {
        imagestring($gdImage, $this->gdnumber, $x, $y - imagefontheight($this->gdnumber), $string, $colour);
        if ($angle != 0) {
            wm_warn("Angled text doesn't work with non-FreeType fonts [WMWARN02]\n");
        }
    }

    public function getConfig($fontNumber)
    {
        if ($fontNumber < 6) {
            return "";
        }
        return sprintf("FONTDEFINE %d %s\n", $fontNumber, $this->file);
    }

    public function calculateImageStringSize($string)
    {
        $lines = explode("\n", $string);
        $lineCount = sizeof($lines);
        $maxLineLength = $this->calculateMaxLineLength($lines);

        return array(imagefontwidth($this->gdnumber) * $maxLineLength, $lineCount * imagefontheight($this->gdnumber));
    }

    private function initGDBuiltin($gdNumber)
    {
        $this->gdnumber = $gdNumber;
        $this->type = "GD builtin";

        return true;
    }

    /**
     * @param string $filename
     * @return bool
     */
    private function initGD($filename)
    {
        $gdFontID = imageloadfont($filename);

        if ($gdFontID) {
            $this->gdnumber = $gdFontID;
            $this->file = $filename;
            $this->type = "gd";

            return true;
        }
        return false;
    }

    public function asConfigData($fontNumber)
    {
        return array(
            "number" => $fontNumber,
            "type" => $this->type,
            "file" => $this->file
        );
    }
}

class WMFontTable
{
    private $table = array();

    public function init()
    {
        for ($i = 1; $i < 6; $i++) {
            $newFont = new WMGDFont($i);
            $this->addFont($i, $newFont);
        }
    }

    public function count()
    {
        return sizeof($this->table);
    }

    public function addFont($fontNumber, $font)
    {
        $this->table[$fontNumber] = $font;
    }

    /**
     * isValid - verify if a font number is valid in the current font table
     *
     * @param $fontNumber int Number of font in table
     * @return bool true if font number is for a valid font
     */
    public function isValid($fontNumber)
    {
        if (!isset($this->table[$fontNumber])) {
            return false;
        }

        return $this->table[$fontNumber]->isLoaded();
    }

    /**
     * @param int $fontNumber
     * @return WMFont
     */
    public function getFont($fontNumber)
    {
        if (!$this->isValid($fontNumber)) {
            wm_warn("Using a non-existent special font ($fontNumber) - falling back to internal GD fonts [WMWARN36]\n");
            return $this->getFont(5);
        }

        return $this->table[$fontNumber];
    }

    public function getList()
    {
        $list = array();

        foreach ($this->table as $fontNumber => $fontObject) {
            $list[$fontNumber] = array("type" => $fontObject->type);
        }

        return $list;
    }

    public function asConfigData()
    {
        $conf = array();

        foreach ($this->table as $fontNumber => $fontObject) {
            $font = $fontObject->asConfigData($fontNumber);
            $conf[] = $font;
        }

        return $conf;
    }

    /**
     * @return string
     */
    public function getConfig()
    {
        $output = "";
        if (count($this->table) > 0) {
            foreach ($this->table as $fontNumber => $fontObject) {
                $output .= $fontObject->getConfig($fontNumber);
            }
        }
        $output .= "\n";

        return $output;
    }

    /**
     * @param string $type
     * @param string $file
     * @param int $size
     * @return WMGDFont|WMTrueTypeFont
     * @throws WeathermapInternalFail
     */
    public function makeFontObject($type, $file, $size = 0)
    {
        if ($type == "truetype") {
            return new WMTrueTypeFont($file, $size);
        }

        if ($type == "gd") {
            return new WMGDFont($file);
        }

        throw new WeathermapInternalFail("Requested non-existent font type");
    }
}
