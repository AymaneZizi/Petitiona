<?php
namespace Petitiona;

if (!defined('ABSPATH')) exit;

class Database {
    private static $instance = null;
    private $forms_table;
    private $signatures_table;

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->forms_table = $wpdb->prefix . 'petitiona_forms';
        $this->signatures_table = $wpdb->prefix . 'petitiona_signatures';
    }

    public function createTables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $forms_sql = "CREATE TABLE IF NOT EXISTS {$this->forms_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            status varchar(20) DEFAULT 'active',  # Add this line
            content longtext,
            goal_signatures int NOT NULL DEFAULT 0,
            start_date datetime DEFAULT NULL,
            end_date datetime DEFAULT NULL,
            form_fields longtext,
            email_restrictions text, /* Stores array of allowed domains */
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        $signatures_sql = "CREATE TABLE IF NOT EXISTS {$this->signatures_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            petition_id bigint(20) NOT NULL,
            email varchar(255),
            firstname varchar(100),
            lastname varchar(100),
            location varchar(255),
            address text,
            country varchar(100),
            phone varchar(50),
            comment text,
            ip_address varchar(45),
            follow_up tinyint(1) DEFAULT 0,
            signed_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY petition_id (petition_id),
            KEY email (email),
            KEY signed_date (signed_date)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($forms_sql);
        dbDelta($signatures_sql);
    }

    public function updatePetitionStatus($petition_id) {
        global $wpdb;
        $petition = $this->getPetition($petition_id);
        $signature_count = $this->getSignatureCount($petition_id);
        
        if ($signature_count >= $petition->goal_signatures) {
            return $wpdb->update(
                $this->forms_table,
                ['status' => 'completed'],
                ['id' => $petition_id]
            );
        }
        return false;
    }

    public function createPetition($data) {
        global $wpdb;
        
        
        if (isset($data['form_fields'])) {
            if (is_array($data['form_fields'])) {
                $data['form_fields'] = wp_json_encode($data['form_fields']);
            }
        }

        $result = $wpdb->insert($this->forms_table, $data);
        
        return $result;
    }

    public function updatePetition($id, $data) {
        global $wpdb;
        
        
        if (isset($data['form_fields'])) {
            if (is_array($data['form_fields'])) {
                $data['form_fields'] = wp_json_encode($data['form_fields']);
            }
        }

        $result = $wpdb->update(
            $this->forms_table,
            $data,
            ['id' => $id]
        );

        return $result;
    }

    public function getPetition($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->forms_table} WHERE id = %d",
            $id
        ));
    }

    public function getAllPetitions() {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->forms_table} ORDER BY created_at DESC"
        ));
    }

    public function addSignature($data) {
        global $wpdb;
        if (isset($_SERVER['REMOTE_ADDR'])) {
            $data['ip_address'] = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }
        return $wpdb->insert($this->signatures_table, $data);
    }
    
    public function getSignatures($petition_id, $limit = null, $offset = 0) {
		global $wpdb;

		if ($limit !== null) {
			return $wpdb->get_results($wpdb->prepare(
				"SELECT * FROM {$this->signatures_table} 
				 WHERE petition_id = %d 
				 ORDER BY signed_date DESC 
				 LIMIT %d OFFSET %d",
				$petition_id,
				$limit,
				$offset
			));
		}

		return $wpdb->get_results($wpdb->prepare(
			"SELECT * FROM {$this->signatures_table} 
			 WHERE petition_id = %d 
			 ORDER BY signed_date DESC",
			$petition_id
		));
	}
    
    public function getSignaturesForExport($petition_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT email, firstname, lastname, location, address, country, phone, comment, signed_date, follow_up 
             FROM {$this->signatures_table} 
             WHERE petition_id = %d 
             ORDER BY signed_date DESC",
            $petition_id
        ));
    }
    
    public function getSignatureCount($petition_id) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->signatures_table} WHERE petition_id = %d",
            $petition_id
        ));
    }
    
    public function checkDuplicateSignature($petition_id, $email) {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->signatures_table} WHERE petition_id = %d AND email = %s",
            $petition_id,
            $email
        )) > 0;
    }

    public function deleteSignature($id) {
        global $wpdb;
        return $wpdb->delete($this->signatures_table, ['id' => $id]);
    }

    public function deletePetition($id) {
        global $wpdb;
        return $wpdb->delete($this->forms_table, ['id' => $id]);
    }

    public function deleteSignatures($petition_id) {
        global $wpdb;
        return $wpdb->delete($this->signatures_table, ['petition_id' => $petition_id]);
    }
}
