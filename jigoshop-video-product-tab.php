<?php
/*
 * Plugin Name: Jigoshop Video Product Tab
 * Plugin URI: http://www.sebs-studio.com/wp-plugins/jigoshop-video-product-tab/
 * Description: Extends Jigoshop to allow you to add a Video to the Product page. An additional tab is added on the single products page to allow your customers to view the video you embeded. 
 * Version: 1.0
 * Author: Sebs Studio
 * Author URI: http://www.sebs-studio.com
 *
 * Text Domain: jigo_video_product_tab
 * Domain Path: /lang/
 * Language File Name: jigo_video_product_tab-'.$locale.'.mo
 *
 * Copyright 2013  Sebastien  (email : sebastien@sebs-studio.com)
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 */

if(!defined('ABSPATH')) exit; // Exit if accessed directly

// Required minimum version of WordPress.
if(!function_exists('jigo_video_tab_min_required')){
	function jigo_video_tab_min_required(){
		global $wp_version;
		$plugin = plugin_basename(__FILE__);
		$plugin_data = get_plugin_data(__FILE__, false);

		if(version_compare($wp_version, "3.3", "<")){
			if(is_plugin_active($plugin)){
				deactivate_plugins($plugin);
				wp_die("'".$plugin_data['Name']."' requires WordPress 3.3 or higher, and has been deactivated! Please upgrade WordPress and try again.<br /><br />Back to <a href='".admin_url()."'>WordPress Admin</a>.");
			}
		}
	}
	add_action('admin_init', 'jigo_video_tab_min_required');
}

// Checks if the Jigoshop plugins is installed and active.
if(in_array('jigoshop/jigoshop.php', apply_filters('active_plugins', get_option('active_plugins')))){

	/* Localisation */
	$locale = apply_filters('plugin_locale', get_locale(), 'jigoshop-video-product-tab');
	load_textdomain('jigo_video_product_tab', WP_PLUGIN_DIR."/".plugin_basename(dirname(__FILE__)).'/lang/jigo_video_product_tab-'.$locale.'.mo');
	load_plugin_textdomain('jigo_video_product_tab', false, dirname(plugin_basename(__FILE__)).'/lang/');

	if(!class_exists('Jigoshop_Video_Product_Tab')){
		class Jigoshop_Video_Product_Tab{

			public static $plugin_prefix;
			public static $plugin_url;
			public static $plugin_path;
			public static $plugin_basefile;

			private $tab_data = false;

			/**
			 * Gets things started by adding an action to 
			 * initialize this plugin once Jigoshop is 
			 * known to be active and initialized.
			 */
			public function __construct(){
				Jigoshop_Video_Product_Tab::$plugin_prefix = 'jigo_video_tab_';
				Jigoshop_Video_Product_Tab::$plugin_basefile = plugin_basename(__FILE__);
				Jigoshop_Video_Product_Tab::$plugin_url = plugin_dir_url(Jigoshop_Video_Product_Tab::$plugin_basefile);
				Jigoshop_Video_Product_Tab::$plugin_path = trailingslashit(dirname(__FILE__));
				add_action('init', array(&$this, 'jigoshop_init'), 0);
			}

			/**
			 * Init Jigoshop Video Product Tab extension once we know Jigoshop is active
			 */
			public function jigoshop_init(){
				// backend stuff
				add_filter('plugin_row_meta', array(&$this, 'add_support_link'), 10, 2);
				// frontend stuff
				add_action('jigoshop_product_tabs', array(&$this, 'video_product_tabs'), 999);
				add_action('jigoshop_product_tab_panels', array(&$this, 'video_product_tabs_panel'));
				// Write panel
				add_action('jigoshop_product_write_panel_tabs', array(&$this, 'write_video_tab'));
				add_action('jigoshop_product_write_panels', array(&$this, 'write_video_tab_panel'));
				add_action('jigoshop_process_product_meta', array(&$this, 'write_video_tab_panel_save'));
			}

			/**
			 * Add links to plugin page.
			 */
			public function add_support_link($links, $file){
				if(!current_user_can('install_plugins')){
					return $links;
				}
				if($file == Jigoshop_Video_Product_Tab::$plugin_basefile){
					$links[] = '<a href="http://www.sebs-studio.com/forum/jigoshop-video-product-tab/" target="_blank">'.__('Support', 'jigo_video_product_tab').'</a>';
					$links[] = '<a href="http://www.sebs-studio.com/wp-plugins/jigoshop-extensions/" target="_blank">'.__('More Jigoshop Extensions', 'jigo_video_product_tab').'</a>';
				}
				return $links;
			}

			/**
			 * Write the video tab on the product view page.
			 * In Jigoshop these are handled by templates.
			 */
			public function video_product_tabs($current_tab){
				global $post;

				if($this->product_has_video_tabs($post)){
					foreach($this->tab_data as $tab){
					?>
					<li<?php if($current_tab == '#tab-video'){ echo ' class="active"'; } ?>><a href="#tab-video"><?php echo __('Video', 'jigo_video_product_tab'); ?></a></li>
					<?php
					}
				}
			}

			/**
			 * Write the video tab panel on the product view page.
			 * In Jigoshop these are handled by templates.
			 */
			public function video_product_tabs_panel(){
				global $post;

				$embed = new WP_Embed();

				if($this->product_has_video_tabs($post)){
					foreach($this->tab_data as $tab){
						echo '<div class="panel" id="tab-video">';
						echo '<h2>'.$tab['title'].'</h2>';
						echo $embed->autoembed(apply_filters('jigoshop_video_product_tab', $tab['video'], $tab['id']));
						echo '</div>';
					}
				}
			}

			/**
			 * Lazy-load the product_tabs meta data, and return true if it exists,
			 * false otherwise.
			 * 
			 * @return true if there is video tab data, false otherwise.
			 */
			private function product_has_video_tabs($post){
				if($this->tab_data === false){
					$this->tab_data = maybe_unserialize(get_post_meta($post->ID, 'jigo_video_product_tab', true));
				}
				// tab must at least have a embed code inserted.
				return !empty($this->tab_data) && !empty($this->tab_data[0]) && !empty($this->tab_data[0]['video']);
			}

			/**
			 * Creates a new tab in the product data for the administrator.
			 * New tab called 'Video' is added.
			 */
			function write_video_tab(){
			?>
			<li class="video_tab">
				<a href="#video-tab"><?php _e('Video', 'jigo_video_product_tab');?></a>
			</li>
			<?php
			}

			/**
			 * Product Meta Data.
			 *
			 * Adds the panel to the Product Data postbox in the product interface.
			 */
			function write_video_tab_panel(){
				global $post;

				// Pull the video tab data out of the database
				$tab_data = maybe_unserialize(get_post_meta($post->ID, 'jigo_video_product_tab', true));

				if(empty($tab_data)){
					$tab_data[] = array('title' => '', 'video' => '');
				}

				// Display the video tab panel
				foreach($tab_data as $tab){
					echo '<div id="video-tab" class="panel jigoshop_options_panel" style="display:none;">';
					echo '<fieldset>';
					$this->jigo_video_product_tab_text_input(
															array(
																'id' => '_tab_video_title', 
																'label' => __('Video Title', 'jigo_video_product_tab'), 
																'placeholder' => __('Enter your title here.', 'jigo_video_product_tab'), 
																'value' => $tab['title'], 
																'style' => 'width:70%;',
															)
					);
					$this->jigo_video_product_tab_textarea_input(
															array(
																'id' => '_tab_video', 
																'label' => __('Embed Code', 'jigo_video_product_tab'), 
																'placeholder' => __('Place your video embed code here.', 'jigo_video_product_tab'), 
																'value' => $tab['video'], 
																'style' => 'width:70%;height:140px;',
															)
					);
					echo '</fieldset>';
					echo '</div>';
				}
		    }

			/**
			 * Output a text input box.
			 */
			public function jigo_video_product_tab_text_input($field){
				global $thepostid, $post;

				$thepostid              = empty( $thepostid ) ? $post->ID : $thepostid;
				$field['placeholder']   = isset( $field['placeholder'] ) ? $field['placeholder'] : '';
				$field['class']         = isset( $field['class'] ) ? $field['class'] : 'short';
				$field['wrapper_class'] = isset( $field['wrapper_class'] ) ? $field['wrapper_class'] : '';
				$field['value']         = isset( $field['value'] ) ? $field['value'] : get_post_meta( $thepostid, $field['id'], true );
				$field['name']          = isset( $field['name'] ) ? $field['name'] : $field['id'];
				$field['type']          = isset( $field['type'] ) ? $field['type'] : 'text';

				echo '<p class="form-field '.esc_attr($field['id']).'_field '.esc_attr($field['wrapper_class']).'"><label for="'.esc_attr($field['id']).'">'.wp_kses_post($field['label']).'</label><input type="'.esc_attr($field['type']).'" class="'.esc_attr($field['class']).'" name="'.esc_attr($field['name']).'" id="'.esc_attr($field['id']).'" value="'.esc_attr($field['value']).'" placeholder="'.esc_attr($field['placeholder']).'"'.(isset($field['style']) ? ' style="'.$field['style'].'"' : '').' /> ';
				echo '</p>';
			}

			/**
			 * Output a textarea box.
			 */
			public function jigo_video_product_tab_textarea_input($field){
				global $thepostid, $post;

				if(!$thepostid) $thepostid = $post->ID;
				if(!isset($field['placeholder'])) $field['placeholder'] = '';
				if(!isset($field['class'])) $field['class'] = 'short';
				if(!isset($field['value'])) $field['value'] = get_post_meta($thepostid, $field['id'], true);

				echo '<p class="form-field '.$field['id'].'_field"><label for="'.$field['id'].'">'.$field['label'].'</label><textarea class="'.$field['class'].'" name="'.$field['id'].'" id="'.$field['id'].'" placeholder="'.$field['placeholder'].'" rows="2" cols="20"'.(isset($field['style']) ? ' style="'.$field['style'].'"' : '').'">'.esc_textarea( $field['value']).'</textarea>';

				if(isset($field['description']) && $field['description']) echo '<span class="description">' .$field['description'] . '</span>';

				echo '</p>';
			}

			/**
			 * Saves the options set in the product tab.
			 */
		    function write_video_tab_panel_save($post_id){

				$tab_title = stripslashes($_POST['_tab_video_title']);
				if($tab_title == ''){
					$tab_title = __('Video', 'jigo_video_product_tab');
				}
				$tab_video = stripslashes($_POST['_tab_video']);

				if(empty($tab_video) && get_post_meta($post_id, 'jigo_video_product_tab', true)){
					// clean up if the video tabs are removed
					delete_post_meta($post_id, 'jigo_video_product_tab');
				}
				elseif(!empty($tab_video)){
					$tab_data = array();

					$tab_id = '';
					// convert the tab title into an id string
					$tab_id = strtolower($tab_title);
					$tab_id = preg_replace("/[^\w\s]/",'',$tab_id); // remove non-alphas, numbers, underscores or whitespace 
					$tab_id = preg_replace("/_+/", ' ', $tab_id); // replace all underscores with single spaces
					$tab_id = preg_replace("/\s+/", '-', $tab_id); // replace all multiple spaces with single dashes
					$tab_id = 'tab-'.$tab_id; // prepend with 'tab-' string

					// save the data to the database
					$tab_data[] = array('title' => $tab_title, 'id' => $tab_id, 'video' => $tab_video);
					update_post_meta($post_id, 'jigo_video_product_tab', $tab_data);
				}
		    }
		}
	}

	/* 
	 * Instantiate plugin class and add it to the set of globals.
	 */
	$jigoshop_video_tab = new Jigoshop_Video_Product_Tab();
}
else{
	add_action('admin_notices', 'jigo_video_tab_error_notice');
	function jigo_video_tab_error_notice(){
		global $current_screen;
		if($current_screen->parent_base == 'plugins'){
			echo '<div class="error"><p>'.__('Jigoshop Video Product Tab requires <a href="http://www.jigoshop.com/" target="_blank">Jigoshop</a> to be activated in order to work. Please install and activate <a href="'.admin_url('plugin-install.php?tab=search&type=term&s=Jigoshop').'" target="_blank">Jigoshop</a> first.').'</p></div>';
		}
	}
}
?>