<?php
namespace Petitiona;

if (!defined('ABSPATH')) exit;

class Admin {
    private static $instance = null;
    private $database;

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->database = Database::getInstance();
        add_action('admin_menu', array($this, 'addMenuPages'));
        add_action('admin_enqueue_scripts', array($this, 'enqueueAssets'));
        add_action('admin_init', array($this, 'handleExport'));
        add_action('admin_init', array($this, 'handlePetitionDelete'));
        add_action('wp_ajax_delete_signature', array($this, 'handleSignatureDelete'));
        add_action('admin_post_save_petition', array($this, 'handleFormSubmission'));
    }

    public function enqueueAssets($hook) {
        if (strpos($hook, 'petitiona') === false) {
            return;
        }

        wp_enqueue_style(
            'petitiona-admin',
            PETITION_PLUGIN_URL . 'assets/css/petition-style.css',
            array(),
            PETITIONA_VERSION
        );

        wp_enqueue_script(
            'petitiona-admin',
            PETITION_PLUGIN_URL . 'assets/js/petition-admin.js',
            array('jquery'),
            PETITIONA_VERSION,
            true
        );
    }

    public function handlePetitionDelete() {
       if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
           $petition_id = intval($_GET['id']);
           check_admin_referer('delete_petition_' . $petition_id);
           
           $this->database->deletePetition($petition_id);
           $this->database->deleteSignatures($petition_id);
           
           wp_redirect(admin_url('admin.php?page=petitiona&deleted=1'));
           exit;
       }
   }

   public function handleSignatureDelete() {
        if (!isset($_POST['signature_id'])) {
            wp_send_json_error('Missing signature ID');
            return;
        }
        
        check_ajax_referer('delete_signature');
        $signature_id = intval($_POST['signature_id']);
        $this->database->deleteSignature($signature_id);
        wp_send_json_success();
    }

    public function addMenuPages() {
        add_menu_page(
            'Petitions',
            'Petitions',
            'manage_options',
            'petitiona',
            array($this, 'renderMainPage'),
            'dashicons-clipboard',
            30
        );

        add_submenu_page(
            'petitiona',
            'All Petitions',
            'All Petitions',
            'manage_options',
            'petitiona'
        );

        add_submenu_page(
            'petitiona',
            'Add New Petition',
            'Add New',
            'manage_options',
            'petitiona-new',
            array($this, 'renderPetitionForm')
        );

        add_submenu_page(
            'petitiona',
            'Signatures',
            'Signatures',
            'manage_options',
            'petitiona-signatures',
            array($this, 'renderSignaturesPage')
        );
    }

    public function renderPetitionForm() {
        $petition_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $petition = $petition_id ? $this->database->getPetition($petition_id) : null;
        require_once PETITION_PLUGIN_DIR . 'admin/petition-form.php';
    }

    public function handleFormSubmission() {
        if (!isset($_POST['petition_nonce']) || 
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['petition_nonce'])), 'save_petition')) {
            wp_die('Invalid nonce');
        }

        $form_fields = array();
        $available_fields = ['email', 'firstname', 'lastname', 'location', 'address', 'country', 'phone', 'comment'];
        
        foreach ($available_fields as $field_key) {
            if (isset($_POST['fields'][$field_key])) {
                $form_fields[$field_key] = !empty($_POST['required'][$field_key]);
            }
        }

        if (!isset($_POST['title'], $_POST['content'], $_POST['goal_signatures'])) {
            wp_die('Required fields are missing');
        }

        $data = array(
            'title' => sanitize_text_field(wp_unslash($_POST['title'])),
            'content' => wp_kses_post(wp_unslash($_POST['content'])),
            'goal_signatures' => intval($_POST['goal_signatures']),
            'start_date' => $_POST['start_type'] === 'scheduled' ? 
                sanitize_text_field(wp_unslash($_POST['start_date'])) : null,
            'end_date' => $_POST['end_type'] === 'scheduled' ? 
                sanitize_text_field(wp_unslash($_POST['end_date'])) : null,
            'form_fields' => wp_json_encode($form_fields),
            'email_restrictions' => !empty($_POST['email_restrictions']) ? 
                wp_json_encode(array_filter(array_map(function($item) {
                    return sanitize_text_field(wp_unslash($item));
                }, $_POST['email_restrictions']))) : null
        );

        $petition_id = isset($_POST['petition_id']) ? intval($_POST['petition_id']) : 0;
        $success = $petition_id ? 
            $this->database->updatePetition($petition_id, $data) : 
            $this->database->createPetition($data);

        wp_redirect(add_query_arg(array(
            'page' => 'petitiona',
            'message' => $success ? 'saved' : 'error'
        ), admin_url('admin.php')));
        exit;
    }

    public function handleExport() {
        if (!isset($_GET['action'], $_GET['petition_id']) || 
            $_GET['action'] !== 'export') {
            return;
        }
    
        $petition_id = intval($_GET['petition_id']);
        
        // Verify nonce
        check_admin_referer('export_signatures_' . $petition_id);
        
        $signatures = $this->database->getSignaturesForExport($petition_id);
        
        if (!empty($signatures)) {
            $filename = 'petition-signatures-' . $petition_id . '-' . gmdate('Y-m-d') . '.csv';
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            
            global $wp_filesystem;
            WP_Filesystem();
    
            $temp_file = wp_tempnam('petition-export');
            $content = "Email,First Name,Last Name,Location,Address,Country,Phone,Comment,Date,Follow-up\n";
    
            foreach ($signatures as $signature) {
                // Clean the data before CSV formatting
                $row = array(
                    $this->clean_csv_field($signature->email),
                    $this->clean_csv_field($signature->firstname),
                    $this->clean_csv_field($signature->lastname),
                    $this->clean_csv_field($signature->location),
                    $this->clean_csv_field($signature->address),
                    $this->clean_csv_field($signature->country),
                    $this->clean_csv_field($signature->phone),
                    $this->clean_csv_field($signature->comment),
                    $this->clean_csv_field($signature->signed_date),
                    $signature->follow_up ? 'Yes' : 'No'
                );
                
                $content .= implode(',', $row) . "\n";
            }
            
            $wp_filesystem->put_contents($temp_file, $content);
            echo $wp_filesystem->get_contents($temp_file);
            $wp_filesystem->delete($temp_file);
            
            exit;
        }
    }
    
    private function clean_csv_field($str) {
        // Remove any existing quotes
        $str = str_replace('"', '', $str);
        
        // If the field contains commas, wrap it in quotes
        if (strpos($str, ',') !== false) {
            $str = '"' . $str . '"';
        }
        
        return $str;
    }

    private function esc_csv($value) {
        $value = str_replace('"', '""', $value); // Escape quotes
        return '"' . $value . '"';
    }

    public function renderSignaturesPage() {
        $petition_id = isset($_GET['petition_id']) ? intval($_GET['petition_id']) : 0;
        ?>
        <div class="wrap">
            <h1>Petition Signatures</h1>
            
            <div class="tablenav top">
                <div class="alignleft actions">
                    <select id="petition-filter">
                       <option value="">Select Petition</option>
                       <?php
                       $petitions = $this->database->getAllPetitions();
                       foreach ($petitions as $petition) {
                           printf(
                               '<option value="%d" %s>%s</option>',
                               esc_attr($petition->id),
                               selected($petition_id, $petition->id, false),
                               esc_html($petition->title)
                           );
                       }
                       ?>
                   </select>
                   <?php if ($petition_id): ?>
                        <a href="<?php echo esc_url(wp_nonce_url(
                            admin_url('admin.php?page=petitiona-signatures&petition_id=' . $petition_id . '&action=export'),
                            'export_signatures_' . $petition_id
                        )); ?>" class="button action">Export CSV</a>
                    <?php endif; ?>
                </div>
            </div>

            <table class="wp-list-table widefat fixed striped">
               <thead>
                   <tr>
                       <th>Email</th>
                       <th>First Name</th>
                       <th>Last Name</th>
                       <th>Location</th>
                       <th>Address</th>
                       <th>Country</th>
                       <th>Phone Number</th>
                       <th>Comment</th>
                       <th>Actions</th>
                   </tr>
               </thead>
               <tbody>
                   <?php
                   if ($petition_id) {
                       $signatures = $this->database->getSignatures($petition_id);
                       if ($signatures && count($signatures) > 0) {
                           foreach ($signatures as $signature) {
                               ?>
                               <tr>
                                   <td><?php echo esc_html($signature->email); ?></td>
                                   <td><?php echo esc_html($signature->firstname); ?></td>
                                   <td><?php echo esc_html($signature->lastname); ?></td>
                                   <td><?php echo esc_html($signature->location); ?></td>
                                   <td><?php echo esc_html($signature->address); ?></td>
                                   <td><?php echo esc_html($signature->country); ?></td>
                                   <td><?php echo esc_html($signature->phone); ?></td>
                                   <td><?php echo esc_html($signature->comment); ?></td>
                                   <td>
                                       <a href="#" class="delete-signature" 
                                          data-id="<?php echo esc_attr($signature->id); ?>" 
                                          style="color: red;">Delete</a>
                                   </td>
                               </tr>
                               <?php
                           }
                       } else {
                           ?>
                           <tr>
                               <td colspan="9" style="text-align: center;">There are no signatures yet</td>
                           </tr>
                           <?php
                       }
                   }
                   ?>
               </tbody>
           </table>
       </div>

       <script>
       jQuery(document).ready(function($) {
           $('#petition-filter').on('change', function() {
               window.location.href = '<?php echo esc_js(admin_url('admin.php')); ?>?page=petitiona-signatures&petition_id=' + $(this).val();
           });

           $('.delete-signature').on('click', function(e) {
               e.preventDefault();
               if (!confirm('Are you sure you want to delete this signature?')) return;
               
               var row = $(this).closest('tr');
               $.post(ajaxurl, {
                   action: 'delete_signature',
                   signature_id: $(this).data('id'),
                   _ajax_nonce: '<?php echo esc_js(wp_create_nonce("delete_signature")); ?>'
               }, function() {
                   row.fadeOut();
               });
           });
       });
       </script>
       <?php
    }

    public function renderMainPage() {
       $petitions = $this->database->getAllPetitions();
       ?>
       <div class="wrap">
           <h1 class="wp-heading-inline">Petitions</h1>
           <a href="<?php echo esc_url(admin_url('admin.php?page=petitiona-new')); ?>" class="page-title-action">Add New</a>
           
           <?php if (!empty($petitions)): ?>
               <table class="wp-list-table widefat fixed striped">
                   <thead>
                       <tr>
                           <th>Title</th>
                           <th>Signatures</th>
                           <th>Goal</th>
                           <th>Start Date</th>
                           <th>End Date</th>
                           <th>Shortcode</th>
                           <th>Actions</th>
                       </tr>
                   </thead>
                   <tbody>
                       <?php foreach ($petitions as $petition): 
                           $signature_count = $this->database->getSignatureCount($petition->id);
                           ?>
                           <tr>
                               <td>
                                   <strong><?php echo esc_html($petition->title); ?></strong>
                               </td>
                               <td><?php echo esc_html(number_format($signature_count)); ?></td>
                               <td><?php echo esc_html(number_format($petition->goal_signatures)); ?></td>
                               <td><?php echo $petition->start_date ? esc_html(gmdate('Y-m-d', strtotime($petition->start_date))) : 'Immediate'; ?></td>
                               <td><?php echo $petition->end_date ? esc_html(gmdate('Y-m-d', strtotime($petition->end_date))) : 'Never'; ?></td>
                               <td><code>[petitiona id=<?php echo esc_attr($petition->id); ?>]</code></td>
                               <td>
                                   <a href="<?php echo esc_url(admin_url('admin.php?page=petitiona-new&id=' . $petition->id)); ?>">Edit</a>
                                   |
                                   <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=petitiona&action=delete&id=' . $petition->id), 'delete_petition_' . $petition->id)); ?>" 
                                      onclick="return confirm('Are you sure you want to delete this petition?')" 
                                      style="color: red;">Delete</a>
                               </td>
                           </tr>
                       <?php endforeach; ?>
                   </tbody>
               </table>
           <?php else: ?>
               <p>No petitions found. <a href="<?php echo esc_url(admin_url('admin.php?page=petitiona-new')); ?>">Create your first petition</a>.</p>
           <?php endif; ?>
       </div>
       <?php
   }
}