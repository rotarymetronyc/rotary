<?php

/*************************************************************************
 *   AJAX function to load a new announcement form when the committee changes.
************************************************************************/
add_action( 'wp_ajax_new_announcement', 'rotary_new_announcement' );
function rotary_new_announcement() {
	$post_id = $_REQUEST['post_id'];
	$args = array(
			'title_reply' => rotary_announcement_header( $post_id, $title, 'edit' ),
			'comment_notes_after'  => rotary_comment_notes_after(),
			'logged_in_as'  => '',
			'label_submit'  => __( 'Save Announcement' ),
			'id_form'       => 'ajax-announcement-form'
	);

	ob_start();
	?>
	    <div id="new-announcement-form">
			<?php echo comment_form( $args, $post_id );?>
		</div>
	<?php 
	$return =  ob_get_clean();
	echo $return; die;
}



/*************************************************************************
 *   AJAX function to EDIT announcement form
 ************************************************************************/ 
add_action( 'wp_ajax_edit_announcement', 'rotary_edit_announcement' );
function rotary_edit_announcement() {
	$comment_id = $_REQUEST['comment_id'];

	$announcement = get_comment( $comment_id );
	$title = get_comment_meta( $comment_id, 'announcement_title', true );
	$comment_post_ID = $announcement->comment_post_ID;

	$args = array(
			'title_reply' => rotary_announcement_header( $comment_post_ID, $title, 'edit' ),
			'comment_notes_after'  => rotary_comment_notes_after(),
			'logged_in_as'  => '',
			'label_submit'  => __( 'Save Announcement' ),
			'id_form'       => 'ajax-edit-announcement-form'
	);
	ob_start();
	?>
	    <div id="edit-announcement-<?php echo $comment_id; ?>-form">
			<?php echo comment_form( $args, $comment_post_ID ); ?>
		</div>
	<?php 
	$return =  ob_get_clean();
	echo $return; die;
}

/*************************************************************************
 *   AJAX function to DELETE announcement form
************************************************************************/
add_action( 'wp_ajax_delete_announcement', 'rotary_delete_announcement' );
function rotary_delete_announcement() {
	$comment_id = $_REQUEST['comment_id'];
	
	//check again if I can delete this comment
	$user_can_delete = ( current_user_can( 'delete_others_announcements' ) || get_current_user_id() == $announcement->user_id || current_user_can( 'manage_options' ));
	if( !$user_can_delete ) :
		$error = array( 'error' => 'You do not have permission to delete this announcment!' );
		echo json_encode( $error ); die;
	endif;

	if (wp_delete_comment( $comment_id ) ) :
		echo json_encode( array( 'success' => 'Comment ' . $_REQUEST['comment_id'] . ' deleted' )); die;
	else:
		echo json_encode( array( 'error' => 'Comment ' . $_REQUEST['comment_id'] . ' could not be deleted' )); die;
	endif;
}


/*************************************************************************
 *   Save Custom Announcement Metadata when the comment is saved
 *   
************************************************************************/
add_action( 'comment_post', 'rotary_save_announcement_meta', 10, 1 ); // Triggered during the normal WP Save Comment process
add_action( 'comment_edit', 'rotary_save_announcement_meta', 10, 1 ); // Triggered during the callback from the AJAX form

function rotary_save_announcement_meta( $comment_id ) {

	// title
	$announcement_title = sanitize_text_field( $_REQUEST['announcement_title'] );
	update_comment_meta( $comment_id, 'announcement_title', $announcement_title );

	// Expiry date
	$announcement_expiry_date = ( sanitize_text_field( $_REQUEST['announcement_expiry_date'] ) );
	if( !$announcement_expiry_date ) :
		$post_id = get_comment_meta( $comment_id, 'comment_post_ID', true );
		if ( 'rotary_projects' == get_post_type( $post_id ) && get_field( 'long_term_project', $post_id ) ) :
			$expiry_date = date_create_from_format ( 'Ymd', get_field( 'rotary_project_end_date', $post_id ));
		endif;
		if ( !is_object( $expiry_date )) :
			$expiry_date = new DateTime;
			$expiry_date->add(new DateInterval( 'P7D' ) ) ;
		endif;
		$announcement_expiry_date = $expiry_date->format( 'Y-m-d' );
	endif;
	
	update_comment_meta( $comment_id, 'announcement_expiry_date', $announcement_expiry_date );

	//Request replies
	$request_replies_input = $_REQUEST['request_replies_input'];
	update_comment_meta( $comment_id, 'request_replies', $request_replies_input );

	//Announcer
	$user_ID = $_REQUEST['announcer'] ;
	$user = get_userdata( $user_ID );
	if ( $user->exists() ) {
		$comment_author       = wp_slash( $user->display_name );
		$comment_author_email = wp_slash( $user->user_email );
		$comment_author_url   = wp_slash( $user->user_url );
	} else {
		if ( get_option( 'comment_registration' ) || 'private' == $status ) {
			wp_die( __( 'Sorry, you must be logged in to post a comment.' ), 403 );
		}
	}

	$comment_ID = $comment_id;
	$commentarr = compact( 'comment_ID', 'comment_author', 'comment_author_email', 'comment_author_url', 'user_ID');
	wp_update_comment( $commentarr );
	
	//Permissions
	$permissions = $_REQUEST['permissions'];
	update_comment_meta( $comment_id, 'permissions', $permissions );

	// Call to action URL
	if( $_REQUEST['call_to_action_input'] ) :
		$link_text = sanitize_text_field( $_REQUEST['call_to_action_text_input'] );
		if( 1 == $_REQUEST['call_to_action_link'] ) : //link to this post
			$link_text = ( $link_text ) ? $link_text : __( 'Go To Post' );
			$announcement = get_comment( $comment_id );
			$link_url = get_permalink( $announcement->comment_post_ID );
			update_comment_meta( $comment_id, 'link_url', '' ); //delete any URL there is
		else:
			$link_text = ( $link_text ) ? $link_text : __( 'Go To Website' );
			$link_url = esc_url( $_REQUEST['other_link_text_input'] );
			update_comment_meta( $comment_id, 'link_url', $link_url );
			$call_to_action_link = 2;
		endif;
		if( $link_url ) :
			$call_to_action = '<a href="' . $link_url . '" class="rotarybutton-smallgold">' . $link_text . '</a>';
			update_comment_meta( $comment_id, 'call_to_action', $call_to_action );
		endif;
		update_comment_meta( $comment_id, 'link_text', $link_text );
		update_comment_meta( $comment_id, 'call_to_action_link', $_REQUEST['call_to_action_link'] );
	else:
		delete_comment_meta( $comment_id, 'call_to_action' );
		delete_comment_meta( $comment_id, 'link_text' );
		delete_comment_meta( $comment_id, 'call_to_action_link' );
		delete_comment_meta( $comment_id, 'link_url' ); //delete any URL there is
	endif;
}



/*************************************************************************
 *   Adding custom fields to the TOP of the Comment Form
 *   Included during Comment_Form generation
************************************************************************/
add_action( 'comment_form_logged_in_after', 'additional_comment_fields_before' );
function additional_comment_fields_before( $fields ) {
	if( $_REQUEST['comment_id'] ) : // we are editing a comment
		$comment_id = (int) $_REQUEST['comment_id'];
		$announcement = get_comment( $comment_id );
		$announcer = $announcement->user_id;
		$request_replies_checked = get_comment_meta( $comment_id, 'request_replies', true );
		$title = get_comment_meta( $comment_id, 'announcement_title', true );
		$permissions = get_comment_meta( $comment_id, 'permissions', true );
	endif;

	echo '<fieldset>';
	echo '<div class="announcementtitlecontainer">
 			<input id="announcement_title_input" name="announcement_title" type="text" size="60" placeholder="'. __( 'Announcement Title', 'Rotary' ) . '" tabindex="1" value="' . htmlentities( $title ) . '"/>
		 </div>';
	echo '<div class="announcercontainer">
 			<label for="announcer">' . __( 'Announced by', 'Rotary'  ) . '</label>
			<select id="announcer" name="announcer">'. get_users_select( $announcer ).'</select>
		</div>';
	echo '<div class="requestrepliescontainer">
			<input id="request_replies_input" name="request_replies_input" type="checkbox"' . ( 'on' == $request_replies_checked ? 'checked' : '' ) . '/>
 			<label for="request_replies_input">' . __( 'Request Replies (Adds a BLUE button to email the announcer)', 'Rotary'  ) . '</label>
		</div>';
	echo '<div class="permissionscontainer">
 			<span class="permissions_label">' . __( 'Visibility:&nbsp;&nbsp;', 'Rotary'  ) . '</span>
			<input type="radio" name="permissions" value="0" ' . ( !$permissions ? 'checked' : '' ) . '><span class="permissions_label both">' . __( 'Both', 'Rotary'  ) . '</span>
			<input type="radio" name="permissions" value="1" ' . ( 1 == $permissions ? 'checked' : '' ) . '><span class="permissions_label members">' . __( 'Members Only', 'Rotary'  ) . '</span>
			<input type="radio" name="permissions" value="2" ' . ( 2 == $permissions ? 'checked' : '' ) . '><span class="permissions_label nonmembers">' . __( 'Non-Members Only', 'Rotary' ) . '</span>
		</div>';
	echo '</fieldset>';
}



/*************************************************************************
 *   Add custom fields to the BOTTOM of the Comment Form
 *   This is included as one of the $commentarr to the Comment_Form function
************************************************************************/
function rotary_comment_notes_after ( ) {
	// if this from an AJAX call, I set the redirect_to field so that when the form is saved, it gets redirected back to the calling page
	if( $_REQUEST['redirect_to'] ) :
		$redirect = '<input type="hidden" name="redirect_to" value="' . trim(esc_url( $_REQUEST['redirect_to'] )) . '" />';
	endif;
	
	// Fetch the current values  if we are editing a comment
	if( $_REQUEST['comment_id'] ) :
		$comment_id = (int) $_REQUEST['comment_id'];
		$comment_id_input_field = '<input type="hidden" name="comment_ID" value="' . $comment_id . '" />';
		$link_text = get_comment_meta( $comment_id, 'link_text', true );
		$call_to_action_link = get_comment_meta( $comment_id, 'call_to_action_link', true );
		$link_url = get_comment_meta( $comment_id, 'link_url', true );
	
		$announcement_expiry_date = get_comment_meta( $comment_id, 'announcement_expiry_date', true );
		if( $announcement_expiry_date ) :
			$expiry_date = new DateTime ( $announcement_expiry_date );
			$announcement_expiry_date_input = $expiry_date->format( 'm/d/Y' );
			$announcement_expiry_date_alt = $expiry_date->format( 'Y-m-d' );
		endif;
	endif;

	$fields = 	'<fieldset class="announcement-expiry-date">
 					<label for="announcement_expiry_date_input">' . __( 'Announcement Expires on' ) . '</label>
 					<input id="announcement_expiry_date_input" type="text" size="10"  tabindex="3" value="' . $announcement_expiry_date_input . '"/>
 					<input id="announcement_expiry_date" name="announcement_expiry_date" type="hidden"  value="' . $announcement_expiry_date_alt . '" />
				</fieldset>';
	$fields .=  '<fieldset class="call-to-action">
					<input id="call_to_action" name="call_to_action_input" type="checkbox" ' . ( $call_to_action_link ? 'checked' : '' ) . '/>
 					<label for="call_to_action">' . __( 'Additional Call to Action (adds a YELLOW button to go to another page/website)' ) . '</label>
					<div id="call_to_action_links" ' . ( $call_to_action_link ? '' : 'style="display:none"') . ' >
	 					<label for="call_to_action_text">' . __( 'Button Text' ) . '</label>
						<input id="call_to_action_text" name="call_to_action_text_input" type="text" value="' . $link_text . '"/>					
							<div class="call-to-action-radio-container">
							<input id="call_to_action_link_1" type="radio" name="call_to_action_link" value="1" ' . ( 2 != $call_to_action_link  ? 'checked' : '' ) . '>
	 						<label for="call_to_action_link_1">' . __( 'Button links to this project/committee\'s page' ) . '</label>
							<br><input id="call_to_action_link_2" type="radio" name="call_to_action_link" value="2" ' . ( 2 == $call_to_action_link ? 'checked' : '' ) . '>
	 						<label for="call_to_action_link_2">' . __( 'Button links to another Website/URL...' ) . '</label>
							<input id="other_link_text" name="other_link_text_input" type="text" value="' . $link_url . '" placeholder="http://" style="display:'. ( 2 == $call_to_action_link ? 'inline-block' : 'none' ) . '" />
						</div>
					</div>
				</fieldset>';
	$fields .=  $redirect . $comment_id_input_field;
		
	return $fields;
}


/********************************************
*  This is a helper function to get a list of users 
*  for the comment form 'announced by"
*/
function get_users_select( $announcer ) {
	$args = array(
			'orderby' => 'meta_value',
			'meta_key' => 'first_name'
	);
	$users = get_users( $args );
	$user_id = ( !$announcer ? get_current_user_id() : $announcer) ;
	foreach ($users as $user) {
		$usermeta = get_user_meta($user->ID);
		if ( !isset($usermeta['membersince'][0]) || '' == trim($usermeta['membersince'][0])) {
			continue;
		}
		$memberName = $usermeta['first_name'][0]. ' ' .$usermeta['last_name'][0];
		$options .= '<option value="'.$user->ID.'"' . (( $user_id ==  $user->ID ) ? 'selected=selected' : '' ) . '>' . $memberName.'</option>';
	}

	return $options;
}

/********************************************
*  Copied from the plugin MDC Comments
*  adds tinyMCE to the comment field both on a page/post, and on AJAX
*  It completely replaces (via a filter) the comment_field on the 
*  Comment_Form.
*  It appears to run on every page? 
*/

// 
add_filter( 'comment_form_field_comment', 'rotary_comment_toolbar' );
add_action( 'wp_enqueue_scripts', 'rotary_comment_toolbar' );
function rotary_comment_toolbar() {
	global $post;

	if( $_REQUEST['comment_id'] ) {
		$comment_id = (int) $_REQUEST['comment_id'];
		$announcement =  get_comment( $comment_id );
		$content = $announcement->comment_content;
	}
	//this is a hack - these styles sometimes don't load :(
	wp_register_style( 'editor_min', site_url('/wp-includes/css/editor.min.css' ));
	wp_register_style( 'dashicons_min', site_url('/wp-includes/css/dashicons.min.css' ));
	wp_enqueue_style( 'editor_min');
	wp_enqueue_style( 'dashicons_min');
	ob_start();
	
	wp_editor(
		$content,
		'comment',
			array(
				'textarea_rows' => 10,
				'teeny' => true,	//hide some icons
				'quicktags' => false,	//enable html toolbar
				'media_buttons' => true
			)
	);
	$toolbar = ob_get_contents();
	ob_end_clean();
	// make sure comment media is attached to parent post
	$toolbar = str_replace( 'post_id=0', 'post_id='.get_the_ID(), $toolbar );
	return $toolbar;
}

/***********************************
 * Announcement header
 */
function rotary_announcement_header( $posted_in_id, $announcement_title = null, $context='web' ) {
	global $ProjectType;

	$posted_in = '<a href="' . get_the_permalink( $posted_in_id ) . '">' . get_the_title( $posted_in_id ) .'</a>';
	$post_type = get_post_type( $posted_in_id );
	$thumbnailsize = ( 'slideshow' == $context  ) ? array( 320,220 ) :  array(160,110) ;
	$thumbnail = get_the_post_thumbnail ( $posted_in_id ,$thumbnailsize  );
	
	// where did this announcement come from - a project, or a committee??
	// If a project, we need to fetch its committee information, if it is associated
	switch ( $post_type ) {
		case 'rotary_projects':
			$type = get_field( 'project_type',  $posted_in_id  );
			$extra_classes = '';
			$committee_title = rotary_get_committee_title_from_project( $posted_in_id, $extra_classes );
			break;
		case 'rotary_committee':
			break;
	}

	ob_start();
	
	switch( $context ) {
		case 'web':
			?>
			<div class="announcement-header">
				<?php if ( $thumbnail && 1 == 2) {  // turn off thumbnail
					$hasthumbnail = "has-thumbnail";?>
					<div class="header-thumbnail-container"><?php echo $thumbnail; ?></div><?php 
				}?>
					<div class="header-text-container <?php echo  $hasthumbnail; ?>">
							<h5 class="inline"><?php echo $posted_in; ?></h5>
							<?php if( $announcement_title ) {?><h1><?php echo $announcement_title; ?></h1><?php }?>
							<?php if ( 'rotary_projects' == $post_type ) :?>
								<span class="project-type"><?php echo $ProjectType[$type] . '&nbsp;&nbsp;&#8226;&nbsp;&nbsp;'; ?></span>
								<span class="organizing-committee"><?php echo sprintf( __( 'Organized by %s' ), $committee_title); ?></span>
							<?php endif;?>
					</div>
			</div>	
		<?php 	
			break;
		case 'slideshow':
			?>
					<div class="announcement-header">
						<?php if ( $thumbnail ) { 
							$hasthumbnail = "has-thumbnail";?>
							<div class="header-thumbnail-container"><?php echo $thumbnail; ?></div><?php 
						}?>
							<div class="header-text-container <?php echo  $hasthumbnail; ?>">
								<?php if( $announcement_title ) :?>
									<h5 class="inline"><?php echo $posted_in; ?></h5>
									<?php if ( 'rotary_projects' == $post_type ) :?>
										<span class="project-type"><?php echo $ProjectType[$type] . '&nbsp;&nbsp;&#8226;&nbsp;&nbsp;'; ?></span>
										<span class="organizing-committee"><?php echo sprintf( __( 'Organized by %s' ), $committee_title); ?></span>
									<?php endif;?>
									<h1><?php echo $announcement_title; ?></h1>
								<?php else:?>
									<h5 class="inline"><?php echo $posted_in; ?></h5>
									<?php if ( 'rotary_projects' == $post_type ) :?>
										<span class="project-type"><?php echo $ProjectType[$type] . '&nbsp;&nbsp;&#8226;&nbsp;&nbsp;'; ?></span>
										<span class="organizing-committee"><?php echo sprintf( __( 'Organized by %s' ), $committee_title); ?></span>
									<?php endif;?>
								<?php endif;?>
							</div>
					</div>	
				<?php 	
					break;
					
		case 'edit':
			?>
			<div class="announcement-header">
					<div class="header-text-container <?php echo  $hasthumbnail; ?>">
							<h5 class="inline"><?php echo $posted_in; ?></h5>
							<?php if ( 'rotary_projects' == $post_type ) :?>
								<span class="project-type"><?php echo $ProjectType[$type] . '&nbsp;&nbsp;&#8226;&nbsp;&nbsp;'; ?></span>
								<span class="organizing-committee"><?php echo sprintf( __( 'Organized by %s' ), $committee_title); ?></span>
							<?php endif;?>
					</div>
			</div>
			<div class="clearleft"></div>	
		<?php 	
			break;
		case 'email':
			?>
			<table class="announcement-header-table">
				<tr class="announcement-header">
					<td class="header-text-container ">
							<h5 class="inline"><?php echo $posted_in; ?></h5>
							<?php if( $announcement_title ) {?><h1><?php echo $announcement_title; ?></h1><?php }?>
							<?php if ( 'rotary_projects' == $post_type ) :?>
								<span class="project-type"><?php echo $ProjectType[$type] . '&nbsp;&nbsp;&#8226;&nbsp;&nbsp;'; ?></span>
								<span class="organizing-committee"><?php echo sprintf( __( 'Organized by %s' ), $committee_title); ?></span>
							<?php endif;?>
					</td>
				</tr>
			</table>	
			<?php 	
		break;
	}
	$return = ob_get_contents();
	ob_end_clean();
	return $return;
}

