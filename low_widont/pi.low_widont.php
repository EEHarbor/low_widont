<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * Low Widon't Plugin class (based on the WordPress Widon't plugin by Shaun Inman)
 *
 * @package        low_widont
 * @author         Lodewijk Schutte <hi@gotolow.com>
 * @link           http://gotolow.com/addons/low-widont
 * @license        http://creativecommons.org/licenses/by-sa/3.0/
 */

include_once "addon.setup.php";
use Low\Widont\FluxCapacitor\Base\Pi;

class Low_widont extends Pi
{

    // --------------------------------------------------------------------
    // PROPERTIES
    // --------------------------------------------------------------------

    /**
     * Plugin return data
     *
     * @var        string
     */
    public $return_data;

    // --------------------------------------------------------------------
    // METHODS
    // --------------------------------------------------------------------

    /**
     * Constructor
     *
     * @param      string
     * @return     string
     */
    public function __construct($str = '')
    {
        parent::__construct();
        // -------------------------------------------
        // Get string to work with
        // -------------------------------------------

        $this->return_data = ($str) ? $str : ee()->TMPL->tagdata;

        // -------------------------------------------
        // Apply widon't algorithm
        // -------------------------------------------

        $this->return_data = preg_replace('/\s+(\S+)$/', '&#160;$1', rtrim($this->return_data));

        // -------------------------------------------
        // Return for good measure
        // -------------------------------------------

        return $this->return_data;
    }

    // --------------------------------------------------------------------
}
// END CLASS

/* End of file pi.low_widont.php */
