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

if ( ! class_exists( '\WPS\Plugins\GravityForms\AddOn\Actions' ) ) {

	/**
	 * Class FeedSettings.
	 *
	 * @package \WPS\Plugins\GravityForms\DynamicFields
	 */
	class Actions extends \WPS\Core\Singleton {

		protected $args = array();

		protected $actions = array();

		/**
		 * Core Addon Instance.
		 *
		 * @var AddOn
		 */
		protected $core;

		/**
		 * FeedSettings constructor.
		 *
		 * @param AddOn $core Core Feed Object.
		 */
		protected function __construct( AddOn $core ) {
			$this->core = $core;
		}

		protected function get_default_args() {

			return array(
				'can_duplicate'     => true,
				'can_have_multiple' => true,
				'no_other_allowed'  => true,
			);

		}

		/**
		 * Registers an action.
		 *
		 * @param string   $name   Name (slug) of an action.
		 * @param callable $action Action class.
		 * @param array    $args   Array of args.
		 */
		public function register( $name, $action, array $args = array() ) {

			// Sanitize $name.
			$name = sanitize_title_with_dashes( $name );

			// Merge args.
			$args = wp_parse_args( $args, $this->get_default_args() );

			// Add action.
			$this->actions[ $name ] = $action;
			$this->args[ $name ]    = $args;

		}

		/**
		 * Unregisters an action.
		 *
		 * @param string $name Name (slug) of an action.
		 *
		 * @return bool Whether the action was deregistered or not.
		 */
		public function deregister( $name ) {

			// Sanitize $name.
			$name = sanitize_title_with_dashes( $name );

			// Add settings.
			if ( isset( $this->actions[ $name ] ) ) {
				unset( $this->actions[ $name ] );
				unset( $this->args[ $name ] );

				return true;
			}

			return false;

		}

		/** ACTIONS */

		/**
		 * Returns (Create/Update) the available actions one can create in the feed.
		 *
		 * @return array
		 */
		public function get_available_feed_actions() {

			$form = \GFAPI::get_form( rgget( 'id' ) );

			foreach ( $this->actions as $name => $action ) {
				if (
					isset( $this->conditions[ $name ] ) &&
					$this->args[ $name ]['no_other_allowed'] &&
					$this->core->form_has_feed_type( $name, $form )
				) {
					return array( $name => $action );
				}
			}

			return $this->actions;

		}
	}
}

