jQuery(document).ready(function ($) {
    if (ppCollabEdit.blockMainPage) {
        var DetectPageParentDiv = function () {
            if ($('div.editor-page-attributes__parent').length) {
                $('div.editor-page-attributes__parent select option[value=""]').html(ppCollabEdit.selectCaption);
            }
        }
        var DetectPageParentDivInterval = setInterval(DetectPageParentDiv, 500);
    }
});