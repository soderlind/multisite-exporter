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
        var isAllPagesSelected = false;
        var totalExports = parseInt($('#me-total-exports').data('total')) || 0;
        
        // Initialize the counter
        updateSelectedCounter();
        
        // Toggle individual checkboxes when "Select All" is clicked
        $selectAllCheckbox.on('change', function() {
            var isChecked = $(this).prop('checked');
            $individualCheckboxes.prop('checked', isChecked);
            
            if (!isChecked) {
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
    });
})(jQuery);