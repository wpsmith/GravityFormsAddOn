<?php
/**
 * Feed Settings Class.
 *
 * Assists in the administration of Custom Post Type Feeds within Gravity Forms.
 *
 * You may copy, distribute and modify the software as long as you track changes/dates in source files.
 * Any modifications to or software including (via compiler) GPL-licensed code must also be made
 * available under the GPL along with build & install instructions.
 *
 * @package    \WPS\Plugins\GravityForms\DynamicFields
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

if ( ! class_exists( '\WPS\Plugins\GravityForms\AddOn\Settings' ) ) {

	/**
	 * Class FeedSettings.
	 *
	 * @package \WPS\Plugins\GravityForms\DynamicFields
	 */
	class Settings extends \WPS\Core\Singleton {

		protected $settings = array();

		/**
		 * Core Addon Instance.
		 *
		 * @var \GFFeedAddOn
		 */
		protected $core;

		/**
		 * FeedSettings constructor.
		 *
		 * @param \GFFeedAddOn $core Core Feed Object.
		 */
		protected function __construct( \GFFeedAddOn $core ) {
			$this->core = $core;

			$this->register( 'feed_settings', $this->get_feed_settings() );
		}

		/**
		 * Registers a set of settings.
		 *
		 * @param string $name     Name (slug) of settings.
		 * @param array  $settings Array of settings.
		 */
		public function register( $name, array $settings ) {

			// Sanitize $name.
			$name = sanitize_title_with_dashes( $name );

			// Add settings.
			$this->settings[ $name ] = $settings;

		}

		/**
		 * Unregisters a set of settings.
		 *
		 * @param string $name Name (slug) of settings.
		 *
		 * @return bool Whether setting was deregistered or not.
		 */
		public function deregister( $name ) {

			// Sanitize $name.
			$name = sanitize_title_with_dashes( $name );

			// Add settings.
			if ( isset( $this->settings[ $name ] ) ) {
				unset( $this->settings[ $name ] );

				return true;
			}

			return false;
		}

		/**
		 * Gets all registered settings conditionally with save and conditional settings.
		 *
		 * @param bool|string $register_save       Whether to register the save settings.
		 * @param bool|string $register_additional Whether to register the conditional settings.
		 *
		 * @return array
		 */
		public function get_feed_settings_fields( $register_save = 'save', $register_additional = 'additional_settings' ) {

			$register_save       = is_string( $register_save ) ? sanitize_title_with_dashes( $register_save ) : false;
			$register_additional = is_string( $register_additional ) ? sanitize_title_with_dashes( $register_additional ) : false;

			if ( $register_additional ) {
				$this->register( $register_additional, $this->get_feed_settings() );
			}
			if ( $register_save ) {
				$this->register( $register_save, $this->get_feed_settings() );
			}

			return $this->settings;

		}

		/** SETTINGS */

		/**
		 * Feed settings for feed listing page.
		 *
		 * @return array
		 */
		public function get_feed_settings() {

			return array(
				'title'       => esc_html__( 'Feed Settings', 'gfaddon' ),
				'description' => '',
				'fields'      => array(
					array(
						'name'     => 'feed_name',
						'label'    => esc_html__( 'Name', 'gfaddon' ),
						'type'     => 'text',
						'required' => true,
						'class'    => 'medium',
						'tooltip'  => sprintf( '<h6>%s</h6> %s', esc_html__( 'Name', 'gfaddon' ), esc_html__( 'Enter a feed name to uniquely identify this setup.', 'gfaddon' ) )
					)
				)
			);

		}

		/**
		 * Gets conditional settings for feed.
		 *
		 * @return array
		 */
		public function additional_settings() {
			$is_update_feed = Feed::is_feed_type( $this->current_feed, 'update' );

			return array(
				'title'       => esc_html__( 'Additional Options', 'gfaddon' ),
				'description' => '',
				'dependency'  => array(
					'field'  => 'feed_type',
//					'values' => array( 'create', 'update' ),
					'values' => '_notempty_',
				),
				'fields'      => array(
					array(
						'name'           => 'post_condition',
						'label'          => $is_update_feed ? esc_html__( 'Update Condition', 'gfaddon' ) : esc_html__( 'Entry/Post Condition', 'gfaddon' ),
						'type'           => 'feed_condition',
						'checkbox_label' => esc_html__( 'Enable', 'gfaddon' ),
						'instructions'   => $is_update_feed ? esc_html__( 'Update post entry if', 'gfaddon' ) : esc_html__( 'Create post entry if', 'gfaddon' ),
						'tooltip'        => $is_update_feed ?
							sprintf( '<h6>%s</h6> %s', esc_html__( 'Update Condition', 'gfaddon' ), esc_html__( 'When the update condition is enabled, form submissions will only update the post entry when the condition is met. The data will always be populated into the form.', 'gfaddon' ) ) :
							sprintf( '<h6>%s</h6> %s', esc_html__( 'Registration Condition', 'gfaddon' ), esc_html__( 'When the registration condition is enabled, form submissions will only create the post entry when the condition is met.', 'gfaddon' ) )
					)
				)
			);
		}

		/**
		 * Gets the save settings for feed.
		 *
		 * @return array
		 */
		public function save_settings() {

			return array(
				'fields' => array(
					array(
						'type'    => 'save',
						'onclick' => '(function($, elem, event) {
						var $form       = $(elem).parents("form"),
							action      = $form.attr("action"),
							hashlessUrl = document.URL.replace(window.location.hash, "");

						if(!action && hashlessUrl !== document.URL) {
							event.preventDefault();
							$form.attr( "action", hashlessUrl );
							$(elem).click();
						};

					})(jQuery, this, event);'
					)
				)
			);

		}

		/**
		 * Validate required condition.
		 *
		 * Requires the field to not be empty IF condition is met.
		 *
		 * @param array  $field         Array of field settings.
		 * @param string $field_setting Value of the field setting.
		 */
		public function validate_required_condition( $field, $field_setting ) {

			$required_condition = rgars( $field, 'args/required_condition' );
			if ( empty( $required_condition ) ) {
				return;
			}

			if ( rgar( $field, 'required' ) && $this->core->setting_dependency_met( $required_condition ) && rgblank( $field_setting ) ) {
				$this->core->set_field_error( $field, rgar( $field, 'error_message' ) );
			}

		}

		/** CHOICES */

		/**
		 * Get appended choices with preserve option.
		 *
		 * @param string $field_name Field name.
		 * @param array  $choices    Array of choices.
		 *
		 * @return array
		 */
		public function get_appended_choices( $field_name, $choices ) {

			$appened_choices = array();
			$pre_choices     = $this->get_preserve_choices( $field_name );
			if ( ! empty( $pre_choices ) ) {
				$appened_choices[] = $pre_choices;
			}

			$appened_choices[] = array(
				'label'   => __( 'Additional Choices', 'gfaddon' ),
				'choices' => $choices,
			);

			return $appened_choices;
		}

		/**
		 * Gets the post types as choices.
		 *
		 * @param bool $default_option
		 *
		 * @return array
		 */
		public static function get_post_types_as_choices( $default_option = false ) {
			global $wp_post_types;

			$post_types = array_merge(
				wp_filter_object_list( $wp_post_types, array( '_builtin' => true, 'public' => true ), 'AND', 'label' ),
				wp_filter_object_list( $wp_post_types, array( '_builtin' => false ), 'AND', 'label' )
			);
			array_walk( $post_types, array(
				'\WPS\Plugins\GravityForms\AddOn\Utils',
				'make_choice'
			), 'post_types[%s]' );

			$choices = array_values( $post_types );

			if ( $default_option ) {
				if ( ! is_array( $default_option ) ) {
					$default_option = array(
						'label' => $default_option,
						'value' => ''
					);
				}
				array_unshift( $choices, $default_option );
			}

			return $choices;
		}

		/**
		 * Get field map choices for specific form.
		 *
		 * @since  unknown
		 * @access public
		 *
		 * @uses   GFCommon::get_label()
		 * @uses   GFFormsModel::get_entry_meta()
		 * @uses   GFFormsModel::get_form_meta()
		 * @uses   GF_Field::get_entry_inputs()
		 * @uses   GF_Field::get_form_editor_field_title()
		 * @uses   GF_Field::get_input_type()
		 *
		 * @param int          $form_id             Form ID to display fields for.
		 * @param array|string $field_type          Field types to only include as choices. Defaults to null.
		 * @param array|string $exclude_field_types Field types to exclude from choices. Defaults to null.
		 *
		 * @return array
		 */
		public static function get_field_map_choices( $form_id, $field_type = null, $exclude_field_types = null ) {

			$choices = \GFAddOn::get_field_map_choices( $form_id, $field_type );
			$form    = \GFAPI::get_form( $form_id );

			for ( $i = count( $choices ) - 1; $i >= 0; $i -- ) {

				if ( ! is_numeric( $choices[ $i ]['value'] ) ) {
					continue;
				}

				$field = \GFFormsModel::get_field( $form, $choices[ $i ]['value'] );
				if ( ! CPTFeed::get_instance()->is_applicable_field_for_field_select( true, $field ) ) {
					unset( $choices[ $i ] );
				}

			}

			return $choices;

		}

		/**
		 * Gets preserve choices.
		 *
		 * @param string $field_name Field name.
		 *
		 * @return array|null
		 */
		private function get_preserve_choices( $field_name ) {

			// Get the preserve choice.
			$pre_choices = array();
			$pre_choices = $this->maybe_add_preserve_choice( $pre_choices, $field_name );
			array_walk( $pre_choices, array( '\WPS\Plugins\GravityForms\AddOn\Utils', 'make_choice' ) );

			if ( ! empty( $pre_choices ) ) {
				return array(
					'label'   => esc_html__( 'Preserve', 'gfaddon' ),
					'choices' => array_values( $pre_choices ),
				);
			}

			return null;
		}

		/**
		 * Conditionally add whether to preserve original choice if feed type is update.
		 *
		 * @param array  $choices    Choices.
		 * @param string $field_name Field name.
		 *
		 * @return mixed
		 */
		private function maybe_add_preserve_choice( $choices, $field_name ) {

			$feed_type = $this->feed_type;

			if ( 'update' === $feed_type ) {
				$choices[ 'gcfpt_preserve_' . $field_name ] = esc_html__( '&mdash; Preserve current choice &mdash;', 'gfaddon' );
			}

			return $choices;

		}

		/**
		 * Returns (Create/Update) the available actions one can create in the feed.
		 *
		 * @return array
		 */
//		public function get_available_feed_actions() {
//
//			$form = \GFAPI::get_form( rgget( 'id' ) );
//
//			$create = array(
//				'label'   => esc_html__( 'Create Entry/Post', 'gfaddon' ),
//				'value'   => 'create',
//				'icon'    => 'fa-edit',
//				'tooltip' => sprintf(
//					'<h6>%s</h6> %s <em><strong>%s</strong></em> %s',
//					esc_html__( 'Create an Entry/Post', 'gfaddon' ),
//					esc_html__( 'Use this action to create a post/entry. You cannot create a feed to update', 'gfaddon' ),
//					esc_html__( 'and', 'gfaddon' ),
//					esc_html__( 'create a post.', 'gfaddon' )
//				),
//			);
//
//			// If create feed(s) exist(s), only allow additional create feeds.
//			if ( $this->core->form_has_feed_type( 'create', $form ) ) {
//				return array(
//					'create' => $create,
//				);
//			}
//
//			$update = array(
//				'label'   => esc_html__( 'Update Entry/Post', 'gfaddon' ),
//				'value'   => 'update',
//				'icon'    => 'fa-refresh',
//				'tooltip' => sprintf(
//					'<h6>%s</h6> %s <em><strong>%s</strong></em> %s',
//					esc_html__( 'Update Entry/Post', 'gfaddon' ),
//					esc_html__( 'Use this action to update an existing post/entry. You cannot create a feed to update', 'gfaddon' ),
//					esc_html__( 'and', 'gfaddon' ),
//					esc_html__( 'create a post.', 'gfaddon' )
//				),
//			);
//
//			// If update(s) feeds exist(s), only allow additional update feeds.
//			if ( $this->core->form_has_feed_type( 'update', $form ) ) {
//				return array(
//					'update' => $update,
//				);
//			}
//
//			$delete = array(
//				'label'   => esc_html__( 'Delete Entry/Post', 'gfaddon' ),
//				'value'   => 'delete',
//				'icon'    => 'fa-trash',
//				'tooltip' => sprintf(
//					'<h6>%s</h6> %s <em><strong>%s</strong></em> %s',
//					esc_html__( 'Delete Entry/Post', 'gfaddon' ),
//					esc_html__( 'Use this action to delete an existing post/entry. You cannot create a feed to delete', 'gfaddon' ),
//					esc_html__( 'and', 'gfaddon' ),
//					esc_html__( 'update or create a post.', 'gfaddon' )
//				),
//			);
//
//			// If delete(s) feeds exist(s), only allow additional delete feeds.
//			if ( $this->core->form_has_feed_type( 'delete', $form ) ) {
//				return array(
//					'delete' => $delete,
//				);
//			}
//
//			$dynamic = array(
//				'label'   => esc_html__( 'Make Form Dynamic', 'gfaddon' ),
//				'value'   => 'dynamic',
//				'icon'    => 'fa-star',
//				'tooltip' => sprintf(
//					'<h6>%s</h6> %s',
//					esc_html__( 'Make Form Dynamic', 'gfaddon' ),
//					esc_html__( 'Use this action to create a dynamic form based on post types.', 'gfaddon' )
//				),
//			);
//
//			// If create feed(s) exist(s), only allow additional create feeds.
//			if ( $this->core->form_has_feed_type( 'dynamic', $form ) ) {
//				return array(
//					'dynamic' => $dynamic,
//				);
//			}
//
//			// By default, allow all actions.
//			return array(
//				'create'  => $create,
//				'update'  => $update,
//				'delete'  => $delete,
//				'dynamic' => $dynamic,
//			);
//
//		}

		/**
		 * Magic getter for our object.
		 *
		 * @param  string    Property in object to retrieve.
		 *
		 * @throws \Exception Throws an exception if the field is invalid.
		 *
		 * @return mixed     Property requested.
		 */
		public function __get( $property ) {

			switch ( $property ) {
				case 'current_feed':
					return $this->core->get_current_feed();

				case 'feed_type':
					return $this->core->get_setting( 'feed_type' );

				default:
					if ( property_exists( $this, $property ) ) {
						return $this->{$property};
					}
					break;
			}

			if ( ! property_exists( $this, $property ) ) {
				throw new \Exception( 'Invalid ' . __CLASS__ . ' property: ' . $property );
			}

			return null;

		}
	}
}

