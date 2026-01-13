/**
 * Sample Lesson Viewer for LearnDash
 * JavaScript functionality
 */

(function() {
    'use strict';

    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        initVideoCards();
    });

    /**
     * Initialize video card functionality
     */
    function initVideoCards() {
        var videoContainers = document.querySelectorAll('.slv-video-wrapper');

        videoContainers.forEach(function(container) {
            var iframe = container.querySelector('iframe');
            var video = container.querySelector('video');

            // Add lazy loading for iframes
            if (iframe && !iframe.hasAttribute('loading')) {
                iframe.setAttribute('loading', 'lazy');
            }

            // Add poster support for self-hosted videos
            if (video) {
                video.setAttribute('preload', 'metadata');
            }
        });

        // Pause other videos when one starts playing (for self-hosted videos)
        var allVideos = document.querySelectorAll('.slv-video-wrapper video');
        allVideos.forEach(function(video) {
            video.addEventListener('play', function() {
                allVideos.forEach(function(otherVideo) {
                    if (otherVideo !== video && !otherVideo.paused) {
                        otherVideo.pause();
                    }
                });
            });
        });
    }

})();
