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
 * @package    \WPS\WP\Plugins\GravityForms
 * @author     Travis Smith <t@wpsmith.net>
 * @copyright  2015-2019 Travis Smith
 * @license    http://opensource.org/licenses/gpl-2.0.php GNU Public License v2
 * @link       https://github.com/wpsmith/WPS
 * @version    1.0.0
 * @since      0.1.0
 */

namespace WPS\WP\Plugins\GravityForms\AddOn;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\Actions' ) ) {

	/**
	 * Class FeedSettings.
	 *
	 * @package \WPS\WP\Plugins\GravityForms
	 */
	class Actions {

		protected $args = array();

		protected $_actions = array();
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
		public function __construct( AddOn $core ) {
			$this->core = $core;
		}

		protected function get_default_action() {
			return array(
				'label' => '',
				'value' => '',
				'icon'  => '',
				'class' => '',
			);
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
			$args   = wp_parse_args( $args, $this->get_default_args() );
			$action = wp_parse_args( $action, $this->get_default_action() );

			// Add action.
			$this->_actions[ $name ] = $action;
			$this->args[ $name ]     = $args;

			// Initialize action.
			$this->_init_action( $name, $this->_actions[ $name ] );

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
			if ( isset( $this->_actions[ $name ] ) ) {
				unset( $this->_actions[ $name ] );
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

			$form_id = rgget( 'id' );
			$form_id = $form_id ? $form_id : rgpost( 'id' );

			$form = \GFAPI::get_form( $form_id );

			$actions = $this->_actions;
			foreach ( $actions as $name => $action ) {
				unset( $actions[$name]['class'] );

				if (
					isset( $this->args[ $name ] ) &&
					$this->args[ $name ]['no_other_allowed'] &&
					$this->core->form_has_feed_type( $name, $form )
				) {
					unset( $action['class'] );
					return array( $name => $action );
				}
			}

			return $actions;

		}

		public function get( $name ) {
			if ( $this->exists( $name ) ) {
				return $this->actions[ $name ];
			}

			return new \WP_Error( 'action-does-not-exist', __( 'Action does not exist', 'gfaddon' ) );
		}

		public function run( $name, $feed, $entry, $form ) {
			$this->init_action( $name, $feed, $entry, $form );

			return $this->process_action( $name );
		}

		public function init_action( $name, $feed, $entry, $form ) {
			$this->actions[ $name ]->init( $feed, $entry, $form );
		}

		public function process_action( $name ) {
			return $this->actions[ $name ]->process();
		}

		public function exists( $name ) {
			if ( isset( $this->actions[ $name ] ) ) {
				return true;
			}

			if ( ! isset( $this->actions[ $name ] ) && isset( $this->_actions[ $name ] ) ) {
				$this->_init_action( $name, $this->_actions[ $name ] );

				return true;
			}

			return false;
		}

		protected function _init_action( $name, $action ) {
			$class = $action['class'];
			$this->actions[ $name ] = new $class( $this->core );
		}

		public function init() {
			foreach ( $this->_actions as $name => $action ) {
				$this->_init_action( $name, $action );
			}
		}
	}
}

