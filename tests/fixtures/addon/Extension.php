<?php

class Low_widont_fixture_ext
{
    public static $updates = [];
    public static $fail = false;

    /**
     * Record the update invocation that a real optional extension receives.
     *
     * @param string $version
     * @return void
     * @throws RuntimeException
     */
    public function updateExtension($version)
    {
        if (self::$fail) {
            throw new RuntimeException('Extension update failed');
        }
        self::$updates[] = $version;
    }
}
