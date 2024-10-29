<?php
/*
Plugin Name: BePro Listings Gallery Sliders
Plugin Script: bepro_listings_gallery_sliders.php
Plugin URI: http://www.beprosoftware.com/shop
Description: Replace the stock wordpress gallery with our slider. Requires BePro Listings which is free
Version: 1.0.1
License: GPL V3
Author: BePro Software Team
Author URI: http://www.beprosoftware.com


Copyright 2013 [Beyond Programs LTD.](http://www.beyondprograms.com/)

This file is part of BePro Listings Galleries. It is part of a paid software solution and
is inteneded to work with the BePro Listings plugin. This product has one licence for
use on one website unless explicit permission is given by beprosoftware.com or its parent
company Beyond Programs LTD.

*/

if ( !defined( 'ABSPATH' ) ) return;

class BePro_listings_galleries{
	function __construct(){
		//filters
		add_filter( 'use_default_gallery_style', '__return_false' );
		add_filter("post_gallery", array($this, "launch_gallery"));
		add_filter("bepro_listings_gallery_last_thumbnails", array($this, "gallery_last_thumbnails"), 1);
		add_filter("bepro_listings_gallery_last_sliders", array($this, "gallery_last_sliders"), 1);
		add_filter("bepro_listings_num_admin_menus", array($this, "gallery_admin_menu_count"));
		
		//actions
		add_action('wp_head', array($this, 'bepro_listings_gallery_frontend_js') );
		add_action('wp_head', array($this, 'bepro_listings_gallery_frontend_css') );
		
		//admin
		add_action("bepro_listings_admin_tabs", array($this, "admin_tab"), 50);
		add_action("bepro_listings_admin_tab_panels", array($this, "admin_tab_panel"), 50);
		add_action( 'wp_ajax_bepro_ajax_save_gallery_settings', array($this, 'save_gallery_settings') );
		
		//shortcodes
		add_shortcode("bpg_slider", array( $this, "bepro_listings_show_slider"));
		
		$data = get_option("bepro_listings");
		if(empty($data["bepro_listings_list_template_bpg3"])){
			$data["bepro_listings_list_template_bpg1"] = array("bepro_listings_list_image" => "bepro_listings_list_image_template", "bepro_listings_list_end" => "bepro_listings_list_links_template", "style" => plugins_url("css/bepro_listings_galleries_list.css", __FILE__ ));
			$data["bepro_listings_list_template_bpg2"] = array("bepro_listings_list_image" => "bepro_listings_list_image_template", "style" => plugins_url("css/bepro_listings_galleries_list.css", __FILE__ ));
			$data["bepro_listings_list_template_bpg3"] = array("bepro_listings_list_image" => "bepro_listings_list_image_template","bepro_listings_list_end" => "bepro_listings_list_title_template", "style" => plugins_url("css/bepro_listings_galleries_list.css", __FILE__ ));
			$data["bepro_listings_gallery_type"] = "slider1";
			update_option("bepro_listings",$data);
		}
	}
	
	function bepro_listings_gallery_frontend_js(){
		
		$scripts .= "\n".'
		<script type="text/javascript" src="'.plugins_url("js/jquery.bxslider.js", __FILE__ ).'"></script>
		<script type="text/javascript" src="'.plugins_url("js/jquery.fitvids.js", __FILE__ ).'"></script>';
		
		$launch_pop_ups = "<script type='text/javascript'>
					jQuery(document).ready(function(){ 
						
						if(jQuery('.bepro_listings_slider1'))
							jQuery('.bepro_listings_slider1').bxSlider({mode: 'fade',  video: true,
  useCSS: false, captions: true,onSliderLoad: function(){jQuery('.bepro_listings_slider1').css('opacity', 1);}});
						
					});
					</script>";
		
		echo $scripts;
		echo $launch_pop_ups; 
		
	}
	
	function bepro_listings_gallery_frontend_css(){
		
		$style = "<link rel='stylesheet' href='".plugins_url("css/bepro_listings_galleries.css", __FILE__ )."' /><link rel='stylesheet' href='".plugins_url("css/jquery.bxslider.css", __FILE__ )."' />";
		
		echo $style; 
		
	}
	
	function gallery_admin_menu_count($num){
		return $num = $num + 1;
	}
	
	//admin area
	function admin_tab(){
		echo "<li class='gallery_tab'><a href='#gallery-tag'>Galleries</a></li>";
	}
	function admin_tab_panel(){
		$data = get_option("bepro_listings_galleries");
		$lighbox_types = array("basic","slider1");
		echo "<div id='gallery-tag'>
		<h2>Gallery Options</h2>
		<form class='admin_addon_form' id='bepro_listings_galleries_admin_form'>
			<input type='hidden' name='action' value='bepro_ajax_save_gallery_settings'>
			<span class='form_label'>Use Lightbox?</span><input type='checkbox' disabled='disabled'/><br />
			<span class='form_label'>Gallery Type</span><select name='bepro_listings_gallery_type'>";
		foreach($lighbox_types as $type){
			$selected = ($type == $data["bepro_listings_gallery_type"])? "selected='selected'":"";
			echo "<option value='".$type."' $selected>".$type."</option>";
		}		
			
		echo "	
			</select><br />
			<input type='submit' value='Save Gallery Options'>
		</form>
		</div>";
	}
	
	
	//This will overide the wordpress gallery
	function launch_gallery($gallery_raw){
		global $post;
		$data = get_option("bepro_listings_galleries");
		$show_lightbox = empty($data["use_lightbox"])? false:true;
		$gallery_type = $data["bepro_listings_gallery_type"];
		$gallery = $this->bepro_listings_get_current_post_gallery_images();
		return $this->bepro_listings_select_gallery_template($gallery, $gallery_type, $show_lightbox, $gallery_raw);
	}
	
	function bepro_listings_select_gallery_template($gallery, $gallery_type, $show_lightbox = false, $gallery_raw = false){
		if($gallery_type == "slider1"){
			return $this->slider_template($gallery, "slider1", $show_lightbox);
		}else{
			return $gallery_raw;
		}
	}
	function slider_template($gallery, $type = "slider1", $show_lightbox = false){
		$thumbnails = array();
		$lightbox = ($show_lightbox)? "bepro_listings_gallery_show":"";
		$image_list = '<ul class="bepro_listings_'.$type.' '.$lightbox.'">';
		foreach( $gallery as $image ) {
			$img = $image["img"];
			$thumbnails[] = $img;
			$img_src = $image["img_src"][0];
			$img_url = empty($image["guid"])? $image["img_src"][0]: $image["guid"];
			$img_title = $image["title"];
			$image_list .= '<li><a href="' . $img_url . '" target="_blank"><img title="'.$img_title.'" src="'.$img_src.'" /></a></li>';
		}
		$image_list .= apply_filters("bepro_listings_gallery_last_sliders", $image_list);
		$image_list .= "</ul>";

		return $image_list;
	}
	
	function bepro_listings_show_slider($atts){
		global $wpdb;
		extract(shortcode_atts(array(
			  'blpid' => $wpdb->escape($_POST["blpid"])
		 ), $atts));
		 
		 if(empty($blpid)) return;
		 
		$post = get_post($blpid);
		$raw_temps = $this->bepro_listings_get_post_gallery_images($blpid);
		foreach($raw_temps as $temp){
			$temp["title"] = $post->post_title;
			$temp["guid"] = $post->guid;
			$gallery[] = $temp;
		}
		
		 return $this->bepro_listings_select_gallery_template($gallery, "slider1");
	}
	
	function bepro_listings_get_post_gallery_images($post_id) {
		$images = array();
		$image_info =& get_children( array (
			'post_parent' => $post_id,
			'post_type' => 'attachment',
			'post_mime_type' => 'image'
		));

		if ( empty($image_info) ) {
			// no attachments here
		} else {
			foreach ( $image_info as $attachment_id => $attachment ) {
				$image['id'] = $attachment_id;
				$image['img'] = wp_get_attachment_image( $attachment_id, "thumbnail");
				$image['img_src'] = wp_get_attachment_image_src($attachment_id, "full");
				array_push($images, $image);
			}
		}
		return $images;
	}
	
	function bepro_listings_get_current_post_gallery_images() {
		global $post;
		/*
		$data = get_option("bepro_listings");
		$gallery_size = $data["gallery_size"];
		*/
		$images = array();
		$image_info =& get_children( array (
			'post_parent' => $post->ID,
			'post_type' => 'attachment',
			'post_mime_type' => 'image'
		));

		if ( empty($image_info) ) {
			// no attachments here
		} else {
			foreach ( $image_info as $attachment_id => $attachment ) {
				$image['id'] = $attachment_id;
				$image['img'] = wp_get_attachment_image( $attachment_id, "thumbnail");
				$image['img_src'] = wp_get_attachment_image_src($attachment_id, "full");
				array_push($images, $image);
			}
		}
		return $images;
	}
	
	function save_gallery_settings(){
		if(!current_user_can('moderate_comments') ){
			echo 0;
			exit;
		}	
		$data["bepro_listings_gallery_type"] = addslashes(strip_tags($_POST["bepro_listings_gallery_type"]));
		if(update_option("bepro_listings_galleries", $data))
			echo 1;
		else
			echo 0;
		
		exit;
	}
	
	//filters returning to avoid -1

	function gallery_last_thumbnails($i){
		return;
	}
	function gallery_last_sliders($i){
		return;
	}

}

$yippy = new BePro_listings_galleries();


?>