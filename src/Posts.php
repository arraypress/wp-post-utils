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
			$options[ $post->ID ] = $post->post_title;
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

}