jQuery(document).ready(function($) {
    // Capture pagination link clicks
    $(document).on('click', '.page-numbers a', function(e) {
        e.preventDefault(); // Prevent default behavior

        var page = $(this).attr('href').split('page/')[1]; // Extract page number from URL
        var data = {
            action: 'articles_api_fetcher_ajax_pagination', // Match with PHP handler action
            page: page, // Pass the page number
        };

        // Show loading message (optional)
        $('#articles-api-fetcher-container').html('<p>Loading...</p>');

        // Send the AJAX request
        $.post(customPluginAjax.ajaxurl, data, function(response) {
            // Replace content with the new posts
            $('#articles-api-fetcher-container').html(response);
        });
    });
});
