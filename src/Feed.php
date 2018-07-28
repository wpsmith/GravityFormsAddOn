<?php
/**
 * Main Custom Post Types Feed Class.
 *
 * Assists in the creation and management of Custom Post Type Feeds within Gravity Forms.
 *
 * You may copy, distribute and modify the software as long as you track changes/dates in source files.
 * Any modifications to or software including (via compiler) GPL-licensed code must also be made
 * available under the GPL along with build & install instructions.
 *
 * @package    WPS\Plugins\GravityForms\CPT
 * @author     Travis Smith <t@wpsmith.net>
 * @copyright  2015-2018 Travis Smith
 * @license    http://opensource.org/licenses/gpl-2.0.php GNU Public License v2
 * @link       https://github.com/wpsmith/WPS
 * @version    1.0.0
 * @since      0.1.0
 */

namespace WPS\Plugins\GravityForms\AddOn;

use WPS\Plugins\GravityForms\AddOn\Utils;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPS\Plugins\GravityForms\AddOn\Feed' ) ) {

	/**
	 * Class Feed.
	 *
	 * @package WPS\Plugins\GravityForms\CPT
	 */
	abstract class Feed extends \WPS\Core\Singleton {

		/**
		 * Feed type.
		 *
		 * @var string
		 */
		protected $type = '';

		/**
		 * Core Addon Instance.
		 *
		 * @var \WPS\Plugins\GravityForms\AddOn\AddOn
		 */
		protected $core;

		/**
		 * Post ID.
		 *
		 * @var int
		 */
		protected $post_id = 0;

		/**
		 * Gravity Forms Feed Array Object.
		 *
		 * @var array
		 */
		protected $feed;

		/**
		 * Gravity Forms Form Array Object.
		 *
		 * @var array
		 */
		protected $form;

		/**
		 * Gravity Forms Entry Array Object.
		 *
		 * @var array
		 */
		protected $entry;

		/**
		 * Meta key for Form ID.
		 */
		const META_FORM_KEY = '_gform-form-id';

		/**
		 * Meta key for Entry ID.
		 */
		const META_ENTRY_KEY = '_gform-entry-id';

		/**
		 * Feed constructor.
		 *
		 * @param AddOn $core      Core Feed registered object.
		 * @param array $feed      Feed array object.
		 * @param array $entry     Entry array object.
		 * @param array $form      Form array object.
		 * @param array $post_data Post array.
		 */
		public function __construct( CPTFeed $core, $form = array(), $feed = array(), $entry = array(), $post_data = array() ) {
			$this->core = $core;
			$this->init( $form, $feed, $entry, $post_data );
		}

		/**
		 * Main initializer for feed.
		 *
		 * @param array $feed      Feed array object.
		 * @param array $entry     Entry array object.
		 * @param array $form      Form array object.
		 * @param array $post_data Post array.
		 */
		public function init( $feed, $entry, $form, $post_data = array() ) {

			$this->form      = $form;
			$this->feed      = $feed;
			$this->entry     = $entry;
			$this->post_data = $post_data;

		}

		/**
		 * Must process this feed.
		 *
		 * @return mixed
		 */
		abstract public function process();

		/** PROPERTY SETTERS */

		/**
		 * Set the object properties.
		 *
		 * @param string $property Property in object.  Must be set in object.
		 * @param mixed  $value    Value of property.
		 *
		 * @return Feed  Returns Feed object, allows for chaining.
		 */
		public function set( $property, $value ) {

			if ( ! property_exists( $this, $property ) ) {
				return $this;
			}

			$this->$property = $value;

			return $this;
		}

		/**
		 * Map field array.
		 *
		 * @param array  $mapped_fields Mapped fields array.
		 * @param array  $post_data     Post data array.
		 * @param array  $meta_value    Meta value array.
		 * @param string $key           Key from post data.
		 */
		protected function map_field_array( &$mapped_fields, $post_data, $meta_value, $key ) {
			foreach ( (array) $meta_value as $value ) {
				if ( isset( $post_data[ $key ][ $value['key'] ] ) ) {
					$mapped_fields[ (string) $value['value'] ] = $post_data[ $key ][ $value['key'] ];
				}
			}
		}

		/** GETTER HELPERS */

		/**
		 * Gets core object.
		 *
		 * @return AddOn
		 */
		public function get_core() {
			if ( empty( $this->core ) ) {
				$this->core = CPTFeed::get_instance();
			}

			return $this->core;
		}

		/**
		 * Gets the current entry.
		 *
		 * @return array|bool|null
		 */
		public function get_entry() {
			if ( empty( $this->entry ) ) {
				$this->entry = \GFFormsModel::get_current_lead();
			}

			return $this->entry;
		}

		/**
		 * Gets the feed meta array.
		 *
		 * @return array
		 */
		public function get_feed_meta() {
			$feed = $this->get_feed();

			return rgar( $feed, 'meta' );
		}

		/**
		 * Gets the feed.
		 *
		 * @return array
		 */
		public function get_feed() {
			if ( empty( $this->feed ) ) {
				$this->feed = $this->get_core()->get_current_feed();
			}

			if ( false === $this->feed ) {
				$this->feed = $this->get_feed_by_form( $this->get_form(), $this->get_entry() );
			}

			return $this->feed;
		}

		/**
		 * Gets the form object.
		 *
		 * @return array|bool|null
		 */
		public function get_form() {
			if ( empty( $this->form ) ) {
				$this->form = $this->get_core()->get_current_form();
			}

			return $this->form;
		}

		/**
		 * Gets the form ID as a string.
		 *
		 * @return string
		 */
		public function get_form_idstr() {
			$form = $this->get_form();

			return (string) $form['id'];
		}

		/**
		 * Gets all feeds.
		 */
		public function get_feeds() {
			$this->get_core()->get_feeds();
		}

		/**
		 * Retrieves value from post to be populated as meta.
		 *
		 * @param mixed $meta_key The meta key as specified in the $feed.
		 * @param array $args     Meta or feed args.
		 *
		 * @return mixed The value matching the meta mapping for the given meta key or if not found, an empty string.
		 */
		public function get_meta_or_setting_value( $meta_key, $args = array() ) {

			$value = $this->get_meta_value( $meta_key );
			$value = $value ? $value : rgars( $this->get_feed(), 'meta/' . $meta_key );
			$value = is_callable( $value ) ? call_user_func( $value, $args ) : $value;
			$value = gf_apply_filters( array(
				'gform_cpt_get_meta_or_setting_value',
				$meta_key,
				$this->form['id']
			), $value, $this->form, $this->get_feed(), $this->get_entry(), $args );

			return $value;
		}

		/**
		 * Retrieves value from post to be populated as meta.
		 *
		 * @param mixed $meta_key The meta key as specified in the $feed.
		 * @param mixed $meta     The array of meta mappings stored in the $feed.
		 *
		 * @return mixed The value matching the meta mapping for the given meta key or if not found, an empty string.
		 */
		public function get_field_id_by_meta_key( $meta_key, $meta = array() ) {

			$meta = $meta ? $meta : $this->get_feed_meta();

			return rgar( $meta, $meta_key );
		}

		/**
		 * Retrieves value from post to be populated as meta.
		 *
		 * @param mixed $meta_key The meta key as specified in the $feed.
		 * @param mixed $meta     The array of meta mappings stored in the $feed.
		 *
		 * @return mixed The value matching the meta mapping for the given meta key or if not found, an empty string/
		 */
		public function get_meta_value( $meta_key, $meta = array() ) {

			$meta = $meta ? $meta : $this->get_feed_meta();

			$input_id = rgar( $meta, $meta_key );
			$value    = $this->get_mapped_field_value( $meta_key, $meta );


			// Post Category fields come with Category Name and ID in the value (i.e. Austin:51); only return the name
			$value = $this->maybe_get_category_name( \GFFormsModel::get_field( $this->get_form(), $input_id ), $value );

			// Add filters.
//          $value = apply_filters( 'gform_cpt_prepared_value', $value, $field, $input_id, $this->entry );
//          $value = apply_filters( 'gform_cpt_meta_value', $value, $meta_key, $this->feed, $this->form, $this->entry );

			if ( \GFAddOn::is_json( $value ) ) {
				$value = json_decode( $value );
			}

			return $value;
		}

		/**
		 * Gets the feed meta field value.
		 *
		 * @param string $field_name Field name.
		 * @param array  $feed       Feed array object.
		 *
		 * @return mixed|null|string
		 */
		protected function get_meta_setting_value( $field_name, $feed ) {
			return rgars( $feed, "meta/$field_name" );
		}

		protected function get_mapped_field_value( $key, $meta = array() ) {
			$meta = ! empty( $meta ) ? $meta : $this->get_feed();

			return $this->get_core()->get_mapped_field_value( $key, $this->get_form(), $this->get_entry(), $meta );
		}

		/**
		 * Converts an array of arrays into an associative key-value array.
		 *
		 * Takes array like:
		 *
		 *  array(
		 *      array(
		 *          'key'        => 'key1',
		 *          'value'      => 'value1',
		 *          'custom_key' => ''
		 *      ),
		 *      array(
		 *          'key'        => '',
		 *          'value'      => 'value2',
		 *          'custom_key' => 'my_custom_key'
		 *      )
		 *  )
		 *
		 * And converts it to:
		 *
		 * array(
		 *      'key1'          => 'value1',
		 *      'my_custom_key' => 'value2'
		 *  )
		 *
		 * @param array $dyn_meta Array of arrays.
		 *
		 * @return array Associative array of key-value pairs.
		 *
		 */
		public function prepare_dynamic_meta( $dyn_meta ) {

			$meta = array();

			if ( empty( $dyn_meta ) ) {
				return $meta;
			}

			foreach ( (array) $dyn_meta as $meta_item ) {
				list( $meta_key, $meta_value, $custom_meta_key ) = array_pad( array_values( $meta_item ), 3, false );
				if ( '' === $meta_key ) {
					continue;
				}
				$meta_key          = $custom_meta_key ? $custom_meta_key : $meta_key;
				$meta[ $meta_key ] = $meta_value;
			}

			return $meta;
		}

		/**
		 * Gets the term string from entry.
		 *
		 * @param string $field_name Field name to fetch from entry.
		 *
		 * @return array
		 */
		protected function get_term( $field_name ) {
			$field_value = $this->get_meta_value( $field_name );
			if ( '' !== $field_value ) {
				$field_value = $this->maybe_split_term_string( $field_value );
				$field_value = is_array( $field_value ) ? $field_value : array( $field_value );
			}

			return $field_value;
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
			$this->get_core()->pre_process_feeds( array( $feed ), $this->get_entry(), $this->form );

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

		/** CONDITIONALS */

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
		 * Determines whether the field is/has a particular type.
		 *
		 * @param string $type Field type.
		 *
		 * @return bool
		 */
		protected function has_field_type( $type ) {
			return sizeof( \GFFormsModel::get_fields_by_type( $this->form, array( $type ) ) ) > 0;
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

		/**
		 * Determines whether the specific file from an input is a new file.
		 *
		 * @param int    $form_id    Form ID.
		 * @param string $input_name Input name.
		 *
		 * @return bool
		 */
		public function is_new_file_upload( $form_id, $input_name ) {

			$file_info     = \GFFormsModel::get_temp_filename( $form_id, $input_name );
			$temp_filepath = \GFFormsModel::get_upload_path( $form_id ) . '/tmp/' . $file_info['temp_filename'];

			// check if file has already been uploaded by previous step
			if ( $file_info && file_exists( $temp_filepath ) ) {
				return true;
			} // check if file is uplaoded on current step
			elseif ( ! empty( $_FILES[ $input_name ]['name'] ) ) {
				return true;
			}

			return false;
		}

		/**
		 * Determines whether the specific file from an input is a prepoulated file.
		 *
		 * @param int    $form_id    Form ID.
		 * @param string $input_name Input name.
		 *
		 * @return bool
		 */
		public function is_prepopulated_file_upload( $form_id, $input_name ) {

			// prepopulated files will be stored in the 'gform_uploaded_files' field
			$uploaded_files = json_decode( rgpost( 'gform_uploaded_files' ), ARRAY_A );

			// file is prepopulated if it is present in the 'gform_uploaded_files' field AND is not a new file upload
			$in_uploaded_files = is_array( $uploaded_files ) && array_key_exists( $input_name, $uploaded_files ) && ! empty( $uploaded_files[ $input_name ] );
			$is_prepopulated   = $in_uploaded_files && ! $this->is_new_file_upload( $form_id, $input_name );

			return $is_prepopulated;
		}

		/** UTILITIES */

		/**
		 * Add gravity forms form ID and entry ID to post meta.
		 *
		 * @param int   $post_id  Post ID.
		 * @param array $form_id  Form array object.
		 * @param array $entry_id Entry array object.
		 */
		protected static function add_gf_post_meta( $post_id, $form_id, $entry_id ) {
			add_post_meta( $post_id, self::META_FORM_KEY, $form_id );
			add_post_meta( $post_id, self::META_ENTRY_KEY, $entry_id );
		}

		/**
		 * Get field ID by entry value.
		 *
		 * @param string $image Entry value.
		 *
		 * @return bool|int|string
		 */
		protected function get_field_id_by_value( $image ) {
			foreach ( $this->get_entry() as $k => $v ) {
				if ( is_string( $v ) ) {
					if ( $v === $image ||
					     0 < strpos( $v, addcslashes( $image, '/' ) ) ||
					     ( \GFAddOn::is_json( $v ) && in_array( $image, (array) json_decode( $v ), true ) )
					) {
						return $k;
					}
				} elseif ( is_array( $v ) ) {
					if ( in_array( $image, $v, true ) ) {
						return $k;
					}
				}
			}

			return false;
		}

		/**
		 * Gets the update feed from all feeds available for a specific form.
		 *
		 * @param int $form_id Form ID.
		 *
		 * @return bool
		 */
		public function get_update_feed( $form_id ) {

			$feeds = $this->get_core()->get_feeds( $form_id );

			foreach ( $feeds as $feed ) {
				if ( $feed['is_active'] && self::is_feed_type( $feed, 'update' ) ) {
					return $feed;
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
			$this->get_core()->log( $method, $message );
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
				$feeds = $this->get_core()->get_feeds( $form['id'] );

				foreach ( $feeds as $_feed ) {
					if ( $_feed['is_active'] && $this->is_feed_condition_met( $_feed, $form, $entry ) ) {
						return $_feed;
					}
				}
			}

			return false;
		}
	}
}
