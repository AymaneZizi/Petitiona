<?php
// admin/petition-form.php

if (!defined('ABSPATH')) exit;

$petition_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$petition = null;

if ($petition_id) {
    $database = Petitiona\Database::getInstance();
    $petition = $database->getPetition($petition_id);
}
?>

<div class="wrap">
    <h1><?php echo $petition_id ? 'Edit Petition' : 'Create New Petition'; ?></h1>
    
<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <input type="hidden" name="action" value="save_petition">
    <table class="form-table">
            <tr>
                <th><label for="title">Title</label></th>
                <td>
                    <input type="text" id="title" name="title" class="regular-text" 
                           value="<?php echo esc_attr($petition ? $petition->title : ''); ?>" required>
                </td>
            </tr>
            
            <tr>
                <th><label for="content">Content</label></th>
                <td>
                    <?php 
                    wp_editor(
                        $petition ? $petition->content : '',
                        'petition_content',
                        array('textarea_name' => 'content')
                    );
                    ?>
                </td>
            </tr>
            
            <tr>
                <th><label for="goal">Signature Goal</label></th>
                <td>
                    <input type="number" id="goal" name="goal_signatures" 
                           value="<?php echo esc_attr($petition ? $petition->goal_signatures : ''); ?>" required>
                </td>
            </tr>
            
            <tr>
                <th><label>Start Date</label></th>
                <td>
                    <label>
                        <input type="radio" name="start_type" value="immediate" checked> 
                        Start immediately
                    </label>
                    <br>
                    <label>
                        <input type="radio" name="start_type" value="scheduled">
                        Schedule start:
                        <input type="datetime-local" name="start_date" 
                               value="<?php echo esc_attr($petition ? $petition->start_date : ''); ?>">
                    </label>
                </td>
            </tr>
            
            <tr>
                <th><label>End Date</label></th>
                <td>
                    <label>
                        <input type="radio" name="end_type" value="never" checked> 
                        Never end
                    </label>
                    <br>
                    <label>
                        <input type="radio" name="end_type" value="scheduled">
                        Schedule end:
                        <input type="datetime-local" name="end_date" 
                               value="<?php echo esc_attr($petition ? $petition->end_date : ''); ?>">
                    </label>
                </td>
            </tr>
            
            <tr>
                <th><label>Form Fields</label></th>
                <td>
                    <?php
                    $fields = array(
                        'email' => 'Email',
                        'firstname' => 'First Name',
                        'lastname' => 'Last Name',
                        'location' => 'Location',
                        'address' => 'Address',
                        'country' => 'Country',
                        'phone' => 'Phone Number',
                        'comment' => 'Comment'
                    );
                    
                    $saved_fields = $petition ? json_decode($petition->form_fields, true) : array();
                    
                    foreach ($fields as $field_key => $field_label) {
                        $checked = isset($saved_fields[$field_key]) ? 'checked' : '';
                        $required = isset($saved_fields[$field_key]) && $saved_fields[$field_key] ? 'checked' : '';
                        ?>
                        <div class="field-option">
                            <label>
                                <input type="checkbox" name="fields[<?php echo esc_attr($field_key); ?>]" 
                                       value="1" <?php echo esc_attr($checked); ?>>
                                <?php echo esc_html($field_label); ?>
                            </label>
                            <label class="required-checkbox">
                                <input type="checkbox" name="required[<?php echo esc_attr($field_key); ?>]" 
                                       <?php echo esc_attr($required); ?>>
                                <?php esc_html_e('Required', 'Petitiona'); ?>
                            </label>
                        </div>
                        <?php
                    }
                    ?>
                </td>
            </tr>
            
            <tr>
                <th><label for="email_restrictions">Email Domain Restrictions</label></th>
                <td>
                    <div id="email-restrictions-container">
                        <?php
                        $restrictions = $petition ? json_decode($petition->email_restrictions, true) : array();
                        if (!empty($restrictions)) {
                            foreach ($restrictions as $restriction) {
                                echo '<div class="restriction-row">';
                                echo '<input type="text" name="email_restrictions[]" value="' . esc_attr($restriction) . '" class="regular-text" readonly>';
                                echo '<button type="button" class="button remove-restriction">Remove</button>';
                                echo '</div>';
                            }
                        }
                        ?>
                        <div class="restriction-row">
                            <input type="text" name="email_restrictions[]" class="regular-text" placeholder="e.g., @uni-konstanz.de">
                            <button type="button" class="button add-restriction">Add Another</button>
                        </div>
                    </div>
                    <p class="description">Enter allowed email domains (e.g., @uni-konstanz.de). Leave empty to allow all email domains.</p>
                </td>
            </tr>
        </table>
        
        <?php wp_nonce_field('save_petition', 'petition_nonce'); ?>
        <input type="hidden" name="petition_id" value="<?php echo esc_attr($petition_id); ?>">
        
        <p class="submit">
            <input type="submit" name="submit" class="button button-primary" 
                   value="<?php esc_attr_e('Save Petition', 'Petitiona'); ?>">
        </p>
    </form>
    
    <?php if ($petition_id): ?>
        <div class="shortcode-info">
            <h3>Shortcode</h3>
            <p>Use this shortcode to embed the petition in your posts or pages:</p>
            <code>[petitiona id=<?php echo esc_attr($petition_id); ?>]</code>
        </div>
    <?php endif; ?>

    <script>
        jQuery(document).ready(function($) {
            function isValidEmailDomain(domain) {
                return /^@[a-zA-Z0-9-]+\.[a-zA-Z]{2,}$/.test(domain);
            }

            $('#email-restrictions-container').on('click', '.add-restriction', function() {
                const row = $(this).closest('.restriction-row');
                const input = row.find('input');
                const domain = input.val().trim();

                if (!isValidEmailDomain(domain)) {
                    alert('Invalid domain format. Must start with @ and end with a valid domain (e.g., @uni.kn)');
                    return;
                }

                input.prop('readonly', true);
                $(this).replaceWith('<button type="button" class="button remove-restriction">Remove</button>');

                const newRow = $('<div class="restriction-row">' +
                    '<input type="text" name="email_restrictions[]" class="regular-text" placeholder="e.g., @uni-konstanz.de">' +
                    '<button type="button" class="button add-restriction">Add Another</button>' +
                    '</div>');

                $('#email-restrictions-container').append(newRow);
            });

            $('#email-restrictions-container').on('click', '.remove-restriction', function() {
                $(this).closest('.restriction-row').remove();
            });
        });
    </script>
</div>
