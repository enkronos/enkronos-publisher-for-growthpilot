jQuery(document).ready(function($) {
    // Add copy to clipboard functionality for API keys
    $('.enkrpufo-pub-admin code').on('click', function() {
        const text = $(this).text();
        const $temp = $('<textarea>');
        $('body').append($temp);
        $temp.val(text).select();
        document.execCommand('copy');
        $temp.remove();
        
        const $notice = $('<span class="copied-notice">Copied!</span>');
        $(this).after($notice);
        setTimeout(() => $notice.fadeOut(300, () => $notice.remove()), 1500);
    });
    
    // Confirm before revoking keys
    $('form[action*="enkrpufo_revoke_key"]').on('submit', function(e) {
        if (!confirm('Are you sure you want to revoke this API key? This action cannot be undone.')) {
            e.preventDefault();
            return false;
        }
    });
    
    // Confirm before deleting logs
    $('form[action*="enkrpufo_delete_logs"]').on('submit', function(e) {
        if (!confirm('Are you sure you want to delete all logs? This action cannot be undone.')) {
            e.preventDefault();
            return false;
        }
    });
});