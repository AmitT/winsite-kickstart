<?php
	class WpFastestCachePowerfulHtml{
		private $html = "";
		private $head_html = "";
		private $body_html = "";
		private $inline_scripts = "";

		public function __construct(){}

		public function set_html($html){			
			$this->html = $html;
			$this->set_head_html();
			$this->set_body_html();
		}

		public function set_body_html(){
			preg_match("/<body(.+)<\/body>/si", $this->html, $out);
			$this->body_html = $out[0];
		}

		public function set_head_html(){
			preg_match("/<head(.+)<\/head>/si", $this->html, $out);
			$this->head_html = $out[0];
		}

		public function remove_head_comments(){
			$data = $this->head_html;
			$comment_list = array();
			$comment_start_index = false;

			for($i = 0; $i < strlen( $data ); $i++) {
				if(isset($data[$i-3])){
				    if($data[$i-3].$data[$i-2].$data[$i-1].$data[$i] == "<!--"){
						if(!preg_match("/if\s+|endif\s*\]/", substr($data, $i, 17))){
							$comment_start_index = $i-3;
						}
					}
				}

				if(isset($data[$i-2])){
					if($comment_start_index){
						if($data[$i-2].$data[$i-1].$data[$i] == "-->"){
							array_push($comment_list, array("start" => $comment_start_index, "end" => $i));
							$comment_start_index = false;
						}
					}
				}
			}

			if(!empty($comment_list)){
				foreach (array_reverse($comment_list) as $key => $value) {
					$data = substr_replace($data, '', $value["start"], ($value["end"] - $value["start"] + 1));
				}

				$this->html = str_replace($this->head_html, $data, $this->html);
			}
			

			// $ini = 0;

			// if(function_exists("ini_set") && function_exists("ini_get")){
			// 	$ini = ini_get("pcre.recursion_limit");
			// 	ini_set("pcre.recursion_limit", "2777");
			// }

			// if($new_head = preg_replace("/<!--((?:(?!-->|endif).)+)-->/si", '', $this->head_html)){
			// 	$this->html = str_replace($this->head_html, $new_head, $this->html);
			// }

			// if($ini){
			// 	ini_set("pcre.recursion_limit", $ini);
			// }

			return $this->html;
		}

		public function minify_html(){
			if(function_exists("ini_set") && function_exists("ini_get")){
				$ini = ini_get("pcre.recursion_limit");
				ini_set("pcre.recursion_limit", "2777");
			}

			//$new_body = $this->remove_single_line_comments($this->body_html);

			$new_body = $this->body_html;

			// $new_body = preg_replace("/<script([^\>\<]*)>\s*/i", "<script$1>", $new_body);
			// $new_body = preg_replace("/\s*<\/script>/i", "</script>", $new_body);

			$this->html = str_replace($this->body_html, $new_body, $this->html);

			$inc = 5000;
			$tmp_html = "";
			$html_part = "";
			for ($i=0; $i < strlen($this->html); $i = $i + $inc) { 
				$html_part = substr($this->html, $i, $inc);
				
				if($html_part_opt = preg_replace_callback("/<div[^\>]*>((?:(?!div|script|style).)+)<\/div>/is", array($this, 'eliminate_newline'), $html_part)){
					$html_part = $html_part_opt;
				}

				$tmp_html = $tmp_html.$html_part;

				// $tmpi = @strpos($this->html, '<div', $i+$inc);

				// if($tmpi){
				// 	$tmp_html .= substr($this->html, ($i+$inc), ($tmpi-$inc));

				// 	if(($i+$tmpi) < strlen($this->html)){
				// 		$i = $tmpi - $inc;
				// 	}else{
				// 		break;
				// 	}
				// }
			}



			$tmp_html = $this->minify_inline_js($tmp_html);
			$tmp_html = $this->minify_inline_css($tmp_html);

			$tmp_html = $this->remove_html_comments($tmp_html);

			$tag_list = "p|div|span|img|nav|ul|li|header|a|b|i|article|section|footer|style|script|link|meta|body";

			$tmp_html = preg_replace_callback("/\<(".$tag_list.")\s+[^\>\<]+\>/i", array($this, "remove_spaces_in_tag"), $tmp_html);
			
			// BECAUSE of JsemÂ<span class="label">
			// - need to remove spaces between >  <
			// - need to remove spaces between <span>  Assdfdf </span>
			// $tmp_html = preg_replace("/\h*\<(".$tag_list.")\s+([^\>]+)>\h*/i", "<$1 $2>", $tmp_html);
			// $tmp_html = preg_replace("/\h*\<\/(".$tag_list.")>\h*/i", "</$1>", $tmp_html);
			$tmp_html = preg_replace("/\s*<\/div>\s*/is", "</div>", $tmp_html);

			$this->html = $tmp_html;

			if($ini){
				ini_set("pcre.recursion_limit", $ini);
			}

			return $this->html;
		}

		public function remove_spaces_in_tag($matches){
			if(preg_match("/".preg_quote($matches[0], "/")."/i", $this->inline_scripts)){
				return $matches[0];
			}

			//  <img id="1"  />
			$matches[0] = preg_replace("/([\"\'])\s+\/>/", "$1/>", $matches[0]);

			// <div      id="1">
			$matches[0] = preg_replace("/\s+/", " ", $matches[0]);

			// <div id="1  ">
			$matches[0] = preg_replace("/\s+([\"\'])/", '$1', $matches[0]);

			// <div id="  1">
			$matches[0] = preg_replace("/\=([\"\'])\s+/", '=$1', $matches[0]);

			// <ul class="">
			$matches[0] = preg_replace("/\h*class\=[\"\'][\"\']\h*/", " ", $matches[0]);

			// <div style="">
			$matches[0] = preg_replace("/\h*style\=[\"\'][\"\']\h*/", " ", $matches[0]);

			// <div id="1"  >
			// <div  >
			$matches[0] = preg_replace("/\h+\>/", ">", $matches[0]);

			return $matches[0];
		}

		public function eliminate_newline($matches){
			return preg_replace("/\s+/", " ", ((string) $matches[0]));
		}

		public function remove_single_line_comments($html){
			$html = preg_replace("/<!--((?:(?!-->).)+)-->/", '', $html);
			$html = preg_replace("/\/\*((?:(?!\*\/).)+)\*\//", '', $html);
			return $html;
		}

		public function remove_html_comments($data){
			$comment_list = array();
			$comment_start_index = false;

			for($i = 0; $i < strlen( $data ); $i++) {
				if(isset($data[$i-3])){
				    if($data[$i-3].$data[$i-2].$data[$i-1].$data[$i] == "<!--"){
						if(!preg_match("/if\s+|endif\s*\]/", substr($data, $i, 17))){
							$comment_start_index = $i-3;
						}
					}
				}

				if(isset($data[$i-2])){
					if($comment_start_index){
						// if(substr($data, ($i-9 + 1), 9) == "</script>"){
						// 	$comment_start_index = false;
						// }
						
						if($data[$i-2].$data[$i-1].$data[$i] == "-->"){
							array_push($comment_list, array("start" => $comment_start_index, "end" => $i));
							$comment_start_index = false;
						}
					}
				}
			}

			if(!empty($comment_list)){
				foreach (array_reverse($comment_list) as $key => $value) {
					if(($value["end"] - $value["start"]) > 4){
						$comment_html = substr($data, $value["start"], ($value["end"] - $value["start"] + 1));

						if(preg_match("/google\_ad\_slot/i", $comment_html)){
						}else{
							$data = substr_replace($data, '', $value["start"], ($value["end"] - $value["start"] + 1));
						}
					}
				}
			}

			return $data;
		}
		/* CSS Part Start */
		public function minify_css($source){
			$data = $source;
			$curl_list = array();
			$curl_start_index = false;

			$curl_start_count = 0;
			$curl_end_count = 0;

			for($i = 0; $i < strlen( $data ); $i++) {
				if($data[$i] == "{"){
					$curl_start_count++;
					if(!$curl_start_index){
						$curl_start_index = $i;
					}
				}

				if($data[$i] == "}"){
					// .icon-basic-printer:before{content:"}";}
					if($data[$i+1] != "'" && $data[$i+1] != '"'){
						$curl_end_count++;
					}
				}

				if($curl_start_count && $curl_start_count == $curl_end_count){
					array_push($curl_list, array("start" => $curl_start_index-3, "end" => $i+3));

					$curl_start_count = 0;
					$curl_end_count = 0;
					$curl_start_index = false;
				}
			}

			if(!empty($curl_list)){
				foreach (array_reverse($curl_list) as $key => $value) {
					$new_data = substr($data, $value["start"], ($value["end"] - $value["start"] + 1));

					if(!preg_match("/[^\{]+\{[^\{]+\{/", $new_data)){
						$new_data = preg_replace("/\s+/", " ", ((string) $new_data));
						$new_data = preg_replace("/\s*{\s*/", "{", $new_data);
						$new_data = preg_replace("/\s*}\s*/", "}\n", $new_data);
						$new_data = preg_replace("/\s*\;\s*/", ";", $new_data);
						$new_data = preg_replace("/\s*\:\s*/", ":", $new_data);

						$data = substr_replace($data, $new_data, $value["start"], ($value["end"] - $value["start"] + 1));

					}else{
						$first = strpos($new_data, "{");
						$last = strrpos($new_data, "}");
						$new_data_tmp = substr($new_data, $first+1, $last-$first-1);
						$new_data_tmp = $this->minify_css($new_data_tmp);

						$new_data = substr_replace($new_data, $new_data_tmp, $first+1, ($last-$first-1));

						$data = substr_replace($data, $new_data, $value["start"], ($value["end"] - $value["start"] + 1));
					}
				}

				$source = $data;
			}

			return $source;

			//$source = preg_replace_callback("/\s*\{((?:(?!content|\}).)+)\}\s*/", array($this, 'eliminate_newline_for_css'), $source);
			//return $source;
		}
		public function eliminate_newline_for_css($matches){
			$matches[0] = preg_replace("/\s+/", " ", ((string) $matches[0]));
			$matches[0] = preg_replace("/\s*{\s*/", "{", $matches[0]);
			$matches[0] = preg_replace("/\s*}\s*/", "}", $matches[0]);
			$matches[0] = preg_replace("/\s*\;\s*/", ";", $matches[0]);
			$matches[0] = preg_replace("/\s*\:\s*/", ":", $matches[0]);

			return $matches[0]."\n";
		}
		public function minify_inline_css($data){
			$style_list = array();
			$style_start_index = false;

			for($i = 0; $i < strlen( $data ); $i++) {
				if(isset($data[$i-5])){
				    if(substr($data, $i-5, 6) == "<style"){
				    	$style_start_index = $i-5;
					}
				}

				if(isset($data[$i-7])){
					if($style_start_index){
						if(substr($data, $i-7, 8) == "</style>"){
							array_push($style_list, array("start" => $style_start_index, "end" => $i));
							$style_start_index = false;
						}
					}
				}
			}

			if(!empty($style_list)){
				foreach (array_reverse($style_list) as $key => $value) {
					// document.write('<style type="text/css">div{}</style')
					$prev_20_chars = substr($data, $value["start"]-20, 20);
					if(strpos($prev_20_chars, "document.write") !== false){
						continue;
					}

					$inline_style = substr($data, $value["start"], ($value["end"] - $value["start"] + 1));
					
					if(strlen($inline_style) > 15000){
						$part_of_inline_style = substr($inline_style, 0, 15000);
					}else{
						$part_of_inline_style = $inline_style;
					}

					if(preg_match("/".preg_quote($part_of_inline_style, "/")."/i", $this->inline_scripts)){
						continue;
					}

					$inline_style = $this->minify_css($inline_style);


					$inline_style = preg_replace("/\/\*(.*?)\*\//s", "\n", $inline_style);

					$inline_style = preg_replace("/(<style[^\>]*>)\s+/i", "$1", $inline_style);
					$inline_style = preg_replace("/\s+(<\/style[^\>]*>)/i", "$1", $inline_style);


					$inline_style = str_replace(' type="text/css"', "", $inline_style);
					$inline_style = str_replace(" type='text/css'", "", $inline_style);



					$data = substr_replace($data, $inline_style, $value["start"], ($value["end"] - $value["start"] + 1));

				}
			}

			return $data;
		}

		public function render_blocking($html){
			include_once WPFC_WP_PLUGIN_DIR."/wp-fastest-cache-premium/pro/library/render-blocking.php";
			$render = new WpFastestCacheRenderBlocking($html);
			return $render->action();
		}

		/* CSS Part Start */

		/* Js Part Start */
		public function minify_js($source, $inline_js = false){
			//$source = preg_replace("/\n\/\/.*/", "", $source);
			//$source = preg_replace("/\/\*.*?\*\//s", "", $source);

			if(preg_match("/dynamicgoogletags\.update\(\)/i", $source)){
				$source = "<script>dynamicgoogletags.update();</script>";
				
				return $source;
			}

			if(preg_match("/GoogleAnalyticsObject|\_gaq\.push\(\[\'\_setAccount/i", $source)){
				$source = preg_replace("/\s+/", " ", ((string) $source));
				$source = preg_replace("/\s*<(\/?)script([^\>]*)>\s*/", "<$1script$2>", $source);

				return $source;
			}

			// sometimes the lines are ended with "\r" instead of "\n"
			$source = str_replace("\r", "\n", $source);

			$source = preg_replace("/^\s+/m", "", $source);

			if(!$inline_js){
				// // --></script> in html
				//$source = preg_replace("/\n\/\/[^\n]+/", "", $source); // to remove single line comments
				$source = preg_replace_callback("/\n\/\/[^\n]+/", array($this, 'remove_single_line_comments_from_js'), $source);

			}

			$source = preg_replace_callback("/([a-z]{4,5}\:)?\/\/[^\n]*/", array($this, 'remove_single_line_comments_from_js'), $source);

			$source = preg_replace("/\}\)\;[^\S\r\n]+/", "});", $source);

			$source = preg_replace("/^\s+/m", "", $source);


			$source = preg_replace("/\s*(\!|\=)(\={1,3})\s*/", "$1$2", $source);

			// to remove spaces at the end of the line
			$source = preg_replace("/(\D)[^\S\r\n]+\n/", "$1\n", $source);

			// We cannot use \s because of excluding new line
			$source = preg_replace("/[^\S\r\n]*\:[^\S\r\n]*/", ":", $source);
			$source = preg_replace("/([^\s\|])[^\S\r\n]*\&\&[^\S\r\n]*([^\s\|])/", "$1&&$2", $source);
			$source = preg_replace("/([^\s\&])[^\S\r\n]*\|\|[^\S\r\n]*([^\s\&])/", "$1||$2", $source);
			// @media all and (width), maybe later we  can do preg_replace_callback()
			//b.match(/^(<div><br( ?\/), no need to remove the spage between ( and ?
			//dashArray.replace(/( *, *)/g, no need to remove the spage between ( and *
			$source = preg_replace("/[^\S\r\n]*\([^\S\r\n]+([^\?\*])/", "($1", $source);
			$source = preg_replace("/and\(/", "and (", $source);
			//------
			$source = preg_replace("/([^\s\=\!])[^\S\r\n]*\=[^\S\r\n]*([^\s\=\!])/", "$1=$2", $source);

			$source = preg_replace("/\)\s+\{/", "){", $source);
			// $source = preg_replace("/;\s*}\s*/s", ";}", $source);
			$source = preg_replace("/\}\s+}/s", "}}", $source);
			$source = preg_replace("/\};\s+}/s", "};}", $source);
			$source = preg_replace("/\}\s*else\s*\{/", "}else{", $source);
			$source = preg_replace("/\}[^\S\r\n]*else[^\S\r\n]*if[^\S\r\n]*\(/", "}else if(", $source);
			$source = preg_replace("/if\s*\(\s*/", "if(", $source);
			//$source = preg_replace("/\(\s+/", "(", $source); // causes an issue
			$source = preg_replace("/[^\S\r\n]+\)/", ")", $source);

			$source = preg_replace("/<script([^\>\<]*)>\s*/i", "<script$1>", $source);
			$source = preg_replace("/\s*<\/script>/i", "</script>", $source);

			// .name( something)
			$source = preg_replace("/(\.[A-Za-z\_]+\()\s{1,2}/", "$1", $source);

			// Muli-Line Comments Start
			$source = $this->fix_js_single_comment_mistakes($source);
			$source = preg_replace_callback("/\n\/\*([^\*]+)\*\/\n/s", array($this, 'remove_multi_line_comments_from_js'), $source);
			$source = preg_replace_callback("/\/\*(.*?)\*\//s", array($this, 'remove_multi_line_comments_from_js'), $source);
			// END

			$source = str_replace("\xEF\xBB\xBF", '', $source);

			$source = preg_replace("/^\s+/m", "", $source);

			return $source;
		}

		public function fix_js_single_comment_mistakes($source){
			/*-------------------------------------------------------------------------*/
			/*	1.	Plugin Init --> no end 
			/*-------------------------------------------------------------------------*/

			/*========================= --> no end 
			/*=========================
			WP8 Fix
			===========================*/

			if(!preg_match("/\/\*[^\*]{1,100}\/\*\s*\-+\s*\*\//", $source) && !preg_match("/\/\*\s*\=+\s+\/\*/", $source)){
				return $source;
			}
		
			$data = "";

			$start_index = false;

			for($i = 0; $i < strlen($source); $i++) {
				if(substr($source, $i, 2) == "/*"){
					if($start_index){
						$data = $data."*/";
					}else{
						$start_index = true;
					}
				}

				if(substr($source, $i, 2) == "*/"){
					$start_index = false;
				}

				$data = $data.$source[$i];
			}

			return $data;

		}

		public function remove_multi_line_comments_from_js($matches){
			if(preg_match("/^\n/", $matches[0]) && preg_match("/\n$/", $matches[0])){
				if(isset($matches[1]) && $matches[1]){
					if(!preg_match("/\*/", $matches[1])){
						return "\n";
					}
				}
			}
			
			if(preg_match("/\/\*\@cc_on/i", $matches[0])){
				return $matches[0];
			}

			if(preg_match("/\.exec\(|\.test\(|\.match\(|\.search\(|\.replace\(|\.split/", $matches[0])){
				return $matches[0];
			}

			if(preg_match("/function\(/", $matches[0])){
				return $matches[0];
			}

			//c("unmatched `/*`");
			if(preg_match("/^\/\*\`\"\)\;/", $matches[0])){
				return $matches[0];
			}

			return "";
		}

		public function remove_single_line_comments_from_js($matches){
			if(preg_match("/\n\/\/[^\n]+/", $matches[0])){
				// // */
				if(preg_match("/\/\/\s*\*\//", $matches[0])){
					return $matches[0];
				}

				return "";
			}

			// // */
			if(preg_match("/\/\/\s*\*\//", $matches[0])){
				return $matches[0];
			}

			// var url = {"name" : "something",
			// 		   "url"  : '//$1/p/$2/media/?size=l'
			// 		  };
			if(preg_match("/\'\h*$/", $matches[0])){
				if(substr_count($matches[0], "'") == 1){
					return $matches[0];
				}
			}
			
			// data:audio/wave;base64,/UklGRiYAAABXQVZFZm10IBAAAAABAAEARKwAAIhYAQACABAAZGF0YQIAAAD//w\x3d\x3d
			if(preg_match("/^\/\/w\\\\x3d/", $matches[0])){
				return $matches[0];
			}

			// var a = '<a href="javascript://" id="nextLink" title="' + opts.strings.nextLinkTitle + '"></a>';
			if(preg_match("/^cript\:\/\/\"/", $matches[0])){
				return $matches[0];
			}

			// url.replace( /^http:\/\//i, 'https://' );
			// var regex = /^\/page\/\d+\//i;
			if(preg_match("/^\/\/i\,/", $matches[0])){
				return $matches[0];
			}

			// {pattern:/\/\*[\*!][\s\S]*?\*\//gm,alias:"co2"}
			if(preg_match("/^\/\/gm\,/", $matches[0])){
				return $matches[0];
			}

			// replace(/\//g,"")
			if(preg_match("/^\/\/g\,\s*[\'\"]/", $matches[0])){
				return $matches[0];
			}

			// match(/^https?:\/\//)
			if(preg_match("/^\/\/\)/", $matches[0])){
				return $matches[0];
			}

			//src="//about:blank" frameborder="0" allowfullscreen></iframe>'+
			if(preg_match("/^\/\/about\:blank/", $matches[0])){
				return $matches[0];
			}

			// if (URL.match( /^https?:\/\// ) ) {
			if(preg_match("/^\/\/\s*\)\s*\)\s*\{/", $matches[0])){
				return $matches[0];
			}

			// "string".replace(/\//,3);
			if(preg_match("/^\/\/\s*\,/", $matches[0])){
				return $matches[0];
			}

			// comments: /\/\*[^*]*\*+([^/][^*]*\*+)*\//gi,
			if(preg_match("/^\/\/\s*gi\s*\,/", $matches[0])){
				return $matches[0];
			}

			// whatsapp://send?text=
			// NOTE: preg_match_replace gets only 5 chars so we check "tsapp://" instead of "whatsapp://"
			if(preg_match("/^tsapp\:\/\/send/", $matches[0])){
				return $matches[0];
			}

			// rtmp://37.77.2.234:1935/redirect/live.flv
			if(preg_match("/^rtmp\:\/\//", $matches[0])){
				return $matches[0];
			}

			if(preg_match("/^maps\:\/\//", $matches[0])){
				return $matches[0];
			}
			
			if(preg_match("/^\/\/\//", $matches[0])){
				return $matches[0];
			}
			
			if(preg_match("/^http/", $matches[0])){
				return $matches[0];
			}

			if(preg_match("/\.exec\(|\.test\(|\.match\(|\.search\(|\.replace\(|\.split/", $matches[0])){
				return $matches[0];
			}

			if(preg_match("/^\/\/(?!\-)(?:[a-zA-Z\d\-]{0,62}[a-zA-Z\d]\.){1,126}(?!\d+)[a-zA-Z\d]{1,63}/", $matches[0])){
				return $matches[0];
			}

			if(preg_match("/\'|\"/", $matches[0])){
				// ' something
				if(preg_match("/^\/\/\s*[\'|\"]/", $matches[0])){
					return $matches[0];
				}

				// new Validator.Assert().Regexp('(https?:\\/\\/)?(www\\.)?[-a-zA-Z0-9@:%._\\+~#=]{2,256}\\.[a-z]{2,4}\\b([-a-zA-Z0-9@:%_\\+.~#?&//=]*)', 'i');
				if(preg_match("/[\'\"]\,\s*[\'\"]i[\'\"]\)\;/", $matches[0])){
					return $matches[0];
				}

				/*function Uc(a,b){var c=Q&&Q.isAvailable(),d=c&&!(nb.kd||!0===nb.get("previous_websocket_failure"));b.ce&&(c||L("
				wss:// URL used, but browser isn't known to support websockets.  Trying anyway."),d=!0);if(d)a.Mb=[Q];else{var e=a.Mb=[];Vb(Vc,function(a,b){b&&b.isAvailable()&&e.push(b)})}}function Wc(a){if(0<a.Mb.length)return a.Mb[0];throw Error("No transports available");};function Xc(a,b,c,d,e,f){this.id=a;this.e=Mb("c:"+this.id+":");this.Lc=c;this.Ab=d;this.S=e;this.Kc=f;this.M=b;this.fc=[];this.Zc=0;this.yd=new Tc(b);this.ma=0;this.e("Connection created");Yc(this)}
				*/
				if(preg_match("/if\(/", $matches[0]) && preg_match("/this\./", $matches[0]) && preg_match("/function/", $matches[0])){
					return $matches[0];
				}

				// <script defer src="//:" id="__onload_ie_pixastic__">\x3c/script>
				if(preg_match("/x3c\/script>/i", $matches[0])){
					return $matches[0];
				}

				return "";
			}

			if(preg_match("/<\/script>/", $matches[0])){
				return preg_replace("/\/\/[^\<]+<\/script>/", "</script>", $matches[0]);
			}

			return "";
		}

		public function minify_inline_js($data){
			$script_list = array();
			$script_start_index = false;

			for($i = 0; $i < strlen( $data ); $i++) {
				if(isset($data[$i-6])){
				    if(substr($data, $i-6, 7) == "<script"){
				    	$script_start_index = $i-6;
					}
				}

				if(isset($data[$i-8])){
					if($script_start_index){
						if(substr($data, $i-8, 9) == "</script>"){
							array_push($script_list, array("start" => $script_start_index, "end" => $i));
							$script_start_index = false;
						}
					}
				}
			}

			if(!empty($script_list)){
				foreach (array_reverse($script_list) as $key => $value) {
					$inline_script = substr($data, $value["start"], ($value["end"] - $value["start"] + 1));

					$this->inline_scripts = $this->inline_scripts.$inline_script;
					
					if(preg_match("/google\_ad\_slot/i", $inline_script)){
						continue;
					}
						
					$inline_script = $this->minify_js($inline_script, true);

					$inline_script = str_replace(' type="text/javascript"', "", $inline_script);
					$inline_script = str_replace(" type='text/javascript'", "", $inline_script);

					$this->inline_scripts = $this->inline_scripts.$inline_script;

					$data = substr_replace($data, $inline_script, $value["start"], ($value["end"] - $value["start"] + 1));

				}
			}

			return $data;
		}

		public function minify_js_in_body($wpfc){
			$data = $this->html;
			$script_list = array();
			$script_start_index = false;

			for($i = 0; $i < strlen( $data ); $i++) {
				if(isset($data[$i-6])){
				    if(substr($data, $i-6, 7) == "<script"){
				    	$script_start_index = $i-6;
					}
				}

				if(isset($data[$i-8])){
					if($script_start_index){
						if(substr($data, $i-8, 9) == "</script>"){
							array_push($script_list, array("start" => $script_start_index, "end" => $i));
							$script_start_index = false;
						}
					}
				}
			}

			if(!empty($script_list)){
				foreach (array_reverse($script_list) as $key => $value) {
					$script_tag = substr($data, $value["start"], ($value["end"] - $value["start"] + 1));

					if(preg_match("/^<script[^\>\<]+src\=[^\>\<]+>/i", $script_tag) && !preg_match("/\/wpfc\-minified\//i", $script_tag)){

						preg_match("/src\=[\"\']([^\'\"]+)[\"\']/i", $script_tag, $src);

						$http_host = str_replace(array("http://", "www."), "", $_SERVER["HTTP_HOST"]);

						if(preg_match("/".preg_quote($http_host, "/")."/i", $src[1])){

							if(preg_match("/alexa\.com\/site\_stats/i", $src[1])){
								continue;
							}

							if(preg_match("/wp-spamshield\/js\/jscripts\.php/i", $src[1])){
								continue;
							}

							$cachFilePath = WPFC_WP_CONTENT_DIR."/cache/wpfc-minified/".md5($script_tag);
							$jsScript = content_url()."/cache/wpfc-minified/".md5($script_tag);
							$jsScript = str_replace(array("http://", "https://"), "//", $jsScript);

							$response = wp_remote_get($this->fix_protocol($src[1]), array('timeout' => 10 ) );

							if ( !$response || is_wp_error( $response ) ) {
								continue;
							}else{
								if(wp_remote_retrieve_response_code($response) == 200){
									$js_content = wp_remote_retrieve_body( $response );

									if(preg_match("/<\/\s*html\s*>\s*$/i", $js_content)){
										continue;
									}else{
										$minified_js_content = $this->minify_js($js_content);

										if(!is_dir($cachFilePath)){
											$prefix = time();
											$wpfc->createFolder($cachFilePath, $minified_js_content, "js", $prefix);
										}

										if($jsFiles = @scandir($cachFilePath, 1)){
											$new_script = str_replace($src[1], $jsScript."/".$jsFiles[0], $script_tag);
											$this->html = substr_replace($this->html, $new_script, $value["start"], ($value["end"] - $value["start"] + 1));
										}
									}
								}
							}
						}
					}
				}
			}

			return $this->html;
		}

		public function combine_js_in_footer($content, $minify = false){
			$footer = strstr($this->html, '<!--WPFC_FOOTER_START-->');

			$js = new JsUtilities($content, $footer, $minify);

			$tmp_footer = $js->combine_js();

			$this->html = str_replace($footer, $tmp_footer, $this->html);

			return $this->html;
		}
		/* Js Part End */

		public function fix_protocol($url){
			if(preg_match("/^\/\//", $url)){
				if(preg_match("/^https:\/\//", home_url())){
					return "https:".$url;
				}else{
					return "http:".$url;
				}
			}
			return $url;
		}
	}
?>