<?php
/*
Plugin Name: Plugin Output Cache
Plugin URI: http://rmarsh.com/plugins/poc-cache/
Description: Provides a caching mechanism for the output from plugins. Recent Posts and Recent Comments, etc,  use the cache automatically if it is installed. The cache is cleared whenever the blog content changes. Start at the <a href="edit.php?page=poc-cache/poc-cache-admin.php">Management page</a>.
Author: Rob Marsh, SJ
Version: 4.0.0
Author URI: http://rmarsh.com/
*/ 


function poc_cache_fetch($key) {
	global $poc_cache;
	return $poc_cache->fetch($key);
}

function poc_cache_store($key, $data) {
	global $poc_cache;
	$poc_cache->store($key, $data);
}

function poc_cache_flush() {
	global $poc_cache;
	$poc_cache->clear_all();
}

function poc_cache_enable() {
	global $poc_cache;
	$poc_cache->active = true;
	update_option('poc_active', 1);			
}

function poc_cache_disable() {
	global $poc_cache;
	$poc_cache->active = false;
	update_option('poc_active', 0);			
}

function poc_cache_timer_start() {
	global $poc_cache;
	$poc_cache->timer_start();
}

function poc_cache_timer_stop() {
	global $poc_cache;
	return $poc_cache->timer_stop();
}

/*

	innards
	
*/

// don't change these 
define('POC_CACHE', true);
define('POC_CACHE_4', true);
$poc_table = $table_prefix.'poc_cache';

class POC_Cache {
	var $timer = 0;
	var $active = false;
	var $stats = false;
	var $hits = 0;
	var $mcache = array();
	var $mstore = array();

	function POC_Cache() {
		register_shutdown_function(array(&$this, "__destruct"));
		$this->__construct();
	}	

	function __construct()	{
		global $wpdb;
		$this->active = (bool) get_option('poc_active');
		$this->stats = (bool) get_option('poc_stats');
		$this->dbh = &$wpdb->dbh; // hitch a ride on the wp connection to minimize the number of database connections
	}
	
	function __destruct() {
		if (!$this->active) return false;
		global $poc_table;
		foreach ($this->mstore as $key => $dummy) {
			$data = $this->mcache[$key];
			$data = base64_encode(gzcompress($data));
			mysql_query("INSERT INTO `$poc_table` (`key_name`, `data_value`) VALUES ('$key', '$data')");
		}
		update_option('poc_hits', get_option('poc_hits') + $this->hits);		
	}
	
	// if the data can be found in the cache it is returned, otherwise false is returned
	function fetch($key) {
		if (!$this->active) return false;
		global $poc_table;
		$key = md5($key);
		if (isset($this->mcache[$key])) {
			$data = $this->mcache[$key];
			if ($this->stats) ++$this->hits;
		} else {
			$result = mysql_query("SELECT `data_value` FROM `$poc_table` WHERE key_name = '$key' LIMIT 1", $this->dbh);
			if (mysql_num_rows($result)) {
				$row = mysql_fetch_row($result);
				$data = $row[0];
				$data = gzuncompress(base64_decode($data));
				$this->mcache[$key] = $data;
				if ($this->stats) ++$this->hits;
				mysql_free_result($result);
			} else {
				$data = false;
			}
		}
		return $data;
	}

	// store data to the cache -- assumes that the key is not already there... under
	// other circumstances this would be a crazy assumption but used in the prescribed
	// way, i.e., after getting a false result from fetch, it is safe and avoids an
	// extra query or two.
	function store($key, $data) {
		if (!$this->active) return false;
		$key = md5($key);
		$this->mcache[$key] = $data;
		$this->mstore[$key] = 1;
	}
	
	function clear_all() {
		global $poc_table;
		$this->mcache = array();
		$this->mstore = array();
		mysql_query("TRUNCATE TABLE `$poc_table`", $this->dbh);
		$this->hits = 0;
		update_option('poc_hits', 0);	
	}
	
	// count how many entries are cached
	function count_entries() {
		global $poc_table;
		$result = mysql_query("SELECT COUNT(*) FROM $poc_table");
		if ($result)	{
			$row = mysql_fetch_row($result);
			return $row[0];
		}	
		return 0;
	}
	
	// count how many times the cache successfully served a request
	function count_hits() {
		return get_option('poc_hits') + $this->hits;
	}
	
	// the number of entries in the cache is also the number of times the cache failed to deliver
	// so i'm defining efficiency as 100*hits/(hits+entries)
	function efficiency() {
		$hits = $this->count_hits();
		$misses = $this->count_entries();
		if ($misses == 0) return 0;
		else return 100.0 * $hits / ($hits + $misses);
	}
	
	function timer_start() {
		list($usec, $sec) = explode(" ", microtime());
		$this->timer = (float)$usec + (float)$sec;
	}
	
	function timer_stop() {
		if ($this->timer === 0) return 0;
		list($usec, $sec) = explode(" ", microtime());
		$t = (float)$usec + (float)$sec - $this->timer; 
		$this->timer = 0;
		return $t;
	}
	
}

// called when a post or comment changes in some way
function poc_cache_invalidate($post_id) {
	// some actions trigger more than one hook so we can
	// end up here again even though the work has already 
	// been done ... so we use a static variable to save time
	static $cache_invalidated = false;
	if ($cache_invalidated) return;
	poc_cache_flush();
	$cache_invalidated = true;
	return $post_id;
}

// called when a comment is changed -- we have to check for properly approved comments only
function poc_cache_pre_invalidate($comment_id) {
	$comment = get_commentdata($comment_id, 1, true);
	if( !preg_match('/wp-admin\//', $_SERVER['REQUEST_URI']) && $comment['comment_approved'] != 1 ){
		return $comment_id;
	}
	return poc_cache_invalidate($comment_id);
}

// installs actions for all the hooks that might change our cached content
function poc_cache_install_hooks(){
	if(function_exists('add_action')) {
		add_action('publish_post', 'poc_cache_invalidate', 1);
		add_action('edit_post', 'poc_cache_invalidate', 1);
		add_action('delete_post', 'poc_cache_invalidate', 1);
		add_action('publish_phone', 'poc_cache_invalidate', 1);
		add_action('delete_comment', 'poc_cache_invalidate', 1);
		// these we check for validity before invalidating the cache
		add_action('trackback_post', 'poc_cache_pre_invalidate', 1);
		add_action('pingback_post', 'poc_cache_pre_invalidate', 1);
		add_action('comment_post', 'poc_cache_pre_invalidate', 1);
		add_action('edit_comment', 'poc_cache_pre_invalidate', 1);
		add_action('wp_set_comment_status', 'poc_cache_pre_invalidate', 1);
	}
}

//called when all plugins have loaded
add_action('plugins_loaded', 'poc_cache_install_hooks', 0);

// when appropriate pull in the admin pages
if ( is_admin() ) {
	require(dirname(__FILE__).'/poc-cache-admin.php');
}

$poc_cache = new POC_Cache;

?>