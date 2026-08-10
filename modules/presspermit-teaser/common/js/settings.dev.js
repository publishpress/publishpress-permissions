jQuery(document).ready(function ($) {
    // Tabs
    var $tabsWrapper = $('#publishpress-permissions-teaser-tabs');
    $tabsWrapper.find('li').click(function (e) {
        e.preventDefault();
        $tabsWrapper.children('li').filter('.nav-tab-active').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        var panel = $(this).find('a').first().attr('href');

        $('section[id^="ppp-"]').hide();
        $(panel).show();

        var current_tab = $(this).find('a').attr('href').replace('#','');
        $('#current_tab').val(current_tab);
    });

    // Expand / Collapse teaser custom code
    $('.ppp-expand-code').bind('click', function(e) {
        e.preventDefault();
        var codeArea = $(this).closest('.ppp-code-sample').find('textarea');
        if( $(this).attr('data-expand') === 'closed' ) {
            codeArea.css( 'height', codeArea[0].scrollHeight );
            $(this).attr('data-expand', 'opened');
            $(this).find('.ppp-expand-msg').hide();
            $(this).find('.ppp-collapse-msg').show();
        } else {
            codeArea.css( 'height', 200 );
            $(this).attr('data-expand', 'closed');
            $(this).find('.ppp-collapse-msg').hide();
            $(this).find('.ppp-expand-msg').show();
        }
    });

    // Copy teaser custom code
    $('.ppp-copy-code').bind('click', function(e) {
        e.preventDefault();
        var codeArea = $(this).closest('.ppp-code-sample').find('textarea');
        codeArea.select();
        document.execCommand('copy');
        if( $(this).attr('data-copy') === 'uncopied' ) {
            $(this).find('.ppp-uncopied-msg').hide();
            $(this).find('.ppp-copied-msg').show();
        } else {
            $(this).attr('data-expand', 'closed');
            $(this).find('.ppp-copied-msg').hide();
            $(this).find('.ppp-uncopied-msg').show();
        }
    });

    // Search posts
    $('.permissions_select_posts').select2( {
        placeholder: presspermitTeaser.strings.select_a_page,
        allowClear: true,
        ajax: {
            url: presspermitTeaser.url,
            dataType: 'json',
            method: 'get',
            delay: 250,
            data: function (params) {
                var postType = $(this).attr('data-post-type') || 'page';
                
                return {
                    search: params.term,
                    action: 'pp_search_posts',
                    post_type: postType,
                    nonce: presspermitTeaser.nonce
                }
            },
            processResults: function( data ) {
                var options = [];
    			if ( data ) {

                    $.each( data, function( index, item ) {
    					options.push( { id: item.ID, text: item.post_title  } );
    				});

                    return {
        				results: options
        			};
    			}
            },
        }
    } );
    
    // Handle post type selector change
    $(document).on('change', '.teaser-redirect-post-type', function() {
        var newPostType = $(this).val();
        var targetSelectId = $(this).attr('data-target-select');
        var $targetSelect = $('#' + targetSelectId);
        
        // Update the data-post-type attribute
        $targetSelect.attr('data-post-type', newPostType);
        
        // Clear the current selection
        $targetSelect.val(null).trigger('change');
        
        // Reinitialize select2 to use the new post type
        $targetSelect.select2('destroy');
        $targetSelect.select2({
            placeholder: presspermitTeaser.strings.select_a_page,
            allowClear: true,
            ajax: {
                url: presspermitTeaser.url,
                dataType: 'json',
                method: 'get',
                delay: 250,
                data: function (params) {
                    return {
                        search: params.term,
                        action: 'pp_search_posts',
                        post_type: newPostType,
                        nonce: presspermitTeaser.nonce
                    }
                },
                processResults: function( data ) {
                    var options = [];
                    if ( data ) {
                        $.each( data, function( index, item ) {
                            options.push( { id: item.ID, text: item.post_title  } );
                        });
                        return {
                            results: options
                        };
                    }
                },
            }
        });
    });

    // Search terms
    $('.permissions_select_terms').select2( {
        placeholder: presspermitTeaser.strings.select_terms,
        ajax: {
            url: presspermitTeaser.url,
            dataType: 'json',
            method: 'get',
            delay: 250,
            data: function (params) {
                return {
                    search: params.term,
                    action: 'pp_search_terms',
                    taxonomy: $('#teaser_hide_links_taxonomy').val(),
                    nonce: presspermitTeaser.nonce
                }
            },
            processResults: function( data ) {
                var options = [];
    			if ( data ) {
                    $.each( data, function( index, item ) {
    					options.push( { id: item.term_id, text: item.name  } );
    				});

                    return {
        				results: options
        			};
    			}
            },
        }
    } );

    $('div.teaser-coverage-post select').on('change', function () {
        $(this).siblings('span.teaser-num-chars').toggle($(this).val() == 'x_chars');
    });

    $('#ppp-tab-redirect select.teaser-redirect-mode').on('change', function() {
        $(this).parent('td').siblings('td').find('div.pp-select-dynamic-wrapper').toggle($(this).val() == '(select)');
    });

    // Expandable Row Functionality
    // Handle expand/collapse icon click
    $(document).on('click', '.pp-expand-icon', function (e) {
        e.preventDefault();
        e.stopPropagation();

        var $icon = $(this);
        var $mainRow = $icon.closest('tr.pp-main-row');
        var postType = $mainRow.data('post-type');
        var $detailRow = $('.pp-detail-' + postType);

        if ($detailRow.is(':visible')) {
            // Collapse
            $icon.removeClass('pp-expanded');
            $mainRow.removeClass('pp-expanded');
            setTimeout(function() {
                $detailRow.toggle();
                $detailRow.removeClass("expanded");
            }, 150);
        } else {
            // Expand
            $icon.addClass('pp-expanded');
            $mainRow.addClass('pp-expanded');
            setTimeout(function() {
                $detailRow.toggle();
                $detailRow.addClass("expanded");
            }, 150);
        }
    });

    // Handle teaser type change - show/hide expand icon and detail row
    $(document).on('change', 'select.teaser-type-select', function () {
        var $select = $(this);
        var $mainRow = $select.closest('tr.pp-main-row');
        var postType = $mainRow.data('post-type');
        var $expandIcon = $mainRow.find('.pp-expand-icon');
        var $detailRow = $('.pp-detail-' + postType);
        var $userApplicationDiv = $mainRow.find('div.teaser_vspace');
        var $numCharsSpan = $select.siblings('span.teaser-num-chars');

        var isEnabled = $select.val() != '0';

        // Show/hide expand icon
        $expandIcon.toggle(isEnabled);

        // Show/hide user application radio buttons
        $userApplicationDiv.toggle(isEnabled);

        // Show/hide num chars input
        $numCharsSpan.toggle($select.val() == 'x_chars');

        // Update row active state
        $mainRow.attr('data-row-active', isEnabled ? '1' : '0');

        // If disabled, collapse the detail row
        if (!isEnabled) {
            $expandIcon.removeClass('pp-expanded');
            $mainRow.removeClass('pp-expanded');
            $detailRow.slideUp(200);
        }

        // Update header column visibility
        var ppAnyTeaserTypesEnabled = $('select.teaser-type-select option:selected[value!="0"]').length;
        $('th.pp-teaser-user-application span').toggle(ppAnyTeaserTypesEnabled > 0);
    });

    // Handle redirect dropdown change in the separate redirect section
    function updateRedirectTargetColumnsVisibility($section) {
        var $redirectModes = $section.find('select.teaser-redirect-mode');
        var allNoRedirect = $redirectModes.length && $redirectModes.filter(function () {
            return $(this).val() === '(select)';
        }).length === 0;

        $section.find('th').filter(function () {
            return !!$(this).data('title');
        }).each(function () {
            var $th = $(this);
            var titleText = $th.data('title');
            $th.text(allNoRedirect ? '' : titleText);
        });
    }

    $('.teaser-redirect-section select.teaser-redirect-mode').on('change', function() {
        $(this).parent('td').siblings('td').find('div.pp-select-dynamic-wrapper').toggle($(this).val() == '(select)');
        updateRedirectTargetColumnsVisibility($(this).closest('.teaser-redirect-section'));
    });

    // Initialize redirect target columns visibility on page load
    $('.teaser-redirect-section').each(function () {
        updateRedirectTargetColumnsVisibility($(this));
    });

    // ========================================================================
    // Progressive Disclosure UI - Teaser Settings
    // ========================================================================

    function getTeaserSitePreview($container) {
        var postType = String($container.data('post-type') || '');

        return $('.pp-teaser-site-preview').filter(function() {
            return String($(this).data('post-type') || '') === postType;
        }).first();
    }

    function getTeaserOptionsContainer($container) {
        var postType = String($container.data('post-type') || '');

        return $('.pp-teaser-options-container').filter(function() {
            return String($(this).data('post-type') || '') === postType;
        }).first();
    }

    function getTeaserContentContainer($container) {
        var postType = String($container.data('post-type') || '');

        return $('.pp-teaser-content-container').filter(function() {
            return String($(this).data('post-type') || '') === postType;
        }).first();
    }

    function getTeaserSettingsContainer($element) {
        var $postTypeContainer = $element.closest('[data-post-type]');
        var postType = String($postTypeContainer.data('post-type') || '');

        return $('.pp-teaser-settings-container').filter(function() {
            return String($(this).data('post-type') || '') === postType;
        }).first();
    }

    function getTeaserNoticePreviews($container) {
        return $container.find('.pp-teaser-notice-preview')
            .add(getTeaserSitePreview($container).find('.pp-teaser-notice-preview'));
    }

    function escapePreviewAttribute(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function updateExternalPreviewLink($sitePreview, teaserType) {
        var $link = $sitePreview.find('.pp-teaser-preview-external-link');
        var defaultPreviewUrl = String($link.data('default-preview-url') || '');
        var teaserPreviewUrl = String($link.data('teaser-preview-url') || '');
        var previousObjectUrl = $link.data('preview-object-url');

        if (!$link.length) {
            return;
        }

        if (previousObjectUrl && window.URL && window.URL.revokeObjectURL) {
            window.URL.revokeObjectURL(previousObjectUrl);
            $link.removeData('preview-object-url');
        }

        if (teaserType === '0') {
            $link.attr('href', defaultPreviewUrl);
            return;
        }

        if (teaserType === '1' && teaserPreviewUrl) {
            var teaserPayload = $sitePreview.data('theme-teaser-payload') || {};

            $link.attr(
                'href',
                teaserPreviewUrl + '#pp_permissions_teaser=' + encodeURIComponent(JSON.stringify(teaserPayload))
            );
            return;
        }

        if (!window.Blob || !window.URL || !window.URL.createObjectURL) {
            $link.attr('href', defaultPreviewUrl);
            return;
        }

        var activeStateSelector = teaserType === 'redirect'
            ? '.pp-teaser-preview-redirect-response'
            : '.pp-teaser-preview-content';
        var $article = $sitePreview.find('.pp-teaser-preview-article').clone();

        $article
            .removeAttr('aria-live')
            .find('.pp-teaser-preview-state')
            .not(activeStateSelector)
            .remove();

        $article.find(activeStateSelector).css(
            'display',
            teaserType === 'redirect' ? 'flex' : 'block'
        );
        $article.find('[aria-live]').removeAttr('aria-live');

        var stylesheetUrl = String($sitePreview.data('preview-stylesheet-url') || '');
        var documentTitle = String($sitePreview.data('preview-document-title') || '');
        var previewDocument = '<!doctype html>'
            + '<html><head><meta charset="utf-8">'
            + '<meta name="viewport" content="width=device-width, initial-scale=1">'
            + '<title>' + escapePreviewAttribute(documentTitle) + '</title>'
            + '<link rel="stylesheet" href="' + escapePreviewAttribute(stylesheetUrl) + '">'
            + '<style>'
            + 'html,body{margin:0;min-height:100%;}'
            + 'body{box-sizing:border-box;padding:32px;background:#f0f0f1;}'
            + '.pp-teaser-preview-article{box-sizing:border-box;width:100%;min-height:0;'
            + 'max-width:none;margin:0 auto;border:1px solid #c3c4c7;'
            + 'box-shadow:0 5px 18px rgba(0,0,0,.08);}'
            + '@media(max-width:782px){body{padding:16px;}}'
            + '</style></head><body>'
            + $article.prop('outerHTML')
            + '</body></html>';
        var objectUrl = window.URL.createObjectURL(
            new window.Blob([previewDocument], { type: 'text/html' })
        );

        $link
            .attr('href', objectUrl)
            .data('preview-object-url', objectUrl);
    }

    function postThemeTeaserPayload($sitePreview) {
        var $frame = $sitePreview.find('.pp-teaser-preview-theme-frame');
        var frame = $frame.get(0);
        var payload = $sitePreview.data('theme-teaser-payload');

        if (!frame || !frame.contentWindow || !payload) {
            return;
        }

        frame.contentWindow.postMessage(
            {
                action: 'pp_permissions_teaser_preview_update',
                payload: payload
            },
            window.location.origin
        );
    }

    function loadThemePreview($sitePreview, teaserType) {
        var $frame = $sitePreview.find('.pp-teaser-preview-theme-frame');
        var previewMode = teaserType === '1' ? 'teaser' : 'default';
        var previewUrl = previewMode === 'teaser'
            ? $frame.data('teaser-src')
            : $frame.data('default-src');

        if (!$frame.length || !previewUrl) {
            return;
        }

        if ($frame.data('preview-mode') === previewMode && $frame.attr('src')) {
            if (previewMode === 'teaser') {
                postThemeTeaserPayload($sitePreview);
            }
            return;
        }

        $frame
            .data('preview-mode', previewMode)
            .removeClass('is-loaded')
            .attr('aria-busy', 'true');
        $sitePreview.find('.pp-teaser-preview-theme-loading').show();
        $sitePreview.find('.pp-teaser-preview-theme-error').prop('hidden', true);

        $frame
            .off('.ppThemePreview')
            .one('load.ppThemePreview', function() {
                $(this)
                    .attr('aria-busy', 'false')
                    .addClass('is-loaded');
                $sitePreview.find('.pp-teaser-preview-theme-loading').hide();

                if (previewMode === 'teaser') {
                    postThemeTeaserPayload($sitePreview);
                }
            })
            .one('error.ppThemePreview', function() {
                $(this).attr('aria-busy', 'false');
                $sitePreview.find('.pp-teaser-preview-theme-loading').hide();
                $sitePreview.find('.pp-teaser-preview-theme-error').prop('hidden', false);
            })
            .attr('src', previewUrl);
    }

    $(window).on('message.ppPermissionsTeaserPreview', function(event) {
        var originalEvent = event.originalEvent;

        if (!originalEvent || originalEvent.origin !== window.location.origin
            || !originalEvent.data
            || originalEvent.data.action !== 'pp_permissions_teaser_preview_ready'
        ) {
            return;
        }

        $('.pp-teaser-site-preview').each(function() {
            var $sitePreview = $(this);
            var frame = $sitePreview.find('.pp-teaser-preview-theme-frame').get(0);

            if (frame && frame.contentWindow === originalEvent.source) {
                postThemeTeaserPayload($sitePreview);
                return false;
            }
        });
    });

    function htmlToPlainText(value) {
        var tempDiv = document.createElement('div');

        tempDiv.innerHTML = value || '';

        return (tempDiv.textContent || tempDiv.innerText || '').trim();
    }

    function getEditorPlainText($container, optionName) {
        var postType = String($container.data('post-type') || '');
        var editorId = postType + '_' + optionName;
        var editorElement = document.getElementById(editorId);
        var value = '';

        if (!editorElement) {
            return '';
        }

        if (typeof tinymce !== 'undefined' && tinymce.get(editorId) && !tinymce.get(editorId).isHidden()) {
            value = tinymce.get(editorId).getContent();
        } else {
            value = $(editorElement).val();
        }

        return htmlToPlainText(value);
    }

    function getTeaserStyleNumber($container, optionName, fallback, minimum, maximum) {
        var value = parseInt($container.find('[name*="' + optionName + '"]').val(), 10);

        if (isNaN(value)) {
            value = fallback;
        }

        return Math.max(minimum, Math.min(maximum, value));
    }

    function getTeaserStyleColor($container, optionName, fallback) {
        var value = String($container.find('[name*="' + optionName + '"]').val() || '');

        return /^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i.test(value) ? value : fallback;
    }

    function getTeaserNoticeStyle($container) {
        var useCustomStyle = String(
            $container.find('.pp-teaser-notice-style-select').val() || 'default'
        ) === 'custom';
        var borderPosition = useCustomStyle
            ? String($container.find('[name*="teaser_notice_border_position"]').val() || 'left')
            : 'left';

        if (['left', 'right', 'top', 'bottom', 'all'].indexOf(borderPosition) === -1) {
            borderPosition = 'left';
        }

        return {
            backgroundColor: useCustomStyle
                ? getTeaserStyleColor($container, 'teaser_notice_bg_color', '#f0f6fc')
                : '#f0f6fc',
            textColor: useCustomStyle
                ? getTeaserStyleColor($container, 'teaser_notice_text_color', '#1d2327')
                : '#1d2327',
            borderColor: useCustomStyle
                ? getTeaserStyleColor($container, 'teaser_notice_border_color', '#0073aa')
                : '#0073aa',
            borderWidth: useCustomStyle
                ? getTeaserStyleNumber($container, 'teaser_notice_border_width', 4, 0, 20)
                : 4,
            borderPosition: borderPosition,
            padding: useCustomStyle
                ? getTeaserStyleNumber($container, 'teaser_notice_padding', 15, 0, 50)
                : 15,
            borderRadius: useCustomStyle
                ? getTeaserStyleNumber($container, 'teaser_notice_border_radius', 0, 0, 50)
                : 0,
            fontSize: useCustomStyle
                ? getTeaserStyleNumber($container, 'teaser_notice_font_size', 14, 10, 30)
                : 14
        };
    }

    function updateTeaserSitePreview($container, teaserType, messageText) {
        var $sitePreview = getTeaserSitePreview($container);
        var $optionsContainer = getTeaserOptionsContainer($container);

        if (!$sitePreview.length) {
            return;
        }

        teaserType = String(teaserType);

        var $article = $sitePreview.find('.pp-teaser-preview-article');
        var $image = $sitePreview.find('.pp-teaser-preview-image');
        var $title = $sitePreview.find('.pp-teaser-preview-title');
        var $body = $sitePreview.find('.pp-teaser-preview-body');
        var $notice = $sitePreview.find('.pp-teaser-notice-preview');
        var $states = $sitePreview.find('.pp-teaser-preview-state');
        var suffix = '_anon';
        var titleText = String($sitePreview.data('sample-title') || '');
        var titlePrefix = getEditorPlainText($container, 'tease_prepend_name' + suffix);
        var titleSuffix = getEditorPlainText($container, 'tease_append_name' + suffix);
        var contentPrefix = getEditorPlainText($container, 'tease_prepend_content' + suffix);
        var contentSuffix = getEditorPlainText($container, 'tease_append_content' + suffix);
        var hideThumbnail = String(
            $optionsContainer.find('input[name^="teaser_hide_thumbnail"]:checked').val() || '0'
        ) === '1';
        var disableComments = $optionsContainer
            .find('input[name^="teaser_disable_comments"][type="checkbox"]')
            .is(':checked');

        $states.hide();
        $image.hide();
        $title.hide();
        $notice.hide();

        if (teaserType === '0') {
            $sitePreview.find('.pp-teaser-preview-default-response').show();
            loadThemePreview($sitePreview, teaserType);

            $article.attr('data-preview-state', 'default');
            $sitePreview.addClass('is-ready');
            updateExternalPreviewLink($sitePreview, teaserType);
            return;
        }

        if (teaserType === '1') {
            titleText = [titlePrefix, titleText, titleSuffix].filter(Boolean).join(' ');
            var teaserContent = getEditorPlainText($container, 'tease_replace_content' + suffix);

            if (!teaserContent) {
                teaserContent = messageText;
            }

            $sitePreview.data('theme-teaser-payload', {
                title: titleText,
                content: [contentPrefix, teaserContent, contentSuffix].filter(Boolean).join(' '),
                hideThumbnail: hideThumbnail,
                disableComments: disableComments,
                noticeStyle: getTeaserNoticeStyle($container)
            });
            $sitePreview.find('.pp-teaser-preview-default-response').show();
            $article.attr('data-preview-state', 'theme-teaser');
            $sitePreview.addClass('is-ready');
            loadThemePreview($sitePreview, teaserType);
            updateExternalPreviewLink($sitePreview, teaserType);
            return;
        }

        if (teaserType === 'redirect') {
            $sitePreview.find('.pp-teaser-preview-redirect-response').css('display', 'flex');
            $article.attr('data-preview-state', 'redirect');
            $sitePreview.addClass('is-ready');
            updateExternalPreviewLink($sitePreview, teaserType);
            return;
        }

        titleText = [titlePrefix, titleText, titleSuffix].filter(Boolean).join(' ');
        $title.text(titleText).show();
        $image.toggle(!hideThumbnail);

        var baseContent = '';

        if (teaserType === 'excerpt' || teaserType === 'read_more' || teaserType === 'more') {
            baseContent = String($sitePreview.data('sample-excerpt') || '');
        } else if (teaserType === 'x_chars') {
            var fullContent = String($sitePreview.data('sample-content') || '');
            var charLimit = parseInt($container.find('input[name^="x_chars_num_chars"]').val(), 10);

            if (!charLimit || charLimit < 1) {
                charLimit = 50;
            }

            baseContent = fullContent.substring(0, charLimit);

            if (fullContent.length > charLimit) {
                baseContent += '...';
            }
        } else {
            baseContent = String($sitePreview.data('sample-content') || '');
        }

        $body.text([contentPrefix, baseContent, contentSuffix].filter(Boolean).join(' '));
        $sitePreview.find('.pp-teaser-preview-content').show();

        if (teaserType !== '1') {
            $notice.text(messageText).show();
        }

        $article.attr('data-preview-state', 'teaser');
        $sitePreview.addClass('is-ready');
        updateExternalPreviewLink($sitePreview, teaserType);
    }

    // Function to update preview text based on teaser type
    function updateTeaserPreviewText($container) {
        var teaserType = $container.find('.pp-teaser-type-select').val();
        var $preview = getTeaserNoticePreviews($container);
        
        if (!$preview.length) {
            return; // No preview element found
        }
        
        var messageText = '';
        
        if (teaserType == '1') {
            messageText = getEditorPlainText($container, 'tease_replace_content_anon');
            messageText = messageText || $preview.first().data('teaser-text-anon') || '';
        } else if (teaserType == 'read_more') {
            messageText = $preview.first().data('read-more-msg');
        } else if (teaserType == 'excerpt') {
            messageText = $preview.first().data('excerpt-msg');
        } else if (teaserType == 'x_chars' || teaserType == 'more') {
            messageText = $preview.first().data('x-chars-msg');
        }
        
        // Fallback to default if empty
        if (!messageText) {
            messageText = $preview.first().data('teaser-text-default')
                || 'You do not have permission to view the full content.';
        }
        
        $preview.text(messageText);
        updateTeaserSitePreview($container, teaserType, messageText);
    }

    // Function to update teaser settings visibility based on selected type
    function updateTeaserSettings(selectedType, $container) {
        var $optionsContainer = getTeaserOptionsContainer($container);
        var $contentContainer = getTeaserContentContainer($container);

        // Show/hide number input based on type
        if (selectedType == 'x_chars') {
            $container.find('.pp-num-chars-setting').fadeIn(300);
            $container.find('.pp-excerpt-chars-setting').fadeOut(300);
        } else if (selectedType == 'excerpt') {
            $container.find('.pp-excerpt-chars-setting').fadeIn(300);
            $container.find('.pp-num-chars-setting').fadeOut(300);
        } else {
            $container.find('.pp-num-chars-setting').fadeOut(300);
            $container.find('.pp-excerpt-chars-setting').fadeOut(300);
        }

        // Show/hide Teaser Notice Style field based on teaser type
        // Hide for redirect and no teaser, show for all other types
        var $teaserNoticeStyleRow = $container.find('select[name^="teaser_notice_style_mode"]').closest('tr');
        var $teaserNoticeStyleCard = $container.find('.pp-teaser-notice-style-settings');
        if (selectedType == '0' || selectedType == 'redirect') {
            $teaserNoticeStyleCard.hide();
            $teaserNoticeStyleRow.hide();
        } else {
            $teaserNoticeStyleRow.show();
            var $teaserNoticeStyleValue = $teaserNoticeStyleRow.find('select[name^="teaser_notice_style_mode"]').val();
            if ($teaserNoticeStyleValue === 'custom') {
                $teaserNoticeStyleCard.show();
            } else {
                $teaserNoticeStyleCard.hide();
            }
        }

        // Hide all notice cards first for smooth transition
        var $noticeCards = $container.find('.pp-read-more-notice-card, .pp-excerpt-notice-card, .pp-x-chars-notice-card');
        
        // Show/hide sections based on teaser type
        if (selectedType == '0') {
            // No Teaser: hide everything
            $optionsContainer.find('.pp-teaser-application-fields').slideUp(300);
            $contentContainer.find('.pp-teaser-text-card').slideUp(300);
            $container.find('.pp-teaser-redirect-settings').slideUp(300);
            $noticeCards.stop(true, false).fadeOut(250);
        } else if (selectedType == 'redirect') {
            // Redirect: show only redirect settings and application fields
            $optionsContainer.find('.pp-teaser-application-fields').slideDown(300);
            $contentContainer.find('.pp-teaser-text-card').slideUp(300);
            $container.find('.pp-teaser-redirect-settings').slideDown(300);
            $noticeCards.stop(true, false).fadeOut(250);
        } else if (selectedType == '1') {
            // Teaser Text: show teaser text card and application fields, hide redirect
            $optionsContainer.find('.pp-teaser-application-fields').slideDown(300);
            $contentContainer.find('.pp-teaser-text-card').slideDown(300);
            $container.find('.pp-teaser-redirect-settings').slideUp(300);
            $noticeCards.stop(true, false).fadeOut(250);
        } else if (selectedType == 'read_more') {
            // Read More: show read more notice and application fields
            $optionsContainer.find('.pp-teaser-application-fields').slideDown(300);
            $contentContainer.find('.pp-teaser-text-card').slideUp(300);
            $container.find('.pp-teaser-redirect-settings').slideUp(300);
            // Hide other notice cards first, then show read more notice
            $noticeCards.not('.pp-read-more-notice-card').stop(true, false).fadeOut(250);
            $container.find('.pp-read-more-notice-card').stop(true, false).delay(250).fadeIn(300);
        } else if (selectedType == 'excerpt') {
            // Excerpt: show excerpt notice and application fields
            $optionsContainer.find('.pp-teaser-application-fields').slideDown(300);
            $contentContainer.find('.pp-teaser-text-card').slideUp(300);
            $container.find('.pp-teaser-redirect-settings').slideUp(300);
            // Hide other notice cards first, then show excerpt notice
            $noticeCards.not('.pp-excerpt-notice-card').stop(true, false).fadeOut(250);
            $container.find('.pp-excerpt-notice-card').stop(true, false).delay(250).fadeIn(300);
        } else if (selectedType == 'x_chars' || selectedType == 'more') {
            // X Chars or More: show x chars notice and application fields
            $optionsContainer.find('.pp-teaser-application-fields').slideDown(300);
            $contentContainer.find('.pp-teaser-text-card').slideUp(300);
            $container.find('.pp-teaser-redirect-settings').slideUp(300);
            // Hide other notice cards first, then show x chars notice
            $noticeCards.not('.pp-x-chars-notice-card').stop(true, false).fadeOut(250);
            $container.find('.pp-x-chars-notice-card').stop(true, false).delay(250).fadeIn(300);
        } else {
            // Other teaser types: show application fields only
            $optionsContainer.find('.pp-teaser-application-fields').slideDown(300);
            $contentContainer.find('.pp-teaser-text-card').slideUp(300);
            $container.find('.pp-teaser-redirect-settings').slideUp(300);
            $noticeCards.stop(true, false).fadeOut(250);
        }
        
        // Update preview text based on new teaser type
        updateTeaserPreviewText($container);
    }

    // Post type selector - switch between different post type settings
    $(document).on('change', '.pp-current-post-type', function() {
        var selectedType = $(this).val();
        
        // Save selected post type to hidden field for persistence
        $('#selected_post_type').val(selectedType);
        $('.pp-current-post-type').val(selectedType);
        
        // Hide all containers
        $('.pp-teaser-settings-container').removeClass('active').hide();
        $('.pp-teaser-content-container').removeClass('active').hide();
        $('.pp-teaser-options-container').removeClass('active').hide();
        $('.pp-teaser-preview-container').removeClass('active').hide();
        
        // Show selected container with animation
        var $selectedContainer = $('.pp-teaser-settings-container[data-post-type="' + selectedType + '"]');
        $selectedContainer.addClass('active pp-fade-in').show();
        $('.pp-teaser-content-container[data-post-type="' + selectedType + '"]')
            .addClass('active pp-fade-in')
            .show();
        $('.pp-teaser-options-container[data-post-type="' + selectedType + '"]')
            .addClass('active pp-fade-in')
            .show();
        $('.pp-teaser-preview-container[data-post-type="' + selectedType + '"]')
            .addClass('active pp-fade-in')
            .show();

        // Check the teaser type of the selected post type and show/hide sections
        var teaserType = $selectedContainer.find('.pp-teaser-type-select').val();

        updateTeaserSettings(teaserType, $selectedContainer);
    });

    // Teaser type select dropdown - show/hide conditional settings
    $(document).on('change', '.pp-teaser-type-select', function() {
        var $container = $(this).closest('.pp-teaser-settings-container');
        var selectedType = $(this).val();
        var postType = $container.data('post-type');

        updateTeaserSettings(selectedType, $container);
    });

    $(document).on(
        'input change',
        'input[name^="teaser_hide_thumbnail"], input[name^="teaser_disable_comments"], input[name^="x_chars_num_chars"], input[name^="excerpt_num_chars"]',
        function() {
            var $container = getTeaserSettingsContainer($(this));

            updateTeaserPreviewText($container);
        }
    );

    // Initialize visibility on page load for progressive disclosure UI
    function initializeProgressiveUIVisibility() {
        // Check if there's a previously selected post type
        var savedPostType = $('#selected_post_type').val();
        var $container;
        
        if (savedPostType && $('.pp-teaser-settings-container[data-post-type="' + savedPostType + '"]').length) {
            // Restore previously selected post type
            $container = $('.pp-teaser-settings-container[data-post-type="' + savedPostType + '"]');
            $('.pp-current-post-type').val(savedPostType);
        } else {
            // Show first post type by default
            $container = $('.pp-teaser-settings-container').first();
            if ($container.length) {
                // Try to find the first enabled option in the post type selector
                var $firstEnabledOption = $('.pp-current-post-type').first().find('option:not(:disabled)').first();
                var firstPostType = $firstEnabledOption.length ? $firstEnabledOption.val() : $container.data('post-type');
                $('.pp-current-post-type').val(firstPostType);
                $('#selected_post_type').val(firstPostType);
            }
        }

        if ($container && $container.length) {
            $container.addClass('active').show();
            var activePostType = String($container.data('post-type') || '');

            $('.pp-teaser-options-container').removeClass('active').hide();
            $('.pp-teaser-options-container').filter(function() {
                return String($(this).data('post-type') || '') === activePostType;
            }).addClass('active').show();

            $('.pp-teaser-content-container').removeClass('active').hide();
            $('.pp-teaser-content-container').filter(function() {
                return String($(this).data('post-type') || '') === activePostType;
            }).addClass('active').show();

            $('.pp-teaser-preview-container').removeClass('active').hide();
            $('.pp-teaser-preview-container').filter(function() {
                return String($(this).data('post-type') || '') === activePostType;
            }).addClass('active').show();

            var selectedType = $container.find('.pp-teaser-type-select').val();

            updateTeaserSettings(selectedType, $container);
        }
    }

    // Run progressive UI initialization
    initializeProgressiveUIVisibility();

    // Redirect settings handlers for progressive UI
    $('#teaser_redirect_anon').on('change', function() {
        var $pageSelect = $('#teaser_redirect_anon_page').closest('.pp-select-dynamic-wrapper');
        if ($(this).val() == '(select)') {
            $pageSelect.show();
        } else {
            $pageSelect.hide();
            $('#teaser_redirect_anon_page').val('');
        }
    });

    $('#teaser_redirect').on('change', function() {
        var $pageSelect = $('#teaser_redirect_page').closest('.pp-select-dynamic-wrapper');
        if ($(this).val() == '(select)') {
            $pageSelect.show();
        } else {
            $pageSelect.hide();
            $('#teaser_redirect_page').val('');
        }
    });

    // Teaser Notice Style Mode Toggle
    function toggleTeaserNoticeStyleSettings($select) {
        var $settingsContainer = $select.closest('.pp-teaser-settings-container');
        var $styleSettings = $settingsContainer.find('.pp-teaser-notice-style-settings');

        if ($select.val() === 'custom') {
            $styleSettings.slideDown(300, function() {
                // Scroll to the customization section after it's fully visible
                $('html, body').animate({
                    scrollTop: $styleSettings.offset().top - 100
                }, 500);
            });
        } else {
            $styleSettings.slideUp(300);
        }
    }

    // Handle change event for any teaser notice style select
    $(document).on('change', '.pp-teaser-notice-style-select', function() {
        toggleTeaserNoticeStyleSettings($(this));
    });

    $(document).on('pp_teaser_notice_style_updated', function(event, $container) {
        if ($container && $container.length) {
            updateTeaserPreviewText($container);
        }
    });

    // Monitor TinyMCE editor changes for teaser text
    $(document).on('input keyup', 'textarea[id*="tease_replace_content"]', function() {
        var editorId = $(this).attr('id');
        var $container = getTeaserSettingsContainer($(this));
        var $preview = getTeaserNoticePreviews($container);
        var value = '';
        
        // Try to get content from TinyMCE if active
        if (typeof tinymce !== 'undefined' && tinymce.get(editorId) && !tinymce.get(editorId).isHidden()) {
            value = tinymce.get(editorId).getContent({format: 'text'});
        } else {
            value = $(this).val();
        }
        
        // Strip HTML tags for preview
        var tempDiv = document.createElement('div');
        tempDiv.innerHTML = value;
        value = tempDiv.textContent || tempDiv.innerText || '';

        $preview
            .data('teaser-text-anon', value)
            .data('teaser-text-logged', value);
        
        updateTeaserPreviewText($container);
    });

    // Monitor title and content prefix/suffix editors used by the full preview
    $(document).on(
        'input keyup',
        'textarea[id*="tease_prepend_"], textarea[id*="tease_append_"]',
        function() {
            var $container = getTeaserSettingsContainer($(this));

            updateTeaserPreviewText($container);
        }
    );

    // Monitor textarea changes for notice message editors
    $(document).on('input keyup', 'textarea[id*="read_more_login_notice"], textarea[id*="excerpt_login_notice"], textarea[id*="x_chars_login_notice"]', function() {
        var editorId = $(this).attr('id');
        var $container = $(this).closest('.pp-teaser-settings-container');
        var $preview = $container.find('.pp-teaser-notice-preview');
        var value = '';
        
        // Try to get content from TinyMCE if active
        if (typeof tinymce !== 'undefined' && tinymce.get(editorId) && !tinymce.get(editorId).isHidden()) {
            value = tinymce.get(editorId).getContent({format: 'text'});
        } else {
            value = $(this).val();
        }
        
        // Strip HTML tags for preview
        var tempDiv = document.createElement('div');
        tempDiv.innerHTML = value;
        value = tempDiv.textContent || tempDiv.innerText || '';

        if (editorId.indexOf('read_more_login_notice') > -1) {
            $preview.data('read-more-msg', value);
        } else if (editorId.indexOf('excerpt_login_notice') > -1) {
            $preview.data('excerpt-msg', value);
        } else if (editorId.indexOf('x_chars_login_notice') > -1) {
            $preview.data('x-chars-msg', value);
        }
        
        updateTeaserPreviewText($container);
    });

    // Function to bind all TinyMCE editor events (preview updates and error removal)
    function bindTinyMCEEditor(editor) {
        // Handle preview text updates for teaser content editors
        if (editor.id.indexOf('tease_replace_content') > -1) {
            editor.on('keyup change', function() {
                var value = editor.getContent({format: 'text'});
                var $container = getTeaserSettingsContainer($('#' + editor.id));
                var $preview = getTeaserNoticePreviews($container);
                
                $preview
                    .data('teaser-text-anon', value)
                    .data('teaser-text-logged', value);
                
                updateTeaserPreviewText($container);
            });
        }

        if (editor.id.indexOf('tease_prepend_') > -1 || editor.id.indexOf('tease_append_') > -1) {
            editor.on('keyup change', function() {
                var $container = getTeaserSettingsContainer($(document.getElementById(editor.id)));

                updateTeaserPreviewText($container);
            });
        }
        
        // Handle preview text updates for message editors
        if (editor.id.indexOf('read_more_login_notice') > -1 || 
            editor.id.indexOf('excerpt_login_notice') > -1 || 
            editor.id.indexOf('x_chars_login_notice') > -1) {
            editor.on('keyup change', function() {
                var value = editor.getContent({format: 'text'});
                var $container = $('#' + editor.id).closest('.pp-teaser-settings-container');
                var $preview = $container.find('.pp-teaser-notice-preview');
                
                // Strip HTML tags for preview
                var tempDiv = document.createElement('div');
                tempDiv.innerHTML = value;
                value = tempDiv.textContent || tempDiv.innerText || '';
                
                if (editor.id.indexOf('read_more_login_notice') > -1) {
                    $preview.data('read-more-msg', value);
                } else if (editor.id.indexOf('excerpt_login_notice') > -1) {
                    $preview.data('excerpt-msg', value);
                } else if (editor.id.indexOf('x_chars_login_notice') > -1) {
                    $preview.data('x-chars-msg', value);
                }
                
                updateTeaserPreviewText($container);
            });
        }
        
    }

    // Bind to existing TinyMCE editors on page load
    if (typeof tinymce !== 'undefined') {
        // Bind to already-initialized editors
        tinymce.editors.forEach(function(editor) {
            bindTinyMCEEditor(editor);
        });
        
        // Bind to new editors as they're added
        tinymce.on('AddEditor', function(e) {
            bindTinyMCEEditor(e.editor);
        });
    }

    // PRO Feature Handling
    // Prevent selecting disabled PRO options
    $(document).on('change', 'select.pp-teaser-type-select', function() {
        var $select = $(this);
        var $selected = $select.find('option:selected');
        
        if ($selected.is(':disabled')) {
            // Revert to previous valid selection
            var $firstEnabled = $select.find('option:not(:disabled)').first();
            $select.val($firstEnabled.val());
            
            // Show upgrade notice
            alert('This feature is only available in PublishPress Permissions PRO.\n\nUpgrade now to unlock advanced teaser types including Read More links, excerpts, and redirects.');
        }
    });

    // Prevent interaction with disabled post type options
    $(document).on('change', '.pp-current-post-type', function() {
        var $select = $(this);
        var $selected = $select.find('option:selected');
        
        if ($selected.is(':disabled')) {
            // Revert to first enabled option
            var $firstEnabled = $select.find('option:not(:disabled)').first();
            $select.val($firstEnabled.val()).trigger('change');
            
            // Show upgrade notice
            alert('This post type is only available in PublishPress Permissions PRO.\n\nUpgrade now to apply teasers to Pages, WooCommerce Products, and all custom post types.');
        }
    });

    // PRO badge click handlers
    $(document).on('click', '.pp-pro-badge', function(e) {
        e.stopPropagation();
        var upgradeUrl = 'https://publishpress.com/links/permissions-banner';
        if (confirm('This feature is only available in PublishPress Permissions PRO.\n\nWould you like to learn more about upgrading?')) {
            window.open(upgradeUrl, '_blank');
        }
    });

    // Style disabled options
    $('select option:disabled').css({
        'color': '#999',
        'font-style': 'italic'
    });

    $('input[type="radio"]:disabled').each(function() {
        $(this).closest('label').css({
            'opacity': '0.5',
            'cursor': 'not-allowed'
        });
    });
});
