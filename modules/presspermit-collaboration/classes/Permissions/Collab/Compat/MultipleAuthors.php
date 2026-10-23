<?php
namespace PublishPress\Permissions\Collab\Compat;

\defined('ABSPATH') || exit;

class MultipleAuthors
{
    function __construct()
    {
        // Note: Some filters will be required even if PPMA is inactive, to deal with orphaned posts

        if (defined('PUBLISHPRESS_MULTIPLE_AUTHORS_VERSION')) {
            // Disable Force Empty Author setting until Editing Permissions filters can support it
            add_filter('pp_multiple_authors_default_options', [$this, 'fltDefaultOptions'], 20);
            add_filter('pre_option_multiple_authors_multiple_authors_options', [$this, 'fltAuthorsOptions'], 20);
        }
    }

    function fltDefaultOptions($options) {
        $options['force_empty_author'] = 'no';
        return $options;
    }

    function fltAuthorsOptions($options) {
        if (!empty($options)) {
            $options->force_empty_author= 'no';
        }

        return $options;
    }
}
