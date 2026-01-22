jQuery(document).ready(function ($) {
    if (ppCollabEdit.blockMainPage) {
        var DetectPageParentDiv = function () {
            if ($('div.editor-page-attributes__parent').length) {
                $('div.editor-page-attributes__parent select option[value=""]').html(ppCollabEdit.selectCaption);
            }
        }
        var DetectPageParentDivInterval = setInterval(DetectPageParentDiv, 500);
    }
    
    // Set post visibility in Gutenberg based on default_privacy
    if (typeof window.ppEditorConfig !== 'undefined' && window.ppEditorConfig.defaultPrivacy) {
        var defaultPrivacy = window.ppEditorConfig.defaultPrivacy;
        var visibility;
        switch (defaultPrivacy) {
            case 'private':
                visibility = 'private';
                break;
            default:
                visibility = 'draft';
                break;
            // Add more cases if needed
        }

        if (visibility) {
            // Wait for Gutenberg editor to be fully ready
            var applyDefaultPrivacy = function() {
                if (typeof wp === 'undefined' || !wp.data || !wp.data.select || !wp.data.dispatch) {
                    setTimeout(applyDefaultPrivacy, 200);
                    return;
                }
                
                // Check if editor is fully loaded by verifying we have a post type
                var currentPost = wp.data.select('core/editor').getCurrentPost();
                if (!currentPost || !currentPost.type) {
                    setTimeout(applyDefaultPrivacy, 200);
                    return;
                }
                
                try {
                    wp.data.dispatch('core/editor').editPost({ status: visibility });
                    if (wp.data.dispatch('core/editor').savePost) {
                        wp.data.dispatch('core/editor').savePost();
                    }
                } catch (e) {
                    console.log('Error applying default privacy:', e);
                    // Retry after a delay if there's still an error
                    setTimeout(applyDefaultPrivacy, 500);
                }
            };
            
            // Start checking for editor readiness
            applyDefaultPrivacy();
        }
    }
});