<?php

/**
 * Gravity Forms Feed Import/Export Class.
 *
 * Assists in the export of Feeds within Gravity Forms.
 *
 * You may copy, distribute and modify the software as long as you track changes/dates in source files.
 * Any modifications to or software including (via compiler) GPL-licensed code must also be made
 * available under the GPL along with build & install instructions.
 *
 * @package    \WPS\Plugins\GravityForms\AddOn
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

if ( ! class_exists( '\WPS\Plugins\GravityForms\AddOn\ImportExport' ) ) {
	/**
	 * Class ImportExport.
	 *
	 * @package \WPS\Plugins\GravityForms\AddOn
	 */
	class ImportExport extends \WPS\Core\Singleton {

		protected $slug;
		protected $feed;

		/**
		 * ImportExport constructor.
		 *
		 * Adds export and import hooks.
		 *
		 * @param string $slug Feed slug.
		 */
		public function __construct( $slug, AddOn $feed ) {
			$this->slug = $slug;
			$this->feed = $feed;

			add_filter( 'gform_export_form', array( $this, 'modify_export_form' ) );
			add_filter( 'gform_form_update_meta', array( $this, 'modify_imported_form' ), 10, 3 );
		}

		/**
		 * @param array $form Form object array.
		 *
		 * @return array
		 */
		public function modify_export_form( $form ) {

			if ( ! $this->feed->has_feed( $form['id'] ) ) {
				return $form;
			}

			$feeds = $this->feed->get_feeds_by_slug( $this->slug, $form['id'] );

			$form['feeds']                = isset( $form['feeds'] ) ? $form['feeds'] : array();
			$form['feeds'][ $this->slug ] = isset( $form['feeds']['gfcptaddon'] ) ? $form['feeds'][ $this->slug ] : array();
			foreach ( $feeds as $f ) {
				$form['feeds'][ $this->slug ][] = rgar( $f, 'meta' );
			}

			return $form;
		}

		/**
		 * @param array  $meta      Maybe form data.
		 * @param int    $form_id   Form ID.
		 * @param string $meta_name Meta name being imported.
		 *
		 * @return mixed
		 */
		public function modify_imported_form( $meta, $form_id, $meta_name ) {

			if ( \GFForms::get_page() !== 'import_form' || ! $meta_name == 'display_meta' || ! isset( $meta['feeds'] ) || ! isset( $meta['feeds'][ $this->slug ] ) ) {
				return $meta;
			}

			$form = $meta;

			$feeds = $meta['feeds'][ $this->slug ];
			foreach ( $feeds as $f ) {
				$this->feed->save_feed_settings( 0, $meta['id'], $f );
			}

			return $form;
		}
	}
}