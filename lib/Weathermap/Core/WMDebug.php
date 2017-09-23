<?php
/**
 * A replacement for the old wm_debug trace functions littered through the code, so that in non-debug
 * mode, the function call is just a stub, with no checking required. There are enough of these that
 * this should make non-debug mode a bit faster, and also allow for more elaborate things to go one in
 * debug mode.
 */
namespace Weathermap\Core;

/**
 * Class WMDebugNull - the stubbed do-nothing version for a normal run
 */
class WMDebugNull
{
    protected $onlyReadData;
    protected $contextName;

    public function __construct($contextName, $onlyReadData = false)
    {
        $this->contextName = $contextName;
        $this->onlyReadData = $onlyReadData;
    }

    public function log($string)
    {
        return;
    }

    public function setContext($newContext)
    {
        $this->contextName = $newContext;
    }

    protected function shouldLog($string)
    {
        return false;
    }
}

/**
 * Class WMDebugLogging - debug logging enabled.
 */
class WMDebugLogging extends WMDebugNull
{
    public function log($string)
    {
        if (!$this->shouldLog($string)) {
            return;
        }

        if (func_num_args() > 1) {
            $args = func_get_args();
            $string = call_user_func_array('sprintf', $args);
        }

        $calling_fn = $this->getCallingFunction();

        $message = "DEBUG:$calling_fn " . ($this->contextName == '' ? '' : $this->contextName . ": ") . rtrim($string) . "\n";

        $this->doLog($message);
    }

    protected function doLog($message)
    {
        $stderr = fopen('php://stderr', 'w');
        fwrite($stderr, $message);
        fclose($stderr);
    }

    /**
     * @return string
     */
    private function getCallingFunction()
    {
        $callingFunction = "";

        if (function_exists("debug_backtrace")) {
            $backtrace = debug_backtrace();
            $index = 3;

            $function = (true === isset($backtrace[$index]['function'])) ? $backtrace[$index]['function'] : '';
            $index = 2;
            $file = (true === isset($backtrace[$index]['file'])) ? basename($backtrace[$index]['file']) : '';
            $line = (true === isset($backtrace[$index]['line'])) ? $backtrace[$index]['line'] : '';

            $callingFunction = " [$function@$file:$line]";
            return $callingFunction;
        }
        return $callingFunction;
    }

    protected function shouldLog($string)
    {
        global $weathermap_debugging_readdata;
        global $weathermap_debugging;

        $isReadData = false;

        if (($weathermap_debugging_readdata) and (false !== strpos("ReadData", $string))) {
            $isReadData = true;
        }

        if ($weathermap_debugging || ($weathermap_debugging_readdata && $isReadData)) {
            return true;
        }
        return false;
    }
}

/**
 * Class WMDebugLoggingCacti - debug logging enabled, and we're running in the Cacti poller.
 */
class WMDebugLoggingCacti extends WMDebugLogging
{
    protected function doLog($message)
    {
        cacti_log($message, true, "WEATHERMAP");
    }
}

/**
 * Class WMDebugFactory - pick which of the debug-logging classes to use. We call wm_debug() thousands of
 * times in a map run, so having the minimum possible decision-making done inside it is useful, especially when
 * it's the same decision each time! (e.g. function_exists() )
 */
class WMDebugFactory
{
    public static function update($newStatus)
    {
        global $weathermap_debugging;

        $weathermap_debugging = $newStatus;

        return self::create();
    }

    public static function create()
    {
        global $weathermap_debugging_readdata;
        global $weathermap_debugging;
        global $weathermap_map;

        if ($weathermap_debugging) {
            if (function_exists('debug_log_insert') && (!function_exists('wmeShowStartPage'))) {
                return new WMDebugLoggingCacti($weathermap_map, $weathermap_debugging_readdata);
            }
            return new WMDebugLogging($weathermap_map, $weathermap_debugging_readdata);
        }
        return new WMDebugNull($weathermap_map, $weathermap_debugging_readdata);
    }
}
