jQuery(document).ready(function ($) {
    $(document).on('click', '#authordiv a.pp-add-author', function () {
        $('#post_author_override').hide();
        $('#pp_author_search').show();
        $('#authordiv a.pp-add-author').hide();
        $('#authordiv a.pp-close-add-author').show();
        $('#agent_search_text_select-author').focus();
        return false;
    });

    $(document).on('click', '#authordiv a.pp-close-add-author', function () {
        $('#pp_author_search').hide();
        $('#authordiv a.pp-close-add-author').hide();
        $('#authordiv a.pp-add-author').show();
        $('#post_author_override').show();
        return false;
    });

    $(document).on('click', '#select_agents_select-author', function () {
        var selected_id = $('#agent_results_select-author').val();
        if (selected_id) {
            if (!$('#post_author_override option[value="' + selected_id + '"]').prop('selected', true).length) {
                var selected_name = $('#agent_results_select-author option:selected').html();

                $('#post_author_override').append('<option value=' + selected_id + '>' + selected_name + '</option>');
                $('#post_author_override option[value="' + selected_id + '"]').prop('selected', true);
            }
        }

        $('#authordiv a.pp-close-add-author').trigger('click');
        return false;
    });

    $(document).on('jchange', '#agent_results_select-author', function () {
        if ($('#agent_results_select-author option').length) {
            $('#agent_results_select-author').show();
            $('#select_agents_select-author').show();
        }
    });
});