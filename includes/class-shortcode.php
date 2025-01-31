<?php
namespace Petitiona;

if (!defined('ABSPATH')) exit;

class Shortcode {
    private static $instance = null;
    private $database;
    private $frontend;

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->database = Database::getInstance();
        $this->frontend = Frontend::getInstance();
        
        add_shortcode('petitiona', array($this, 'renderShortcode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueueAssets'));
    }

    public function enqueueAssets() {
        if (has_shortcode(get_post()->post_content ?? '', 'petitiona')) {
            wp_enqueue_style(
                'petitiona-style',
                plugins_url('/assets/css/petition-style.css', dirname(__FILE__)),
                array(),
                PETITIONA_VERSION
            );

            wp_enqueue_script(
                'petitiona-frontend',
                plugins_url('/assets/js/petition-frontend.js', dirname(__FILE__)),
                array('jquery'),
                PETITIONA_VERSION,
                true
            );

            wp_localize_script('petitiona-frontend', 'petitionaAjax', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('petitiona-signature-nonce')
            ));
        }
    }

    public function renderShortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
            'theme' => 'default'
        ), $atts);

        if (empty($atts['id'])) {
            return '<p class="petitiona-error">Petition ID is required.</p>';
        }

        $petition = $this->database->getPetition($atts['id']);
        if (!$petition) {
            return '<p class="petitiona-error">Petition not found.</p>';
        }

        // Check if petition is active
        $now = current_time('mysql');
        if (!empty($petition->start_date) && strtotime($petition->start_date) > strtotime($now)) {
            return '<p class="petitiona-notice">This petition has not started yet.</p>';
        }
        if (!empty($petition->end_date) && strtotime($petition->end_date) < strtotime($now)) {
            return '<p class="petitiona-notice">This petition has ended.</p>';
        }

        // Get current signature count
        $signature_count = $this->database->getSignatureCount($petition->id);
        
        // Prepare data for frontend display
        $data = array(
            'petition' => $petition,
            'signature_count' => $signature_count,
            'form_fields' => json_decode($petition->form_fields, true),
            'theme' => $atts['theme']
        );

        // Return rendered template
        return $this->frontend->renderPetitionForm($data);
    }

}
