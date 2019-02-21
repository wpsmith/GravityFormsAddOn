<?php
/**
 * Custom Post Types Utils Class.
 *
 * Helper and utility methods for Custom Post Type Feeds.
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

if ( ! class_exists( __NAMESPACE__ . '\Utils' ) ) {
	/**
	 * Class Utils.
	 *
	 * @package \WPS\WP\Plugins\GravityForms
	 */
	class Utils extends \WPS\Core\Singleton {

		/**
		 * Check if Gravity Forms is installed.
		 */
		public static function is_gravityforms_installed() {
			return ( class_exists( 'GFForms' ) || class_exists( 'RGForms' ) );
		}

		/**
		 * Check if the installed version of Gravity Forms is supported.
		 *
		 * @param $min_version
		 *
		 * @return bool
		 */
		public static function is_gravityforms_supported( $min_version ) {
			return self::check_gravityforms_version( $min_version, '>=' );
		}

		/**
		 * Do a Gravity Forms version compare.
		 *
		 * @param string $version  Gravity Forms version.
		 * @param string $operator Operation to check/compare version.
		 *
		 * @return bool
		 */
		public static function check_gravityforms_version( $version, $operator ) {
			if ( class_exists( 'GFCommon' ) ) {
				return version_compare( \GFCommon::$version, $version, $operator );
			}

			return false;
		}

		/**
		 * Tests if a text starts with an given string.
		 *
		 * @param string $haystack The string being checked.
		 * @param string $needle   The string being checked for.
		 *
		 * @return bool
		 */
		public static function starts_with( $haystack, $needle ) {
			return strpos( $haystack, $needle ) === 0;
		}

		/**
		 * Determines whether a substring exists in another string.
		 *
		 * @param string $haystack The string being checked.
		 * @param string $needle   The string being checked for.
		 *
		 * @return bool
		 */
		public static function str_contains( $haystack, $needle ) {
			if ( empty( $haystack ) || empty( $needle ) ) {
				return false;
			}

			$pos = strpos( strtolower( $haystack ), strtolower( $needle ) );

			if ( false === $pos ) {
				return false;
			}

			return true;
		}

		/**
		 * Translates a camel case string into a string with underscores (e.g. firstName -&gt; first_name).
		 *
		 * @param string $str String in camel case format.
		 *
		 * @return string $str Translated into underscore format.
		 */
		public static function from_camel_case( $str ) {
			$str[0] = strtolower( $str[0] );

			return preg_replace_callback( '/([A-Z])/', function ( $c ) {
				return '_' . strtolower( $c[1] );
			}, $str );
		}

		/**
		 * Translates a string with underscores into camel case (e.g. first_name -&gt; firstName)
		 *
		 * @param string $str                   String in underscore format.
		 * @param bool   $capitalise_first_char If true, capitalise the first char in $str.
		 *
		 * @return string $str translated into camel caps.
		 */
		public static function to_camel_case( $str, $capitalise_first_char = false ) {
			if ( $capitalise_first_char ) {
				$str[0] = strtoupper( $str[0] );
			}

			return preg_replace_callback( '/_([a-z])/', function ( $c ) {
				return strtoupper( $c[1] );
			}, $str );
		}

		/**
		 * Maks a choice from a key-value/label-value pair.
		 *
		 * @param array $value Choice label.
		 * @param string $key Value of the choice.
		 * @param string $pattern Name of the choice.
		 */
		public static function make_choice( &$value, $key, $pattern = '%s' ) {
			$value = array(
				'label' => esc_attr( $value ),
				'value' => esc_attr( $key ),
				'name'  => esc_attr( sprintf( $pattern, $key ) ),
			);
		}

		/**
		 * Cached version of term_exists()
		 *
		 * Term exists calls can pile up on a single pageload.
		 * This function adds a layer of caching to prevent lots of queries.
		 *
		 * @param int|string $term     The term to check can be id, slug or name.
		 * @param string     $taxonomy The taxonomy name to use
		 * @param int        $parent   Optional. ID of parent term under which to confine the exists search.
		 *
		 * @return mixed Returns null if the term does not exist. Returns the term ID
		 *               if no taxonomy is specified and the term ID exists. Returns
		 *               an array of the term ID and the term taxonomy ID the taxonomy
		 *               is specified and the pairing exists.
		 */
		public static function term_exists( $term, $taxonomy = '', $parent = null ) {
			// If $parent is not null, let's skip the cache.
			if ( null !== $parent ) {
				return term_exists( $term, $taxonomy, $parent );
			}
			if ( ! empty( $taxonomy ) ) {
				$cache_key = $term . '|' . $taxonomy;
			} else {
				$cache_key = $term;
			}
			$cache_value = wp_cache_get( $cache_key, 'term_exists' );
			// term_exists frequently returns null, but (happily) never false
			if ( false === $cache_value ) {
				$term_exists = term_exists( $term, $taxonomy );
				wp_cache_set( $cache_key, $term_exists, 'term_exists', 3 * HOUR_IN_SECONDS );
			} else {
				$term_exists = $cache_value;
			}
			if ( is_wp_error( $term_exists ) ) {
				$term_exists = null;
			}

			return $term_exists;
		}

		/**
		 * Emulate PHP native ctype_digit() function for when the ctype extension would be disabled *sigh*.
		 * Only emulates the behaviour for when the input is a string, does not handle integer input as ascii value.
		 *
		 * @param string $string String input to validate.
		 *
		 * @return bool
		 */
		public static function ctype_digit( $string ) {
			if ( extension_loaded( 'ctype' ) || ! function_exists( 'ctype_digit' ) ) {
				return ctype_digit( $string );
			}

			$return = false;
			if ( ( is_string( $string ) && $string !== '' ) && preg_match( '`^\d+$`', $string ) === 1 ) {
				$return = true;
			}

			return $return;
		}

		/**
		 * Determines whether the given array is an associative array.
		 *
		 * @param array $arr Array being evaluated.
		 *
		 * @return bool
		 */
		public static function is_assoc( array $arr ) {
			if ( array() === $arr ) {
				return false;
			}

			return array_keys( $arr ) !== range( 0, count( $arr ) - 1 );
		}

		/**
		 * Converts integer/string into absolute integer.
		 *
		 * @param string|int $maybeint Integer/String to be made into int.
		 *
		 * @return float|int
		 */
		public static function absint( $maybeint ) {
			if ( is_int( $maybeint ) ) {
				return $maybeint;
			}

			if ( is_string( $maybeint ) && is_numeric( $maybeint ) && ctype_digit( $maybeint ) ) {
				return abs( intval( $maybeint ) );
			}

			return 0;
		}
	}
}
