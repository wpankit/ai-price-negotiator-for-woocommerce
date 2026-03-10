/**
 * AI Price Negotiator — Checkout Widget
 *
 * Handles the negotiation chat interface on the WooCommerce checkout page.
 * Communicates with the REST API and manages the conversation UI.
 */
(function ($) {
    'use strict';

    if (typeof AIPNNegotiator === 'undefined') {
        return;
    }

    var data = AIPNNegotiator;
    var t = function (key) {
        return (data.strings && data.strings[key]) || key;
    };

    // DOM references.
    var $widget, $cta, $panel, $messages, $form, $message, $submit, $typing,
        $suggestions, $dealBanner, $dealMessage, $dealCoupon, $openBtn, $closeBtn,
        $emailCapture, $emailForm, $captureEmail, $captureName;

    var isOpen = false;
    var isLoading = false;
    var isAccepted = false;
    var sessionRestored = false;
    var emailCaptured = false;

    /**
     * Initialize the widget.
     */
    function init() {
        $widget      = $('#aipn-negotiation-widget');
        $cta         = $('#aipn-cta');
        $panel       = $('#aipn-chat-panel');
        $messages    = $('#aipn-messages');
        $form        = $('#aipn-form');
        $message     = $('#aipn-message');
        $submit      = $('#aipn-submit');
        $typing      = $('#aipn-typing');
        $suggestions = $('#aipn-suggestions');
        $dealBanner  = $('#aipn-deal-banner');
        $dealMessage = $('#aipn-deal-message');
        $dealCoupon  = $('#aipn-deal-coupon');
        $openBtn      = $('#aipn-open-btn');
        $closeBtn     = $('#aipn-close-btn');
        $emailCapture = $('#aipn-email-capture');
        $emailForm    = $('#aipn-email-form');
        $captureEmail = $('#aipn-capture-email');
        $captureName  = $('#aipn-capture-name');

        if (!$widget.length) {
            return;
        }

        // For block checkout: move widget into the checkout layout.
        positionWidgetInBlockCheckout();

        bindEvents();

        // Restore session first — set flag immediately to prevent greeting race.
        sessionRestored = true; // Assume restored until proven otherwise.
        restoreSession();
    }

    /**
     * If rendered via wp_footer fallback (block checkout), move the widget
     * into the checkout layout below the order summary.
     */
    function positionWidgetInBlockCheckout() {
        // Already inside a classic checkout form — no need to move.
        if ($widget.closest('form.woocommerce-checkout').length) {
            return;
        }

        // Block checkout — append inside the sidebar so it sits right below the order summary total.
        var $sidebar = $('.wc-block-checkout__sidebar');
        if ($sidebar.length) {
            $sidebar.append($widget);
            return;
        }

        // Fallback: try after the order summary block itself.
        var $summary = $('.wp-block-woocommerce-checkout-order-summary-block');
        if ($summary.length) {
            $summary.after($widget);
            return;
        }
    }

    /**
     * Bind UI events.
     */
    function bindEvents() {
        $openBtn.on('click', openPanel);
        $closeBtn.on('click', closePanel);
        $form.on('submit', handleSubmit);
        $emailForm.on('submit', handleEmailSubmit);

        // Allow Enter to submit.
        $message.on('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                $form.trigger('submit');
            }
        });
    }

    /**
     * Open the chat panel.
     */
    function openPanel() {
        isOpen = true;
        $cta.hide();
        $panel.removeAttr('hidden').addClass('aipn-widget__panel--open');
        $openBtn.attr('aria-expanded', 'true');

        // Auto-trigger greeting on first open if no messages yet.
        if ($messages.children().length === 0 && !sessionRestored) {
            triggerGreeting();
        }

        scrollToBottom();
        $message.focus();
    }

    /**
     * Close the chat panel.
     */
    function closePanel() {
        isOpen = false;
        $panel.attr('hidden', '').removeClass('aipn-widget__panel--open');
        $cta.show();
        $openBtn.attr('aria-expanded', 'false');
    }

    /**
     * Trigger the AI greeting message.
     */
    function triggerGreeting() {
        setLoading(true);
        showTyping();

        apiCall('negotiate', { message: '', offer: 0 }, function (res) {
            hideTyping();
            setLoading(false);
            if (res.reply) {
                appendMessage(res.reply, 'assistant');
            }
        }, function () {
            hideTyping();
            setLoading(false);
        });
    }

    /**
     * Handle form submission.
     */
    function handleSubmit(e) {
        e.preventDefault();

        if (isLoading || isAccepted) {
            return;
        }

        var messageVal = $.trim($message.val());

        if (!messageVal) {
            shakeElement($form);
            return;
        }

        // Display the user message.
        appendMessage(messageVal, 'user');

        // Clear input.
        $message.val('');

        // Send to API — offer is extracted from the message text on the server.
        setLoading(true);
        showTyping();

        apiCall('negotiate', { message: messageVal, offer: 0 }, function (res) {
            hideTyping();
            setLoading(false);

            if (res.reply) {
                appendMessage(res.reply, 'assistant');
            }

            // Handle suggestions.
            if (res.suggestions && res.suggestions.length > 0) {
                showSuggestions(res.suggestions);
            }

            // Handle cart actions (add, remove, update qty performed by the AI).
            if (res.cart_actions && res.cart_actions.length > 0) {
                var anySuccess = res.cart_actions.some(function (a) { return a.success; });
                if (anySuccess) {
                    appendMessage(t('cartUpdated'), 'system');
                }
                refreshCheckout();
            }

            // Handle deal accepted.
            if (res.accepted) {
                handleDealAccepted(res);
                return;
            }

            // Handle email capture request (mid-chat or deal hold).
            if (res.request_email && !emailCaptured) {
                // If billing fields are already filled, capture silently.
                if (tryAutoCaptureBillingEmail()) {
                    return;
                }
                showEmailCapture();
                return;
            }

            // Handle negotiation ended (max turns reached).
            if (res.ended) {
                isAccepted = true; // Prevents further submissions.
                $form.hide();
            }
        }, function () {
            hideTyping();
            setLoading(false);
            appendMessage(t('errorGeneric'), 'system');
        });
    }

    /**
     * Handle when a deal is accepted.
     */
    function handleDealAccepted(res) {
        isAccepted = true;

        // Show deal banner.
        $dealBanner.removeAttr('hidden');
        if (res.coupon_code) {
            $dealCoupon.text(t('couponApplied') + ' ' + res.coupon_code);
        }

        // Hide the form.
        $form.hide();

        // Launch fireworks celebration.
        launchFireworks();

        // Refresh checkout totals — support both classic and block checkout.
        refreshCheckout();

        // Scroll to the deal banner.
        scrollToBottom();

        // Hide the entire widget after a short delay so the customer sees the celebration.
        setTimeout(function () {
            $widget.slideUp(300);
        }, 3000);
    }

    /**
     * Launch a fireworks/confetti celebration inside the chat panel.
     */
    function launchFireworks() {
        var container = $panel[0];
        if (!container) return;

        var overlay = document.createElement('div');
        overlay.className = 'aipn-fireworks';
        container.appendChild(overlay);

        var colors = ['#f43f5e', '#8b5cf6', '#06b6d4', '#f59e0b', '#22c55e', '#ec4899'];
        var particleCount = 40;

        for (var i = 0; i < particleCount; i++) {
            var particle = document.createElement('span');
            particle.className = 'aipn-fireworks__particle';
            particle.style.setProperty('--x', (Math.random() * 200 - 100) + 'px');
            particle.style.setProperty('--y', (Math.random() * -200 - 50) + 'px');
            particle.style.setProperty('--r', (Math.random() * 720 - 360) + 'deg');
            particle.style.setProperty('--delay', (Math.random() * 0.3) + 's');
            particle.style.setProperty('--size', (Math.random() * 6 + 4) + 'px');
            particle.style.background = colors[Math.floor(Math.random() * colors.length)];
            overlay.appendChild(particle);
        }

        // Clean up after animation ends.
        setTimeout(function () {
            if (overlay.parentNode) {
                overlay.parentNode.removeChild(overlay);
            }
        }, 2000);
    }

    /**
     * Show the inline email capture form, hide the chat input.
     */
    function showEmailCapture() {
        $form.hide();
        $emailCapture.removeAttr('hidden');
        scrollToBottom();
        $captureName.focus();
    }

    /**
     * Hide the email capture form and restore the chat input.
     */
    function hideEmailCapture() {
        $emailCapture.attr('hidden', '');
        $form.show();
        $message.focus();
    }

    /**
     * Handle email capture form submission.
     * Sends the captured email with an empty negotiate call to resume the flow.
     */
    function handleEmailSubmit(e) {
        e.preventDefault();

        var name  = $.trim($captureName.val());
        var email = $.trim($captureEmail.val());

        if (!email) {
            shakeElement($emailForm);
            return;
        }

        emailCaptured = true;

        // Auto-fill checkout billing fields.
        fillCheckoutFields(name, email);

        // Show a system message confirming email capture.
        var emailMsg = (data.strings && data.strings.emailCaptured) || 'Thanks! Your details have been saved.';
        appendMessage(emailMsg, 'system');
        hideEmailCapture();

        // Send email to the server — this may finalize a held deal.
        setLoading(true);
        showTyping();

        apiCall('negotiate', {
            message: '',
            offer: 0,
            customer_email: email,
            customer_name: name
        }, function (res) {
            hideTyping();
            setLoading(false);

            if (res.reply) {
                appendMessage(res.reply, 'assistant');
            }

            if (res.accepted) {
                handleDealAccepted(res);
            }
        }, function () {
            hideTyping();
            setLoading(false);
        });
    }

    /**
     * Check if billing fields are already filled. If so, silently send them
     * to the server (skipping the email capture form entirely).
     * Returns true if auto-capture was triggered.
     */
    function tryAutoCaptureBillingEmail() {
        var email = '';
        var name  = '';

        // Classic checkout.
        var $billingEmail = $('#billing_email');
        if ($billingEmail.length && $.trim($billingEmail.val())) {
            email = $.trim($billingEmail.val());
            var first = $.trim($('#billing_first_name').val()) || '';
            var last  = $.trim($('#billing_last_name').val()) || '';
            name = $.trim(first + ' ' + last);
        }

        // Block checkout — read from WC store.
        if (!email && typeof wp !== 'undefined' && wp.data && wp.data.select) {
            try {
                var cartStore = wp.data.select('wc/store/cart');
                if (cartStore && typeof cartStore.getCustomerData === 'function') {
                    var customer = cartStore.getCustomerData();
                    var billing  = customer && customer.billingAddress ? customer.billingAddress : {};
                    if (billing.email) {
                        email = billing.email;
                        name  = $.trim((billing.first_name || '') + ' ' + (billing.last_name || ''));
                    }
                }
            } catch (e) { /* ignore */ }
        }

        if (!email) {
            return false;
        }

        // Silently capture — same flow as handleEmailSubmit but without the form.
        emailCaptured = true;
        setLoading(true);
        showTyping();

        apiCall('negotiate', {
            message: '',
            offer: 0,
            customer_email: email,
            customer_name: name
        }, function (res) {
            hideTyping();
            setLoading(false);

            if (res.reply) {
                appendMessage(res.reply, 'assistant');
            }

            if (res.accepted) {
                handleDealAccepted(res);
            }
        }, function () {
            hideTyping();
            setLoading(false);
        });

        return true;
    }

    /**
     * Refresh the checkout page totals.
     * Works for both classic shortcode checkout and WooCommerce block checkout.
     */
    function refreshCheckout() {
        // Classic checkout.
        $(document.body).trigger('update_checkout');

        // Block checkout — invalidate the cart store so it re-fetches from server.
        if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch) {
            try {
                var cartStore = wp.data.dispatch('wc/store/cart');
                if (cartStore && typeof cartStore.invalidateResolutionForStoreSelector === 'function') {
                    cartStore.invalidateResolutionForStoreSelector('getCartData');
                }
            } catch (e) { /* not block checkout */ }
        }
    }

    /**
     * Show cross-sell product suggestions.
     */
    function showSuggestions(suggestions) {
        $suggestions.empty().removeAttr('hidden');

        suggestions.forEach(function (product) {
            var nameHtml = product.permalink
                ? '<a href="' + escAttr(product.permalink) + '" target="_blank" class="aipn-widget__suggestion-name">' + escHtml(product.name) + '</a>'
                : '<span class="aipn-widget__suggestion-name">' + escHtml(product.name) + '</span>';

            var $card = $('<div class="aipn-widget__suggestion-card">' +
                (product.image_url ? '<img src="' + escAttr(product.image_url) + '" alt="' + escAttr(product.name) + '" class="aipn-widget__suggestion-img" />' : '') +
                '<div class="aipn-widget__suggestion-info">' +
                    nameHtml +
                    '<span class="aipn-widget__suggestion-price">' +
                        '<del>' + data.currency + product.regular_price.toFixed(2) + '</del> ' +
                        '<strong>' + data.currency + product.special_price.toFixed(2) + '</strong>' +
                    '</span>' +
                '</div>' +
                '<button type="button" class="aipn-widget__suggestion-add" data-product-id="' + product.product_id + '">' +
                    t('addToCart') +
                '</button>' +
            '</div>');

            $card.find('.aipn-widget__suggestion-add').on('click', function () {
                addSuggestionToCart(product.product_id, product.special_price, $(this));
            });

            $suggestions.append($card);
        });
    }

    /**
     * Add a suggested product to cart via our custom REST endpoint (preserves special price).
     */
    function addSuggestionToCart(productId, specialPrice, $btn) {
        $btn.prop('disabled', true).text('...');

        apiCall('add-suggestion', {
            product_id: productId,
            special_price: specialPrice
        }, function (res) {
            if (res && res.success) {
                $btn.text('Added!').addClass('aipn-widget__suggestion-added');
                appendMessage(t('cartUpdated'), 'system');
                refreshCheckout();
            } else {
                // Server returned 200 but success is false/missing.
                $btn.prop('disabled', false).text(t('addToCart'));
                appendMessage(t('errorGeneric'), 'system');
            }
        }, function (errorMsg) {
            $btn.prop('disabled', false).text(t('addToCart'));
            appendMessage(errorMsg || t('errorGeneric'), 'system');
        });
    }

    /**
     * Append a message to the chat.
     */
    function appendMessage(text, role) {
        var roleClass = 'aipn-widget__msg--' + role;
        // Escape HTML first for safety, then convert **bold** markdown to <strong>.
        var safeText = escHtml(text).replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        var $msg = $('<div class="aipn-widget__msg ' + roleClass + '">' +
            '<div class="aipn-widget__msg-bubble">' + safeText + '</div>' +
        '</div>');

        $messages.append($msg);

        // Animate in.
        requestAnimationFrame(function () {
            $msg.addClass('aipn-widget__msg--visible');
        });

        scrollToBottom();
    }

    /**
     * Auto-fill checkout billing fields with the captured name and email.
     * Works for both classic shortcode checkout and WooCommerce block checkout.
     */
    function fillCheckoutFields(name, email) {
        // Classic checkout fields.
        var $billingEmail = $('#billing_email');
        if ($billingEmail.length && !$billingEmail.val()) {
            $billingEmail.val(email).trigger('change');
        }

        if (name) {
            var parts = name.split(' ');
            var firstName = parts[0] || '';
            var lastName  = parts.slice(1).join(' ') || '';

            var $firstName = $('#billing_first_name');
            var $lastName  = $('#billing_last_name');

            if ($firstName.length && !$firstName.val()) {
                $firstName.val(firstName).trigger('change');
            }
            if ($lastName.length && !$lastName.val()) {
                $lastName.val(lastName).trigger('change');
            }
        }

        // Block checkout — dispatch to the WC store.
        if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch) {
            try {
                var checkout = wp.data.dispatch('wc/store/checkout');
                if (checkout && typeof checkout.setBillingAddress === 'function') {
                    var addr = { email: email };
                    if (name) {
                        var parts2 = name.split(' ');
                        addr.first_name = parts2[0] || '';
                        addr.last_name  = parts2.slice(1).join(' ') || '';
                    }
                    checkout.setBillingAddress(addr);
                }
            } catch (e) { /* not block checkout */ }
        }
    }

    /**
     * Restore session from server (conversation persistence across page reloads).
     */
    function restoreSession() {
        apiCall('session', null, function (res) {
            if (!res.active && !res.accepted) {
                // No existing session — allow greeting to trigger on first open.
                sessionRestored = false;
                return;
            }

            // Track email state from server.
            if (res.email_captured) {
                emailCaptured = true;
            }

            // Restore conversation messages.
            if (res.conversation && res.conversation.length > 0) {
                res.conversation.forEach(function (msg) {
                    appendMessage(msg.content, msg.role);
                });
            }

            // If deal was already accepted — hide the widget entirely.
            if (res.accepted && res.coupon_code) {
                isAccepted = true;
                $widget.hide();
                return;
            }
        }, function () {
            // Silent fail — allow greeting on first open.
            sessionRestored = false;
        }, 'GET');
    }

    /**
     * Make an API call.
     */
    function apiCall(endpoint, payload, onSuccess, onError, method) {
        method = method || 'POST';

        var settings = {
            url: data.restUrl + '/' + endpoint,
            method: method,
            headers: { 'X-WP-Nonce': data.nonce },
            dataType: 'json'
        };

        if (method === 'POST' && payload) {
            settings.contentType = 'application/json';
            settings.data = JSON.stringify(payload);
        }

        $.ajax(settings).done(function (res) {
            if (typeof onSuccess === 'function') {
                onSuccess(res);
            }
        }).fail(function (xhr) {
            var msg = '';
            try {
                var err = JSON.parse(xhr.responseText);
                msg = err.message || '';
            } catch (e) { /* ignore */ }

            if (typeof onError === 'function') {
                onError(msg);
            }
        });
    }

    /**
     * UI helpers.
     */
    function showTyping() {
        $typing.removeAttr('hidden').attr('aria-hidden', 'false');
        scrollToBottom();
    }

    function hideTyping() {
        $typing.attr('hidden', '').attr('aria-hidden', 'true');
    }

    function setLoading(loading) {
        isLoading = loading;
        $submit.prop('disabled', loading);
    }

    function scrollToBottom() {
        if ($messages.length) {
            $messages.scrollTop($messages[0].scrollHeight);
        }
    }

    function shakeElement($el) {
        $el.addClass('aipn-widget__shake');
        setTimeout(function () { $el.removeClass('aipn-widget__shake'); }, 500);
    }

    function escHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function escAttr(str) {
        return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    // Boot when DOM is ready.
    $(document).ready(init);

})(jQuery);
