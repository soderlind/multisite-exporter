/**
 * Multisite Exporter - Scheduler Progress Tracking
 *
 * JavaScript for tracking and displaying Action Scheduler progress
 */
(function($) {
    'use strict';

    const SchedulerProgress = {
        // DOM elements
        $progressContainer: null,
        $progressBar: null, 
        $progressPercentage: null,
        $currentSite: null,
        $scheduledInfo: null,

        // Progress check interval (in milliseconds)
        checkInterval: 3000,
        
        // Timer reference for tracking progress
        progressTimer: null,
        
        /**
         * Initialize the scheduler progress tracker
         */
        init: function() {
            // Initialize DOM elements
            this.$progressContainer = $('#multisite-exporter-progress');
            this.$progressBar = this.$progressContainer.find('.me-progress-bar');
            this.$progressPercentage = this.$progressContainer.find('.me-progress-percentage');
            this.$currentSite = this.$progressContainer.find('.me-current-site');
            this.$scheduledInfo = this.$progressContainer.find('.me-scheduled-info');
            
            // Initialize progress check on page load
            this.checkForActiveExports();
            
            // Bind events
            this.bindEvents();
        },
        
        /**
         * Bind event listeners
         */
        bindEvents: function() {
            // Listen for form submission to track new exports
            $('#multisite-exporter-form').on('submit', function(e) {
                // Don't track progress here - the server will handle the export creation 
                // We'll detect active exports in the regular interval check
            });
        },
        
        /**
         * Check for active exports via AJAX
         */
        checkForActiveExports: function() {
            const self = this;
            
            $.ajax({
                url: multisite_exporter_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'me_check_scheduled_exports',
                    security: multisite_exporter_params.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        // We have active exports, show progress and start tracking
                        if (response.data.has_active_exports) {
                            self.showProgressTracker();
                            self.updateProgress(response.data);
                            self.startProgressTracking();
                        }
                    }
                },
                complete: function() {
                    // If not already tracking, check again after a longer interval
                    if (self.progressTimer === null) {
                        setTimeout(function() {
                            self.checkForActiveExports();
                        }, 10000); // Check every 10 seconds for newly started exports
                    }
                }
            });
        },
        
        /**
         * Start tracking progress with regular updates
         */
        startProgressTracking: function() {
            // Clear any existing timer
            if (this.progressTimer !== null) {
                clearInterval(this.progressTimer);
            }
            
            const self = this;
            
            // Start new timer for regular progress updates
            this.progressTimer = setInterval(function() {
                self.checkProgressUpdate();
            }, this.checkInterval);
        },
        
        /**
         * Check for progress updates via AJAX
         */
        checkProgressUpdate: function() {
            const self = this;
            
            $.ajax({
                url: multisite_exporter_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'me_check_scheduled_progress',
                    security: multisite_exporter_params.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        // Update the progress display
                        self.updateProgress(response.data);
                        
                        // If no active exports, stop tracking
                        if (!response.data.has_active_exports) {
                            self.stopProgressTracking();
                            
                            // If we have a completed export, show completion message
                            if (response.data.status === 'completed') {
                                self.showCompletionMessage(response.data);
                            } else {
                                // Hide progress after a delay
                                setTimeout(function() {
                                    self.hideProgressTracker();
                                }, 5000);
                            }
                        }
                    }
                }
            });
        },
        
        /**
         * Update progress display with the latest data
         * 
         * @param {object} data Progress data from the server
         */
        updateProgress: function(data) {
            // Update progress bar
            const percentage = data.percentage || 0;
            this.$progressBar.css('width', percentage + '%');
            this.$progressPercentage.text(percentage + '%');
            
            // Update current site info
            if (data.current_site) {
                this.$currentSite.text(data.current_site_message + ': ' + data.current_site);
            } else {
                this.$currentSite.text('');
            }
            
            // Update scheduled info
            if (data.scheduled_info) {
                this.$scheduledInfo.text(data.scheduled_info);
            } else {
                this.$scheduledInfo.text('');
            }
        },
        
        /**
         * Stop tracking progress
         */
        stopProgressTracking: function() {
            if (this.progressTimer !== null) {
                clearInterval(this.progressTimer);
                this.progressTimer = null;
            }
        },
        
        /**
         * Show the progress tracker UI
         */
        showProgressTracker: function() {
            this.$progressContainer.show();
        },
        
        /**
         * Hide the progress tracker UI
         */
        hideProgressTracker: function() {
            this.$progressContainer.hide();
        },
        
        /**
         * Show completion message
         * 
         * @param {object} data Completion data
         */
        showCompletionMessage: function(data) {
            // Set progress to 100%
            this.$progressBar.css('width', '100%');
            this.$progressPercentage.text('100%');
            
            // Show completion message
            if (data.completion_message) {
                // Add completion message as a notice
                const $notice = $('<div class="notice notice-success"><p>' + data.completion_message + '</p></div>');
                this.$progressContainer.append($notice);
                
                // If we have a redirect URL, redirect after a delay
                if (data.redirect_url) {
                    setTimeout(function() {
                        window.location.href = data.redirect_url;
                    }, 3000);
                }
            }
        }
    };
    
    // Initialize scheduler progress tracking when document is ready
    $(document).ready(function() {
        SchedulerProgress.init();
    });
    
})(jQuery);