jQuery(document).ready(function ($) {
    // Tabs
    var $tabsWrapper = $('#pp_settings_form ul.nav-tab-wrapper');
    $tabsWrapper.find('li').click(function (e) {
        e.preventDefault();
        $tabsWrapper.children('li').filter('.nav-tab-active').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        $('.pp-options-wrapper > div').hide();
        var panel = $(this).find('a').first().attr('href');
        $(panel).show();
        
        // Update the hidden tab field to maintain current tab after form submission
        var tabName = panel.replace('#pp-', '');
        $('input[name="pp_tab"]').val(tabName);
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
                var img_html = '<img class="pp-show-hints" title="See more configuration tips..." src="' + ppCoreSettings.hintImg + '" />';
                
                if ($row.find('div.pp-extra-heading').length) {
                    $row.find('div.pp-extra-heading').before(img_html);
                } else if ($row.find('> th').length) {
                    $row.find('> th').append(img_html);
                } else {
                    $row.find('> td').first().find('span').first().append(img_html);
                }
            }
        });

        $('.pp-options-table tr img.pp-show-hints').click(function() {
            $(this).closest('tr').find('td .pp-subtext, td .pp-hint, table.pp-hint, div.pp-hint').show();
            $(this).hide();
        });
    }
});