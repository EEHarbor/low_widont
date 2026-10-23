<?php

declare(strict_types=1);

namespace LowWidontTests;

final class ContextConfig
{
    public $values = ['site_id' => 1, 'base_url' => 'https://example.test', 'theme_folder_url' => '/themes/', 'cp_session_type' => 's', 'admin_session_type' => 's'];

    /**
     * Preserve the EE config lookup's absent false sentinel.
     *
     * @param string $name
     * @return mixed
     */
    public function item($name)
    {
        return $this->values[$name] ?? false;
    }

    /**
     * Return a configured URL/path with its trailing slash.
     *
     * @param string $name
     * @return string
     */
    public function slash_item($name)
    {
        return rtrim($this->values[$name], '/') . '/';
    }
}

final class ContextSession
{
    public $cache = [];
    public $flashdata = [];

    /**
     * Supply deterministic session identifiers for legacy URL directives.
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function userdata($name, $default = null)
    {
        return ['session_id' => 'session-id', 'fingerprint' => 'fingerprint'][$name] ?? $default;
    }

    /**
     * Store legacy flash data for side-effect assertions.
     *
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function set_flashdata($name, $value)
    {
        $this->flashdata[$name] = $value;
    }
}

final class ContextCp
{
    public $footer_item = [];
    public $breadcrumbs = [];
    public $rightNav = [];

    /**
     * Append a footer item through the EE legacy CP API.
     *
     * @param string $html
     * @return void
     */
    public function add_to_foot($html)
    {
        $this->footer_item[] = $html;
    }

    /**
     * Return all existing footer fragments for licensing idempotency checks.
     *
     * @return array
     */
    public function get_foot()
    {
        return $this->footer_item;
    }

    /**
     * Record a module breadcrumb without coercing its visible title.
     *
     * @param mixed $url
     * @param string $title
     * @return void
     */
    public function set_breadcrumb($url, $title)
    {
        $this->breadcrumbs[] = [(string) $url, $title];
    }

    /**
     * Replace the EE2 right-navigation map.
     *
     * @param array $items
     * @return void
     */
    public function set_right_nav(array $items)
    {
        $this->rightNav = $items;
    }
}

final class ContextUri
{
    public $segments = ['cp', 'addons', 'settings', 'low_widont'];

    /**
     * Return request URI segments in their existing order.
     *
     * @return array
     */
    public function segment_array()
    {
        return $this->segments;
    }

    /**
     * Return the relative CP route used by the global URI helper.
     *
     * @return string
     */
    public function uri_string()
    {
        return implode('/', $this->segments);
    }
}

final class ContextLoader
{
    public $views = [];

    /**
     * Record-free helper loading uses helper contracts installed by the host fixture.
     *
     * @param string $name
     * @return void
     */
    public function helper($name)
    {
    }

    /**
     * Keep library loading within the explicitly installed host collaborators.
     *
     * @param string $name
     * @return void
     */
    public function library($name)
    {
    }

    /**
     * Schema service initialization uses the fixture's existing dbforge object.
     *
     * @return void
     */
    public function dbforge()
    {
    }

    /**
     * Preserve legacy view arguments while returning deterministic rendered content.
     *
     * @param string $view
     * @param array $variables
     * @param bool $return
     * @return string
     */
    public function view($view, array $variables, $return)
    {
        $this->views[] = [$view, $variables, $return];
        return 'rendered:' . $view;
    }
}

final class ContextCache
{
    public $values = [];

    /**
     * Return a persistent cache hit or the EE cache miss sentinel.
     *
     * @param string $key
     * @return mixed
     */
    public function get($key)
    {
        return $this->values[$key] ?? false;
    }

    /**
     * Persist the payload under the caller's native cache namespace.
     *
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public function save($key, $value)
    {
        $this->values[$key] = $value;
        return true;
    }

    /**
     * Delete one cache entry or a complete namespace using EE prefix semantics.
     *
     * @param string $key
     * @return bool
     */
    public function delete($key)
    {
        foreach (array_keys($this->values) as $entry) {
            if ($entry === $key || strpos($entry, rtrim($key, '/') . '/') === 0) {
                unset($this->values[$entry]);
            }
        }
        return true;
    }
}
