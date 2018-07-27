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

		protected $order = array();
		protected $settings = array();
		protected $feed_actions = array();

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
		}

		public function register_feed_settings( $actions = array() ) {
			$this->register( 'feed_settings', $this->get_feed_settings( $actions ) );

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
			$this->order[]           = $name;

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

				foreach ( $this->order as $i => $setting ) {
					if ( $name === $setting ) {
						unset ( $this->settings[ $i ] );
					}
				}

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
				$this->register( $register_additional, $this->get_additional_settings() );
			}
			if ( $register_save ) {
				$this->register( $register_save, $this->get_save_settings() );
			}

			$settings = array();
			foreach ( $this->order as $setting ) {
				$settings[] = $this->settings[ $setting ];
			}

			return $settings;

		}

		/** SETTINGS */

		/**
		 * Feed settings for feed listing page.
		 *
		 * @param array $feed_actions Array of feed available actions.
		 *
		 * @return array
		 */
		public function get_feed_settings( $feed_actions = array() ) {

			$feed_actions = empty( $feed_actions ) ? $this->feed_actions : $feed_actions;
			$feed_name    = array(
				'name'     => 'feed_name',
				'label'    => esc_html__( 'Name', 'gfcptaddon' ),
				'type'     => 'text',
				'required' => true,
				'class'    => 'medium',
				'tooltip'  => sprintf( '<h6>%s</h6> %s', esc_html__( 'Name', 'gfcptaddon' ), esc_html__( 'Enter a feed name to uniquely identify this setup.', 'gfcptaddon' ) )
			);

			$fields = array( $feed_name );

			if ( ! empty( $feed_actions ) ) {
				$fields[] = array(
					'name'     => 'feed_type',
					'label'    => esc_html__( 'Action', 'gfcptaddon' ),
					'type'     => 'radio',
					'required' => true,
					'tooltip'  => sprintf(
						'<h6>%s</h6> %s <p><em>%s</em></p>',
						esc_html__( 'Action', 'gfcptaddon' ),
						esc_html__( 'Select the type of feed you would like to create. "Create" feeds will create a new entry/post. "Update" feeds will update entries/posts.', 'gfcptaddon' ),
						__( 'A form can have multiple "Create" feeds <strong>or</strong> a single "Update" feed. A form cannot have both a "Create" feed and an "Update" feed.', 'gfcptaddon' )
					),
					'choices'  => $feed_actions,
					'onchange' => 'jQuery( this ).parents( "form" ).submit();'
				);

			}

			return array(
				'title'       => esc_html__( 'Feed Settings', 'gfcptaddon' ),
				'description' => '',
				'fields'      => $fields,
			);

		}

		/**
		 * Gets conditional settings for feed.
		 *
		 * @return array
		 */
		public function get_additional_settings() {

			return array(
				'title'       => esc_html__( 'Additional Options', 'gfaddon' ),
				'description' => '',
				'dependency'  => array(
					'field'  => 'feed_type',
					'values' => '_notempty_',
				),
				'fields'      => array(
					array(
						'name'           => 'feed_condition',
						'label'          => esc_html__( 'Condition', 'gfaddon' ),
						'type'           => 'feed_condition',
						'checkbox_label' => esc_html__( 'Enable', 'gfaddon' ),
						'instructions'   => esc_html__( 'The condition that must be met for this feed to be executed.', 'gfaddon' ),
						'tooltip'        =>
							sprintf( '<h6>%s</h6> %s', esc_html__( 'Condition', 'gfaddon' ), esc_html__( 'When the condition is enabled, form submissions will only run when the condition is met. The data will always be populated into the form.', 'gfaddon' ) ),
					)
				)
			);

		}

		/**
		 * Gets the save settings for feed.
		 *
		 * @return array
		 */
		public function get_save_settings() {

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
				if ( ! self::get_instance()->core->is_applicable_field_for_field_select( true, $field ) ) {
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

