(function () {
    'use strict';

    var messageAction = 'pp_permissions_teaser_preview_update';
    var readyAction = 'pp_permissions_teaser_preview_ready';
    var resizedAction = 'pp_permissions_teaser_preview_resized';

    // The parent scales this whole page down to fit the preview panel (see
    // updateTeaserPreviewViewport() in settings.dev.js), sizing the panel to match a fixed
    // "device" height (e.g. 1080px for desktop). A page taller than that would then need its
    // own internal scrollbar on top of the panel's - two scrollbars for one page. Reporting
    // the real rendered height lets the parent size the panel to the actual page instead, so
    // only the panel scrolls.
    function reportContentHeight() {
        if (!window.parent || window.parent === window) {
            return;
        }

        var height = Math.max(
            document.documentElement ? document.documentElement.scrollHeight : 0,
            document.body ? document.body.scrollHeight : 0
        );

        if (height) {
            window.parent.postMessage({ action: resizedAction, height: height }, window.location.origin);
        }
    }

    function getPreviewRoot() {
        return document.querySelector('main, .site-main, #main, #primary') || document.body;
    }

    function getPreviewTitle(root) {
        return root.querySelector('.entry-title, .wp-block-post-title, .page-title, h1');
    }

    function getPreviewContent(root, title) {
        var previewContent = document.getElementById('pp-permissions-theme-teaser-content');

        if (previewContent) {
            return previewContent;
        }

        var contentArea = root.querySelector('.entry-content, .wp-block-post-content, .page-content');
        previewContent = document.createElement('div');
        previewContent.id = 'pp-permissions-theme-teaser-content';

        if (contentArea) {
            contentArea.textContent = '';
            contentArea.appendChild(previewContent);
        } else if (title && title.parentNode) {
            title.parentNode.insertBefore(previewContent, title.nextSibling);
        } else {
            root.appendChild(previewContent);
        }

        return previewContent;
    }

    function getNumber(value, fallback, minimum, maximum) {
        var number = parseInt(value, 10);

        if (isNaN(number)) {
            number = fallback;
        }

        return Math.max(minimum, Math.min(maximum, number));
    }

    function getColor(value, fallback) {
        return /^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i.test(String(value || '')) ? value : fallback;
    }

    var allowedInlineTags = {
        a: true,
        abbr: true,
        acronym: true,
        b: true,
        br: true,
        cite: true,
        code: true,
        del: true,
        em: true,
        i: true,
        mark: true,
        small: true,
        span: true,
        strong: true,
        sub: true,
        sup: true,
        u: true
    };

    var allowedBlockTags = {
        blockquote: true,
        div: true,
        li: true,
        ol: true,
        p: true,
        pre: true,
        ul: true
    };

    var blockedContentTags = {
        button: true,
        embed: true,
        form: true,
        iframe: true,
        input: true,
        math: true,
        object: true,
        option: true,
        script: true,
        select: true,
        style: true,
        svg: true,
        textarea: true
    };

    function isAllowedTag(tagName, inlineOnly) {
        return !!allowedInlineTags[tagName] || (!inlineOnly && !!allowedBlockTags[tagName]);
    }

    function getSafeHref(value) {
        var href = String(value || '');
        var normalized = href.replace(/[\u0000-\u001F\u007F\s]+/g, '');
        var link;

        if (!normalized) {
            return '';
        }

        if (/^(?:data|javascript|vbscript):/i.test(normalized)) {
            return '';
        }

        link = document.createElement('a');
        link.href = href;

        return /^(?:http:|https:|mailto:|tel:)$/i.test(link.protocol) ? href : '';
    }

    function copySafeAttributes(source, target, tagName) {
        var href;
        var rel;
        var targetValue;
        var title;

        if ('a' === tagName) {
            href = getSafeHref(source.getAttribute('href'));

            if (href) {
                target.setAttribute('href', href);
            }

            targetValue = String(source.getAttribute('target') || '');

            if (/^_(?:blank|parent|self|top)$/.test(targetValue)) {
                target.setAttribute('target', targetValue);
            }

            rel = String(source.getAttribute('rel') || '').replace(/[^\w\s-]/g, '').trim();

            if ('_blank' === targetValue) {
                rel = (rel + ' noopener noreferrer').trim();
            }

            if (rel) {
                target.setAttribute('rel', rel);
            }
        }

        if ('a' === tagName || 'abbr' === tagName || 'acronym' === tagName) {
            title = source.getAttribute('title');

            if (title) {
                target.setAttribute('title', title);
            }
        }
    }

    function appendSanitizedNodes(source, target, inlineOnly) {
        Array.prototype.forEach.call(source.childNodes, function (child) {
            var cleanElement;
            var tagName;

            if (3 === child.nodeType) {
                target.appendChild(document.createTextNode(child.nodeValue || ''));
                return;
            }

            if (1 !== child.nodeType) {
                return;
            }

            tagName = child.nodeName.toLowerCase();

            if (blockedContentTags[tagName]) {
                return;
            }

            if (!isAllowedTag(tagName, inlineOnly)) {
                appendSanitizedNodes(child, target, inlineOnly);
                return;
            }

            cleanElement = document.createElement(tagName);
            copySafeAttributes(child, cleanElement, tagName);
            appendSanitizedNodes(child, cleanElement, inlineOnly);
            target.appendChild(cleanElement);
        });
    }

    function sanitizeHtmlFragment(value, inlineOnly) {
        var fragment = document.createDocumentFragment();
        var parsed;

        if (!window.DOMParser) {
            fragment.appendChild(document.createTextNode(String(value || '')));
            return fragment;
        }

        parsed = new window.DOMParser().parseFromString(String(value || ''), 'text/html');
        appendSanitizedNodes(parsed.body || parsed, fragment, !!inlineOnly);

        return fragment;
    }

    function replaceWithSanitizedHtml(element, value, inlineOnly) {
        while (element.firstChild) {
            element.removeChild(element.firstChild);
        }

        element.appendChild(sanitizeHtmlFragment(value, inlineOnly));
    }

    function applyNoticeStyle(content, noticeStyle) {
        if (!content) {
            return;
        }

        content = content.querySelector('.pp-teaser-notice') || content;

        noticeStyle = noticeStyle && typeof noticeStyle === 'object' ? noticeStyle : {};

        var backgroundColor = getColor(noticeStyle.backgroundColor, '#f0f6fc');
        var textColor = getColor(noticeStyle.textColor, '#1d2327');
        var borderColor = getColor(noticeStyle.borderColor, '#0073aa');
        var borderWidth = getNumber(noticeStyle.borderWidth, 4, 0, 20);
        var padding = getNumber(noticeStyle.padding, 15, 0, 50);
        var borderRadius = getNumber(noticeStyle.borderRadius, 0, 0, 50);
        var fontSize = getNumber(noticeStyle.fontSize, 14, 10, 30);
        var allowedPositions = ['left', 'right', 'top', 'bottom', 'all'];
        var borderPosition = allowedPositions.indexOf(noticeStyle.borderPosition) > -1
            ? noticeStyle.borderPosition
            : 'left';

        content.style.padding = padding + 'px';
        content.style.backgroundColor = backgroundColor;
        content.style.color = textColor;
        content.style.margin = '15px 0';
        content.style.fontSize = fontSize + 'px';
        content.style.lineHeight = '1.6';
        content.style.borderRadius = borderRadius + 'px';
        content.style.overflowWrap = 'anywhere';
        content.style.wordBreak = 'break-word';
        content.style.boxSizing = 'border-box';
        content.style.border = '';
        content.style.borderLeft = '';
        content.style.borderRight = '';
        content.style.borderTop = '';
        content.style.borderBottom = '';

        if ('all' === borderPosition) {
            content.style.border = borderWidth + 'px solid ' + borderColor;
        } else {
            content.style.setProperty(
                'border-' + borderPosition,
                borderWidth + 'px solid ' + borderColor
            );
        }
    }

    // [login_form] is this plugin's own placeholder (not a real shortcode) - on the real
    // front end and on the initial preview page load it's swapped server-side for a styled
    // wp_login_form(). This live preview only gets the raw edited text via postMessage, and
    // JS has no way to call wp_login_form(), so it substitutes a static, non-functional
    // mockup instead (disabled fields, no <form>, so a stray click here can't submit a login
    // request from inside the preview iframe).
    function buildLoginFormPreview() {
        var wrapper = document.createElement('div');
        wrapper.className = 'pp-login-form-wrapper';
        wrapper.style.cssText = 'max-width: 360px; margin: 20px auto; padding: 30px; background: #ffffff; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);';

        function field(className, labelText, type) {
            var p = document.createElement('p');
            p.className = className;

            var label = document.createElement('label');
            label.textContent = labelText;

            var input = document.createElement('input');
            input.type = type;
            input.disabled = true;
            input.className = 'input';

            p.appendChild(label);
            p.appendChild(input);

            return p;
        }

        wrapper.appendChild(field('login-username', 'Username or Email Address', 'text'));
        wrapper.appendChild(field('login-password', 'Password', 'password'));

        var rememberField = document.createElement('p');
        rememberField.className = 'login-remember';
        var rememberLabel = document.createElement('label');
        var rememberInput = document.createElement('input');
        rememberInput.type = 'checkbox';
        rememberInput.disabled = true;
        rememberLabel.appendChild(rememberInput);
        rememberLabel.appendChild(document.createTextNode(' Remember Me'));
        rememberField.appendChild(rememberLabel);
        wrapper.appendChild(rememberField);

        var submitField = document.createElement('p');
        submitField.className = 'login-submit';
        var submitInput = document.createElement('input');
        submitInput.type = 'submit';
        submitInput.value = 'Log In';
        submitInput.disabled = true;
        submitField.appendChild(submitInput);
        wrapper.appendChild(submitField);

        var style = document.createElement('style');
        style.textContent = '.pp-login-form-wrapper .login-username,.pp-login-form-wrapper .login-password,'
            + '.pp-login-form-wrapper .login-remember,.pp-login-form-wrapper .login-submit{margin-bottom:15px}'
            + '.pp-login-form-wrapper label{display:block;margin-bottom:5px;font-weight:600;color:#333;font-size:14px}'
            + '.pp-login-form-wrapper input[type="text"],.pp-login-form-wrapper input[type="password"]{width:100%;'
            + 'padding:10px 12px;border:1px solid #ddd;border-radius:4px;font-size:14px;box-sizing:border-box}'
            + '.pp-login-form-wrapper .login-remember label{display:inline;font-weight:normal;margin-left:5px}'
            + '.pp-login-form-wrapper input[type="checkbox"]{margin:0}'
            + '.pp-login-form-wrapper input[type="submit"]{width:100%;padding:12px;background:#0073aa;color:#fff;'
            + 'border:none;border-radius:4px;font-size:14px;font-weight:600}'
            + '.pp-login-form-wrapper .login-submit{margin-bottom:0}';
        wrapper.insertBefore(style, wrapper.firstChild);

        return wrapper;
    }

    function replaceLoginFormPlaceholder(root) {
        if (!root) {
            return;
        }

        var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null, false);
        var textNodes = [];
        var node;

        while ((node = walker.nextNode())) {
            if (node.nodeValue.indexOf('[login_form]') !== -1) {
                textNodes.push(node);
            }
        }

        textNodes.forEach(function (textNode) {
            var segments = textNode.nodeValue.split('[login_form]');
            var fragment = document.createDocumentFragment();

            segments.forEach(function (segment, index) {
                if (segment) {
                    fragment.appendChild(document.createTextNode(segment));
                }

                if (index < segments.length - 1) {
                    fragment.appendChild(buildLoginFormPreview());
                }
            });

            textNode.parentNode.replaceChild(fragment, textNode);
        });
    }

    function applyPreview(payload) {
        if (!payload || typeof payload !== 'object') {
            return;
        }

        var root = getPreviewRoot();
        var title = getPreviewTitle(root);
        var content = getPreviewContent(root, title);

        // Teaser Text preserves formatting (bold/italic/etc.) on the front end, so the
        // preview renders the payload as HTML to match, not as escaped plain text.
        if (title && typeof payload.title === 'string') {
            replaceWithSanitizedHtml(title, payload.title, true);
        }

        if (content && typeof payload.content === 'string') {
            replaceWithSanitizedHtml(content, payload.content, false);
            replaceLoginFormPlaceholder(content);
        }

        applyNoticeStyle(content, payload.noticeStyle);

        var images = root.querySelectorAll('.post-thumbnail, .wp-block-post-featured-image, img.wp-post-image');
        Array.prototype.forEach.call(images, function (image) {
            image.style.display = payload.hideThumbnail ? 'none' : '';
        });

        var comments = root.querySelectorAll('.comments-area, .wp-block-comments');
        Array.prototype.forEach.call(comments, function (commentArea) {
            commentArea.style.display = payload.disableComments ? 'none' : '';
        });

        reportContentHeight();
    }

    window.addEventListener('message', function (event) {
        if (event.origin !== window.location.origin || !event.data || event.data.action !== messageAction) {
            return;
        }

        applyPreview(event.data.payload);
    });

    if (window.location.hash.indexOf('#pp_permissions_teaser=') === 0) {
        try {
            applyPreview(JSON.parse(decodeURIComponent(window.location.hash.substring(23))));
        } catch (error) {
            // Keep the server-rendered preview if the URL fragment is incomplete or invalid.
        }
    }

    if (window.parent && window.parent !== window) {
        window.parent.postMessage({ action: readyAction }, window.location.origin);
    }

    reportContentHeight();
    // Images/fonts finishing after this script runs (it's footer-enqueued, close to the load
    // event but not guaranteed after it) can still grow the page - one more report once
    // everything has actually settled.
    window.addEventListener('load', reportContentHeight);
}());
