<?php 
/*
Plugin Name: EJDA FavePersonal Colors
Plugin URI: http://evanjdanderson.com
Description: Automatically choose best contrasting text colors for the FavePersonal theme
Version: 1.0
Author: Evan Anderson
Author URI: http://evanjdanderson.com
License: GPLv2 or later
*/

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed without warranty.
*/

if (__FILE__ == $_SERVER['SCRIPT_FILENAME']) { die(); }

// Maximize constrast in all text except for hover
function ejda_colors() {
	$ejda_colors = new EJDA_Colors();
	if ($ejda_colors->colors_are_set()) {
		$ejda_colors->add_filters();	
	}
}
add_action('wp', 'ejda_colors');

// Provides better text color selection based on background color
class EJDA_Colors {
	function __construct() {
		$this->color_types = array(
			'darkest',
			'dark',
			'medium',
			'light',
			'lightest'
		);
		$this->colors = array();

		if (function_exists('cf_colors_get_colors')) {
			if ($colors = cf_colors_get_colors()) {
				foreach ($this->color_types as $key => $type) {
					$this->$type = $colors[$key];
					$this->colors[$type] = $colors[$key];
				}
			}
		}
	}

	function colors_are_set() {
		foreach ($this->color_types as $type) {
			if (!isset($this->$type)) {
				return false;
			}
		}
		return true;
	}

	function add_filters() {
		$filters = array(
			'cf_colors_featured_posts_hover_color' => 'lightest_darkest_light',

			'cf_colors_widget_title_color' => 'lightest_darkest_light',
			'cf_colors_widget_search_placeholder_color' => 'lightest_darkest_light',

			// Bio box is a bit unique in terms of color usage
			// Just select top three contrasting from all colors
			'cf_colors_bio_box_title_color' => 'all_second_medium',
			'cf_colors_bio_box_a' => 'all_second_medium',
			'cf_colors_bio_box_a_hover' => 'all_third_medium',
			'cf_colors_bio_box_links_a_hover_border' => 'all_medium',
			'cf_colors_bio_box_content_color' => 'all_medium',
			
			'cf_colors_social_a_color' => 'medium_dark_light',
			'cf_colors_social_nav_a_color' => 'lightest_darkest_light',

			'cf_colors_footer_color' => 'lightest_darkest_dark',
			'cf_colors_footer_a' => 'light_medium_dark',
		); 

		foreach ($filters as $filter => $function) {
			add_filter($filter, array($this, $function));
		}
	}

// Color selection filters
// @TODO this is a bit tedious, might just want to replace the colors file...

	// Get most contrasting color to the medium color
	function all_medium($key) {
		$colors = $this->colors;
		unset($colors['medium']);
		$color = $this->greatest_contrast($colors, $this->medium);
		if ($color) {
			return $this->color_key($color);
		}
		return $key;
	}

	// Get second most contrasting color to the medium color
	function all_second_medium($key) {
		$colors = $this->colors;
		unset($colors['medium']);
		$first = $this->all_medium($key);
		unset($colors[$first]);

		$color = $this->greatest_contrast($colors, $this->medium);
		if ($color) {
			return $this->color_key($color);
		}
		return $key;
	}

	// Get third most contrasting color to the medium color
	function all_third_medium($key) {
		$colors = $this->colors;
		unset($colors['medium']);
		$first = $this->all_medium($key);
		unset($colors[$first]);
		$second = $this->all_second_medium($key);
		unset($colors[$second]);

		$color = $this->greatest_contrast($colors, $this->medium);
		if ($color) {
			return $this->color_key($color);
		}
		return $key;
	}

// Naming is as such: color1_color2_backgroundColor

	function lightest_darkest_light($key) {
		$colors = array(
			'lightest' => $this->lightest,
			'darkets' => $this->darkest,
		);

		$color = $this->greatest_contrast($colors, $this->light);
		if ($color) {
			return $this->color_key($color);
		}
		return $key;
	}

	function lightest_darkest_meduim($key) {
		$colors = array(
			$this->lightest,
			$this->darkest,
		);
		$color = $this->greatest_contrast($colors, $this->medium);
		if ($color) {
			return $this->color_key($color);
		}
		return $key;
	}

	function lightest_darkest_dark($key) {
		$colors = array(
			$this->lightest,
			$this->darkest,
		);
		$color = $this->greatest_contrast($colors, $this->dark);
		if ($color) {
			return $this->color_key($color);
		}
		return $key;
	}

	function medium_dark_light($key) {
		$colors = array(
			$this->medium,
			$this->dark,
		);
		$color = $this->greatest_contrast($colors, $this->light);
		if ($color) {
			return $this->color_key($color);
		}
		return $key;
	}

	function dark_light_medium($key) {
		$colors = array(
			$this->dark,
			$this->light,
		);
		$color = $this->greatest_contrast($colors, $this->medium);
		if ($color) {
			return $this->color_key($color);
		}
		return $key;
	}

	function light_medium_dark($key) {
		$colors = array(
			$this->medium,
			$this->light,
		);
		$color = $this->greatest_contrast($colors, $this->dark);
		if ($color) {
			return $this->color_key($color);
		}
		return $key;
	}

	function color_key($color) {
		foreach ($this->color_types as $type) {
			if ($this->$type == $color) {
				return $type;
			}
		}
		return false;
	}

	// Get the numerical distance between the luma of 2 hex colors
	function luma_distance($hex_1, $hex_2) {
		$rgb_1 = $this->rgbify_color($hex_1);
		$rgb_2 = $this->rgbify_color($hex_2);
		if ($rgb_1 && $rgb_2) {
			$luma_1 = $this->get_luma($rgb_1[0], $rgb_1[1], $rgb_1[2]);
			$luma_2 = $this->get_luma($rgb_2[0], $rgb_2[1], $rgb_2[2]);
			return abs($luma_1 - $luma_2);
		}

		return false;
	}

	/**
	 * Choose the color that provides the greatest contrast to a secnod color
	 *	 *
	 * @param array $colors Potential color choices, hex format
	 * @param string $from_color Color that the greatest contrast is calculated from
	 * @return string hex value of the color chosen
	 **/
	function greatest_contrast($colors, $from_color) {
		$distances = array();
		foreach ($colors as $color) {
			$distances[$color] = $this->luma_distance($color, $from_color);
		}
		error_log(print_r($distances,1));
		$max = max($distances);
		return array_search($max, $distances);
	}

	/**
	 * Break a hexadecimal string representation into its RGB components.
	 *
	 * @param string $hex_string 
	 * @return array|false Array with RGB values with keys 0,1,2 respectively. 
	 *						False if an invalid hex color string was passed in
	 **/
	function rgbify_color($hex_string) {
		$hex_string = trim(str_replace('#', '', $hex_string));
		if (strlen($hex_string) == 3) {
			$hex_string .= $hex_string;
		}

		if (preg_match('/^[a-fA-F0-9]{6}$/i', $hex_string)) {
			return str_split($hex_string, 2);
		}

		return false;
	}

	/**
	 * Calculate luma from a set of RGB colors
	 * see http://en.wikipedia.org/wiki/YIQ for value reference
	 * @return float luma value
	 **/
	function get_luma($red, $green, $blue) {
		return (hexdec($red) * 0.299) + (hexdec($green) * 0.587) + (hexdec($blue) * 0.114);
	}
}
