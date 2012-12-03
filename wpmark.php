<?php
/*
Plugin Name: WPMark
Plugin URI: http://mtekk.us/code/wpmark/
Description: A WordPress benchmarking plugin
Version: 1.0.0
Author: John Havlik
Author URI: http://mtekk.us/
License: GPL2
TextDomain: wpmark
DomainPath: /languages/
*/
/*  Copyright 2012  John Havlik  (email : mtekkmonkey@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
require_once(dirname(__FILE__) . '/includes/block_direct_access.php');
//Do a PHP version check, require 5.2 or newer
if(version_compare(phpversion(), '5.2.0', '<'))
{
	//Only purpose of this function is to echo out the PHP version error
	function wpmark_phpold()
	{
		printf('<div class="error"><p>' . __('Your PHP version is too old, please upgrade to a newer version. Your version is %1$s, WPMark requires %2$s', 'wpmark') . '</p></div>', phpversion(), '5.2.0');
	}
	//If we are in the admin, let's print a warning then return
	if(is_admin())
	{
		add_action('admin_notices', 'wpmark_phpold');
	}
	return;
}
/**
 * The plugin class
 */
class wpmark
{
	protected $version = '1.0.0';
	protected $full_name = 'WordMark';
	protected $short_name = 'WPMark';
	protected $access_level = 'manage_options';
	protected $identifier = 'wpmark';
	protected $unique_prefix = 'wpmark';
	protected $plugin_basename = 'wpmark/wpmark.php';
	protected $support_url = 'http://mtekk.us/archives/wordpress/plugins-wordpress/wpmark-';
	protected $message;
	protected $dictionary;
	protected $dictionary150;
	/**
	 * Class default constructor
	 */
	function __construct()
	{
		//Initilizes l10n domain
		$this->local();
		//WordPress Admin interface hook
		add_action('admin_menu', array($this, 'add_page'));
		//add_action('wp_loaded', array($this, 'wp_loaded'));
	}
	/**
	 * Adds the adminpage the menu and the nice little settings link
	 */
	function add_page()
	{
		//Add the submenu page to "settings" menu
		$hookname = add_submenu_page('tools.php', __($this->full_name, $this->identifier), $this->short_name, $this->access_level, $this->identifier, array($this, 'tools_page'));
		// check capability of user to manage options (access control)
		if(current_user_can($this->access_level))
		{
			//Register admin_print_styles-$hookname callback
			add_action('admin_print_styles-' . $hookname, array($this, 'admin_styles'));
			//Register admin_print_scripts-$hookname callback
			add_action('admin_print_scripts-' . $hookname, array($this, 'admin_scripts'));
			//Register Help Output
			//add_action('load-' . $hookname, array($this, 'help'));
		}
	}
	/**
	 * Initilizes localization textdomain for translations (if applicable)
	 * 
	 * Will conditionally load the textdomain for translations. This is here for
	 * plugins that span multiple files and have localization in more than one file
	 * 
	 * @return void
	 */
	function local()
	{
		global $l10n;
		// the global and the check might become obsolete in
		// further wordpress versions
		// @see https://core.trac.wordpress.org/ticket/10527		
		if(!isset($l10n[$this->identifier]))
		{
			load_plugin_textdomain($this->identifier, false, $this->identifier . '/languages');
		}
	}
	/**
	 * enqueue's the tab style sheet on the settings page
	 */
	function admin_styles()
	{
		wp_enqueue_style('mtekk_adminkit_tabs');
	}
	/**
	 * Prints to screen all of the messages stored in the message member variable
	 */
	function message()
	{
		if(count($this->message))
		{
			//Loop through our message classes
			foreach($this->message as $key => $class)
			{
				//Loop through the messages in the current class
				foreach($class as $message)
				{
					printf('<div class="%s"><p>%s</p></div>', $key, $message);	
				}
			}
		}
		$this->message = array();
	}
	/**
	 * enqueue's the tab js and translation js on the settings page
	 */
	function admin_scripts()
	{
		//Enqueue ui-tabs
		wp_enqueue_script('jquery-ui-tabs');
		//Enqueue the admin tabs javascript
		wp_enqueue_script('mtekk_adminkit_tabs');
		//Load the translations for the tabs
		wp_localize_script('mtekk_adminkit_tabs', 'objectL10n', array(
			'mtad_uid' => 'wpmark',
			'mtad_import' => __('Import', 'breadcrumb-navxt'),
			'mtad_export' => __('Export', 'breadcrumb-navxt'),
			'mtad_reset' => __('Reset', 'breadcrumb-navxt'),
		));
	}
	function findEncoding($content)
	{
		//Find the charset meta attribute
		preg_match_all('~charset\=.*?(\'|\"|\s)~i', $content, $matches);
		//Trim out everything we don't need
		$matches = preg_replace('/(charset|\=|\'|\"|\s)/', '', $matches[0]);
		//Return the charset in uppercase so that mb_convert_encoding can work it's magic
		if(strtoupper($matches[0]) == '')
		{
			return 'auto';
		}
		else
		{
			return strtoupper($matches[0]);
		}
	}
	function getContent($url, $referer = null, $range = null)
	{
		if(function_exists('curl_init'))
		{
			$curlOpt = array(
				CURLOPT_RETURNTRANSFER	=> true,		// Return web page
				CURLOPT_HEADER			=> false,		// Don't return headers
				CURLOPT_FOLLOWLOCATION	=> !ini_get('safe_mode'),		// Follow redirects, if not in safemode
				CURLOPT_ENCODING		=> '',			// Handle all encodings
				CURLOPT_USERAGENT		=> $this->opt['Scurl_agent'],		// Useragent
				CURLOPT_AUTOREFERER		=> true,		// Set referer on redirect
				CURLOPT_FAILONERROR		=> true,		// Fail silently on HTTP error
				CURLOPT_CONNECTTIMEOUT	=> $this->opt['acurl_timeout'],	// Timeout on connect
				CURLOPT_TIMEOUT			=> $this->opt['acurl_timeout'],	// Timeout on response
				CURLOPT_MAXREDIRS		=> 3,			// Stop after x redirects
				CURLOPT_SSL_VERIFYHOST	=> 0            // Don't verify ssl
			);
			//Conditionally set range, if passed in
			if($range !== null)
			{
				$curlOpt[CURLOPT_RANGE] = $range;
			}
			//Conditionally set referer, if passed in
			if($referer !== null)
			{
				$curlOpt[CURLOPT_REFERER] = $referer;
			}
			//Instantiate a CURL context
			$context = curl_init($url);
			//Set our options
			curl_setopt_array($context, $curlOpt); 
			//Get our content from CURL
			$content = curl_exec($context);
			//Get any errors from CURL
			$this->error = curl_error($context);
			//Close the CURL context
			curl_close($context);
			//Deal with CURL errors
			if(empty($content))
			{
				return false;
			}
			return $content;
		}
	}
	function grab_resources()
	{
		$file = 'index.html';
		if($content = $this->getContent('wordmark'))
		{
			//Convert to UTF-8
			return array(array('file' => $file, 'contents' => mb_convert_encoding($content, "UTF-8", $this->findEncoding($content))));
		}
	}
	function setup_cache()
	{
		//Get the uploads directory
		$uploadDir = wp_upload_dir();
		//Check to ensure the upload directory is writable
		if(!isset($uploadDir['path']) || !is_writable($uploadDir['path']))
		{
			//Return early if not writable
			return false;
		}
		//Let's check for the wpmark_cache directory
		$cache = $uploadDir['basedir'] . '/wpmark_cache';
		if(!is_dir($cache))
		{
			//If it doesn't exist, let's make it
			mkdir($cache, 0775);
		}
		//Ensure the directory is writable
		if(is_writable($cache))
		{
			//Let's do our thing...
			$pages = $this->grab_resources();
			//Loop around pages
			foreach($pages as $key => $page)
			{
				//Open a file resource for writing, destroy previous copy if exists
				$resource = fopen($cache . '/' . $page['file'], 'w+');
				//If we sucessfully opened it, write the contents
				if($resource)
				{
					fwrite($resource, $page['contents']);
				}
				//Be good and cleanup after ourseleves
				fclose($resource);
			}
		}
	}
	/**
	 * Uses the polar form of the Box-Muller transformation which 
	 * is both faster and more robust numerically than basic Box-Muller
	 * transform. To speed up repeated RNG computations, two random values  
	 * are computed after the while loop and the second one is saved and 
	 * directly used if the method is called again.
	 *
	 * @see http://www.taygeta.com/random/gaussian.html
	 *
	 * @return single normal deviate
	 */
	function lognormal_rng()
	{
		//Values picked to match distribution found by Stuart Brown
		//@see http://modernl.com/article/how-long-is-the-ideal-blog-post
		$variance = 0.8;
		$mean = 6;
		do
		{
			$r1 = mt_rand() / mt_getrandmax();
			$r2 = mt_rand() / mt_getrandmax();          
			$x1 = 2.0 * $r1 - 1.0; // between -1.0 and 1.0
			$x2 = 2.0 * $r2 - 1.0; // between -1.0 and 1.0
			$w = $x1 * $x1 + $x2 * $x2;      
		}
		while ($w >= 1.0);    
		$w = sqrt((-2.0 * log($w)) / $w);
		$y1 = $x1 * $w;
		return floor(exp($mean + $y1 * sqrt($variance)));
	}
	function load_dictionary()
	{
		if(count($this->dictionary) == 0)
		{
			$this->dictionary = file(dirname(__FILE__) . '/5k_top_words_english.txt');
		}
		if(count($this->dictionary150) == 0)
		{
			$this->dictionary150 = array_slice($this->dictionary, (count($this->dictionary) - 150));
		}
	}
	function generate_tags()
	{
		$this->load_dictionary();
		$length = rand(2,7);
		$tags = array();
		for($i = 0; $i < $length; $i++)
		{
			$tags[] = ucfirst(trim($this->dictionary150[array_rand($this->dictionary150)]));
		}
		return $tags;
	}
	function generate_title()
	{
		$this->load_dictionary();
		$length = rand(3,15);
		$title = "";
		for($i = 0; $i < $length; $i++)
		{
			$title .= trim($this->dictionary[array_rand($this->dictionary)]) . ' ';
		}
		return ucwords(trim($title));
	}
	function generate_content()
	{
		$this->load_dictionary();
		$words = $this->lognormal_rng();
		$contents = "<p>";
		$sentence = "";
		$last_period = -1;
		$last_newline = -1;
		$j = 0;
		for($i = 0; $i < $words; $i++)
		{
			$word = trim($this->dictionary[array_rand($this->dictionary)]);
			if($last_period + 1 == $i)
			{
				$word = ucfirst($word);
			}
			if($i - $last_period > rand(1,20))
			{
				$last_period = $i;
				$sentence .= $word . ". ";
				$j++;
				if($j - $last_newline > rand(1,20))
				{
					$last_newline = $j;
					$contents .= $sentence . "</p><p>";
					$sentence = "";
				}
			}
			else
			{
				$sentence .= $word . " ";
			}
		}
		//If we exited before the last paragraph/sentence was added, do that now.
		if($last_newline < $words)
		{
			$contents .= trim($sentence);
			if($last_period + 1 < $words)
			{
				$contents .= '.';
			}
		}
		return $contents . '</p>';
	}
	function tools_page()
	{
		?>
		<div class="wrap"><div id="icon-tools" class="icon32"></div><h2><?php _e('WPMark Control Panel', 'wpmark'); ?></h2>
		<?php
		if(isset($_GET['make_cache']))
		{
			$this->setup_cache();
		}
		//We exit after the version check if there is an action the user needs to take before saving settings
		/*if(!$this->version_check(get_option($this->unique_prefix . '_version')))
		{
			return;
		}
		*/?>
			<h3><?php _e('Diagnostics', 'wpmark')?></h3>
			<?php
				$uploadDir = wp_upload_dir();
				if(!isset($uploadDir['path']) || !is_writable($uploadDir['path']))
				{
					//Let the user know their directory is not writable
					$this->message['error'][] = __('WordPress uploads directory is not writable, cache test will be disabled.', 'wpmark');
				}
				//Too late to use normal hook, directly display the message
				$this->message();
				srand(158723957239);
				mt_srand(1028415237357);
				echo "<h3>" . $this->generate_title() . "</h3>";
				echo $this->generate_content();
				echo implode(', ', $this->generate_tags());
				echo "<h3>" . $this->generate_title() . "</h3>";
				echo $this->generate_content();
				echo implode(', ', $this->generate_tags()) . "<br />";
				echo implode(', ', $this->dictionary150);
				/*mt_srand(1028415237357);
				for($i=0; $i < 100; $i++)
				{
					echo $this->lognormal_rng() . "<br />";
				}*/
			?>
			<p class="submit"><a class="button-primary" href="tools.php?page=wpmark&make_cache=1">Setup Cache</a></p>
		</div>
		<?php
	}
}
$wpmark = new wpmark();
