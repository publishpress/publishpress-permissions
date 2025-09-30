jQuery(document).ready(function($){$(document).on('click','#authordiv a.pp-add-author',function(){$('#post_author_override').hide();$('#pp_author_search').show();$('#authordiv a.pp-add-author').hide();$('#authordiv a.pp-close-add-author').show();$('#agent_search_text_select-author').focus();return false});$(document).on('click','#authordiv a.pp-close-add-author',function(){$('#pp_author_search').hide();$('#authordiv a.pp-close-add-author').hide();$('#authordiv a.pp-add-author').show();$('#post_author_override').show();return false});$(document).on('click','#select_agents_select-author',function(){var selected_id=$('#agent_results_select-author').val();if(selected_id){if(!$('#post_author_override option[value="'+selected_id+'"]').prop('selected',true).length){var selected_name=$('#agent_results_select-author option:selected').html();$('#post_author_override').append('<option value='+selected_id+'>'+selected_name+'</option>');$('#post_author_override option[value="'+selected_id+'"]').prop('selected',true)}}$('#authordiv a.pp-close-add-author').trigger('click');return false});$(document).on('jchange','#agent_results_select-author',function(){if($('#agent_results_select-author option').length){$('#agent_results_select-author').show();$('#select_agents_select-author').show()}});if(typeof window.ppEditorConfig!=='undefined'&&window.ppEditorConfig.defaultPrivacy){var defaultPrivacy=window.ppEditorConfig.defaultPrivacy;var forceDefaultPrivacy=window.ppEditorConfig.forceDefaultPrivacy;var visibility;switch(defaultPrivacy){case'private':visibility='private';break;default:visibility='draft';break}if(visibility&&typeof wp!=='undefined'){var applyDefaultPrivacy=function(){if(!wp.data||!wp.data.select||!wp.data.dispatch){setTimeout(applyDefaultPrivacy,200);return}var currentPost=wp.data.select('core/editor').getCurrentPost();if(!currentPost||!currentPost.type){setTimeout(applyDefaultPrivacy,200);return}try{wp.data.dispatch('core/editor').editPost({status:visibility});if(wp.data.dispatch('core/editor').savePost){wp.data.dispatch('core/editor').savePost()}if(forceDefaultPrivacy){var previousStatus=visibility;wp.data.subscribe(function(){var currentPost=wp.data.select('core/editor').getCurrentPost();if(currentPost&&currentPost.status&&currentPost.status!==previousStatus){wp.data.dispatch('core/editor').editPost({status:previousStatus})}});var lockPostPanel=function(){var style=document.createElement('style');style.textContent=`
                                /* Disable visibility controls but keep them visible */
                                .editor-post-status .components-panel__row:has([aria-label*="Visibility"]) *,
                                .editor-post-status .components-panel__row:has([aria-label*="visibility"]) *,
                                .editor-post-visibility *,
                                .components-button[aria-label*="Change post visibility"],
                                .components-button[aria-label*="visibility"],
                                .editor-post-visibility__toggle,
                                .editor-post-visibility button,
                                .edit-post-post-status button,
                                .edit-post-post-status .components-button,
                                .editor-post-status button,
                                .editor-post-status .components-button,
                                .components-panel__row button[aria-expanded],
                                .edit-post-post-status .components-panel__row:nth-child(2) *,
                                .edit-post-post-status .components-panel__row:nth-child(2) button,
                                .edit-post-post-status .components-panel__row:nth-child(2) .components-button {
                                    pointer-events: none !important;
                                    opacity: 0.5 !important;
                                    cursor: not-allowed !important;
                                }
                                
                                /* Style the locked panel */
                                .editor-post-status .components-panel__row:has([aria-label*="Visibility"]),
                                .editor-post-status .components-panel__row:has([aria-label*="visibility"]),
                                .edit-post-post-status .components-panel__row:nth-child(2) {
                                    background-color: #fff3cd !important;
                                    border: 1px solid #ffeaa7 !important;
                                    border-radius: 4px !important;
                                    position: relative !important;
                                }
                                
                                /* Add lock notice */
                                .pp-visibility-lock-notice {
                                    background: #fff3cd;
                                    border: 1px solid #ffeaa7;
                                    border-radius: 4px;
                                    padding: 8px 12px;
                                    margin: 8px 0;
                                    font-size: 12px;
                                    color: #856404;
                                    position: relative;
                                }
                            `;document.head.appendChild(style);var addLockNotice=function(){if($('.pp-visibility-lock-notice').length===0){var translations=window.ppEditorConfig&&window.ppEditorConfig.translations||{};var visibilityLockedText=translations.visibilityLocked||'Visibility Locked:';var visibilitySetToText=translations.visibilitySetTo||'Post visibility is set to';var cannotChangeText=translations.cannotChange||'and cannot be changed due to admin settings.';var contactAdminText=translations.contactAdmin||'Contact your administrator to modify this setting.';var notice='<div class="pp-visibility-lock-notice"><span class="dashicons dashicons-lock"></span>'+'<strong>'+visibilityLockedText+'</strong> '+visibilitySetToText+' "'+defaultPrivacy+'" '+cannotChangeText+' '+'<br><small>'+contactAdminText+'</small>'+'</div>';if($('.edit-post-post-status').length){$('.edit-post-post-status').prepend(notice)}else if($('.editor-post-status').length){$('.editor-post-status').prepend(notice)}else if($('.components-panel').length){$('.components-panel').first().prepend(notice)}}};setTimeout(addLockNotice,500);setTimeout(addLockNotice,1500);setTimeout(addLockNotice,3e3);$(document).on('click','.components-panel__body-toggle',function(){setTimeout(addLockNotice,100)});var preventVisibilityClicks=function(){var visibilitySelectors=['.editor-post-visibility button','.editor-post-visibility .components-button','.editor-post-visibility__toggle','.components-button[aria-label*="visibility"]','.components-button[aria-label*="Visibility"]','.components-button[aria-label*="Change post visibility"]','.edit-post-post-status button','.edit-post-post-status .components-button','.editor-post-status button','.editor-post-status .components-button','.components-panel__row button[aria-expanded]'];visibilitySelectors.forEach(function(selector){$(document).off('click',selector).on('click',selector,function(e){e.preventDefault();e.stopPropagation();e.stopImmediatePropagation();return false})})};preventVisibilityClicks();setTimeout(preventVisibilityClicks,1e3);setTimeout(preventVisibilityClicks,3e3)};setTimeout(lockPostPanel,1e3)}}catch(e){console.error('Error applying default privacy:',e);setTimeout(applyDefaultPrivacy,500)}};applyDefaultPrivacy()}}});