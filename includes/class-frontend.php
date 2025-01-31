<?php
namespace Petitiona;

if (!defined('ABSPATH')) exit;

class Frontend {
    private static $instance = null;

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_petitiona_sign', array($this, 'handleSignatureSubmission'));
        add_action('wp_ajax_nopriv_petitiona_sign', array($this, 'handleSignatureSubmission'));
    }

    public function renderPetitionForm($data) {
        ob_start();
        
        $petition = $data['petition'];
        $signature_count = $data['signature_count'];
        $form_fields = $data['form_fields'];
        $theme_class = 'petitiona-theme-' . sanitize_html_class($data['theme']);
        $is_completed = ($signature_count >= $petition->goal_signatures) || $petition->status === 'completed';

        // Ensure email field exists and is required
        $form_fields['email'] = true;
        
        // Define field order
        $field_order = ['firstname', 'lastname', 'location', 'address', 'country', 'phone', 'comment'];
        
        // Create ordered fields array
        $ordered_fields = [];
        foreach ($field_order as $field) {
            if (isset($form_fields[$field])) {
                $ordered_fields[$field] = $form_fields[$field];
            }
        }
        // Add email at the end
        $ordered_fields['email'] = true;

        // Pass ordered fields to template
        $data['form_fields'] = $ordered_fields;
        
        include(PETITION_PLUGIN_DIR . 'templates/petition-form.php');
        return ob_get_clean();
    }

    private function renderFormField($field, $required) {
        $fieldOrder = ['firstname', 'lastname', 'location', 'address', 'country', 'phone', 'comment', 'email'];
    
        uasort($this->form_fields, function($a, $b) use ($fieldOrder) {
            return array_search($a, $fieldOrder) <=> array_search($b, $fieldOrder);
        });

        $required_attr = $required ? ' required' : '';
        $label = ucfirst(str_replace('_', ' ', $field));
        
        echo '<div class="petitiona-field">';
        echo '<label for="petitiona-' . esc_attr($field) . '">' . esc_html($label) . '</label>';

        switch ($field) {
            case 'email':
                printf(
                    '<input type="email" id="petitiona-%1$s" name="%1$s" class="petitiona-input"%2$s>',
                    esc_attr($field),
                    esc_attr($required_attr)
                );
                break;

            case 'comment':
                printf(
                    '<textarea id="petitiona-%1$s" name="%1$s" class="petitiona-textarea"%2$s></textarea>',
                    esc_attr($field),
                    esc_attr($required_attr)
                );
                break;

            case 'phone':
                printf(
                    '<input type="tel" id="petitiona-%1$s" name="%1$s" class="petitiona-input"%2$s>',
                    esc_attr($field),
                    esc_attr($required_attr)
                );
                break;

            case 'country':
                printf(
                    '<select id="petitiona-%1$s" name="%1$s" class="petitiona-select"%2$s>',
                    esc_attr($field),
                    esc_attr($required_attr)
                );
                echo '<option value="">Select Country</option>';
                foreach ($this->getCountries() as $code => $name) {
                    printf(
                        '<option value="%s">%s</option>',
                        esc_attr($code),
                        esc_html($name)
                    );
                }
                echo '</select>';
                break;

            default:
                printf(
                    '<input type="text" id="petitiona-%1$s" name="%1$s" class="petitiona-input"%2$s>',
                    esc_attr($field),
                    esc_attr($required_attr)
                );
        }
        echo '</div>';
        
        if ($field === 'email') {
            echo '<div class="petitiona-field follow-up">';
            echo '<input type="checkbox" name="follow_up" value="1" id="follow-up">';
            echo '<label for="follow-up" style="display:inline-block;margin-left:5px;">Yes! I would like to be informed whether this petition is successful and how I can support other important petitions.</label>';
            echo '</div>';
        }
    }

    private function getCountries() {
        return array(
            'US' => 'United States',
            'GB' => 'United Kingdom',
            'CA' => 'Canada',
            'DE' => 'Germany',
            'FR' => 'France',
            // Add more countries as needed
        );
    }

    public function handleSignatureSubmission() {
        try {
            if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'petitiona-signature-nonce')) {
    wp_send_json_error('Invalid nonce verification');
    return;
}

            if (!isset($_POST['petition_id']) || !isset($_POST['email'])) {
                wp_send_json_error('Required fields are missing.');
                return;
            }

            $petition_id = intval($_POST['petition_id']);
            $email = sanitize_email(wp_unslash($_POST['email']));

            if (empty($petition_id) || empty($email)) {
                wp_send_json_error('Required fields are missing.');
                return;
            }

            $database = Database::getInstance();
            $petition = $database->getPetition($petition_id);
            
            if (!$petition) {
                wp_send_json_error('Invalid petition.');
                return;
            }

            $email_restrictions = json_decode($petition->email_restrictions, true);
            if (!empty($email_restrictions)) {
                $domain = substr(strrchr($email, "@"), 1);
                if (!in_array('@'.$domain, $email_restrictions)) {
                    wp_send_json_error('Please use an email address from allowed domains: ' . implode(', ', $email_restrictions));
                    return;
                }
            }

            if ($database->checkDuplicateSignature($petition_id, $email)) {
                wp_send_json_error('You have already signed this petition.');
                return;
            }

            $signature_data = array(
                'email' => $email,
                'petition_id' => $petition_id,
                'firstname' => isset($_POST['firstname']) ? sanitize_text_field(wp_unslash($_POST['firstname'])) : '',
                'lastname' => isset($_POST['lastname']) ? sanitize_text_field(wp_unslash($_POST['lastname'])) : '',
                'location' => isset($_POST['location']) ? sanitize_text_field(wp_unslash($_POST['location'])) : '',
                'address' => isset($_POST['address']) ? sanitize_text_field(wp_unslash($_POST['address'])) : '',
                'country' => isset($_POST['country']) ? sanitize_text_field(wp_unslash($_POST['country'])) : '',
                'phone' => isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '',
                'comment' => isset($_POST['comment']) ? sanitize_textarea_field(wp_unslash($_POST['comment'])) : '',
                'follow_up' => isset($_POST['follow_up']) ? 1 : 0
            );

            if ($database->addSignature($signature_data)) {
                $new_count = $database->getSignatureCount($petition_id);
                $percentage = min(100, ($new_count / $petition->goal_signatures) * 100);
                
                if ($percentage >= 100) {
                    $database->updatePetitionStatus($petition_id);
                }
                
                wp_send_json_success(array(
                    'message' => 'Thank you for signing!',
                    'new_count' => $new_count,
                    'percentage' => $percentage,
                    'is_completed' => $percentage >= 100
                ));
            } else {
                wp_send_json_error('Failed to add signature. Please try again.');
            }
        } catch (Exception $e) {
            wp_send_json_error('An error occurred. Please try again later.');
        }
    }
}