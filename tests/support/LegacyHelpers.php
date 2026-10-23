<?php

/**
 * Delegate a legacy helper call to an explicitly configured host collaborator.
 *
 * @param string $text
 * @return string
 */
function url_title($text)
{
    return call_user_func(\LowWidontTests\Environment::$services['legacy:url_title'], $text);
}

/**
 * Delegate URL preparation through the legacy host helper contract.
 *
 * @param string $text
 * @return string
 */
function prep_url($text = '')
{
    return call_user_func(\LowWidontTests\Environment::$services['legacy:prep_url'], $text);
}

/**
 * Preserve URI schemes while matching EE's pure duplicate-slash helper.
 *
 * @param string $text
 * @return string
 */
function reduce_double_slashes($text)
{
    return preg_replace('#([^/:])/+#', '\\1/', $text);
}
