<?php
if (!defined('ABSPATH')) exit;

$percentage = $petition->goal_signatures > 0 ? min(100, ($signature_count / $petition->goal_signatures) * 100) : 0;
$is_completed = ($signature_count >= $petition->goal_signatures) || $petition->status === 'completed';
?>
<div class="petitiona-container" data-petition-id="<?php echo esc_attr($petition->id); ?>">
   <h2><?php echo esc_html($petition->title); ?></h2>
   <div class="petitiona-content"><?php echo wp_kses_post($petition->content); ?></div>
   
   <div class="petitiona-progress">
       <?php if ($is_completed): ?>
           <div class="petitiona-completed">
               <h3>✓ Completed</h3>
               <p>This petition made change with <?php echo esc_html(number_format($signature_count)); ?> supporters!</p>
           </div>
       <?php else: ?>
        <div class="petitiona-count">
            <div class="petitiona-count-wrapper">
                <span class="current"><?php echo esc_html(number_format($signature_count)); ?></span>
                <span class="petitiona-count-label">Signatures</span>
            </div>
            <div class="petitiona-goal-wrapper">
                <span class="goal"><?php echo esc_html(number_format($petition->goal_signatures)); ?></span>
                <span class="petitiona-goal-label">Next Goal</span>
            </div>
        </div>
        <div class="petitiona-bar">
            <div class="petitiona-progress-fill" style="width: <?php echo esc_attr($percentage); ?>%"></div>
        </div>
       <?php endif; ?>
   </div>
   <?php if (!$is_completed): ?>
   <form id="petitiona-form-<?php echo esc_attr($petition->id); ?>" class="petitiona-form" method="post">
    <?php wp_nonce_field('petitiona-signature-nonce', 'nonce'); ?>
    <input type="hidden" name="petition_id" value="<?php echo esc_attr($petition->id); ?>">
    <input type="hidden" name="action" value="petitiona_sign">

    <div class="petitiona-regular-fields">
        <?php 
        foreach ($form_fields as $field => $required):
            if ($field !== 'email'):
                $field_id = 'petitiona-' . esc_attr($field);
                $input_class = $field === 'comment' ? 'petitiona-textarea' : 'petitiona-input';
        ?>
            <div class="petitiona-field">
                <label for="<?php echo esc_attr($field_id); ?>">
                    <?php echo esc_html(ucfirst(str_replace('_', ' ', $field))); ?>
                    <?php if ($required): ?><span class="required">*</span><?php endif; ?>
                </label>

                <?php if ($field === 'comment'): ?>
                    <textarea id="<?php echo esc_attr($field_id); ?>" name="<?php echo esc_attr($field); ?>" 
                        <?php echo $required ? 'required' : ''; ?> class="<?php echo esc_attr($input_class); ?>"></textarea>
                <?php else: ?>
                    <input type="text" id="<?php echo esc_attr($field_id); ?>" 
                        name="<?php echo esc_attr($field); ?>" <?php echo $required ? 'required' : ''; ?> 
                        class="<?php echo esc_attr($input_class); ?>">
                <?php endif; ?>
            </div>
        <?php 
            endif;
        endforeach; 
        ?>
    </div>

    <div class="petitiona-email-field">
        <?php
        if (isset($form_fields['email'])):
            $required = $form_fields['email'];
        ?>
            <div class="petitiona-field">
                <label for="petitiona-email">
                    Email <span class="required">*</span>
                </label>
                <input type="email" id="petitiona-email" name="email" required class="petitiona-input">
            </div>
        <?php endif; ?>
    </div>
    
    <div class="petitiona-followup-field">
        <label>
            <input type="checkbox" name="follow_up" value="1">
            <span>Yes! I would like to be informed whether this petition is successful and how I can support other important petitions.</span>
        </label>
    </div>

    <div class="petitiona-submit-section">
        <button type="submit" class="petitiona-submit-button">Sign Now</button>
        <div class="petitiona-message" style="display: none;"></div>
    </div>
</form>
<?php endif; ?>
</div>