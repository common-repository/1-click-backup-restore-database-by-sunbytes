<?php
/**
 * Plugin Name: 1-click Backup & Restore Database
 * Description: This plugin is still on developing & testing without any support. Please don't use for live site.
 * Version: 1.0.3
 * Plugin URI: https://sunbytes.vn/
 * Author: Sunbytes
 * Author URI: https://sunbytes.vn/
 * Text Domain: sbocbr
 * Domain Path: /languages/
 * License: GPL v3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


define( 'SBOCUR_VERSION', '1.0.3' );

class Sbocbr {

	// vars
	var $settings;
	var $bk_file;
	var $fp;

	function __construct() {
	}

	function initialize() {
		// vars
		$this->settings = array(
			// basic
			'name'          => __( '1-Click Backup & Restore Database', 'sbocbr' ),
			'slug'          => 'sb-one-click-backup-restore',
			'version'       => '1.0.0',
			'admin_notices' => array(),
			// urls
			'basename'      => plugin_basename( __FILE__ ),
			'dir'           => plugin_dir_path( __FILE__ ),
			'url'           => plugin_dir_url( __FILE__ ),

			'capability'       => apply_filters( 'sbocbr_capability', 'manage_options' ),
			'eligible'         => false,
			'rows_per_segment' => 100
		);

		// upload dir
		$upload_dir                   = wp_upload_dir();
		$this->settings['upload_dir'] = trailingslashit( $upload_dir['basedir'] . '/' . $this->settings['slug'] );
		$this->settings['upload_url'] = trailingslashit( $upload_dir['baseurl'] . '/' . $this->settings['slug'] );

		// backup file name
		$this->settings['bk_filename']     = DB_NAME . '.sql';
		$this->settings['bk_filename_zip'] = DB_NAME . '.zip';
		$this->bk_file                     = $this->get_bk_file();

		// add libs
		require_once( $this->settings['dir'] . 'libs.php' );

		// actions
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );

		// enqueue style
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_style' ) );

		// ajax
		add_action( 'wp_ajax_sbocbr_init_export', array( $this, 'ajax_sbocbr_init_export' ) );
		add_action( 'wp_ajax_sbocbr_process_table', array( $this, 'ajax_sbocbr_process_table' ) );
		add_action( 'wp_ajax_sbocbr_zip_exported_file', array( $this, 'ajax_sbocbr_zip_exported_file' ) );
		add_action( 'wp_ajax_sbocbr_restore_backup', array( $this, 'ajax_sbocbr_restore_backup' ) );

		// hook install
		register_activation_hook( WP_PLUGIN_DIR . '/' . $this->settings['basename'], array(
			$this,
			'plugin_activation'
		) );
		register_deactivation_hook( WP_PLUGIN_DIR . '/' . $this->settings['basename'], array(
			$this,
			'plugin_deactivation'
		) );

		add_action( 'init', array( $this, 'check_requirements' ), 5 );

	}

	function plugin_activation() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		if ( ! is_dir( $this->settings['upload_dir'] ) ) {
			mkdir( $this->settings['upload_dir'], 0777, true );
			$findex = $this->open( $this->settings['upload_dir'] . 'index.html' );
			if ( $findex ) {
				fclose( $findex );
			}

		}
	}

	function plugin_deactivation() {

	}

	function admin_menu() {
		add_menu_page( __( '1-Click Backup & Restore Database', 'sbocbr' ), __( '1-Click Backup DB', 'sbocbr' ), $this->settings['capability'], 'sbocbr-main', array(
			$this,
			'page_view'
		), 'dashicons-backup' );
	}

	function enqueue_style() {
		if ( is_admin() ) {
			wp_enqueue_style( 'sbocbr-style', trailingslashit( sbocbr_get_setting( 'url' ) ) . 'assets/css/sbocbr.css', array(), SBOCUR_VERSION );
		}
	}

	function check_requirements() {
		$result = true;
		sbocbr_update_setting( 'eligible', $result );

		if ( ! is_dir( $this->settings['upload_dir'] ) ) {
			$result = false;
			sbocbr_add_admin_notice( 'error', __( 'Backup folder not found! Please try to re-activate this plugin!', 'sbocbr' ) );
			sbocbr_update_setting( 'eligible', $result );
		}
		if ( ! is_writable( $this->settings['upload_dir'] ) ) {
			$result = false;
			sbocbr_add_admin_notice( 'error', __( 'The backup directory is not writeable! Please check permission!', 'sbocbr' ) );
			sbocbr_update_setting( 'eligible', $result );
		}

		return $result;
	}

	function page_view() {
		require_once( $this->settings['dir'] . 'views/main.php' );
	}

	function get_bk_file() {

		if ( ! empty( $this->bk_file ) ) {
			return $this->bk_file;
		} else {
			$file_dir = $this->settings['upload_dir'] . $this->settings['bk_filename_zip'];
			if ( file_exists( $file_dir ) ) {

				$this->bk_file = (object) array(
					'name'     => $this->settings['bk_filename_zip'],
					'dir'      => $file_dir,
					'url'      => $this->settings['upload_url'] . $this->settings['bk_filename_zip'],
					'modified' => filemtime( $file_dir ),
					'size'     => filesize( $file_dir ),
				);

				return $this->bk_file;
			}
		}

		return false;
	}

	function remove_bk_file() {
		$file_dir = $this->settings['upload_dir'] . $this->settings['bk_filename'];
		if ( file_exists( $file_dir ) ) {
			@unlink( $file_dir );
		}

		return;
	}

	function ajax_return_error( $message, $remove_sql = true ) {
		$result = array(
			'status'  => 0,
			'message' => $message
		);

		if ( $remove_sql ) {
			$this->remove_bk_file();
		}

		echo json_encode( $result );
		die;
	}

	/**
	 * Better addslashes for SQL queries.
	 *
	 * @param string $a_string
	 * @param bool $is_like
	 *
	 * @return mixed
	 */
	function sql_addslashes( $a_string = '', $is_like = false ) {
		if ( $is_like ) {
			$a_string = str_replace( '\\', '\\\\\\\\', $a_string );
		} else {
			$a_string = str_replace( '\\', '\\\\', $a_string );
		}

		return str_replace( '\'', '\\\'', $a_string );
	}

	/**
	 * Add backquotes to tables and db-names in
	 * SQL queries. Taken from phpMyAdmin.
	 *
	 * @param $a_name
	 *
	 * @return array|string
	 */
	function backquote( $a_name ) {
		if ( ! empty( $a_name ) && $a_name != '*' ) {
			if ( is_array( $a_name ) ) {
				$result = array();
				reset( $a_name );
				while ( list( $key, $val ) = each( $a_name ) ) {
					$result[ $key ] = '`' . $val . '`';
				}

				return $result;
			} else {
				return '`' . $a_name . '`';
			}
		} else {
			return $a_name;
		}
	}

	function open( $filename = '', $mode = 'w' ) {
		if ( '' == $filename ) {
			return false;
		}
		$fp = @fopen( $filename, $mode );

		return $fp;
	}

	function close( $fp ) {
		fclose( $fp );
	}

	/**
	 *  Write to the backup file
	 *
	 * @param $fp
	 * @param $query_line
	 *
	 * @return int
	 */
	function stow( $fp, $query_line ) {
		return @fwrite( $fp, $query_line );
	}

	function export_database( $fp, $table, $table_structure ) {
		global $wpdb;
		// ---------------------------- Parse header & structure of table ------------------------------------
		// Add SQL statement to drop existing table
		$this->stow( $fp, "\n\n" );
		$this->stow( $fp, "#\n" );
		$this->stow( $fp, "# " . sprintf( __( 'Delete any existing table %s', 'sbocbr' ), $this->backquote( $table ) ) . "\n" );
		$this->stow( $fp, "#\n" );
		$this->stow( $fp, "\n" );
		$this->stow( $fp, "DROP TABLE IF EXISTS " . $this->backquote( $table ) . ";\n" );

		// Table structure
		// Comment in SQL-file
		$this->stow( $fp, "\n\n" );
		$this->stow( $fp, "#\n" );
		$this->stow( $fp, "# " . sprintf( __( 'Table structure of table %s', 'sbocbr' ), $this->backquote( $table ) ) . "\n" );
		$this->stow( $fp, "#\n" );
		$this->stow( $fp, "\n" );

		$create_table = $wpdb->get_results( "SHOW CREATE TABLE $table", ARRAY_N );
		if ( false === $create_table ) {
			$err_msg = sprintf( __( 'Error with SHOW CREATE TABLE for %s.', 'sbocbr' ), $table );
			$this->stow( $fp, "#\n# $err_msg\n#\n" );
			$this->ajax_return_error( $err_msg );
		}
		$this->stow( $fp, $create_table[0][1] . ' ;' );

		// Comment in SQL-file
		$this->stow( $fp, "\n\n" );
		$this->stow( $fp, "#\n" );
		$this->stow( $fp, '# ' . sprintf( __( 'Data contents of table %s', 'sbocbr' ), $this->backquote( $table ) ) . "\n" );
		$this->stow( $fp, "#\n" );


		// ---------------------------- Parse content of table ------------------------------------
		$defs = array();
		$ints = array();
		foreach ( $table_structure as $struct ) {
			if ( ( 0 === strpos( $struct->Type, 'tinyint' ) ) ||
			     ( 0 === strpos( strtolower( $struct->Type ), 'smallint' ) ) ||
			     ( 0 === strpos( strtolower( $struct->Type ), 'mediumint' ) ) ||
			     ( 0 === strpos( strtolower( $struct->Type ), 'int' ) ) ||
			     ( 0 === strpos( strtolower( $struct->Type ), 'bigint' ) )
			) {
				$defs[ strtolower( $struct->Field ) ] = ( null === $struct->Default ) ? 'NULL' : $struct->Default;
				$ints[ strtolower( $struct->Field ) ] = "1";
			}
		}


		// Batch by $row_inc
		$row_start = 0;
		$row_inc   = sbocbr_get_setting( 'rows_per_segment' );

		do {
			$where = '';

			if ( ! ini_get( 'safe_mode' ) ) {
				@set_time_limit( 15 * 60 );
			}
			$table_data = $wpdb->get_results( "SELECT * FROM $table $where LIMIT {$row_start}, {$row_inc}", ARRAY_A );

			$entries = 'INSERT INTO ' . $this->backquote( $table ) . ' VALUES (';
			//    \x08\\x09, not required
			$search  = array( "\x00", "\x0a", "\x0d", "\x1a" );
			$replace = array( '\0', '\n', '\r', '\Z' );
			if ( $table_data ) {
				foreach ( $table_data as $row ) {
					$values = array();
					foreach ( $row as $key => $value ) {
						if ( ! empty( $ints[ strtolower( $key ) ] ) ) {
							// make sure there are no blank spots in the insert syntax,
							// yet try to avoid quotation marks around integers
							$value    = ( null === $value || '' === $value ) ? $defs[ strtolower( $key ) ] : $value;
							$values[] = ( '' === $value ) ? "''" : $value;
						} else {
							$values[] = "'" . str_replace( $search, $replace, $this->sql_addslashes( $value ) ) . "'";
						}
					}
					$this->stow( $fp, " \n" . $entries . implode( ', ', $values ) . ');' );
				}
				$row_start += $row_inc;
			}
		} while ( ( count( $table_data ) > 0 ) );
	}

	function import_database() {
		global $wpdb;

		$file_dir = $this->settings['upload_dir'] . $this->settings['bk_filename'];
		if ( ! file_exists( $file_dir ) ) {
			$this->ajax_return_error( __( 'Backup file not found. Restore fail!', 'sbocbr' ) );
		}

		$tables = $wpdb->get_col( 'SHOW TABLES' );
		if ( ! empty( $tables ) ) {
			foreach ( $tables as $table ) {
				$wpdb->query( "DROP TABLE $table" );
			}
		}


		$templine = '';
		$lines    = file( $file_dir );
		foreach ( $lines as $line ) {
			if ( substr( $line, 0, 2 ) == '--' || substr( $line, 0, 1 ) == '#' || $line == '' ) {
				continue;
			}

			// Add this line to the current segment
			$templine .= $line;
			// If it has a semicolon at the end, it's the end of the query
			if ( substr( trim( $line ), - 1, 1 ) == ';' ) {
				// Perform the query
				$wpdb->query( $templine );
				// Reset temp variable to empty
				$templine = '';
			}
		}
	}

	function ajax_sbocbr_init_export() {
		if ( ! sbocbr_user_can_admin() ) {
			die;
		}

		global $wpdb;
		$result = array(
			'status' => 0,
		);

		if ( ! sbocbr_get_setting( 'eligible' ) ) {
			$this->ajax_return_error( __( 'Backup fail! Please check requirements before try again!', 'sbocbr' ) );
		}

		// create file
		$fp = $this->open( $this->settings['upload_dir'] . $this->settings['bk_filename'] );
		if ( ! $fp ) {
			$this->ajax_return_error( __( 'Cannot create backup file! Please check your folder permission!', 'sbocbr' ) );
		} else {

			//Begin new backup of MySql
			$this->stow( $fp, "# " . sbocbr_get_setting( 'name' ) . "\n" );
			$this->stow( $fp, "#\n" );
			$this->stow( $fp, "# " . sprintf( __( 'Generated: %s', 'sbocbr' ), date( "l j. F Y H:i T" ) ) . "\n" );
			$this->stow( $fp, "# " . sprintf( __( 'Hostname: %s', 'sbocbr' ), DB_HOST ) . "\n" );
			$this->stow( $fp, "# " . sprintf( __( 'Database: %s', 'sbocbr' ), $this->backquote( DB_NAME ) ) . "\n" );
			$this->stow( $fp, "# --------------------------------------------------------\n" );

			fclose( $fp );
		}


		// get database structure
		session_start();
		if ( isset( $_SESSION['sbocbr_tables'] ) ) {
			unset( $_SESSION['sbocbr_tables'] );
		}

		$tables = $wpdb->get_col( 'SHOW TABLES' );
		if ( ! empty( $tables ) ) {
			$_SESSION['sbocbr_tables'] = array();
			foreach ( $tables as $table ) {
				$_SESSION['sbocbr_tables'][] = $table;
			}
		}

		$result['status']  = 1;
		$result['message'] = sprintf( __( 'Exporting table `%s`', 'sbocbr' ), reset( $tables ) ) . '...';


		echo json_encode( $result );
		die;
	}

	function ajax_sbocbr_process_table() {
		session_start();
		$result['status'] = 0;
		if ( isset( $_SESSION['sbocbr_tables'] ) && ! empty( $_SESSION['sbocbr_tables'] ) ) {

			$table = array_shift( $_SESSION['sbocbr_tables'] );
			global $wpdb;

			$table_structure = $wpdb->get_results( "DESCRIBE $table" );
			if ( ! $table_structure ) {
				$this->ajax_return_error( sprintf( __( 'Error getting table details: `%s`!', 'sbocbr' ), $table ) );
			}

			// open file for writing
			$fp = $this->open( $this->settings['upload_dir'] . $this->settings['bk_filename'], 'a' );
			if ( ! $fp ) {
				$this->ajax_return_error( __( 'Cannot create backup file! Please check your folder permission!', 'sbocbr' ) );
			} else {
				$this->export_database( $fp, $table, $table_structure );
				fclose( $fp );
			}


			$result['status'] = 1;
			if ( ! empty( $_SESSION['sbocbr_tables'] ) ) {
				$result['is_done'] = 0;
				$result['message'] = sprintf( __( 'Exporting table `%s`', 'sbocbr' ), reset( $_SESSION['sbocbr_tables'] ) ) . '...';
			} else {
				$result['is_done'] = 1;
				$result['message'] = __( "Exporting is done. Now we're zipping the backup file", "sbocbr" ) . "...";
			}

		} else {
			$this->ajax_return_error( __( 'There are something wrong, please try again!', 'sbocbr' ) );
		}
		echo json_encode( $result );
		die;
	}

	function ajax_sbocbr_zip_exported_file() {

		$result['status']  = 1;
		$result['message'] = __( 'Database has been successful backup!', 'sbocbr' );

		$zip          = new ZipArchive();
		$zip_filename = $this->settings['upload_dir'] . $this->settings['bk_filename_zip'];

		if ( $zip->open( $zip_filename, ZipArchive::CREATE ) !== true ) {
			$this->ajax_return_error( __( 'Cannot compress file. Backup fail!', 'sbocbr' ) );
		}

		$file_dir = $this->settings['upload_dir'] . $this->settings['bk_filename'];
		if ( ! file_exists( $file_dir ) ) {
			$this->ajax_return_error( __( 'SQL file not found. Backup fail!', 'sbocbr' ) );
		}

		$zip->addFile( $file_dir, $this->settings['bk_filename'] );
		$zip->close();


		$this->remove_bk_file();
		echo json_encode( $result );
		die;
	}


	function ajax_sbocbr_restore_backup() {
		$result['status']  = 1;
		$result['message'] = __( 'Database has been successful restored!', 'sbocbr' );

		// get the absolute path to $file
		// @todo get file name via ajax
		$zip_filename = $this->settings['upload_dir'] . $this->settings['bk_filename_zip'];
		$path         = pathinfo( realpath( $zip_filename ), PATHINFO_DIRNAME );

		$zip = new ZipArchive;
		$res = $zip->open( $zip_filename );
		if ( ! $res ) {
			$this->ajax_return_error( __( 'Backup file not found. Restore fail!', 'sbocbr' ) );
		}

		$zip->extractTo( $path );
		$zip->close();


		// import function
		$this->import_database();

		$this->remove_bk_file();
		echo json_encode( $result );
		die;
	}

	function restore_tables() {
		// Name of the file

		$data_file = ABSPATH . '/_db/local/' . DB_NAME . '.sql';
		global $wpdb;
// Drop table first
		$link = mysql_connect( DB_HOST, DB_USER, DB_PASSWORD );
		mysql_select_db( DB_NAME, $link );
		$result = mysql_query( 'SHOW TABLES' );
		$data   = '';
		while ( $row = mysql_fetch_row( $result ) ) {
			$data .= $row[0] . ',';
			// $tables[] = $row[0];
			// mysql_query('DROP TABLE '.$row[0]);
		}
		mysql_query( 'DROP TABLE ' . substr( $data, 0, - 1 ) );
//
//
//exit;
// Temporary variable, used to store current query
		$templine = '';
// Read in entire file
		$lines = file( $data_file );
// Loop through each line
		foreach ( $lines as $line ) {
			// Skip it if it's a comment
			if ( substr( $line, 0, 2 ) == '--' || $line == '' ) {
				continue;
			}

			// Add this line to the current segment
			$templine .= $line;
			// If it has a semicolon at the end, it's the end of the query
			if ( substr( trim( $line ), - 1, 1 ) == ';' ) {
				// Perform the query
				mysql_query( $templine ) or print( 'Error performing query \'<strong>' . $templine . '\': ' . mysql_error() . '<br /><br />' );
				// Reset temp variable to empty
				$templine = '';
			}
		}
		echo "Tables imported successfully";
	}


}   // EOC


function sbocbr() {
	global $sbocbr;
	if ( ! isset( $sbocbr ) ) {
		$sbocbr = new sbocbr();
		$sbocbr->initialize();
	}

	return $sbocbr;
}

sbocbr();