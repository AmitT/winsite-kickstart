var Wpfc_New_Dialog = {
	id : "",
	buttons: [],
	clone: "",
	current_page_number: 1,
	total_page_number: 0,
	dialog: function(id, buttons){
		var self = this;
		self.clone = jQuery("div[template-id='" + id + "']").clone();

		self.total_page_number = self.clone.find("div[wpfc-page]").length;

		self.id = id + "-" + new Date().getTime();
		self.buttons = buttons;
		
		self.clone.attr("id", self.id);
		self.clone.removeAttr("template-id");

		jQuery("body").append(self.clone);
		
		self.clone.show();
		
		self.clone.draggable();
		self.clone.position({my: "center", at: "center", of: window});
		self.clone.find(".close-wiz").click(function(){
			self.remove(this);
		});

		self.update_ids_for_label();
		
		self.show_buttons();
	},
	remove: function(button){
		jQuery(button).closest("div[id^='wpfc-modal-']").remove();
	},
	show_buttons: function(){
		var self = this;

		if(typeof self.buttons != "undefined"){
			jQuery.each(self.buttons, function( index, value ) {
				self.clone.find("button[action='" + index + "']").click(function(){
					if(value == "default"){
						if(index == "next"){
							self.default_next_action();
						}

						if(index == "back"){
							self.default_back_action();
						}

						if(index == "close"){
							self.default_close_action();
						}
					}else{
						value(this);
					}
				});
			});
		}
	},
	default_next_action: function(){
		this.current_page_number = this.current_page_number + 1;

		this.show_page(this.current_page_number);

		this.show_button("back");

		if(this.total_page_number == this.current_page_number){
			this.hide_button("next");
			this.show_button("finish");
		}
	},
	default_back_action: function(){
		this.current_page_number = this.current_page_number - 1;

		this.show_page(this.current_page_number);

		this.show_button("next");
		this.hide_button("finish");

		if(this.current_page_number == 1){
			this.hide_button("back");
		}
	},
	default_close_action: function(){
		Wpfc_New_Dialog.clone.remove();
	},
	show_button: function(index){
		this.clone.find("button[action='" + index + "']").show();
	},
	hide_button: function(index){
		this.clone.find("button[action='" + index + "']").hide();
	},
	show_page: function(number){
		this.clone.find("div[wpfc-page]").hide();
		this.clone.find("div[wpfc-page='" + number + "']").show();
		this.current_page_number = number;
	},
	update_ids_for_label: function(){
		var self = this;
		var id = "";

		self.clone.find("div.window-content div.wiz-input-cont").each(function(){
			id = jQuery(this).find("label.mc-input-label input").attr("id") + self.id;

			jQuery(this).find("label.mc-input-label input").attr("id", id);
			jQuery(this).find("label").last().attr("for", id);
		});
	}
};