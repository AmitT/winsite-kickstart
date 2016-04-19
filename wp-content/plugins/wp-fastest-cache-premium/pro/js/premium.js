var Wpfc_Premium = {
	check_update: function(current_premium_version, wpfc_api_url, plugins_url){
		jQuery.ajax({
			type: 'GET', 
			url: "https://api.wpfastestcache.net/premium/version/",
			cache: false,
			error: function(x, t, m) {
				alert(t);
			},
			success: function(new_version){
				if(new_version != current_premium_version){
					jQuery("#wpfc-update-premium-button").attr("class", "wpfc-btn primaryCta");

					jQuery("#wpfc-update-premium-button").click(function(){
						jQuery("#revert-loader-toolbar").show();
						jQuery.ajax({
							type: 'GET',
							url: ajaxurl,
							data : {"action": "wpfc_update_premium"},
							dataType : "json",
							cache: false, 
							success: function(data){
								jQuery("#revert-loader-toolbar").hide();
								
								if(data.success){
									jQuery.get(plugins_url + "/update_success.html", function( data ) {
										jQuery("body").append(data);
										Wpfc_Dialog.dialog("wpfc-modal-updatesuccess", {"finish" : 
												function(){
													Wpfc_Dialog.remove();
													location.reload();
												}
											}
										);
									});
								}else{
									jQuery.get(plugins_url + "/update_error.html", function( html ) {
										jQuery("body").append(html);
										jQuery("#wpfc-download-now").attr("href", wpfc_api_url);
										jQuery('#wpfc-update-error-message').text(data.error_message);
										console.log(wpfc_api_url, data.error_message, "wpfc_api_url");
										Wpfc_Dialog.dialog("wpfc-modal-updateerror");
										jQuery("#revert-loader-toolbar").hide();
									});
								}
								console.log(data, "data");
							}
						});
					});
				}else{
					jQuery("#wpfc-update-premium-button").text("No Update");
				}
			}
		});
	}
};