<?php
	class WpFastestCacheAdmin extends WpFastestCache{
		private $adminPageUrl = "wp-fastest-cache/admin/index.php";
		private $systemMessage = array();
		private $options = array();
		private $cronJobSettings;
		private $startTime;
		private $blockCache = false;

		public function __construct(){
			$this->options = $this->getOptions();

			//to call like that because on WP Multisite current_user_can() cannot get the user
			add_action('admin_init', array($this, "optionsPageRequest"));

			$this->setCronJobSettings();
			$this->addButtonOnEditor();
			add_action('admin_enqueue_scripts', array($this, 'addJavaScript'));
		}

		public function create_auto_cache_timeout($recurrance, $interval){
			$exist_cronjob = false;
			$wpfc_timeout_number = 0;

			$crons = _get_cron_array();

			foreach ((array)$crons as $cron_key => $cron_value) {
				foreach ( (array) $cron_value as $hook => $events ) {
					if(preg_match("/^wp\_fastest\_cache(.*)/", $hook, $id)){
						if(!$id[1] || preg_match("/^\_(\d+)$/", $id[1])){
							$wpfc_timeout_number++;

							foreach ( (array) $events as $event_key => $event ) {
								$schedules = wp_get_schedules();

								if(isset($event["args"]) && isset($event["args"][0])){
									if($event["args"][0] == '{"prefix":"all","content":"all"}'){
										if($schedules[$event["schedule"]]["interval"] <= $interval){
											$exist_cronjob = true;
										}
									}
								}
							}
						}
					}
				}
			}

			if(!$exist_cronjob){
				$args = array("prefix" => "all", "content" => "all");
				wp_schedule_event(time(), $recurrance, "wp_fastest_cache_".$wpfc_timeout_number, array(json_encode($args)));
			}
		}

		public function get_premium_version(){
			$wpfc_premium_version = "";
			if(file_exists(WPFC_WP_PLUGIN_DIR."/wp-fastest-cache-premium/wpFastestCachePremium.php")){
				if($data = @file_get_contents(WPFC_WP_PLUGIN_DIR."/wp-fastest-cache-premium/wpFastestCachePremium.php")){
					preg_match("/Version:\s*(.+)/", $data, $out);
					if(isset($out[1]) && $out[1]){
						$wpfc_premium_version = trim($out[1]);
					}
				}
			}
			return $wpfc_premium_version;
		}

		public function addButtonOnEditor(){
			add_action('admin_print_footer_scripts', array($this, 'addButtonOnQuicktagsEditor'));
			add_action('init', array($this, 'myplugin_buttonhooks'));
		}

		public function checkShortCode($content){
			preg_match("/\[wpfcNOT\]/", $content, $wpfcNOT);
			if(count($wpfcNOT) > 0){
				if(is_single() || is_page()){
					$this->blockCache = true;
				}
				$content = str_replace("[wpfcNOT]", "", $content);
			}
			return $content;
		}

		public function myplugin_buttonhooks() {
		   // Only add hooks when the current user has permissions AND is in Rich Text editor mode
		   if (current_user_can( 'manage_options' )) {
		     add_filter("mce_external_plugins", array($this, "myplugin_register_tinymce_javascript"));
		     add_filter('mce_buttons', array($this, 'myplugin_register_buttons'));
		   }
		}
		// Load the TinyMCE plugin : editor_plugin.js (wp2.5)
		public function myplugin_register_tinymce_javascript($plugin_array) {
		   $plugin_array['wpfc'] = plugins_url('../js/button.js?v='.time(),__file__);
		   return $plugin_array;
		}

		public function myplugin_register_buttons($buttons) {
		   array_push($buttons, 'wpfc');
		   return $buttons;
		}

		public function addButtonOnQuicktagsEditor(){
			if (wp_script_is('quicktags') && current_user_can( 'manage_options' )){ ?>
				<script type="text/javascript">
					if(typeof QTags != "undefined"){
				    	QTags.addButton('wpfc_not', 'wpfcNOT', '<!--[wpfcNOT]-->', '', '', 'Block caching for this page');
					}
			    </script>
		    <?php }
		}

		public function optionsPageRequest(){
			if(!empty($_POST)){
				if(isset($_POST["wpFastestCachePage"])){
					include_once ABSPATH."wp-includes/capabilities.php";
					include_once ABSPATH."wp-includes/pluggable.php";

					if(is_multisite()){
						$this->systemMessage = array("The plugin does not work with Multisite", "error");
						return 0;
					}

					if(current_user_can('manage_options')){
						if($_POST["wpFastestCachePage"] == "options"){
							$this->saveOption();
						}else if($_POST["wpFastestCachePage"] == "deleteCache"){
							$this->deleteCache();
						}else if($_POST["wpFastestCachePage"] == "deleteCssAndJsCache"){
							$this->deleteCache(true);
						}else if($_POST["wpFastestCachePage"] == "cacheTimeout"){
							$this->addCacheTimeout();
						}
					}else{
						die("Forbidden");
					}
				}
			}
		}

		public function addCacheTimeout(){
			if(isset($_POST["wpFastestCacheTimeOut"])){
				if($_POST["wpFastestCacheTimeOut"]){
					if(isset($_POST["wpFastestCacheTimeOutHour"]) && is_numeric($_POST["wpFastestCacheTimeOutHour"])){
						if(isset($_POST["wpFastestCacheTimeOutMinute"]) && is_numeric($_POST["wpFastestCacheTimeOutMinute"])){
							$selected = mktime($_POST["wpFastestCacheTimeOutHour"], $_POST["wpFastestCacheTimeOutMinute"], 0, date("n"), date("j"), date("Y"));

							if($selected > time()){
								$timestamp = $selected;
							}else{
								if(time() - $selected < 60){
									$timestamp = $selected + 60;
								}else{
									// if selected time is less than now, 24hours is added
									$timestamp = $selected + 24*60*60;
								}
							}

							wp_clear_scheduled_hook($this->slug());
							wp_schedule_event($timestamp, $_POST["wpFastestCacheTimeOut"], $this->slug());
						}else{
							echo "Minute was not set";
							exit;
						}
					}else{
						echo "Hour was not set";
						exit;
					}
				}else{
					wp_clear_scheduled_hook($this->slug());
				}
			}
		}

		public function setCronJobSettings(){
			if(wp_next_scheduled($this->slug())){
				$this->cronJobSettings["period"] = wp_get_schedule($this->slug());
				$this->cronJobSettings["time"] = wp_next_scheduled($this->slug());
			}
		}

		public function addMenuPage(){
			add_action('admin_menu', array($this, 'register_my_custom_menu_page'));
		}

		public function addJavaScript(){
			wp_enqueue_script("wpfc-jquery-ui", plugins_url("wp-fastest-cache/js/jquery-ui.min.js"), array(), time(), false);
			wp_enqueue_script("wpfc-dialog", plugins_url("wp-fastest-cache/js/dialog.js"), array(), time(), false);
			wp_enqueue_script("wpfc-dialog-new", plugins_url("wp-fastest-cache/js/dialog_new.js"), array(), time(), false);


			wp_enqueue_script("wpfc-cdn", plugins_url("wp-fastest-cache/js/cdn/cdn.js"), array(), time(), false);


			wp_enqueue_script("wpfc-language", plugins_url("wp-fastest-cache/js/language.js"), array(), time(), false);
			//wp_enqueue_script("wpfc-info", plugins_url("wp-fastest-cache/js/info.js"), array(), time(), true);
			wp_enqueue_script("wpfc-schedule", plugins_url("wp-fastest-cache/js/schedule.js"), array(), time(), true);

			
			if(class_exists("WpFastestCacheImageOptimisation")){

				if(file_exists(WPFC_WP_PLUGIN_DIR."/wp-fastest-cache-premium/pro/js/statics.js")){
					wp_enqueue_script("wpfc-statics", plugins_url("wp-fastest-cache-premium/pro/js/statics.js"), array(), time(), false);
				}else{
					wp_enqueue_script("wpfc-statics", plugins_url("wp-fastest-cache/js/statics.js"), array(), time(), false);
				}

				if(file_exists(WPFC_WP_PLUGIN_DIR."/wp-fastest-cache-premium/pro/js/premium.js")){
					wp_enqueue_script("wpfc-premium", plugins_url("wp-fastest-cache-premium/pro/js/premium.js"), array(), time(), true);
				}
			}
			
			if(isset($this->options->wpFastestCacheLanguage) && $this->options->wpFastestCacheLanguage != "eng"){
				wp_enqueue_script("wpfc-dictionary", plugins_url("wp-fastest-cache/js/lang/".$this->options->wpFastestCacheLanguage.".js"), array(), time(), false);
			}
		}

		public function saveOption(){
			unset($_POST["wpFastestCachePage"]);
			$data = json_encode($_POST);
			//for optionsPage() $_POST is array and json_decode() converts to stdObj
			$this->options = json_decode($data);

			$this->systemMessage = $this->modifyHtaccess($_POST);

			if(isset($this->systemMessage[1]) && $this->systemMessage[1] != "error"){

				if($message = $this->checkCachePathWriteable()){


					if(is_array($message)){
						$this->systemMessage = $message;
					}else{
						if(isset($this->options->wpFastestCachePreload)){
							$this->set_preload();
						}else{
							delete_option("WpFastestCachePreLoad");
						}

						if(get_option("WpFastestCache")){
							update_option("WpFastestCache", $data);
						}else{
							add_option("WpFastestCache", $data, null, "yes");
						}
					}
				}
			}
		}

		public function checkCachePathWriteable(){
			$message = array();

			if(!is_dir($this->getWpContentDir()."/cache/")){
				if (@mkdir($this->getWpContentDir()."/cache/", 0755, true)){
					//
				}else{
					array_push($message, "- /wp-content/cache/ is needed to be created");
				}
			}else{
				if (@mkdir($this->getWpContentDir()."/cache/testWpFc/", 0755, true)){
					rmdir($this->getWpContentDir()."/cache/testWpFc/");
				}else{
					array_push($message, "- /wp-content/cache/ permission has to be 755");
				}
			}

			if(!is_dir($this->getWpContentDir()."/cache/all/")){
				if (@mkdir($this->getWpContentDir()."/cache/all/", 0755, true)){
					//
				}else{
					array_push($message, "- /wp-content/cache/all/ is needed to be created");
				}
			}else{
				if (@mkdir($this->getWpContentDir()."/cache/all/testWpFc/", 0755, true)){
					rmdir($this->getWpContentDir()."/cache/all/testWpFc/");
				}else{
					array_push($message, "- /wp-content/cache/all/ permission has to be 755");
				}	
			}

			if(count($message) > 0){
				return array(implode("<br>", $message), "error");
			}else{
				return true;
			}
		}

		public function modifyHtaccess($post){
			$path = ABSPATH;
			if($this->is_subdirectory_install()){
				$path = $this->getABSPATH();
			}

			if(isset($_SERVER["SERVER_SOFTWARE"]) && $_SERVER["SERVER_SOFTWARE"] && preg_match("/iis/i", $_SERVER["SERVER_SOFTWARE"])){
				return array("The plugin does not work with Microsoft IIS. Only with Apache", "error");
			}

			// if(isset($_SERVER["SERVER_SOFTWARE"]) && $_SERVER["SERVER_SOFTWARE"] && preg_match("/nginx/i", $_SERVER["SERVER_SOFTWARE"])){
			// 	return array("The plugin does not work with Nginx. Only with Apache", "error");
			// }

			if(!file_exists($path.".htaccess")){
				if(isset($_SERVER["SERVER_SOFTWARE"]) && $_SERVER["SERVER_SOFTWARE"] && preg_match("/nginx/i", $_SERVER["SERVER_SOFTWARE"])){
					//
				}else{
					return array("<label>.htaccess was not found</label> <a target='_blank' href='http://www.wpfastestcache.com/warnings/htaccess-was-not-found/'>Read More</a>", "error");
				}
			}

			if($this->isPluginActive('wp-postviews/wp-postviews.php')){
				$wp_postviews_options = get_option("views_options");
				$wp_postviews_options["use_ajax"] = true;
				update_option("views_options", $wp_postviews_options);

				if(!WP_CACHE){
					if($wp_config = @file_get_contents(ABSPATH."wp-config.php")){
						$wp_config = str_replace("\$table_prefix", "define('WP_CACHE', true);\n\$table_prefix", $wp_config);

						if(!@file_put_contents(ABSPATH."wp-config.php", $wp_config)){
							return array("define('WP_CACHE', true); is needed to be added into wp-config.php", "error");
						}
					}else{
						return array("define('WP_CACHE', true); is needed to be added into wp-config.php", "error");
					}
				}
			}

			$htaccess = @file_get_contents($path.".htaccess");

			// if(defined('DONOTCACHEPAGE')){
			// 	return array("DONOTCACHEPAGE <label>constant is defined as TRUE. It must be FALSE</label>", "error");
			// }else 
			

			if(!get_option('permalink_structure')){
				return array("You have to set <strong><u><a href='".admin_url()."options-permalink.php"."'>permalinks</a></u></strong>", "error");
			}else if($res = $this->checkSuperCache($path, $htaccess)){
				return $res;
			}else if($this->isPluginActive('wp-hide-security-enhancer/wp-hide.php')){
				return array("WP Hide & Security Enhancer needs to be deactived<br>", "error");
			}else if($this->isPluginActive('adrotate/adrotate.php') || $this->isPluginActive('adrotate-pro/adrotate.php')){
				return $this->warningIncompatible("AdRotate");
			}else if($this->isPluginActive('mobilepress/mobilepress.php')){
				return $this->warningIncompatible("MobilePress", array("name" => "WPtouch Mobile", "url" => "https://wordpress.org/plugins/wptouch/"));
			}else if($this->isPluginActive('speed-booster-pack/speed-booster-pack.php')){
				return array("Speed Booster Pack needs to be deactive<br>", "error");
			}else if($this->isPluginActive('cdn-enabler/cdn-enabler.php')){
				return array("CDN Enabler needs to be deactive<br>This plugin has aldready CDN feature", "error");
			}else if($this->isPluginActive('wp-performance-score-booster/wp-performance-score-booster.php')){
				return array("WP Performance Score Booster needs to be deactive<br>This plugin has aldready Gzip, Leverage Browser Caching features", "error");
			}else if($this->isPluginActive('bwp-minify/bwp-minify.php')){
				return array("Better WordPress Minify needs to be deactive<br>This plugin has aldready Minify feature", "error");
			}else if($this->isPluginActive('check-and-enable-gzip-compression/richards-toolbox.php')){
				return array("Check and Enable GZIP compression needs to be deactive<br>This plugin has aldready Gzip feature", "error");
			}else if($this->isPluginActive('gzippy/gzippy.php')){
				return array("GZippy needs to be deactive<br>This plugin has aldready Gzip feature", "error");
			}else if($this->isPluginActive('gzip-ninja-speed-compression/gzip-ninja-speed.php')){
				return array("GZip Ninja Speed Compression needs to be deactive<br>This plugin has aldready Gzip feature", "error");
			}else if($this->isPluginActive('wordpress-gzip-compression/ezgz.php')){
				return array("WordPress Gzip Compression needs to be deactive<br>This plugin has aldready Gzip feature", "error");
			}else if($this->isPluginActive('filosofo-gzip-compression/filosofo-gzip-compression.php')){
				return array("GZIP Output needs to be deactive<br>This plugin has aldready Gzip feature", "error");
			}else if($this->isPluginActive('head-cleaner/head-cleaner.php')){
				return array("Head Cleaner needs to be deactive", "error");
			}else if(is_writable($path.".htaccess")){
				$htaccess = $this->insertLBCRule($htaccess, $post);
				$htaccess = $this->insertGzipRule($htaccess, $post);
				$htaccess = $this->insertRewriteRule($htaccess, $post);
				//$htaccess = preg_replace("/\n+/","\n", $htaccess);

				file_put_contents($path.".htaccess", $htaccess);
			}else{
				return array("Options have been saved", "success");
				//return array(".htaccess is not writable", "error");
			}
			return array("Options have been saved", "success");

		}

		public function warningIncompatible($incompatible, $alternative = false){
			if($alternative){
				return array($incompatible." <label>needs to be deactive</label><br><label>We advise</label> <a id='alternative-plugin' target='_blank' href='".$alternative["url"]."'>".$alternative["name"]."</a>", "error");
			}else{
				return array($incompatible." <label>needs to be deactive</label>", "error");
			}
		}

		public function insertLBCRule($htaccess, $post){
			if(isset($post["wpFastestCacheLBC"]) && $post["wpFastestCacheLBC"] == "on"){


			$data = "# BEGIN LBCWpFastestCache"."\n".
					'<FilesMatch "\.(ico|pdf|flv|jpg|jpeg|png|gif|js|css|swf|x-html|css|xml|js|woff|woff2|ttf|svg|eot)(\.gz)?$">'."\n".
					'<IfModule mod_expires.c>'."\n".
					'ExpiresActive On'."\n".
					'ExpiresDefault A0'."\n".
					'ExpiresByType image/gif A2592000'."\n".
					'ExpiresByType image/png A2592000'."\n".
					'ExpiresByType image/jpg A2592000'."\n".
					'ExpiresByType image/jpeg A2592000'."\n".
					'ExpiresByType image/ico A2592000'."\n".
					'ExpiresByType image/svg+xml A2592000'."\n".
					'ExpiresByType text/css A2592000'."\n".
					'ExpiresByType text/javascript A2592000'."\n".
					'ExpiresByType application/javascript A2592000'."\n".
					'ExpiresByType application/x-javascript A2592000'."\n".
					'</IfModule>'."\n".
					'<IfModule mod_headers.c>'."\n".
					'Header set Expires "max-age=2592000, public"'."\n".
					'Header unset ETag'."\n".
					'Header set Connection keep-alive'."\n".
					'FileETag None'."\n".
					'</IfModule>'."\n".
					'</FilesMatch>'."\n".
					"# END LBCWpFastestCache"."\n";

				if(!preg_match("/BEGIN\s*LBCWpFastestCache/", $htaccess)){
					return $data.$htaccess;
				}else{
					return $htaccess;
				}
			}else{
				//delete levere browser caching
				$htaccess = preg_replace("/#\s?BEGIN\s?LBCWpFastestCache.*?#\s?END\s?LBCWpFastestCache/s", "", $htaccess);
				return $htaccess;
			}
		}

		public function insertGzipRule($htaccess, $post){
			if(isset($post["wpFastestCacheGzip"]) && $post["wpFastestCacheGzip"] == "on"){
		    	$data = "# BEGIN GzipWpFastestCache"."\n".
		          		"<IfModule mod_deflate.c>"."\n".
		          		"AddType x-font/woff .woff"."\n".
		          		"AddOutputFilterByType DEFLATE image/svg+xml"."\n".
		  				"AddOutputFilterByType DEFLATE text/plain"."\n".
		  				"AddOutputFilterByType DEFLATE text/html"."\n".
		  				"AddOutputFilterByType DEFLATE text/xml"."\n".
		  				"AddOutputFilterByType DEFLATE text/css"."\n".
		  				"AddOutputFilterByType DEFLATE text/javascript"."\n".
		  				"AddOutputFilterByType DEFLATE application/xml"."\n".
		  				"AddOutputFilterByType DEFLATE application/xhtml+xml"."\n".
		  				"AddOutputFilterByType DEFLATE application/rss+xml"."\n".
		  				"AddOutputFilterByType DEFLATE application/javascript"."\n".
		  				"AddOutputFilterByType DEFLATE application/x-javascript"."\n".
		  				"AddOutputFilterByType DEFLATE application/x-font-ttf"."\n".
						"AddOutputFilterByType DEFLATE application/vnd.ms-fontobject"."\n".
						"AddOutputFilterByType DEFLATE font/opentype font/ttf font/eot font/otf"."\n".
		  				"</IfModule>"."\n";

				if(defined("WPFC_GZIP_FOR_COMBINED_FILES") && WPFC_GZIP_FOR_COMBINED_FILES){
					$data = $data."\n".'<FilesMatch "\d+index\.(css|js)(\.gz)?$">'."\n".
			  				"# to zip the combined css and js files"."\n\n".
							"RewriteEngine On"."\n".
							"RewriteCond %{HTTP:Accept-encoding} gzip"."\n".
							"RewriteCond %{REQUEST_FILENAME}\.gz -s"."\n".
							"RewriteRule ^(.*)\.(css|js) $1\.$2\.gz [QSA]"."\n\n".
							"# to revent double gzip and give the correct mime-type"."\n\n".
							"RewriteRule \.css\.gz$ - [T=text/css,E=no-gzip:1,E=FORCE_GZIP]"."\n".
							"RewriteRule \.js\.gz$ - [T=text/javascript,E=no-gzip:1,E=FORCE_GZIP]"."\n".
							"Header set Content-Encoding gzip env=FORCE_GZIP"."\n".
							"</FilesMatch>"."\n";
				}

				$data = $data."# END GzipWpFastestCache"."\n";

				$htaccess = preg_replace("/\s*\#\s?BEGIN\s?GzipWpFastestCache.*?#\s?END\s?GzipWpFastestCache\s*/s", "", $htaccess);
				return $data.$htaccess;

			}else{
				//delete gzip rules
				$htaccess = preg_replace("/\s*\#\s?BEGIN\s?GzipWpFastestCache.*?#\s?END\s?GzipWpFastestCache\s*/s", "", $htaccess);
				return $htaccess;
			}
		}

		public function insertRewriteRule($htaccess, $post){
			if(isset($post["wpFastestCacheStatus"]) && $post["wpFastestCacheStatus"] == "on"){
				$htaccess = preg_replace("/#\s?BEGIN\s?WpFastestCache.*?#\s?END\s?WpFastestCache/s", "", $htaccess);
				$htaccess = $this->getHtaccess().$htaccess;
			}else{
				$htaccess = preg_replace("/#\s?BEGIN\s?WpFastestCache.*?#\s?END\s?WpFastestCache/s", "", $htaccess);
				$this->deleteCache();
			}

			return $htaccess;
		}

		public function prefixRedirect(){
			$forceTo = "";
			
			if(defined("WPFC_DISABLE_REDIRECTION") && WPFC_DISABLE_REDIRECTION){
				return $forceTo;
			}

			if(preg_match("/^https:\/\//", home_url())){
				if(preg_match("/^https:\/\/www\./", home_url())){
					$forceTo = "\nRewriteCond %{HTTPS} =on"."\n".
					           "RewriteCond %{HTTP_HOST} ^www.".str_replace("www.", "", $_SERVER["HTTP_HOST"])."\n";
				}else{
					$forceTo = "\nRewriteCond %{HTTPS} =on"."\n".
							   "RewriteCond %{HTTP_HOST} ^".str_replace("www.", "", $_SERVER["HTTP_HOST"])."\n";
				}
			}else{
				if(preg_match("/^http:\/\/www\./", home_url())){
					$forceTo = "\nRewriteCond %{HTTP_HOST} ^".str_replace("www.", "", $_SERVER["HTTP_HOST"])."\n".
							   "RewriteRule ^(.*)$ ".preg_quote(home_url(), "/")."\/$1 [R=301,L]"."\n";
				}else{
					$forceTo = "\nRewriteCond %{HTTP_HOST} ^www.".str_replace("www.", "", $_SERVER["HTTP_HOST"])." [NC]"."\n".
							   "RewriteRule ^(.*)$ ".preg_quote(home_url(), "/")."\/$1 [R=301,L]"."\n";
				}
			}
			return $forceTo;
		}

		public function getHtaccess(){
			$mobile = "";
			$loggedInUser = "";
			$ifIsNotSecure = "";
			$trailing_slash_rule = "";

			if(isset($_POST["wpFastestCacheMobile"]) && $_POST["wpFastestCacheMobile"] == "on"){
				$mobile = "RewriteCond %{HTTP_USER_AGENT} !^.*(".$this->getMobileUserAgents().").*$ [NC]"."\n";
			}

			if(isset($_POST["wpFastestCacheLoggedInUser"]) && $_POST["wpFastestCacheLoggedInUser"] == "on"){
				$loggedInUser = "RewriteCond %{HTTP:Cookie} !(comment_author_|wordpress_logged_in|wp_woocommerce_session)"."\n";
			}

			if(!preg_match("/^https/i", get_option("home"))){
				$ifIsNotSecure = "RewriteCond %{HTTPS} !=on";
			}

			if($this->is_trailing_slash()){
				$trailing_slash_rule = "RewriteCond %{REQUEST_URI} \/$"."\n";
			}else{
				//toDo
			}

			$data = "# BEGIN WpFastestCache"."\n".
					"<IfModule mod_rewrite.c>"."\n".
					"RewriteEngine On"."\n".
					"RewriteBase /"."\n".
					$this->ruleForWpContent()."\n".
					$this->prefixRedirect().
					$this->excludeRules()."\n".
					$this->http_condition_rule()."\n".
					"RewriteCond %{HTTP_USER_AGENT} !(".$this->get_excluded_useragent().")"."\n".
					"RewriteCond %{REQUEST_METHOD} !POST"."\n".
					$ifIsNotSecure."\n".
					"RewriteCond %{REQUEST_URI} !(\/){2}$"."\n".
					$trailing_slash_rule.
					"RewriteCond %{QUERY_STRING} !.+"."\n".$loggedInUser.
					'RewriteCond %{HTTP:Profile} !^[a-z0-9\"]+ [NC]'."\n".$mobile;
			

			if(ABSPATH == "//"){
				$data = $data."RewriteCond %{DOCUMENT_ROOT}/".WPFC_WP_CONTENT_BASENAME."/cache/all/$1/index.html -f"."\n";
			}else{
				$data = $data."RewriteCond %{DOCUMENT_ROOT}/".WPFC_WP_CONTENT_BASENAME."/cache/all/$1/index.html -f [or]"."\n";
				$data = $data."RewriteCond ".WPFC_WP_CONTENT_DIR."/cache/all/".$this->getRewriteBase(true)."$1/index.html -f"."\n";
			}

			$data = $data.'RewriteRule ^(.*) "/'.$this->getRewriteBase().WPFC_WP_CONTENT_BASENAME.'/cache/all/'.$this->getRewriteBase(true).'$1/index.html" [L]'."\n";
			
			//RewriteRule !/  "/wp-content/cache/all/index.html" [L]


			if(class_exists("WpFcMobileCache") && isset($this->options->wpFastestCacheMobileTheme) && $this->options->wpFastestCacheMobileTheme){
				$wpfc_mobile = new WpFcMobileCache();

				if($this->isPluginActive('wptouch/wptouch.php') || $this->isPluginActive('wptouch-pro/wptouch-pro.php')){
					$wpfc_mobile->set_wptouch(true);
				}else{
					$wpfc_mobile->set_wptouch(false);
				}

				$data = $data."\n\n\n".$wpfc_mobile->update_htaccess($data);
			}

			$data = $data."</IfModule>"."\n".
					"<FilesMatch \"\.(html|htm)$\">"."\n".
					"AddDefaultCharset UTF-8"."\n".
					"<ifModule mod_headers.c>"."\n".
					"FileETag None"."\n".
					"Header unset ETag"."\n".
					"Header set Cache-Control \"max-age=0, no-cache, no-store, must-revalidate\""."\n".
					"Header set Pragma \"no-cache\""."\n".
					"Header set Expires \"Mon, 29 Oct 1923 20:30:00 GMT\""."\n".
					"</ifModule>"."\n".
					"</FilesMatch>"."\n".
					"# END WpFastestCache"."\n";
			return preg_replace("/\n+/","\n", $data);
		}

		public function http_condition_rule(){
			$http_host = preg_replace("/(http(s?)\:)?\/\/(www\d*\.)?/i", "", trim(home_url(), "/"));

			if(preg_match("/\//", $http_host)){
				$http_host = strstr($http_host, '/', true);
			}

			if(preg_match("/www\./", home_url())){
				$http_host = "www.".$http_host;
			}

			return "RewriteCond %{HTTP_HOST} ^".$http_host;
		}

		public function ruleForWpContent(){
			return "";
			$newContentPath = str_replace(home_url(), "", content_url());
			if(!preg_match("/wp-content/", $newContentPath)){
				$newContentPath = trim($newContentPath, "/");
				return "RewriteRule ^".$newContentPath."/cache/(.*) ".WPFC_WP_CONTENT_DIR."/cache/$1 [L]"."\n";
			}
			return "";
		}

		public function getRewriteBase($sub = ""){
			if($sub && $this->is_subdirectory_install()){
				$trimedProtocol = preg_replace("/http:\/\/|https:\/\//", "", trim(home_url(), "/"));
				$path = strstr($trimedProtocol, '/');

				if($path){
					return trim($path, "/")."/";
				}else{
					return "";
				}
			}
			
			$url = rtrim(site_url(), "/");
			preg_match("/https?:\/\/[^\/]+(.*)/", $url, $out);

			if(isset($out[1]) && $out[1]){
				$out[1] = trim($out[1], "/");

				if(preg_match("/\/".preg_quote($out[1], "/")."\//", WPFC_WP_CONTENT_DIR)){
					return $out[1]."/";
				}else{
					return "";
				}
			}else{
				return "";
			}
		}



		public function checkSuperCache($path, $htaccess){
			if($this->isPluginActive('wp-super-cache/wp-cache.php')){
				return array("WP Super Cache needs to be deactive", "error");
			}else{
				@unlink($path."wp-content/wp-cache-config.php");

				$message = "";
				
				if(is_file($path."wp-content/wp-cache-config.php")){
					$message .= "<br>- be sure that you removed /wp-content/wp-cache-config.php";
				}

				if(preg_match("/supercache/", $htaccess)){
					$message .= "<br>- be sure that you removed the rules of super cache from the .htaccess";
				}

				return $message ? array("WP Super Cache cannot remove its own remnants so please follow the steps below".$message, "error") : "";
			}

			return "";
		}

		public function check_htaccess(){
			$path = ABSPATH;

			if($this->is_subdirectory_install()){
				$path = $this->getABSPATH();
			}
			
			if(!is_writable($path.".htaccess") && count($_POST) > 0){
				include_once(WPFC_MAIN_PATH."templates/htaccess.html");

				$htaccess = @file_get_contents($path.".htaccess");

				if(isset($this->options->wpFastestCacheLBC)){
					$htaccess = $this->insertLBCRule($htaccess, array("wpFastestCacheLBC" => "on"));
				}
				if(isset($this->options->wpFastestCacheGzip)){
					$htaccess = $this->insertGzipRule($htaccess, array("wpFastestCacheGzip" => "on"));
				}
				if(isset($this->options->wpFastestCacheStatus)){
					$htaccess = $this->insertRewriteRule($htaccess, array("wpFastestCacheStatus" => "on"));
				}
				
				$htaccess = preg_replace("/\n+/","\n", $htaccess);

				echo "<noscript id='wpfc-htaccess-data'>".$htaccess."</noscript>";
				echo "<noscript id='wpfc-htaccess-path-data'>".$path.".htaccess"."</noscript>";
				?>
				<script type="text/javascript">
					Wpfc_Dialog.dialog("wpfc-htaccess-modal");
					jQuery("#wpfc-htaccess-modal-rules").html(jQuery("#wpfc-htaccess-data").html());
					jQuery("#wpfc-htaccess-modal-path").html(jQuery("#wpfc-htaccess-path-data").html());
				</script>
				<?php
			}

		}

		public function optionsPage(){
			$this->systemMessage = count($this->systemMessage) > 0 ? $this->systemMessage : $this->getSystemMessage();

			$wpFastestCacheCombineCss = isset($this->options->wpFastestCacheCombineCss) ? 'checked="checked"' : "";
			$wpFastestCacheGzip = isset($this->options->wpFastestCacheGzip) ? 'checked="checked"' : "";
			$wpFastestCacheCombineJs = isset($this->options->wpFastestCacheCombineJs) ? 'checked="checked"' : "";
			$wpFastestCacheCombineJsPowerFul = isset($this->options->wpFastestCacheCombineJsPowerFul) ? 'checked="checked"' : "";
			$wpFastestCacheRenderBlocking = isset($this->options->wpFastestCacheRenderBlocking) ? 'checked="checked"' : "";
			
			$wpFastestCacheRenderBlockingCss = isset($this->options->wpFastestCacheRenderBlockingCss) ? 'checked="checked"' : "";

			$wpFastestCacheLanguage = isset($this->options->wpFastestCacheLanguage) ? $this->options->wpFastestCacheLanguage : "eng";
			

			$wpFastestCacheLazyLoad = isset($this->options->wpFastestCacheLazyLoad) ? 'checked="checked"' : "";




			$wpFastestCacheLBC = isset($this->options->wpFastestCacheLBC) ? 'checked="checked"' : "";
			$wpFastestCacheLoggedInUser = isset($this->options->wpFastestCacheLoggedInUser) ? 'checked="checked"' : "";
			$wpFastestCacheMinifyCss = isset($this->options->wpFastestCacheMinifyCss) ? 'checked="checked"' : "";

			$wpFastestCacheMinifyCssPowerFul = isset($this->options->wpFastestCacheMinifyCssPowerFul) ? 'checked="checked"' : "";


			$wpFastestCacheMinifyHtml = isset($this->options->wpFastestCacheMinifyHtml) ? 'checked="checked"' : "";
			$wpFastestCacheMinifyHtmlPowerFul = isset($this->options->wpFastestCacheMinifyHtmlPowerFul) ? 'checked="checked"' : "";

			$wpFastestCacheMinifyJs = isset($this->options->wpFastestCacheMinifyJs) ? 'checked="checked"' : "";

			$wpFastestCacheMobile = isset($this->options->wpFastestCacheMobile) ? 'checked="checked"' : "";
			$wpFastestCacheMobileTheme = isset($this->options->wpFastestCacheMobileTheme) ? 'checked="checked"' : "";

			$wpFastestCacheNewPost = isset($this->options->wpFastestCacheNewPost) ? 'checked="checked"' : "";
			
			$wpFastestCacheRemoveComments = isset($this->options->wpFastestCacheRemoveComments) ? 'checked="checked"' : "";


			$wpFastestCachePreload = isset($this->options->wpFastestCachePreload) ? 'checked="checked"' : "";
			$wpFastestCachePreload_homepage = isset($this->options->wpFastestCachePreload_homepage) ? 'checked="checked"' : "";
			$wpFastestCachePreload_post = isset($this->options->wpFastestCachePreload_post) ? 'checked="checked"' : "";
			$wpFastestCachePreload_category = isset($this->options->wpFastestCachePreload_category) ? 'checked="checked"' : "";
			$wpFastestCachePreload_page = isset($this->options->wpFastestCachePreload_page) ? 'checked="checked"' : "";
			$wpFastestCachePreload_number = isset($this->options->wpFastestCachePreload_number) ? $this->options->wpFastestCachePreload_number : 4;


			$wpFastestCacheStatus = isset($this->options->wpFastestCacheStatus) ? 'checked="checked"' : "";
			$wpFastestCacheTimeOut = isset($this->cronJobSettings["period"]) ? $this->cronJobSettings["period"] : "";

			$wpFastestCacheUpdatePost = isset($this->options->wpFastestCacheUpdatePost) ? 'checked="checked"' : "";
			?>
			
			<div class="wrap">

				<h2>WP Fastest Cache Options</h2>
				<?php if($this->systemMessage){ ?>
					<div style="display:block !important;" class="updated <?php echo $this->systemMessage[1]."-wpfc"; ?>" id="message"><p><?php echo $this->systemMessage[0]; ?></p></div>
				<?php } ?>
				<div class="tabGroup">
					<?php
						$tabs = array(array("id"=>"wpfc-options","title"=>"Settings"),
									  array("id"=>"wpfc-deleteCache","title"=>"Delete Cache"),
									  array("id"=>"wpfc-cacheTimeout","title"=>"Cache Timeout"));

						if(class_exists("WpFastestCacheImageOptimisation")){
						}
							array_push($tabs, array("id"=>"wpfc-imageOptimisation","title"=>"Image Optimization"));
						
						array_push($tabs, array("id"=>"wpfc-premium","title"=>"Premium"));

						array_push($tabs, array("id"=>"wpfc-exclude","title"=>"Exclude"));

						array_push($tabs, array("id"=>"wpfc-cdn","title"=>"CDN"));

						foreach ($tabs as $key => $value){
							$checked = "";

							//tab of "delete css and js" has been removed so there is need to check it
							if(isset($_POST["wpFastestCachePage"]) && $_POST["wpFastestCachePage"] && $_POST["wpFastestCachePage"] == "deleteCssAndJsCache"){
								$_POST["wpFastestCachePage"] = "deleteCache";
							}

							if(!isset($_POST["wpFastestCachePage"]) && $value["id"] == "wpfc-options"){
								$checked = ' checked="checked" ';
							}else if((isset($_POST["wpFastestCachePage"])) && ("wpfc-".$_POST["wpFastestCachePage"] == $value["id"])){
								$checked = ' checked="checked" ';
							}
							echo '<input '.$checked.' type="radio" id="'.$value["id"].'" name="tabGroup1" style="display:none;">'."\n";
							echo '<label for="'.$value["id"].'">'.$value["title"].'</label>'."\n";
						}
					?>
				    <br>
				    <div class="tab1" style="padding-left:10px;">
						<form method="post" name="wp_manager">
							<input type="hidden" value="options" name="wpFastestCachePage">
							<div class="questionCon">
								<div class="question">Cache System</div>
								<div class="inputCon"><input type="checkbox" <?php echo $wpFastestCacheStatus; ?> id="wpFastestCacheStatus" name="wpFastestCacheStatus"><label for="wpFastestCacheStatus">Enable</label></div>
							</div>
							
							<div class="questionCon">
								<div class="question">Preload</div>
								<div class="inputCon"><input type="checkbox" <?php echo $wpFastestCachePreload; ?> id="wpFastestCachePreload" name="wpFastestCachePreload"><label for="wpFastestCachePreload">Create the cache of all the site automatically</label></div>
								<div class="get-info"><a target="_blank" href="http://www.wpfastestcache.com/features/preload-settings/"><img src="<?php echo plugins_url("wp-fastest-cache/images/info.png"); ?>" /></a></div>
							</div>

							<?php include(WPFC_MAIN_PATH."templates/update_now.php"); ?>

							<?php include(WPFC_MAIN_PATH."templates/preload.php"); ?>

							<div class="questionCon">
								<div class="question">Logged-in Users</div>
								<div class="inputCon"><input type="checkbox" <?php echo $wpFastestCacheLoggedInUser; ?> id="wpFastestCacheLoggedInUser" name="wpFastestCacheLoggedInUser"><label for="wpFastestCacheLoggedInUser">Don't show the cached version for logged-in users</label></div>
							</div>

							<div class="questionCon">
								<div class="question">Mobile</div>
								<div class="inputCon"><input type="checkbox" <?php echo $wpFastestCacheMobile; ?> id="wpFastestCacheMobile" name="wpFastestCacheMobile"><label for="wpFastestCacheMobile">Don't show the cached version for desktop to mobile devices</label></div>
							</div>

							<?php if(class_exists("WpFastestCachePowerfulHtml")){ ?>
							<div class="questionCon">
								<div class="question">Mobile Theme</div>
								<div class="inputCon"><input type="checkbox" <?php echo $wpFastestCacheMobileTheme; ?> id="wpFastestCacheMobileTheme" name="wpFastestCacheMobileTheme"><label for="wpFastestCacheMobileTheme">Create cache for mobile theme</label></div>
							</div>
							<?php }else{ ?>
							<div class="questionCon disabled">
								<div class="question">Mobile Theme</div>
								<div class="inputCon"><input type="checkbox" id="wpFastestCacheMobileTheme"><label for="wpFastestCacheMobileTheme">Create cache for mobile theme</label></div>
							</div>
							<?php } ?>

							<div class="questionCon">
								<div class="question">New Post</div>
								<div class="inputCon"><input type="checkbox" <?php echo $wpFastestCacheNewPost; ?> id="wpFastestCacheNewPost" name="wpFastestCacheNewPost"><label for="wpFastestCacheNewPost">Clear cache files when a post or page is published</label></div>
							</div>

							<?php include(WPFC_MAIN_PATH."templates/newpost.php"); ?>

							<div class="questionCon">
								<div class="question">Update Post</div>
								<div class="inputCon"><input type="checkbox" <?php echo $wpFastestCacheUpdatePost; ?> id="wpFastestCacheUpdatePost" name="wpFastestCacheUpdatePost"><label for="wpFastestCacheUpdatePost">Clear cache files when a post or page is updated</label></div>
								<div class="get-info"><a target="_blank" href="http://www.wpfastestcache.com/tutorial/to-clear-cache-after-update"><img src="<?php echo plugins_url("wp-fastest-cache/images/info.png"); ?>" /></a></div>
							</div>

							<?php include(WPFC_MAIN_PATH."templates/updatepost.php"); ?>


							<div class="questionCon">
								<div class="question">Minify HTML</div>
								<div class="inputCon"><input type="checkbox" <?php echo $wpFastestCacheMinifyHtml; ?> id="wpFastestCacheMinifyHtml" name="wpFastestCacheMinifyHtml"><label for="wpFastestCacheMinifyHtml">You can decrease the size of page</label></div>
								<div class="get-info"><a target="_blank" href="http://www.wpfastestcache.com/optimization/minify-html/"><img src="<?php echo plugins_url("wp-fastest-cache/images/info.png"); ?>" /></a></div>
							</div>

							<?php if(class_exists("WpFastestCachePowerfulHtml")){ ?>
							<div class="questionCon">
								<div class="question">Minify HTML Plus</div>
								<div class="inputCon"><input type="checkbox" <?php echo $wpFastestCacheMinifyHtmlPowerFul; ?> id="wpFastestCacheMinifyHtmlPowerFul" name="wpFastestCacheMinifyHtmlPowerFul"><label for="wpFastestCacheMinifyHtmlPowerFul">More powerful minify html</label></div>
							</div>
							<?php }else{ ?>
							<div class="questionCon disabled">
								<div class="question">Minify HTML Plus</div>
								<div class="inputCon"><input type="checkbox" id="wpFastestCacheMinifyHtmlPowerFul"><label for="wpFastestCacheMinifyHtmlPowerFul">More powerful minify html</label></div>
							</div>
							<?php } ?>



							<div class="questionCon">
								<div class="question">Minify Css</div>
								<div class="inputCon"><input type="checkbox" <?php echo $wpFastestCacheMinifyCss; ?> id="wpFastestCacheMinifyCss" name="wpFastestCacheMinifyCss"><label for="wpFastestCacheMinifyCss">You can decrease the size of css files</label></div>
								<div class="get-info"><a target="_blank" href="http://www.wpfastestcache.com/optimization/minify-css/"><img src="<?php echo plugins_url("wp-fastest-cache/images/info.png"); ?>" /></a></div>
							</div>



							<?php if(class_exists("WpFastestCachePowerfulHtml") && method_exists("WpFastestCachePowerfulHtml", "minify_css")){ ?>
							<div class="questionCon">
								<div class="question">Minify Css Plus</div>
								<div class="inputCon"><input type="checkbox" <?php echo $wpFastestCacheMinifyCssPowerFul; ?> id="wpFastestCacheMinifyCssPowerFul" name="wpFastestCacheMinifyCssPowerFul"><label for="wpFastestCacheMinifyCssPowerFul">More powerful minify css</label></div>
							</div>
							<?php }else{ ?>
							<div class="questionCon disabled">
								<div class="question">Minify Css Plus</div>
								<div class="inputCon"><input type="checkbox" id="wpFastestCacheMinifyCssPowerFul"><label for="wpFastestCacheMinifyCssPowerFul">More powerful minify css</label></div>
							</div>
							<?php } ?>


							<div class="questionCon">
								<div class="question">Combine Css</div>
								<div class="inputCon"><input type="checkbox" <?php echo $wpFastestCacheCombineCss; ?> id="wpFastestCacheCombineCss" name="wpFastestCacheCombineCss"><label for="wpFastestCacheCombineCss">Reduce HTTP requests through combined css files</label></div>
								<div class="get-info"><a target="_blank" href="http://www.wpfastestcache.com/optimization/combine-js-css-files/"><img src="<?php echo plugins_url("wp-fastest-cache/images/info.png"); ?>" /></a></div>
							</div>

							<?php if(class_exists("WpFastestCachePowerfulHtml")){ ?>
								<?php if(method_exists("WpFastestCachePowerfulHtml", "minify_js_in_body")){ ?>
									<div class="questionCon">
										<div class="question">Minify Js</div>
										<div class="inputCon"><input type="checkbox" <?php echo $wpFastestCacheMinifyJs; ?> id="wpFastestCacheMinifyJs" name="wpFastestCacheMinifyJs"><label for="wpFastestCacheMinifyJs">You can decrease the size of js files</label></div>
									</div>
								<?php }else{ ?>
									<div class="questionCon update-needed">
										<div class="question">Minify Js</div>
										<div class="inputCon"><input type="checkbox" id="wpFastestCacheMinifyJs"><label for="wpFastestCacheMinifyJs">You can decrease the size of js files</label></div>
									</div>
								<?php } ?>
							<?php }else{ ?>
							<div class="questionCon disabled">
								<div class="question">Minify Js</div>
								<div class="inputCon"><input type="checkbox" id="wpFastestCacheMinifyJs"><label for="wpFastestCacheMinifyJs">You can decrease the size of js files</label></div>
							</div>
							<?php } ?>

							<div class="questionCon">
								<div class="question">Combine Js</div>
								<div class="inputCon"><input type="checkbox" <?php echo $wpFastestCacheCombineJs; ?> id="wpFastestCacheCombineJs" name="wpFastestCacheCombineJs"><label for="wpFastestCacheCombineJs">Reduce HTTP requests through combined js files</label> <b style="color:red;">(header)</b></div>
								<div class="get-info"><a target="_blank" href="http://www.wpfastestcache.com/optimization/combine-js-css-files/"><img src="<?php echo plugins_url("wp-fastest-cache/images/info.png"); ?>" /></a></div>
							</div>

							<?php if(class_exists("WpFastestCachePowerfulHtml")){ ?> 
								<?php if(method_exists("WpFastestCachePowerfulHtml", "combine_js_in_footer")){ ?>
									<div class="questionCon"> <div class="question">Combine Js Plus</div>
										<div class="inputCon"><input type="checkbox" <?php echo $wpFastestCacheCombineJsPowerFul; ?> id="wpFastestCacheCombineJsPowerFul" name="wpFastestCacheCombineJsPowerFul">
											<label for="wpFastestCacheCombineJsPowerFul">Reduce HTTP requests through combined js files</label> <b style="color:red;">(footer)</b>
										</div> 
									</div>
								<?php }else{ ?>
									<div class="questionCon update-needed">
										<div class="question">Combine Js Plus</div>
										<div class="inputCon"><input type="checkbox" id="wpFastestCacheCombineJsPowerFul"><label for="wpFastestCacheCombineJsPowerFul">Reduce HTTP requests through combined js files</label> <b style="color:red;">(footer)</b></div> 
									</div> 
								<?php } ?>
							<?php }else{ ?>
								<div class="questionCon disabled">
									<div class="question">Combine Js Plus</div>
									<div class="inputCon"><input type="checkbox" id="wpFastestCacheCombineJsPowerFul"><label for="wpFastestCacheCombineJsPowerFul">Reduce HTTP requests through combined js files</label> <b style="color:red;">(footer)</b></div>
								</div>
							<?php } ?>

							<div class="questionCon">
								<div class="question">Gzip</div>
								<div class="inputCon"><input type="checkbox" <?php echo $wpFastestCacheGzip; ?> id="wpFastestCacheGzip" name="wpFastestCacheGzip"><label for="wpFastestCacheGzip">Reduce the size of files sent from your server</label></div>
								<div class="get-info"><a target="_blank" href="http://www.wpfastestcache.com/optimization/enable-gzip-compression/"><img src="<?php echo plugins_url("wp-fastest-cache/images/info.png"); ?>" /></a></div>
							</div>

							<div class="questionCon">
								<div class="question">Browser Caching</div>
								<div class="inputCon"><input type="checkbox" <?php echo $wpFastestCacheLBC; ?> id="wpFastestCacheLBC" name="wpFastestCacheLBC"><label for="wpFastestCacheLBC">Reduce page load times for repeat visitors</label></div>
								<div class="get-info"><a target="_blank" href="http://www.wpfastestcache.com/optimization/leverage-browser-caching/"><img src="<?php echo plugins_url("wp-fastest-cache/images/info.png"); ?>" /></a></div>
							</div>

							<?php if(class_exists("WpFastestCachePowerfulHtml")){ ?>
								<?php if(method_exists("WpFastestCachePowerfulHtml", "render_blocking")){ ?>
									<?php
									$tester_arr = array(
														"de-DE",
														"es_CL",
														"es_AR",
														"es_GT",
														"es_PE",
														"es_VE",
														"es_CO",
														"es_MX",
														"es_ES",
														"es-ES",
														"fr-FR",
														"fr-BE",
														"fr-CA",
														"fr-FR",
														"it-IT",
														"ja",
														"nl-NL",
														"pt-PT",
														"pt-BR",
														"tr-TR",
														"berkatan.com",
														"worthynews.com",
														"beherit.pl",
														"freshonlinejobs.com",
														"chelly.id",
														"spycoupon.in",
														"comfortableshoeguide.com",
														"043web.nl",
														"ifra.nl",
														"goflre.com",
														"highhouseinsurance.com",
														"silongedu.com",
														"ssn.localhost",
														"speedskatingnews.info",
														"vicuras.dk",
														"juicycherries.ie",
														"citymapxl.com",
														"surfingrealty.com",
														"biz163.inmotionhosting.com",
														"apreet.com",
														"rkade.uk.com",
														"alaskaremote.com",
														"quanglepro.com",
														"marccastricum.nl",
														"flexiscreens.com",
														"montarent.nl",
														"tropicalserver.com",
														"dgidirect.ca",
														"campfireaudio.com",
														"dgitest.qtelmedia.ca",
														"qtelmedia.ca",
														"solacity.com",
														"123casinos.com",
														"en.metinkerem.com",
														"rjspest.com",
														"alexandra-boutique.co.uk",
														"dev.pshsa.ca",
														"pshsa.ca",
														"harlemlocal.com",
														"adoptafamily.org",
														"thebrooklandscars.co.uk",
														"smartyblog.com",
														"stevechaplinphotography.com",
														"oemperformance.com",
														"image-restore.co.uk",
														"technews247.de",
														"allfacebook.de",
														"aerospaceengineering.aero",
														"swankyrecipes.com",
														"mybettermarriage.com",
														"webbdo.se",
														"bellsalaska.com",
														"tryhealthier.com",
														"aboutpainting.ca",
														"countrygraphics.com",
														"promanagewp.com.au",
														"webstrategy.de",
														"melaniedawn.co",
														"rykon.ca",
														"wpfastestcache.com",
														"hakangurer.com",
														"ronischmuck.ch",
														"musthaveguy.com",
														"artofwellbeing.com",
														"mrmasterkey.com",
														"ww2.mrmasterkey.com",
														"androiduygulamalar.com",
														"themovingroad.com",
														"videoseyredin.com",
														"internetzanatlija.com",
														"xtremerain.com",
														"applerepairclub.com",
														"nackenstuetzkissen-test.de", 
														"coincollectingenterprises.com"
														);

									if(in_array(get_bloginfo('language'), $tester_arr) || in_array(str_replace("www.", "", $_SERVER["HTTP_HOST"]), $tester_arr)){ ?>
										
									<?php } ?>
								<?php } ?>
							<?php } ?>

							<?php if(class_exists("WpFastestCachePowerfulHtml")){ ?> 
								<?php if(method_exists("WpFastestCachePowerfulHtml", "render_blocking")){ ?>
									<div class="questionCon">
										<div class="question">Render Blocking Js</div>
										<div class="inputCon"><input type="checkbox" <?php echo $wpFastestCacheRenderBlocking; ?> id="wpFastestCacheRenderBlocking" name="wpFastestCacheRenderBlocking"><label for="wpFastestCacheRenderBlocking">Remove render-blocking JavaScript</label></div>
										<div class="get-info"><a target="_blank" href="http://www.wpfastestcache.com/premium/render-blocking-js/"><img src="<?php echo plugins_url("wp-fastest-cache/images/info.png"); ?>" /></a></div>
									</div>
								<?php }else{ ?>
									<div class="questionCon update-needed">
										<div class="question">Render Blocking Js</div>
										<div class="inputCon"><input type="checkbox" id="wpFastestCacheRenderBlocking" name="wpFastestCacheRenderBlocking"><label for="wpFastestCacheRenderBlocking">Remove render-blocking JavaScript</label></div>
										<div class="get-info"><a target="_blank" href="http://www.wpfastestcache.com/premium/render-blocking-js/"><img src="<?php echo plugins_url("wp-fastest-cache/images/info.png"); ?>" /></a></div>
									</div>
								<?php } ?>
							<?php }else{ ?>
								<div class="questionCon disabled">
									<div class="question">Render Blocking Js</div>
									<div class="inputCon"><input type="checkbox" id="wpFastestCacheRenderBlocking" name="wpFastestCacheRenderBlocking"><label for="wpFastestCacheRenderBlocking">Remove render-blocking JavaScript</label></div>
									<div class="get-info"><a target="_blank" href="http://www.wpfastestcache.com/premium/render-blocking-js/"><img src="<?php echo plugins_url("wp-fastest-cache/images/info.png"); ?>" /></a></div>
								</div>
							<?php } ?>


							<?php if(false){ ?>
							<div class="questionCon">
								<div class="question">Lazy Load</div>
								<div class="inputCon"><input type="checkbox" <?php echo $wpFastestCacheLazyLoad; ?> id="wpFastestCacheLazyLoad" name="wpFastestCacheLazyLoad"><label for="wpFastestCacheLazyLoad">Lazy Load</label></div>
								<div class="get-info"><a target="_blank" href="http://www.wpfastestcache.com/premium/render-blocking-js/"><img src="<?php echo plugins_url("wp-fastest-cache/images/info.png"); ?>" /></a></div>
							</div>

							<?php include(WPFC_MAIN_PATH."templates/lazy_load.php"); ?>

							<?php } ?>

							<div class="questionCon">
								<div class="question">Language</div>
								<div class="inputCon">
									<select id="wpFastestCacheLanguage" name="wpFastestCacheLanguage">
										<option value="cn">中文</option>
										<option value="de">Deutsch</option>
										<option value="eng">English</option>
										<option value="es">Español</option>
										<option value="fr">Français</option>
										<option value="it">Italiana</option>
										<option value="nl">Nederlands</option>
										<option value="ja">日本語</option>
										<option value="pl">Polski</option>
										<option value="pt">Português</option>
										<option value="ro">Română</option>
										<option value="ru">Русский</option>
										<option value="fi">Suomi</option>
										<option value="sv">Svenska</option>
										<option value="tr">Türkçe</option>
										<!-- <option value="ukr">Українська</option> -->
									</select> 
								</div>
							</div>
							<div class="questionCon qsubmit">
								<div class="submit"><input type="submit" value="Submit" class="button-primary"></div>
							</div>
						</form>
				    </div>
				    <div class="tab2">
				    	<div id="container-show-hide-logs" style="display:none; float:right; padding-right:20px; cursor:pointer;">
				    		<span id="show-delete-log">Show Logs</span>
				    		<span id="hide-delete-log" style="display:none;">Hide Logs</span>
				    	</div>

				    	<?php 
			   				if(class_exists("WpFastestCacheStatics")){
				   				$cache_statics = new WpFastestCacheStatics();
				   				$cache_statics->statics();
			   				}else{
			   					?>
					   			<div style="z-index:9999;width: 160px; height: 60px; position: absolute; margin-left: 254px; margin-top: 25px; color: white;">
						    		<div style="font-family:sans-serif;font-size:13px;text-align: center; border-radius: 5px; float: left; background-color: rgb(51, 51, 51); color: white; width: 147px; padding: 20px 50px;">
						    			<label>Only available in Premium version</label>
						    		</div>
						    	</div>
					   			<div style="opacity:0.3;float: right; padding-right: 20px; cursor: pointer;">
						    		<span id="show-delete-log">Show Logs</span>
						    		<span id="hide-delete-log" style="display:none;">Hide Logs</span>
						    	</div>
						    	<h2 style="opacity:0.3;padding-left:20px;padding-bottom:10px;">Cache Statics</h2>
						    	<div id="wpfc-cache-statics" style="opacity:0.3;width:100%;float:right;margin:15px 0;">
									<style type="text/css">
										#wpfc-cache-statics > div{
											float: left;
											width: 24%;
											text-align: center;
										}
										#wpfc-cache-statics > div > p{
											font-size: 1.3em;
											font-weight: 600;
											margin-top: 10px;
										}
										#wpfc-cache-statics-desktop, #wpfc-cache-statics-mobile, #wpfc-cache-statics-css {
											border-right: 1px solid #ddd;
										}
									</style>
									<div id="wpfc-cache-statics-desktop" style="margin-left:1%;">
										<i class="flaticon-desktop1"></i> 
										<p id="wpfc-cache-statics-desktop-data">12.3Kb / 1 Items</p>
									</div>
									<div id="wpfc-cache-statics-mobile">
										<i class="flaticon-smart"></i> 
										<p id="wpfc-cache-statics-mobile-data">12.4Kb / 1 Items</p>
									</div>
									<div id="wpfc-cache-statics-css">
										<i class="flaticon-css4"></i> 
										<p id="wpfc-cache-statics-css-data">278.2Kb / 9 Items</p>
									</div>
									<div id="wpfc-cache-statics-js">
										<i class="flaticon-js"></i> 
										<p id="wpfc-cache-statics-js-data">338.4Kb / 16 Items</p>
									</div>
								</div>
			   					<?php
			   				}
				   		?>
				   		<h2 id="delete-cache-h2" style="padding-left:20px;padding-bottom:10px;">Delete Cache</h2>
				    	<form method="post" name="wp_manager" class="delete-line">
				    		<input type="hidden" value="deleteCache" name="wpFastestCachePage">
				    		<div class="questionCon qsubmit left">
				    			<div class="submit"><input type="submit" value="Delete Cache" class="button-primary"></div>
				    		</div>
				    		<div class="questionCon right">
				    			<div style="padding-left:11px;">
				    			<label>You can delete all cache files</label><br>
				    			<label>Target folder</label> <b><?php echo $this->getWpContentDir(); ?>/cache/all</b>
				    			</div>
				    		</div>
				   		</form>
				   		<form method="post" name="wp_manager" class="delete-line" style="height: 120px;">
				    		<input type="hidden" value="deleteCssAndJsCache" name="wpFastestCachePage">
				    		<div class="questionCon qsubmit left">
				    			<div class="submit"><input type="submit" value="Delete Cache and Minified CSS/JS" class="button-primary"></div>
				    		</div>
				    		<div class="questionCon right">
				    			<div style="padding-left:11px;">
				    			<label>If you modify any css file, you have to delete minified css files</label><br>
				    			<label>All cache files will be removed as well</label><br>
				    			<label>Target folder</label> <b><?php echo $this->getWpContentDir(); ?>/cache/all</b><br>
				    			<!-- <label>Target folder</label> <b><?php echo $this->getWpContentDir(); ?>/cache/wpfc-mobile-cache</b><br> -->
				    			<label>Target folder</label> <b><?php echo $this->getWpContentDir(); ?>/cache/wpfc-minified</b>
				    			</div>
				    		</div>
				   		</form>
				   		<?php 
				   				if(class_exists("WpFastestCacheLogs")){
					   				$logs = new WpFastestCacheLogs("delete");
					   				$logs->printLogs();
				   				}
				   		?>
				    </div>
				    <div class="tab3">
				    	<h2 style="padding-bottom:10px;padding-left:20px;float:left;">Timeout Rules</h2>

				    	<!-- samples start: clones -->
				    	<div class="wpfc-timeout-rule-line" style="display:none;">
							<div class="wpfc-timeout-rule-line-left">
								<select name="wpfc-timeout-rule-prefix">
										<option selected="" value=""></option>
										<option value="all">All</option>
										<option value="homepage">Home Page</option>
										<option value="startwith">Start With</option>
										<option value="contain">Contain</option>
										<option value="exact">Exact</option>
								</select>
							</div>
							<div class="wpfc-timeout-rule-line-middle">
								<input type="text" name="wpfc-timeout-rule-content">
								<input type="text" name="wpfc-timeout-rule-schedule">
								<input type="text" name="wpfc-timeout-rule-hour">
								<input type="text" name="wpfc-timeout-rule-minute">
							</div>
						</div>
						<!-- item sample -->
	    				<div class="wpfc-timeout-item" tabindex="1" prefix="" content="" schedule="" style="position: relative;display:none;">
	    					<div class="app">
				    			<div class="wpfc-timeout-item-form-title">Title M</div>
				    			<span class="wpfc-timeout-item-details wpfc-timeout-item-url"></span>
	    					</div>
			    		</div>
		    			<!-- samples end -->

				    	<div style="float:left;margin-top:-37px;padding-left:628px;">
					    	<button type="button" class="wpfc-add-new-timeout-button wpfc-dialog-buttons" style="display: inline-block;padding: 4px 10px;">
					    		<span>Add New Rule</span>
					    	</button>
				    	</div>

				    	<div class="wpfc-timeout-list" style="display: block;width:98%;float:left;">

				    	</div>

				    	<?php
				    		include(WPFC_MAIN_PATH."templates/timeout.php");
				    	?>

				    	<form method="post" name="wp_manager">
				    		<input type="hidden" value="timeout" name="wpFastestCachePage">
				    		<div class="wpfc-timeout-rule-container"></div>
				    	</form>
				    	<script type="text/javascript">

					    	<?php
					    		$schedules_rules = array();
						    	$crons = _get_cron_array();

						    	foreach ((array)$crons as $cron_key => $cron_value) {
						    		foreach ( (array) $cron_value as $hook => $events ) {
						    			if(preg_match("/^wp\_fastest\_cache(.*)/", $hook, $id)){
						    				if(!$id[1] || preg_match("/^\_(\d+)$/", $id[1])){
							    				foreach ( (array) $events as $event_key => $event ) {
							    					$tmp_array = array();

							    					if($id[1]){
							    						// new cronjob which is (wp_fastest_cache_d+)
								    					$tmp_std = json_decode($event["args"][0]);

								    					$tmp_array = array("schedule" => $event["schedule"],
								    									   "prefix" => $tmp_std->prefix,
								    									   "content" => $tmp_std->content);

								    					if(isset($tmp_std->hour) && isset($tmp_std->minute)){
								    						$tmp_array["hour"] = $tmp_std->hour;
								    						$tmp_array["minute"] = $tmp_std->minute;
								    					}
							    					}else{
							    						// old cronjob which is (wp_fastest_cache)
							    						$tmp_array = array("schedule" => $event["schedule"],
								    									   "prefix" => "all",
								    									   "content" => "all");
							    					}
							    				}

							    				array_push($schedules_rules, $tmp_array);
						    				}
						    			}
						    		}
						    	}

					    		echo "WpFcTimeout.schedules = ".json_encode($this->cron_add_minute(array())).";";

					    		if(count($schedules_rules) > 0){
					    			echo "WpFcTimeout.init(".json_encode($schedules_rules).");";
					    		}else{
					    			echo "WpFcTimeout.init();";
					    		} ?>
				    	</script>
				    </div>
				    <?php if(class_exists("WpFastestCacheImageOptimisation")){ ?>
					    <div class="tab4">
					    	<h2 style="padding-left:20px;padding-bottom:10px;">Optimize Image Tool</h2>

					    		<?php $xxx = new WpFastestCacheImageOptimisation(); ?>
					    		<?php $xxx->statics(); ?>
						    	<?php $xxx->imageList(); ?>
					    </div>
				    <?php }else{ ?>
						<div class="tab4" style="">
							<div style="z-index:9999;width: 160px; height: 60px; position: absolute; margin-left: 254px; margin-top: 74px; color: white;">
								<div style="font-family:sans-serif;font-size:13px;text-align: center; border-radius: 5px; float: left; background-color: rgb(51, 51, 51); color: white; width: 147px; padding: 20px 50px;">
									<label>Only available in Premium version</label>
								</div>
							</div>
							<h2 style="opacity: 0.3;padding-left:20px;padding-bottom:10px;">Optimize Image Tool</h2>
							<div id="container-show-hide-image-list" style="opacity: 0.3;float: right; padding-right: 20px; cursor: pointer;">
								<span id="show-image-list">Show Images</span>
								<span id="hide-image-list" style="display:none;">Hide Images</span>
							</div>
							<div style="opacity: 0.3;width:100%;float:left;" id="wpfc-image-static-panel">
								<div style="float: left; width: 100%;">
									<div style="float:left;padding-left: 22px;padding-right:15px;">
										<div style="display: inline-block;">
											<div style="width: 150px; height: 150px; position: relative; border-top-left-radius: 150px; border-top-right-radius: 150px; border-bottom-right-radius: 150px; border-bottom-left-radius: 150px; background-color: #ffcc00;">
												

												<div style="position: absolute; top: 0px; left: 0px; width: 150px; height: 150px; border-top-left-radius: 150px; border-top-right-radius: 150px; border-bottom-right-radius: 150px; border-bottom-left-radius: 150px; clip: rect(0px 150px 150px 75px);">
													<div style="position: absolute; top: 0px; left: 0px; width: 150px; height: 150px; border-radius: 150px; clip: rect(0px, 75px, 150px, 0px); transform: rotate(109.62deg); background-color: rgb(255, 165, 0); border-spacing: 109.62px;" id="wpfc-pie-chart-little"></div>
												</div>


												<div style="display:none;position: absolute; top: 0px; left: 0px; width: 150px; height: 150px; border-top-left-radius: 150px; border-top-right-radius: 150px; border-bottom-right-radius: 150px; border-bottom-left-radius: 150px; clip: rect(0px 150px 150px 25px); -webkit-transform: rotate(0deg); transform: rotate(0deg);" id="wpfc-pie-chart-big-container-first">
													<div style="position: absolute; top: 0px; left: 0px; width: 150px; height: 150px; border-top-left-radius: 150px; border-top-right-radius: 150px; border-bottom-right-radius: 150px; border-bottom-left-radius: 150px; clip: rect(0px 75px 150px 0px); -webkit-transform: rotate(180deg); transform: rotate(180deg); background-color: #FFA500;"></div>
												</div>
												<div style="display:none;position: absolute; top: 0px; left: 0px; width: 150px; height: 150px; border-top-left-radius: 150px; border-top-right-radius: 150px; border-bottom-right-radius: 150px; border-bottom-left-radius: 150px; clip: rect(0px 150px 150px 75px); -webkit-transform: rotate(180deg); transform: rotate(180deg);" id="wpfc-pie-chart-big-container-second-right">
													<div style="position: absolute; top: 0px; left: 0px; width: 150px; height: 150px; border-top-left-radius: 150px; border-top-right-radius: 150px; border-bottom-right-radius: 150px; border-bottom-left-radius: 150px; clip: rect(0px 75px 150px 0px); -webkit-transform: rotate(90deg); transform: rotate(90deg); background-color: #FFA500;" id="wpfc-pie-chart-big-container-second-left"></div>
												</div>

											</div>
											<div style="width: 114px;height: 114px;margin-top: -133px;background-color: white;margin-left: 18px;position: absolute;border-radius: 150px;">
												<p style="text-align:center;margin:27px 0 0 0;color: black;">Succeed</p>
												<p style="text-align: center; font-size: 18px; font-weight: bold; font-family: verdana; margin: -2px 0px 0px; color: black;" id="wpfc-optimized-statics-percent" class="">30.45</p>
												<p style="text-align:center;margin:0;color: black;">%</p>
											</div>
										</div>
									</div>
									<div style="float: left;padding-left:12px;" id="wpfc-statics-right">
										<ul style="list-style: none outside none;float: left;">
											<li>
												<div style="background-color: rgb(29, 107, 157);width:15px;height:15px;float:left;margin-top:4px;border-radius:5px;"></div>
												<div style="float:left;padding-left:6px;">All</div>
												<div style="font-size: 14px; font-weight: bold; color: black; float: left; width: 65%; margin-left: 5px;" id="wpfc-optimized-statics-total_image_number" class="">7196</div>
											</li>
											<li>
												<div style="background-color: rgb(29, 107, 157);width:15px;height:15px;float:left;margin-top:4px;border-radius:5px;"></div>
												<div style="float:left;padding-left:6px;">Pending</div>
												<div style="font-size: 14px; font-weight: bold; color: black; float: left; width: 65%; margin-left: 5px;" id="wpfc-optimized-statics-pending" class="">5002</div>
											</li>
											<li>
												<div style="background-color: #FF0000;width:15px;height:15px;float:left;margin-top:4px;border-radius:5px;"></div>
												<div style="float:left;padding-left:6px;">Errors</div>
												<div style="font-size: 14px; font-weight: bold; color: black; float: left; width: 65%; margin-left: 5px;" id="wpfc-optimized-statics-error" class="">3</div>
											</li>
										</ul>
										<ul style="list-style: none outside none;float: left;">
											<li>
												<div style="background-color: rgb(61, 207, 60);width:15px;height:15px;float:left;margin-top:4px;border-radius:5px;"></div>
												<div style="float:left;padding-left:6px;"><span>Optimized Images</span></div>
												<div style="font-size: 14px; font-weight: bold; color: black; float: left; width: 65%; margin-left: 5px;" id="wpfc-optimized-statics-optimized" class="">2191</div>
											</li>

											<li>
												<div style="background-color: rgb(61, 207, 60);width:15px;height:15px;float:left;margin-top:4px;border-radius:5px;"></div>
												<div style="float:left;padding-left:6px;"><span>Total Reduction</span></div>
												<div style="font-size: 14px; font-weight: bold; color: black; float: left; width: 80%; margin-left: 5px;" id="wpfc-optimized-statics-reduction" class="">78400.897</div>
											</li>
											<li></li>
										</ul>

										<ul style="list-style: none outside none;float: left;">
											<li>
												<h1 style="margin-top:0;float:left;">Credit: <span style="display: inline-block; height: 16px; width: auto;min-width:25px;" id="wpfc-optimized-statics-credit" class="">9910</span></h1>
												<span id="buy-image-credit">More</span>
											</li>
											<li>
												<input type="submit" class="button-primary" value="Optimize All" id="wpfc-optimize-images-button" style="width:100%;height:110px;">
											</li>
										</ul>
									</div>
								</div>
							</div>
						</div>
				    <?php } ?>
				    <div class="tab5">
				    	<?php
				    		if(!get_option("WpFc_api_key")){
				    			update_option("WpFc_api_key", md5(microtime(true)));
				    		}

				    		if(!defined('WPFC_API_KEY')){ // for download_error.php
				    			define("WPFC_API_KEY", get_option("WpFc_api_key"));
				    		}
				    	?>
				    	<div id="wpfc-premium-container">
				    		<div class="wpfc-premium-step">
				    			<div class="wpfc-premium-step-header">
				    				<label>Discover Features</label>
				    			</div>
				    			<div class="wpfc-premium-step-content">
				    				In the premium version there are some new features which speed up the sites more.
				    			</div>
				    			<div class="wpfc-premium-step-image">
				    				<img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAMAAAD04JH5AAAABGdBTUEAALGPC/xhBQAAAAFzUkdCAK7OHOkAAAL9UExURUxpcUNodz+MoUKFmT9jeElwfUGInDxmlUV6j0SCk0d3ii2huzmQpzmNpUFCQienwTSVrhy10xLC5A/G5zKYsSOsyArL7jVlki6dthS/3he62iCyzzCbtAbQ9CijvyWqxDyVqx2T7hOC8BiJ7iKvzCV/+ghTxh6K7Bm410eE4wXU+SCZ6yszNx147gLW/CR+501+zhJXwNPb3hJWvxlv6hpfyBWH8zt0zxJWwAhMtbbFzSMmJ0VZZUNJTLXCy7LByrG7xBlMqiKq/jA1OCCW+RZ29BqK8RWG8CZp07XM2CHJ/yPY/0KD51Zoc2x8hiUlJfb29kBOVyWJ/vf39/f39ytyrB+p7DY2Nj8/P/f29D61+4Or6UjN5S3Y9gsOD2Fud/Ly8ne9yhFk08Xb8/j4+ElearLEzydy5Pf29srV2FKFv0S+2Gu4yZi7x5m4xpqmsPf39/X19fPz8wEBAfX19fPz8y1hu3Ol4dng47K2urG0trKys7K5vbO9xLK7wAxTxgDZ/wxQwgMDBPz7+w1VyvHy8ubl5ipmySlnzerq6rfEzBFg2yFp2gpNvwhGtAsLDPX19fn5+ODh4Q1XzgpMuyVn0hBd1R9q3xdy9hdu7r+/wAlJuA9a0c3Nzi5mxdLT1O7u7gZCrri4ubu7vNjZ2s/Q0RIUFgU+pxJl4bPByRseH9TV1sTExbW1tiJn1hRp6Nzc3dbX10tLSydw4sLCw8fIyC177jmA6yl16LfAxTNnwRpg0DN65yxw3LrJ0jk5OTF24f///Rtt5ycnJwM5n8rKy0SM9FVWVjKB9T6F8DAwMChr1xp5/Bpj1jaI+2FhYmmp7WxsbSAqL4XR/HHE+xVayX+AgK6ur1qf9io3Pj5PWYeHhx2K/DRDTHZ2d7a6vTqY+jxwwpSfrHa171Ov+1d8rAk/jiVXlqSmqZmbnI6OjqO0xI+pwzKl+iRfw0ql/FCS4gx37ildrnaLq2Ke5N3o8nmbw0VqrJi++WWNxVvC/AAwhJm12oeTmqOvtEp03rQAAAD2dFJOUwARSTkLGkEEKDEgeldQ/ohhqcnTZpXfFXK/tqFt6oCPXAwgO5z7Mfmw/vT5SFH5rftcG395nub/+cG1oPxm7WMw/aXh0MBo28jTdEfN/f6DMqWT6af77C3LUffy7P7TwIT35/nP5Ijnu9PSwKPep7vXuc7Samril6b//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////j0Q6JoAABBTSURBVHja7Zt5VJNn2sZBoyBIREWhiiAoUKooKFZsdTpurXaq7Yx1vna6TduZcfaZb5n5DrKVLYWwJUHZFIMsQQgohEUCCoZdVlGWVnAB2UWtCjo66vnu+3nfN3kDtA0vFr4/elnb03M4/K7nvq9nJejp/agfNT2axZtWvP7br06nAYPV79W8PY38/3iVXyNbPX3Nf5uvKJF9bDBNeN6W7QrgR0xXB1a/x69SlKRHRKyeruZXAb8swu+/edMy9bYrGqsUfOD7vToNU2/Ldn4j8mtkYGD1NFS/pKpJzf+YNw1Tr4nipwM/4tUpn3r8xquEjxPAz2+KVyGYeoomml+G/IiP9aey+T/nKxpbGT4GIEI2hR2YtUXBr7qqxfeTpa+euqn3nkLR1M7iYwHSt09VB975RKGoah1k+DUkgBGyi1PUgXW/3henaBps1/CpAKRfnJoOvP9f4sCEzNF8CMDFKenAuncDRcHBosB+Np8E4OLXP5+C8P1jn38g8MWBggHgVwGfDqDs4tdXfvgOvPOfYlFCcILYX5SQIGnHDVjNhwJsn/WDhy+QDN8fMpAQHJzViuNXB+DrKz90B97/DQxcgMMnfIEguZXw6QBcubz6Bw6fiBk+xZdkZSW3pzMBAAObjbh9Zxudwvc/+1SiYGr4YIDiZ0ulg+l+dACuXObWAZttv3rz+y2883sYuACHr+ZLgB+fkdFfQwfg8uYtnAz8oqHh1oKffc8XzQ2mhy8WibT40ozEtioZzsArl3/LqQOvNzQ0VDfc2mb7XV+0pZUZ/mi+JNfDI3GQTwrwCRf+ikrgV1fnN/zyO/qwhd8IsYPhjxp/Xookz8PD4+C1tr2XL2/ew6UDtjsofv6Z/I4d39aH1Qo+P0UFeDY/WZqSFy+JR/5Bd/drz/bu2WPILQCEn19QkFPgsG3FeF/z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"/>
				    			</div>
				    			<div class="wpfc-premium-step-footer">
				    				<h1 id="new-features-h1">New Features</h1>
				    				<ul>
				    					<li><a target="_blank" style="text-decoration: none;color: #444;" href="http://www.wpfastestcache.com/premium/image-optimization/">Image Optimization</a></li>
				    					<li><a target="_blank" style="text-decoration: none;color: #444;" href="http://www.wpfastestcache.com/premium/mobile-cache/">Mobile Cache</a></li>
				    					<li><a target="_blank" style="text-decoration: none;color: #444;" href="http://www.wpfastestcache.com/premium/minify-html-plus/">Minify HTML Plus</a></li>
				    					<li><a target="_blank" style="text-decoration: none;color: #444;" href="http://www.wpfastestcache.com/premium/combine-js-plus/">Combine Js Plus</a></li>
				    					<li><a target="_blank" style="text-decoration: none;color: #444;" href="http://www.wpfastestcache.com/premium/minify-js/">Minify Js</a></li>
				    					<li><a target="_blank" style="text-decoration: none;color: #444;" href="http://www.wpfastestcache.com/premium/delete-cache-logs/">Delete Cache Logs</a></li>
				    					<li><a target="_blank" style="text-decoration: none;color: #444;" href="http://www.wpfastestcache.com/premium/cache-statics/">Cache Statics</a></li>
				    				</ul>
				    			</div>
				    		</div>
				    		<div class="wpfc-premium-step">
				    			<div class="wpfc-premium-step-header">
				    				<label>Checkout</label>
				    			</div>
				    			<div class="wpfc-premium-step-content">
				    				You need to pay before downloading the premium version.
				    			</div>
				    			<div class="wpfc-premium-step-image">
				    				<img width="140px" height="140px" src="data:image/png;base64,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"/>
				    			</div>
				    			<div class="wpfc-premium-step-footer">
				    				<?php
				    					$svn_price_arr = wp_remote_get("http://plugins.svn.wordpress.org/wp-fastest-cache/assets/price.html", 5);

				    					if ( !$svn_price_arr || is_wp_error( $svn_price_arr ) ) {
				    						$premium_price = $svn_price_arr->get_error_message();
				    					}else{
				    						if(wp_remote_retrieve_response_code($svn_price_arr) == 200){
				    							$premium_price = wp_remote_retrieve_body( $svn_price_arr );
					    					}else{
					    						$premium_price = "Error";
					    					}
				    					}

				    				?>
				    				<h1 style="float:left;" id="just-h1">Just</h1><h1>$<span id="wpfc-premium-price"><?php echo $premium_price; ?></span></h1>
				    				<p>The download button will be available after paid. You can buy the premium version now.</p>
				    				
				    				<?php if(!preg_match("/\.ir$/i", $_SERVER["HTTP_HOST"])){ ?>
					    				<?php if(class_exists("WpFastestCachePowerfulHtml")){ ?>
						    					<button id="wpfc-buy-premium-button" type="submit" class="wpfc-btn primaryDisableCta" style="width:200px;">
							    					<span>Purchased</span>
							    				</button>
						    				<?php }else{ ?>
							    				<form action="http://api.wpfastestcache.net/paypal/buypremium/" method="post">
							    					<input type="hidden" name="wpfclang" value="<?php echo $this->options->wpFastestCacheLanguage; ?>">
							    					<input type="hidden" name="bloglang" value="<?php echo get_bloginfo('language'); ?>">
							    					<input type="hidden" name="hostname" value="<?php echo str_replace(array("http://", "www."), "", $_SERVER["HTTP_HOST"]); ?>">
								    				<button id="wpfc-buy-premium-button" type="submit" class="wpfc-btn primaryCta" style="width:200px;">
								    					<span>Buy</span>
								    				</button>
							    				</form>
						    			<?php } ?>
					    			<?php } ?>


				    			</div>
				    		</div>
				    		<div class="wpfc-premium-step">
				    			<div class="wpfc-premium-step-header">
				    				<label>Download & Update</label>
				    			</div>
				    			<div class="wpfc-premium-step-content">
				    				You can download and update the premium when you want if you paid.
				    			</div>
				    			<div class="wpfc-premium-step-image" style="">
				    				<img src="data:image/png;base64,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"/>
				    			</div>
				    			<div class="wpfc-premium-step-footer">
				    				<h1 id="get-now-h1">Get It Now!</h1>
				    				<p>Please don't delete the free version. Premium version works with the free version.</p>


				    				<?php if(class_exists("WpFastestCachePowerfulHtml")){ ?>
					    				<button id="wpfc-update-premium-button" class="wpfc-btn primaryDisableCta" style="width:200px;">
					    					<span data-type="update">Update</span>
					    				</button>
					    				<script type="text/javascript">
					    					jQuery(document).ready(function(){

				    							// if(jQuery(".tab5").is(":visible")){
										    	// 	wpfc_premium_page();
									    		// }

									    		// jQuery("#wpfc-premium").change(function(e){
									    		// 	wpfc_premium_page();
									    		// });

									    		wpfc_premium_page();

									    		function wpfc_premium_page(){
										    		jQuery(document).ready(function(){
							    						if(typeof Wpfc_Premium == "undefined"){
							    							jQuery("#wpfc-update-premium-button").attr("class", "wpfc-btn primaryCta");

							    							jQuery("#wpfc-update-premium-button").click(function(){
							    								jQuery("#revert-loader-toolbar").show();
							    								
																jQuery.get('<?php echo plugins_url('wp-fastest-cache/templates'); ?>' + "/update_error.php?error_message=" + "You use old version of premium. " + "&apikey=" + '<?php echo get_option("WpFc_api_key"); ?>', function( data ) {
																	jQuery("body").append(data);
																	Wpfc_Dialog.dialog("wpfc-modal-updateerror");
																	jQuery("#revert-loader-toolbar").hide();
																});
							    							});
							    						}else{
							    							Wpfc_Premium.check_update("<?php echo $this->get_premium_version(); ?>", '<?php echo "http://api.wpfastestcache.net/premium/newdownload/".str_replace(array("http://", "www."), "", $_SERVER["HTTP_HOST"])."/".get_option("WpFc_api_key"); ?>', '<?php echo plugins_url('wp-fastest-cache/templates'); ?>');

							    							setTimeout(function(){
								    							if(jQuery("#wpfc-update-premium-button").attr("class") == "wpfc-btn primaryCta"){
								    								Wpfc_Dialog.dialog("wpfc-modal-updatenow");
								    							}
							    							}, 1000);
							    						}
										    		});
									    		}

					    					});
					    				</script>
					    				<script type="text/javascript">

					    				</script>
				    				<?php }else{ ?>
					    				<button class="wpfc-btn primaryCta" id="wpfc-download-premium-button" class="wpfc-btn primaryDisableCta" style="width:200px;">
					    					<span data-type="download">Download</span>
					    				</button>

					    				<?php include(WPFC_MAIN_PATH."templates/download.html"); ?> 

					    				<script type="text/javascript">
					    					jQuery("#wpfc-download-premium-button").click(function(){
					    						//jQuery("#revert-loader-toolbar").show();

					    						Wpfc_New_Dialog.dialog("wpfc-modal-downloaderror", {close: "default"});

					    						var wpfc_api_url = '<?php echo "http://api.wpfastestcache.net/premium/newdownload/".str_replace(array("http://", "www."), "", $_SERVER["HTTP_HOST"])."/".get_option("WpFc_api_key"); ?>';
					    						jQuery("div[id^='wpfc-modal-downloaderror'] a.wpfc-download-now").attr("href", wpfc_api_url);

					    						// jQuery("body").append(data);
					    						// jQuery("#wpfc-download-now").attr("href", wpfc_api_url);
					    						// Wpfc_Dialog.dialog("wpfc-modal-downloaderror");
					    						// jQuery("#revert-loader-toolbar").hide();
						    					
						    					// jQuery.get("<?php echo plugins_url('wp-fastest-cache/templates'); ?>/download.html", function( data ) {
						    					// });
					    					});
					    				</script>
				    				<?php } ?>
				    				<!--
				    				<button class="wpfc-btn primaryNegativeCta" style="width:200px;">
				    					<span>Update</span>
				    					<label>(v 1.0)</label>
				    				</button>
				    			-->
				    			</div>
				    		</div>
				    	</div>
				    </div>
				    <div class="tab6" style="padding-left:20px;">
				    	<!-- samples start: clones -->
				    	<div class="wpfc-exclude-rule-line" style="display:none;">
							<div class="wpfc-exclude-rule-line-left">
								<select name="wpfc-exclude-rule-prefix">
										<option selected="" value=""></option>
										<option value="homepage">Home Page</option>
										<option value="startwith">Start With</option>
										<option value="contain">Contain</option>
										<option value="exact">Exact</option>
								</select>
							</div>
							<div class="wpfc-exclude-rule-line-middle">
								<input type="text" name="wpfc-exclude-rule-content" style="width:390px;">
								<input type="text" name="wpfc-exclude-rule-type" style="width:90px;">
							</div>
						</div>
						<!-- item sample -->
	    				<div class="wpfc-exclude-item" tabindex="1" type="" prefix="" content="" style="position: relative;display:none;">
	    					<div class="app">
				    			<div class="wpfc-exclude-item-form-title">Title M</div>
				    			<span class="wpfc-exclude-item-details wpfc-exclude-item-url"></span>
	    					</div>
			    		</div>
		    			<!-- samples end -->

		    			<h2 style="padding-bottom:10px;float:left;">Exclude Pages</h2>

				    	<div style="float:left;margin-top:-37px;padding-left:608px;">
					    	<button data-type="page" type="button" class="wpfc-add-new-exclude-button wpfc-dialog-buttons" style="display: inline-block;padding: 4px 10px;">
					    		<span>Add New Rule</span>
					    	</button>
				    	</div>

				    	<div class="wpfc-exclude-page-list" style="display: block;width:98%;float:left;">

				    	</div>

				    	<div class="exclude_section_clear">
				    		<div></div>
				    	</div>




				    	<h2 style="padding-bottom:10px;float:left;">Exclude User-Agents</h2>

				    	<div style="float:left;margin-top:-37px;padding-left:608px;">
					    	<button data-type="useragent" type="button" class="wpfc-add-new-exclude-button wpfc-dialog-buttons" style="display: inline-block;padding: 4px 10px;">
					    		<span>Add New Rule</span>
					    	</button>
				    	</div>

				    	<div class="wpfc-exclude-useragent-list" style="display: block;width:98%;float:left;">

				    	</div>




				    	<div class="exclude_section_clear">
				    		<div></div>
				    	</div>




				    	<h2 style="padding-bottom:10px;float:left;">Exclude CSS</h2>

				    	<div style="float:left;margin-top:-37px;padding-left:608px;">
					    	<button data-type="css" type="button" class="wpfc-add-new-exclude-button wpfc-dialog-buttons" style="display: inline-block;padding: 4px 10px;">
					    		<span>Add New Rule</span>
					    	</button>
				    	</div>

				    	<div class="wpfc-exclude-css-list" style="display: block;width:98%;float:left;">

				    	</div>



				    	<div class="exclude_section_clear">
				    		<div></div>
				    	</div>



				    	<h2 style="padding-bottom:10px;float:left;">Exclude JS</h2>

				    	<div style="float:left;margin-top:-37px;padding-left:608px;">
					    	<button data-type="js" type="button" class="wpfc-add-new-exclude-button wpfc-dialog-buttons" style="display: inline-block;padding: 4px 10px;">
					    		<span>Add New Rule</span>
					    	</button>
				    	</div>

				    	<div class="wpfc-exclude-js-list" style="display: block;width:98%;float:left;">

				    	</div>

				    	<?php
				    		include(WPFC_MAIN_PATH."templates/exclude.php");
				    	?>

				    	<form method="post" name="wp_manager">
				    		<input type="hidden" value="exclude" name="wpFastestCachePage">
				    		<div class="wpfc-exclude-rule-container"></div>
				    		<!-- <div class="questionCon qsubmit">
								<div class="submit"><input type="submit" class="button-primary" value="Submit"></div>
							</div> -->
				    	</form>
				    	<script type="text/javascript">

					    	<?php 
					    		if($rules_json = get_option("WpFastestCacheExclude")){
					    			?>WpFcExcludePages.init(<?php echo $rules_json; ?>);<?php
					    		}else{
					    			?>WpFcExcludePages.init();<?php
					    		}
					    	?>
				    	</script>
				    </div>

				    <div class="tab7" style="padding-left:20px;">
				    	<h2 style="padding-bottom:10px;">CDN Settings</h2>
				    	<div>
				    		<div class="integration-page" style="display: block;width:98%;float:left;">

				    			<div wpfc-cdn-name="maxcdn" class="int-item int-item-left">
				    				<img src="data:image/png;base64,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"/>
				    				<div class="app">
				    					<div style="font-weight:bold;font-size:14px;">CDN by MaxCDN</div>
				    					<p>Experts in Content Delivery Network Services</p>
				    				</div>
				    				<div class="meta"></div>
				    			</div>


				    			<div wpfc-cdn-name="other" class="int-item">
				    				<img src="data:image/png;base64,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"/>
				    				<div class="app">
				    					<div style="font-weight:bold;font-size:14px;">Other CDN Providers</div>
				    					<p>You can use any cdn provider.</p>
				    				</div>
				    				<div class="meta"></div>
				    			</div>

				    			<div wpfc-cdn-name="photon" class="int-item">
				    				<img src="data:image/png;base64,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"/>
				    				<div class="app">
				    					<div style="font-weight:bold;font-size:14px;">CDN by Photon</div>
				    					<p>Wordpress Content Delivery Network Services</p>
				    				</div>
				    				<div class="meta"></div>
				    			</div>

				    		</div>
				    	</div>
				    	<script type="text/javascript">
				    		(function() {
					    		<?php
					    			$cdn_values = get_option("WpFastestCacheCDN");

					    			if($cdn_values){
					    				$std_obj = json_decode($cdn_values);
					    				$cdn_values_arr = array();

					    				if(is_array($std_obj)){
											$cdn_values_arr = $std_obj;
										}else{
											array_push($cdn_values_arr, $std_obj);
										}

					    				foreach ($cdn_values_arr as $cdn_key => $cdn_value) {
						    				if($cdn_value->id == "amazonaws" || $cdn_value->id == "keycdn" || $cdn_value->id == "cdn77"){
						    					$cdn_value->id = "other";
						    				}
						    				?>jQuery("div[wpfc-cdn-name='<?php echo $cdn_value->id;?>']").find("div.meta").addClass("isConnected");<?php
					    				}
					    			}
					    		?>
				    			jQuery("div.integration-page .int-item").click(function(e){
				    				jQuery("#revert-loader-toolbar").show();
				    				jQuery("div[id='wpfc-modal-maxcdn'], div[id='wpfc-modal-other'], div[id='wpfc-modal-photon']").remove();

					    			jQuery.ajax({
										type: 'GET', 
										url: ajaxurl,
										cache: false,
										data : {"action": "wpfc_cdn_options_ajax_request"},
										dataType : "json",
										success: function(data){
											if(data.id){
												if(data.id == "keycdn" || data.id == "cdn77" || data.id == "amazonaws"){
													data.id = "other";
												}
											}


											WpfcCDN.init({"id" : jQuery(e.currentTarget).attr("wpfc-cdn-name"),
							    				"template_main_url" : "<?php echo plugins_url('wp-fastest-cache/templates/cdn'); ?>",
							    				"values" : data
							    			});


											
											// if(data.id && jQuery(e.currentTarget).attr("wpfc-cdn-name") != data.id){
											// 	Wpfc_New_Dialog.dialog("wpfc-modal-onlyonecdn", {close: "default"});

											// 	Wpfc_New_Dialog.show_button("close");
												
											// 	jQuery("#revert-loader-toolbar").hide();
											// }else{
							    // 				WpfcCDN.init({"id" : jQuery(e.currentTarget).attr("wpfc-cdn-name"),
							    // 					"template_main_url" : "<?php echo plugins_url('wp-fastest-cache/templates/cdn'); ?>",
							    // 					"values" : data
							    // 				});
											// }
										}
									});
				    			});
				    		})();
				    	</script>
				    </div>

				    <?php include_once(WPFC_MAIN_PATH."templates/permission_error.html"); ?>












			</div>

			<div class="omni_admin_sidebar">
<!-- 				<div style="padding:0 !important;float:left;">
					<a href="//partners.hostgator.com/c/149801/178138/3094" target="_blank">
						<img src="<?php echo plugins_url("wp-fastest-cache/images/ads/hostgator-250x250.gif"); ?>" border="0" alt="" width="222" height="220"/>
					</a>
				</div> -->
				<div class="omni_admin_sidebar_section" id="vote-us">
					<h3 style="color: antiquewhite;">Rate Us</h3>
					<ul>
						<li><label>If you like it, Please vote and support us.</label></li>
					</ul>
					<script>
						jQuery("#vote-us").click(function(){
							var win=window.open("http://wordpress.org/support/view/plugin-reviews/wp-fastest-cache?free-counter?rate=5#postform", '_blank');
							win.focus();
						});
					</script>
				</div>
				<div class="omni_admin_sidebar_section">
					<?php if(class_exists("WpFastestCachePowerfulHtml")){ ?>
						<h3>Premium Support</h3>
						<ul>
							<li><label>You can send an email</label> <a target="_blank"><label>fastestcache@gmail.com</label></a></li>
						</ul>
					<?php }else{ ?>
						<h3>Having Issues?</h3>
						<ul>
							<li><label>You can create a ticket</label> <a target="_blank" href="http://wordpress.org/support/plugin/wp-fastest-cache"><label>WordPress support forum</label></a></li>
							<?php
							if(isset($this->options->wpFastestCacheLanguage) && $this->options->wpFastestCacheLanguage == "tr"){
								?>
								<li><label>R10 Üzerinden Sorabilirsiniz</label> <a target="_blank" href="http://www.r10.net/wordpress/1096868-wp-fastest-cache-wp-en-hizli-ve-en-basit-cache-sistemi.html"><label>R10.net destek başlığı</label></a></li>
								<?php
							}
							?>
						</ul>
					<?php } ?>
				</div>
			</div>

			<div id="wpfc-plugin-setup-warning" class="mainContent" style="display:none;border:1px solid black">
			        <div class="pageView"style="display: block;">
			            <div class="fakeHeader">
			                <h3 class="title-h3">Error Occured</h3>
			            </div>
			            <div class="fieldRow active">

			            </div>
			            <div class="pagination">
			                <div class="next" style="text-align: center;float: none;">
			                    <button class="wpfc-btn primaryCta" id="wpfc-read-tutorial">
			                        <span class="label">Continue</span>
			                    </button>
			                </div>
			            </div>
			        </div>
			</div>
			<script type="text/javascript">
				var WPFC_SPINNER = {
					id: false,
					number: false,
					init: function(id, number){
						this.id = id;
						//this.number = number;
						this.set_number();
						this.click_event();
					},
					set_number: function(){
						this.number = jQuery("#" + this.id + " input.wpfc-form-spinner-input").val();
						this.number = parseInt(this.number);
					},
					click_event: function(){
						var id = this.id;
						var number = this.number;

						jQuery("#" + this.id + " .wpfc-form-spinner-up, #" + this.id + " .wpfc-form-spinner-down").click(function(e){
							if(jQuery(this).attr('class').match(/up$/)){
								number = number + 2;
							}else if(jQuery(this).attr('class').match(/down$/)){
								number = number - 2;
							}

							number = number < 2 ? 2 : number;
							number = number > 12 ? 12 : number;

							jQuery("#" + id + " .wpfc-form-spinner-number").text(number);
							jQuery("#" + id + " input.wpfc-form-spinner-input").val(number);
						});
					}
				};
			</script>
			<script type="text/javascript">
				jQuery("#wpFastestCachePreload").click(function(){
					if(typeof jQuery(this).attr("checked") != "undefined"){
						if(jQuery("div[id^='wpfc-modal-preload-']").length === 0){
							Wpfc_New_Dialog.dialog("wpfc-modal-preload", {close: function(){
								Wpfc_New_Dialog.clone.find("div.window-content input").each(function(){
									if(typeof jQuery(this).attr("checked") != "undefined"){
										jQuery("div.tab1 div[template-id='wpfc-modal-preload'] div.window-content input[name='" + jQuery(this).attr("name") + "']").attr("checked", true);
									}else{
										jQuery("div.tab1 div[template-id='wpfc-modal-preload'] div.window-content input[name='" + jQuery(this).attr("name") + "']").attr("checked", false);
									}

									Wpfc_New_Dialog.clone.remove();
								});
							}});

							Wpfc_New_Dialog.show_button("close");
							WPFC_SPINNER.init("wpfc-form-spinner-preload", 6);
						}
					}
				});
			</script>

			<?php if(!class_exists("WpFastestCacheImageOptimisation")){ ?>
				<div id="wpfc-premium-tooltip" style="display:none;width: 160px; height: 60px; position: absolute; margin-left: 354px; margin-top: 112px; color: white;">
					<div style="float:left;width:13px;">
						<div style="width: 0px; height: 0px; border-top: 6px solid transparent; border-right: 6px solid #333333; border-bottom: 6px solid transparent; float: right; margin-right: 0px; margin-top: 25px;"></div>
					</div>
					<div style="font-family:sans-serif;font-size:13px;text-align: center; border-radius: 5px; float: left; background-color: rgb(51, 51, 51); color: white; width: 147px; padding: 10px 0px;">
						<label>Only available in Premium version</label>
					</div>
				</div>

				<script type="text/javascript">
					jQuery("div.questionCon.disabled").click(function(e){
						if(typeof window.wpfc.tooltip != "undefined"){
							clearTimeout(window.wpfc.tooltip);
						}

						var inputCon = jQuery(e.currentTarget).find(".inputCon");
						var left = 30;

						jQuery(e.currentTarget).children().each(function(i, child){
							left = left + jQuery(child).width();
						});

						jQuery("#wpfc-premium-tooltip").css({"margin-left" : left + "px", "margin-top" : (jQuery(e.currentTarget).offset().top - jQuery(".tab1").offset().top + 25) + "px"});
						jQuery("#wpfc-premium-tooltip").fadeIn( "slow", function() {
							window.wpfc.tooltip = setTimeout(function(){ jQuery("#wpfc-premium-tooltip").hide(); }, 1000);
						});
						return false;
					});
				</script>
			<?php }else{ ?>
				<script type="text/javascript">
					jQuery(".update-needed").click(function(){
						if(jQuery("div[id^='wpfc-modal-updatenow-']").length === 0){
							Wpfc_New_Dialog.dialog("wpfc-modal-updatenow", {close: function(){
								Wpfc_New_Dialog.clone.find("div.window-content input").each(function(){
									if(jQuery(this).attr("checked")){
										var id = jQuery(this).attr("action-id");
										jQuery("div.tab1 div[template-id='wpfc-modal-updatenow'] div.window-content input#" + id).attr("checked", true);
									}
								});

								Wpfc_New_Dialog.clone.remove();
							}});

							Wpfc_New_Dialog.show_button("close");
						}

						return false;
					});
				</script>
			<?php } ?>
			<script>
				jQuery(document).ready(function() {
					Wpfclang.init("<?php echo $wpFastestCacheLanguage; ?>");
				});
			</script>
			<?php
			if(isset($_SERVER["SERVER_SOFTWARE"]) && $_SERVER["SERVER_SOFTWARE"] && !preg_match("/iis/i", $_SERVER["SERVER_SOFTWARE"]) && !preg_match("/nginx/i", $_SERVER["SERVER_SOFTWARE"])){
				$this->check_htaccess();
			}
		}
	}
?>