<?php

/**
 * Code Snippets - An easy, clean and simple way to add code snippets to your site.
 *
 * If you're interested in helping to develop Code Snippets, or perhaps
 * contribute to the localization, please see http://code-snippets.bungeshea.com
 *
 * @package Code Snippets
 * @subpackage Main
 *
 *
 * Plugin Name: Code Snippets
 * Plugin URI: http://code-snippets.bungeshea.com
 * Description: An easy, clean and simple way to add code snippets to your site. No need to edit to your theme's functions.php file again!
 * Author: Shea Bunge
 * Author URI: http://bungeshea.com
 * Version: 1.6.1
 * License: GPLv3 or later
 * Network: true
 * Text Domain: code-snippets
 * Domain Path: /languages/
 *
 *
 * Code Snippets - WordPress Plugin
 * Copyright (C) 2012  Shea Bunge
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses>.
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Code_Snippets' ) ) :

/**
 * The main class for our plugin
 * It all happens here, folks
 *
 * Please use the global variable $code_snippets to access
 * the methods in this class. Anything you need
 * to access should be publicly available there
 *
 * @since Code Snippets 1.0
 * @access private
 */
final class Code_Snippets {

	/**
	 * The base name for the snippets table in the database
	 * This will later be prepended with the WordPress table prefix
	 *
	 * DO NOT EDIT THIS VARIABLE!
	 * Instead, use the 'code_snippets_table' filter
	 *
	 * If you need to use the table name in your own code,
	 * use the $code_snippets->get_table_name() function
	 *
	 * @since Code Snippets 1.0
	 * @access public
	 */
	private $table = 'snippets';

	/**
	 * The base name for the network snippets table in the database
	 * This will later be prepended with the WordPress base table prefix
	 *
	 * DO NOT EDIT THIS VARIABLE!
	 * Instead, use the 'code_snippets_multisite_table' filter
	 *
	 * If you need to use the table name in your own code,
	 * use the $code_snippets->get_table_name() function
	 *
	 * @since Code Snippets 1.4
	 * @access private
	 */
	private $ms_table = 'ms_snippets';

	/**
	 * The version number for this release of the plugin.
	 * This will later be used for upgrades and enqueueing files
	 *
	 * This should be set to the 'Plugin Version' value,
	 * as defined above in the plugin header
	 *
	 * @since Code Snippets 1.0
	 * @access public
	 */
	public $version = 1.6;

	/**
	 * The base URLs for the admin pages
	 *
	 * @since Code Snippets 1.0
	 * @access public
	 */
	public $admin_manage_url, $admin_single_url, $admin_import_url;

	/**
	 * The hooks for the admin pages
	 * Used primarily for enqueueing scripts and styles
	 *
	 * @since Code Snippets 1.0
	 * @access public
	 */
	public $admin_manage, $admin_single, $admin_import;

	/**
	 * The constructor function for our class
	 *
	 * @since Code Snippets 1.0
	 * @access private
	 *
	 * @return void
	 */
	function __construct() {
		$this->setup_vars();  // initialise the variables
		$this->setup_hooks(); // register the action and filter hooks
		$this->upgrade();     // check if we need to change some stuff
	}

	/**
	 * Initialise variables
	 *
	 * @since Code Snippets 1.2
	 * @access private
	 *
	 * @return void
	 */
	function setup_vars() {
		global $wpdb;
		$this->file       = __FILE__;

		$this->table      = apply_filters( 'code_snippets_table', $wpdb->prefix . $this->table );
		$this->ms_table   = apply_filters( 'code_snippets_multisite_table', $wpdb->base_prefix . $this->ms_table );

		$this->basename	  = plugin_basename( $this->file );
		$this->plugin_dir = plugin_dir_path( $this->file );
		$this->plugin_url = plugin_dir_url ( $this->file );

		$this->admin_manage_slug = apply_filters( 'code_snippets_admin_manage', 'snippets' );
		$this->admin_single_slug = apply_filters( 'code_snippets_admin_single', 'snippet' );

		$this->admin_manage_url	 = self_admin_url( 'admin.php?page=' . $this->admin_manage_slug );
		$this->admin_single_url  = self_admin_url( 'admin.php?page=' . $this->admin_single_slug );
	}

	/**
	 * Register action and filter hooks
	 *
	 * @since Code Snippets 1.6
	 * @access private
	 *
	 * @return void
	 */
	function setup_hooks() {

		/* execute the snippets once the plugins are loaded */
		add_action( 'plugins_loaded', array( $this, 'run_snippets' ) );

		/* add the administration menus */
		add_action( 'admin_menu', array( $this, 'add_admin_menus' ) );
		add_action( 'network_admin_menu', array( $this, 'add_network_admin_menus' ) );

		/* register the importer */
		add_action( 'admin_init', array( $this, 'load_importer' ), 999 );
		add_action( 'network_admin_menu', array( $this, 'add_import_admin_menu' ) );

		/* load the translations */
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		/* add helpful links to the Plugins menu */
		add_filter( 'plugin_action_links_' . $this->basename, array( $this, 'settings_link' ) );
		add_filter( 'plugin_row_meta', array( $this, 'plugin_meta' ), 10, 2 );

		/* Add a custom icon to Snippets menu pages */
		add_action( 'admin_head', array( $this, 'load_admin_icon_style' ) );
	}

	/**
	 * Load the Code Snippets importer
	 *
	 * Add both an importer to the Tools menu
	 * and an Import Snippets page to the network admin menu
	 *
	 * @since Code Snippets 1.6
	 * @access private
	 *
	 * @return void
	 */
	function load_importer() {

		if ( defined( 'WP_LOAD_IMPORTERS' ) ) {

			// Load Importer API
			require_once ABSPATH . 'wp-admin/includes/import.php';

			if ( ! class_exists( 'WP_Importer' ) ) {
				$class_wp_importer = ABSPATH .  'wp-admin/includes/class-wp-importer.php';
				if ( file_exists( $class_wp_importer ) )
					require_once $class_wp_importer;
			}

			register_importer(
				'code-snippets',
				__('Code Snippets', 'code-snippets'),
				__('Import snippets from a <strong>Code Snippets</strong> export file', 'code-snippets'),
				array( $this, 'display_admin_import' )
			);
		}

		$this->admin_import_url = self_admin_url( 'admin.php?import=code-snippets' );
		add_action( 'load-importer-code-snippets', array( $this, 'load_admin_import' ) );
	}

	/**
	 * Return the appropriate snippet table name
	 *
	 * @since Code Snippets 1.6
	 * @access private
	 *
	 * @param string $scope Retrieve the multisite table name or the site table name?
	 * @param bool $check_screen Query the current screen if no scope passed?
	 * @return string $table The snippet table name
	 */
	function get_table_name( $scope = '', $check_screen = true ) {

		$this->create_tables(); // create the snippet tables if they do not exist

		if ( ! is_multisite() ) {
			$network = false;
		}
		elseif ( empty( $network ) && $check_screen && function_exists( 'get_current_screen' ) ) {
			/* if no scope is set, query the current screen to see if in network admin */
			$screen = get_current_screen();
			$network = $screen->is_network;
		}
		elseif ( $scope == 'multisite' || $scope == 'network' ) {
			$network = true;
		}
		elseif ( $scope == 'site' || $scope = 'single' ) {
			$network = false;
		}
		else {
			$network = false;
		}

		$table = ( $network ? $this->ms_table : $this->table );

		return $table;
	}

	/**
	 * Create the snippet tables if they do not already exist
	 *
	 * @since Code Snippets 1.2
	 * @access public
	 *
	 * @uses $this->create_table() To create a single snippet table
	 *
	 * @return void
	 */
	public function create_tables() {

		$this->create_table( $this->table );

		if ( is_multisite() )
			$this->create_table( $this->ms_table );

		add_site_option( 'code_snippets_version', $this->version );
	}

	/**
	 * Create a single snippet table
	 * if one of the same name does not already exist
	 *
	 * @since Code Snippets 1.6
	 * @access public
	 *
	 * @param string $table_name The name of the table to create
	 * @return void
	 */
	function create_table( $table_name ) {

		global $wpdb;

		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
			$sql = "CREATE TABLE $table_name (
				id			MEDIUMINT	NOT NULL AUTO_INCREMENT,
				name		VARCHAR(64)	NOT NULL,
				description	TEXT,
				code		TEXT		NOT NULL,
				active		TINYINT(1)	NOT NULL DEFAULT 0,
				UNIQUE KEY id (id)
			);";
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );
		}
	}

	/**
	 * Preform upgrade tasks such as deleting and updating options
	 *
	 * @since Code Snippets 1.2
	 * @access private
	 *
	 * @return bool True on successful upgrade, false on failure
	 */
	function upgrade() {

		/* add backwards-compatibly for the CS_SAFE_MODE constant */
		if ( defined( 'CS_SAFE_MODE' ) && ! defined( 'CODE_SNIPPETS_SAFE_MODE' ) ) {
			define( 'CODE_SNIPPETS_SAFE_MODE', CS_SAFE_MODE );
		}

		/* get the current plugin version from the database */
		if ( get_option( 'cs_db_version' ) ) {
			$this->current_version = get_option( 'cs_db_version', $this->version );
			delete_option( 'cs_db_version' );
			add_site_option( 'code_snippets_version', $this->current_version );
		}
		else {
			$this->current_version = get_site_option( 'code_snippets_version', $this->version );
		}

		/* bail early if we're on the latest version */
		if ( $this->current_version < $this->version ) return false;

		if ( ! get_site_option( 'code_snippets_version' ) ) {

			/* This is the first time the plugin has run */

			$this->add_caps(); // register the capabilities ONCE ONLY

			if ( is_multisite() ) {
				$this->add_caps( 'multisite' ); // register the multisite capabilities ONCE ONLY
			}
		}

		/* migrate the recently_network_activated_snippets to the site options */
		if ( is_multisite() && get_option( 'recently_network_activated_snippets' ) ) {
			add_site_option( 'recently_activated_snippets', get_option( 'recently_network_activated_snippets', array() ) );
			delete_option( 'recently_network_activated_snippets' );
		}

		/* preform version specific upgrades */

		if ( $this->current_version < 1.5 ) {
			global $wpdb;

			/* Let's alter the name column to accept up to 64 characters */
			$wpdb->query( "ALTER TABLE $this->table CHANGE COLUMN name name VARCHAR(64) NOT NULL" );

			if ( is_multisite() ) {
				/* We must not forget the multisite table! */
				$wpdb->query( "ALTER TABLE $this->ms_table CHANGE COLUMN name name VARCHAR(64) NOT NULL" );
			}

			/* Add the custom capabilities that were introduced in version 1.5 */
			$this->add_roles();
		}

		if ( $this->current_version < 1.2 ) {
			/* The 'Complete Uninstall' option was removed in version 1.2 */
			delete_option( 'cs_complete_uninstall' );
		}

		if ( $this->current_version < $this->version ) {
			/* Update the current version */
			update_site_option( 'code_snippets_version', $this->version );
		}

		return true;
	}

	/**
	 * Load up the localization file if we're using WordPress in a different language
	 * Place it in this plugin's "languages" folder and name it "code-snippets-[value in wp-config].mo"
	 *
	 * If you wish to contribute a language file to be included in the Code Snippets package,
	 * please see the plugin's website at http://code-snippets.bungeshea.com
	 *
	 * @since Code Snippets 1.5
	 * @access private
	 *
	 * @return void
	 */
	function load_textdomain() {
		load_plugin_textdomain( 'code-snippets', false, dirname( $this->basename ) . '/languages/' );
	}

	/**
	 * Add the user capabilities
	 *
	 * @since Code Snippets 1.5
	 * @access public
	 *
	 * @uses $this->setup_roles() To register the capabilities
	 *
	 * @param string $scope Add site-specific or multisite-specific capabilities?
	 * @return void
	 */
	public function add_caps( $scope = '' ) {

		$network = ( $scope == 'multisite' || $scope == 'network' ? true : false );

		if ( $network && is_multisite() )
			$this->setup_ms_roles( 'add' );
		else
			$this->setup_roles( 'add' );
	}

	/**
	 * Remove the user capabilities
	 *
	 * @since Code Snippets 1.5
	 * @access public
	 *
	 * @uses $this->setup_roles() To register the capabilities
	 *
	 * @param string $scope Add site-specific or multisite-specific capabilities?
	 * @return void
	 */
	public function remove_caps( $scope = '' ) {

		$network = ( $scope == 'multisite' || $scope == 'network' ? true : false );

		if ( $network && is_multisite() )
			$this->setup_ms_roles( 'remove' );
		else
			$this->setup_roles( 'remove' );
	}

	/**
	 * Register the user roles and capabilities
	 *
	 * @since Code Snippets 1.5
	 * @access private
	 *
	 * @param string $install True to add the capabilities, false to remove
	 * @return void
	 */
	function setup_roles( $action = 'install' ) {

		if ( $action == 'install' || $action = 'add' )
			$install = true;

		elseif ( $action == 'uninstall' || $action = 'remove' )
			$install = false;

		else
			$install = true;


		$this->caps = apply_filters( 'code_snippets_caps', array(
			'manage_snippets',
			'install_snippets',
			'edit_snippets'
		) );

		$this->role = get_role( apply_filters( 'code_snippets_role', 'administrator' ) );

		foreach( $this->caps as $cap ) {
			if ( $install )
				$this->role->add_cap( $cap );
			else
				$this->role->remove_cap( $cap );
		}
	}

	/**
	 * Register the multisite user roles and capabilities
	 *
	 * @since Code Snippets 1.5
	 * @access private
	 *
	 * @param string $action Add or remove the capabilities
	 * @return void
	 */
	function setup_ms_roles( $action = 'install' ) {

		if ( ! is_multisite() ) return;

		if ( $action == 'install' || $action = 'add' )
			$install = true;

		elseif ( $action == 'uninstall' || $action = 'remove' )
			$install = false;

		else
			$install = true;

		$this->network_caps = apply_filters( 'code_snippets_network_caps', array(
			'manage_network_snippets',
			'install_network_snippets',
			'edit_network_snippets'
		) );

		$supers = get_super_admins();
		foreach( $supers as $admin ) {
			$user = new WP_User( 0, $admin );
			foreach( $this->network_caps as $cap ) {
				if ( $install )
					$user->add_cap( $cap );
				else
					$user->remove_cap( $cap );
			}
		}
	}

	/**
	 * Add the dashboard admin menu and subpages
	 *
	 * @since Code Snippets 1.0
	 * @access private
	 *
	 * @uses add_menu_page() To register a top-level menu
	 * @uses add_submenu_page() To register a submenu page
	 * @uses apply_filters() To retrieve the corrent menu slug
	 * @uses plugins_url() To retrieve the URL to a resource
	 * @return void
	 */
	function add_admin_menus() {

		$this->admin_manage = add_menu_page(
			__('Snippets', 'code-snippets'),
			__('Snippets', 'code-snippets'),
			'manage_snippets',
			apply_filters( 'code_snippets_manage_url', 'snippets' ),
			array( $this, 'display_admin_manage' ),
			plugins_url( 'images/icon16.png', $this->file ),
			67
		);

		add_submenu_page(
			apply_filters( 'code_snippets_manage_url', 'snippets' ),
			__('Snippets', 'code-snippets'),
			__('Manage Snippets', 'code-snippets'),
			'manage_snippets',
			apply_filters( 'code_snippets_manage_url', 'snippets' ),
			array( $this, 'display_admin_manage')
		);

		$this->admin_single = add_submenu_page(
			apply_filters( 'code_snippets_manage_url', 'snippets' ),
			__('Add New Snippet', 'code-snippets'),
			__('Add New', 'code-snippets'),
			'install_snippets',
			apply_filters( 'code_snippets_single_url', 'snippet' ),
			array( $this, 'display_admin_single' )
		);

		add_action( "load-$this->admin_manage", array( $this, 'load_admin_manage' ) );
		add_action( "load-$this->admin_single", array( $this, 'load_admin_single' ) );

		add_action( "admin_print_styles-$this->admin_single", array( $this, 'load_editor_styles' ) );
		add_action( "admin_print_scripts-$this->admin_single", array( $this, 'load_editor_scripts' ) );
	}

	/**
	 * Add the network dashboard admin menu and subpages
	 *
	 * @since Code Snippets 1.4
	 * @access private
	 *
	 * @uses add_menu_page() To register a top-level menu
	 * @uses add_submenu_page() To register a submenu page
	 * @uses apply_filters() To retrieve the corrent menu slug
	 * @uses plugins_url() To retrieve the URL to a resource
	 * @return void
	 */
	function add_network_admin_menus() {

		$this->admin_manage = add_menu_page(
			__('Snippets', 'code-snippets'),
			__('Snippets', 'code-snippets'),
			'manage_network_snippets',
			apply_filters( 'code_snippets_manage_url', 'snippets' ),
			array( $this, 'display_admin_manage' ),
			plugins_url( 'images/icon16.png', $this->file ),
			21
		);

		add_submenu_page(
			apply_filters( 'code_snippets_manage_url', 'snippets' ),
			__('Snippets', 'code-snippets'),
			__('Manage Snippets', 'code-snippets'),
			'manage_network_snippets',
			apply_filters( 'code_snippets_manage_url', 'snippets' ),
			array( $this, 'display_admin_manage' )
		);

		$this->admin_single = add_submenu_page(
			apply_filters( 'code_snippets_manage_url', 'snippets' ),
			__('Add New Snippet', 'code-snippets'),
			__('Add New', 'code-snippets'),
			'install_network_snippets',
			apply_filters( 'code_snippets_single_url', 'snippet' ),
			array( $this, 'display_admin_single' )
		);

		add_action( "load-$this->admin_manage", array( $this, 'load_admin_manage' ) );
		add_action( "load-$this->admin_single", array( $this, 'load_admin_single' ) );

		add_action( "admin_print_styles-$this->admin_single", array( $this, 'load_editor_styles' ) );
		add_action( "admin_print_scripts-$this->admin_single", array( $this, 'load_editor_scripts' ) );
	}

	/**
	 * Add an Import Snippets page to the network admin menu
	 * We need to do this as there is no Tools menu in the network
	 * admin, and so we cannot register an importer
	 *
	 * @since Code Snippets 1.6
	 * @access private
	 *
	 * @uses add_submenu_page() To register the menu page
	 * @uses apply_filters() To retrieve the corrent menu slug
	 * @uses add_action() To enqueue scripts and styles
	 * @return void
	 */
	function add_import_admin_menu() {

		$this->admin_import = add_submenu_page(
			apply_filters( 'code_snippets_manage_url', 'snippets' ),
			__('Import Snippets', 'code-snippets'),
			__('Import', 'code-snippets'),
			'import_snippets',
			'import-code-snippets',
			array( $this, 'display_admin_import' )
		);

		$this->admin_import_url = self_admin_url( 'admin.php?page=import-code-snippets' );
		add_action( "admin_print_styles-$this->admin_import", array( $this, 'load_stylesheet' ) );
		add_action( "load-$this->admin_import", array( $this, 'load_admin_import' ) );
	}

	/**
	 * Enqueue the icon stylesheet
	 *
	 * @since Code Snippets 1.0
	 * @access private
	 *
	 * @uses wp_enqueue_style() To add the stylesheet to the queue
	 *
	 * @return void
	 */
	function load_admin_icon_style() {

		wp_enqueue_style(
			'icon-snippets',
			plugins_url( 'assets/icon.css', $this->file ),
			false,
			$this->version
		);
	}

	/**
	 * Registers and loads the code editor's scripts
	 *
	 * @since Code Snippets 1.4
	 * @access private
	 *
	 * @uses wp_register_script()
	 * @uses wp_enqueue_style() To add the scripts to the queue
	 *
	 * @return void
	 */
	function load_editor_scripts() {

		/* CodeMirror package version */
		$version = '3.0';

		/* CodeMirror base framework */

		wp_register_script(
			'codemirror',
			plugins_url( 'assets/codemirror.js', $this->file ),
			false,
			$version
		);

		/* CodeMirror modes */

		$modes = array( 'php', 'xml', 'javascript', 'css', 'clike', 'htmlmixed' );

		foreach ( $modes as $mode ) {

			wp_register_script(
				"codemirror-mode-$mode",
				plugins_url( "assets/mode/$mode.js", $this->file ),
				array( 'codemirror' ),
				$version
			);
		}

		/* CodeMirror utilities */

		$utils = array( 'dialog', 'searchcursor', 'search', 'matchbrackets' );

		foreach ( $utils as $util ) {

			wp_register_script(
				"codemirror-util-$util",
				plugins_url( "assets/util/$util.js", $this->file ),
				array( 'codemirror' ),
				$version
			);
		}

		/* Enqueue the registered scripts */

		wp_enqueue_script( array(
			'codemirror-util-matchbrackets',
			'codemirror-mode-htmlmixed',
			'codemirror-mode-xml',
			'codemirror-mode-js',
			'codemirror-mode-css',
			'codemirror-mode-clike',
			'codemirror-mode-php',
			'codemirror-util-search',
		) );
	}

	/**
	 * Registers and loads the code editor's styles
	 *
	 * @since Code Snippets 1.4
	 * @access private
	 *
	 * @uses wp_register_style()
	 * @uses wp_enqueue_style() To add the stylesheets to the queue
	 *
	 * @return void
	 */
	function load_editor_styles() {

		/* CodeMirror package version */
		$version = '3.0';

		/* CodeMirror base framework */

		wp_register_style(
			'codemirror',
			plugins_url( 'assets/codemirror.css', $this->file ),
			false,
			$version
		);

		/* CodeMirror utilities */

		wp_register_style(
			'codemirror-util-dialog',
			plugins_url( 'assets/util/dialog.css', $this->file ),
			array( 'codemirror' ),
			$version
		);

		/* Enqueue the registered stylesheets */

		wp_enqueue_style( array(
			'codemirror',
			'codemirror-util-dialog',
		) );

	}

	/**
	 * Activates a snippet
	 *
	 * @since Code Snippets 1.5
	 * @access public
	 *
	 * @uses $wpdb To set the snippets' active status
	 *
	 * @param array $ids The ids of the snippets to activate
	 * @param string $scope Are the snippets multisite-wide or site-wide?
	 * @return void
	 */
	public function activate( $ids, $scope = '' ) {
		global $wpdb;

		$ids = (array) $ids;

		$table = $this->get_table_name( $scope );

		foreach( $ids as $id ) {
			$wpdb->update(
				$table,
				array( 'active' => '1' ),
				array( 'id' => $id ),
				array( '%d' ),
				array( '%d' )
			);
		}
	}

	/**
	 * Deactivates selected snippets
	 *
	 * @since Code Snippets 1.5
	 * @access public
	 *
	 * @uses $wpdb To set the snippets' active status
	 *
	 * @param array $ids The IDs of the snippets to deactivate
	 * @param string $scope Are the snippets multisite-wide or site-wide?
	 * @return void
	 */
	public function deactivate( $ids, $scope = '' ) {
		global $wpdb;

		$ids = (array) $ids;
		$recently_active = array();

		$table = $this->get_table_name( $scope );

		foreach( $ids as $id ) {
			$wpdb->update(
				$table,
				array( 'active' => '0' ),
				array( 'id' => $id ),
				array( '%d' ),
				array( '%d' )
			);
			$recently_active = array( $id => time() ) + (array) $recently_active;
		}

		if ( $table == $this->ms_table )
			update_site_option(
				'recently_activated_snippets',
				$recently_active + (array) get_site_option( 'recently_activated_snippets' )
			);
		else
			update_option(
				'recently_activated_snippets',
				$recently_active + (array) get_option( 'recently_activated_snippets' )
			);
	}

	/**
	 * Deletes a snippet from the database
	 *
	 * @since Code Snippets 1.5
	 * @access public
	 *
	 * @uses $wpdb To access the database
	 * @uses $this->get_table_name() To dynamically retrieve the name of the snippet table
	 */
	public function delete_snippet( $id, $scope = '' ) {
		global $wpdb;

		$table = $this->get_table_name( $scope );
		$id = intval( $id );

		$wpdb->query( "DELETE FROM $table WHERE id='$id' LIMIT 1" );
	}

	/**
	 * Saves a snippet to the database.
	 *
	 * @since Code Snippets 1.5
	 * @access public
	 *
	 * @uses $wpdb To update/add the snippet to the database
	 * @uses $this->get_table_name() To dynamically retrieve the name of the snippet table
	 *
	 * @param array $snippet The snippet to add/update to the database
	 * @return int|bool The ID of the snippet on success, false on failure
	 */
	public function save_snippet( $snippet, $scope = '' ) {
		global $wpdb;

		$name = mysql_real_escape_string( htmlspecialchars( $snippet['name'] ) );
		$description = mysql_real_escape_string( htmlspecialchars( $snippet['description'] ) );
		$code = mysql_real_escape_string( htmlspecialchars( $snippet['code'] ) );

		if ( empty( $name ) or empty( $code ) )
			return false;

		$table = $this->get_table_name( $scope );

		if ( isset( $snippet['id'] ) && ( intval( $snippet['id'] ) != 0 )  ) {
			$wpdb->query( "UPDATE $table SET
				name='$name',
				description='$description',
				code='$code' WHERE id=" . intval( $snippet['id'] ) . "
				LIMIT 1"
			);
			return intval( $snippet['id'] );
		} else {
			$wpdb->query(
				"INSERT INTO $table(name,description,code)
				VALUES ('$name','$description','$code')"
			);
			return $wpdb->insert_id;
		}
	}

	/**
	 * Imports snippets from an XML file
	 *
	 * @since Code Snippets 1.5
	 * @access public
	 *
	 * @uses $this->save_snippet() To add the snippets to the database
	 *
	 * @param file $file The path to the XML file to import
	 * @param string $scope Import into network-wide table or site-wide table?
	 * @return mixed The number of snippets imported on success, false on failure
	 */
	public function import( $file, $scope = '' ) {

		if ( ! file_exists( $file ) || ! is_file( $file ) )
			return false;

		$xml = simplexml_load_file( $file );

		foreach ( $xml->children() as $child ) {
			$this->save_snippet( array(
				'name' => $child->name,
				'description' => $child->description,
				'code' => $child->code,
			), $scope );
		}
		return $xml->count();
	}

	/**
	 * Exports snippets as an XML file
	 *
	 * @since Code Snippets 1.5
	 * @access public
	 *
	 * @uses code_snippets_export() To export selected snippets
	 * @uses $this->get_table_name() To dynamically retrieve the name of the snippet table
	 *
	 * @param array $id An array if the IDs of the snippets to export
	 * @param string $scope Is the snippet a network-wide or site-wide snippet?
	 * @return void
	 */
	public function export( $ids, $scope = '' ) {

		$table = $this->get_table_name( $scope );

		if ( ! function_exists( 'code_snippets_export' ) )
			require_once $this->plugin_dir . 'includes/export.php';

		code_snippets_export( $ids, 'xml', $table );
	}

	/**
	 * Exports snippets as a PHP file
	 *
	 * @since Code Snippets 1.5
	 * @access public
	 *
	 * @uses code_snippets_export() To export selected snippets
	 * @uses $this->get_table_name() To dynamically retrieve the name of the snippet table
	 *
	 * @param array $id An array if the IDs of the snippets to export
	 * @param string $scope Is the snippet a network-wide or site-wide snippet?
	 * @return void
	 */
	public function export_php( $ids, $scope = '' ) {

		$table = $this->get_table_name( $scope );

		if ( ! function_exists( 'code_snippets_export' ) )
			require_once $this->plugin_dir . 'includes/export.php';

		code_snippets_export( $ids, 'php', $table );
	}

	/**
	 * Execute a snippet
	 *
	 * Code must NOT be escaped, as
	 * it will be executed directly
	 *
	 * @since Code Snippets 1.5
	 * @access public
	 *
	 * @param string $code The snippet code to execute
	 * @return $result The result of the code execution
	 */
	public function execute_snippet( $code ) {
		ob_start();
		$result = eval( $code );
		$output = ob_get_contents();
		ob_end_clean();
		return $result;
	}

	/**
	 * Replaces the text 'Add New Snippet' with 'Edit Snippet'
	 *
	 * @since Code Snippets 1.1
	 * @access private
	 *
	 * @param $title The current page title
	 * @return $title The modified page title
	 */
	function admin_single_title( $title ) {
		return str_ireplace(
			__('Add New Snippet', 'code-snippets'),
			__('Edit Snippet', 'code-snippets'),
			$title
		);
	}

	/**
	 * Processes any action command and loads the help tabs
	 *
	 * @since Code Snippets 1.0
	 * @access private
	 *
	 * @uses $wpdb To activate, deactivate and delete snippets
	 *
	 * @return void
	 */
	function load_admin_manage() {
		global $wpdb;

		$this->create_tables(); // create the snippet tables if they do not exist

		if ( isset( $_GET['action'], $_GET['id'] ) ) :

			$id = intval( $_GET['id'] );

			$_SERVER['REQUEST_URI'] = remove_query_arg( array( 'action', 'id' ) );

			if ( 'activate' == $_GET['action'] ) {
				$this->activate( $id );
				wp_redirect( add_query_arg( 'activate', true ) );
			}
			elseif ( 'deactivate' == $_GET['action'] ) {
				$this->deactivate( $id );
				wp_redirect( add_query_arg( 'deactivate', true ) );
			}
			elseif ( 'delete' == $_GET['action'] ) {
				$this->delete_snippet( $id );
				wp_redirect( add_query_arg( 'delete', true ) );
			}
			elseif ( 'export' == $_GET['action'] ) {
				$this->export( $id );
			}
			elseif ( 'export-php' == $_GET['action'] ) {
				$this->export_php( $id );
			}

		endif;

		include $this->plugin_dir . 'includes/help/manage.php'; // Load the help tabs

		/**
		 * Initialize the snippet table class
		 */
		require_once $this->plugin_dir . 'includes/class-list-table.php';

		global $code_snippets_list_table;
		$code_snippets_list_table = new Code_Snippets_List_Table();
		$code_snippets_list_table->prepare_items();
	}

	/**
	 * Loads the help tabs for the Edit Snippets page
	 *
	 * @since Code Snippets 1.0
	 * @access private
	 *
	 * @uses $wpdb To save the posted snippet to the database
	 * @uses wp_redirect To pass the results to the page
	 *
	 * @return void
	 */
	function load_admin_single() {

		$this->create_tables(); // create the snippet tables if they do not exist

		if ( isset( $_REQUEST['save_snippet'] ) ) {

			if ( isset( $_REQUEST['snippet_id'] ) ) {
				$result = $this->save_snippet( array(
					'name' => $_REQUEST['snippet_name'],
					'description' => $_REQUEST['snippet_description'],
					'code' => $_REQUEST['snippet_code'],
					'id' => $_REQUEST['snippet_id'],
				) );
			} else {
				$result = $this->save_snippet( array(
					'name' => $_REQUEST['snippet_name'],
					'description' => $_REQUEST['snippet_description'],
					'code' => $_REQUEST['snippet_code'],
				) );
			}

			$_SERVER['REQUEST_URI'] = remove_query_arg( array( 'added', 'updated', 'invalid' ) );

			if ( ! $result || $result < 1 ) {
				wp_redirect( add_query_arg( 'invalid', true ) );
			}
			elseif ( isset( $_REQUEST['snippet_id'] ) ) {
				wp_redirect( add_query_arg(	array(
					'edit' => $result,
					'updated' => true
				) ) );
			}
			else {
				wp_redirect( add_query_arg( array(
					'edit' => $result,
					'added' => true
				) ) );
			}
		}

		if ( isset( $_GET['edit'] ) )
			add_filter( 'admin_title',  array( $this, 'admin_single_title' ) );

		include $this->plugin_dir . 'includes/help/single.php'; // Load the help tabs

	}

	/**
	 * Processes import files and loads the help tabs for the Import Snippets page
	 *
	 * @since Code Snippets 1.3
	 *
	 * @uses $this->import() To process the import file
	 * @uses wp_redirect() To pass the import results to the page
	 * @uses add_query_arg() To append the results to the current URI
	 *
	 * @return void
	 */
	function load_admin_import() {

		$this->create_tables(); // create the snippet tables if they do not exist

		if ( isset( $_FILES['code_snippets_import_file']['tmp_name'] ) ) {
			$count = $this->import( $_FILES['code_snippets_import_file']['tmp_name'] );
			if ( $count ) {
				wp_redirect( add_query_arg( 'imported', $count ) );
			}
		}
		require_once $this->plugin_dir . 'includes/help/import.php';
	}

	/**
	 * Displays the Manage Snippets page
	 *
	 * @since Code Snippets 1.0
	 * @access private
	 *
	 * @return void
	 */
	function display_admin_manage() {
		require_once $this->plugin_dir . 'includes/admin/manage.php';
	}

	/**
	 * Displays the Add New/Edit Snippet page
	 *
	 * @since Code Snippets 1.0
	 * @access private
	 *
	 * @return void
	 */
	function display_admin_single() {
		require_once $this->plugin_dir . 'includes/admin/single.php';
	}

	/**
	 * Displays the Import Snippets page
	 *
	 * @since Code Snippets 1.3
	 * @access private
	 *
	 * @return void
	 */
	function display_admin_import() {
		require_once $this->plugin_dir . 'includes/admin/import.php';
	}

	/**
	 * Adds a link pointing to the Manage Snippets page
	 *
	 * @since Code Snippets 1.0
	 * @access private
	 *
	 * @return void
	 */
	function settings_link( $links ) {
		array_unshift( $links, sprintf(
			'<a href="%1$s" title="%2$s">%3$s</a>',
			$this->admin_manage_url,
			__('Manage your existing snippets', 'code-snippets'),
			__('Manage', 'code-snippets')
		) );
		return $links;
	}

	/**
	 * Adds extra links related to the plugin
	 *
	 * @since Code Snippets 1.2
	 * @access private
	 */
	function plugin_meta( $links, $file ) {

		if ( $file != $this->basename ) return $links;

		$format = '<a href="%1$s" title="%2$s">%3$s</a>';

		return array_merge( $links, array(
			sprintf( $format,
				'http://wordpress.org/extend/plugins/code-snippets/',
				__('Visit the WordPress.org plugin page', 'code-snippets'),
				__('About', 'code-snippets')
			),
			sprintf( $format,
				'http://wordpress.org/support/plugin/code-snippets/',
				__('Visit the support forums', 'code-snippets'),
				__('Support', 'code-snippets')
			),
			sprintf( $format,
				'http://code-snippets.bungeshea.com/donate/',
				__('Support this plugin\'s development', 'code-snippets'),
				__('Donate', 'code-snippets')
			)
		) );
	}

	/**
	 * Run the active snippets
	 *
	 * @since Code Snippets 1.0
	 * @access private
	 *
	 * @uses $wpdb To grab the active snippets from the database
	 * @uses $this->execute_snippet() To execute a snippet
	 * @uses $this->get_table_name() To retrieve the name of the snippet table
	 *
	 * @param string $scope Execute network-wide or site-wide snippets?
	 * @return void
	 */
	function run_snippets() {

		if ( defined( 'CODE_SNIPPETS_SAFE_MODE' ) && CODE_SNIPPETS_SAFE_MODE )
			return;

		global $wpdb;

		// grab the active snippets from the database
		$active_snippets = $wpdb->get_results( "SELECT code FROM $this->table WHERE active=1;" );

		if ( is_multisite() ) {
			$active_snippets = array_merge(
				$wpdb->get_results( "SELECT code FROM $this->ms_table WHERE active=1;" ),
				$active_snippets
			);
		}

		if ( count( $active_snippets ) ) {
			foreach( $active_snippets as $snippet ) {
				// execute the php code
				$this->execute_snippet( htmlspecialchars_decode( stripslashes( $snippet->code ) ) );
			}
		}
	}
}

/**
 * The global variable in which the Code Snippets class is stored
 *
 * @since Code Snippets 1.0
 * @access public
 */
global $code_snippets;
$code_snippets = new Code_Snippets;

/* set up a pointer in the old variable (for backwards-compatibility) */
global $cs;
$cs = &$code_snippets;

endif; // class exists check

register_uninstall_hook( $code_snippets->file, 'code_snippets_uninstall' );

/**
 * Cleans up data created by the Code_Snippets class
 *
 * @since Code Snippets 1.2
 * @access private
 *
 * @uses $wpdb To remove tables from the database
 * @uses $code_snippets->get_table_name() To find out which table to drop
 * @uses is_multisite() To check the type of installation
 * @uses switch_to_blog() To switch between blogs
 * @uses restore_current_blog() To switch between blogs
 * @uses delete_option() To remove site options
 *
 * @return void
 */
function code_snippets_uninstall() {
	global $wpdb, $code_snippets;
	if ( is_multisite() ) {
		$blogs = $wpdb->get_results( "SELECT blog_id FROM $wpdb->blogs", ARRAY_A );
		if ( $blogs ) {
			foreach( $blogs as $blog ) {
				switch_to_blog( $blog['blog_id'] );
				$wpdb->query( 'DROP TABLE IF EXISTS ' . apply_filters( 'code_snippets_table', $wpdb->prefix . 'snippets' ) );
				delete_option( 'cs_db_version' );
				delete_option( 'recently_activated_snippets' );
				$code_snippets->remove_caps();
			}
			restore_current_blog();
		}
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $code_snippets->get_table_name( 'multisite' ) );
		delete_site_option( 'recently_activated_snippets' );
		$code_snippets->remove_caps( 'multisite' );
	} else {
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $code_snippets->get_table_name( 'site' ) );
		delete_option( 'recently_activated_snippets' );
		delete_option( 'cs_db_version' );
		$code_snippets->remove_caps();
	}

	delete_site_option( 'code_snippets_version' );
}