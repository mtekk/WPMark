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
/*  Copyright 2012-2014  John Havlik  (email : john.havlik@mtekk.us)

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
if(!function_exists('__return_one'))
{
	function __return_one()
	{
		return '1';
	}
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
		add_action('init', array($this, 'init'));
		//WordPress Admin interface hook
		add_action('admin_menu', array($this, 'add_page'));
	}
	function init()
	{
		add_filter('comment_flood_filter', __return_false(), 99);
		add_filter('pre_comment_approved', __return_one(), 99);
	}
	function wp_enqueue_scripts()
	{
		wp_dequeue_style('twentytwelve-fonts');
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
	function find_encoding($content)
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
	function get_content($url, $referer = null, $range = null)
	{
		if(function_exists('curl_init'))
		{
			$curl_opt = array(
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
				$curl_opt[CURLOPT_RANGE] = $range;
			}
			//Conditionally set referer, if passed in
			if($referer !== null)
			{
				$curl_opt[CURLOPT_REFERER] = $referer;
			}
			//Instantiate a CURL context
			$context = curl_init($url);
			//Set our options
			curl_setopt_array($context, $curl_opt); 
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
		$pages = array('171', '69', '161', '225', '355', '331', '117', '395', '219', '103');
		$resources = array();
		if($content = $this->get_content(site_url()))
		{
			//Convert to UTF-8
			$resources[] = array('file' => 'index.html', 'contents' => mb_convert_encoding($content, "UTF-8", $this->find_encoding($content)));
		}
		foreach($pages as $page)
		{
			if($content = $this->get_content(site_url() . '?p=' . $page))
			{
				//Convert to UTF-8
				$resources[] = array('file' => 'post' . $page . '.html', 'contents' => mb_convert_encoding($content, "UTF-8", $this->find_encoding($content)));
			}
		}
		return $resources;
	}
	function setup_cache()
	{
		//Get the uploads directory
		$upload_dir = wp_upload_dir();
		//Check to ensure the upload directory is writable
		if(!isset($upload_dir['path']) || !is_writable($upload_dir['path']))
		{
			//Return early if not writable
			return false;
		}
		//Let's check for the wpmark_cache directory
		$cache = $upload_dir['basedir'] . '/wpmark_cache';
		if(!is_dir($cache))
		{
			//If it doesn't exist, let's make it
			mkdir($cache, 0775);
		}
		//Ensure the directory is writable
		if(is_writable($cache))
		{
			set_time_limit(0);
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
	function setup_posts()
	{
		//Get the upload location
		$upload_dir = wp_upload_dir();
		//Get the existing categories
		$args = array(
			'hide_empty' => false,
			'hierarchical' => true,
			'exclude' => '1');
		$existing_categories = get_categories($args);
		$stime = time();
		//Set our rand and mt_rand seeds
		srand(158723957239);
		mt_srand(1028415237357);
		//Let's create 500 posts
		for($i = 0; $i < 500; $i++)
		{
			set_time_limit(0);
			$category = $existing_categories[array_rand($existing_categories)]->term_id;
			//Our new post array
			$post = array(
				'post_type' => 'post',
				'post_status' => 'publish',
				'post_title' =>  wp_strip_all_tags($this->generate_title()),
				'post_content' => wp_kses($this->generate_content(), wp_kses_allowed_html('post')),
				'tags_input' => implode(', ', $this->generate_tags()),
				'tax_input' => array('category' => array($category)),
				'post_date' => date('Y-m-d H:i:s', $stime - ($i * 86400)),
				'post_date_gmt' => date('Y-m-d H:i:s', $stime - ($i * 86400))
			);
			$post_id = wp_insert_post($post);
			//Make sure we use a unique filename for the featured image
			$filename = wp_unique_filename($upload_dir['path'], 'wpmark_post_featured_image.png');
			//Generate the image/attachment and set it to be the featured image for our post
			$this->generate_featured_image($post_id, $upload_dir['path'] . "/$filename");
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
	/**
	 * Generates a random image
	 * 
	 * @param string $filename
	 * @param int $width
	 * @param int $height
	 */
	function generate_random_image($filename, $width = 624, $height = 351, $type = 'png')
	{
		//Create a GD image
		$image = imagecreatetruecolor($width, $height);
		$i = 0;
		while($i < $width)
		{
			//Generate our strip width
			$strip_width = rand(4,32);
			//Generate our color
			$red = mt_rand(0, 255);
			$green = mt_rand(0, 255);
			$blue = mt_rand(0, 255);
			$color = imagecolorallocate($image, $red, $green, $blue);
			//Paint with the color
			imagefilledrectangle($image, $i - 1, 0, $i + $strip_width - 1, $height - 1, $color);
			//Increment by out strip height
			$i += $strip_width;
		}
		if($type == 'png')
		{
			imagepng($image, $filename, 9);
		}
		else
		{
			imagejpeg($image, $filename, 90);
		}
	}
	/**
	 * This makes an attachment, needs real file location and parent to attac hto
	 * 
	 * @param string $filename The file to attach
	 * @param int $parent The ID of the parent post (generic type) to attach the file to
	 * @return int $attach_id
	 */
	function generate_attachment($parent, $filename)
	{
		$wp_filetype = wp_check_filetype(basename($filename), null);
		$wp_upload_dir = wp_upload_dir();
		$attachment = array(
			'guid' => $wp_upload_dir['baseurl'] . _wp_relative_upload_path($filename), 
			'post_mime_type' => $wp_filetype['type'],
			'post_title' => preg_replace('/\.[^.]+$/', '', basename($filename)),
			'post_content' => '',
			'post_status' => 'inherit'
		);
		$attach_id = wp_insert_attachment($attachment, $filename, $parent);
		$attach_data = wp_generate_attachment_metadata($attach_id, $filename);
		wp_update_attachment_metadata($attach_id, $attach_data);
		return $attach_id;
	}
	function generate_featured_image($post_id, $filename)
	{
		//First we must generate the file
		$this->generate_random_image($filename, 624, 351);
		//Now let's attach it to our post
		$attachment_id = $this->generate_attachment($post_id, $filename);
		//Now let's set it as the featured image for the post
		set_post_thumbnail($post_id, $attachment_id);
	}
	function generate_categories()
	{
		srand(158723957236);
		mt_srand(1028415237357);
		$this->load_dictionary();
		$top_dictionary = array_slice($this->dictionary, (count($this->dictionary) - 300), 50);
		$child_dictionary = array_slice($this->dictionary, (count($this->dictionary) - 250), 100);
		//Let's go with 9 top categories
		for($i = 0; $i < 9; $i++)
		{
			//Create categories
			$parent_id = wp_create_category(ucfirst(trim($top_dictionary[array_rand($top_dictionary)])));
			//Pick a random number of 0 to 7 subcategories
			$length = rand(0, 7);
			for($j = 0; $j < $length; $j++)
			{
				//Create our child category
				wp_create_category(ucfirst(trim($child_dictionary[array_rand($child_dictionary)])), $parent_id);
			}
		}
		//Have to do this to update the category term cache, shouldn't have to do this, it's a WP bug
		delete_option('category_children');
		_get_term_hierarchy('category');
		//clean_term_cache($parent_id, 'category', true);
	}
	function generate_tags()
	{
		$this->load_dictionary();
		$length = rand(2, 7);
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
		$length = rand(3, 15);
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
			if($i - $last_period > rand(1, 20))
			{
				$last_period = $i;
				$sentence .= $word . ". ";
				$j++;
				if($j - $last_newline > rand(1, 20))
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
	function check_categories()
	{
		//Have to run this to mimic the real category generator
		srand(158723957236);
		mt_srand(1028415237357);
		$this->load_dictionary();
		//Setup our parent and child dictionaries as independent subsets of the main dictionary
		$top_dictionary = array_slice($this->dictionary, (count($this->dictionary) - 300), 50);
		$child_dictionary = array_slice($this->dictionary, (count($this->dictionary) - 250), 100);
		//Generate the categories that we should have
		$categories = array();
		for($i = 0; $i < 9; $i++)
		{
			//Create categories
			$categories[] = ucfirst(trim($top_dictionary[array_rand($top_dictionary)]));
			//Pick a random number of 0 to 7 subcategories
			$length = rand(0, 7);
			for($j = 0; $j < $length; $j++)
			{
				//Create our child category
				$categories[] = ucfirst(trim($child_dictionary[array_rand($child_dictionary)]));
			}
		}
		//Get the existing categories
		$args = array(
			'hide_empty' => false,
			'hierarchical' => true,
			'exclude' => '1');
		$existing_categories = get_categories($args);
		//Start by ensuring we have the same number of categories as expected categories
		if(count($existing_categories) === count($categories))
		{
			//Check to make sure the categories match, have to traverse by what we have due to the structure
			foreach($existing_categories as $existing_category)
			{
				//If we can't find this category in the expected list, return false
				if(!in_array($existing_category->name, $categories))
				{
					return false;
				}
			}
			//If we made it out of the loop then the categories should match (same number, all found categories exist in expected list)
			return true;
		}
		//Didn't pass
		return false;
	}
	/*
	 * Checks to ensure the number of posts is over 2 (default install)
	 */
	function check_posts()
	{
		//Get all of the posts
		$posts = get_posts(array('numberposts' => 10));
		if(count($posts) > 3)
		{
			return true;
		}
		else
		{
			return false;
		}
	}
	function twentytwelve_mods()
	{
		//Get the upload location
		$upload_dir = wp_upload_dir();
		//Get the theme mods option
		$theme_mods = get_option('theme_mods_twentytwelve');
		//Make sure we use a unique filename for the featured image
		$filename = wp_unique_filename($upload_dir['path'], 'wpmark_twentytwelve_header_image.jpg');
		//First we must generate the file, using JPEG as most headers will be that type (even if it's less efficient)
		$this->generate_random_image($upload_dir['path'] . "/$filename", 1440, 375, 'jpeg');
		//Now let's 'attach' it to our post
		$attachment_id = $this->generate_attachment(0, $upload_dir['path'] . "/$filename");
		//Update our data
		$theme_mods['header_image'] = $upload_dir['url'] . "/$filename";
		$theme_mods['header_textcolor'] = '444';
		$theme_mods['header_image_data'] = array(
			'attachment_id' => $attachment_id,
			'url' => $theme_mods['header_image'],
			'thumbnail_url' => $theme_mods['header_image'],
			'width' => 1440,
			'height' => 375
		);
		//Update the setting
		update_option('theme_mods_twentytwelve', $theme_mods);
	}
	function wordpress_mods()
	{
		update_option('comments_notify', false);
		update_option('moderation_notify', false);
		update_option('page_comments', true);
		update_option('comments_per_page', 20);
		update_option('posts_per_page', 10);
	}
	function tools_page()
	{
		?>
		<div class="wrap"><div id="icon-tools" class="icon32"></div><h2><?php _e('WPMark Control Panel', 'wpmark'); ?></h2>
		<?php
		//Process a make posts request, don't process if we have all of our categories
		if(isset($_GET['make_cats']) && !$this->check_categories())
		{
			$this->generate_categories();
		}
		//Process a make posts request
		if(isset($_GET['make_posts']) && !$this->check_posts())
		{
			/*$upload_dir = wp_upload_dir();
			//Make sure we use a unique filename for the featured image
			$filename = wp_unique_filename($upload_dir['path'], 'wpmark_post_featured_image.png');
			$this->generate_featured_image(1, $upload_dir['path'] . "/$filename");*/
			$this->setup_posts();
		}
		//Process a make cache request
		if(isset($_GET['make_cache']))
		{
			$this->setup_cache();
		}
		if(isset($_GET['make_twentytwelve']))
		{
			$this->twentytwelve_mods();
		}
		if(isset($_GET['make_wordpress']))
		{
			$this->wordpress_mods();
		}
		//We exit after the version check if there is an action the user needs to take before saving settings
		/*if(!$this->version_check(get_option($this->unique_prefix . '_version')))
		{
			return;
		}
		*/
		?>
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
				//Let's go with 9 top categories
				//TODO add check for if the various parts have been setup
			?>
			<p class="submit">
				<a class="<?php if($this->check_categories()){echo 'button disabled';}else{echo 'button-primary';} ?>" href="<?php if($this->check_categories()){echo '#';}else{echo 'tools.php?page=wpmark&make_cats=1';} ?>">Setup Categories</a>
				<a class="<?php if(!$this->check_categories() || $this->check_posts()){echo 'button disabled';}else{echo 'button-primary';} ?>" href="<?php if(!$this->check_categories() || $this->check_posts()){echo '#';}else{echo 'tools.php?page=wpmark&make_posts=1';} ?>">Setup Posts</a>
				<a class="button-primary" href="tools.php?page=wpmark&make_twentytwelve=1">Setup Theme</a>
				<a class="button-primary" href="tools.php?page=wpmark&make_wordpress=1">Setup WordPress Options</a>
				<a class="button-primary" href="tools.php?page=wpmark&make_cache=1">Setup Cache</a>
			</p>
		</div>
		<?php
	}
}
$wpmark = new wpmark();
