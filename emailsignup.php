<?php
/*
Plugin Name: Email Signup
Version: 1.0
Plugin URI: http://www.torch.agency
Author: Travis Freeman
Author URI: http://www.torch.agency
Description: Capture email addresses internally as well as pushing that data to your NationBuilder account. Please note, this plugin is based around the Canadian postal code system.
*/
?>
<?php
defined( 'ABSPATH' ) or die( '!This page can not be loaded outside of WordPress' );

global $wp_version;
if ( version_compare( $wp_version, '4.1', '<' ) )
{
	exit( 'Email Signup requires WordPress 4.1 OR newer. <a href="https://codex.wordpress.org/Upgrading_WordPress" target="_blank">Please update!</a>' );
}
if( !class_exists( 'EMAILSIGNUP' ) ):
class EMAILSIGNUP
{
	var $DIR;
	var $URI;
	var $text_domain;
	var $title;
	var $permissions;
	var $slug;
	var $page;
	var $icon;
	var $WPDB_EMAILSIGNUP;
	var $force_update;
	var $msg;

	function __construct(  )
	{
		global $wpdb;
		$this->DIR				= plugin_dir_path( __FILE__ );
		$this->URI				= plugin_dir_url( __FILE__ );
		$this->text_domain		= 'emailsignup';
		$this->title			= __( 'Email Signup', $this->text_domain );
		$this->permissions		= 'manage_options';
		$this->slug				= 'emailsignup';
		$this->icon				= 'emailsignup.png';
		$this->WPDB_EMAILSIGNUP	= $wpdb->prefix . 'emailsignup';
		return;
	}
	
	private function scrub( $post )
	{
		$post = strip_tags( $post );
		$post = stripslashes( $post );
		$post = trim( $post );
		return $post;
	}
	
	public function init( )
	{
		$this->process_form( );
	}
	
	/* BACK-END */
	
	public function admin_init( )
	{
		$this->get_emailsignup_download( );
	}
	
	private function get_emailsignup_download( )
	{
		if ( wp_verify_nonce( $_POST['emailsignup_download_nonce_field'], 'emailsignup_download_action' )  )
		{
			global $wpdb;
			$emails = $wpdb->get_results( "SELECT * FROM `$this->WPDB_EMAILSIGNUP` ORDER BY `timestamp` DESC;" );
			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename=EMAILSIGNUP.csv' );
			$output = fopen( 'php://output', 'w' );
			fputcsv( $output, array( 'Email Address', 'Postal Code', 'Riding', 'Date of Sign Up' ) );
			if ( $emails )
			{
				foreach( $emails as $email ):
					fputcsv( $output, array( $email->email, $email->postalcode, $email->riding, $email->timestamp ) );
				endforeach;
			}
			fclose( $output );
			exit( );
		}
	}
	
	public function initial_install( )
	{
		$this->create_database_table( );
		$this->create_cron_job( );
		return NULL;
	}
	
	private function create_database_table( )
	{
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate( );
		$sql = "CREATE TABLE `$this->WPDB_EMAILSIGNUP` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`email` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
			`email_consent` int(11) NOT NULL DEFAULT '0',
			`postalcode` varchar(6) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
			`riding` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
			`nationbuilder_id` int(11) NOT NULL DEFAULT '0',
			`nationbuilder_updated` datetime DEFAULT NULL,
			`post_id` int(11) NOT NULL DEFAULT '0',
			`timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
			UNIQUE KEY id (id)
		) $charset_collate;";
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );	
	}
	
	private function create_cron_job( )
	{
		if( !wp_next_scheduled( 'emailsignup_updates' ) )
		{
			wp_schedule_event( time( ), 'hourly', 'emailsignup_updates' );
		}
	}
	
	public function uninstall( )
	{
		wp_clear_scheduled_hook( 'emailsignup_updates' );
	}
	
	public function setup_menu_page( )
	{
		add_action( 'admin_menu', array( $this,'setup_panels' ) );
	}
	
	public function setup_panels( )
	{
		$this->page = add_menu_page( $this->title, $this->title, $this->permissions, $this->slug, array( &$this, 'setup_page' ), $this->URI . $this->icon );
		add_action( 'admin_print_scripts-' . $this->page, array( &$this, 'header_scripts' ) );
		add_action( 'load-' . $this->page,  array( &$this, 'page_actions' ), 9 );
		add_action( 'admin_footer-' . $this->page, array( &$this, 'footer_scripts' ) );
		add_meta_box( 'panel_admin_settings', __( 'Form Settings', $this->text_domain ), array( &$this, 'panel_admin_settings' ), @$this->page, 'normal', 'high' );
		add_meta_box( 'panel_shortcode_info', __( 'Shortcode Info', $this->text_domain ), array( &$this, 'panel_shortcode_info' ), @$this->page, 'normal', 'high' );
		add_meta_box( 'panel_remove_email', __( 'Remove Email', $this->text_domain ), array( &$this, 'panel_remove_email' ), @$this->page, 'side', 'high' );
		add_meta_box( 'panel_email_download', __( 'Download Emails', $this->text_domain ), array( &$this, 'panel_email_download' ), @$this->page, 'side', 'high' );
	}
	
	public function panel_admin_settings( )
	{
		if ( wp_verify_nonce( $_POST['emailsignup_nonce_field'], 'emailsignup_action' )  )
		{
			$nationbuilder_slug			= @$this->scrub( $_POST['emailsignup_nationbuilder_slug'] );
			$nationbuilder_api_token	= @$this->scrub( $_POST['emailsignup_nationbuilder_api_token'] );
			update_option( $this->slug, array(
				'nationbuilder_slug'		=> $nationbuilder_slug,
				'nationbuilder_api_token'	=> $nationbuilder_api_token,
			) );
			$msg = '<div id="message" class="updated fade"><p>' . __( 'Your settings have been updated.', $this->text_domain ) . '</p></div>';
		}
		$settings = get_option( $this->slug );
		echo $msg;
		?>
        <form  action="" method="post">  
        <?php wp_nonce_field( 'emailsignup_action', 'emailsignup_nonce_field' ); ?>
        <div class="emailsignup_panel">
            <p class="blurb"><?php _e( 'Should you want to leverage the ability to push your email signups to your NationBuilder account, you must enter the slug of your Nation.  Just the slug portion of the URL.  Lastly, you must obtain a valid API Token to your Nation and enter it below.', $this->text_domain ); ?></p>
            <div class="section">
                <div class="header"><?php _e( 'NationBuilder Slug', $this->text_domain ); ?></div>
                <div>
                    <input type="text" name="emailsignup_nationbuilder_slug" value="<?php echo $settings['nationbuilder_slug']; ?>" placeholder="<?php _e( 'http://[slug].nationbuilder.com', $this->text_domain ); ?>" class="widefat" maxlength="100" />
                </div>
            </div>
            <div class="section">
                <div class="header"><?php _e( 'NationBuilder API Token', $this->text_domain ); ?></div>
                <div>
                    <input type="password" name="emailsignup_nationbuilder_api_token" value="<?php echo $settings['nationbuilder_api_token']; ?>" placeholder="<?php _e( 'NationBuilder API Token', $this->text_domain ); ?>" class="widefat" maxlength="100" />
                </div>
            </div>
            <div><input type="submit" name="Submit" value="<?php _e( 'Update', $this->text_domain ); ?>" class="button-primary" /></div>
        </div>
        </form>
		<?php
	}
	
	public function panel_shortcode_info( )
	{
		?>
        <div class="emailsignup_panel">
            <p class="blurb"><?php _e( 'You can leverage the shortcode [EMAILSIGNUP] in your POST(s) / PAGE(s).  The shortcode comes with a few attributes detailed below.', $this->text_domain ); ?></p>
            <div class="section">
            	<div class="header"><?php _e( 'Example', $this->text_domain ); ?></div>
               	<div>
            		<input type="text" value="[EMAILSIGNUP]" class="widefat" />
                </div>
           	</div>
            <div class="section">
                <p><?php _e( "To remove the postal code field set the 'postalcode' attribute to 'false'. Default is set to 'true'.", $this->text_domain ); ?></p>
                <div class="header"><?php _e( 'Example', $this->text_domain ); ?></div>
               	<div>
            		<input type="text" value="[EMAILSIGNUP postalcode='false']" class="widefat" />
                </div>
                <p><?php _e( "When a postal code is submited, and the 'postalcode' attribute is set to 'true', there is an attempt to attain the riding number based on the postal code. If you want to turn that off then set the 'riding_lookup' attribute to 'false'. Default is set to 'true'.", $this->text_domain ); ?></p>
                <div class="header"><?php _e( 'Example', $this->text_domain ); ?></div>
               	<div>
            		<input type="text" value="[EMAILSIGNUP riding_lookup='false']" class="widefat" />
                </div>
            </div>
        </div>
		<?php
	}
	
	private function remove_email( )
	{
		global $wpdb;
		$id						= $this->scrub( $_POST['emailsignup_id'] );
		$remove_signup			= true;
		$remove_nationbuilder	= ( $_POST['emailsignup_nationbuilder'] ) ? true : false;
		$signup					= $wpdb->get_row( "SELECT * FROM `$this->WPDB_EMAILSIGNUP` WHERE `id` = {$id};" );
		if ( !$signup )
		{
			$this->msg = __( 'That email does not exist in the Email Signups.', $this->text_domain );
			return false;	
		}
		$settings					= get_option( $this->slug );
		$nationbuilder_slug			= $settings['nationbuilder_slug'];
		$nationbuilder_api_token	= $settings['nationbuilder_api_token'];
		$email						= $signup->email;
		$nationbuilder_id			= $signup->nationbuilder_id;
		if ( $remove_nationbuilder )
		{
			$remove_signup = false;
			if ( !$nationbuilder_slug || !$nationbuilder_api_token )
			{
				$this->msg = __( 'The NationBuilder Slug and API Token are not set. Please do so before attempting to remove from your NationBuilder account.', $this->text_domain );
				return false;
			}
			$api_url	= 'https://' . $nationbuilder_slug . '.nationbuilder.com';
			$endpoint	= $api_url . '/api/v1/people/' . $nationbuilder_id . '/?access_token=' . $nationbuilder_api_token;
			$args = array(
				'method' => 'DELETE',
			);
			$response = wp_remote_request( $endpoint, $args );
			if ( is_wp_error( $response ) )
			{
				$this->msg = __( 'A technical issue occured while attempting to remove the email from NationBuilder. Please try again.', $this->text_domain );
				return false;
			}
			if ( is_array( $response ) )
			{
				if ( 204 == $response['response']['code'] )
				{
					$remove_signup 			= true;
					$remove_nationbuilder	= true;
				}
			}
		}
		if ( !$remove_signup )
		{
			$this->msg = __( 'That email could not be removed from the Email Signups.', $this->text_domain );
			return false;
		}
		$delete = $wpdb->delete( $this->WPDB_EMAILSIGNUP, array( 'id' => $id ), array( '%d' ) );
		if ( !$delete )
		{
			$this->msg = __( 'That email was not removed from the Email Signups.', $this->text_domain );
			return false;
		}
		if ( $remove_nationbuilder )
		{
			$this->msg = sprintf( __( 'The email %s has been removed from your Email Signups as well as from your [%s] Nation.', $this->text_domain ), $email, $nationbuilder_slug );
		}
		else
		{
			$this->msg = sprintf( __( 'The email %s has been removed from your Email Signups.', $this->text_domain ), $email );
		}
		return true;
	}
	
	public function panel_remove_email( )
	{
		global $wpdb;
		$settings					= get_option( $this->slug );
		$nationbuilder_slug			= $settings['nationbuilder_slug'];
		$nationbuilder_api_token	= $settings['nationbuilder_api_token'];
		if ( wp_verify_nonce( $_POST['emailsignup_remove_nonce_field'], 'emailsignup_remove_action' )  )
		{
			$remove_email = $this->remove_email( );
			if ( $remove_email )
			{
				$msg = '<div id="message" class="updated fade"><p>' . $this->msg . '</div>';
			}
			else
			{
				$msg = '<div id="message" class="error fade"><p>' . $this->msg . '</div>';
			}
		}
		$signups = $wpdb->get_results( "SELECT * FROM `$this->WPDB_EMAILSIGNUP` ORDER BY `email` ASC;" );
		echo $msg;
		?>
        <form action="" method="post">  
        <?php wp_nonce_field( 'emailsignup_remove_action', 'emailsignup_remove_nonce_field' ); ?>
        <div class="emailsignup_panel">
        	<?php if ( !$signups ): ?>
            <p class="blurb"><?php _e( 'Once you start collecting emails, you will have the option have removing them here.', $this->text_domain ); ?></p>
            <?php else: ?>
            <p class="blurb"><?php _e( 'Select the email you want to remove from your list and click on the "Remove" button.', $this->text_domain ); ?></p>
            <div class="section">
                <div class="header"><?php _e( 'Emails', $this->text_domain ); ?></div>
                <div>
                    <select name="emailsignup_id" style="width:100%;">
						<?php
                        foreach( $signups as $signup ):
                        ?>
                        <option value="<?php echo $signup->id; ?>"><?php echo $signup->email; ?></option>
                        <?php
                        endforeach;	
                        ?>
                    </select>
                </div>
            </div>
            <?php if ( $nationbuilder_slug && $nationbuilder_api_token ): ?>
            <div class="section">
                <div>
                    <label><input type="checkbox" name="emailsignup_nationbuilder" value="1" />&nbsp;Remove from NationBuilder also</label>
                </div>
            </div>
            <?php endif; ?>
            <div><input type="submit" name="Submit" value="<?php _e( 'Remove', $this->text_domain ); ?>" class="button-primary" /></div>
            <?php endif; ?>
        </div>
        </form>
		<?php
	}
	
	public function panel_email_download( )
	{
		?>
        <form action="" method="post">  
        <?php wp_nonce_field( 'emailsignup_download_action', 'emailsignup_download_nonce_field' ); ?>
        <div class="emailsignup_panel">
        	<p class="blurb"><?php _e( 'Download a CSV spreadsheet of your current Email Signups list by clicking on the "Download" button.', $this->text_domain ); ?></p>
            <div><input type="submit" name="Submit" value="<?php _e( 'Download', $this->text_domain ); ?>" class="button-primary" /></div>
        </div>
        </form>
		<?php
	}
	
	public function header_scripts( )
	{
		?>
<style type="text/css">
	.emailsignup_panel .section { margin:0 0 20px 0; }
		.emailsignup_panel .section .header { font-size:14px; margin:0 0 10px 0; }
	.emailsignup_panel .blurb { font-size:14px; margin:0 0 10px 0; }
</style>        
		<?php
	}

	public function page_actions( )
	{
		do_action( 'add_meta_boxes_' . $this->page, NULL );
		do_action( 'add_meta_boxes', $this->page, NULL );
		add_screen_option( 'layout_columns', array( 'max' => 2, 'default' => 2 ) );
		wp_enqueue_script( 'postbox' );
	}
	
	public function footer_scripts( )
	{
		?>
		<script>postboxes.add_postbox_toggles(pagenow);</script>
		<?php
	}

	public function setup_page( )
	{
		?>
		<div class="wrap">
			<?php screen_icon( ); ?>
			<h2><?php _e( $this->title, $this->text_domain ); ?></h2>
			<?php
			wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false );
			wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
			?>
			<div id="poststuff">
                <div id="post-body" class="metabox-holder columns-<?php echo 1 == get_current_screen()->get_columns() ? '1' : '2'; ?>"> 
                    <div id="postbox-container-1" class="postbox-container">
						<?php do_meta_boxes( '', 'side', NULL ); ?>
                    </div>    
                    <div id="postbox-container-2" class="postbox-container">
							<?php do_meta_boxes( '', 'normal', NULL );  ?>
							<?php do_meta_boxes( '', 'advanced', NULL ); ?>
                    </div>	     					
                </div>
			</div>
		</div>
		<?php
	}
	
	public function nationbuilder_update( )
	{
		$settings = get_option( $this->slug );
		if ( $settings )
		{
			global $wpdb;
			$nationbuilder_slug			= $settings['nationbuilder_slug'];
			$nationbuilder_api_token	= $settings['nationbuilder_api_token'];
			if ( !$nationbuilder_slug || !$nationbuilder_api_token )
			{
				return false;	
			}
			$emails	= 	$wpdb->get_results( "SELECT * FROM `$this->WPDB_EMAILSIGNUP` WHERE `nationbuilder_id` = 0;" );		
			if ( !$emails )
			{
				return false;
			}
			$api_url	= 'https://' . $nationbuilder_slug . '.nationbuilder.com';
			$endpoint	= $api_url . '/api/v1/people/push/?access_token=' . $nationbuilder_api_token;
			foreach( $emails as $email ):
				$tags			= array( );
				$email_consent	= $email->email_consent;
				$postalcode		= $email->postalcode;
				if ( $email->riding )
					$tags[] = $email->riding;
				$person = array(
					'person'	=> array( 
						'email'		=> $email->email,
					),
				);
				if ( 1 == $email_consent )
					$person['person']['email_opt_in'] = true;
				if ( $postalcode )
					$person['person']['home_address']['zip'] = $postalcode;
				//print_r( $person );
				$args = array(
					'headers' => array( 'Content-type' => 'application/json' ),
					'method' => 'PUT',
					'body' => json_encode( $person ),
				);
				$response = wp_remote_request( $endpoint, $args );
				//echo '<div><textarea style="width:100%;height:500px;">' . print_r( $response, 1 ) . '</textarea></div>';
				if ( is_wp_error( $response ) )
				{

				}
				else
				{
					if ( is_array( $response ) )
					{
						if( 201 == $response['response']['code'] || 200 == $response['response']['code'] )
						{
							$body				= $response['body'];
							$object				= json_decode( $body );
							$nationbuilder_id	= $object->person->id;
							$update = $wpdb->update( 
								$this->WPDB_EMAILSIGNUP, 
								array(
									'nationbuilder_id'		=> $nationbuilder_id,
									'nationbuilder_updated'	=> date( 'Y-m-d H:i:s' ),
								), 
								array( 'id' => ( int ) $email->id ), 
								array( '%s', '%s' ), 
								array( '%d' ) 
							);
							if ( !empty( $tags ) )
							{
								foreach( $tags as $tag )
								{
									$endpoint2 = $api_url . '/api/v1/people/' . $nationbuilder_id . '/taggings/?access_token=' . $nationbuilder_api_token;
									$person_info = array( 
										'tagging' => array(
											'tag'	=> $tag,
										),
									);
									$args2 = array(
										'headers' => array( 'Content-type' => 'application/json' ),
										'method' => 'PUT',
										'body' => json_encode( $person_info ),
									);
									$response2 = wp_remote_request( $endpoint2, $args2 );
									//echo '<div><textarea style="width:100%;height:500px;">' . print_r( $response2, 1 ) . '</textarea></div>';
								}
							}
						}
					}
				}
			endforeach;
		}
	}
	
	/* FRONT-END */
	
	private function process_form(  )
	{
		$process = $_REQUEST['emailsignup'];
		if ( isset( $process ) )
		{
			if ( !$_SERVER['HTTP_X_REQUESTED_WITH'] )
			{
				return false;	
			}
			$p = $_POST;
			if ( !wp_verify_nonce( $p['nonce'], 'emailsignup_shortcode' ) )
			{
				$data = array( 'msg' => 'Invalid authentication' );
				echo json_encode( $data );
				exit( );
			}
			global $wpdb;
			$email = $this->scrub( $p['email'] );
			if ( is_email( $email ) )
			{
				$email			= strtolower( $email );
				$data['email']	= $email;
				$format[]		= '%s';
				$postalcode_check = $this->scrub( $p['postalcode_check'] );
				if ( $postalcode_check )
				{
					$postalcode = $this->scrub( $p['postalcode'] );
					$valid_postalcode = preg_match( '/^[a-zA-Z][0-9][a-zA-Z][[:space:]]*[0-9][a-zA-Z][0-9]$/', $postalcode );
					if ( !$valid_postalcode )
					{
						$data['msg'] = __( 'Please enter a valid postal code.' );
						echo json_encode( $data );
						exit( );
					}
					$postalcode			= str_replace( ' ', '', $postalcode );
					$postalcode			= strtoupper( $postalcode );
					$data['postalcode'] = $postalcode;
					$format[]			= '%s';
				}
				$post_id = $this->scrub( $p['post_id'] );
				if ( $post_id )
				{
					$data['post_id']	= $post_id;
					$format[]			= '%d';
				}
				$post_id = $this->scrub( $p['post_id'] );
				if ( $post_id )
				{
					$data['post_id']	= $post_id;
					$format[]			= '%d';
				}
				$riding_lookup = $this->scrub( $p['riding_lookup'] );
				if ( $riding_lookup )
				{
					$endpoint				= "http://represent.opennorth.ca/postcodes/{$postalcode}/?sets=federal-electoral-districts";
					$response				= wp_remote_get( $endpoint );
					if ( is_array( $response ) )
					{
						$body	= $response['body'];
						$object	= json_decode( $body );
						if ( !empty( $object->boundaries_centroid ) ):
							$riding			= $object->boundaries_centroid[0]->external_id;
							$data['riding']	= $riding;
							$format[]		= '%s';
						endif;
					}
				}
				$email_duplicate = $wpdb->get_row( "SELECT * FROM `$this->WPDB_EMAILSIGNUP` WHERE `email` = '{$email}';" );
				if ( $email_duplicate )
				{
					$insert = true;
				}
				else
				{
					$insert = $wpdb->insert( $this->WPDB_EMAILSIGNUP, $data, $format );
				}
				if ( $insert )
				{
					$data['msg']	= __( 'Thank you for subscribing.' );
					$data['success']= true;
				}
				else
				{
					$data['msg'] = __( 'There was an issue subscribing. Please try again.' );
				}
			}
			else
			{
				$data['msg'] = __( 'Please enter a valid email address.' );
			}
			echo json_encode( $data );
			exit( );
		}
	}
	
	private function get_form( $atts )
	{
		global $post;
		$postalcode		= true;
		$riding_lookup	= true;
		$nationbuilder	= false;
		if ( is_array( $atts ) )
		{
			if ( $atts['postalcode'] )
				$postalcode = ( 'false' == $atts['postalcode'] ) ? false : true;
			if ( $atts['riding_lookup'] )
				$riding_lookup = ( 'false' == $atts['riding_lookup'] ) ? false : true;
			if ( $atts['nationbuilder'] )
				$nationbuilder = ( 'true' == $atts['nationbuilder'] ) ? true : false;
		}
		ob_start( );
		?>
        <div class="emailsignup_container">
            <div class="emailsignup_row emailsignup_notification">
            	<div class="emailsignup_col_1_of_1">
                	<div class="emailsignup_msg">
            			<p></p>
                    </div>
                </div>
            </div>
            <div class="emailsignup_row emailsignup_form_container">
            	<form action="" method="post" class="emailsignup_form">
                	<?php wp_nonce_field( 'emailsignup_shortcode', 'emailsignup_shortcode_nonce_field' ); ?>
                    <input type="hidden" name="emailsignup_post_id" value="<?php echo ( is_front_page( ) ) ? false : $post->ID; ?>" />
                    <input type="hidden" name="emailsignup_uri" value="<?php echo get_option( 'home' ) . '/?emailsignup=' . date( 'YmdHis' ); ?>" />
					<?php if ( $postalcode ): ?>
                    <input type="hidden" name="emailsignup_riding_lookup" value="<?php echo ( $riding_lookup ) ? 1 : 0; ?>" />
                    <input type="hidden" name="emailsignup_postalcode_check" value="1" />
                    <div class="emailsignup_col_2_of_5"><input type="email" name="emailsignup_email" value="" placeholder="Email Address" maxlength="64" /></div>
                    <div class="emailsignup_col_2_of_5"><input type="text" name="emailsignup_postalcode" value="" placeholder="Postal Code" maxlength="7" /></div>
                    <?php else: ?>
                    <div class="emailsignup_col_4_of_5"><input type="email" name="emailsignup_email" value="" placeholder="Email Address" maxlength="64" /></div>
                    <?php endif; ?>
                    <div class="emailsignup_col_1_of_5"><input type="submit" name="emailsignup_submit" value="Submit" /></div>
           		</form>
            </div>
        </div>
        <script type="text/javascript">
			jQuery( document ).ready( function( $ ) {
				var emailsignup_uri_field = $( 'input[name="emailsignup_uri"]' ),
					emailsignup_post_id_field = $( 'input[name="emailsignup_post_id"]' ),
					emailsignup_email_field = $( 'input[name="emailsignup_email"]' ),
					emailsignup_postalcode_field = $( 'input[name="emailsignup_postalcode"]' ),
					emailsignup_postalcode_check_field = $( 'input[name="emailsignup_postalcode_check"]' ),
					emailsignup_riding_lookup_field = $( 'input[name="emailsignup_riding_lookup"]' ),
					emailsignup_nonce_field = $( 'input[name="emailsignup_shortcode_nonce_field"]' ),
					emailsignup_submit_field = $( 'input[name="emailsignup_submit"]' );
			});		
		</script>
        <?php
		$output = ob_get_contents( );
		ob_end_clean( );
		return $output;
	}
	
	public function shortcode( $atts )
	{
		return $this->get_form( $atts );
	}
	
	public function scripts( )
	{
		wp_enqueue_style( 'emailsignup', $this->URI . 'emailsignup.min.css' );
		wp_enqueue_script( 'emailsignup', $this->URI . 'emailsignup.min.js', array( 'jquery' ), NULL, true );
	}
}
$EMAILSIGNUP = new EMAILSIGNUP( );
else :
	exit( "Class 'EMAILSIGNUP' already exists" );
endif;
if ( isset( $EMAILSIGNUP ) )
{
	if ( is_admin( ) )
	{
		@$EMAILSIGNUP->setup_menu_page( );
		register_activation_hook( __FILE__, array( &$EMAILSIGNUP, 'initial_install' ) );
		register_deactivation_hook( __FILE__, array( &$EMAILSIGNUP, 'uninstall' ) );
		add_action( 'admin_init', array( &$EMAILSIGNUP, 'admin_init' ) );
	}
	add_action( 'emailsignup_updates', array( &$EMAILSIGNUP, 'nationbuilder_update' ) );
	add_action( 'init', array( &$EMAILSIGNUP, 'init' ) );
	add_action( 'wp_enqueue_scripts', array( &$EMAILSIGNUP, 'scripts' ) );
	add_shortcode( 'EMAILSIGNUP', array( &$EMAILSIGNUP, 'shortcode' ) );
}
?>