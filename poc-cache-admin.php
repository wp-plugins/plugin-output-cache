<?php
/*
Admin Pages for Plugin Output Cache 4.0.7
*/ 

function poc_cache_manage_menu($nothing) {
	if (function_exists('current_user_can')) {
		if (!current_user_can('manage_options')) return;
	} else {
		global $user_level;
		get_currentuserinfo();
		if ($user_level < 8) return;
	}
	if (function_exists('add_management_page')) {
		add_management_page(__('Plugin Cache'), __('Plugin Cache'), 1, __FILE__, 'poc_cache_manage_page');	
	}
}

function poc_cache_manage_page() {
	global $poc_cache;
	if (isset($_POST['delete_cache'])) {
		poc_cache_flush();
	}
	if (isset($_POST['cache_on'])) {
		poc_cache_enable();			
	}   
	if (isset($_POST['cache_off'])) {
		poc_cache_disable();			
	}
	if (isset($_POST['stats_on'])) {
		update_option('poc_stats', 1);			
	}   
	if (isset($_POST['stats_off'])) {
		update_option('poc_stats', 0);			
	}
	?>
    <div class="wrap"> 
		<h2><?php _e('Manage the Plugin Output Cache'); ?></h2>
		<form method="post" action="">	
		<?php 
			$num_entries = $poc_cache->count_entries();
			$num_good = $poc_cache->count_hits();
			$is_active = (bool) get_option('poc_active'); 
			$active_text = ($is_active) ? __('The Plugin Output Cache is ON') : __('The Plugin Output Cache is OFF');
			$is_stats = (bool) get_option('poc_stats');
			$stats_text = ($is_stats) ? __('Cache Statistics are ON') : __('Cache Statistics are OFF');
			$num_misses = ($is_stats) ? $num_entries  : '?';
			$num_hits = ($is_stats) ? $num_good : '?';
			$efficiency = ($is_stats) ? sprintf('%.0f%%', (100.0 * $num_good / ($num_good + $num_entries + 0.001))) : '?';
		?>	
		<table class="optiontable form-table">
			<tr valign="top">
				<td>
					<h3><?php echo $active_text; ?></h3>
					<div class="submit">
					<?php 
						if ($is_active) {
							echo '<input type="submit" name="cache_off" value="'.__("Turn Cache Off").'" />';
						} else {
							echo '<input type="submit" name="cache_on" value="'.__("Turn Cache On").'" />';
						}
					?>	
					</div>	
					<?php echo '<p>'.__('There are ').$num_entries.__(' items in the cache').'</p>'; ?>
					<div class="submit">	
					<input type="submit" name="delete_cache" value="<?php _e('Clear the Cache') ?>" />
					</div>
					<div class="submit">	
					<input type="submit" name="refresh_display" value="<?php _e('Refresh Display') ?>" />
					</div>
				</td>
				<td>
					<?php
						echo '<h3>'.$stats_text.'</h3>';
						echo '<p>'.__('Number of misses: ').$num_misses.__(' Number of hits: ').$num_hits.'</p>';
						echo '<p>'.__('The cache is currently ').$efficiency.__(' efficient').'</p>';
						if ($is_stats) {
							echo '<div class="submit"><input type="submit" name="stats_off" value="'.__("Turn Statistics Off").'" /></div>';
						} else {
							echo '<div class="submit"><input type="submit" name="stats_on" value="'.__("Turn Statistics On").'" /></div>';
						}
						echo '<p>'.__('<strong>N.B.</strong> While statistics are being collected the cache slows down dramatically.<br />Only collect statistics from time to time to make sure the cache is efficient.').'</p>'; 			
					?>	
				</td>
			</tr>	
		</table>
		</form>       
    </div>
	<?php
}

add_action('admin_menu', 'poc_cache_manage_menu');

// gets called when the plugin is installed ... sets up the cache table and some settings
function poc_cache_install () {
	global $wpdb, $table_prefix;
	$poc_table = $table_prefix.'poc_cache';
	// clear any old-style entries
	$wpdb->query("DELETE FROM `" . $wpdb->options . "` WHERE `option_name` LIKE 'poccache%'");
	// drop any previous table
	$sql = "DROP TABLE IF EXISTS `$poc_table`";
	$wpdb->query($sql);
	// install the new table
	$sql = "CREATE TABLE IF NOT EXISTS `$poc_table` (
		key_name char(32) NOT NULL COLLATE 'ascii_bin', 
		data_value longtext NOT NULL,
		PRIMARY KEY key_name (key_name)
	) ENGINE = MyISAM DEFAULT CHARSET=utf8;"; 
	$wpdb->query($sql);

	// store default options
	update_option('poc_active', 0);
	update_option('poc_stats', 0);
	update_option('poc_hits', 0);
}

// gets called when the plugin is uninstalled ... removes the cache table and settings
function poc_cache_uninstall () {
	global $wpdb, $table_prefix;
	$poc_table = $table_prefix.'poc_cache';
	update_option('poc_active', 0);			
	$sql = "DROP TABLE IF EXISTS `$poc_table`";
	$wpdb->query($sql);
	delete_option('poc_active');
	delete_option('poc_stats');
	delete_option('poc_hit');
	delete_option('poc_hits');
}

add_action('activate_'.str_replace('-admin', '', plugin_basename(__FILE__)), 'poc_cache_install');
add_action('deactivate_'.str_replace('-admin', '', plugin_basename(__FILE__)), 'poc_cache_uninstall');


?>