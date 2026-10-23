<?php

declare(strict_types=1);

namespace LowWidontTests;

final class Url
{
    private $path;
    private $variables;

    /**
     * Retain a CP route and query variables for deterministic URL assertions.
     *
     * @param string $path
     * @param array $variables
     * @return void
     */
    public function __construct($path, array $variables = [])
    {
        $this->path = '/' . ltrim($path, '/');
        $this->variables = $variables;
    }

    /**
     * Compile a CP URL using the stored route and query variables.
     *
     * @return string
     */
    public function compile()
    {
        return $this->path . ($this->variables ? '?' . http_build_query($this->variables) : '');
    }

    /**
     * Preserve the native CP URL object's string conversion surface.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->compile();
    }
}

final class UrlFactory
{
    public $calls = [];

    /**
     * Create a URL while retaining the exact service arguments.
     *
     * @param string $path
     * @param array $variables
     * @return Url
     */
    public function make($path, array $variables = [])
    {
        $this->calls[] = [$path, $variables];
        return new Url($path, $variables);
    }
}

final class Sidebar
{
    public $headers = [];

    /**
     * Return the Sidebar instance created by the EE service.
     *
     * @return self
     */
    public function make()
    {
        return $this;
    }

    /**
     * Append a header in source order and expose its fluent link/button API.
     *
     * @param string $title
     * @return Header
     */
    public function addHeader($title)
    {
        $header = new Header($title);
        $this->headers[] = $header;
        return $header;
    }
}

final class Header
{
    public $title;
    public $url;
    public $buttons = [];
    public $active = false;

    /**
     * Retain the visible sidebar label.
     *
     * @param string $title
     * @return void
     */
    public function __construct($title)
    {
        $this->title = $title;
    }

    /**
     * Assign the native URL object or external link string.
     *
     * @param string|Url $url
     * @return self
     */
    public function withUrl($url)
    {
        $this->url = $url;
        return $this;
    }

    /**
     * Append a labeled toolbar button and its destination.
     *
     * @param string $title
     * @param string|Url $url
     * @return self
     */
    public function withButton($title, $url)
    {
        $this->buttons[] = [$title, $url];
        return $this;
    }

    /**
     * Mark this header active through the standard fluent API.
     *
     * @return self
     */
    public function isActive()
    {
        $this->active = true;
        return $this;
    }
}
