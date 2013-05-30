<?php
/*
=====================================================
 ExpressionEngine - by pMachine
-----------------------------------------------------
 http://www.pmachine.com/
=====================================================
 This plugin was created by Lodewijk Schutte
 - lodewijk@gmail.com
 - http://loweblog.com/
 This work is licensed under a
 Creative Commons Attribution-ShareAlike 2.5 License.
 - http://creativecommons.org/licenses/by-sa/2.5/
=====================================================
 File: pi.widont.php
-----------------------------------------------------
 Purpose: Eliminates 'widows' in your post titles
=====================================================
*/


$plugin_info = array(
						'pi_name'			=> "Widon't",
						'pi_version'		=> '1.0',
						'pi_author'			=> 'Lodewijk Schutte',
						'pi_author_url'	=> 'http://loweblog.com/',
						'pi_description'	=> "Eliminates widows in your post titles by inserting a non-breaking "
                                     . "space between the last two words of a title. Ported from the "
                                     . "<a href='http://www.shauninman.com/plete/2006/08/widont-wordpress-plugin'>Wordpress plugin by Shaun Inman</a>.",
						'pi_usage'			=> Widont::usage()
					);



class Widont {

    var $return_data;    
    
    // ----------------------------------------
    //  Away with widows!
    // ----------------------------------------

    function Widont($str = '')
    {
      global $TMPL;
 
      // Fetch string
      if ($str == '')
      {
        $str = $TMPL->tagdata;
      }
      
      // return new spiffy widonty string
      $this->return_data = preg_replace("|\s+(\w+)$|", '&nbsp;\1', rtrim($str));
    }
    // END
    
// ----------------------------------------
//  Plugin Usage
// ----------------------------------------

// This function describes how the plugin is used.

function usage()
{
ob_start(); 
?>
What is a widow? In typesetting, a widow is a single word on a line by itself at the end
of a paragraph and is considered bad style.

To use this plugin, wrap anything you want to be processed by it between these tag pairs.
The title of a weblog entry works best:

{exp:widont}{title}{/exp:widont}

<?php
$buffer = ob_get_contents();
	
ob_end_clean(); 

return $buffer;
}
// END
}
// END CLASS
?>