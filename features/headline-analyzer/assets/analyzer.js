jQuery(document).ready(function($) {
    // Only run on headline analyzer pages
    if (!$('body').hasClass('smark-headline-analyzer-page')) {
        return;
    }
    'use strict';
    
    // Elements
    const $headlineInput = $('#headline_input');
    const $analyzeBtn = $('#analyze_btn');
    const $resultsSection = $('#results_section');
    const $characterCounter = $('.current-count');
    
    // Update character counter - use multiple events for better compatibility
    $headlineInput.on('input keyup paste', function() {
        const length = $(this).val().length;
        $characterCounter.text(length);
    });
    
    // Analyze button click
    $analyzeBtn.on('click', function() {
        const headline = $headlineInput.val().trim();
        
        if (headline === '') {
            alert(smarkHeadlineAnalyzer.strings.error);
            $headlineInput.focus();
            return;
        }
        
        analyzeHeadline(headline);
    });
    
    // Allow Enter key to submit
    $headlineInput.on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            $analyzeBtn.click();
        }
    });
    
    /**
     * Analyze headline via AJAX
     */
    function analyzeHeadline(headline) {
        // Disable button and show loading state
        $analyzeBtn.prop('disabled', true).addClass('loading');
        $analyzeBtn.find('span:not(.dashicons)').text(smarkHeadlineAnalyzer.strings.analyzing);
        
        // Hide previous results
        $resultsSection.slideUp(300);
        
        // Send AJAX request
        $.ajax({
            url: smarkHeadlineAnalyzer.ajaxUrl,
            type: 'POST',
            data: {
                action: 'SMARK_analyze_headline',
                nonce: smarkHeadlineAnalyzer.nonce,
                headline: headline
            },
            success: function(response) {
                if (response.success) {
                    displayResults(response.data);
                } else {
                    alert(response.data.message || smarkHeadlineAnalyzer.strings.error);
                }
            },
            error: function() {
                alert(smarkHeadlineAnalyzer.strings.error);
            },
            complete: function() {
                // Re-enable button
                $analyzeBtn.prop('disabled', false).removeClass('loading');
                $analyzeBtn.find('span:not(.dashicons)').text(smarkHeadlineAnalyzer.strings.analyze);
            }
        });
    }
    
    /**
     * Display analysis results
     */
    function displayResults(data) {
        // Update score
        const score = data.score || 0;
        $('#score_value').text(score);
        
        // Calculate circle progress
        const circumference = 314; // 2 * PI * 50 (radius)
        const progress = circumference - (circumference * score) / 100;
        
        // Add gradient definition to SVG if not exists
        if ($('#scoreGradient').length === 0) {
            const svg = $('#score_circle').parent();
            const defs = $('<defs></defs>');
            const gradient = $('<linearGradient id="scoreGradient" x1="0%" y1="0%" x2="100%" y2="100%"></linearGradient>');
            gradient.append('<stop offset="0%" style="stop-color:#667eea;stop-opacity:1" />');
            gradient.append('<stop offset="100%" style="stop-color:#764ba2;stop-opacity:1" />');
            defs.append(gradient);
            svg.prepend(defs);
        }
        
        // Animate score circle
        $('#score_circle').css('stroke-dashoffset', progress);
        
        // Update details
        $('#char_count').text(data.char_count);
        $('#word_count').text(data.word_count);
        
        // Update has numbers with color coding
        const $hasNumbers = $('#has_numbers');
        if (data.has_numbers) {
            $hasNumbers.text('Yes ✓').removeClass('status-error').addClass('status-success');
        } else {
            $hasNumbers.text('No ✗').removeClass('status-success').addClass('status-error');
        }
        
        // Update Gains & Pains with AI analysis
        const $hasGainsPains = $('#has_gains_pains');
        const $explanation = $('#gains_pains_explanation');
        const $explanationText = $('#explanation_text');
        
        if (data.gains_pains_error) {
            // Show error state
            $hasGainsPains.text('Error').removeClass('status-success').addClass('status-error');
            $explanation.hide();
        } else if (data.has_gains_pains) {
            $hasGainsPains.text('Yes ✓').removeClass('status-error').addClass('status-success');
            $explanationText.text(data.gains_pains_explanation);
            $explanation.slideDown(300);
        } else {
            $hasGainsPains.text('No ✗').removeClass('status-success').addClass('status-error');
            $explanationText.text(data.gains_pains_explanation);
            $explanation.slideDown(300);
        }
        
        // Show results section with animation
        $resultsSection.slideDown(500);
        
        // Scroll to results
        $('html, body').animate({
            scrollTop: $resultsSection.offset().top - 100
        }, 500);
    }
    
    /**
     * Initialize
     */
    function init() {
        // Set focus on headline input
        $headlineInput.focus();
    }
    
    // Initialize on page load
    init();
});

// Fallback: Pure JavaScript character counter (in case jQuery doesn't load)
(function() {
    'use strict';
    
    function updateCharacterCount() {
        const input = document.getElementById('headline_input');
        const counter = document.querySelector('.current-count');
        
        if (input && counter) {
            const updateCount = function() {
                counter.textContent = input.value.length;
            };
            
            input.addEventListener('input', updateCount);
            input.addEventListener('keyup', updateCount);
            input.addEventListener('paste', updateCount);
            
            // Initial update
            updateCount();
        }
    }
    
    // Try to initialize immediately
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', updateCharacterCount);
    } else {
        updateCharacterCount();
    }
})();

