/**
 * Multisite Exporter Admin JavaScript
 * 
 * Handles multi-page selection and download of exports
 */
(function($) {
    'use strict';
    
    $(document).ready(function() {
        var $selectAllCheckbox = $('#me-select-all');
        var $selectAllPagesToggle = $('#me-select-all-pages');
        var $selectAllPagesInfo = $('.me-select-all-pages-info');
        var $individualCheckboxes = $('.me-export-checkbox');
        var $downloadButton = $('#me-download-selected');
        var $selectCounter = $('.me-selected-count');
        var $sitesTable = $('.me-sites-table');
        var isAllPagesSelected = false;
        var totalExports = parseInt($('#me-total-exports').data('total')) || 0;
        var isExportInProgress = false;
        
        // Check if export is in progress
        checkExportProgress();
        
        // Initialize the counter
        updateSelectedCounter();
        
        // Function to check export progress
        function checkExportProgress() {
            if (typeof multisite_exporter_params !== 'undefined') {
                $.ajax({
                    url: multisite_exporter_params.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'me_check_scheduled_exports',
                        security: multisite_exporter_params.nonce
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            // If exports are in progress, hide the sites table
                            if (response.data.has_active_exports) {
                                isExportInProgress = true;
                                $sitesTable.hide();
                                
                                // Check again after a delay
                                setTimeout(checkExportProgress, 5000);
                            } else if (response.data.status === 'completed') {
                                // Show the sites table when exports are completed
                                isExportInProgress = false;
                                $sitesTable.show();
                            } else {
                                // No active exports and not completed, show the table
                                isExportInProgress = false;
                                $sitesTable.show();
                            }
                        } else {
                            // No response or error, show the table as default
                            isExportInProgress = false;
                            $sitesTable.show();
                        }
                    },
                    error: function() {
                        // On error, show the table as default
                        isExportInProgress = false;
                        $sitesTable.show();
                    }
                });
            } else {
                // If progress params not available, show the table
                $sitesTable.show();
            }
        }
        
        // Toggle individual checkboxes when "Select All" is clicked
        $selectAllCheckbox.on('change', function() {
            var isChecked = $(this).prop('checked');
            $individualCheckboxes.prop('checked', isChecked);
            
            if (isChecked) {
                // When checking "Select All", always show the "Select All Pages" option
                $selectAllPagesToggle.show();
                $selectAllPagesToggle.parent().show();
            } else {
                // If unchecking, also uncheck select all pages
                $selectAllPagesToggle.prop('checked', false);
                isAllPagesSelected = false;
                $selectAllPagesInfo.hide();
            }
            
            updateSelectedCounter();
            updateDownloadButtonState();
        });
        
        // Toggle "Select All Pages" info
        $selectAllPagesToggle.on('change', function() {
            isAllPagesSelected = $(this).prop('checked');
            
            if (isAllPagesSelected) {
                // Check the regular select all checkbox too
                $selectAllCheckbox.prop('checked', true);
                $individualCheckboxes.prop('checked', true);
                $selectAllPagesInfo.show();
            } else {
                $selectAllPagesInfo.hide();
            }
            
            updateSelectedCounter();
            updateDownloadButtonState();
        });
        
        // Update select all checkbox when individual checkboxes change
        $individualCheckboxes.on('change', function() {
            var allChecked = ($individualCheckboxes.length === $individualCheckboxes.filter(':checked').length);
            $selectAllCheckbox.prop('checked', allChecked);
            
            // If manually unchecking any box, disable select all pages
            if (!$(this).prop('checked') && isAllPagesSelected) {
                $selectAllPagesToggle.prop('checked', false);
                isAllPagesSelected = false;
                $selectAllPagesInfo.hide();
            }
            
            // Always show the "Select All Pages" option when all checkboxes are checked
            if (allChecked) {
                $selectAllPagesToggle.show();
                $selectAllPagesToggle.parent().show();
            } else {
                // Hide the toggle when not all checkboxes are checked
                $selectAllPagesToggle.prop('checked', false);
            }
            
            updateSelectedCounter();
            updateDownloadButtonState();
        });
        
        // Handle the download button click
        $downloadButton.on('click', function(e) {
            e.preventDefault();
            
            var selectedExports = [];
            
            // If not selecting all pages, collect the checked exports
            if (!isAllPagesSelected) {
                $individualCheckboxes.filter(':checked').each(function() {
                    selectedExports.push($(this).val());
                });
            }
            
            // Send the download request
            var formData = new FormData();
            formData.append('action', 'me_download_selected_exports');
            formData.append('me_download_nonce', me_admin_vars.download_nonce);
            formData.append('select_all_pages', isAllPagesSelected ? '1' : '0');
            
            // Only append selected exports if we're not selecting all pages
            if (!isAllPagesSelected) {
                for (var i = 0; i < selectedExports.length; i++) {
                    formData.append('selected_exports[]', selectedExports[i]);
                }
            }
            
            // Create and submit a form to handle the download
            var $form = $('<form>')
                .attr('method', 'post')
                .attr('action', me_admin_vars.ajax_url)
                .css('display', 'none');
            
            // Convert FormData to form inputs
            for (var pair of formData.entries()) {
                if (Array.isArray(pair[1]) || pair[0].endsWith('[]')) {
                    // Handle array values
                    for (var i = 0; i < pair[1].length; i++) {
                        $('<input>').attr({
                            type: 'hidden',
                            name: pair[0],
                            value: pair[1][i]
                        }).appendTo($form);
                    }
                } else {
                    // Handle scalar values
                    $('<input>').attr({
                        type: 'hidden',
                        name: pair[0],
                        value: pair[1]
                    }).appendTo($form);
                }
            }
            
            $('body').append($form);
            $form.submit();
        });
        
        // Update the counter based on current selection
        function updateSelectedCounter() {
            var count = isAllPagesSelected ? totalExports : $individualCheckboxes.filter(':checked').length;
            $selectCounter.text(count);
        }
        
        // Enable/disable download button based on selection state
        function updateDownloadButtonState() {
            var hasSelection = isAllPagesSelected || $individualCheckboxes.filter(':checked').length > 0;
            $downloadButton.prop('disabled', !hasSelection);
            
            if (hasSelection) {
                $downloadButton.removeClass('button-disabled').addClass('button-primary');
            } else {
                $downloadButton.addClass('button-disabled').removeClass('button-primary');
            }
        }

        // Initialize Select2 on content select if available
        if ($.fn.select2 && $('#me-content-select').length) {
            $('#me-content-select').select2({
                placeholder: 'Select content types',
                allowClear: true,
                width: '100%'
            }).on('select2:select', function(e) {
                var data = e.params.data;
                var $select = $(this);
                var values = $select.val() || [];
                
                // If "All Content" is selected, clear all other selections
                if (data.id === 'all') {
                    $select.val(['all']).trigger('change');
                } 
                // If something else is selected and "All Content" was previously selected, remove "All Content"
                else if (values.includes('all')) {
                    values = values.filter(value => value !== 'all');
                    $select.val(values).trigger('change');
                }
            });
            
            // Handle form submission to ensure content types are properly sent
            $('#multisite-exporter-form').on('submit', function(e) {
                var $contentSelect = $('#me-content-select');
                var selectedValues = $contentSelect.val();
                
                // Ensure we have at least one selection
                if (!selectedValues || selectedValues.length === 0) {
                    // If nothing selected, default to "all"
                    $contentSelect.val(['all']).trigger('change');
                }
                
                // Continue with form submission
                return true;
            });
        }
    });
})(jQuery);