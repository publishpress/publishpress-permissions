(function ($) {
    function resetSelectItem(selectElementId) {
        // Reset the Select2 dropdown
        $(selectElementId).val(null).trigger('change');
        const [_, identifier] = selectElementId.split(/(?<=#v2_agent_search_text_)/);
        const [op, forItemType, agentType] = identifier.replace(/\\:/g, ':').split(':');

        // Update all hidden inputs to have empty values
        $(`input[name^="pp_exceptions[${forItemType}][${op}][${agentType}][item]"]`).each(function () {
            $(this).val(''); // Set the value to empty
        });
    }
    // Expose the function to the global window object
    window.resetSelectItem = resetSelectItem;

    $.fn.DynamicListbox = function (args) {
        /*
        var args = {
            search_id: 
            ,button_id: 
            ,results_id: 
            ,ajaxurl:''	
            ,agent_type: 'user'
            ,agent_id: ''
            ,topic: ''
            ,pp_context: ''
            ,ajaxhandler: 'got_ajax_listbox'
        };
        */

        var initializeSelect2 = function (args2) {
            let selector = "#v2_" + args2.search_id;
            const [op, forItemType, agentType] = args2.topic.replace(/\\:/g, ':').split(':');
            const selectedValues = $(selector).val() || [];
            let agent_type_lbl = ppListbox.placeholder[args2.agent_type] || args2.agent_type;

            if (!['member', 'select-author'].includes(args2.topic)) {
                // Clear all existing hidden inputs for this agent type
                $(`input[name^="pp_exceptions[${forItemType}][${op}][${agentType}][item]"]`).remove();
        
                // Add hidden inputs for each selected value
                selectedValues.forEach(function (value) {
                    $('<input>')
                        .attr('type', 'hidden')
                        .attr('name', `pp_exceptions[${forItemType}][${op}][${agentType}][item][${value}]`)
                        .val('2') // Default value for selected items
                        .appendTo($(selector).parent());
                });
            }

            if (['member', 'select-author'].includes(args2.topic)) {
                selector += ", #v2_" + CSS.escape(args2.search_id + '::' + args2.agent_type);
            }

            $(selector).select2({
              placeholder: agent_type_lbl,
              dropdownAutoWidth: true,
              dropdownCssClass: 'pp-select2-dropdown',
              containerCssClass: 'pp-select2-container',
              width: '325px',
              ajax: {
                url: args2.ajaxurl,
                dataType: "html",
                delay: 250,
                data: function (params) {
                  let roletext = "";
                  if ($("#pp_search_role_" + args2.topic).length) {
                    roletext = $("#pp_search_role_" + args2.topic).val();
                  }
          
                  const umkey = [];
                  const umval = [];
                  for (let i = 0; i < 6; i++) {
                    if ($("#pp_search_user_meta_key_" + i + "_" + args2.topic).length) {
                      umkey[i] = $(
                        "#pp_search_user_meta_key_" + i + "_" + args2.topic
                      ).val();
                      umval[i] = $(
                        "#pp_search_user_meta_val_" + i + "_" + args2.topic
                      ).val();
                    } else {
                      umkey[i] = "";
                      umval[i] = "";
                    }
                  }
          
                  return {
                    pp_agent_search: params.term || "",
                    pp_role_search: roletext,
                    pp_agent_type: args2.agent_type,
                    pp_agent_id: args2.agent_id,
                    pp_topic: args2.topic,
                    pp_usermeta_key: umkey,
                    pp_usermeta_val: umval,
                    pp_omit_admins: ppListbox.omit_admins,
                    pp_metagroups: ppListbox.metagroups,
                    pp_operation: args2.op,
                    pp_context: args2.pp_context,
                  };
                },
                processResults: function (data) {
                    // Parse the HTML response and convert it to Select2 format
                    const options = [];
                    const currentValues = [];

                    // Extract the current values from the hidden inputs
                    $(selector).closest('table.pp-item-exceptions-ui').find('td.pp-current-item-exceptions td input[type="hidden"]').each(function (i, item) {
                        currentValues.push($(item).val());
                    });

                    if (args2.topic === 'member') {
                        $(selector).closest('table').find('.pp-members-current').find('select#member option').each(function (i, item) {
                            currentValues.push($(item).val());
                        });
                    }

                    // Parse the HTML response to extract options
                    $(data)
                    .filter("option")
                    .each(function () {
                        const id = $(this).val();
                        // Omit already selected values
                        if (!currentValues.includes(id)) {
                            options.push({
                                id: id,
                                text: $(this).text(),
                            });
                        }
                    });

                    return {
                        results: options,
                    };
                },
                cache: true,
              },
            }).on('select2:select select2:unselect', function (e) {                
                if (!['member', 'select-author'].includes(args2.topic)) {
                    const [op, forItemType, agentType] = args2.topic.replace(/\\:/g, ':').split(':');
                    const selectedValues = $(this).val() || [];

                    // Add hidden inputs for each selected value
                    selectedValues.forEach(function (value) {
                        $('<input>')
                            .attr('type', 'hidden')
                            .attr('name', `pp_exceptions[${forItemType}][${op}][${agentType}][item][${value}]`)
                            .val('2') // Default value for selected items
                            .appendTo($(selector).parent());
                    });
            
                    // Add hidden input for the unselected value with an empty value
                    if (e.type === 'select2:unselect') {
                        $('<input>')
                            .attr('type', 'hidden')
                            .attr('name', `pp_exceptions[${forItemType}][${op}][${agentType}][item][${e.params.data.id}]`)
                            .val('') // Empty value for unselected items
                            .appendTo($(selector).parent());
                    }
                }

                // Need to update the post_author_override select if classic editor is used
                if (args2.topic === 'select-author') {
                    const selectedValue = $(this).val();
                    const selectedText = $(this).find('option:selected').text();

                    // Add the selected value to the post_author_override dropdown if not present
                    const $authorSelect = $('#post_author_override');
                    if (selectedValue && !$authorSelect.find(`option[value="${selectedValue}"]`).length) {
                        $authorSelect.append(new Option(selectedText, selectedValue));
                    }

                    $authorSelect.val(selectedValue).trigger('change');
                }
            });
        }

        initializeSelect2(args);
          
        $('#' + args.search_id).on('keydown', function (e) {
            // this will catch pressing enter and call find function
            if (e.keyCode == 13) {
                ajax_request($(this).val());
                e.preventDefault();
            }
        });

        $('input.pp-user-meta-field').on('keydown', function (e) {
            if (e.keyCode == 13) {
                ajax_request($('#' + args.search_id).val());
                e.preventDefault();
            }
        });

        $('#' + args.search_id).next('i.dashicons-search').on('click', function(e) {
            ajax_request($('#' + args.search_id).val());
        });

        $("#" + args.button_id).on('click', function () {
            ajax_request($('#' + args.search_id).val());
        });

        var ajax_request = function (stext) {
            $("#" + args.button_id).closest('div').find('.waiting').show();
            $("#" + args.button_id).prop('disabled', true);
            $("#" + args.search_id).prop('disabled', true);

            if (stext == null || stext == 'undefined') stext = '';

            if ($('#pp_search_role_' + args.topic).length)
                var roletext = $('#pp_search_role_' + args.topic).val();
            else
                var roletext = '';

            umkey = [];
            umval = [];
            for (i = 0; i < 6; i++) {
                if ($('#pp_search_user_meta_key_' + i + '_' + args.topic).length) {
                    umkey[i] = $('#pp_search_user_meta_key_' + i + '_' + args.topic).val();
                    umval[i] = $('#pp_search_user_meta_val_' + i + '_' + args.topic).val();
                } else {
                    umkey[i] = '';
                    umval[i] = '';
                }
            }

            var data = {
                'pp_agent_search': stext,
                'pp_role_search': roletext,
                'pp_agent_type': args.agent_type,
                'pp_agent_id': args.agent_id,
                'pp_topic': args.topic,
                'pp_usermeta_key': umkey,
                'pp_usermeta_val': umval,
                'pp_omit_admins': ppListbox.omit_admins,
                'pp_metagroups': ppListbox.metagroups,
                'pp_operation': args.op,
                'pp_context': args.pp_context
            };

            $.ajax({url: args.ajaxurl, data: data, dataType: "html", success: got_ajax_listbox, error: ajax_failure});
        }

        var got_ajax_listbox = function (data, txtStatus) {
            //Set listbox contents to Ajax response
            $('#' + args.results_id).html(data).show();

            if (typeof document.all == 'undefined') // triggers removal of agents who already have a dropdown (but IE chokes on trigger call)
                $('#' + args.results_id).trigger('jchange');

            $("#" + args.button_id).closest('div').find('.waiting').hide();

            $("#" + args.button_id).prop('disabled', false);
            $("#" + args.search_id).prop('disabled', false);
        }

        var ajax_failure = function (XMLHttpRequest, textStatus, errorThrown) {
            if (!args.debug) return;

            $('#' + args.results_id).html('<option value="0"><b style="color:red">' +
                XMLHttpRequest.status + ':' +
                (textStatus ? textStatus : '') +
                (errorThrown ? errorThrown : '') + '</b></option>');
        }
    }
})(jQuery); 
