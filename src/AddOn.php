<?php
/**
 * Gravity Forms Class
 *
 * Extends Gravity Forms.
 *
 * You may copy, distribute and modify the software as long as you track changes/dates in source files.
 * Any modifications to or software including (via compiler) GPL-licensed code must also be made
 * available under the GPL along with build & install instructions.
 *
 * @package    WPS\Core
 * @author     Travis Smith <t@wpsmith.net>
 * @copyright  2015-2018 Travis Smith
 * @license    http://opensource.org/licenses/gpl-2.0.php GNU Public License v2
 * @link       https://github.com/wpsmith/WPS
 * @version    1.0.0
 * @since      0.1.0
 */

namespace WPS\Plugins\GravityForms\AddOn;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPS\Plugins\GravityForms\AddOn\AddOn' ) ) {

	// Includes the feeds portion of the add-on framework.
	\GFForms::include_feed_addon_framework();

	/**
	 * GravityFormsAddOn class.
	 *
	 * Child classes should contain `private static $_instance` and
	 * implement `get_instance()` method.
	 *
	 * @package WPS\Plugins\GravityForms
	 */
	class AddOn extends \GFFeedAddOn {

		/**
		 * Plugin name.
		 *
		 * @var string
		 */
		protected $_title = '';

		/**
		 * Form Setting Title.
		 *
		 * @var string
		 */
		protected $_short_title = '';

		/**
		 * Plugin slug.
		 *
		 * @var string
		 */
		protected $_slug = '';

		/**
		 * Plugin basename.
		 *
		 * @var string
		 */
		protected $_path = '';

		/**
		 * Enable auto updates.
		 *
		 * @var bool
		 */
		protected $_enable_rg_autoupgrade = true;

		/**
		 * Members plugin integration
		 *
		 * @var array
		 */
		protected $_capabilities = array();

		/**
		 * Permissions for settings page.
		 *
		 * @var string
		 */
		protected $_capabilities_settings_page = '';

		/**
		 * Permissions for form settings.
		 *
		 * @var string
		 */
		protected $_capabilities_form_settings = '';

		/**
		 * Permissions for uninstalling plugin.
		 *
		 * @var string
		 */
		protected $_capabilities_uninstall = '';

		/**
		 * Whether feed ordering is supported.
		 *
		 * @var bool
		 */
		protected $_supports_feed_ordering = false;

		/**
		 * Array of Feed Actions.
		 *
		 * @var array
		 */
		protected $actions;

//		/**
//		 * @var GravityFormsAddOn\MergeTags
//		 */
//		protected $merge_tags;

		/**
		 * Initializes GFAddon and adds the actions that we need
		 *
		 * @see GFAddon
		 */
		public function init() {
			if ( method_exists( $this, 'register_actions' ) ) {
				$this->register_actions();
			}

			// Add functionality from the parent GFAddon class
			parent::init();
		}
		/**
		 * Initializes GFAddon and adds the actions that we need
		 *
		 * @see GFAddon
		 */
		public function should_run() {
			return (
				is_admin() &&
				defined( 'DOING_AJAX' ) && DOING_AJAX ||
				'heartbeat' === rgpost( 'action' )
			);
		}

		/** FEED PROCESSING */

		/**
		 * Processed the feed for the Custom Post Types add-on.
		 *
		 * @see GFFeedAddOn->process_feed()
		 *
		 * @param array $feed  The Feed object.
		 * @param array $entry The Entry object.
		 * @param array $form  The Form object.
		 *
		 * @return mixed
		 */
		public function process_feed_with_actions( $type, $feed, $entry, $form ) {

			// Log that the feed is being processed
			$this->log( __METHOD__, "form #{$form['id']} - starting process_feed()." );

			// Initialize & Process if action exists and form has feed type/action.
			if ( $this->actions->exists( $type ) && $this->form_has_feed_type( $type, $form ) ) {

				return $this->actions->run( $type, $feed, $entry, $form );

			}

			return false;

		}

		/** FEED SETTINGS */

		/**
		 * Overrides Feed settings.
		 *
		 * @return array
		 */
		public function feed_settings_fields() {

			// Maybe initialize settings.
			if ( method_exists( $this, 'init_settings' ) ) {
				$this->init_settings();
			}

			// Settings.
			$settings = Settings::get_instance( $this );

			$fields = $settings->get_feed_settings_fields();

			/**
			 * Filter the setting fields that appears on the feed page.
			 *
			 * @since 3.0.beta1.1
			 *
			 * @param array $fields An array of setting fields.
			 * @param array $form   Form object to which the current feed belongs.
			 *
			 * @see   https://gist.github.com/spivurno/15592a66497096338864
			 */
			$fields = apply_filters( 'gform_cpt_feed_settings_fields', $fields, $this->get_current_form() );

			// sections cannot be an associative array
			return array_values( $fields );

		}

		/**
		 * Overrides renders and initializes a drop down field with a input field for custom input based on the $field array.
		 * (Forked to add support for merge tags in input field.)
		 *
		 * @since  2.4
		 * @access public
		 *
		 * @param array $field Field array containing the configuration options of this field
		 * @param bool  $echo  True to echo the output to the screen, false to simply return the contents as a string
		 *
		 * @return string The HTML for the field
		 */
		public function settings_select_custom( $field, $echo = true ) {

			// Prepare select field.
			$select_field             = $field;
			$select_field_value       = $this->get_setting( $select_field['name'], rgar( $select_field, 'default_value' ) );
			$select_field['onchange'] = '';
			$select_field['class']    = ( isset( $select_field['class'] ) ) ? $select_field['class'] . 'gaddon-setting-select-custom' : 'gaddon-setting-select-custom';

			// Prepare input field.
			$input_field          = $field;
			$input_field['name']  .= '_custom';
			$input_field['style'] = 'width:200px;max-width:90%;';
			$input_field['class'] = rgar( $field, 'input_class' );
			$input_field_display  = '';

			// Loop through select choices and make sure option for custom exists.
			$has_gf_custom = self::has_gf_custom( $select_field['choices'] );
			if ( ! $has_gf_custom ) {
				$select_field['choices'][] = array(
					'label' => esc_html__( 'Add Custom', 'gfaddon' ) . ' ' . $select_field['label'],
					'value' => 'gf_custom'
				);
			}

			// If select value is "gf_custom", hide the select field and display the input field.
			if ( $select_field_value == 'gf_custom' || ( count( $select_field['choices'] ) == 1 && $select_field['choices'][0]['value'] == 'gf_custom' ) ) {
				$select_field['style'] = 'display:none;';
			} else {
				$input_field_display = ' style="display:none;"';
			}

			// Add select field.
			$html = $this->settings_select( $select_field, false );

			// Add input field.
			$html .= '<div class="gaddon-setting-select-custom-container"' . $input_field_display . '>';
			$html .= count( $select_field['choices'] ) > 1 ? sprintf( '<a href="#" class="select-custom-reset">%s</a>', __( 'Reset', 'gfaddon' ) ) : '';
			$html .= $this->settings_text( $input_field, false );
			$html .= '</div>';

			if ( $echo ) {
				echo $html;
			}

			return $html;

		}

		/** FEED LISTING PAGE DISPLAY FUNCTIONS */

		/**
		 * Overrides deed list columns for feed listing page.
		 *
		 * @return array
		 */
		public function feed_list_columns() {

			$columns = array(
				'feed_name' => esc_html__( 'Name', 'gfaddon' ),
			);

			if ( !empty( $this->actions ) ) {
				$columns['feed_type'] = esc_html__( 'Action', 'gfaddon' );
			}

			return $columns;
		}

		/**
		 * Overrides feed list title with Add New button.
		 *
		 * @return string
		 */
		public function feed_list_title() {

			$title = '';

			if ( $this->is_feed_list_page() ) {

				$title = sprintf( esc_html__( '%s Feeds', 'gravityforms' ), $this->get_short_title() );

				if ( $this->can_have_multiple() ) {
					$title .= sprintf( ' <a class="add-new-h2" href="%s">%s</a>', add_query_arg( array( 'fid' => '0' ) ), esc_html__( 'Add New', 'gravityforms' ) );
				}

			}

			return $title;
		}

		protected function can_have_multiple() {
			$form  = \GFAPI::get_form( rgget( 'id' ) );

			foreach( $this->actions as $feed_type => $action ) {
				if ( $this->form_has_feed_type( $feed_type, $form ) && ! $action['can_have_multiple'] ) {
					return false;
				}
			}

			return true;
		}

		public function feed_settings_title() {

			$title = sprintf( esc_html__( '%s Feed Settings', 'gfaddon' ), $this->get_short_title() );

			if ( $this->can_have_multiple() ) {
				$title .= sprintf(
					' <a class="button button-secondary" href="%s">%s</a>',
					add_query_arg( array( 'fid' => '0' ) ),
					esc_html__( 'Add New', 'gfaddon' )
				);
			} else {
				$title .= sprintf(
					' <a class="button button-secondary" href="%s">%s</a>',
					remove_query_arg( 'fid' ),
					esc_html__( 'Go Back', 'gfaddon' )
				);
			}

			return $title;

		}

		/**
		 * Overrides defautl and enables the ability to duplicate create feeds.
		 *
		 * @param array|int $feed Feed ID or Feed Object Array.
		 *
		 * @return bool|true
		 */
		public function can_duplicate_feed( $feed ) {

			/* Get the feed. */
			$feed = is_array( $feed ) ? $feed : $this->get_feed( $feed );

			foreach( $this->actions as $feed_type => $action ) {
				if (
					! $action['can_duplicate'] &&
					self::is_feed_type( $feed, $feed_type ) ) {
					return false;
				}
			}

			return true;

		}

		/**
		 * Retrieve an array of form fields formatted for select, radio and checkbox settings fields.
		 *
		 * @access public
		 *
		 * @param array $form - The form object
		 * @param array $args - Additional settings to check for (field and input types to include, callback for applicable input type)
		 *
		 * @return array The array of formatted form fields
		 */
		public function get_form_fields_as_choices( $form, $args = array() ) {

			$fields = array();

			if ( ! is_array( $form['fields'] ) ) {
				return $fields;
			}

			$args = wp_parse_args(
				$args, array(
					'field_types' => array(),
					'input_types' => array(),
					'callback'    => false
				)
			);

			foreach ( $form['fields'] as $field ) {

				if ( ! empty( $args['field_types'] ) && ! in_array( $field->type, $args['field_types'] ) ) {

					continue;

				}

				$input_type               = \GFFormsModel::get_input_type( $field );
				$is_applicable_input_type = empty( $args['input_types'] ) || in_array( $input_type, $args['input_types'] );

				if ( is_callable( $args['callback'] ) ) {
					$is_applicable_input_type = call_user_func( $args['callback'], $is_applicable_input_type, $field, $form );
				}

				if ( ! $is_applicable_input_type ) {
					continue;
				}

				if ( ! empty( $args['property'] ) && ( ! isset( $field->{$args['property']} ) || $field->{$args['property']} != $args['property_value'] ) ) {
					continue;
				}

				$inputs = $field->get_entry_inputs();
				if ( is_array( $inputs ) ) {
					// if this is an address field, add full name to the list
					// if this is a name field, add full name to the list
					if ( $input_type == 'name' || $input_type == 'singleproduct' || $input_type == 'address' ) {
						$fields[] = array(
							'value' => $field->id,
							'label' => \GFCommon::get_label( $field ) . ' (' . esc_html__( 'Full', 'gravityforms' ) . ')'
						);
					}
					// if this is a checkbox field, add to the list
					if ( $input_type == 'checkbox' ) {
						$fields[] = array(
							'value' => $field->id,
							'label' => \GFCommon::get_label( $field ) . ' (' . esc_html__( 'Selected', 'gravityforms' ) . ')'
						);
					}

					if ( ! isset( $args['dynamic'] ) ) {
						foreach ( $inputs as $input ) {
							$fields[] = array(
								'value' => $input['id'],
								'label' => \GFCommon::get_label( $field, $input['id'] )
							);
						}
					}
				} elseif ( $input_type == 'list' && $field->enableColumns ) {
					$fields[]  = array(
						'value' => $field->id,
						'label' => \GFCommon::get_label( $field ) . ' (' . esc_html__( 'Full', 'gravityforms' ) . ')'
					);
					$col_index = 0;
					if ( ! isset( $args['dynamic'] ) ) {
						foreach ( $field->choices as $column ) {
							$fields[] = array(
								'value' => $field->id . '.' . $col_index,
								'label' => \GFCommon::get_label( $field ) . ' (' . rgar( $column, 'text' ) . ')',
							);
							$col_index ++;
						}
					}
				} elseif ( ! $field->displayOnly ) {
					$fields[] = array( 'value' => $field->id, 'label' => \GFCommon::get_label( $field ) );
				} else {
					$fields[] = array(
						'value' => $field->id,
						'label' => \GFCommon::get_label( $field )
					);
				}
			}

			return $fields;
		}

		/** CONDITIONALS */

		/**
		 * Whether the feed has a specific feed type.
		 *
		 * @param string $feed_type       Feed type (create/update).
		 * @param array  $form            Form object.
		 * @param bool   $current_feed_id Current feed ID.
		 *
		 * @return bool
		 */
		public function form_has_feed_type( $feed_type, $form, $current_feed_id = false ) {

			$feeds = $this->get_feeds( $form['id'] );

			foreach ( $feeds as $feed ) {

				// skip current feed as it may be changing feed type
				if ( $current_feed_id && $feed['id'] == $current_feed_id ) {
					continue;
				}

				// if there is no feed type specified, default to "create"
				if ( ! self::get_feed_type( $feed ) ) {
					$feed['meta']['feed_type'] = 'create';
				}

				if ( self::is_feed_type( $feed, $feed_type ) ) {
					return true;
				}

			}

			return false;
		}

		/**
		 * Whether the field is applicable to field_select.
		 *
		 * @param bool   $is_applicable_field Whether applicable.
		 * @param object $field               Field type.
		 *
		 * @return bool
		 */
		public function is_applicable_field_for_field_select( $is_applicable_field, $field ) {

			if ( rgobj( $field, 'multipleFiles' ) ) {
				$is_applicable_field = false;
			}

			return $is_applicable_field;

		}

		/**
		 * Whether the array of choices has gf_custom name/value.
		 *
		 * @param array[] $choices Array of choices.
		 *
		 * @return bool
		 */
		public static function has_gf_custom( $choices ) {

			foreach ( $choices as $choice ) {

				if ( 'gf_custom' === rgar( $choice, 'name' ) || 'gf_custom' === rgar( $choice, 'value' ) ) {
					return true;
				}

				// If choice has choices, check inside those choices..
				if ( rgar( $choice, 'choices' ) ) {
					return self::has_gf_custom( $choice['choices'] );
				}

			}

			return false;
		}

		/**
		 * Whether a form as a feed by slug.
		 *
		 * @param array  $form_id Form array object.
		 * @param string $slug    Addon Feed Slug.
		 *
		 * @return bool
		 */
		public static function has_feed_by_slug( $form_id, $slug ) {

			$feeds = self::get_all_feeds_by_form( $form_id, $slug );

			return ! empty( $feeds );

		}

		/**
		 * Determines whether an image has already been processed/uploaded/
		 *
		 * @param string $image Image temporary URL.
		 *
		 * @return bool
		 */
		protected function is_image_already_processed( $image ) {
			$args = array(
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'meta_value'             => $image,
//  'meta_query' => array(
//      'relation' => 'OR',
//      array(
//          'key'     => '_gf_cpt_content_image_temp_url',
//          'value'   => 'http://mamaskitchen.test/wp-content/uploads/gravity_forms/10-4bdc3c72036a276d4ceadcf3c5329eeb/2018/07/013_C9UayiA.jpg',
////            'compare' => '=',
//      ),
//      array(
//          'key'     => '_gf_cpt_post_image_temp_url',
//          'value'   => 'http://mamaskitchen.test/wp-content/uploads/gravity_forms/10-4bdc3c72036a276d4ceadcf3c5329eeb/2018/07/013_C9UayiA.jpg',
////            'compare' => 'LIKE',
//      ),
//  )
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'posts_per_page'         => 1,
			);

			$query = new \WP_Query( $args );
			$posts = $query->get_posts();

			if ( 0 < $query->post_count ) {
				return $posts[0]->ID;
			}

			return false;
		}

		/**
		 * Is form valid.
		 *
		 * @param array $form Form array object.
		 *
		 * @return bool
		 */
		public function is_form_valid( $form = array() ) {
			$form = $form ? $form : $this->form;
			foreach ( $form['fields'] as $field ) {
				if ( $field->failed_validation ) {
					return false;
				}
			}

			return true;
		}

		/**
		 * Determines whether the given feed is a specific type of feed.
		 *
		 * @param array $feed Feed array object.
		 *
		 * @return bool
		 */
		public function is_feed_this_type( $feed ) {
			return rgars( $feed, 'meta/feed_type' ) === $this->type;
		}

		/**
		 * Determines whether the given feed is a specific type of feed.
		 *
		 * @param array  $feed Feed array object.
		 * @param string $type Feed type.
		 *
		 * @return bool
		 */
		public static function is_feed_type( $feed, $type ) {
			return self::get_feed_type( $feed ) === $type;
		}

		/**
		 * Determines whether the given form is $this->form.
		 *
		 * @param array $form Form array object.
		 *
		 * @return bool
		 */
		protected function is_this_form( $form ) {
			if ( ! isset( $this->form['id'] ) ) {
				if ( ! $this->maybe_set_this_by_form( $form ) ) {
					return false;
				}
			}

			return ( $form['id'] === $this->form['id'] );
		}

		/**
		 * @param array $feed  Feed array object.
		 * @param array $form  Form array object.
		 * @param array $entry Entry array object.
		 *
		 * @return bool
		 */
		public function is_feed_condition_met( $feed, $form, $entry ) {

			$feed_meta            = rgar( $feed, 'meta' );
			$is_condition_enabled = rgar( $feed_meta, 'feed_condition_conditional_logic' ) == true;
			$logic                = rgars( $feed_meta, 'feed_condition_conditional_logic_object/conditionalLogic' );

			if ( ! $is_condition_enabled || empty( $logic ) ) {
				return true;
			}

			return \GFCommon::evaluate_conditional_logic( $logic, $form, $entry );
		}

		/** VALIDATION */

		/**
		 * Adds custom validation to gform_validation
		 *
		 * @see filter gform_validation
		 * @see GFFormsModel::get_current_lead()
		 * @see GFFormsModel::get_field()
		 * @see GFFormsModel::is_field_hidden()
		 * @see $this->get_meta_value
		 *
		 * @param array $validation_result The validation result passed from the gform_validation filter
		 *
		 * @return bool
		 */
		protected function should_abort_validation( $validation_result ) {

			$this->form      = $validation_result['form'];
			$is_last_page    = \GFFormDisplay::is_last_page( $this->form );
			$failed_honeypot = false;

			if ( $is_last_page && rgar( $this->form, 'enableHoneypot' ) ) {
				$honeypot_id     = \GFFormDisplay::get_max_field_id( $this->form ) + 1;
				$failed_honeypot = ! rgempty( "input_{$honeypot_id}" );
			}

			// Validation called by partial entries feature via the heartbeat API.
			$is_heartbeat = rgpost( 'action' ) == 'heartbeat';

			// If not last page OR failed honeypot OR heartbeat, return.
			if ( ! $validation_result['is_valid'] || ! $is_last_page || $failed_honeypot || $is_heartbeat ) {
				return true;
			}

			$entry   = $this->get_entry();
			$form_id = isset( $this->form['id'] ) ? $this->form['id'] : ( isset( $entry['form_id'] ) ? $entry['form_id'] : 0 );
			$feed    = $this->get_feed_by_form( $form_id, $entry );
			if ( ! $feed || ! $this->is_feed_this_type( $feed ) ) {
				return true;
			}

			// Pre-Process Feeds.
			$this->pre_process_feeds( array( $feed ), $this->get_entry(), $this->form );

			return false;
		}

		/**
		 * Adds error message to validation response.
		 *
		 * @param int    $field_id Field ID.
		 * @param string $message  Error message.
		 *
		 * @return array
		 */
		public function add_validation_error( $field_id, $message ) {

			foreach ( $this->form['fields'] as &$field ) {
				if ( $field->id == $field_id && ! $field->failed_validation ) {
					$field->failed_validation  = true;
					$field->validation_message = apply_filters( 'gform_cpt_validation_message', $message, $this->form );
					break;
				}
			}

			return $this->form;
		}

		/**
		 * Sends the validation.
		 *
		 * @param array $validation_result Validation array result.
		 *
		 * @return mixed
		 */
		function send_validation( $validation_result ) {

			$submitted_page = rgpost( sprintf( 'gform_source_page_number_%d', $this->form['id'] ) );
			/**
			 * Filters the form object, allowing for extended validation of user registration submissions
			 *
			 * @param array $form           The Form object
			 * @param array $feed           The Feed object
			 * @param int   $submitted_page The ID of the form page that was submitted
			 */
			$form                          = apply_filters( 'gform_cpt_validation', $this->form, $this->feed, $submitted_page );
			$validation_result['is_valid'] = $this->is_form_valid( $form );
			$validation_result['form']     = $form;

			return $validation_result;
		}

		/** HELPERS */

		public function get_current_feed_type() {

			$feed_id = $this->get_current_feed_id();

			if ( empty( $feed_id ) ) {
				return rgpost( '_gaddon_setting_feed_type' );
			}

			return Feed::get_feed_type( $this->get_feed( $feed_id ) );

		}

		/**
		 * Gets all feeds for a particular form.
		 *
		 * @param int    $form_id Form ID.
		 * @param string $slug    Addon slug.
		 *
		 * @return array Array of feeds.
		 */
		public static function get_all_feeds_by_form( $form_id, $slug = '' ) {
			global $wpdb;

			$form_filter = is_numeric( $form_id ) ? $wpdb->prepare( 'form_id=%d', absint( $form_id ) ) : '';

			$sql = "SELECT * FROM {$wpdb->prefix}gf_addon_feed WHERE {$form_filter} ORDER BY `feed_order`, `id` ASC";
			if ( '' !== $slug ) {
				$sql = $wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}gf_addon_feed WHERE addon_slug=%s AND {$form_filter} ORDER BY `feed_order`, `id` ASC", $slug
				);
			}

			$results = $wpdb->get_results( $sql, ARRAY_A );
			foreach ( $results as &$result ) {
				$result['meta'] = json_decode( $result['meta'], true );
			}

			return $results;

		}

		/**
		 * Determines whether the given feed is a specific type of feed.
		 *
		 * @param array $feed Feed array object.
		 *
		 * @return bool
		 */
		public static function get_feed_type( $feed ) {

			if ( empty( $feed ) ) {
				return rgpost( '_gaddon_setting_feed_type' );
			}

			return rgars( $feed, 'meta/feed_type' );

		}

		/**
		 * Return the active feed to be used when processing the current entry, evaluating conditional logic if configured.
		 *
		 * @param array       $form  The current form.
		 * @param array|false $entry The current entry.
		 *
		 * @return bool|array
		 */
		public function get_feed_by_form( $form, $entry ) {
			if ( $form ) {
				$feeds = $this->get_feeds( $form['id'] );

				foreach ( $feeds as $_feed ) {
					if ( $_feed['is_active'] && $this->is_feed_condition_met( $_feed, $form, $entry ) ) {
						return $_feed;
					}
				}
			}

			return false;
		}

		/**
		 * Log helper.
		 *
		 * @param string $method  Method string.
		 * @param string $message Message string.
		 */
		public function log( $method, $message ) {
			$this->log_debug( sprintf( '%s(): %s', $method, $message ) );
		}

		/** MISC */

		/**
		 * Initializing translations.
		 *
		 * @todo remove once min GF version reaches 2.0.7.
		 */
		public function load_text_domain() {
			\GFCommon::load_gf_text_domain( $this->_slug, 'gfaddon' );
		}
	}

}
