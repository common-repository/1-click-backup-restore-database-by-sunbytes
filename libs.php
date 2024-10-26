<?php
function sbocbr_get_setting( $name, $allow_filter = true ) {
	// vars
	$r = null;

	// load from sbocbr if available
	if ( isset( sbocbr()->settings[ $name ] ) ) {
		$r = sbocbr()->settings[ $name ];
	}

	// filter for 3rd party customization
	if ( $allow_filter ) {
		$r = apply_filters( "sbocbr/settings/{$name}", $r );
	}

	// return
	return $r;
}

function sbocbr_update_setting( $name, $value ) {
	sbocbr()->settings[ $name ] = $value;
}


function sbocbr_append_setting( $name, $value ) {
	// createa array if needed
	if ( ! isset( sbocbr()->settings[ $name ] ) ) {

		sbocbr()->settings[ $name ] = array();

	}

	// append to array
	sbocbr()->settings[ $name ][] = $value;
}

function sbocbr_sql_addslashes( $a_string = '', $is_like = false ) {
	if ( $is_like ) {
		$a_string = str_replace( '\\', '\\\\\\\\', $a_string );
	} else {
		$a_string = str_replace( '\\', '\\\\', $a_string );
	}

	return str_replace( '\'', '\\\'', $a_string );
}

function sbocbr_backquote( $a_name ) {
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

function sbocbr_file_open( $filename = '', $mode = 'w' ) {
	if ( '' == $filename ) {
		return false;
	}
	$fp = @fopen( $filename, $mode );

	return $fp;
}

function sbocbr_file_close( $fp ) {
	fclose( $fp );
}

function sbocbr_format_filesize( $bytes ) {
	if ( $bytes >= 1073741824 ) {
		$bytes = number_format( $bytes / 1073741824, 2 ) . ' GB';
	} elseif ( $bytes >= 1048576 ) {
		$bytes = number_format( $bytes / 1048576, 2 ) . ' MB';
	} elseif ( $bytes >= 1024 ) {
		$bytes = number_format( $bytes / 1024, 2 ) . ' KB';
	} elseif ( $bytes > 1 ) {
		$bytes = $bytes . ' bytes';
	} elseif ( $bytes == 1 ) {
		$bytes = $bytes . ' byte';
	} else {
		$bytes = '0 bytes';
	}

	return $bytes;
}

function sbocbr_user_can_admin() {
	return current_user_can( sbocbr_get_setting( 'capability' ) );
}


function sbocbr_get_admin_notices() {
	// vars
	$admin_notices = sbocbr_get_setting( 'admin_notices' );


	// validate
	if ( empty( $admin_notices ) ) {
		$admin_notices = array();
	}


	// return
	return $admin_notices;
}

function sbocbr_add_admin_notice( $type = 'error', $message = '' ) {
	// vars
	$admin_notices = sbocbr_get_admin_notices();


	// add to array
	if ( ! empty( $message ) ) {
		$admin_notices[] = array(
			'type'    => $type,
			'message' => $message
		);
	}

	// update
	sbocbr_update_setting( 'admin_notices', $admin_notices );


	// return
	return ( count( $admin_notices ) - 1 );

}

function sbocbr_show_admin_notices() {
	$display = '';

	$admin_notices = sbocbr_get_admin_notices();

	if ( ! empty( $admin_notices ) ) {
		foreach ( $admin_notices as $item ) {
			echo '<div class="notice-item notice-item-' . $item['type'] . '"><p>' . $item['message'] . '</p></div>';
		}
	}

	echo $display;

	return;
}

function sbocbr_ajax_return_error( $message ) {
	$result = array(
		'status'  => 0,
		'message' => $message
	);
	echo json_encode( $result );
	die;
}
