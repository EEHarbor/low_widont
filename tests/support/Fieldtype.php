<?php

class Low_widont_fixture_ft extends Low\Widont\FluxCapacitor\Base\Ft
{
}

/**
 * Match the native URL helper's request URI delegation.
 *
 * @return string
 */
function uri_string()
{
    return ee()->uri->uri_string();
}
