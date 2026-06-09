/**
 * Копирование записей между сайтами мультисайта
 */

(function($) {
	'use strict';

	function loadPosts() {
		$('.mps-loading').show();
		$('#mps-posts-container').hide().empty();
		$('#mps-post-count').text('');

		$.ajax({
			url:  mpsCopyPostsData.ajaxurl,
			type: 'POST',
			data: {
				action: 'mps_get_posts',
				nonce:  mpsCopyPostsData.nonce
			},
			success: function(response) {
				$('.mps-loading').hide();

				if (response.success) {
					$('#mps-posts-container').html(response.data.html).show();
					if (response.data.count > 0) {
						$('#mps-post-count').text('(' + response.data.count + ')');
					}
				} else {
					alert('Ошибка: ' + (response.data || 'неизвестная ошибка'));
				}
			},
			error: function() {
				$('.mps-loading').hide();
				alert('Ошибка AJAX запроса');
			}
		});
	}

	function copyPost() {
		var $btn    = $(this);
		var $row    = $btn.closest('tr');
		var $status = $row.find('.ms-copy-status');
		var postId  = $btn.data('post-id');
		var sites   = mpsCopyPostsData.sites;

		if (!sites || !sites.length) {
			$status.html('<span style="color:#dc3232;">✗ Нет дочерних сайтов</span>');
			return;
		}

		$btn.prop('disabled', true).text('Копирование...');

		var ok    = 0;
		var err   = 0;
		var total = sites.length;
		var index = 0;

		function copySite(site) {
			$status.html('<span style="color:#888;">Сайт ' + (index + 1) + ' из ' + total + ': ' + site.name + '…</span>');

			$.ajax({
				url:     mpsCopyPostsData.ajaxurl,
				type:    'POST',
				timeout: 60000,
				data: {
					action:  'mps_copy_post',
					post_id: postId,
					site_id: site.id,
					nonce:   mpsCopyPostsData.nonce
				},
				success: function(response) {
					var $cell   = $row.find('.ms-col-site[data-site-id="' + site.id + '"]');
					var ok_site = response.success
						&& response.data.results
						&& response.data.results[site.id]
						&& response.data.results[site.id].success;

					if (ok_site) {
						$cell.removeClass('ms-site-status-missing ms-site-status-error')
							.addClass('ms-site-status-ok').text('✓');
						ok++;
					} else {
						var errMsg = (response.data && response.data.results && response.data.results[site.id])
							? (response.data.results[site.id].error || 'Ошибка')
							: (response.data || 'Ошибка');
						$cell.removeClass('ms-site-status-missing ms-site-status-ok')
							.addClass('ms-site-status-error').attr('title', errMsg).text('✗');
						err++;
					}
					next();
				},
				error: function(xhr, status) {
					var $cell = $row.find('.ms-col-site[data-site-id="' + site.id + '"]');
					var msg   = status === 'timeout' ? 'Таймаут' : 'Ошибка запроса';
					$cell.removeClass('ms-site-status-missing ms-site-status-ok')
						.addClass('ms-site-status-error').attr('title', msg).text('✗');
					err++;
					next();
				}
			});
		}

		function next() {
			index++;
			if (index < total) {
				copySite(sites[index]);
			} else {
				$btn.prop('disabled', false).text('Копировать');
				var msg   = '✓ Готово: ' + ok;
				if (err > 0) { msg += ', ошибок: ' + err; }
				var color = err > 0 ? '#dc3232' : '#46b450';
				$status.html('<span style="color:' + color + ';">' + msg + '</span>');
				setTimeout(function() { $status.html(''); }, 6000);
			}
		}

		copySite(sites[0]);
	}

	$(document).ready(function() {
		$('#mps-load-posts').on('click', loadPosts);
		$(document).on('click', '.mps-copy-post-btn', copyPost);
	});

})(jQuery);
