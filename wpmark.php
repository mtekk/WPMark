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
				var_dump($uploadDir);
			?>
			<p class="submit"><a class="button-primary" href="tools.php?page=wpmark&make_cache=1">Setup Cache</a></p>
		</div>
		<?php
	}
}
$wpmark = new wpmark();
