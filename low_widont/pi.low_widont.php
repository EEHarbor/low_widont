<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

$plugin_info = array(
	'pi_name'        => "Low Widon&rsquo;t",
	'pi_version'     => '2.1.0',
	'pi_author'      => 'Lodewijk Schutte ~ Low',
	'pi_author_url'  => 'http://gotolow.com/addons/low-widont',
	'pi_description' => 'Eliminates typographical widows.',
	'pi_usage'       => '{exp:low_widont}Your text here{/exp:low_widont}'
);

/**
 * < EE 2.6.0 backward compat
 */
if ( ! function_exists('ee'))
{
	function ee()
	{
		static $EE;
		if ( ! $EE) $EE = get_instance();
		return $EE;
	}
}

/**
 * Low Widon't Plugin class (based on the WordPress Widon't plugin by Shaun Inman)
 *
 * @package        low_widont
 * @author         Lodewijk Schutte <hi@gotolow.com>
 * @link           http://gotolow.com/addons/low-widont
 * @license        http://creativecommons.org/licenses/by-sa/3.0/
 */
class Low_widont {

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