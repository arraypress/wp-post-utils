<?php
/**
 * Posts Utility Class
 *
 * Provides utility functions for working with multiple WordPress posts in bulk,
 * including batch operations for creation, retrieval, status management, author
 * changes, searching, and content filtering across post collections.
 *
 * @package ArrayPress\PostUtils
 * @since   1.0.0
 * @author  ArrayPress
 * @license GPL-2.0-or-later
 */

declare( strict_types=1 );

namespace ArrayPress\PostUtils;

/**
 * Posts Class
 *
 * Operations for working with multiple WordPress posts.
 */
class Posts {

	// ========================================
	// Core Retrieval
	// ========================================

	/**
	 * Get multiple posts by IDs.
	 *
	 * @param array        $post_ids  Array of post IDs.
	 * @param string|array $post_type Optional. Post type(s). Default 'any'.
	 * @param array        $args      Optional. Additional arguments.
	 *
	 * @return array Array of post objects.
	 */
	public static function get( array $post_ids, $post_type = 'any', array $args = [] ): array {
		$post_ids = array_filter( array_map( 'intval', $post_ids ) );

		if ( empty( $post_ids ) ) {
			return [];
		}

		$default_args = [
			'post_type'      => $post_type,
			'include'        => $post_ids,
			'posts_per_page' => - 1,
			'orderby'        => 'post__in'
		];

		$args = wp_parse_args( $args, $default_args );

		return get_posts( $args );
	}

	/**
	 * Get posts by identifiers.
	 *
	 * @param array        $identifiers    Array of post identifiers.
	 * @param string|array $post_type      Optional. Post type(s). Default 'any'.
	 * @param bool         $return_objects Whether to return objects or IDs.
	 *
	 * @return array Array of post IDs or objects.
	 */
	public static function get_by_identifiers( array $identifiers, $post_type = 'any', bool $return_objects = false ): array {
		if ( empty( $identifiers ) ) {
			return [];
		}

		$unique_posts = [];

		foreach ( $identifiers as $identifier ) {
			if ( empty( $identifier ) ) {
				continue;
			}

			$post = Post::get_by_identifier( $identifier, $post_type );
			if ( $post ) {
				$unique_posts[ $post->ID ] = $post;
			}
		}

		if ( $return_objects ) {
			return array_values( $unique_posts );
		}

		return array_map( 'intval', array_keys( $unique_posts ) );
	}

	/**
	 * Get posts by author.
	 *
	 * @param int   $author_id Author ID.
	 * @param array $args      Optional arguments.
	 *
	 * @return array Array of post objects.
	 */
	public static function get_by_author( int $author_id, array $args = [] ): array {
		$defaults = [
			'author'         => $author_id,
			'posts_per_page' => - 1,
			'post_status'    => 'publish'
		];

		$args = wp_parse_args( $args, $defaults );

		return get_posts( $args );
	}

	/**
	 * Get recent posts.
	 *
	 * @param int   $number Number of posts.
	 * @param array $args   Optional arguments.
	 *
	 * @return array Array of post objects.
	 */
	public static function get_recent( int $number = 5, array $args = [] ): array {
		$defaults = [
			'posts_per_page' => $number,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'post_status'    => 'publish'
		];

		$args = wp_parse_args( $args, $defaults );

		return get_posts( $args );
	}

	/**
	 * Get posts by date range.
	 *
	 * @param string $start_date Start date (Y-m-d format).
	 * @param string $end_date   End date (Y-m-d format).
	 * @param array  $args       Optional arguments.
	 *
	 * @return array Array of post objects.
	 */
	public static function get_by_date_range( string $start_date, string $end_date, array $args = [] ): array {
		$defaults = [
			'date_query'     => [
				[
					'after'     => $start_date,
					'before'    => $end_date,
					'inclusive' => true,
				],
			],
			'posts_per_page' => - 1,
			'post_status'    => 'publish'
		];

		$args = wp_parse_args( $args, $defaults );

		return get_posts( $args );
	}

	// ========================================
	// Creation
	// ========================================

	/**
	 * Create multiple posts.
	 *
	 * @param array $posts    Array of post data arrays.
	 * @param array $defaults Default arguments for all posts.
	 *
	 * @return array Array of created post objects and errors.
	 */
	public static function create( array $posts, array $defaults = [] ): array {
		$result = [
			'created' => [],
			'errors'  => []
		];

		if ( empty( $posts ) ) {
			return $result;
		}

		foreach ( $posts as $post_data ) {
			if ( ! is_array( $post_data ) || empty( $post_data['post_title'] ) ) {
				$result['errors'][] = 'Invalid post data format - post_title required';
				continue;
			}

			$title   = $post_data['post_title'];
			$content = $post_data['post_content'] ?? '';
			$args    = array_merge( $defaults, array_diff_key( $post_data, [
				'post_title'   => '',
				'post_content' => ''
			] ) );

			$post = Post::create( $title, $content, $args );

			if ( $post ) {
				$result['created'][] = $post;
			} else {
				$result['errors'][] = "Failed to create post: {$title}";
			}
		}

		return $result;
	}

	/**
	 * Create posts if they don't exist.
	 *
	 * @param array $posts    Array of post data arrays.
	 * @param array $defaults Default arguments.
	 *
	 * @return array Array with 'created', 'existing', and 'errors' keys.
	 */
	public static function create_if_not_exists( array $posts, array $defaults = [] ): array {
		$result = [
			'created'  => [],
			'existing' => [],
			'errors'   => []
		];

		foreach ( $posts as $post_data ) {
			if ( ! is_array( $post_data ) || empty( $post_data['post_title'] ) ) {
				$result['errors'][] = 'Invalid post data format - post_title required';
				continue;
			}

			$title   = $post_data['post_title'];
			$content = $post_data['post_content'] ?? '';
			$args    = array_merge( $defaults, array_diff_key( $post_data, [
				'post_title'   => '',
				'post_content' => ''
			] ) );

			$post_type   = $args['post_type'] ?? 'post';
			$post_status = $args['post_status'] ?? 'publish';

			// Check if post exists
			$existing_post = Post::get_by_title( $title, $post_type, $post_status );

			if ( $existing_post ) {
				$result['existing'][] = $existing_post;
			} else {
				$post = Post::create( $title, $content, $args );

				if ( $post ) {
					$result['created'][] = $post;
				} else {
					$result['errors'][] = "Failed to create post: {$title}";
				}
			}
		}

		return $result;
	}

	// ========================================
	// Search & Options
	// ========================================

	/**
	 * Search posts.
	 *
	 * @param string $search     Search term.
	 * @param array  $post_types Post types to search.
	 * @param array  $args       Additional arguments.
	 *
	 * @return array Array of post objects.
	 */
	public static function search( string $search, array $post_types = [ 'post' ], array $args = [] ): array {
		if ( empty( $search ) ) {
			return [];
		}

		$defaults = [
			's'              => $search,
			'post_type'      => $post_types,
			'posts_per_page' => 20,
			'post_status'    => 'publish'
		];

		$args = wp_parse_args( $args, $defaults );

		return get_posts( $args );
	}

	/**
	 * Search posts and return in value/label format.
	 *
	 * @param string $search     Search term.
	 * @param array  $post_types Post types to search.
	 * @param array  $args       Additional arguments.
	 *
	 * @return array Array of ['value' => id, 'label' => title] items.
	 */
	public static function search_options( string $search, array $post_types = [ 'post' ], array $args = [] ): array {
		$posts = self::search( $search, $post_types, $args );

		$options = [];
		foreach ( $posts as $post ) {
			$options[] = [
				'value' => $post->ID,
				'label' => $post->post_title,
			];
		}

		return $options;
	}

	/**
	 * Get post options for form fields.
	 *
	 * @param string|array $post_type Post type(s).
	 * @param array        $args      Optional arguments.
	 *
	 * @return array Array of ['id' => 'title'] options.
	 */
	public static function get_options( $post_type = 'post', array $args = [] ): array {
		$defaults = [
			'post_type'      => $post_type,
			'posts_per_page' => - 1,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC'
		];

		$args  = wp_parse_args( $args, $defaults );
		$posts = get_posts( $args );

		if ( empty( $posts ) ) {
			return [];
		}

		$options = [];
		foreach ( $posts as $post ) {
			$options[ $post->ID ] = esc_html( $post->post_title );
		}

		return $options;
	}

	// ========================================
	// Bulk Actions
	// ========================================

	/**
	 * Change status for multiple posts.
	 *
	 * @param array  $post_ids   Array of post IDs.
	 * @param string $new_status New status.
	 *
	 * @return array Array of results with post IDs as keys and boolean success as values.
	 */
	public static function change_status( array $post_ids, string $new_status ): array {
		$results = [];

		foreach ( $post_ids as $post_id ) {
			$results[ $post_id ] = Post::update_status( $post_id, $new_status );
		}

		return $results;
	}

	/**
	 * Change author for multiple posts.
	 *
	 * @param array $post_ids   Array of post IDs.
	 * @param int   $new_author New author ID.
	 *
	 * @return array Array of results with post IDs as keys and boolean success as values.
	 */
	public static function change_author( array $post_ids, int $new_author ): array {
		$results = [];

		foreach ( $post_ids as $post_id ) {
			$result = wp_update_post( [
				'ID'          => $post_id,
				'post_author' => $new_author
			] );

			$results[ $post_id ] = ! is_wp_error( $result ) && $result !== 0;
		}

		return $results;
	}

	/**
	 * Trash multiple posts.
	 *
	 * @param array $post_ids Array of post IDs.
	 *
	 * @return array Array of results with post IDs as keys and boolean success as values.
	 */
	public static function trash( array $post_ids ): array {
		$results = [];

		foreach ( $post_ids as $post_id ) {
			$results[ $post_id ] = Post::trash( $post_id );
		}

		return $results;
	}

	/**
	 * Delete multiple posts.
	 *
	 * @param array $post_ids Array of post IDs.
	 * @param bool  $force    Whether to bypass trash.
	 *
	 * @return array Array of results with post IDs as keys and boolean success as values.
	 */
	public static function delete( array $post_ids, bool $force = false ): array {
		$results = [];

		foreach ( $post_ids as $post_id ) {
			$results[ $post_id ] = Post::delete( $post_id, $force );
		}

		return $results;
	}

	// ========================================
	// Counting & Statistics
	// ========================================

	/**
	 * Get the total count of posts by type and status.
	 *
	 * @param string       $post_type Optional. Post type. Default 'post'.
	 * @param string|array $status    Optional. Post status or array of statuses. Default 'publish'.
	 *
	 * @return int The total count of posts.
	 */
	public static function get_count( string $post_type = 'post', $status = 'publish' ): int {
		$counts = wp_count_posts( $post_type );

		if ( $status === 'any' ) {
			return array_sum( (array) $counts );
		}

		if ( is_string( $status ) ) {
			return (int) ( $counts->$status ?? 0 );
		}

		if ( is_array( $status ) ) {
			$total = 0;
			foreach ( $status as $stat ) {
				$total += (int) ( $counts->$stat ?? 0 );
			}

			return $total;
		}

		return 0;
	}

	// ========================================
	// Validation & Sanitization
	// ========================================

	/**
	 * Sanitize and validate a list of post IDs.
	 *
	 * @param array|string|int $posts     Array of post IDs or single post ID.
	 * @param string|array     $post_type Optional. Post type(s) to validate against. Default 'any'.
	 * @param bool             $validate  Optional. Whether to validate posts exist. Default true.
	 *
	 * @return array Array of sanitized post IDs.
	 */
	public static function sanitize( $posts, $post_type = 'any', bool $validate = true ): array {
		// Handle various input types
		if ( is_string( $posts ) || is_int( $posts ) ) {
			$posts = [ $posts ];
		}

		if ( ! is_array( $posts ) ) {
			return [];
		}

		$valid_posts = [];

		foreach ( $posts as $post ) {
			$post_id = absint( $post );

			if ( empty( $post_id ) ) {
				continue;
			}

			// Skip validation if not required
			if ( ! $validate ) {
				$valid_posts[] = $post_id;
				continue;
			}

			// Validate post exists and matches type
			$post_obj = get_post( $post_id );
			if ( ! $post_obj ) {
				continue;
			}

			// Check post type if specified
			if ( $post_type !== 'any' ) {
				$current_type = $post_obj->post_type;

				if ( is_array( $post_type ) ) {
					if ( ! in_array( $current_type, $post_type, true ) ) {
						continue;
					}
				} elseif ( $current_type !== $post_type ) {
					continue;
				}
			}

			$valid_posts[] = $post_id;
		}

		return array_unique( $valid_posts );
	}

	// ========================================
	// Statistics & Analysis
	// ========================================

	/**
	 * Calculate the average age of posts based on their publishing dates.
	 *
	 * @param array $post_ids Array of post IDs.
	 *
	 * @return int|null Average age in seconds, or null if no valid dates found.
	 */
	public static function calculate_average_age( array $post_ids ): ?int {
		if ( empty( $post_ids ) ) {
			return null;
		}

		$total_age    = 0;
		$valid_items  = 0;
		$current_time = current_time( 'timestamp' );

		foreach ( $post_ids as $post_id ) {
			$post_date = get_post_field( 'post_date', $post_id );

			if ( empty( $post_date ) ) {
				continue;
			}

			$post_timestamp = strtotime( $post_date );

			if ( $post_timestamp === false ) {
				continue;
			}

			$total_age += ( $current_time - $post_timestamp );
			$valid_items ++;
		}

		if ( $valid_items === 0 ) {
			return null;
		}

		return (int) ( $total_age / $valid_items );
	}

	/**
	 * Calculate the average age of posts in days.
	 *
	 * @param array $post_ids Array of post IDs.
	 *
	 * @return int|null Average age in days, or null if no valid dates found.
	 */
	public static function calculate_average_age_days( array $post_ids ): ?int {
		$average_seconds = self::calculate_average_age( $post_ids );

		return $average_seconds !== null ? (int) ( $average_seconds / DAY_IN_SECONDS ) : null;
	}

	/**
	 * Calculate the average age of posts in human-readable format.
	 *
	 * @param array $post_ids Array of post IDs.
	 *
	 * @return string|null Human-readable average age, or null if no valid dates found.
	 */
	public static function calculate_average_age_human( array $post_ids ): ?string {
		$average_seconds = self::calculate_average_age( $post_ids );

		if ( $average_seconds === null ) {
			return null;
		}

		return human_time_diff( current_time( 'timestamp' ) - $average_seconds, current_time( 'timestamp' ) );
	}

	// ========================================
	// Term Analysis & Collection Operations
	// ========================================

	/**
	 * Get unique terms from a collection of posts.
	 *
	 * @param array  $post_ids Array of post IDs.
	 * @param string $taxonomy The taxonomy to retrieve terms from.
	 * @param bool   $ids_only Whether to return only term IDs instead of objects.
	 *
	 * @return array Array of term objects or term IDs.
	 */
	public static function get_terms_from_taxonomy( array $post_ids, string $taxonomy, bool $ids_only = false ): array {
		if ( ! taxonomy_exists( $taxonomy ) || empty( $post_ids ) ) {
			return [];
		}

		$terms = [];
		foreach ( $post_ids as $post_id ) {
			$post_terms = wp_get_post_terms( $post_id, $taxonomy );
			if ( $post_terms && ! is_wp_error( $post_terms ) ) {
				foreach ( $post_terms as $term ) {
					$terms[ $term->term_id ] = $term;
				}
			}
		}

		return $ids_only ? array_map( 'absint', wp_list_pluck( array_values( $terms ), 'term_id' ) ) : array_values( $terms );
	}

	/**
	 * Check if taxonomy has a specific term across post collection.
	 *
	 * @param array  $post_ids Array of post IDs.
	 * @param mixed  $term     The term to check for (ID, name, or slug).
	 * @param string $taxonomy The taxonomy to check the term in.
	 *
	 * @return bool True if the term is found, false otherwise.
	 */
	public static function taxonomy_has_term( array $post_ids, $term, string $taxonomy ): bool {
		$terms = self::get_terms_from_taxonomy( $post_ids, $taxonomy );

		foreach ( $terms as $found_term ) {
			if ( $found_term->term_id == $term ||
			     $found_term->slug === $term ||
			     $found_term->name === $term ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if taxonomy has all or any of the specified terms across post collection.
	 *
	 * @param array  $post_ids  Array of post IDs.
	 * @param array  $terms     An array of terms to check for (IDs, names, or slugs).
	 * @param string $taxonomy  The taxonomy to check the terms in.
	 * @param bool   $match_all Whether all terms must be present (true) or any term (false).
	 *
	 * @return bool True if the specified terms are found according to match_all parameter.
	 */
	public static function taxonomy_has_terms( array $post_ids, array $terms, string $taxonomy, bool $match_all = true ): bool {
		$found_count = 0;

		foreach ( $terms as $term ) {
			if ( self::taxonomy_has_term( $post_ids, $term, $taxonomy ) ) {
				$found_count ++;
				if ( ! $match_all ) {
					return true;
				}
			} elseif ( $match_all ) {
				return false;
			}
		}

		return $match_all && $found_count === count( $terms );
	}

	// ========================================
	// Author Operations
	// ========================================

	/**
	 * Get unique author IDs from a collection of posts.
	 *
	 * @param array $post_ids Array of post IDs.
	 *
	 * @return array Array of unique author IDs.
	 */
	public static function get_author_ids( array $post_ids ): array {
		if ( empty( $post_ids ) ) {
			return [];
		}

		$author_ids = [];
		foreach ( $post_ids as $post_id ) {
			$author_id = get_post_field( 'post_author', $post_id );
			if ( ! empty( $author_id ) ) {
				$author_ids[] = (int) $author_id;
			}
		}

		return array_unique( $author_ids );
	}

	/**
	 * Get unique author objects from a collection of posts.
	 *
	 * @param array $post_ids Array of post IDs.
	 *
	 * @return array Array of unique WP_User objects.
	 */
	public static function get_authors( array $post_ids ): array {
		$author_ids = self::get_author_ids( $post_ids );

		if ( empty( $author_ids ) ) {
			return [];
		}

		$authors = [];
		foreach ( $author_ids as $author_id ) {
			$user = get_userdata( $author_id );
			if ( $user ) {
				$authors[] = $user;
			}
		}

		return $authors;
	}

}