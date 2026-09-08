(function () {
    'use strict';

    var messageAction = 'pp_permissions_teaser_preview_update';
    var readyAction = 'pp_permissions_teaser_preview_ready';

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
        previewContent.className = 'pp-teaser-notice';

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
}());
