<div class="wrap">
	<h1><?php echo sbocbr_get_setting( 'name' ) ?></h1>

	<div class="sbocbr-notice">
		<?php sbocbr_show_admin_notices(); ?>
	</div>

	<div id="poststuff">
		<div class="metabox-holder">

			<!-- main content -->
			<div id="post-body-content">

				<div class="meta-box-sortables ui-sortable">

					<div class="postbox sbocbr-page sbocbr-page-status">
						<div class="inside">
							<section class="sbocbr-backup">
								<h3><span><?php _e( 'Backup database', 'sbocbr' ) ?></span></h3>

								<p class="submit-action">
									<input class="button-primary" type="submit" id="start-backup" name="start-backup" value="<?php _e( 'Backup Now!', 'sbocbr' ) ?>"/>
								</p>
								<div class="sbocbr-status">
								</div>
							</section>
							<hr>

							<section class="sbocbr-restore">
								<h3><span><?php _e( 'Current Database', 'sbocbr' ) ?></span></h3>
								<p><span class="current-time"></span></p>
								<p class="small-notice">
									* <?php _e( 'Please note that only restore the file which created by this backup functions. You can save the backup file by Right-click > Save link as..., and manually import it again to your database.', 'sbocbr' ) ?></p>
								<div class="bk-file-holder">
									<?php
									global $wpdb;
									$bk_file = sbocbr()->get_bk_file();

									if ( ! empty( $bk_file ) ) {
										?>
										<table class="file-list widefat">
											<thead>
											<tr>
												<th class="row-title"><?php _e( 'Database name', 'sbocbr' ) ?></th>
												<th><?php _e( 'Backup date', 'sbocbr' ) ?></th>
												<th><?php _e( 'File size', 'sbocbr' ) ?></th>
												<th><?php _e( 'Action', 'sbocbr' ) ?></th>
											</tr>
											</thead>
											<tbody>
											<tr class="file-row" data-file="<?php echo $bk_file->name ?>" valign="top">
												<td scope="row">
													<label for="tablecell"><a href="<?php echo $bk_file->url ?>"><?php echo $bk_file->name ?></a></label>
												</td>
												<td><?php echo date( 'Y-m-d H:i:s', $bk_file->modified ); ?></td>
												<td><?php echo sbocbr_format_filesize( $bk_file->size ) ?></td>
												<td class="submit-action">
													<input class="button-secondary restore-backup" type="submit" name="start-restore" value="<?php _e( 'Restore Now!', 'sbocbr' ) ?>"/>
												</td>
											</tr>
											</tbody>
										</table>
									<?php } else { ?>
										<p class="not-backup"><?php _e( "You don't have any backup database file!", "sbocbr" ) ?></p>
									<?php } ?>
									<div class="sbocbr-status">
									</div>
								</div>

							</section>

						</div> <!-- .inside -->

					</div> <!-- .postbox -->

				</div> <!-- .meta-box-sortables .ui-sortable -->
				

			</div> <!-- post-body-content -->

		</div> <!-- #post-body .metabox-holder .columns-2 -->
		<br class="clear">
	</div> <!-- #poststuff -->
</div>
<!-- .wrap -->

<script type="text/javascript">
	jQuery(document).ready(function ($) {
		var flag_backup = 1,
			flag_restore = 1;
		var $sbocbr_backup = $('.sbocbr-backup'),
			$sbocbr_restore = $('.sbocbr-restore');

		function sbocbr_add_notice(type, message, clear_all) {
			if (clear_all) {
				$('.sbocbr-notice').html('<div class="notice-item notice-item-' + type + '"><p>' + message + '</p></div>');
			} else {
				$('.sbocbr-notice').append('<div class="notice-item notice-item-' + type + '"><p>' + message + '</p></div>');
			}
		}

		function sbocbr_add_status($holder, message, clear_all) {
			if (clear_all) {
				$holder.find('.sbocbr-status').html('<p>' + message + '</p>');
			} else {
				$holder.find('.sbocbr-status').append('<p>' + message + '</p>');
			}
		}

		function sbocbr_zip_exported_file(processing_message) {

			$.ajax({
				type: "post",
				url: ajaxurl,
				dataType: 'json',

				data: ({
					action: "sbocbr_zip_exported_file"
				}),
				beforeSend: function () {
					sbocbr_add_status($sbocbr_backup, processing_message, false);
				},
				success: function (data) {
					if (data.status) {
						// zip complete - done
						sbocbr_add_status($sbocbr_backup, data.message, false);
					} else {
						// zip-fail - done
						sbocbr_add_status($sbocbr_backup, data.message, false);
					}
					$sbocbr_backup.find('.submit-action .sbocbr-icon').remove();
					flag_backup = 1;
				}
			});

		}

		function sbocbr_process_table(processing_message) {
			// var $li_current = $process_cont_list.find('li.upload-file');

			$.ajax({
				type: "post",
				url: ajaxurl,
				dataType: 'json',

				data: ({
					action: "sbocbr_process_table"
				}),
				beforeSend: function () {
					sbocbr_add_status($sbocbr_backup, processing_message, false);
				},
				success: function (data) {
					if (data.status) {
						// 1 table complete
						if (data.is_done) {
							// done all tables - call zip ajax
							sbocbr_zip_exported_file(data.message);
						} else {
							// next table
							sbocbr_process_table(data.message);
						}
					} else {
						// 1 table fail- done
						flag_backup = 1;
						$sbocbr_backup.find('.submit-action .sbocbr-icon').remove();
					}
				}
			});

		}

		$('#start-backup').click(function (e) {
			e.preventDefault();

			if (flag_backup) {
				flag_backup = 0;

				$.ajax({
					type: "POST",
					url: ajaxurl,
					dataType: 'json',
					data: {
						action: 'sbocbr_init_export'
					},
					beforeSend: function () {
						$sbocbr_backup.find('.submit-action .sbocbr-icon').remove();
						$sbocbr_backup.find('.submit-action').append(' <span class="sbocbr-icon spinner is-active"></span>');
						sbocbr_add_status($sbocbr_backup, '<?php _e( 'Starting export', 'sbocbr' ) ?>...', true);
					},
					success: function (data) {
						if (data.status) {
							sbocbr_process_table(data.message);
						} else {
							// setting error - done
							sbocbr_add_status($sbocbr_backup, data.message, false);
							$sbocbr_backup.find('.submit-action .sbocbr-icon').remove();
							flag_backup = 1;
						}
					}
				});
			} // flag backup
		});

		$(document).on('click', '.restore-backup', function (e) {
			e.preventDefault();
			if (flag_restore) {
				if (confirm('<?php _e( 'Do you want to restore this backup? Please make sure you was already downloaded this backup file to you computer before, in case this process cannot be done!', 'sbocbr' ); ?>')) {
					flag_restore = 0;

					var $row_container = $(this).closest('.file-row');

					$.ajax({
						type: "POST",
						url: ajaxurl,
						dataType: 'json',
						data: {
							action: 'sbocbr_restore_backup'
						},
						beforeSend: function () {
							$row_container.find('.submit-action .sbocbr-icon').remove();
							$row_container.find('.submit-action').append(' <span class="sbocbr-icon spinner is-active"></span>');
							sbocbr_add_status($sbocbr_restore, '<?php _e( 'Starting restore', 'sbocbr' ) ?>...', true);
						},
						success: function (data) {
							if (data.status) {
								// finish
								sbocbr_add_status($sbocbr_restore, data.message, false);
								$sbocbr_restore.find('.submit-action .sbocbr-icon').remove();
							} else {
								// setting error
								sbocbr_add_status($sbocbr_restore, data.message, false);
								$sbocbr_restore.find('.submit-action .sbocbr-icon').remove();
							}

							// done
							flag_restore = 1;
						}
					});
				}   // confirm
			} // flag restore
		});

	});
</script>