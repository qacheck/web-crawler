jQuery(function($){
	function arrUnique(value, index, self) { 
	    return self.indexOf(value) === index;
	}

	var ajax_scan = [],
		results = $('#webcrl-results'),
		message = $('#webcrl-message'),
		btn_do = $('#webcrl_do'),
		btn_save = $('#webcrl_save'),
		btn_stop = $('#webcrl_stop');

	function web_crawler(source_url, source_type) {
		if(source_url.length>0) {
			btn_do.html('Scanning...');
			btn_do.prop('disabled', true);
			btn_save.prop('disabled', true);
			btn_stop.prop('disabled', false);

			var crawl_url = source_url.pop();
			
			var ajax = $.ajax({
					url: webcrl.ajax_url+'?action=webcrl_scan',
					type: 'POST',
					data: {su:crawl_url,st:source_type},
					dataType: 'json',
					success: function(res) {
						var msg = (res['error']!='')?res['crawled_url']+' - '+res['error'] : res['crawled_url'];
						message.html(msg);
						//console.log(res);
						var next_crawl = source_url.concat(res['next_crawl']);
						next_crawl = next_crawl.filter(arrUnique);

						web_crawler(next_crawl,source_type);
					},
					error: function(xhr,status,err) {
						web_crawler(source_url,source_type);
					}

				});
			ajax_scan.push(ajax);
		} else {
			btn_do.html('Get products');
			btn_do.prop('disabled', false);
			btn_save.prop('disabled', false);
			btn_stop.prop('disabled', true);
		}
	}

	btn_do.on('click', function(e){
		var su = [$('#webcrl_su').val()],
			ua = $('#webcrl_ua').val(),
			st = $('#webcrl_source_type').val(); //source type
		// btn_save.prop('disabled', true);
		// btn_stop.prop('disabled', false);
		if(su!='' && st!='') {
			$.ajax({
				url: webcrl.ajax_url+'?action=webcrl_remove_crawled',
				data: {ua:ua,su:su[0],st:st},
				success: function(res) {
					web_crawler(su, st);
				}
			});
		} else {
			alert('Input your source url!');
		}
	});

	btn_stop.on('click', function(e){
		// if(ajax_scan.length>0) {
		// 	ajax_scan.each(function(index, ajax){
		// 		ajax.abort();
		// 	});
		// 	ajax_scan = [];
		// }
		while(ajax_scan.length>0) {
			var ajax = ajax_scan.pop();
			ajax.abort();
		}
		
		btn_do.html('Get products');
		btn_do.prop('disabled', false);
		btn_save.prop('disabled', false);
		btn_stop.prop('disabled', true);
	});

	btn_save.on('click', function(e){
		btn_save.html('Saving...');
		message.html('');
		$.ajax({
			url: webcrl.ajax_url+'?action=webcrl_view_crawled',
			//data: {ua:ua,su:su[0]},
			success: function(res) {
				$('#webcrl-view-products').html(res);
				btn_save.html('Saved');
			}
		});
	});
});