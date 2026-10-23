<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$mode = $argv[1];
$eeVersion = $argv[2];
$temporaryRoot = realpath($argv[3]);
if ($temporaryRoot === false) {
    throw new RuntimeException('Request context requires an existing owned temporary root.');
}
$mirror = $temporaryRoot . '/third/';
$sourceDirectory = $root . '/low_widont/';
$provenance = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDirectory, FilesystemIterator::SKIP_DOTS)) as $file) {
    if ($file->isLink()) {
        throw new RuntimeException('Refusing a symlink in source mirror.');
    }
    $relative = substr($file->getPathname(), strlen($sourceDirectory));
    $target = $mirror . 'low_widont/' . $relative;
    if (!is_dir(dirname($target))) {
        mkdir(dirname($target), 0700, true);
    }
    copy($file->getPathname(), $target);
    $digest = hash_file('sha256', $file->getPathname());
    if (hash_file('sha256', $target) !== $digest) {
        throw new RuntimeException('Context mirror changed shipped source: ' . $relative);
    }
    $provenance[$relative] = $digest;
}
$withNav = $mode === 'navigation' || $mode === 'legacy-navigation';
if ($withNav) {
    mkdir($mirror . 'low_widont/Conduit', 0700, true);
    copy($root . '/tests/fixtures/addon/McpNav.php', $mirror . 'low_widont/Conduit/McpNav.php');
}
copy($root . '/tests/fixtures/addon/Extension.php', $mirror . 'low_widont/ext.low_widont.php');
putenv('LOW_WIDONT_THIRD_ROOT=' . $mirror);
putenv('LOW_WIDONT_EE_VERSION=' . $eeVersion);
if (strpos($mode, 'fieldtype-') === 0) {
    $layout = substr($mode, strlen('fieldtype-'));
    $application = $temporaryRoot . ($layout === 'installer' ? '/installer/' : '/application/');
    $actual = $layout === 'installer' ? str_replace('/installer/', '/expressionengine/', $application) : $application;
    mkdir($actual . 'fieldtypes', 0700, true);
    copy($root . '/tests/fixtures/system/ee/legacy/fieldtypes/EE_Fieldtype.php', $actual . 'fieldtypes/EE_Fieldtype.php');
    putenv('LOW_WIDONT_SYSTEM_ROOT=' . $temporaryRoot . '/empty-system/');
    putenv('LOW_WIDONT_APP_ROOT=' . $application);
}
if ($mode === 'fieldtype-predefined') {
    putenv('LOW_WIDONT_SYSTEM_ROOT=' . $root . '/tests/fixtures/system/');
    putenv('LOW_WIDONT_APP_ROOT=' . $root . '/tests/fixtures/system/ee/legacy/');
    define('Low\\Widont\\VERSION', '4.1.1');
}
define('REQ', $mode === 'site' ? 'PAGE' : 'CP');
define('AMP', '&amp;');
define('BASE', $mode === 'legacy-existing-directives' ? 'index.php?S=existing&D=cp' : 'index.php?route=cp');
if ($mode === 'theme-root') {
    define('URL_THIRD_THEMES', '/assets/third_party/');
}
require $root . '/tests/tools/verify-tool.php';
require $root . '/tests/support/Evidence.php';
ob_start();
require $root . '/tests/.phpunit/phpunit-9.phar';
ob_end_clean();
$collect = function_exists('xdebug_start_code_coverage');
if ($collect) {
    xdebug_set_filter(XDEBUG_FILTER_CODE_COVERAGE, XDEBUG_PATH_INCLUDE, [$root . '/low_widont/', $mirror . 'low_widont/', 'phar://' . $mirror . 'low_widont/low_widont.phar/']);
    xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE | XDEBUG_CC_BRANCH_CHECK);
}
require $root . '/tests/bootstrap.php';
require $root . '/tests/support/Database.php';
require $root . '/tests/support/Schema.php';
require $root . '/tests/support/Navigation.php';
require $root . '/tests/support/Context.php';
require $root . '/tests/support/LegacyHelpers.php';
set_error_handler(['LowWidontTests\\Environment', 'diagnostic'], E_ALL);
$ee = (object) ['config' => new LowWidontTests\ContextConfig(), 'session' => new LowWidontTests\ContextSession(),
    'cp' => new LowWidontTests\ContextCp(), 'uri' => new LowWidontTests\ContextUri(), 'load' => new LowWidontTests\ContextLoader(),
    'view' => (object) ['cp_page_title' => 'Original']];
$database = new LowWidontTests\Database(['eeharbor_licenses' => [], 'modules' => [], 'extensions' => [], 'menu_items' => []]);
$database->columns['eeharbor_licenses'] = ['site_id', 'addon', 'license_key', 'ignore_site'];
$ee->db = $database;
$ee->dbforge = new LowWidontTests\Schema($database);
LowWidontTests\Environment::$ee = $ee;
$urls = new LowWidontTests\UrlFactory();
$sidebar = new LowWidontTests\Sidebar();
LowWidontTests\Environment::$services = ['CP/Sidebar' => $sidebar, 'CP/URL' => static function ($path = null) use ($urls) { return $path === null ? $urls : $urls->make($path); }];
Low\Widont\FluxCapacitor\Conduit\StaticCache::put('app_settings', ['name' => 'Low Widon’t', 'description' => 'Eliminates widows', 'docs_url' => '/docs', 'version' => '4.1.1']);
$assertions = 0;
$failures = [];
$cases = [];
$same = static function ($expected, $actual, $label) use (&$assertions) {
    $assertions++;
    if ($expected !== $actual) {
        throw new RuntimeException($label . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
};
$cases['runtime bridge dispatch matches the actual EE version'] = static function () use ($same) {
    $flux = new Low\Widont\FluxCapacitor\FluxCapacitor();
    $reflection = new ReflectionClass('Low\\Widont\\FluxCapacitor\\Bridge\\EE');
    $expected = (int) APP_VER >= 6 ? 'EE6' : 'EE' . substr(APP_VER, 0, 1);
    $same('Low\\Widont\\FluxCapacitor\\Bridge\\' . $expected, $reflection->getParentClass()->getName(), 'selected bridge parent');
    $same(substr(APP_VER, 0, 1), $flux->getEEVersion(), 'runtime version');
};
if ($mode === 'navigation') {
    require $mirror . 'low_widont/Conduit/McpNav.php';
    $cases['empty defaults and lifecycle'] = static function () use ($same, $sidebar) {
        $nav = new Low\Widont\Conduit\McpNav();
        $same(['before', 'after'], $nav->events, 'hook order');
        $same(['License'], array_column($sidebar->headers, 'title'), 'forced license');
        $same(null, $nav->getActiveItem(), 'no active default item');
    };
    $cases['aliases links buttons map and toolbar'] = static function () use ($same, $ee) {
        Low\Widont\Conduit\McpNav::$items = ['/' => 'Overview', 'license' => 'License', 'HEADING-tools' => 'Tools', 'https://example.test/help' => 'Help'];
        Low\Widont\Conduit\McpNav::$buttons = ['index' => ['create' => 'New']];
        Low\Widont\Conduit\McpNav::$map = ['edit' => '/', 'other' => 'license'];
        $ee->uri->segments = ['cp', 'addons', 'settings', 'edit'];
        $nav = new Low\Widont\Conduit\McpNav();
        $same('Overview', $nav->active_title, 'active alias label');
        $same(true, $nav->getActiveItem()->active, 'active header');
        $same(null, $nav->nav_items['HEADING-tools']->url, 'heading has no URL');
        $same('https://example.test/help', $nav->nav_items['https://example.test/help']->url, 'external URL');
        $same('/addons/settings/low_widont/index', (string) $nav->getUrl('/'), 'index alias URL');
        $same('New', $nav->nav_items['index']->buttons[0][0], 'button label');
        $nav->setToolbarIcon();
        $same('Low Widon’t', $ee->view->header['title'], 'default toolbar label');
        $nav->setToolbarIcon('Custom', 'license', 'key');
        $same('Custom', $ee->view->header['toolbar_items']['key']['title'], 'custom toolbar label');
        $same('/addons/settings/low_widont/license', (string) $ee->view->header['toolbar_items']['key']['href'], 'custom toolbar URL');
    };
    $cases['deferred generation and explicit active item'] = static function () use ($same, $ee) {
        Low\Widont\Conduit\McpNav::$items = ['' => 'Overview', 'license' => 'License'];
        Low\Widont\Conduit\McpNav::$buttons = [];
        Low\Widont\Conduit\McpNav::$map = ['low_widont' => 'license'];
        Low\Widont\Conduit\McpNav::$deferred = true;
        $ee->uri->segments = ['cp', 'license'];
        $nav = new Low\Widont\Conduit\McpNav();
        $same(['before'], $nav->events, 'deferred hooks');
        $same(null, $nav->sidebar, 'deferred sidebar');
        $nav->generateNav();
        $same('License', $nav->active_title, 'explicit active route');
        $same(true, $nav->getActiveItem()->active, 'explicit active header');
        Low\Widont\Conduit\McpNav::$deferred = false;
        $mcp = new Low\Widont\FluxCapacitor\Base\Mcp();
        $same(true, $mcp->nav instanceof Low\Widont\Conduit\McpNav, 'CP constructs optional addon navigation');
    };
}
if (strpos($mode, 'legacy') === 0 && $mode !== 'legacy-cache') {
    $cases['real version dispatch and legacy URL directives'] = static function () use ($same, $ee, $mode) {
        $flux = new Low\Widont\FluxCapacitor\FluxCapacitor();
        $ee->config->values['admin_session_type'] = 'cs';
        $same(true, $flux->is_ee2(), 'EE2 bridge selected');
        $url = $flux->getBaseURL('/');
        $expectedSession = version_compare(APP_VER, '2.6.0', '>=') && version_compare(APP_VER, '2.7.3', '<=') ? 'S=fingerprint' : 'S=session-id';
        $same(true, strpos($url, $mode === 'legacy-existing-directives' ? 'S=existing' : $expectedSession) !== false, 'session directive');
        $same(1, substr_count($url, 'D=cp'), 'CP directive is not duplicated');
        $ee->config->values['admin_session_type'] = 'cs';
        $ee->config->values['cp_session_type'] = 'cs';
        $url = $flux->moduleURL('license', ['site' => 1]);
        $same(true, strpos($url, $mode === 'legacy-existing-directives' ? 'S=existing' : 'S=fingerprint') !== false, 'fingerprint directive');
        $same(true, strpos($url, 'method=license') !== false, 'module method');
    };
    $cases['legacy dynamic view writes only the private mirror'] = static function () use ($same, $mirror) {
        $flux = new Low\Widont\FluxCapacitor\FluxCapacitor();
        $same('rendered:dynamic', $flux->view('unused', ['value' => 0], true, '<p>preview</p>'), 'dynamic view result');
        $same('<p>preview</p>', file_get_contents($mirror . 'low_widont/views/dynamic.php'), 'dynamic view bytes');
    };
    $cases['legacy fieldtype settings layout'] = static function () use ($same, $ee) {
        require_once dirname(__DIR__) . '/support/Fieldtype.php';
        $table = new class {
            public $rows = [];
            /**
             * Capture legacy fieldtype rows using the EE table helper signature.
             *
             * @param mixed ...$arguments
             * @return void
             */
            public function add_row(...$arguments) { $this->rows[] = $arguments; }
        };
        $ee->table = $table;
        $fieldtype = new Low_widont_fixture_ft();
        $fieldtype->displaySettingsRow('Title', '<input>');
        $fieldtype->displaySettingsRow('Wide', '<p>', '', true);
        $same([['Title', '<input>'], [['colspan' => '2', 'data' => 'Wide<p>']]], $table->rows, 'legacy table rows');
        $same(true, $fieldtype->displaySettingsReturn(), 'legacy settings result');
        $ee->input = new class {
            /**
             * Return a posted legacy channel identifier through the real Input boundary.
             *
             * @param string $name
             * @return string
             */
            public function get_post($name) {
                if ($name !== 'channel_id') {
                    throw new LogicException('Unexpected legacy Input lookup.');
                }
                return '17';
            }
        };
        $same('17', $fieldtype->getChannelId(), 'EE2 channel input');
    };
    $cases['legacy extension removal preserves unsupported menu storage'] = static function () use ($same, $ee) {
        $extension = new Low\Widont\FluxCapacitor\Base\Ext();
        $ee->db->tables['extensions'] = [['class' => get_class($extension)], ['class' => 'Other_ext']];
        $ee->db->tables['menu_items'] = [['data' => get_class($extension)], ['data' => 'Other_ext']];
        $same(true, $extension->disable_extension(), 'disable result');
        $same([['class' => 'Other_ext']], $ee->db->tables['extensions'], 'only selected extension removed');
        $same(2, count($ee->db->tables['menu_items']), 'EE2 does not query modern menu storage');
    };
    if (version_compare(APP_VER, '2.6.0', '<')) {
        $cases['legacy navigation and all session URL modes'] = static function () use ($same, $ee, $mode) {
            $flux = new Low\Widont\FluxCapacitor\FluxCapacitor();
            $ee->config->values['cp_session_type'] = 'cs';
            $url = $flux->getBaseURL('license', AMP . 'extra=0');
            $same(true, strpos($url, AMP . 'method=license' . AMP . 'extra=0') !== false, 'method and extra variables');
            $same(1, substr_count($url, 'S='), 'session directive remains unique');
            $same(true, strpos($url, $mode === 'legacy-existing-directives' ? 'S=existing' : 'S=fingerprint') !== false, 'fingerprint session mode');
            $ee->config->values['cp_session_type'] = false;
            $url = $flux->getBaseURL();
            $same($mode === 'legacy-existing-directives', strpos($url, 'S=') !== false, 'session-free mode preserves preexisting directive');
            $same(true, strpos($flux->moduleURL('index', null), AMP . 'method=index') !== false, 'nullable URL variables');
            $flux->getNav(['index' => 'Overview', 'https://example.test/help' => 'Help']);
            $same('https://example.test/help', $ee->cp->rightNav['Help'], 'external right navigation');
            $same(true, strpos($ee->cp->rightNav['Overview'], AMP . 'method=index') !== false, 'internal right navigation');
            $flux->getNav([]);
            $same([], $ee->cp->rightNav, 'empty right navigation');
            $ee->functions = new class {
                /**
                 * Expose the legacy formatting callback without substituting bridge behavior.
                 *
                 * @param string $value
                 * @return string
                 */
                public function remove_double_slashes($value) {
                    if ($value !== 'https://example.test//path') {
                        throw new LogicException('Unexpected legacy formatting argument.');
                    }
                    return 'legacy-formatted';
                }
            };
            $same('legacy-formatted', $flux->reduce_double_slashes('https://example.test//path'), 'legacy formatting API');
        };
    }
}
if ($mode === 'legacy-cache') {
    $ee->cache = new LowWidontTests\ContextCache();
    $cases['legacy persistent reads and writes use the native cache backend'] = static function () use ($same, $ee) {
        $flux = new Low\Widont\FluxCapacitor\FluxCapacitor();
        $same(true, $flux->cache('set', 'entry', ['id' => 0]), 'persistent save');
        $same(['id' => 0], $flux->cache('get', 'entry'), 'persistent get');
        $same(['/low_widont/entry' => ['id' => 0]], $ee->cache->values, 'native namespace');
    };
    $cases['legacy persistent delete invalidates the native cache'] = static function () use ($same, $ee) {
        $flux = new Low\Widont\FluxCapacitor\FluxCapacitor();
        $ee->cache->values = ['/low_widont/entry' => 'cached'];
        $flux->cache('delete', 'entry');
        $same(false, $ee->cache->get('/low_widont/entry'), 'deleted persistent entry');
    };
    $cases['legacy persistent clear invalidates the native cache namespace'] = static function () use ($same, $ee) {
        $flux = new Low\Widont\FluxCapacitor\FluxCapacitor();
        $ee->cache->values = ['/low_widont/entry' => 'cached'];
        $flux->cache('clear');
        $same(false, $ee->cache->get('/low_widont/entry'), 'cleared persistent namespace');
    };
}
if ($mode === 'legacy-navigation') {
    $cases['shared navigation delegates to the legacy right navigation API'] = static function () use ($same, $ee, $mirror) {
        require $mirror . 'low_widont/Conduit/McpNav.php';
        Low\Widont\Conduit\McpNav::$items = ['/' => 'Overview', 'https://example.test/help' => 'Help'];
        $navigation = new Low\Widont\Conduit\McpNav();
        $same(['before', 'after'], $navigation->events, 'legacy navigation lifecycle');
        $same(null, $navigation->sidebar, 'legacy navigation has no modern sidebar');
        $same('https://example.test/help', $ee->cp->rightNav['Help'], 'legacy external link');
        $same(true, strpos($ee->cp->rightNav['Overview'], 'method=index') !== false, 'legacy normalized index link');
        $same(true, strpos($ee->cp->rightNav['License'], 'method=license') !== false, 'forced license link');
    };
}
if ($mode === 'theme-root') {
    $cases['explicit theme constants take precedence across bridge implementations'] = static function () use ($same) {
        foreach (['EE2', 'EE3', 'EE4', 'EE5', 'EE6'] as $name) {
            $class = 'Low\\Widont\\FluxCapacitor\\Bridge\\' . $name;
            $bridge = new $class(['module' => 'low_widont', 'module_name' => 'Low Widont']);
            $same('/assets/third_party/low_widont/', $bridge->getAddonThemesDir(), $name . ' explicit themes path');
        }
    };
}
if ($mode === 'updater-legacy') {
    $cases['legacy uninstall removes only the selected module permissions'] = static function () use ($same, $ee) {
        require_once dirname(__DIR__) . '/support/Updater.php';
        $ee->db->tables['modules'] = [['module_id' => 5, 'module_name' => 'Low_widont_fixture'], ['module_id' => 6, 'module_name' => 'Other']];
        $ee->db->tables['actions'] = [['class' => 'Low_widont_fixture'], ['class' => 'Other']];
        $ee->db->tables['module_member_groups'] = [['module_id' => 5], ['module_id' => 6]];
        $ee->db->tables['module_member_roles'] = [['module_id' => 5], ['module_id' => 6]];
        $updater = new Low_widont_fixture_upd();
        $same(true, $updater->uninstall(), 'legacy uninstall result');
        $same([['module_id' => 6]], $ee->db->tables['module_member_groups'], 'selected legacy permissions removed');
        $same([['module_id' => 5], ['module_id' => 6]], $ee->db->tables['module_member_roles'], 'modern role storage untouched');
        $same([['module_id' => 6, 'module_name' => 'Other']], $ee->db->tables['modules'], 'unrelated module retained');
        $same([['class' => 'Other']], $ee->db->tables['actions'], 'unrelated action retained');
    };
}
if ($mode === 'cp' || $mode === 'site') {
    $cases['request-aware constructors and extension menu'] = static function () use ($same, $ee, $mode) {
        $mcp = new Low\Widont\FluxCapacitor\Base\Mcp();
        $same(null, $mcp->nav, 'missing optional navigation stays absent');
        $extension = new Low\Widont\FluxCapacitor\Base\Ext();
        $menu = new class {
            public $items = [];
            /**
             * Capture the extension menu hook's public API.
             *
             * @param string $label
             * @param mixed $url
             * @return void
             */
            public function addItem($label, $url) { $this->items[] = [$label, (string) $url]; }
        };
        $result = $extension->cp_custom_menu($menu);
        $same($mode === 'site' ? true : null, $result, 'menu request guard result');
        $same($mode === 'site' ? [] : [['Low Widon’t', '/addons/settings/low_widont/index']], $menu->items, 'request-scoped menu items');
        new Low\Widont\FluxCapacitor\Base\Tab();
        require_once dirname(__DIR__) . '/support/Fieldtype.php';
        new Low_widont_fixture_ft();
        $same($mode === 'site' ? 0 : 2, count($ee->cp->footer_item), 'licensing footer added only once in CP');
    };
}
if (strpos($mode, 'fieldtype-') === 0) {
    $cases['legacy or installer parent lookup and predeclared version'] = static function () use ($same) {
        require_once dirname(__DIR__) . '/support/Fieldtype.php';
        $fieldtype = new Low_widont_fixture_ft();
        $same('4.1.1', $fieldtype->info['version'], 'fieldtype version');
        $same(true, $fieldtype->accepts_content_type('channel'), 'parent loaded through supported layout');
    };
}
if ($mode === 'updater') {
    $cases['optional extension update and absent handler'] = static function () use ($same) {
        require_once dirname(__DIR__) . '/support/Updater.php';
        $updater = new Low_widont_fixture_upd();
        $same(true, $updater->update('4.0.0'), 'extension update completes');
        $same(['4.0.0'], Low_widont_fixture_ext::$updates, 'extension receives old version');
        $updater->module_name = 'Missing';
        $same(true, $updater->update('4.0.0'), 'missing extension handler is tolerated');
    };
    $cases['extension exceptions do not escape the updater catch'] = static function () use ($same) {
        Low_widont_fixture_ext::$fail = true;
        $updater = new Low_widont_fixture_upd();
        $same(true, $updater->update('4.0.0'), 'extension exception handled');
    };
}
if ($mode === 'missing-navigation-base') {
    $cases['optional addon file is not required to load the shared navigation base'] = static function () use ($same) {
        $same(true, class_exists('Low\\Widont\\FluxCapacitor\\Conduit\\McpNav'), 'navigation base loads');
    };
}
foreach ($cases as $name => $case) {
    try {
        $case();
    } catch (Throwable $exception) {
        $failures[] = ['case' => $name, 'exception' => get_class($exception), 'message' => $exception->getMessage(), 'file' => $exception->getFile(), 'line' => $exception->getLine(), 'trace' => $exception->getTraceAsString()];
    }
}
$reportDirectory = getenv('LOW_WIDONT_CONTEXT_REPORT_DIR') ?: getenv('LOW_WIDONT_REPORT_DIR');
$nativeReport = null;
$dispatchClass = new ReflectionClass('Low\\Widont\\FluxCapacitor\\Bridge\\EE');
$dispatch = ['parent' => $dispatchClass->getParentClass()->getName(),
    'constructor_start_line' => $dispatchClass->getMethod('__construct')->getStartLine()];
if ($collect) {
    $raw = xdebug_get_code_coverage();
    xdebug_stop_code_coverage();
    if ($reportDirectory) {
        if (!is_dir($reportDirectory . '/contexts')) {
            mkdir($reportDirectory . '/contexts', 0700, true);
        }
        $nativeReport = $reportDirectory . '/contexts/' . $mode . '-' . $eeVersion . '-' . bin2hex(random_bytes(4)) . '.json';
        $data = [];
        foreach ($raw as $file => $coverage) {
            if (strpos($file, $mirror . 'low_widont/') !== 0 && strpos($file, 'phar://' . $mirror . 'low_widont/') !== 0 && strpos($file, $root . '/low_widont/') !== 0) {
                continue;
            }
            if (strpos($file, $mirror . 'low_widont/') === 0) {
                $relative = substr($file, strlen($mirror . 'low_widont/'));
                if (!array_key_exists($relative, $provenance)) {
                    continue;
                }
            }
            $coverage['sha256'] = hash_file('sha256', $file);
            $data[$file] = $coverage;
        }
        LowWidontTests\Evidence::write($nativeReport, ['mode' => $mode, 'ee' => APP_VER, 'php' => PHP_VERSION, 'xdebug' => phpversion('xdebug'), 'mirror' => $mirror, 'dispatch' => $dispatch,
            'flags' => XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE | XDEBUG_CC_BRANCH_CHECK,
            'shipped_source_hashes' => $provenance, 'source' => $data]);
    }
}
echo json_encode(['mode' => $mode, 'ee' => APP_VER, 'php' => PHP_VERSION, 'cases' => array_keys($cases), 'assertions' => $assertions,
    'failures' => $failures, 'dispatch' => $dispatch, 'shipped_source_hashes' => $provenance, 'native_report' => $nativeReport], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
