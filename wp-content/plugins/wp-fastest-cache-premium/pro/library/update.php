<?php
	class WpFastestCacheUpdate{
		public function __construct(){}

		public function download_premium(){
			$res = array();
			$response = wp_remote_get("http://api.wpfastestcache.net/premium/newdownload/".str_replace(array("http://", "www."), "", $_SERVER["HTTP_HOST"])."/".get_option("WpFc_api_key"), array('timeout' => 10 ) );

			if ( !$response || is_wp_error( $response ) ) {
				$res = array("success" => false, "error_message" => $response->get_error_message());
			}else{
				if(wp_remote_retrieve_response_code($response) == 200){

					if($wpfc_zip_data = wp_remote_retrieve_body( $response )){
						$res = array("success" => true, "content" => $wpfc_zip_data);
					}else{
						$res = array("success" => false, "error_message" => ".zip file is empty");
					}

				}else{
					$res = array("success" => false, "error_message" => "Error: Try later...");
				}
			}
			return $res;
		}
	}
?>