<?php

final class BridgeNativeCarrier
{
    /**
     * Supply canonical bytes inside the original native conversion frame.
     *
     * @return string
     */
    public function __toString()
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $GLOBALS['bridge_operand_events'][] = array('convert', $trace[1]['function'], error_reporting());
        $wire = serialize(array('kept' => 'native'));
        return $GLOBALS['bridge_operand_mode'] === 'raw' ? base64_encode($wire) : $wire;
    }

    /**
     * Observe the native argument release scope.
     *
     * @return void
     */
    public function __destruct()
    {
        $GLOBALS['bridge_operand_events'][] = array('destroy', error_reporting());
    }
}

final class BridgeNativeDatabase
{
    public $data_cache = array();

    /**
     * Retain legacy select reset.
     *
     * @return void
     */
    public function _reset_select() {}

    /**
     * Retain legacy write reset.
     *
     * @return void
     */
    public function _reset_write() {}

    /**
     * Retain field selection.
     *
     * @param string $key
     * @return self
     */
    public function select($key) { return $this; }

    /**
     * Retain fieldtype filtering.
     *
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function where($key, $value) { return $this; }

    /**
     * Supply the fieldtype query.
     *
     * @param string $table
     * @return self
     */
    public function get($table) { return $this; }

    /**
     * Retain the absent optional module settings table.
     *
     * @param string $table
     * @return bool
     */
    public function table_exists($table) { return false; }

    /**
     * Supply one stored fieldtype.
     *
     * @return int
     */
    public function num_rows() { return 1; }

    /**
     * Supply the untouched native or host-hook operand.
     *
     * @param string $key
     * @return mixed
     */
    public function row($key)
    {
        if ($GLOBALS['bridge_operand_mode'] === 'raw' || $GLOBALS['bridge_operand_mode'] === 'hook') {
            return new BridgeNativeCarrier();
        }
        return base64_encode(serialize(array('kept' => 'native')));
    }
}

/**
 * Resolve the real legacy query-builder boundary.
 *
 * @param string|null $service
 * @return object
 */
function ee($service = null) { return $service === 'db' ? $GLOBALS['bridge_operand_app']->db : $GLOBALS['bridge_operand_app']; }

/**
 * Preserve the original raw argument passed to a caller-local hook.
 *
 * @param mixed $carrier
 * @return BridgeNativeCarrier
 */
function bridgeNativeCarrierHook($carrier)
{
    $GLOBALS['bridge_operand_events'][] = array('carrier-hook', gettype($carrier), error_reporting());
    return $carrier instanceof BridgeNativeCarrier ? $carrier : new BridgeNativeCarrier();
}

/**
 * Preserve the original untouched decoded host-parser argument.
 *
 * @param mixed $decoded
 * @return array
 */
function bridgeNativeParserHook($decoded)
{
    $GLOBALS['bridge_operand_events'][] = array('parser-hook', gettype($decoded), error_reporting());
    return array('kept' => $decoded instanceof BridgeNativeCarrier ? 'native' : 'changed');
}

$archive = $argv[1];
$kind = $argv[2];
$GLOBALS['bridge_operand_mode'] = $argv[3];
$GLOBALS['bridge_operand_events'] = array();
$GLOBALS['bridge_operand_app'] = (object) array('db' => new BridgeNativeDatabase());
define('PATH_THIRD', dirname(__DIR__, 2) . '/');
spl_autoload_register(static function ($class) use ($archive) {
    $prefix = 'Low\\Widont\\';
    if (strpos($class, $prefix) === 0) {
        $path = 'phar://' . $archive . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (file_exists($path)) { require $path; }
    }
});
$class = 'Low\\Widont\\FluxCapacitor\\Bridge\\' . $kind;
class_exists($class);
$read = static function () use ($class) {
    Low\Widont\FluxCapacitor\Conduit\StaticCache::clear();
    $object = (new ReflectionClass($class))->newInstanceWithoutConstructor();
    $property = new ReflectionProperty($class, 'module');
    if (PHP_VERSION_ID < 80100) { $property->setAccessible(true); }
    $property->setValue($object, 'low_widont');
    $settings = $object->getSettings(true);
    return $settings['kept'] ?? null;
};
if ($GLOBALS['bridge_operand_mode'] === 'frame' || $GLOBALS['bridge_operand_mode'] === 'hook') {
    eval('namespace Low\\Widont\\FluxCapacitor\\Bridge; function base64_decode($carrier) { return \\bridgeNativeCarrierHook($carrier); }');
}
if ($GLOBALS['bridge_operand_mode'] === 'hook') {
    eval('namespace Low\\Widont\\FluxCapacitor\\Bridge; function unserialize($decoded) { return \\bridgeNativeParserHook($decoded); }');
}
if ($GLOBALS['bridge_operand_mode'] === 'native-guard') {
    eval('namespace Low\\Widont\\FluxCapacitor\\Bridge; function is_array($value) { return false; }');
}
if ($GLOBALS['bridge_operand_mode'] === 'snapshot') {
    $tool = dirname(__DIR__, 2) . '/tests/.phpunit/phpunit-9.phar';
    require 'phar://' . $tool . '/sebastian-recursion-context/Context.php';
    require 'phar://' . $tool . '/sebastian-object-reflector/ObjectReflector.php';
    foreach (array('ExcludeList', 'Snapshot', 'Restorer') as $part) { require 'phar://' . $tool . '/sebastian-global-state/' . $part . '.php'; }
    $snapshot = new PHPUnitPHAR\SebastianBergmann\GlobalState\Snapshot(null, false, true, false, false, true, false, false, false, false);
}
$values = array($read());
if ($GLOBALS['bridge_operand_mode'] === 'snapshot') {
    eval('namespace Low\\Widont\\FluxCapacitor\\Bridge; function unserialize($decoded) { $GLOBALS["bridge_operand_events"][] = "late-hook"; return array("kept" => "changed"); }');
    (new PHPUnitPHAR\SebastianBergmann\GlobalState\Restorer())->restoreStaticAttributes($snapshot);
    $values[] = $read();
}
echo json_encode(array('values' => $values, 'events' => $GLOBALS['bridge_operand_events']), JSON_THROW_ON_ERROR), "\n";
