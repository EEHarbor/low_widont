<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

$plugin_info = array(
	'pi_name'			=> "Low Widon&rsquo;t",
	'pi_version'		=> '2.0',
	'pi_author'			=> 'Lodewijk Schutte ~ Low',
	'pi_author_url'		=> 'http://loweblog.com/freelance/article/ee-widont-plugin/',
	'pi_description'	=> 'Eliminates widows in your post titles by inserting a non-breaking space between the last two words of, for example, a title.',
	'pi_usage'			=> Low_widont::usage()
);

/**
* Low Widon't Plugin class (based on the WordPress Widon't plugin by Shaun Inman)
*
* @package			low-widont-ee2_addon
* @version			2.0
* @author			Lodewijk Schutte ~ Low <low@loweblog.com>
* @link				http://loweblog.com/freelance/article/ee-widont-plugin/
* @license			http://creativecommons.org/licenses/by-sa/3.0/
*/
class Low_widont {

	/**
	* Plugin return data
	*
	* @var	string
	*/
	var $return_data;

	// --------------------------------------------------------------------

	/**
	* PHP4 Constructor
	*
	* @see	__construct()
	*/
	function Low_widont($str = '')
	{
		$this->__construct($str);
	}

	// --------------------------------------------------------------------

	/**
	* PHP 5 Constructor
	*
	* @param	string	$str	String to apply the widont algorithm to
	* @return	string
	*/
	function __construct($str = '')
	{
		/** -------------------------------------
		/**  Get global instance
		/** -------------------------------------*/

		$this->EE =& get_instance();

		/** -------------------------------------
		/**  Get string to work with
		/** -------------------------------------*/
		
		$this->return_data = ($str) ? $str : $this->EE->TMPL->tagdata;

		/** -------------------------------------
		/**  Apply widon't algorithm
		/** -------------------------------------*/

		$this->_marry();
		
		/** -------------------------------------
		/**  Return new spiffy widonty string
		/** -------------------------------------*/
		
		return $this->return_data;
	}

	// --------------------------------------------------------------------

	/**
	* Marry - applies widon't algorithm to string
	*
	* @access	private
	*/
	function _marry()
	{
		$this->return_data = preg_replace('/\s+(\S+)$/', '&#160;$1', rtrim($this->return_data));
	}

	// --------------------------------------------------------------------

	/**
	* Plugin Usage
	*
	* @return	string
	*/
	function usage()
	{
		ob_start(); 
		?>
			What is a widow? In typesetting, a widow is a single word on a line by itself at the end
			of a paragraph and is considered bad style.

			To use this plugin, wrap anything you want to be processed by it between these tag pairs.
			The title of a weblog entry works best:

			{exp:low_widont}{title}{/exp:low_widont}
		<?php
		$buffer = ob_get_contents();
	
		ob_end_clean(); 

		return $buffer;
	}

	// --------------------------------------------------------------------

}
// END CLASS

/* End of file pi.low_widont.php */