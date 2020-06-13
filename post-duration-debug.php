<?php

class postDurationDebug {

	function __construct() {
		global $wpdb;
		$this->debug_table = $wpdb->prefix . 'postduration_debug';
		$this->createDBTable();
	}

	/**
	 * Create Database Table to store debugging information if it does not already exist.
	 */
	private function createDBTable() {
		global $wpdb;

		if ($wpdb->get_var("SHOW TABLES LIKE '".$this->debug_table."'") != $this->debug_table) {
			$sql = "CREATE TABLE `".$this->debug_table."` (
				`id` INT(9) NOT NULL AUTO_INCREMENT PRIMARY KEY,
				`timestamp` text NOT NULL,
				`blog` INT(9) NOT NULL,
				`message` text NOT NULL
			);";
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);
		}
	}

	public function removeDBTable() {
		global $wpdb;
		$wpdb->query('DROP TABLE IF EXISTS '.$this->debug_table);
	}

	public function save($data) {
		global $wpdb;
		if (is_multisite()) {
			global $current_blog;
			$blog = $current_blog->blog_id;
		} else $blog = 0;
		$wpdb->query($wpdb->prepare('INSERT INTO '.$this->debug_table.' (`timestamp`,`message`,`blog`) VALUES (%s,%s,%s)',time(),$data['message'],$blog));
	}

	public function getTable() {
		global $wpdb;
		$tz = get_option('timezone_string');
		if ( $tz ) date_default_timezone_set( $tz );
		$results = $wpdb->get_results("SELECT * FROM {$this->debug_table} ORDER BY `id` DESC");
		if (empty($results)) {
			print '<p>'.__('Debugging table is currently empty.','post-duration').'</p>';
			return;
		}
		print '<table class="post-duration-debug">';
		print '<tr><th class="post-duration-timestamp">'.__('Timestamp','post-duration').'</th>';
		print '<th>'.__('Message','post-duration').'</th></tr>';
		foreach ($results as $result) {
			print '<tr><td>'. date("Y-m-d H:i:s",$result->timestamp) .'</td>';
			print '<td>'.$result->message.'</td></tr>';
		}
		print '</table>';
	}

	public function purge() {
		global $wpdb;
		$wpdb->query("TRUNCATE TABLE {$this->debug_table}");
	}
}
