if ( ! mda_change_directory_data ) {
	mda_change_directory_data = {};
} else if (typeof mda_change_directory_data !== "object") {
	throw new Error('mda_change_directory_data already exists and not an object');
}

jQuery(function () {
	'use strict';
	var bdy = jQuery(document.body), MDAChangeDirectory = null, mda_obj = null;;

	// bulkアクション
	jQuery('#doaction, #doaction2').on('click', function (e) {
		if (jQuery(this).parent().find('select').val() === 'media-directory-arrange') {

			// チェックされたエレメントを取得
			var els = jQuery('#the-list .check-column input[type="checkbox"]:checked');

			// 対象のエレメントがある場合
			if (els.length > 0) {

				// デフォルトアクションを停止
				e.preventDefault();

				// チェックされたエレメントにMDAオブジェクトを適用
				els.each(function (i, el) {
					mda_obj = new MDAChangeDirectory(el);
				});
			}

		}
	});
	
	// ディレクトリ変更クラス
	var MDAChangeDirectory = function (el) {
		var changeDirectory = {
			id: null,
			parent: null,
			message: null,
			init: function (el) {
				this.id = el.val();
				this.parent = el.closest('tr');

				if (this.parent.find('.title .mda-msg').length !== 0)  this.parent.find('.title .mda-msg').remove();
				if (this.parent.find('.title .mda-msg').length === 0)  this.parent.find('.title .filename').after('<div class="mda-msg"/>');
				this.message = this.parent.find('.title .mda-msg');

				if (!this.parent.hasClass('ajaxing')) this.changeDirectoryAjax();
				
			},
			setMessage: function (msg) {
				// Display the message
				this.message.html(' - ' + msg).addClass('updated').addClass('fade').show();
			},
			changeDirectoryAjax: function () {
				var self = this;
				jQuery.ajax({
					url: ajaxurl,
					type: "POST",
					dataType: 'json',
					cache: false,
					data: {
						action: mda_change_directory_data.action,
						id: this.id,
						nonce: mda_change_directory_data.nonce
					},
					beforeSend: function () {
						self.parent.addClass('ajaxing').fadeTo('fast', '0.3');
					},
					success: function (r) {
						var msg = '';
						if ( ! r.success || r.data.error.length !== 0 || typeof r !== 'object') {
							msg += (typeof r !== 'object') ? 'Non Object' : r.data.error + "\n";
						}else{
							msg += r.data.path;
						}
						self.setMessage(msg);
						self.parent.removeClass('ajaxing').fadeTo('fast', '1');
					}
				});//end ajax
			}
		};
		changeDirectory.init(jQuery(el));
	};


});