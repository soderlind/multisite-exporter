/**
 * Multisite Exporter - History Page Scripts
 *
 * Handles all the interactive elements on the export history page.
 */
(function($) {
    'use strict';

    // Initialize the history page functionality
    $(document).ready(function() {
        // Track "select all pages" state
        var allPagesSelected = false;

        // Select/deselect all checkboxes on current page
        $('#cb-select-all').on('click', function() {
            $('input[name="selected_exports[]"]').prop('checked', this.checked);
            updateSelectAllPagesNotice(this.checked);
        });

        // Select all button (current page)
        $('#select-all').on('click', function(e) {
            e.preventDefault();
            // Only allow selecting all if not in "all pages selected" mode
            if (!allPagesSelected) {
                $('input[name="selected_exports[]"]').prop('checked', true);
                $('#cb-select-all').prop('checked', true);
                updateSelectAllPagesNotice(true);
            }
        });

        // Deselect all button 
        $('#deselect-all').on('click', function(e) {
            e.preventDefault();
            // Only allow deselecting if we're not in "all pages selected" mode
            if (!allPagesSelected) {
                $('input[name="selected_exports[]"]').prop('checked', false);
                $('#cb-select-all').prop('checked', false);
                $('#select_all_pages').val(0);
                updateSelectAllPagesNotice(false);
                hideAllSelectedNotice();
            }
        });

        // Select all exports across all pages
        $('#select-across-pages').on('click', function(e) {
            e.preventDefault();
            $('#select_all_pages').val(1);
            allPagesSelected = true;
            showAllSelectedNotice();
            hideSelectAllPagesNotice();
            // Disable both select all and deselect buttons when all pages are selected
            $('#deselect-all, #select-all').addClass('disabled');
        });

        // Clear selection of all exports across pages
        $('#clear-selection').on('click', function(e) {
            e.preventDefault();
            $('#select_all_pages').val(0);
            allPagesSelected = false;
            $('input[name="selected_exports[]"]').prop('checked', false);
            $('#cb-select-all').prop('checked', false);
            hideAllSelectedNotice();
            // Re-enable both buttons
            $('#deselect-all, #select-all').removeClass('disabled');
        });

        // Show notice that all exports on current page are selected
        function updateSelectAllPagesNotice(allChecked) {
            if (allChecked && !allPagesSelected) {
                showSelectAllPagesNotice();
            } else {
                hideSelectAllPagesNotice();
            }
        }

        function showSelectAllPagesNotice() {
            $('#select-all-pages-notice').removeClass('hidden').show();
        }

        function hideSelectAllPagesNotice() {
            $('#select-all-pages-notice').addClass('hidden').hide();
        }

        function showAllSelectedNotice() {
            $('#all-selected-notice').removeClass('hidden').show();
        }

        function hideAllSelectedNotice() {
            $('#all-selected-notice').addClass('hidden').hide();
        }

        // Update header checkbox when individual checkboxes change
        $('input[name="selected_exports[]"]').on('change', function() {
            var allChecked = $('input[name="selected_exports[]"]:checked').length === $('input[name="selected_exports[]"]').length;
            $('#cb-select-all').prop('checked', allChecked);
            updateSelectAllPagesNotice(allChecked);

            // If individual checkboxes are unchecked, we're not in "all pages selected" mode anymore
            if (!$(this).prop('checked') && allPagesSelected) {
                allPagesSelected = false;
                $('#select_all_pages').val(0);
                hideAllSelectedNotice();
                // Re-enable both buttons
                $('#deselect-all, #select-all').removeClass('disabled');
            }
        });

        // Handle manual page input submission
        $('#current-page-selector').keydown(function(e) {
            if (e.keyCode === 13) { // Enter key
                e.preventDefault();
                var page = parseInt($(this).val());
                
                // Get total pages from the data attribute we'll add
                var totalPages = parseInt($(this).data('total-pages'));
                
                if (isNaN(page) || page < 1) {
                    page = 1;
                } else if (page > totalPages) {
                    page = totalPages;
                }

                // Get base URL from the data attribute we'll add
                var baseUrl = $(this).data('base-url');
                var url = baseUrl + '&paged=' + page;
                window.location.href = url;
            }
        });
    });
})(jQuery);