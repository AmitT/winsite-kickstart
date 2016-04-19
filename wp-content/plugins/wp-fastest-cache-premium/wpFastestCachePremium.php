<?php
/*
Plugin Name: WP Fastest Cache Premium
Plugin URI: http://www.wpfastestcache.com/
Description: The Premium Version of WP Fastest Cache
Version: 1.3.1
Author: Emre Vona
Author URI: http://tr.linkedin.com/in/emrevona

Copyright (C)2014 Emre Vona

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
*/

	if (!defined('WPFC_WP_CONTENT_BASENAME')) {
		define("WPFC_WP_CONTENT_BASENAME", str_replace("/", "", basename(content_url())));
		define("WPFC_WP_CONTENT_DIR", ABSPATH.str_replace("/", "", basename(content_url())));
		if (!defined('WPFC_WP_PLUGIN_DIR')) {
			if(preg_match("/(\/trunk\/|\/wp-fastest-cache-premium\/)$/", plugin_dir_path( __FILE__ ))){
				define("WPFC_WP_PLUGIN_DIR", preg_replace("/(\/trunk\/|\/wp-fastest-cache-premium\/)$/", "", plugin_dir_path( __FILE__ )));
			}else if(preg_match("/\\\wp-fastest-cache-premium\/$/", plugin_dir_path( __FILE__ ))){
				//D:\hosting\LINEapp\public_html\wp-content\plugins\wp-fastest-cache/
				define("WPFC_WP_PLUGIN_DIR", preg_replace("/\\\wp-fastest-cache-premium\/$/", "", plugin_dir_path( __FILE__ )));
			}
		}

	}
?>