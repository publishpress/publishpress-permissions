function presspermitLoadAgentsJS(id_sfx, agent_type, context, agent_id, selection_only) {
    jQuery(document).ready(function ($) {
        id_sfx = CSS.escape(id_sfx);

        $("#select_agents_" + id_sfx).on('click', function (e) {
            e.preventDefault();
            presspermitSelectAgents(id_sfx);
        });

        $("#agent_results_" + id_sfx).on('dblclick', function (e) {
            e.preventDefault();
            presspermitSelectAgents(id_sfx);
        });

        if (selection_only != true) {
            $("#unselect_agents_" + id_sfx).on('click', function (event) {
                presspermitUnselectListAgents(id_sfx);
            });
            $("#" + id_sfx).on('dblclick', function (event) {
                presspermitUnselectListAgents(id_sfx);
            });

            $("#agent_results_" + id_sfx).DynamicListbox({
                ajaxurl: PPAgentSelect.ajaxurl,
                agent_type: agent_type,
                search_id: 'agent_search_text_' + id_sfx,
                results_id: 'agent_results_' + id_sfx,
                button_id: 'agent_submit_' + id_sfx,
                ajaxhandler: PPAgentSelect.ajaxhandler,
                pp_context: context,
                topic: id_sfx,
                agent_id: agent_id
            });
        }
    });
}

function presspermitBuildSelectionCSV(list_id, $) {
    var s = '';

    $("#" + list_id + " option").each(function () {
        s = s + $(this).attr("value") + ',';
    });

    $("#" + list_id + "_csv").attr("value", s);
}

function presspermitSelectAgents(id_sfx, select_into, hierarchical) {
    jQuery(document).ready(function ($) {
        var selector = '#' + CSS.escape('v2_agent_search_text_' + id_sfx + '::' + select_into);
        if ($(selector).length) {
            $(selector).select2('data').forEach(function (item) {
                if ($("#" + id_sfx + " option").filter(function () { return this.value === String(item.id); }).length === 0) {
                    $(new Option(item.text, item.id)).attr('title', item.text).addClass('pp-new-selection').appendTo("#" + id_sfx);
                }
            });
            if ($("#" + id_sfx).length && $("#" + id_sfx).is(":visible")) {
                $(selector).val(null).trigger('change');
            }
        } else {
            $("#agent_results_" + id_sfx + " option:selected").each(function (i) {
                var value = this.value;
                if ($("#" + id_sfx + " option").filter(function () { return this.value === value; }).length == 0) {
                    $(new Option($(this).text(), value)).attr('title', $(this).text()).addClass('pp-new-selection').appendTo("#" + id_sfx);
                    $(this).remove();
                }
            });
        }

        presspermitBuildSelectionCSV(id_sfx, $);
    });
}

function presspermitUnselectListAgents(id_sfx) {
    jQuery(document).ready(function ($) {
        $("#" + id_sfx + " option:selected").appendTo("#agent_results_" + id_sfx);
        presspermitBuildSelectionCSV(id_sfx, $);
    });
}

function presspermitLoadSelect2AgentsJS(id_sfx, agent_type, context, agent_id, selection_only) {
    jQuery(document).ready(function ($) {
        var id_sfx_dashed = `${id_sfx}::${agent_type}`.replace(':', '-').replace(':', '-');
        $('select[name="_select-' + id_sfx_dashed + '\\[\\]"]').on('select2:select', function (e) {
            presspermitSelectAgents(id_sfx, agent_type);
            return false;
        }).on('select2:unselect', function (e) {
            var data = e.params.data;
            $("#" + id_sfx + " option[value='" + data.id + "']").remove(); // Remove the option matching data.id
            presspermitBuildSelectionCSV(id_sfx, $); // Update the CSV after removal
            return false;
        });
    });
}
