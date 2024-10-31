<?php
/*
Plugin Name: Prompty Web Push Notifications
Plugin URI: https://www.prompty.io/push-notification-plugin-for-wordpress/
Description: Easily integrate the Prompty web push notification service with your WordPress site.
Version: 1.0.2
Author: Prompty
Author URI: https://www.prompty.io/
*/

if ( ! defined( 'ABSPATH' ) ) exit;

class Prompty_Plugin {

	var $site_id;
	var $areas_available;
	var $areas_set;
	var $excluded_posts;
	var $hook_used;
	var $hook_priority;

	function __construct() {
		$this->hook_used = apply_filters('prompty_hook_used', 'get_header');
		$this->hook_priority = apply_filters('prompty_hook_priority', 10);
		$this->areas_available = array(
			'home' => 'Home Page',
			'blog' => 'Blog Page',
			'pages' => 'Content Pages',
			'posts' => 'Posts',
			'custom_post_types' => 'Custom Post Types',
			'categories' => 'Category Archives',
			'tags' => 'Tag Archives',
			'tax' => 'Other Taxonomies',
			'author' => 'Author Archives',
			'date' => 'Date-based Archives',
			'archive' => 'Other Archive Pages',
			'search' => 'Search Result Pages',
			'404' => '404 Pages'
		);

		$this->load_options();

		if ($this->site_id) add_action($this->hook_used, array($this, 'output'), $this->hook_priority);
	}

	function load_options() {
		$plugin_options = get_option('prompty_options');
		$this->site_id = (isset($plugin_options['site_id'])) ? $this->validate('site_id', $plugin_options['site_id']) : null;
		$this->areas_set = (isset($plugin_options['areas_set'])) ? $this->validate('areas', $plugin_options['areas_set']) : array();
		$this->excluded_posts = (isset($plugin_options['excluded_posts'])) ? $this->validate('post_ids', $plugin_options['excluded_posts']) : array();
	}

	function area_enabled($id) {
		if (isset($this->areas_set[$id]) && $this->areas_set[$id] == true) return '1';
		return '0';
	}

	function validate($type, $input) {
		if ($type == 'site_id') {
			if (!preg_match('/^[0-9]+$/', $input)) return null;
			return $input;

		} elseif ($type == 'areas') {
			$result = array();
			if (!is_array($input) || !$input) return $result;
			foreach ($input as $key => $value) {
				if (isset($this->areas_available[$key])) {
					if ($value) {
						$result[$key] = 1;
					} else {
						$result[$key] = 0;
					}
				}
			}
			return $result;

		} elseif ($type == 'post_ids') {
			$result = array();
			if (!is_array($input) || !$input) return $result;
			foreach ($input as $value) {
				if (preg_match('/^[0-9]+$/', $value)) $result[] = $value;
			}
			return $result;
		}

		return null;
	}

	function add_menu() {
		add_submenu_page('options-general.php', 'Prompty', 'Prompty', 'manage_options', 'prompty', array($this, 'options_page'));
	}

	function options_page() {
		if ($_POST) $this->form_post();
		$this->form_get();
	}

	function form_post() {
		check_admin_referer('prompty-options');

		$prompty_options = array(
			'site_id' => ((isset($_POST['site_id'])) ? $this->validate('site_id', $_POST['site_id']) : null),
			'excluded_posts' => ((isset($_POST['excluded_posts'])) ? $this->validate('post_ids', explode(',', $_POST['excluded_posts'])) : array())
		);

		$areas_set = array();

		foreach ($this->areas_available as $area_id => $area_name) {
			if (isset($_POST['areas_set'][$area_id]) && $_POST['areas_set'][$area_id] == 1) {
				$areas_set[$area_id] = 1;
			} else {
				$areas_set[$area_id] = 0;
			}
		}

		$prompty_options['areas_set'] = $areas_set;

		update_option('prompty_options', $prompty_options);
		$this->load_options();
	}

	function form_get() {
		?>
		<div class="wrap">
		<h1>Prompty</h1>
		<p>This plugin makes it easy to control where push notification permission prompts are displayed on your website.<br><br>If you do not already have an account, start by <a target="_blank" href="https://www.prompty.io/">signing up with Prompty</a>. Then configure the plugin below.</p>
		<form method="post" action="">
		<table class="form-table" role="presentation">
		<tr><th scope="row"><label for="site_id">Prompty Site ID</label></th>
		<?php
		echo '<td><input name="site_id" type="text" id="site_id" aria-describedby="site-id-description" value="'.esc_attr($this->site_id).'" class="regular-text ltr" />';
		?>
		<p class="description" id="site-id-description"><b>Required for this plugin to function</b>. Please enter the Site ID found in your <a target="_blank" href="https://app.prompty.io/home">dashboard</a>.</p>
		</td></tr>
		<tr><th scope="row">Areas Enabled</th>
		<td><fieldset><legend class="screen-reader-text"><span>Areas Enabled</span></legend>
		<?php
		foreach ($this->areas_available as $area_id => $area_name) {
			echo '<label for="'.esc_attr($area_id).'"><input ';
			if ($this->area_enabled($area_id)) echo 'checked="checked" ';
			echo 'name="areas_set['.esc_attr($area_id).']" type="checkbox" id="'.esc_attr($area_id).'" value="1"  /> ' . esc_html($area_name) . '</label><br>';
		}
		?>
		<p class="description" id="post-types-enabled-description">Select the areas where you would like the prompt to appear. By unchecking all of the boxes you can temporarily disable it.</p>
		</fieldset></td></tr>
		<tr><th scope="row"><label for="excluded_posts">Excluded Posts/Pages</label></th>
		<?php
		echo '<td><input name="excluded_posts" type="text" id="excluded_posts" aria-describedby="excluded-posts-description" value="'.esc_attr(implode(',', $this->excluded_posts)).'" class="regular-text ltr" />';
		?>
		<p class="description" id="excluded-posts-description"><b>Optional</b>. Enter a comma-delimited list of post/page IDs where you do not want the prompt to appear (example: 1,2,3).</p>
		</td></tr>
		</table>
		<?php wp_nonce_field('prompty-options'); ?>
		<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes"  /></p>
		</form>
		<?php
	}

	function output() {
		global $post;

		if (!$this->site_id) return;

		$excluded_posts = apply_filters('prompty_excluded_posts', $this->excluded_posts);
		if (in_array($post->ID, $excluded_posts)) return;

		$display = false;

		if (is_front_page()) {
			if (isset($this->areas_set['home']) && $this->areas_set['home']) $display = true;
		} elseif (is_home()) {
			if (isset($this->areas_set['blog']) && $this->areas_set['blog']) $display = true;
		} elseif (is_page()) {
			if (isset($this->areas_set['pages']) && $this->areas_set['pages']) $display = true;
		} elseif (is_single() && 'post' == $post->post_type) {
			if (isset($this->areas_set['posts']) && $this->areas_set['posts']) $display = true;
		} elseif (is_single() && 'post' != $post->post_type) {
			if (isset($this->areas_set['custom_post_types']) && $this->areas_set['custom_post_types']) $display = true;
		} elseif (is_category()) {
			if (isset($this->areas_set['categories']) && $this->areas_set['categories']) $display = true;
		} elseif (is_tag()) {
			if (isset($this->areas_set['tags']) && $this->areas_set['tags']) $display = true;
		} elseif (is_tax()) {
			if (isset($this->areas_set['tax']) && $this->areas_set['tax']) $display = true;
		} elseif (is_author()) {
			if (isset($this->areas_set['author']) && $this->areas_set['author']) $display = true;
		} elseif (is_date()) {
			if (isset($this->areas_set['date']) && $this->areas_set['date']) $display = true;
		} elseif (is_archive()) {
			if (isset($this->areas_set['archive']) && $this->areas_set['archive']) $display = true;
		} elseif (is_search()) {
			if (isset($this->areas_set['search']) && $this->areas_set['search']) $display = true;
		} elseif (is_404()) {
			if (isset($this->areas_set['404']) && $this->areas_set['404']) $display = true;
		}

		$display = apply_filters('prompty_display_prompt', $display);
		if (!$display) return;

		$path = wp_make_link_relative(site_url('/PromptySW.js'));

		$in_footer = apply_filters('prompty_in_footer', true);

		$prompty_js_url = 'https://app.prompty.io/client/' . $this->site_id . '.js';
		wp_enqueue_script('prompty-js', $prompty_js_url, array(), null, $in_footer);
		wp_add_inline_script('prompty-js', 'Prompty.init(true, \'' . esc_attr($path) . '\');');
	}

	function service_worker() {
		$sw_path = wp_make_link_relative(site_url('/PromptySW.js'));

		if (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) == $sw_path) {
			$sw_content = file_get_contents(__DIR__ . '/PromptySW.js');
			if ($sw_content) {
				status_header(200);
				header('Content-type: application/javascript');
				echo $sw_content;
				exit;
			}
		}
	}
}

$prompty_plugin = new Prompty_Plugin;
add_action('wp', array($prompty_plugin, 'service_worker'));
add_action('admin_menu', array($prompty_plugin, 'add_menu'));
?>