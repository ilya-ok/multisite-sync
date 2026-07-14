/* global mpsCopyGalleriesData, jQuery */

(function ($) {
    'use strict';

    function loadGalleries() {
        var $btn       = $('#mps-load-galleries');
        var $count     = $('#mps-galleries-count');
        var $loading   = $('.mps-galleries-loading');
        var $container = $('#mps-galleries-container');

        $btn.prop('disabled', true);
        $loading.show();
        $container.hide().empty();
        $count.text('');

        $.ajax({
            url:  mpsCopyGalleriesData.ajaxurl,
            type: 'POST',
            data: {
                action: 'mps_get_galleries',
                nonce:  mpsCopyGalleriesData.nonce
            },
            success: function (response) {
                $btn.prop('disabled', false);
                $loading.hide();

                if (response.success) {
                    $container.html(response.data.html).show();
                    if (response.data.count > 0) {
                        $count.text('(' + response.data.count + ')');
                    }
                } else {
                    $container.html('<p class="ms-error">' + (response.data || 'Ошибка') + '</p>').show();
                }
            },
            error: function () {
                $btn.prop('disabled', false);
                $loading.hide();
                $container.html('<p class="ms-error">Ошибка запроса</p>').show();
            }
        });
    }

    function copyGallery() {
        var $btn    = $(this);
        var $row    = $btn.closest('tr');
        var $status = $row.find('.ms-copy-status');
        var postId  = $btn.data('post-id');
        var sites   = mpsCopyGalleriesData.sites;

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
                url:     mpsCopyGalleriesData.ajaxurl,
                type:    'POST',
                timeout: 60000,
                data: {
                    action:  'mps_copy_gallery',
                    post_id: postId,
                    site_id: site.id,
                    nonce:   mpsCopyGalleriesData.nonce
                },
                success: function (response) {
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
                error: function (xhr, status) {
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
                setTimeout(function () { $status.html(''); }, 6000);
            }
        }

        copySite(sites[0]);
    }

    $(document).ready(function () {
        $('#mps-load-galleries').on('click', loadGalleries);
        $(document).on('click', '.mps-copy-gallery-btn', copyGallery);
    });

}(jQuery));
