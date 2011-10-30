<?php
/*
Plugin Name: Flickr to WP
Plugin URI: http://bradt.ca/projects/wordpress/wp-migrate-db/
Description: Retake control of your photos by importing them into your Wordpress web site from your Flickr account.
Author: Brad Touesnard
Version: 0.1
Author URI: http://bradt.ca/
*/

// Copyright (c) 2011 Brad Touesnard. All rights reserved.
//
// Released under the GPL license
// http://www.opensource.org/licenses/gpl-license.php
//
// **********************************************************************
// This program is distributed in the hope that it will be useful, but
// WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
// **********************************************************************

// Define the directory seperator if it isn't already
if (!defined('DS')) {
    if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN') {
        define('DS', '\\');
    }
    else {
        define('DS', '/');
    }
}

class Flickr_to_WP {
    var $errors;
    var $upload_dir;
    var $upload_url;
    var $filename;
	var $nicename;
    var $fp;
    var $replaced;
	
	var $plugin_path;

    function __construct() {
        $this->errors = array();
		
		$this->plugin_path = dirname(__FILE__);
		
		require $this->plugin_path . '/phpFlickr/phpFlickr.php';
		
        $this->upload_dir = ( defined('WP_CONTENT_DIR') ) ? WP_CONTENT_DIR . '/uploads' : ABSPATH . 'wp-content' . DS . 'uploads';
        $this->upload_url = ( defined('WP_CONTENT_URL') ) ? WP_CONTENT_URL . '/uploads' : home_url() . '/wp-content/uploads';

		add_action('admin_menu', array($this, 'admin_menu'));
		add_action('admin_init', array($this, 'admin_init'));
		
		add_action('wp_ajax_migrate_set', array($this, 'migrate_set'));
		add_action('wp_ajax_migrate_photo', array($this, 'migrate_photo'));
		add_action('wp_ajax_photoset_info', array($this, 'photoset_info'));
    }

    function show_error($key) {
        if (isset($this->errors[$key])) {
            echo '<br /><span style="color: #cc0000; font-weight: bold;">', $this->errors[$key], '</span>';
        }
    }
	
	function header() {
		?>
		
		<div class="wrap">
			<?php screen_icon(); echo "<h2>" . __( 'Flickr to WP' ) . "</h2>"; ?>
			
		<?php		
	}
	
	function footer() {
		?>
		
		</div>
		
		<?php
	}
	
	function display_fields($fields, $values) {

		foreach ($fields as $id => $field) :
			
			switch ($field['type']) :
				
				case 'checkbox': // Checkbox
					?>
					
					<tr valign="top">
						<th scope="row"><label for="flickr_to_wp[<?php echo $id; ?>]"><?php echo $field['label']; ?></label></th>
						<td>
							<input id="flickr_to_wp[<?php echo $id; ?>]" name="flickr_to_wp[<?php echo $id; ?>]" type="checkbox" value="1" <?php checked( '1', $values[$id] ); ?> />
							<?php if ( $field['desc'] ) : ?>
							<span class="description"><?php echo $field['desc']; ?></span>
							<?php endif; ?>
						</td>
					</tr>
	
					<?php
					break;
				
				case 'select':  // Select box
					?>
					
					<tr valign="top">
						<th scope="row"><label for="flickr_to_wp[<?php echo $id; ?>]"><?php echo $field['label']; ?></label></th>
						<td>
							<select name="flickr_to_wp[<?php echo $id; ?>]">
								<?php
								$p = '';
								$r = '';

								foreach ( $field['options'] as $value => $label ) {
									if ( $values[$id] == $value ) // Make default first in list
										$p = "\n\t<option style=\"padding-right: 10px;\" selected='selected' value='" . esc_attr( $value ) . "'>$label</option>";
									else
										$r .= "\n\t<option style=\"padding-right: 10px;\" value='" . esc_attr( $value ) . "'>$label</option>";
								}
								echo $p . $r;
								?>
							</select>
							<?php if ( $field['desc'] ) : ?>
							<span class="description"><?php echo $field['desc']; ?></span>
							<?php endif; ?>
						</td>
					</tr>
	
					<?php
					break;
				
				case 'radios': // Radio buttons
					?>
					
					<tr valign="top">
						<th scope="row"><label for="flickr_to_wp[<?php echo $id; ?>]"><?php echo $field['label']; ?></label></th>
						<td>
							<fieldset><legend class="screen-reader-text"><span><?php echo $field['label']; ?></span></legend>
							<?php
								if ( ! isset( $checked ) )
									$checked = '';
								foreach ( $radio_options as $option ) {
									if ( $values[$id] == $option['value'] ) {
										$checked = "checked=\"checked\"";
									} else {
										$checked = '';
									}
									?>
									<label class="description"><input type="radio" name="flickr_to_wp[<?php echo $id; ?>]" value="<?php esc_attr_e( $option['value'] ); ?>" <?php echo $checked; ?> /> <?php echo $option['label']; ?></label><br />
									<?php
								}
							?>
							</fieldset>
							<?php if ( $field['desc'] ) : ?>
							<span class="description"><?php echo $field['desc']; ?></span>
							<?php endif; ?>
						</td>
					</tr>
	
					<?php
					break;
				
				case 'textarea': // Textarea
					$rows = ( isset( $field['rows'] ) ) ? $field['rows'] : 10;
					$cols = ( isset( $field['cols'] ) ) ? $field['cols'] : 50;
					?>

					<tr valign="top">
						<th scope="row"><label for="flickr_to_wp[<?php echo $id; ?>]"><?php echo $field['label']; ?></label></th>
						<td>
							<textarea id="flickr_to_wp[<?php echo $id; ?>]" class="large-text" cols="<?php echo $cols; ?>" rows="<?php echo $rows; ?>" name="flickr_to_wp[<?php echo $id; ?>]"><?php echo stripslashes( $values[$id] ); ?></textarea>
							<?php if ( $field['desc'] ) : ?>
							<span class="description"><?php echo $field['desc']; ?></span>
							<?php endif; ?>
						</td>
					</tr>
					
					<?php
					break;
				
				default: // Text box
					?>

					<tr valign="top">
						<th scope="row"><label for="flickr_to_wp[<?php echo $id; ?>]"><?php echo $field['label']; ?></label></th>
						<td>
							<input id="flickr_to_wp[<?php echo $id; ?>]" class="regular-text" type="text" name="flickr_to_wp[<?php echo $id; ?>]" value="<?php esc_attr_e( $values[$id] ); ?>" />
							<?php if ( $field['desc'] ) : ?>
							<span class="description"><?php echo $field['desc']; ?></span>
							<?php endif; ?>
						</td>
					</tr>
	
					<?php
			endswitch;
			
		endforeach;
		
	}
	
	function render_page() {
		$settings = $this->get_settings();
		
		if (!isset($settings['api_token'])) {
			return $this->auth_page();
		}

		if (isset($_POST['action']) && method_exists($this, $_POST['action'] . '_page')) {
			return call_user_method($_POST['action'] . '_page', $this);
		}

		return $this->migrate_settings_page();
	}
	
	function get_settings() {
		return get_option('flickr_to_wp');
	}
	
	function migrate_photo() {
		set_time_limit(5*60);

		extract($this->get_settings());

		$flickr = new phpFlickr($api_key, $api_secret);
		$flickr->setToken($api_token);
		
		$info = $flickr->photos_getInfo($_POST['photo_flickr_id']);
		$info = $info['photo'];

		$photo_wp_id = $this->media_sideload_image($_POST['photo_url'], $_POST['set_wp_id'], '', array(
			'post_title' => $info['title'],
			'post_content' => $info['description'],
			'post_date' => gmdate( 'Y-m-d H:i:s', ( $info['dateuploaded'] + ( get_option( 'gmt_offset' ) * 3600 ) ) ),
			'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $info['dateuploaded'] )
		));
		
		$tags = $info['tags']['tag'];
		
		$terms = array();
		foreach ($tags as $tag) {
			$terms[] = $tag['raw'];
		}
		
		wp_set_object_terms($photo_wp_id, $terms, $tax_tag);

		if (isset($_POST['isprimary']) && $_POST['isprimary']) {
			set_post_thumbnail($_POST['set_wp_id'], $photo_wp_id);
		}

		if (isset($info['dates']['taken']) && $info['dates']['taken']) {
			update_post_meta($photo_wp_id, 'date_taken', strtotime($info['dates']['taken']));
		}

		if (isset($info['views']) && $info['views']) {
			update_post_meta($photo_wp_id, 'views', $info['views']);
		}

		if (isset($info['comments']) && $info['comments']) {
			$comments = $flickr->photos_comments_getList($_POST['photo_flickr_id']);
			$comments = $comments['comments']['comment'];
			foreach ($comments as $comment) {
				wp_insert_comment(array(
					'comment_post_ID' => $photo_wp_id,
					'comment_author' => $comment['authorname'],
					'comment_content' => $comment['_content'],
					'comment_date' => gmdate( 'Y-m-d H:i:s', ( $comment['datecreate'] + ( get_option( 'gmt_offset' ) * 3600 ) ) ),
					'comment_date_gmt' => gmdate( 'Y-m-d H:i:s', $comment['datecreate'] )
				));
			}
		}
		
		echo wp_get_attachment_image($photo_wp_id, 'thumbnail');

		die(); // required
	}
	
	function migrate_set() {
		set_time_limit(0);

		extract($this->get_settings());

		$flickr = new phpFlickr($api_key, $api_secret);
		$flickr->setToken($api_token);
		
		$set = $flickr->photosets_getInfo($_POST['set_flickr_id']);

		$_photos = $flickr->photosets_getPhotos($_POST['set_flickr_id'], 'date_upload,url_o,url_l');
		$_photos = $_photos['photoset']['photo'];
		
		$photos = array();
		
		if ($_photos) {
			foreach ($_photos as $photo) {
				if (isset($photo['url_o'])) {
					$url = $photo['url_o'];
				}
				else {
					$url = $photo['url_l'];
				}
				
				$photos[] = array(
					'flickr_id' => $photo['id'],
					'url' => $url,
					'isprimary' => $photo['isprimary']
				);
			}
		}
		
		if ($date = strtotime($set['description'])) {
			$desc = '';
		}
		else {
			$date = $set['date_create'];
			$desc = $set['description'];
		}
		
		$set_wp_id = wp_insert_post(array(
			'post_type' => $set_post_type,
			'post_title' => $set['title'],
			'post_content' => $desc,
			'post_status' => 'publish',
			'post_date' => gmdate( 'Y-m-d H:i:s', ( $date + ( get_option( 'gmt_offset' ) * 3600 ) ) ),
			'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $date )
		));
		
		if ($_POST['collection_term_id']) {
			wp_set_post_terms($set_wp_id, $_POST['collection_term_id'], $tax_collection);
		}
		
		echo json_encode(compact('set_wp_id', 'photos'));
		
		die(); // required
	}
	
	function photoset_info() {
		set_time_limit(0);

		extract($this->get_settings());

		$flickr = new phpFlickr($api_key, $api_secret);
		$flickr->setToken($api_token);
		
		$set = $flickr->photosets_getInfo($_POST['flickr_id']);

		/*
		$_photos = $flickr->photosets_getPhotos($_POST['flickr_id'], 'date_upload,url_o,date_taken,views');
		$_photos = $_photos['photoset']['photo'];

		$photos = array();
		foreach ($_photos as $photo) {
			$info = $flickr->photos_getInfo($photo['id']);
			$info = $info['photo'];
			print_r($info);
		}
		*/
		
		echo json_encode($set);
		
		die(); // required
	}
		
	function migrate_sets_page() {
		set_time_limit(0);
		
		$settings = $this->get_settings();
		$settings = array_merge($settings, $_POST['flickr_to_wp']);
		update_option('flickr_to_wp', $settings);
		
		extract($settings);

		$this->header();

		$flickr = new phpFlickr($api_key, $api_secret);
		$flickr->setToken($api_token);
		
		$collections = $flickr->collections_getTree();
		$collections = $collections['collections']['collection'];
			
		$sets_list = $flickr->photosets_getList();
		$sets_list = $sets_list['photoset'];
		
		$sets_in_collection = array();
		?>
		
		<form method="post" action="" class="migrate">
			<?php settings_fields( 'flickr-to-wp' ); ?>
			<input type="hidden" name="action" value="migrate_photos" />

			<div class="select-controls">
				<a href="" class="select-all">Select All</a><span class="sep">|</span><a href="" class="select-none">Select None</a>
			</div>
			
			<table class="migrate-sets">
				
			<?php
			$i = 0;
			foreach ($collections as $collection) {
				$sets = $collection['set'];
				?>
				
				<?php if ($i) : ?>
				<tr>
					<td colspan="4" class="spacer">&nbsp;</td>
				</tr>
				<?php endif; ?>
				
				<tr>
					<td class="check"><input type="checkbox" name="collection_ids[]" id="collection-<?php echo $collection['id']; ?>" value="<?php echo $collection['id']; ?>" class="collection-ids" /></td>
					<th colspan="3" class="collection" id="<?php echo $collection['id']; ?>"><label for="collection-<?php echo $collection['id']; ?>"><?php echo $collection['title']; ?> <span class="count">(Sets: <?php echo count($sets); ?>)</span></label></th>
				</tr>
				
				<?php
				$collection_term = get_term_by('name', $collection['title'], $tax_collection);
				
				if ($collection_term) {
					$collection_term_id = $collection_term->term_id;
				}
				else {
					$collection_term = wp_insert_term($collection['title'], $tax_collection, array(
						'description' => $collection['description']
					));
				
					if (is_wp_error($collection_term)) {
						echo $collection_term->get_error_message() . "<br />";
					}
					else {
						$collection_term_id = $collection_term['term_id'];
					}
				}
				
				foreach ($sets as $set) {
					$sets_in_collection[] = $set['id'];
					?>
					
					<tr>
						<td class="check"><input type="checkbox" name="set_ids[]" id="set-<?php echo $set['id']; ?>" value="<?php echo $set['id']; ?>" rel="<?php echo $collection_term_id; ?>" class="set-ids collection-<?php echo $collection['id']; ?>" /></td>
						<td class="title"><label for="set-<?php echo $set['id']; ?>"><?php echo $set['title']; ?></label> <span class="more">(<a href="">more info</a>)</span></td>
						<td class="thumb"></td>
						<td class="progress"><div><span class="bar"></span><span class="txt"></span></div></td>
					</tr>
					
					<?php
				}
			
				$i++;
			}
			
			$sets_no_collection = array();
			foreach ($sets_list as $set) {
				if (!in_array($set['id'], $sets_in_collection)) {
					$sets_no_collection[] = $set;
				}
			}
			?>

			<?php if ($sets_no_collection) : ?>
				<tr>
					<td colspan="4" class="spacer">&nbsp;</td>
				</tr>
				<tr>
					<td class="check"><input type="checkbox" name="collection_ids[]" id="collection-0" value="0" class="collection-ids" /></td>
					<th colspan="3" class="collection"><label for="collection-0"><em>Not in a Collection</em></label></th>
				</tr>
				
				<?php
				foreach ($sets_no_collection as $set) {
					?>
					
					<tr>
						<td class="check"><input type="checkbox" name="set_ids[]" id="set-<?php echo $set['id']; ?>" value="<?php echo $set['id']; ?>" rel="0" class="set-ids collection-0" /></td>
						<td class="title"><label for="set-<?php echo $set['id']; ?>"><?php echo $set['title']; ?></label> <span class="more">(<a href="">more info</a>)</span></td>
						<td class="thumb"></td>
						<td class="progress"><div><span class="bar"></span><span class="txt"></span></div></td>
					</tr>
					
					<?php
				}
				
			endif;
			?>

			</table>

			<div class="select-controls bottom">
				<a href="" class="select-all">Select All</a><span class="sep">|</span><a href="" class="select-none">Select None</a>
			</div>

			<p class="submit">
				<input type="submit" class="migrate-photos button-primary" value="<?php _e( 'Migrate Photos' ); ?>" />
			</p>
		</form>
		<?php
		$this->footer();
	}

	function migrate_settings_page() {
		if ( ! isset( $_REQUEST['settings-updated'] ) )
			$_REQUEST['settings-updated'] = false;

		$settings = $this->get_settings();
		extract($settings);

		$flickr = new phpFlickr($api_key, $api_secret);
		$flickr->setToken($api_token);
		
		$photos_no_set = $flickr->photos_getNotInSet();
		$photos_no_set = $photos_no_set['photos']['photo'];
		
		$this->header();
		?>

		<?php if ( $_REQUEST['settings-updated'] ) : ?>
		<div class="updated fade"><p><strong><?php _e( 'Options saved' ); ?></strong></p></div>
		<?php endif; ?>

		<form method="post" action="">
			<?php settings_fields( 'flickr-to-wp' ); ?>
			<input type="hidden" name="action" value="migrate_sets" />

			<table class="form-table">
			
			<?php
			$post_types = array_diff(array_reverse(get_post_types()), array('page', 'attachment', 'revision', 'nav_menu_item'));
			$taxonomies = array_diff(array_reverse(get_taxonomies()), array('nav_menu', 'link_category', 'post_format'));
			
			if (!$settings['set_post_type']) {
				foreach ($post_types as $post_type) {
					if (preg_match('@set@', $post_type)) {
						$settings['set_post_type'] = $post_type;
					}
				}
			}

			if (!$settings['tax_tag']) {
				foreach ($taxonomies as $tax) {
					if (preg_match('@tag@', $tax)) {
						$settings['tax_tag'] = $tax;
					}
				}
			}

			if (!$settings['tax_collection']) {
				foreach ($taxonomies as $tax) {
					if (preg_match('@collection@', $tax)) {
						$settings['tax_collection'] = $tax;
					}
				}
			}
		
			$fields = array(
				'set_post_type' => array(
					'type' => 'select',
					'label' => 'Photo Set Post Type',
					'options' => $post_types,
					'desc' => '<br /><a href="http://wptest/nightly/wp-admin/plugin-install.php?tab=search&type=term&s=custom+post+type">Setup more post types using a plugin</a>'
				),
				'tax_tag' => array(
					'type' => 'select',
					'label' => 'Photo Tag Taxonomy',
					'options' => $taxonomies,
					'desc' => '<br /><a href="http://wptest/nightly/wp-admin/plugin-install.php?tab=search&type=term&s=custom+taxonomy">Setup more taxonomies using a plugin</a>'
				),
				'tax_collection' => array(
					'type' => 'select',
					'label' => 'Photo Collection Taxonomy',
					'options' => $taxonomies,
					'desc' => '<br /><a href="http://wptest/nightly/wp-admin/plugin-install.php?tab=search&type=term&s=custom+taxonomy">Setup more taxonomies using a plugin</a>'
				)
			);
		
			$this->display_fields($fields, $settings);
			?>
			
			<?php if ($photos_no_set) : ?>
			<tr>
				<td class="warning" colspan="2">
				<strong>Warning:</strong> You have <?php echo count($photos_no_set); ?> photos not in a set.
				To migrate all your photos, please ensure all photos are included in a set.
				</td>
			</tr>
			<?php endif; ?>

			</table>

			<p class="submit">
				<input type="submit" class="button-primary" value="<?php _e( 'Next Step' ); ?>" />
			</p>
		</form>
		<?php
		$this->footer();
	}
		
	function auth_page() {
		if ( ! isset( $_REQUEST['settings-updated'] ) )
			$_REQUEST['settings-updated'] = false;

		if (isset($_POST['flickr_to_wp'])) {
		}
		
		$api_callback = site_url() . '/wp-admin/tools.php?page=flickr-to-wp';
		
		$this->header();
		?>
		
		<h3>Connect to Flickr</h3>

		<?php if ( $_REQUEST['settings-updated'] ) : ?>
		<div class="updated fade"><p><strong><?php _e( 'Options saved' ); ?></strong></p></div>
		<?php else : ?>
		<p>To get started, follow these instructions:</p>
		<ol>
			<li>1. <a href="http://www.flickr.com/services/apps/create/apply/" target="_blank">Create a new API key</a></li>
			<li>2. Choose <strong>Non-Commercial</strong></li>
			<li>3. Enter "Flickr to WP" for the app name and "Importing my Flickr data into WordPress" as the description</li>
			<li>5. Copy the <strong>Key</strong> and <strong>Secret</strong> into the respective fields below (don't submit the form just yet)</li>
			<li>6. Click <strong>Edit auth flow for this app</strong> in Flickr</li>
			<li>7. Enter <strong><?php echo $api_callback; ?></strong> as the Callback URL and save changes</li>
			<li>8. Click the <strong>Authenticate with Flickr</strong> button below</li>
		</ol>
		<?php endif; ?>

		<form method="post" action="">
			<?php settings_fields( 'flickr-to-wp' ); ?>
			<input type="hidden" name="action" value="auth" />

			<table class="form-table">
			
			<?php
			$options = $this->get_settings();
			$options['api_callback'] = $api_callback;
			
			$fields = array(
				'api_key' => array(
					'type' => 'textbox',
					'label' => 'Flickr API Key',
				),
				'api_secret' => array(
					'type' => 'textbox',
					'label' => 'Flickr API Secret',
				),
				'api_callback' => array(
					'type' => 'textbox',
					'label' => 'Flickr API Callback URL'
				)
			);
			
			$this->display_fields($fields, $options);
			?>

			</table>

			<p class="submit">
				<input type="submit" class="button-primary" value="<?php _e( 'Authenticate with Flickr' ); ?>" />
			</p>
		</form>
		<?php
		$this->footer();
	}
	
	function admin_init() {
		wp_register_style('flickr-to-wp', plugins_url('/styles.css', __FILE__) );
		wp_register_script( 'flickr-to-wp', plugins_url('/script.js', __FILE__) );
		wp_register_script( 'scrollto', plugins_url('/jquery.scrollTo-1.4.2-min.js', __FILE__), array('jquery') );
		
		if (isset($_POST['action']) && $_POST['action'] == 'auth') {
			update_option('flickr_to_wp', $_POST['flickr_to_wp']);
		}
		
		if ((isset($_POST['action']) && $_POST['action'] == 'auth') || isset($_GET['frob'])) {
			$settings = $this->get_settings();
			extract($settings);

		    unset($_SESSION['phpFlickr_auth_token']);

			if ( isset($_SESSION['phpFlickr_auth_redirect']) && !empty($_SESSION['phpFlickr_auth_redirect']) ) {
				$redirect = $_SESSION['phpFlickr_auth_redirect'];
				unset($_SESSION['phpFlickr_auth_redirect']);
			}
			
			$flickr = new phpFlickr($api_key, $api_secret);
			
			if (empty($_GET['frob'])) {
				$flickr->auth('read', false);
			}
			else {
				$result = $flickr->auth_getToken($_GET['frob']);
				$settings['api_token'] = $result['token'];
				update_option('flickr_to_wp', $settings);
			}
			
			if (empty($redirect)) {
				header("Location: " . remove_query_arg('frob'));
			}
			else {
				header("Location: " . $redirect);
			}
			
			exit;
		}
	}
	
	// Modified WP function to return attachment post id instead of <img> tag
	function media_sideload_image($file, $post_id, $desc, $post_data) {
		// Download file to temp location
		$tmp = download_url( $file );

		// Set variables for storage
		// fix file filename for query strings
		preg_match('/[^\?]+\.(jpg|JPG|jpe|JPE|jpeg|JPEG|gif|GIF|png|PNG)/', $file, $matches);
		$file_array['name'] = basename($matches[0]);
		$file_array['tmp_name'] = $tmp;

		// If error storing temporarily, unlink
		if ( is_wp_error( $tmp ) ) {
			@unlink($file_array['tmp_name']);
			$file_array['tmp_name'] = '';
		}

		// do the validation and storage stuff
		$id = media_handle_sideload( $file_array, $post_id, $desc, $post_data );
		// If error storing permanently, unlink
		if ( is_wp_error($id) ) {
			@unlink($file_array['tmp_name']);
			return $id;
		}

		return $id;
	}

    function admin_menu() {
		$page = add_management_page('Flickr to WP','Flickr to WP',8,'flickr-to-wp',array($this, 'render_page'));
		add_action( 'admin_print_styles-' . $page, array($this, 'admin_styles') );
    }
	
	function admin_styles() {
		wp_enqueue_style('flickr-to-wp');
		wp_enqueue_script('flickr-to-wp');
		wp_enqueue_script('scrollto');
	}
}

global $flickrtowp;
$flickrtowp = new Flickr_to_WP;
?>
