<?php
global $action;
$post_type = $post->post_type;
$post_type_object = get_post_type_object($post_type);
$can_publish = current_user_can($post_type_object->cap->publish_posts);
?>

<div class="submitbox" id="submitpost">
	<div id="minor-publishing">

			<?php // Hidden submit button early on so that the browser chooses the right button when form is submitted with Return key ?>
			<div style="display:none;">
				<?php submit_button( __( 'Save' ), 'button', 'save' ); ?>
			</div>

			<div id="minor-publishing-actions">
				<div id="save-action">
					<?php if ( 'publish' != $post->post_status && 'future' != $post->post_status && 'pending' != $post->post_status ) { ?>
					<input <?php if ( 'private' == $post->post_status ) { ?>style="display:none"<?php } ?> type="submit" name="save" id="save-post" value="<?php esc_attr_e('Save Draft'); ?>" class="button" />
					<?php } elseif ( 'pending' == $post->post_status && $can_publish ) { ?>
					<input type="submit" name="save" id="save-post" value="<?php esc_attr_e('Save as Pending'); ?>" class="button" />
					<?php } ?>
					<span class="spinner"></span>
				</div>

				<div class="clear"></div>
			</div><!-- #minor-publishing-actions -->

			<div id="misc-publishing-actions">

				<div class="misc-pub-section">
					<label for="post_status"><?php _e('Status:') ?></label>
					<span id="post-status-display">
					<?php
					switch ( $post->post_status ) {
						case 'private':
							_e('Privately Published');
							break;
						case 'publish':
							_e('Published');
							break;
						case 'future':
							_e('Scheduled');
							break;
						case 'pending':
							_e('Pending Review');
							break;
						case 'draft':
						case 'auto-draft':
							_e('Draft');
							break;
					}
					?>
					</span>
					<?php if ( 'publish' == $post->post_status || 'private' == $post->post_status || $can_publish ) { ?>
						<a href="#post_status" <?php if ( 'private' == $post->post_status ) { ?>style="display:none;" <?php } ?>class="edit-post-status hide-if-no-js"><?php _e('Edit') ?></a>

						<div id="post-status-select" class="hide-if-js">
							<input type="hidden" name="hidden_post_status" id="hidden_post_status" value="<?php echo esc_attr( ('auto-draft' == $post->post_status ) ? 'draft' : $post->post_status); ?>" />
							<select name='post_status' id='post_status'>
								<?php if ( 'publish' == $post->post_status ) : ?>
								<option<?php selected( $post->post_status, 'publish' ); ?> value='publish'><?php _e('Published') ?></option>
								<?php elseif ( 'private' == $post->post_status ) : ?>
								<option<?php selected( $post->post_status, 'private' ); ?> value='publish'><?php _e('Privately Published') ?></option>
								<?php elseif ( 'future' == $post->post_status ) : ?>
								<option<?php selected( $post->post_status, 'future' ); ?> value='future'><?php _e('Scheduled') ?></option>
								<?php endif; ?>
								<option<?php selected( $post->post_status, 'pending' ); ?> value='pending'><?php _e('Pending Review') ?></option>
								<?php if ( 'auto-draft' == $post->post_status ) : ?>
								<option<?php selected( $post->post_status, 'auto-draft' ); ?> value='draft'><?php _e('Draft') ?></option>
								<?php else : ?>
								<option<?php selected( $post->post_status, 'draft' ); ?> value='draft'><?php _e('Draft') ?></option>
								<?php endif; ?>
							</select>
						 	<a href="#post_status" class="save-post-status hide-if-no-js button"><?php _e('OK'); ?></a>
						 	<a href="#post_status" class="cancel-post-status hide-if-no-js"><?php _e('Cancel'); ?></a>
						</div>
					<?php } ?>
				</div><!-- .misc-pub-section -->

				<?php
				// translators: Publish box date format, see http://php.net/date
				$datef = __( 'M j, Y @ G:i' );
				if ( 0 != $post->ID ) {
					if ( 'future' == $post->post_status ) { // scheduled for publishing at a future date
						$stamp = __('Reported in the future: <b>%1$s</b>');
					} else if ( 'publish' == $post->post_status || 'private' == $post->post_status ) { // already published
						$stamp = __('Reported on: <b>%1$s</b>');
					} else if ( '0000-00-00 00:00:00' == $post->post_date_gmt ) { // draft, 1 or more saves, no date specified
						$stamp = __('Report <b>immediately</b>');
					} else if ( time() < strtotime( $post->post_date_gmt . ' +0000' ) ) { // draft, 1 or more saves, future date specified
						$stamp = __('Report in the future: <b>%1$s</b>');
					} else { // draft, 1 or more saves, date specified
						$stamp = __('Reported on: <b>%1$s</b>');
					}
					$date = date_i18n( $datef, strtotime( $post->post_date ) );
				} else { // draft (no saves, and thus no date specified)
					$stamp = __('Report <b>immediately</b>');
					$date = date_i18n( $datef, strtotime( current_time('mysql') ) );
				}

				if ( $can_publish ) : // Contributors don't get to choose the date of publish ?>
					<div class="misc-pub-section curtime">
						<span id="timestamp">
						<?php printf($stamp, $date); ?></span>
						<a href="#edit_timestamp" class="edit-timestamp hide-if-no-js"><?php _e('Edit') ?></a>
						<div id="timestampdiv" class="hide-if-js"><?php touch_time(($action == 'edit'), 1); ?></div>
					</div><?php // /misc-pub-section ?>
				<?php endif; ?>

				<?php do_action('post_submitbox_misc_actions'); ?>
			</div>
			<div class="clear"></div>
		</div>

		<div id="major-publishing-actions">
			<?php do_action('post_submitbox_start'); ?>
			<div id="delete-action">
			<?php
			if ( current_user_can( "delete_post", $post->ID ) ) {
				if ( !EMPTY_TRASH_DAYS )
					$delete_text = __('Delete Permanently');
				else
					$delete_text = __('Move to Trash');
				?>
			<a class="submitdelete deletion" href="<?php echo get_delete_post_link($post->ID); ?>"><?php echo $delete_text; ?></a><?php
			} ?>
		</div>

		<div id="publishing-action">
			<span class="spinner"></span>
			<?php
			if ( !in_array( $post->post_status, array('publish', 'future', 'private') ) || 0 == $post->ID ) {
				if ( $can_publish ) :
					if ( !empty($post->post_date_gmt) && time() < strtotime( $post->post_date_gmt . ' +0000' ) ) : ?>
					<input name="original_publish" type="hidden" id="original_publish" value="<?php esc_attr_e('Schedule') ?>" />
					<?php submit_button( __( 'Schedule' ), 'primary button-large', 'publish', false, array( 'accesskey' => 'p' ) ); ?>
			<?php	else : ?>
					<?php if ( 'pending' == $post->post_status ) : ?>
						<input name="original_publish" type="hidden" id="original_publish" value="<?php esc_attr_e('Approve') ?>" />
						<?php submit_button( __( 'Approve' ), 'primary button-large', 'publish', false, array( 'accesskey' => 'p' ) ); ?>
					<?php else : ?>
						<input name="original_publish" type="hidden" id="original_publish" value="<?php esc_attr_e('Publish') ?>" />
						<?php submit_button( __( 'Publish' ), 'primary button-large', 'publish', false, array( 'accesskey' => 'p' ) ); ?>
					<?php endif; ?>
			<?php	endif;
				else : ?>
					<input name="original_publish" type="hidden" id="original_publish" value="<?php esc_attr_e('Submit for Review') ?>" />
					<?php submit_button( __( 'Submit for Review' ), 'primary button-large', 'publish', false, array( 'accesskey' => 'p' ) ); ?>
			<?php
				endif;
			} else { ?>
					<input name="original_publish" type="hidden" id="original_publish" value="<?php esc_attr_e('Update') ?>" />
					<input name="save" type="submit" class="button-primary button-large" id="publish" accesskey="p" value="<?php esc_attr_e('Update') ?>" />
			<?php
			} ?>
		</div>
		<div class="clear"></div>

	</div>
</div>