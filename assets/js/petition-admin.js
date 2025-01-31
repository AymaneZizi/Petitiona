jQuery(document).ready(function($) {
    // Form field management
    const formFieldsContainer = $('.petitiona-form-fields');
    
    // Toggle required status
    $('.field-required').on('change', function() {
        const fieldName = $(this).data('field');
        const isRequired = $(this).is(':checked');
        $(`input[name="fields[${fieldName}]"]`).prop('required', isRequired);
    });

    // Date controls
    $('input[name="start_type"]').on('change', function() {
        const isScheduled = $(this).val() === 'scheduled';
        $('input[name="start_date"]').prop('disabled', !isScheduled);
    });

    $('input[name="end_type"]').on('change', function() {
        const isScheduled = $(this).val() === 'scheduled';
        $('input[name="end_date"]').prop('disabled', !isScheduled);
    });

    // Email restrictions management
    const restrictionsContainer = $('#email-restrictions-container');

    restrictionsContainer.on('click', '.add-restriction', function() {
        const row = $(this).closest('.restriction-row');
        const newRow = row.clone();
        newRow.find('input').val('');
        newRow.find('.add-restriction')
            .removeClass('add-restriction')
            .addClass('remove-restriction')
            .text('Remove');
        restrictionsContainer.append(newRow);
    });

    restrictionsContainer.on('click', '.remove-restriction', function() {
        $(this).closest('.restriction-row').remove();
    });
    let emailTestTimeout;
    $('#email_restriction').on('input', function() {
        clearTimeout(emailTestTimeout);
        emailTestTimeout = setTimeout(function() {
            const pattern = $('#email_restriction').val();
            if (pattern) {
                try {
                    new RegExp(pattern);
                    $('.email-pattern-preview').removeClass('error').text('Pattern is valid');
                } catch(e) {
                    $('.email-pattern-preview').addClass('error').text('Invalid regex pattern');
                }
            }
        }, 500);
    });

    // Signature list functionality
    const signatureTable = $('#petitiona-signatures');
    if (signatureTable.length) {
        // Petition filter
        $('#petition-filter').on('change', function() {
            const petitionId = $(this).val();
            if (petitionId) {
                window.location.href = `?page=petitiona-signatures&petition_id=${petitionId}`;
            }
        });

        // Export functionality
        $('.export-csv').on('click', function(e) {
            e.preventDefault();
            const petitionId = $('#petition-filter').val();
            if (petitionId) {
                window.location.href = `?page=petitiona-signatures&petition_id=${petitionId}&action=export`;
            }
        });

        // Bulk actions
        $('#doaction').on('click', function(e) {
            e.preventDefault();
            const action = $('#bulk-action-selector-top').val();
            const checkedItems = $('input[name="signature[]"]:checked');
            
            if (action && checkedItems.length) {
                if (action === 'delete' && !confirm('Are you sure you want to delete these signatures?')) {
                    return;
                }
                
                $('#signatures-form').submit();
            }
        });
    }

    // Form validation
    $('#petitiona-form').on('submit', function(e) {
        const startType = $('input[name="start_type"]:checked').val();
        const endType = $('input[name="end_type"]:checked').val();
        
        if (startType === 'scheduled' && !$('input[name="start_date"]').val()) {
            e.preventDefault();
            alert('Please set a start date for scheduled start');
            return;
        }
        
        if (endType === 'scheduled' && !$('input[name="end_date"]').val()) {
            e.preventDefault();
            alert('Please set an end date for scheduled end');
            return;
        }
    });
});
