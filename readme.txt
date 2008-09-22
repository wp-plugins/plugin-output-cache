=== Plugin Name ===
Contributors: RobMarsh
Tags: cache, speed, plugins
Requires at least: 1.5
Tested up to: 2.6.2
Stable tag: 4.0.7

Plugin Output Cache can be used by other plugins to cache portions of their output for efficiency.

== Description ==

Plugin Output Cache can be used by other plugins to cache portions of their output for efficiency. By itself it does nothing but other plugins (like my own Similar Posts, Recent Posts, Recent Comments, and Random Posts) can be adapted to take advantage of its cache whenever available.

== Installation ==

Plugin Output Cache is installed in 3 easy steps:

   1. Unzip the "Plugin Output Cache" archive and copy the folder to /wp-content/plugins/
   2. Activate the plugin
   3. Use the Manage > Plugin Cache admin page to switch the cache on, etc.
   4. See [the plugin homepage](http://rmarsh.com/plugins/poc-cache/) for more details, including instructions on adapting other plugins to use the output cache.

== Version History ==

* 4.0.7
	* fix for utf8 chars
* 4.0.6
	* fixed bug with unescaped quotes
	* fixed warning in error log
* 4.0.5
	* some speed improvements
	* changes to work with automatic update
	* fixed problem with some installations of PHP
* 4.0.0
	* improved ease of use and efficiency
