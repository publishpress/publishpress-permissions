jQuery(document).ready(function ($) {
    // Tabs
    var $tabsWrapper = $('#pp_settings_form ul.nav-tab-wrapper');
    var $tabs = $tabsWrapper.find('a[role="tab"]');

    function activateTab($tab, moveFocus) {
        if (!$tab.length) {
            return;
        }

        var panel = $tab.attr('href');
        $tabs.attr('aria-selected', 'false').attr('tabindex', '-1');
        $tab.attr('aria-selected', 'true').attr('tabindex', '0');
        $tabsWrapper.children('li').removeClass('nav-tab-active');
        $tab.closest('li').addClass('nav-tab-active');

        $('.pp-options-wrapper > div').hide().attr('aria-hidden', 'true');
        $(panel).show().attr('aria-hidden', 'false');

        // Update the hidden tab field to maintain current tab after form submission
        var tabName = panel.replace('#pp-', '');
        $('input[name="pp_tab"]').val(tabName);

        if (moveFocus) {
            $tab.focus();
        }
    }

    $tabs.on('click', function (e) {
        e.preventDefault();
        activateTab($(this), false);
    });

    $tabs.on('keydown', function (e) {
        var currentIndex = $tabs.index(this);
        var targetIndex = currentIndex;

        if (e.key === 'Enter' || e.key === ' ' || e.keyCode === 13 || e.keyCode === 32) {
            e.preventDefault();
            activateTab($(this), false);
            return;
        }

        if (e.key === 'ArrowRight' || e.key === 'ArrowDown' || e.keyCode === 39 || e.keyCode === 40) {
            targetIndex = (currentIndex + 1) % $tabs.length;
        } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp' || e.keyCode === 37 || e.keyCode === 38) {
            targetIndex = (currentIndex - 1 + $tabs.length) % $tabs.length;
        } else if (e.key === 'Home' || e.keyCode === 36) {
            targetIndex = 0;
        } else if (e.key === 'End' || e.keyCode === 35) {
            targetIndex = $tabs.length - 1;
        } else {
            return;
        }

        e.preventDefault();
        activateTab($tabs.eq(targetIndex), true);
    });

    // todo: pass img url variable, title
    if (ppCoreSettings.displayHints == 1 && ppCoreSettings.forceDisplayHints != 1) {
        $('.pp-options-table tr').each(function (i,e) {
            var $row = $(this); // Cache the current row for better performance

            // Check for .pp-subtext elements
            var subtextElements = $row.find('.pp-subtext, .pp-hint');
            var hasSubtext = subtextElements.length > 0;

            // Check if there is at least one .pp-subtext that does NOT have .pp-no-hide
            var hasVisibleSubtext = subtextElements.filter(':not(.pp-no-hide)').length > 0;

            // Append the image if the conditions are met
            if (hasSubtext && hasVisibleSubtext) {
                var img_html = '<button type="button" class="pp-show-hints" aria-label="See more configuration tips"><img alt="" src="' + ppCoreSettings.hintImg + '" /></button>';
                
                if ($row.find('div.pp-extra-heading').length) {
                    $row.find('div.pp-extra-heading').before(img_html);
                } else if ($row.find('> th').length) {
                    $row.find('> th').append(img_html);
                } else {
                    $row.find('> td').first().find('span').first().append(img_html);
                }
            }
        });

        $('.pp-options-table tr button.pp-show-hints').click(function() {
            $(this).closest('tr').find('td .pp-subtext, td .pp-hint, table.pp-hint, div.pp-hint').show();
            $(this).hide();
        });
    }
});
