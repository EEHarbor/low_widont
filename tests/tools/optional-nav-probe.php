<?php

$state = $argv[1];
$root = sys_get_temp_dir() . '/optional-third/';
mkdir($root . 'low_widont/Conduit', 0700, true);
$override = $root . 'low_widont/Conduit/McpNav.php';
if ($state !== 'missing') {
    file_put_contents($override, '<?php $GLOBALS["owned_nav_override"] = true;');
    chmod($override, $state === 'unreadable' ? 0000 : 0600);
}
define('PATH_THIRD', $root);
try {
    require 'phar://' . dirname(__DIR__, 2) . '/low_widont/low_widont.phar/FluxCapacitor/Conduit/McpNav.php';
    echo json_encode(array(
        'loaded' => class_exists('Low\\Widont\\FluxCapacitor\\Conduit\\McpNav', false),
        'override' => isset($GLOBALS['owned_nav_override']),
    ), JSON_THROW_ON_ERROR), "\n";
} finally {
    if (is_file($override)) {
        chmod($override, 0600);
    }
}
