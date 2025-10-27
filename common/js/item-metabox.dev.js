/**
 * Post / Term metabox js, ported by Kevin Behrens from WP code:
 *
 * WordPress Administration Navigation Menu
 * Interface JS functions version 2.0.0
 *
 */

var wpNavMenu;

(function ($) {

    var api = wpNavMenu = {
        // Functions that run on init.
        init: function () {
            this.jQueryExtensions();
            this.attachQuickSearchListeners();
            this.attachTabsPanelListeners();
            this.initToggles();
        },

        jQueryExtensions: function () {
            // jQuery extensions
            $.fn.extend({
                getItemData: function () {
                    var itemData = {};
                    itemData['menu-item-object-id'] = this.find('input.menu-item-checkbox').val();
                    itemData['menu-item-title'] = this.find('label').html();
                    return itemData;
                }
            });
        },

        initToggles: function () {
            // init postboxes
            postboxes.add_postbox_toggles('nav-menus');
        },

        attachQuickSearchListeners: function () {
            var searchTimer;

            $('.quick-search').keypress(function (e) {
                var t = $(this);

                if (13 == e.which) {
                    api.updateQuickSearchResults(t);
                    return false;
                }

                if (searchTimer) clearTimeout(searchTimer);

                searchTimer = setTimeout(function () {
                    api.updateQuickSearchResults(t);
                }, 400);
            }).attr('autocomplete', 'off');
        },

        updateQuickSearchResults: function (input) {
            var panel, params,
                minSearchLength = 2,
                q = input.val();

            if (q.length < minSearchLength) return;

            panel = input.parents('.tabs-panel');
            params = {
                'action': 'menu-quick-search',
                'response-format': 'markup',
                'menu': $('#menu').val(),
                'menu-settings-column-nonce': $('#menu-settings-column-nonce').val(),
                'q': q,
                'type': input.attr('name')
            };

            $('.spinner', panel).show();

            $.post(ppItems.ajaxurl, params, function (menuMarkup) {
                api.processQuickSearchQueryResponse(menuMarkup, params, panel);
            });
        },

        attachTabsPanelListeners: function () {
            $('#menu-settings-column').on('click', function (e) {
                var selectAreaMatch, panelId, wrapper, items,
                    target = $(e.target);

                if (target.hasClass('nav-tab-link')) {
                    panelId = /#(.*)$/.exec(e.target.href);
                    if (panelId && panelId[1])
                        panelId = panelId[1]
                    else
                        return false;

                    wrapper = target.parents('.inside').first();

                    // upon changing tabs, we want to uncheck all checkboxes
                    $('input', wrapper).prop('checked', false);

                    $('.tabs-panel-active', wrapper).not('#tabs-panel-search-taxonomy-category').removeClass('tabs-panel-active').addClass('tabs-panel-inactive');
                    $('#' + panelId, wrapper).removeClass('tabs-panel-inactive').addClass('tabs-panel-active');

                    $('.tabs', wrapper).removeClass('tabs');
                    target.parent().addClass('tabs');

                    // select the search bar
                    $('.quick-search', wrapper).focus();

                    return false;
                } else if (target.hasClass('select-all')) {
                    selectAreaMatch = /#(.*)$/.exec(e.target.href);
                    if (selectAreaMatch && selectAreaMatch[1]) {
                        items = $('#' + selectAreaMatch[1] + ' .tabs-panel-active input.menu-item-checkbox');
                        if (items.length === items.filter(':checked').length)
                            items.prop('checked', false);
                        else
                            items.prop('checked', true);
                        return false;
                    }

                } else if (target.hasClass('page-numbers')) {
                    $.post(ppItems.ajaxurl, e.target.href.replace(/.*\?/, '').replace(/action=([^&]*)/, '') + '&pp_ajax_items_metabox=1',
                        function (resp) {
                            if (-1 == resp.indexOf('replace-id'))
                                return;

                            var metaBoxData = $.parseJSON(resp),
                                toReplace = document.getElementById(metaBoxData['replace-id']),
                                placeholder = document.createElement('div'),
                                wrap = document.createElement('div');

                            if (!metaBoxData['markup'] || !toReplace)
                                return;

                            wrap.innerHTML = metaBoxData['markup'] ? metaBoxData['markup'] : '';

                            toReplace.parentNode.insertBefore(placeholder, toReplace);
                            placeholder.parentNode.removeChild(toReplace);

                            placeholder.parentNode.insertBefore(wrap, placeholder);

                            placeholder.parentNode.removeChild(placeholder);

                            $('span.add-to-menu img.waiting').hide();
                        }
                    );

                    return false;
                }
            });
        },

        /**
         * Process the quick search response into a search result
         *
         * @param string resp The server response to the query.
         * @param object req The request arguments.
         * @param jQuery panel The tabs panel we're searching in.
         */
        processQuickSearchQueryResponse: function (resp, req, panel) {
            var matched, newID,
                takenIDs = {},
                form = document.getElementById('nav-menu-meta'),
                pattern = new RegExp('menu-item\\[([^\\]]*)', 'g'),
                $items = $('<div>').html(resp).find('li'),
                $item;

            if (!$items.length) {
                $('.categorychecklist', panel).html('<li><p>' + ppItems.noResultsFound + '</p></li>');
                $('.spinner', panel).hide();
                return;
            }

            $items.each(function () {
                $item = $(this);

                // make a unique DB ID number
                matched = pattern.exec($item.html());

                if (matched && matched[1]) {
                    newID = matched[1];
                    while (form.elements['menu-item[' + newID + '][menu-item-type]'] || takenIDs[newID]) {
                        newID--;
                    }

                    takenIDs[newID] = true;
                    if (newID != matched[1]) {
                        $item.html($item.html().replace(new RegExp(
                            'menu-item\\[' + matched[1] + '\\]', 'g'),
                            'menu-item[' + newID + ']'
                        ));
                    }
                }
            });

            $('.categorychecklist', panel).html($items);
            $('.spinner', panel).hide();
        }
    };

    $(document).ready(function () {
        wpNavMenu.init();
    });

})(jQuery);
