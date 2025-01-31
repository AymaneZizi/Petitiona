jQuery(document).ready(function($) {
    const petitionForm = $('.petitiona-form');

    petitionForm.on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const submitButton = form.find('button[type="submit"]');
        const messageDiv = form.find('.petitiona-message');
        const emailInput = form.find('input[type="email"]');
        const allowedDomains = emailInput.data('allowed-domains');

        // Email domain validation
        if (allowedDomains && allowedDomains.length) {
            const email = emailInput.val();
            const domain = email.substring(email.lastIndexOf('@'));
            if (!allowedDomains.includes(domain)) {
                messageDiv.removeClass('success')
                         .addClass('error')
                         .html('Please use an email from allowed domains: ' + allowedDomains.join(', '))
                         .show();
                return;
            }
        }

        // Disable submit button and show loading state
        submitButton.prop('disabled', true).text('Signing...');
        messageDiv.removeClass('success error').hide();

        // Collect form data
        const formData = new FormData(this);
        formData.append('action', 'petitiona_sign');
        formData.append('nonce', petitionaAjax.nonce);

        // Send AJAX request
        $.ajax({
            url: petitionaAjax.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    if (response.data.percentage >= 100) {
                        // Show completion message
                        $('.petitiona-progress').html(
                            '<div class="petitiona-completed">' +
                            '<h3>✓ Completed</h3>' +
                            '<p>This petition made change with ' + response.data.new_count.toLocaleString() + ' supporters!</p>' +
                            '</div>'
                        );
                    } else {
                        // Update count and progress bar
                        $('.petitiona-count .current').text(response.data.new_count.toLocaleString());
                        $('.petitiona-progress-fill').css('width', response.data.percentage + '%');
                    }
                    form.replaceWith('<div class="petitiona-success">Thank you for signing the petition!</div>');
                } else {
                    messageDiv.removeClass('success')
                             .addClass('error')
                             .html(response.data)
                             .slideDown();
                }
            },
            error: function(xhr, status, error) {
                console.error('Ajax error:', {xhr, status, error});
                messageDiv.removeClass('success')
                         .addClass('error')
                         .html('An error occurred. Please try again later.')
                         .slideDown();
            },
            complete: function() {
                submitButton.prop('disabled', false).text('Sign Now');
            }
        });
    });

    // Real-time email validation
    const emailInput = petitionForm.find('input[type="email"]');
    if (emailInput.length) {
        emailInput.on('input', function() {
            const email = $(this).val();
            const allowedDomains = $(this).data('allowed-domains');
            
            if (allowedDomains && allowedDomains.length && email.includes('@')) {
                const domain = email.substring(email.lastIndexOf('@'));
                if (!allowedDomains.includes(domain)) {
                    $(this).addClass('invalid');
                    $(this).next('.validation-message')
                           .text('Please use an allowed email domain: ' + allowedDomains.join(', '))
                           .show();
                } else {
                    $(this).removeClass('invalid');
                    $(this).next('.validation-message').hide();
                }
            }
        });
    }

    // Phone number formatting
    const phoneInput = petitionForm.find('input[type="tel"]');
    if (phoneInput.length) {
        phoneInput.on('input', function() {
            let number = $(this).val().replace(/[^\d]/g, '');
            if (number.length > 10) number = number.substr(0, 10);
            
            if (number.length > 6) {
                number = number.replace(/(\d{3})(\d{3})(\d{4})/, '($1) $2-$3');
            } else if (number.length > 3) {
                number = number.replace(/(\d{3})(\d{3})/, '($1) $2');
            } else if (number.length > 0) {
                number = number.replace(/(\d{3})/, '($1)');
            }
            
            $(this).val(number);
        });
    }

    // Comment field character counter
    const commentField = petitionForm.find('textarea[name="comment"]');
    if (commentField.length) {
        const maxLength = commentField.attr('maxlength') || 500;
        const counter = $('<div class="character-counter">0/' + maxLength + ' characters</div>');
        commentField.after(counter);
        
        commentField.on('input', function() {
            const remaining = maxLength - $(this).val().length;
            counter.text($(this).val().length + '/' + maxLength + ' characters');
            counter.toggleClass('warning', remaining < 50);
        });
    }
});