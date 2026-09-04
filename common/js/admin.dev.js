(function ($) {
    'use strict';

    function initUpgradeMenuLinks() {
        $('.pp-upgrade-menu-config').each(function () {
            var url = $(this).data('url');

            $('#toplevel_page_presspermit-groups ul li:last a, ' +
                '#toplevel_page_presspermit-settings ul li:last a, ' +
                '#toplevel_page_presspermit-posts-teaser ul li:last a')
                .attr({href: url, target: '_blank'})
                .css({fontWeight: 'bold', color: '#FEB123'});
        });

        $('.pp-version-notice-menu-config').each(function () {
            var pluginName = $(this).data('plugin-name');

            $('a.pp-version-notice-upgrade-menu-item').filter(function () {
                return $(this).hasClass(pluginName);
            })
                .attr({target: '_blank', href: $(this).data('redirect-to')});
        });
    }

    function initDismissibleNotices() {
        $(document).on('click', 'a.presspermit-dismiss-notice', function (event) {
            event.preventDefault();
            $(this).closest('div').slideUp();
            $.post(window.ajaxurl, {
                action: 'pp_dismiss_msg',
                msg_id: $(this).attr('id'),
                cookie: encodeURIComponent(document.cookie),
                _ajax_nonce: $(this).data('nonce')
            });
        });
    }

    function initSingleAgentSelection() {
        $(document).on('click', '.pp_agents_wrapper.pp-single-select ul.pp-agents-list input[type="checkbox"]', function () {
            $(this).closest('.pp_agents_wrapper').find('ul.pp-agents-list input[type="checkbox"]').not(this).prop('checked', false);
        });
    }

    function initQueuedAgentSelectors() {
        $('.pp-agent-selector-config').each(function () {
            var config = this.dataset;
            var authorSelectionOnly = config.authorSelectionOnly === '1';
            var suppressSelection = config.suppressSelection === '1';

            window.presspermitLoadAgentsJS(
                config.idSuffix,
                config.agentType,
                config.context,
                config.agentId,
                suppressSelection,
                authorSelectionOnly
            );

            if (config.loadSelect2 === '1') {
                window.presspermitLoadSelect2AgentsJS(
                    config.idSuffix,
                    config.agentType,
                    config.context,
                    config.agentId,
                    suppressSelection,
                    authorSelectionOnly
                );
            }
        });
    }

    function initPostEdit() {
        $('.pp-edit-parent-config').each(function () {
            $('#pageparentdiv div.inside p').first().wrapInner(
                $('<a>', {href: $(this).data('url')})
            );
        });

        if ($('.pp-force-autosave-before-upload').length) {
            $('#wp-content-media-buttons a').on('click', function () {
                if ($('#post-status-info span.autosave-message').html() === '&nbsp;' && typeof window.autosave === 'function') {
                    window.autosave();
                }
            });
        }

        if ($('.pp-suppress-upload-ui').length) {
            $(document).on('focus', 'div.supports-drag-drop', function () {
                $('div.media-router a:first').hide();
                $('div.media-router a:nth-child(2)').trigger('click');
            });
            $(document).on('mouseover', 'div.supports-drag-drop', function () {
                $('div.media-menu a:nth-child(2), div.media-menu a:nth-child(5)').hide();
            });
        }

        $('.pp-author-search-config').each(function () {
            var $config = $(this);
            var $base = $config.find('.pp-author-search-ui-base');
            var $search = $('<div>', {id: 'pp_author_search', class: 'pp-select-author'}).hide().append($base.contents());
            var $open = $('<a>', {
                href: '#',
                class: 'pp-add-author',
                title: $config.data('title'),
                text: $config.data('open-label')
            }).css('margin-left', '8px');
            var $close = $('<a>', {
                href: '#',
                class: 'pp-close-add-author',
                text: $config.data('close-label')
            }).hide();

            $('#post_author_override').after($search, '\u00a0', $open, $close);
            $config.remove();
        });

        $('.pp-status-promo-config').each(function () {
            var $config = $(this);
            $('a.edit-post-status').after($config.contents());
            $config.remove();
        });

        $(document).on('click', 'a.pp-statuses-promo', function (event) {
            event.preventDefault();
            $(this).hide().next('span').show();
        });
    }

    function initTermListing() {
        if ($('.pp-resize-term-listing').length) {
            $('#col-left').css('width', '25%');
            $('#col-right').css('width', '75%');
            $('.column-slug').css('width', '15%');
            $('.column-posts').css('width', '10%');
        }

        $('.pp-universal-terms-config').each(function () {
            var postType = $(this).data('post-type');
            $('#the-list tr').find('a.row-title, span.edit a').each(function () {
                var url = $(this).attr('href').replace('&post_type=' + postType, '');
                $(this).attr('href', url + '&pp_universal=1');
            });
        });

        if ($('.pp-hide-main-term-option').length) {
            $('#parent option[value="-1"]').remove();
        }
    }

    function initSettings() {
        if ($('.pp-sync-submenu-config').length) {
            $('#adminmenu li.toplevel_page_presspermit-groups ul.wp-submenu li').removeClass('current');
            $('#adminmenu li.toplevel_page_presspermit-groups ul.wp-submenu li a[href="admin.php?page=presspermit-sync"]')
                .parent().addClass('current');
        }

        $('input#advanced_options').on('click', function () {
            var enabled = $(this).closest('td').find('.pp-advanced-caution').data('options-enabled') === 1;
            $(this).closest('td').find('.pp-advanced-caution').slideToggle(enabled ? this.checked : !this.checked);
        });

        $('#limit_user_edit_enabled').on('change', function () {
            $('#pp_limit_user_edit_by_level_wrap').toggle(this.checked);
        });

        $('input#list_all_constants').on('click', function () {
            $('#pp_available_constants').toggle(this.checked);
        });

        $('.pp-post-types-config').each(function () {
            var labels = this.dataset;

            $('.pp-post-type-toggle').on('click', function (event) {
                event.preventDefault();
                event.stopPropagation();

                var postType = $(this).data('post-type');
                var $content = $('.filter_post_type_content[data-post-type="' + postType + '"]');
                var $summary = $('.pp-post-type-summary[data-post-type="' + postType + '"]');
                var $chevron = $(this);

                $content.slideToggle(300, function () {
                    $summary[$content.is(':visible') ? 'fadeOut' : 'fadeIn'](200);
                });
                $chevron.css('transform', $chevron.css('transform') === 'none' || $chevron.css('transform') === 'matrix(1, 0, 0, 1, 0, 0)'
                    ? 'rotate(180deg)' : 'rotate(0deg)');
            });

            $('.agp-vtight_input label').on('click', function (event) {
                if (event.target.tagName !== 'INPUT') {
                    $(this).siblings('.pp-post-type-toggle').trigger('click');
                }
            });

            $('input[id^="pp_enable_metabox_"], input[id^="pp_include_permission_screen_"]').on('change', function () {
                var optionId = this.id;
                var postType = optionId.indexOf('pp_enable_metabox_') === 0
                    ? optionId.replace('pp_enable_metabox_', '')
                    : optionId.replace('pp_include_permission_screen_', '');
                var summary = [
                    $('#pp_enable_metabox_' + postType).is(':checked') ? labels.metaboxEnabled : labels.metaboxDisabled,
                    $('#pp_include_permission_screen_' + postType).is(':checked') ? labels.permissionsEnabled : labels.permissionsDisabled
                ];

                $('.pp-post-type-summary[data-post-type="' + postType + '"]').text(summary.join(', '));
            });
        });

        $('.pp-category-label').on('click', function () {
            var category = $(this).data('category');
            $('.pp-category-label').removeClass('active');
            $(this).addClass('active');
            $('.pp-integration-card').each(function () {
                var categories = ($(this).data('categories') || 'all').toString().split(',');
                $(this).toggleClass('pp-hidden', category !== 'all' && categories.indexOf(category) === -1);
            });
        });

        $('#pp-integrations .pp-integration-card.pp-disabled input[type="checkbox"]').on('click', function (event) {
            event.preventDefault();
            var $card = $(this).closest('.pp-integration-card');
            $card.find('.pp-upgrade-overlay').css('opacity', '1').delay(3000).animate({opacity: '0'}, 500);
            if (!$card.find('.pp-temp-message').length) {
                $('<div class="pp-temp-message">Pro Feature</div>').appendTo($card).delay(2000).fadeOut(500, function () {
                    $(this).remove();
                });
            }
        });
    }

    function initPromos() {
        $('.pp-file-filtering-promo-config').each(function () {
            $('#posts-filter').after($(this).contents());
            $(this).remove();
        });

        $(document).on('click', 'a.pp-file-filtering-promo', function (event) {
            event.preventDefault();
            $(this).hide().next('span').show();
        });

        $('a[href="#pp-pro-info"]').on('click', function (event) {
            event.preventDefault();
            $('#pp_features, ul.pro-pplinks').show();
        });
        $('a[href="#pp-pro-hide"]').on('click', function (event) {
            event.preventDefault();
            $('#pp_features, ul.pro-pplinks').hide();
        });
    }

    function initCollaborationUi() {
        $('.pp-appearance-menu-config').each(function () {
            $('#menu-appearance .wp-submenu-wrap a[href!="nav-menus.php"]').not('[class*="wp-has-submenu"]').parent().remove();
            if ($(this).data('suppress-link') === 1) {
                $('#menu-appearance .wp-submenu-wrap a[href="nav-menus.php"]').closest('li.menu-top').find('a.menu-top')
                    .attr('href', '#').on('click', function (event) { event.preventDefault(); });
            } else {
                $('#menu-appearance .wp-submenu-wrap a[href="nav-menus.php"]').closest('li.menu-top').find('a.menu-top').attr('href', 'nav-menus.php');
            }
        });

        $('.pp-nav-menu-name-config').each(function () {
            $('#menu-name').prop('disabled', true).attr('name', 'menu-name-label')
                .after($('<input>', {type: 'hidden', name: 'menu-name', value: $(this).data('menu-name')}));
        });

        $('.pp-nav-menu-item-config').each(function () {
            $('#menu-item-' + $(this).data('item-id')).removeClass('menu-item').addClass('menu-item-edit-inactive');
        });

        $('.pp-moderation-status-config').each(function () {
            var status = $(this).data('status');
            var exists = $('select[name="_status"] option').filter(function () {
                return this.value === String(status);
            }).length;

            if (!exists) {
                $('<option>', {value: status, text: $(this).data('label')})
                    .insertBefore('select[name="_status"] option[value="pending"]');
            }
        });

        $('.pp-member-page-adder-config').each(function () {
            var $config = $(this);
            if ($config.data('message')) {
                $('div.wrap h2').after($('<div>', {id: 'message', class: $config.data('message-class') + ' below-h2'})
                    .append($('<p>', {text: $config.data('message')})));
            }
            $("select[name='action']").closest('div.top').find('div.tablenav-pages').after($config.children());
            $config.remove();
        });

        $(document).on('change', '#member_page_type', function () {
            var hasType = $(this).val() !== '';
            $('#member_page_title, #member_page_add').toggle(hasType);
            $('span.member-page-pattern').hide();
            $('#member_page_pattern_div_' + $(this).val()).show();
            $('#member_page_adder').toggleClass('pp-bg-gray', hasType);
        });
    }

    function initReviewNotices() {
        $('.pp-wordpress-review-config').each(function () {
            var $notice = $(this);
            function dismiss(reason) {
                $.ajax({
                    method: 'POST',
                    dataType: 'json',
                    url: window.ajaxurl,
                    data: {
                        action: $notice.data('action'),
                        nonce: $notice.data('nonce'),
                        group: $notice.data('group'),
                        code: $notice.data('code'),
                        priority: $notice.data('priority'),
                        reason: reason
                    }
                });
            }

            $notice.on('click', '.pp-wordpress-review-dismiss', function () {
                var reason = $(this).data('reason');
                $notice.fadeTo(100, 0, function () {
                    $notice.slideUp(100, function () { $notice.remove(); });
                });
                dismiss(reason);
            });

            window.setTimeout(function () {
                $notice.find('button.notice-dismiss').on('click', function () { dismiss('maybe_later'); });
            }, 1000);
        });
    }

    $(function () {
        initUpgradeMenuLinks();
        initDismissibleNotices();
        initSingleAgentSelection();
        initQueuedAgentSelectors();
        initPostEdit();
        initTermListing();
        initSettings();
        initPromos();
        initCollaborationUi();
        initReviewNotices();
    });
}(jQuery));
