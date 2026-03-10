/**
 * AI Price Negotiator — Analytics Dashboard JS
 *
 * Handles CSV export and chat transcript toggles.
 */
(function ($) {
    'use strict';

    $(document).ready(function () {
        initCsvExport();
        initChatToggle();
    });

    /* ── CSV Export ───────────────────────────────────────────────────── */
    function initCsvExport() {
        var $btn = $('#aipn-export-csv');
        if (!$btn.length || typeof aipnAnalytics === 'undefined') {
            return;
        }

        var s = aipnAnalytics.strings;

        $btn.on('click', function () {
            var period = $btn.data('period') || 30;

            $btn.prop('disabled', true).addClass('aipn-spin').html(
                '<span class="dashicons dashicons-update"></span> ' + s.exporting
            );

            $.ajax({
                url: aipnAnalytics.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'aipn_export_csv',
                    nonce: aipnAnalytics.nonce,
                    period: period
                },
                dataType: 'json'
            }).done(function (res) {
                if (res.success && res.data.csv) {
                    var blob = new Blob([res.data.csv], { type: 'text/csv;charset=utf-8;' });
                    var url = URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = res.data.filename || 'negotiations.csv';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                }
            }).always(function () {
                $btn.prop('disabled', false).removeClass('aipn-spin').html(
                    '<span class="dashicons dashicons-download"></span> ' + s.exportBtn
                );
            });
        });
    }

    /* ── Chat Transcript Toggle ──────────────────────────────────────── */
    function initChatToggle() {
        if (typeof aipnAnalytics === 'undefined') {
            return;
        }

        var s = aipnAnalytics.strings;

        $(document).on('click', '.aipn-view-chat', function () {
            var $btn = $(this);
            var idx = $btn.data('chat-index');
            var $row = $('#aipn-chat-row-' + idx);

            if ($row.is(':visible')) {
                $row.hide();
                $btn.removeClass('aipn-btn-chat--active').text(s.view);
            } else {
                $row.show();
                $btn.addClass('aipn-btn-chat--active').text(s.hide);
            }
        });

        $(document).on('click', '.aipn-close-chat', function () {
            var idx = $(this).data('chat-index');
            var $row = $('#aipn-chat-row-' + idx);
            $row.hide();
            $('.aipn-view-chat[data-chat-index="' + idx + '"]')
                .removeClass('aipn-btn-chat--active')
                .text(s.view);
        });
    }

})(jQuery);
