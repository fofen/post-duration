<?php
/*
Plugin Name: Post Duration
Plugin URI: http://wordpress.org/extend/plugins/post-duration/
Description: Allows you to change a post to be private or a draft at closing time.
Author: Fofen Leng
Version: 20.0623
Author URI: https://fofen.top/
Text Domain: post-duration
*/

/* Load translation, if it exists */
function postDuration_init() {
	$plugin_dir = basename(dirname(__FILE__));
	load_plugin_textdomain( 'post-duration', null, $plugin_dir.'/languages/' );
}
add_action('plugins_loaded', 'postDuration_init');

// Default Values
define('POSTDURATION_VERSION','20.0623');
define('POSTDURATION_DATEFORMAT',__('Y-m-d','post-duration'));
define('POSTDURATION_TIMEFORMAT',__('H:i','post-duration'));
define('POSTDURATION_FOOTERCONTENTS',__('Post closes on CLOSINGDATE','post-duration'));
define('POSTDURATION_FOOTERSTYLE','font-weight: bold; text-align: center; color: red;');
define('POSTDURATION_FOOTERDISPLAY','1');
define('POSTDURATION_DEBUGDEFAULT','0');

function postDuration_plugin_action_links($links, $file) {
    $this_plugin = basename(plugin_dir_url(__FILE__)) . '/post-duration.php';
    if($file == $this_plugin) {
        $links[] = '<a href="options-general.php?page=post-duration">' . __('Settings', 'post-duration') . '</a>';
    }
    return $links;
}
add_filter('plugin_action_links', 'postDuration_plugin_action_links', 10, 2);

/**
 * adds an 'Closing date' column to the post display table.
 */
add_filter ('manage_posts_columns', 'closingdate_add_column', 10, 2);
function closingdate_add_column ($columns,$type) {
	$defaults = get_option('closingdateDefaults'.ucfirst($type));
	if (!isset($defaults['activeMetaBox']) || $defaults['activeMetaBox'] == 'active') {
	  	$columns['closingdate'] = __('Closing date','post-duration');
	}
  	return $columns;
}

add_action( 'init', 'init_managesortablecolumns', 100 );
function init_managesortablecolumns (){
    $post_types = get_post_types(array('public'=>true));
    foreach( $post_types as $post_type ){
        add_filter( 'manage_edit-' . $post_type . '_sortable_columns', 'closingdate_sortable_column' );
    }
}
function closingdate_sortable_column($columns) {
	$columns['closingdate'] = 'closingdate';
	return $columns;
}

add_action( 'pre_get_posts', 'my_closingdate_orderby' );
function my_closingdate_orderby( $query ) {
    	if( ! is_admin() )
        	return;
	$orderby = $query->get( 'orderby');
	if( 'closingdate' == $orderby ) {
		$query->set('meta_query',array(
    			'relation'  => 'OR',
	    		array(
        			'key'       => '_closing-date',
        			'compare'   => 'EXISTS'
	    		),
    			array(
        			'key'       => '_closing-date',
        			'compare'   => 'NOT EXISTS',
	        		'value'     => ''
    			)
		));
        	$query->set('orderby','meta_value_num');
   	}
}

/**
 * adds an 'Closing date' column to the page display table.
 */
add_filter ('manage_pages_columns', 'closingdate_add_column_page');
function closingdate_add_column_page ($columns) {
	$defaults = get_option('closingdateDefaultsPage');
	if (!isset($defaults['activeMetaBox']) || $defaults['activeMetaBox'] == 'active') {
	  	$columns['closingdate'] = __('Closing date','post-duration');
	}
  	return $columns;
}

/**
 * fills the 'Closing date' column of the post display table.
 */
add_action ('manage_posts_custom_column', 'closingdate_show_value');
add_action ('manage_pages_custom_column', 'closingdate_show_value');
function closingdate_show_value ($column_name) {
	global $post;
	$id = $post->ID;
	if ($column_name === 'closingdate') {
		$ed = get_post_meta($id,'_closing-date',true);
    		echo ($ed ? get_date_from_gmt(gmdate('Y-m-d H:i:s',$ed),get_option('date_format').' '.get_option('time_format')) : __("Never",'post-duration'));
  	}
}

/**
 * Adds hooks to get the meta box added to pages and custom post types
 */
function closingdate_meta_custom() {
	$custom_post_types = get_post_types();
	array_push($custom_post_types,'page');
	foreach ($custom_post_types as $t) {
		$defaults = get_option('closingdateDefaults'.ucfirst($t));
		if (!isset($defaults['activeMetaBox']) || $defaults['activeMetaBox'] == 'active') {
			add_meta_box('closingdatediv', __('Post Duration','post-duration'), 'closingdate_meta_box', $t, 'side', 'core');
		}
	}
}
add_action ('add_meta_boxes','closingdate_meta_custom');

/**
 * Actually adds the meta box
 */
function closingdate_meta_box($post) {
	// Get default month
	$closingdatets = get_post_meta($post->ID,'_closing-date',true);
	$durationstatus = get_post_meta($post->ID,'_closing-date-status',true);
	$default = '';
	$changeTo = '';
	$defaults = get_option('closingdateDefaults'.ucfirst($post->post_type));
	if (empty($durationstatus)) { // new post
		$custom = get_option('closingdateDefaultDateCustom');
		if ($custom === false) $ts = time();
		else {
			$tz = get_option('timezone_string');
			if ( $tz ) date_default_timezone_set( $tz );
			$ts = time() + (strtotime($custom) - time());
			if ( $tz ) date_default_timezone_set('UTC');
		}
		$closingdate = get_date_from_gmt(gmdate('Y-m-d H:i:s',$ts),'Y-m-d');
		$closingtime = get_date_from_gmt(gmdate('Y-m-d H:i:s',$ts),'H:i');

		$enabled = '';
		$disabled = ' disabled="disabled"';

		if (isset($defaults['changeTo'])) {
			$changeTo = $defaults['changeTo'];
		}

		if (isset($defaults['autoEnable']) && ($durationstatus !== 'disabled') && ($defaults['autoEnable'] === true || $defaults['autoEnable'] == 1)) {
			$enabled = ' checked="checked"';
			$disabled='';
		}
	} else { // existing post
		$closingdate = get_date_from_gmt(gmdate('Y-m-d H:i:s',$closingdatets),'Y-m-d');
		$closingtime = get_date_from_gmt(gmdate('Y-m-d H:i:s',$closingdatets),'H:i');

		if ($durationstatus == 'enabled') {
			$enabled 	= 	' checked="checked"';
			$disabled 	= 	'';
		} else {
			$enabled = '';
                	$disabled = ' disabled="disabled"';
		}
		$opts 		= 	get_post_meta($post->ID,'_closing-date-options',true);
		if (isset($opts['changeTo'])) {
                	$changeTo = $opts['changeTo'];
		}
	}

	$rv = array();
	$rv[] = '<p><input type="checkbox" name="enable-closingdate" id="enable-closingdate" value="checked"'.$enabled.' onclick="closingdate_ajax_add_meta(\'enable-closingdate\')" />';
	$rv[] = '<label for="enable-closingdate">'.__('Enable Post Duration','post-duration').'</label></p>';
	$rv[] = '<input type="datetime-local" id="closing_date_input" name="closing_date_input" value="' . $closingdate . 'T' . $closingtime . '" ' . $disabled . ' onblur="change_post_status()">';
	$rv[] = '<input type="hidden" id="closing_date" name="closing_date" type="text" value=' . $closingdate . 'T' . $closingtime . ' />';
	echo implode("\n",$rv);

	echo '<p>'.__('Change to','post-duration').': ';
	echo _postDurationExpireType(array('type' => $post->post_type, 'name'=>'closingdate_changeto','selected'=>$changeTo,'disabled'=>$disabled));
	echo '</p>';
	echo '<div id="closingdate_ajax_result"></div>';
}

/**
 * Add's ajax javascript
 */
function closingdate_js_admin_header() {
	// Define custom JavaScript function
	?>
<script type="text/javascript">
//<![CDATA[

function closingdate_ajax_add_meta(durationenable) {
	var closing = document.getElementById(durationenable);

	if (closing.checked == true) {
		var enable = 'true';
		document.getElementById('closing_date_input').disabled = false;
		document.getElementById('closingdate_changeto').disabled = false;
	} else {
		document.getElementById('closing_date_input').disabled = true;
		document.getElementById('closingdate_changeto').disabled = true;
		var enable = 'false';
	}
	return true;
}

function getNow(s) { // get part of time
    return s < 10 ? '0' + s: s;
}
function change_post_status() { // change post status according to closing datetime
	closingdate = document.getElementById("closing_date_input").value; // in xxxx-xx-xxTxx:xx format

	var myDate = new Date(); //current date/time
	var year = myDate.getFullYear();
	var month = myDate.getMonth()+1;
	var date = myDate.getDate();
	var h = myDate.getHours();
	var m = myDate.getMinutes();

	var now = year + "-" +  getNow(month) + "-" + getNow(date) + "T" + getNow(h) + ":" + getNow(m); // current date/time in xxxx-xx-xx xx:xx format

	if (closingdate > now) { // closingdate is in future
		jQuery("#visibility-radio-public").prop("checked", true); // chose the 'public' radio button
	} else { // closingdate has been past
		jQuery("#visibility-radio-private").prop("checked", true); // chose the 'private' radio button
		jQuery("#enable-closingdate").prop("checked", false); // don't enable the post duration
		window["closingdate_ajax_add_meta"]("enable-closingdate"); // disable all input in the meta box
	}
	labeltext = jQuery(jQuery(":radio[name=visibility]:checked").prop("labels") ).text(); // get the lable of chosed radio button
	jQuery("#post-visibility-display").text(labeltext); // set the lable

	var x= document.getElementById('closing_date_input').value; // get the value of the datetime-local input element
	document.getElementById('closing_date').value = x;
}
//]]>
</script>
<?php
}
add_action('admin_head', 'closingdate_js_admin_header' );

/**
 * Called when post is saved - stores closing-date meta value
 */
add_action('save_post','closingdate_update_post_meta');
function closingdate_update_post_meta($id) {
	// don't run the echo if this is an auto save
	if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
	// don't run for neither revision nor auto-draft
        $posttype = get_post_type($id);
	$poststatus = get_post_status($id);
	if ( $posttype == 'revision' || $poststatus == 'auto-draft' ) return;
	$datetime = str_replace("T"," ", $_POST['closing_date']) . ":0";  // replace "T" with " ", then add ":0" in the end
	$ts = get_gmt_from_date($datetime,'U'); // timestamp of post closing datetime

	if (isset($_POST['enable-closingdate'])) {
		$opts = array();
		$opts['changeTo'] = $_POST['closingdate_changeto'];
		$opts['id'] = $id;
		_scheduleDurationEvent($id,$ts,$opts);
	} else {
		_unscheduleDurationEvent($id,$ts);
	}
}

function _scheduleDurationEvent($id,$ts,$opts) { // Schedule/Update cron job
       	$debug = postDurationDebug(); //check for/load debug

	wp_clear_scheduled_hook('postDurationExpire',array($id)); //Remove any existing hooks

	$tz = get_option('timezone_string');
	if ( $tz ) date_default_timezone_set( $tz );

	$dateformat = get_option('date_format') . ' ' . get_option('time_format');

	wp_schedule_single_event($ts,'postDurationExpire',array($id));
	if (POSTDURATION_DEBUG) $debug->save(array('message' => $id.' -> SCHEDULED at '. date($dateformat,$ts) .' '.print_r($opts,true)));

	// Update Post Meta
       	update_post_meta($id, '_closing-date', $ts);
        update_post_meta($id, '_closing-date-options', $opts);
	update_post_meta($id, '_closing-date-status','enabled');
}

function _unscheduleDurationEvent($id,$ts) { // Delete Scheduled cron job
       	$debug = postDurationDebug(); // check for/load debug

	wp_clear_scheduled_hook('postDurationExpire',array($id)); //Remove any existing hooks

       	update_post_meta($id, '_closing-date', $ts);
	update_post_meta($id, '_closing-date-status','disabled');
}

/**
 * The new expiration function, to work with single scheduled events.
 * This was designed to hopefully be more flexible for future tweaks/modifications to the architecture.*
 * @param array $opts - options to pass into the expiration process, in key/value format
 */
function postDurationExpire($id) {
        $debug = postDurationDebug(); //check for/load debug
	if (empty($id)) {
		if (POSTDURATION_DEBUG) $debug->save(array('message' => 'No Post ID found - exiting'));
		return false;
	}
	if (is_null(get_post($id))) {
		if (POSTDURATION_DEBUG) $debug->save(array('message' => $id.' -> Post does not exist - exiting'));
		return false;
	}
	$posttype = get_post_type($id);
	$posttitle = get_the_title($id);
	$postlink = get_post_permalink($id);

	$postoptions = get_post_meta($id,'_closing-date-options',true);
	extract($postoptions);
        $ed = get_post_meta($id,'_closing-date',true);

	// Check for default duration only if not passed in
	if (empty($expireType)) {
		$posttype = get_post_type($id);
		if ($posttype == 'page') {
			$expireType = strtolower(get_option('closingdateDefaultsPage','Private'));
		} elseif ($posttype == 'post') {
			$expireType = strtolower(get_option('closingdateDefaultsPost','Private'));
		}
	}

	// Remove KSES - wp_cron runs as an unauthenticated user, which will by default trigger kses filtering,
	// even if the post was published by a admin user.  It is fairly safe here to remove the filter call since
	// we are only changing the post status/meta information and not touching the content.
	kses_remove_filters();

	// Do Work
	if ($changeTo == 'draft') {
		if (wp_update_post(array('ID' => $id, 'post_status' => 'draft')) == 0) {
			if (POSTDURATION_DEBUG) $debug->save(array('message' => $id.' -> FAILED '.$changeTo.' '.print_r($postoptions,true)));
		} else {
			if (POSTDURATION_DEBUG) $debug->save(array('message' => $id.' -> PROCESSED '.$changeTo.' '.print_r($postoptions,true)));
		}
	} elseif ($changeTo == 'private') {
		if (wp_update_post(array('ID' => $id, 'post_status' => 'private')) == 0) {
			if (POSTDURATION_DEBUG) $debug->save(array('message' => $id.' -> FAILED '.$changeTo.' '.print_r($postoptions,true)));
		} else {
			if (POSTDURATION_DEBUG) $debug->save(array('message' => $id.' -> PROCESSED '.$changeTo.' '.print_r($postoptions,true)));
		}
	}
}
add_action('postDurationExpire','postDurationExpire');

/**
 * Build the menu for the options page
 */
function postDurationMenuTabs($tab) {
        echo '<div class="nav-tab-wrapper">';
	if (empty($tab)) $tab = 'general';
	echo '<a href="'.admin_url('options-general.php?page=post-duration.php&tab=general').'" class="nav-tab'.($tab == 'general' ? ' nav-tab-active"' : '"').'>'.__('General Settings','post-duration').'</a>';
        echo '<a href="'.admin_url('options-general.php?page=post-duration.php&tab=defaults').'" class="nav-tab'.($tab == 'defaults' ? ' nav-tab-active"' : '"').'>'.__('Defaults','post-duration').'</a>';
        echo '<a href="'.admin_url('options-general.php?page=post-duration.php&tab=list').'" class="nav-tab'.($tab == 'list' ? ' nav-tab-active"' : '"').'>'.__('Scheduled Posts','post-duration').'</a>';
	echo '<a href="'.admin_url('options-general.php?page=post-duration.php&tab=viewdebug').'" class="nav-tab'.($tab == 'viewdebug' ? ' nav-tab-active"' : '"').'>'.__('Debug Info','post-duration').'</a>';
	echo '</div>';
}

/**
 *
 */
function postDurationMenu() {
        $tab = isset($_GET['tab']) ? $_GET['tab'] : '';

	echo '<div class="wrap">';
        echo '<h2>'.__('Post Duration Options','post-duration').'</h2>';

	postDurationMenuTabs($tab);
	if (empty($tab) || $tab == 'general') {
		postDurationMenuGeneral();
	} elseif ($tab == 'defaults') {
		postDurationMenuDefaults();
	} elseif ($tab == 'list') {
		postDurationMenuList();
	} elseif ($tab == 'viewdebug') {
		postDurationMenuViewdebug();
	}
	echo '</div>';
}

/**
 * Hook's to add plugin page menu
 */
function postDurationPluginMenu() {
	add_submenu_page('options-general.php',__('Post Duration Options','post-duration'),__('Post Duration','post-duration'),'manage_options',basename(__FILE__),'postDurationMenu');
}
add_action('admin_menu', 'postDurationPluginMenu');

/**
 * Show the Expiration Date options page
 */
function postDurationMenuGeneral() {
	if (isset($_POST['closingdateSave']) && $_POST['closingdateSave']) {
		if ( !isset($_POST['_postDurationMenuGeneral_nonce']) || !wp_verify_nonce($_POST['_postDurationMenuGeneral_nonce'],'postDurationMenuGeneral') ) {
			print 'Form Validation Failure: Sorry, your nonce did not verify.';
			exit;
		} else {
			//Filter Content
			$_POST = filter_input_array(INPUT_POST,FILTER_SANITIZE_STRING);

			update_option('closingdateDefaultDateFormat',$_POST['closed-default-date-format']);
			update_option('closingdateDefaultTimeFormat',$_POST['closed-default-time-format']);
			update_option('closingdateDisplayFooter',$_POST['closed-display-footer']);
			update_option('closingdateFooterContents',$_POST['closed-footer-contents']);
			update_option('closingdateFooterStyle',$_POST['closed-footer-style']);
			//update_option('closingdateDefaultDate',$_POST['closed-default-closing-date']);
			if ($_POST['closed-custom-closing-date']) update_option('closingdateDefaultDateCustom',$_POST['closed-custom-closing-date']);
                	echo "<div id='message' class='updated fade'><p>";
        	        _e('Saved Options!','post-duration');
	                echo "</p></div>";
		}
	}

	// Get Option
	$closingdateDefaultDateFormat = get_option('closingdateDefaultDateFormat',POSTDURATION_DATEFORMAT);
	$closingdateDefaultTimeFormat = get_option('closingdateDefaultTimeFormat',POSTDURATION_TIMEFORMAT);
	$closeddisplayfooter = get_option('closingdateDisplayFooter',POSTDURATION_FOOTERDISPLAY);
	$closingdateFooterContents = get_option('closingdateFooterContents',POSTDURATION_FOOTERCONTENTS);
	$closingdateFooterStyle = get_option('closingdateFooterStyle',POSTDURATION_FOOTERSTYLE);
	$closingdateDefaultDateCustom = get_option('closingdateDefaultDateCustom');

	$closeddisplayfooterenabled = '';
	$closeddisplayfooterdisabled = '';
	if ($closeddisplayfooter == 0)
		$closeddisplayfooterdisabled = 'checked="checked"';
	else if ($closeddisplayfooter == 1)
		$closeddisplayfooterenabled = 'checked="checked"';
	?>
	<p>
	<?php _e('The post duration plugin sets a custom meta value, and then optionally allows you to select if you want the post changed to be private or a draft status when it expires.','post-duration'); ?>
	</p>
	<form method="post" id="closingdate_save_options">
		<?php wp_nonce_field('postDurationMenuGeneral','_postDurationMenuGeneral_nonce'); ?>
		<h3><?php _e('Defaults','post-duration'); ?></h3>
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><label for="closed-default-date-format"><?php _e('Date Format:','post-duration');?></label></th>
				<td>
					<input type="text" name="closed-default-date-format" id="closed-default-date-format" value="<?php echo $closingdateDefaultDateFormat ?>" size="25" /> (<?php echo date_i18n("$closingdateDefaultDateFormat") ?>)
					<br/>
					<?php _e('The default format to use when displaying the closing date within the footer.  For information on valid formatting options, see: <a href="http://us2.php.net/manual/en/function.date.php" target="_blank">PHP Date Function</a>.','post-duration'); ?>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="closed-default-time-format"><?php _e('Time Format:','post-duration');?></label></th>
				<td>
					<input type="text" name="closed-default-time-format" id="closed-default-time-format" value="<?php echo $closingdateDefaultTimeFormat ?>" size="25" /> (<?php echo date_i18n("$closingdateDefaultTimeFormat") ?>)
					<br/>
					<?php _e('The default format to use when displaying the closing time within the footer.  For information on valid formatting options, see: <a href="http://us2.php.net/manual/en/function.date.php" target="_blank">PHP Date Function</a>.','post-duration'); ?>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="closed-default-closing-date"><?php _e('Default Duration:','post-duration');?></label></th>
				<td>
					<input type="text" value="<?php echo $closingdateDefaultDateCustom; ?>" name="closed-custom-closing-date" id="closed-custom-closing-date" />
					<br/>
					<?php _e('Set the custom value to use for the default closing date.  For information on formatting, see <a href="http://php.net/manual/en/function.strtotime.php">PHP strtotime function</a>. For example, you could enter "+1 month" or "+1 week 2 days 4 hours 2 seconds" or "next Thursday."','post-duration'); ?>
					</div>
				</td>
			</tr>
		</table>

		<h3><?php _e('Post Footer Display','post-duration');?></h3>
		<p><?php _e('Enabling this below will display the closing date automatically at the end of any post.','post-duration');?></p>
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><?php _e('Show in post footer?','post-duration');?></th>
				<td>
					<input type="radio" name="closed-display-footer" id="closed-display-footer-true" value="1" <?php echo $closeddisplayfooterenabled ?>/> <label for="closed-display-footer-true"><?php _e('Enabled','post-duration');?></label>
					<input type="radio" name="closed-display-footer" id="closed-display-footer-false" value="0" <?php echo $closeddisplayfooterdisabled ?>/> <label for="closed-display-footer-false"><?php _e('Disabled','post-duration');?></label>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="closed-footer-contents"><?php _e('Footer Contents:','post-duration');?></label></th>
				<td>
					<textarea id="closed-footer-contents" name="closed-footer-contents" rows="3" cols="50"><?php echo $closingdateFooterContents; ?></textarea>
					<ul>
						<li>CLOSINGFULL -> <?php echo date_i18n("$closingdateDefaultDateFormat $closingdateDefaultTimeFormat") ?></li>
						<li>CLOSINGDATE -> <?php echo date_i18n("$closingdateDefaultDateFormat") ?></li>
						<li>CLOSINGTIME -> <?php echo date_i18n("$closingdateDefaultTimeFormat") ?></li>
					</ul>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="closed-footer-style"><?php _e('Footer Style:','post-duration');?></label></th>
				<td>
					<input type="text" name="closed-footer-style" id="closed-footer-style" value="<?php echo $closingdateFooterStyle ?>" size="25" />
					(<span style="<?php echo $closingdateFooterStyle ?>"><?php _e('This post will be closed on','post-duration');?> <?php echo date_i18n("$closingdateDefaultDateFormat $closingdateDefaultTimeFormat"); ?></span>)
				</td>
			</tr>
		</table>
		<p class="submit">
			<input type="submit" name="closingdateSave" class="button-primary" value="<?php _e('Save Changes','post-duration');?>" />
		</p>
	</form>
	<?php
}

function postDurationMenuDefaults() {
	$debug = postDurationDebug();
	$types = get_post_types(array('public' => true, '_builtin' => false));
	array_unshift($types,'post','page');

	if (isset($_POST['closingdateSaveDefaults'])) {
		if ( !isset($_POST['_postDurationMenuDefaults_nonce']) || !wp_verify_nonce($_POST['_postDurationMenuDefaults_nonce'],'postDurationMenuDefaults') ) {
			print 'Form Validation Failure: Sorry, your nonce did not verify.';
			exit;
		} else {
			//Filter Content
                        $_POST = filter_input_array(INPUT_POST,FILTER_SANITIZE_STRING);

			$defaults = array();
			foreach ($types as $type) {
				if (isset($_POST['closingdate_changeto-'.$type])) {
					$defaults[$type]['changeTo'] = $_POST['closingdate_changeto-'.$type];
				}
				if (isset($_POST['closingdate_autoenable-'.$type])) {
					$defaults[$type]['autoEnable'] = intval($_POST['closingdate_autoenable-'.$type]);
				}
				if (isset($_POST['closingdate_activemeta-'.$type])) {
					$defaults[$type]['activeMetaBox'] = $_POST['closingdate_activemeta-'.$type];
				}

				//Save Settings
		                update_option('closingdateDefaults'.ucfirst($type),$defaults[$type]);
			}
                	echo "<div id='message' class='updated fade'><p>";
       		        _e('Saved Options!','post-duration');
        	        echo "</p></div>";
		}
	}

	?>
        <form method="post">
                <?php wp_nonce_field('postDurationMenuDefaults','_postDurationMenuDefaults_nonce');
		foreach ($types as $type) {
			echo "<br><fieldset style='border: 1px solid black; border-radius: 6px; padding: 0px 12px; margin-bottom: 20px;'>";
			echo "<legend>Post Type: $type</legend>";
			$defaults = get_option('closingdateDefaults'.ucfirst($type));

			if (isset($defaults['autoEnable']) && $defaults['autoEnable'] == 1) {
				$closedautoenabled = 'checked = "checked"';
				$closedautodisabled = '';
			} else {
				$closedautoenabled = '';
				$closedautodisabled = 'checked = "checked"';
			}
			if (isset($defaults['activeMetaBox']) && $defaults['activeMetaBox'] == 'inactive') {
				$closedactivemetaenabled = '';
				$closedactivemetadisabled = 'checked = "checked"';
			} else {
				$closedactivemetaenabled = 'checked = "checked"';
				$closedactivemetadisabled = '';
			}
			?>
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><label for="closingdate_activemeta-<?php echo $type ?>"><?php _e('Active:','post-duration');?></label></th>
					<td>
						<input type="radio" name="closingdate_activemeta-<?php echo $type ?>" id="closingdate_activemeta-true-<?php echo $type ?>" value="active" <?php echo $closedactivemetaenabled ?>/> <label for="closed-active-meta-true"><?php _e('Active','post-duration');?></label>
						<input type="radio" name="closingdate_activemeta-<?php echo $type ?>" id="closingdate_activemeta-false-<?php echo $type ?>" value="inactive" <?php echo $closedactivemetadisabled ?>/> <label for="closed-active-meta-false"><?php _e('Inactive','post-duration');?></label><br/>
						<?php _e('Select whether the post duration meta box is active for this post type.','post-duration');?>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="closingdate_changeto-<?php echo $type ?>"><?php _e('Change to:','post-duration'); ?></label></th>
					<td>
						<?php echo _postDurationExpireType(array('name'=>'closingdate_changeto-'.$type,'selected' => $defaults['changeTo'])); ?>
						</select><br/>
						<?php _e('Post will be changed to selected status on closing time.','post-duration');?>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="closingdate_autoenable-<?php echo $type ?>"><?php _e('Auto-Enable?','post-duration');?></label></th>
					<td>
						<input type="radio" name="closingdate_autoenable-<?php echo $type ?>" id="closingdate_autoenable-true-<?php echo $type ?>" value="1" <?php echo $closedautoenabled ?>/> <label for="closed-auto-enable-true"><?php _e('Enabled','post-duration');?></label>
						<input type="radio" name="closingdate_autoenable-<?php echo $type ?>" id="closingdate_autoenable-false-<?php echo $type ?>" value="0" <?php echo $closedautodisabled ?>/> <label for="closed-auto-enable-false"><?php _e('Disabled','post-duration');?></label><br/>
						<?php _e('Select whether the post duration is enabled for all new posts.','post-duration');?>
					</td>
				</tr>
			</table>
			</fieldset>
			<?php
		}
		?>
                <p class="submit">
                        <input type="submit" name="closingdateSaveDefaults" class="button-primary" value="<?php _e('Save Changes','post-duration');?>" />
                </p>
        </form>
	<?php
}

function postDurationMenuList() { // Show all scheduled post (cron jobs)
	$cronsa = get_option('cron');
	$cron_info_text = "<table>";
	$i = 0;
	$tz = get_option('timezone_string');
        if ( $tz ) date_default_timezone_set( $tz );
	foreach ( (array)$cronsa as $timestamp => $cronhooks ) {
		foreach ( (array)$cronhooks as $hook => $keys ) {
		  if (!$hook) continue;
		  if ($filterHook && $hook!=$filterHook) continue;
		  $tkeys = "";
		    if($hook == "postDurationExpire") {
			foreach ( (array)$keys as $k => $v ) {
				$i++;
				$cron_info_text .= "<tr><th>".date("Y-m-d H:i",$timestamp)."</th>";
				if (is_array($v)) {
					foreach ($v as $k1 => $v1) {
						if (is_array($v1) && !empty($v1)) $tkeys .= implode(", ",$v1);
						else if ($v1) $tkeys .= "$v1, ";
					}
				}
				else if ($v) $tkeys .= "$v, ";
			}
			$cron_info_text .= "<td>".$tkeys."</td>";
			$cron_info_text .= "<td>". get_the_title(implode(", ",$v1)) ."</td>";
		    }
		}
	}
	echo "<h3>"; _e('Total '); echo $i . "</h3>";
	echo $cron_info_text;
}

function postDurationMenuViewdebug() {
	require_once(plugin_dir_path(__FILE__).'post-duration-debug.php');
	if ($_SERVER['REQUEST_METHOD'] == 'POST') {
		if ( !isset($_POST['_postDurationMenuList_nonce']) || !wp_verify_nonce($_POST['_postDurationMenuList_nonce'],'postDurationMenuList') ) {
			print 'Form Validation Failure: Sorry, your nonce did not verify.';
			exit;
		}
		if (isset($_POST['debugging-disable'])) {
			update_option('closingdateDebug',0);
        	        echo "<div id='message' class='updated fade'><p>"; _e('Debugging Disabled','post-duration'); echo "</p></div>";
		} elseif (isset($_POST['debugging-enable'])) {
			update_option('closingdateDebug',1);
                	echo "<div id='message' class='updated fade'><p>"; _e('Debugging Enabled','post-duration'); echo "</p></div>";
		} elseif (isset($_POST['purge-debug'])) {
			require_once(plugin_dir_path(__FILE__).'post-duration-debug.php');
			$debug = new postDurationDebug();
			$debug->purge();
        	        echo "<div id='message' class='updated fade'><p>"; _e('Debugging Table Emptied','post-duration'); echo "</p></div>";
		}
	}

	$debug = postDurationDebug();
	?>
        <form method="post" id="postDurationMenuUpgrade">
                <?php wp_nonce_field('postDurationMenuList','_postDurationMenuList_nonce');
			if (POSTDURATION_DEBUG) {
				echo '<br><input type="submit" class="button" name="debugging-disable" id="debugging-disable" value="'.__('Disable Debugging','post-duration').'" />';
			} else {
				echo '<br><input type="submit" class="button" name="debugging-enable" id="debugging-enable" value="'.__('Enable Debugging','post-duration').'" />';
			}
		?>
		<input type="submit" class="button" name="purge-debug" id="purge-debug" value="<?php _e('Purge Debug Log','post-duration');?>" />
        </form>
        <?php
	$debug = new postDurationDebug();
	$debug->getTable(); // display the table
}

function postduration_add_footer($text) {
	global $post;

	$displayFooter = get_option('closingdateDisplayFooter');
	if ($displayFooter === false || $displayFooter == 0) // if not enabled footer
		return $text;

        $closingdatets = get_post_meta($post->ID,'_closing-date',true);
	if (!is_numeric($closingdatets))
		return $text;

        $dateformat = get_option('closingdateDefaultDateFormat',POSTDURATION_DATEFORMAT);
        $timeformat = get_option('closingdateDefaultTimeFormat',POSTDURATION_TIMEFORMAT);
        $closingdateFooterContents = get_option('closingdateFooterContents',POSTDURATION_FOOTERCONTENTS);
        $closingdateFooterStyle = get_option('closingdateFooterStyle',POSTDURATION_FOOTERSTYLE);

	$search = array(
		'CLOSINGFULL',
		'CLOSINGDATE',
		'CLOSINGTIME'
	);
	$replace = array(
		get_date_from_gmt(gmdate('Y-m-d H:i:s',$closingdatets),"$dateformat $timeformat"),
		get_date_from_gmt(gmdate('Y-m-d H:i:s',$closingdatets),$dateformat),
		get_date_from_gmt(gmdate('Y-m-d H:i:s',$closingdatets),$timeformat)
	);

	$add_to_footer = '<p style="'.$closingdateFooterStyle.'">'.str_replace($search,$replace,$closingdateFooterContents).'</p>';
	return $text.$add_to_footer;
}
add_action('the_content','postduration_add_footer',0);

/**
 * Check for Debug
 */
function postDurationDebug() {
	$debug = get_option('closingdateDebug');
	if ($debug == 1) {
		if (!defined('POSTDURATION_DEBUG')) define('POSTDURATION_DEBUG',1);
                require_once(plugin_dir_path(__FILE__).'post-duration-debug.php'); // Load Class
                return new postDurationDebug();
	} else {
		if (!defined('POSTDURATION_DEBUG')) define('POSTDURATION_DEBUG',0);
		return false;
	}
}

/**
 * Called at plugin activation
 */
function postduration_activate () {
	if (get_option('closingdateDefaultDateFormat') === false)	update_option('closingdateDefaultDateFormat',POSTDURATION_DATEFORMAT);
	if (get_option('closingdateDefaultTimeFormat') === false)	update_option('closingdateDefaultTimeFormat',POSTDURATION_TIMEFORMAT);
	if (get_option('closingdateFooterContents') === false)	update_option('closingdateFooterContents',POSTDURATION_FOOTERCONTENTS);
	if (get_option('closingdateFooterStyle') === false)		update_option('closingdateFooterStyle',POSTDURATION_FOOTERSTYLE);
	if (get_option('closingdateDisplayFooter') === false)	update_option('closingdateDisplayFooter',POSTDURATION_FOOTERDISPLAY);
	if (get_option('closingdateDebug') === false)		update_option('closingdateDebug',POSTDURATION_DEBUGDEFAULT);
}

function _postDurationExpireType($opts) {
	if (empty($opts)) return false;

	extract($opts);
	if (!isset($name)) return false;
	if (!isset($id)) $id = $name;
	if (!isset($disabled)) $disabled = false;
	if (!isset($onchange)) $onchange = '';
	if (!isset($type)) $type = '';

	$rv = array();
	$rv[] = '<select name="'.$name.'" id="'.$id.'"'.($disabled == true ? ' disabled="disabled"' : '').' onchange="'.$onchange.'">';
	$rv[] = '<option value="private" '. ($selected == 'private' ? 'selected="selected"' : '') . '>'.__('Private','post-duration').'</option>';
	$rv[] = '<option value="draft" '. ($selected == 'draft' ? 'selected="selected"' : '') . '>'.__('Draft','post-duration').'</option>';
	$rv[] = '</select>';
	return implode("<br/>\n",$rv);
}

function postduration_trash_old_posts() { // trash old private posts more than xx days
        global $wpdb;
        $wpdb->query("UPDATE `" . $wpdb->prefix . "posts` SET `post_status` = 'trash'  WHERE `post_status` = 'private' AND DATEDIFF(NOW(), `post_modified`) > 40");
}
add_action( 'delete_expired_transients', 'postduration_trash_old_posts', 10, 0 );
