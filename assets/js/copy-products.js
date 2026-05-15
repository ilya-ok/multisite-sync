/**
 * JavaScript для страницы копирования товаров
 * Multisite Sync Plugin
 */

(function($) {
	'use strict';

	/**
	 * Загрузка товаров при выборе категории
	 */
	function loadProducts() {
		var categoryId = $(this).val();

		if (!categoryId) {
			$('#ms-copy-products-container').hide().empty();
			$('#ms-copy-product-count').text('');
			return;
		}

		$('.ms-copy-loading').show();
		$('#ms-copy-products-container').hide().empty();
		$('#ms-copy-product-count').text('');

		$.ajax({
			url: msCopyData.ajaxurl,
			type: 'POST',
			data: {
				action: 'ms_get_copy_products',
				category_id: categoryId,
				nonce: msCopyData.nonce
			},
			success: function(response) {
				$('.ms-copy-loading').hide();

				if (response.success) {
					$('#ms-copy-products-container').html(response.data.html).show();

					if (response.data.count > 0) {
						$('#ms-copy-product-count').text('(' + response.data.count + ' товаров)');
					}
				} else {
					alert('Ошибка загрузки: ' + (response.data || 'неизвестная ошибка'));
				}
			},
			error: function() {
				$('.ms-copy-loading').hide();
				alert('Ошибка AJAX запроса');
			}
		});
	}

	/**
	 * Копирование товара — по одному сайту за запрос
	 */
	function copyProduct() {
		var $btn      = $(this);
		var $row      = $btn.closest('tr');
		var $status   = $row.find('.ms-copy-status');
		var productId = $btn.data('product-id');
		var sites     = msCopyData.sites;

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
				url: msCopyData.ajaxurl,
				type: 'POST',
				timeout: 120000,
				data: {
					action:     'ms_copy_product',
					product_id: productId,
					site_id:    site.id,
					nonce:      msCopyData.nonce
				},
				success: function(response) {
					var $cell = $row.find('.ms-col-site[data-site-id="' + site.id + '"]');

					if (response.success && response.data.results && response.data.results[site.id] && response.data.results[site.id].success) {
						$cell.removeClass('ms-site-status-missing ms-site-status-error').addClass('ms-site-status-ok').text('✓');
						ok++;
					} else {
						var errMsg = (response.data && response.data.results && response.data.results[site.id])
							? (response.data.results[site.id].error || 'Ошибка')
							: (response.data || 'Ошибка');
						$cell.removeClass('ms-site-status-missing ms-site-status-ok').addClass('ms-site-status-error').attr('title', errMsg).text('✗');
						err++;
					}

					next();
				},
				error: function(xhr, status) {
					var $cell = $row.find('.ms-col-site[data-site-id="' + site.id + '"]');
					var msg = status === 'timeout' ? 'Таймаут' : 'Ошибка запроса';
					$cell.removeClass('ms-site-status-missing ms-site-status-ok').addClass('ms-site-status-error').attr('title', msg).text('✗');
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
				var msg = '✓ Готово: ' + ok;
				if (err > 0) { msg += ', ошибок: ' + err; }
				var color = err > 0 ? '#dc3232' : '#46b450';
				$status.html('<span style="color:' + color + ';">' + msg + '</span>');
				setTimeout(function() { $status.html(''); }, 6000);
			}
		}

		copySite(sites[0]);
	}

	/**
	 * Инициализация при загрузке страницы
	 */
	$(document).ready(function() {
		$('#ms-copy-category-select').on('change', loadProducts);
		$(document).on('click', '.ms-copy-btn', copyProduct);
	});

})(jQuery);
