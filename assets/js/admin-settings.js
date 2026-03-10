/**
 * AI Price Negotiator — Admin Settings JS
 */
(function ($) {
    'use strict';

    $(document).ready(function () {
        // Initialize color pickers.
        $('.aipn-color-picker').wpColorPicker();

        // Add "Test API Key" button next to the API key field.
        initApiKeyTest();

        // Reset Styling button.
        initResetStyling();
    });

    function initApiKeyTest() {
        var $keyField = $('#aipn_openai_key');
        if (!$keyField.length || typeof aipnAdmin === 'undefined') {
            return;
        }

        var s = aipnAdmin.strings;

        // Create the test button and result indicator.
        var $btn = $('<button type="button" class="button aipn-test-key-btn">' + s.testBtn + '</button>');
        var $result = $('<span class="aipn-test-key-result"></span>');

        // Insert after the field's parent td content.
        $keyField.after($result).after($btn);

        $btn.on('click', function () {
            var key = $.trim($keyField.val());

            if (!key) {
                showResult($result, 'error', s.empty);
                return;
            }

            $btn.prop('disabled', true).text(s.testing);
            $result.removeAttr('class').addClass('aipn-test-key-result').text('');

            $.ajax({
                url: aipnAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'aipn_test_api_key',
                    nonce: aipnAdmin.nonce,
                    api_key: key
                },
                dataType: 'json'
            }).done(function (res) {
                if (res.success) {
                    showResult($result, 'success', s.success);
                } else {
                    showResult($result, 'error', res.data && res.data.message ? res.data.message : s.error);
                }
            }).fail(function () {
                showResult($result, 'error', s.error);
            }).always(function () {
                $btn.prop('disabled', false).text(s.testBtn);
            });
        });
    }

    function initResetStyling() {
        var $btn = $('#aipn-reset-styling');
        if (!$btn.length || typeof aipnAdmin === 'undefined') {
            return;
        }

        var s = aipnAdmin.strings;

        $btn.on('click', function () {
            if (!confirm(s.resetConfirm)) {
                return;
            }

            var originalText = $btn.text();
            $btn.prop('disabled', true).text(s.resetting);

            $.ajax({
                url: aipnAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'aipn_reset_styling',
                    nonce: aipnAdmin.resetNonce
                },
                dataType: 'json'
            }).done(function (res) {
                if (res.success) {
                    window.location.reload();
                } else {
                    alert(s.resetError);
                    $btn.prop('disabled', false).text(originalText);
                }
            }).fail(function () {
                alert(s.resetError);
                $btn.prop('disabled', false).text(originalText);
            });
        });
    }

    function showResult($el, type, message) {
        $el.removeAttr('class')
           .addClass('aipn-test-key-result aipn-test-key-result--' + type)
           .text(message);
    }

})(jQuery);
