<?php
	class WpFastestCacheRenderBlocking{
		private $html = "";
		private $except = "";
		private $tags = array();
		private $header_start_index = 0;
		private $js_tags_text = "";

		public function __construct($html){
			$this->html = $html;
			$this->set_header_start_index();
			$this->set_tags();
			$this->tags_reorder();
		}

		public function set_header_start_index(){
			$head_tag = $this->find_tags("<head", ">");
			$this->header_start_index = isset($head_tag[0]) && isset($head_tag[0]["start"]) && $head_tag[0]["start"] ? $head_tag[0]["start"] : 0;
		}

		public function tags_reorder(){
			// <script>jQuery('head').append('<style>' + arr_splits[i] + '</style>');</script>
			// <script>document.getElementById("id").innerHTML='<div> <span> <!--[if !IE]>--> xxx <!--<![endif]--> </span></div>';</script>
			$list = array();

			for ($i=0; $i < count($this->tags); $i++) {
				for ($j=0; $j < count($this->tags); $j++) { 
					if($this->tags[$i]["start"] > $this->tags[$j]["start"]){
						if($this->tags[$i]["end"] < $this->tags[$j]["end"]){
							array_push($list, $i);
						}
					}
				}
			}

			foreach ($list as $key => $value) {
				unset($this->tags[$value]);
			}




		    $sorter = array();
		    $ret = array();

		    foreach ($this->tags as $ii => $va) {
		        $sorter[$ii] = $va['start'];
		    }

		    asort($sorter);

		    foreach ($sorter as $ii => $va) {
		        $ret[$ii] = $this->tags[$ii];
		    }

		    $this->tags = $ret;
		}

		public function set_except($tags){
			foreach ($tags as $key => $value) {
				$this->except = $value["text"].$this->except;
			}
		}

		public function set_tags(){
			$this->set_comments();
			$this->set_js();
			$this->set_css();
		}

		public function set_css(){
			$style_tags = $this->find_tags("<style", "</style>");
			$this->tags = array_merge($this->tags, $style_tags);
			
			$link_tags = $this->find_tags("<link", ">");

			foreach ($link_tags as $key => $value) {
				if(preg_match("/href\s*\=/i", $value["text"])){
					if(preg_match("/rel\s*\=\s*[\'\"]\s*stylesheet\s*[\'\"]/i", $value["text"])){
						array_push($this->tags, $value);
					}
				}
			}
		}

		public function set_js(){
			$script_tag = $this->find_tags("<script", "</script>");

			foreach ($script_tag as $key => $value) {
				if(preg_match("/google_ad_client/", $value["text"])){
					continue;
				}

				if(preg_match("/googlesyndication\.com/", $value["text"])){
					continue;
				}

				if(preg_match("/srv\.sayyac\.net/", $value["text"])){
					continue;
				}

				if(preg_match("/app\.getresponse\.com/i", $value["text"])){
					continue;
				}

				if(preg_match("/adsbygoogle/i", $value["text"])){
					continue;
				}

				$this->js_tags_text = $this->js_tags_text.$value["text"];

				array_push($this->tags, $value);
			}
		}

		public function set_comments(){
			$comment_tags = $this->find_tags("<!--", "-->");

			$this->set_except($comment_tags);

			foreach ($comment_tags as $key => $value) {
				if(preg_match("/\<\!--\s*\[if/i", $value["text"])){
					array_push($this->tags, $value);
				}
			}
		}

		public function find_tags($start_string, $end_string){
			$data = $this->html;

			$list = array();
			$start_index = false;
			$end_index = false;

			for($i = 0; $i < strlen( $data ); $i++) {
			    if(substr($data, $i, strlen($start_string)) == $start_string){
			    	if(!$start_index && !$end_index){
			    		$start_index = $i;
			    	}
				}

				if($start_index && $i > $start_index){
					if(substr($data, $i, strlen($end_string)) == $end_string){
						$end_index = $i + strlen($end_string)-1;
						$text = substr($data, $start_index, ($end_index-$start_index + 1));
						
						if($start_index > $this->header_start_index){
							if($this->except){
								if(strpos($this->except, $text) === false){
									array_push($list, array("start" => $start_index, "end" => $end_index, "text" => $text));
								}
							}else{
								array_push($list, array("start" => $start_index, "end" => $end_index, "text" => $text));
							}
						}

						$start_index = false;
						$end_index = false;
					}
				}
			}

			return $list;
		}

		public function action(){

			$loading_html = "<div id='wpfc-rb-loading' style='background-color:#fefefe;bottom:0;height:100%;left:0;overflow:hidden !important;position:fixed;right:0;top:0;width:100%;z-index:99999;'>".
							file_get_contents(WPFC_WP_PLUGIN_DIR."/wp-fastest-cache-premium/pro/templates/render-blocking-loading/6.html").
							"</div>";

			$loading_html = preg_replace("/\s+/", " ", $loading_html);
			$loading_html = preg_replace("/\h*([\#\;|\:\{\}])\h*/", "$1", $loading_html);

			$remove_loading_html = '<script>setTimeout(function(){document.getElementById("wpfc-rb-loading").style.visibility="hidden";},1000);</script>';

			foreach (array_reverse($this->tags) as $key => $value) {
				$this->html = substr_replace($this->html, "", $value["start"], ($value["end"] - $value["start"] + 1));
			}

			foreach ($this->tags as $key => $value) {
				$this->html = str_replace("</body>", $value["text"]."\n"."</body>", $this->html);
			}
			
			$this->html = preg_replace("/(<body[^\>]*>)/i", "$1\n".$loading_html, $this->html);
			$this->html = str_replace("</body>", $remove_loading_html."\n"."</body>", $this->html);

			return preg_replace("/^\s+/m", "", $this->html);
		}
	}
?>