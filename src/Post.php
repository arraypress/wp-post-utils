<?php
/**
 * Post Utility Class
 *
 * Provides utility functions for working with individual WordPress posts,
 * including content management, meta operations, status checking, and basic
 * hierarchical relationships.
 *
 * @package ArrayPress\PostUtils
 * @since   1.0.0
 * @author  ArrayPress
 * @license GPL-2.0-or-later
 */

declare( strict_types=1 );

namespace ArrayPress\PostUtils;

use WP_Post;
use WP_User;

/**
 * Post Class
 *
 * Core operations for working with individual WordPress posts.
 */
class Post {

	// ========================================
	// Core Retrieval
	// ========================================

	/**
	 * Check if a post exists.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return bool True if exists, false otherwise.
	 */
	public static function exists( int $post_id ): bool {
		return get_post( $post_id ) instanceof WP_Post;
	}

	/**
	 * Get a post object.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return WP_Post|null The post object or null if not found.
	 */
	public static function get( int $post_id ): ?WP_Post {
		$post = get_post( $post_id );

		return $post instanceof WP_Post ? $post : null;
	}

	/**
	 * Get a post by identifier (ID, slug, title, or post object).
	 *
	 * @param mixed        $identifier  The post identifier.
	 * @param string|array $post_type   Optional. Post type(s). Default 'any'.
	 * @param string|array $post_status Optional. Post status(es). Default 'publish'.
	 *
	 * @return WP_Post|null The post object or null if not found.
	 */
	public static function get_by_identifier( $identifier, $post_type = 'any', $post_status = 'publish' ): ?WP_Post {
		if ( $identifier instanceof WP_Post ) {
			return $identifier;
		}

		if ( is_numeric( $identifier ) ) {
			$post = get_post( (int) $identifier );

			return ( $post instanceof WP_Post ) ? $post : null;
		}

		$args = [
			'post_type'      => $post_type,
			'post_status'    => $post_status,
			'posts_per_page' => 1,
			'no_found_rows'  => true,
			'fields'         => 'ids',
		];

		// Try by slug first
		$args['name'] = sanitize_title( $identifier );
		$posts        = get_posts( $args );

		if ( empty( $posts ) ) {
			unset( $args['name'] );
			$args['title'] = $identifier;
			$posts         = get_posts( $args );
		}

		return ! empty( $posts ) ? get_post( $posts[0] ) : null;
	}

	/**
	 * Get a post by slug.
	 *
	 * @param string       $slug        The post slug.
	 * @param string|array $post_type   Optional. Post type(s). Default 'post'.
	 * @param string|array $post_status Optional. Post status(es). Default 'publish'.
	 *
	 * @return WP_Post|null The post object or null if not found.
	 */
	public static function get_by_slug( string $slug, $post_type = 'post', $post_status = 'publish' ): ?WP_Post {
		$args = [
			'name'           => $slug,
			'post_type'      => $post_type,
			'post_status'    => $post_status,
			'posts_per_page' => 1,
			'no_found_rows'  => true,
		];

		$posts = get_posts( $args );

		return ! empty( $posts ) ? $posts[0] : null;
	}

	/**
	 * Get a post by title.
	 *
	 * @param string       $title       The post title.
	 * @param string|array $post_type   Optional. Post type(s). Default 'post'.
	 * @param string|array $post_status Optional. Post status(es). Default 'publish'.
	 *
	 * @return WP_Post|null The post object or null if not found.
	 */
	public static function get_by_title( string $title, $post_type = 'post', $post_status = 'publish' ): ?WP_Post {
		$args = [
			'title'          => $title,
			'post_type'      => $post_type,
			'post_status'    => $post_status,
			'posts_per_page' => 1,
			'no_found_rows'  => true,
			'orderby'        => [ 'post_date' => 'ASC', 'ID' => 'ASC' ],
		];

		$posts = get_posts( $args );

		return ! empty( $posts ) ? $posts[0] : null;
	}

	/**
	 * Get a post by meta value.
	 *
	 * @param string       $meta_key    The meta key.
	 * @param mixed        $meta_value  The meta value.
	 * @param string|array $post_type   Optional. Post type(s). Default 'post'.
	 * @param string|array $post_status Optional. Post status(es). Default 'publish'.
	 *
	 * @return WP_Post|null The post object or null if not found.
	 */
	public static function get_by_meta( string $meta_key, $meta_value, $post_type = 'post', $post_status = 'publish' ): ?WP_Post {
		$args = [
			'post_type'      => $post_type,
			'post_status'    => $post_status,
			'posts_per_page' => 1,
			'no_found_rows'  => true,
			'meta_key'       => $meta_key,
			'meta_value'     => $meta_value,
		];

		$posts = get_posts( $args );

		return ! empty( $posts ) ? $posts[0] : null;
	}

	// ========================================
	// Creation
	// ========================================

	/**
	 * Create a new post.
	 *
	 * @param string $title   Post title.
	 * @param string $content Post content.
	 * @param array  $args    Optional arguments (post_type, status, author, etc.).
	 *
	 * @return WP_Post|null The created post object or null on failure.
	 */
	public static function create( string $title, string $content = '', array $args = [] ): ?WP_Post {
		if ( empty( $title ) ) {
			return null;
		}

		$post_data = array_merge( [
			'post_title'   => $title,
			'post_content' => $content,
			'post_status'  => 'publish',
			'post_type'    => 'post',
			'post_author'  => get_current_user_id(),
		], $args );

		$post_id = wp_insert_post( $post_data );

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return null;
		}

		return self::get( $post_id );
	}

	/**
	 * Create post if it doesn't exist (by title).
	 *
	 * @param string $title   Post title.
	 * @param string $content Post content.
	 * @param array  $args    Optional arguments.
	 *
	 * @return WP_Post|null The post object (created or existing) or null on failure.
	 */
	public static function create_if_not_exists( string $title, string $content = '', array $args = [] ): ?WP_Post {
		$post_type   = $args['post_type'] ?? 'post';
		$post_status = $args['post_status'] ?? 'publish';

		// Check if post exists by title
		$existing_post = self::get_by_title( $title, $post_type, $post_status );

		if ( $existing_post ) {
			return $existing_post;
		}

		return self::create( $title, $content, $args );
	}

	// ========================================
	// Content & Basic Info
	// ========================================

	/**
	 * Get the post title.
	 *
	 * @param int  $post_id The post ID.
	 * @param bool $raw     Whether to return raw title. Default false.
	 *
	 * @return string The post title.
	 */
	public static function get_title( int $post_id, bool $raw = false ): string {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}

		return $raw ? $post->post_title : apply_filters( 'the_title', $post->post_title );
	}

	/**
	 * Get the post content.
	 *
	 * @param int  $post_id The post ID.
	 * @param bool $raw     Whether to return raw content. Default false.
	 *
	 * @return string The post content.
	 */
	public static function get_content( int $post_id, bool $raw = false ): string {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}

		return $raw ? $post->post_content : apply_filters( 'the_content', $post->post_content );
	}

	/**
	 * Get the post excerpt.
	 *
	 * @param int  $post_id The post ID.
	 * @param bool $raw     Whether to return raw excerpt. Default false.
	 *
	 * @return string The post excerpt.
	 */
	public static function get_excerpt( int $post_id, bool $raw = false ): string {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}

		if ( $raw ) {
			return $post->post_excerpt;
		}

		return $post->post_excerpt ? apply_filters( 'get_the_excerpt', $post->post_excerpt )
			: wp_trim_words( $post->post_content );
	}

	/**
	 * Get the post URL.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return string|false The post URL or false if not found.
	 */
	public static function get_url( int $post_id ) {
		return get_permalink( $post_id );
	}

	/**
	 * Get the post type.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return string|false The post type or false if not found.
	 */
	public static function get_type( int $post_id ) {
		return get_post_type( $post_id );
	}

	/**
	 * Get the post slug.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return string|null The post slug or null if not found.
	 */
	public static function get_slug( int $post_id ): ?string {
		$post = get_post( $post_id );

		return $post ? $post->post_name : null;
	}

	/**
	 * Get a specific field from the post.
	 *
	 * @param int    $post_id The post ID.
	 * @param string $field   The field name.
	 *
	 * @return mixed The field value or null if not found.
	 */
	public static function get_field( int $post_id, string $field ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return null;
		}

		if ( isset( $post->$field ) ) {
			return $post->$field;
		}

		return get_post_meta( $post->ID, $field, true );
	}

	/**
	 * Get thumbnail URL.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return string|false Thumbnail URL or false if not found.
	 */
	public static function get_thumbnail_url( int $post_id ) {
		return get_the_post_thumbnail_url( $post_id );
	}

	// ========================================
	// Author
	// ========================================

	/**
	 * Get the post author ID.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return int|null The author ID or null if not found.
	 */
	public static function get_author_id( int $post_id ): ?int {
		$post = get_post( $post_id );

		return $post ? (int) $post->post_author : null;
	}

	/**
	 * Get the post author.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return WP_User|null The author user object or null if not found.
	 */
	public static function get_author( int $post_id ): ?WP_User {
		$author_id = self::get_author_id( $post_id );

		if ( ! $author_id ) {
			return null;
		}

		$user = get_userdata( $author_id );

		return ( $user instanceof WP_User ) ? $user : null;
	}

	// ========================================
	// Content Analysis
	// ========================================

	/**
	 * Count words in post content.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return int Word count.
	 */
	public static function count_words( int $post_id ): int {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return 0;
		}

		return str_word_count( strip_tags( $post->post_content ) );
	}

	/**
	 * Get estimated reading time.
	 *
	 * @param int $post_id          The post ID.
	 * @param int $words_per_minute Optional. Reading speed. Default 200.
	 *
	 * @return int Estimated reading time in minutes.
	 */
	public static function get_reading_time( int $post_id, int $words_per_minute = 200 ): int {
		$word_count = self::count_words( $post_id );

		return (int) ceil( $word_count / $words_per_minute );
	}

	// ========================================
	// Meta Operations
	// ========================================

	/**
	 * Get meta value.
	 *
	 * @param int    $post_id The post ID.
	 * @param string $key     Meta key.
	 * @param bool   $single  Whether to return single value.
	 *
	 * @return mixed Meta value or null if not found.
	 */
	public static function get_meta( int $post_id, string $key, bool $single = true ) {
		$value = get_post_meta( $post_id, $key, $single );

		if ( $single && $value === '' ) {
			return null;
		}

		if ( ! $single && empty( $value ) ) {
			return null;
		}

		return $value;
	}

	/**
	 * Get meta value with default.
	 *
	 * @param int    $post_id  The post ID.
	 * @param string $meta_key Meta key.
	 * @param mixed  $default  Default value.
	 *
	 * @return mixed Meta value or default.
	 */
	public static function get_meta_with_default( int $post_id, string $meta_key, $default ) {
		$value = get_post_meta( $post_id, $meta_key, true );

		return $value !== '' ? $value : $default;
	}

	/**
	 * Update meta if value has changed.
	 *
	 * @param int    $post_id    The post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value New meta value.
	 *
	 * @return bool True if value was changed.
	 */
	public static function update_meta_if_changed( int $post_id, string $meta_key, $meta_value ): bool {
		$current_value = get_post_meta( $post_id, $meta_key, true );

		if ( $current_value !== $meta_value ) {
			return update_post_meta( $post_id, $meta_key, $meta_value );
		}

		return false;
	}

	/**
	 * Delete meta.
	 *
	 * @param int    $post_id  The post ID.
	 * @param string $meta_key Meta key.
	 *
	 * @return bool True on success.
	 */
	public static function delete_meta( int $post_id, string $meta_key ): bool {
		if ( empty( $post_id ) ) {
			return false;
		}

		return delete_post_meta( $post_id, $meta_key );
	}

	// ========================================
	// Status & Conditionals
	// ========================================

	/**
	 * Get the post status.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return string|false The post status or false if not found.
	 */
	public static function get_status( int $post_id ) {
		return get_post_status( $post_id );
	}

	/**
	 * Check if post is published.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return bool True if published.
	 */
	public static function is_published( int $post_id ): bool {
		return get_post_status( $post_id ) === 'publish';
	}

	/**
	 * Check if post is draft.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return bool True if draft.
	 */
	public static function is_draft( int $post_id ): bool {
		$status = get_post_status( $post_id );

		return $status === 'draft' || $status === 'auto-draft';
	}

	/**
	 * Check if post is private.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return bool True if private.
	 */
	public static function is_private( int $post_id ): bool {
		return get_post_status( $post_id ) === 'private';
	}

	/**
	 * Check if post is scheduled.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return bool True if scheduled.
	 */
	public static function is_scheduled( int $post_id ): bool {
		return get_post_status( $post_id ) === 'future';
	}

	/**
	 * Check if post is sticky.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return bool True if sticky.
	 */
	public static function is_sticky( int $post_id ): bool {
		return is_sticky( $post_id );
	}

	/**
	 * Check if post is of a specific type.
	 *
	 * @param int          $post_id   The post ID.
	 * @param string|array $post_type Post type(s) to check against.
	 *
	 * @return bool True if post matches the type(s).
	 */
	public static function is_type( int $post_id, $post_type ): bool {
		$current_type = get_post_type( $post_id );

		if ( ! $current_type ) {
			return false;
		}

		if ( is_array( $post_type ) ) {
			return in_array( $current_type, $post_type, true );
		}

		return $current_type === $post_type;
	}

	/**
	 * Check if post has content.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return bool True if post has content.
	 */
	public static function has_content( int $post_id ): bool {
		$post = get_post( $post_id );

		return $post && ! empty( $post->post_content );
	}

	/**
	 * Check if post has excerpt.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return bool True if post has manual excerpt.
	 */
	public static function has_excerpt( int $post_id ): bool {
		$post = get_post( $post_id );

		return $post && ! empty( $post->post_excerpt );
	}

	/**
	 * Check if post has password.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return bool True if post has password.
	 */
	public static function has_password( int $post_id ): bool {
		$post = get_post( $post_id );

		return $post && ! empty( $post->post_password );
	}

	/**
	 * Check if post has thumbnail.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return bool True if post has thumbnail.
	 */
	public static function has_thumbnail( int $post_id ): bool {
		return current_theme_supports( 'post-thumbnails' ) && has_post_thumbnail( $post_id );
	}

	/**
	 * Check if post allows comments.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return bool True if comments are allowed.
	 */
	public static function allows_comments( int $post_id ): bool {
		$post = get_post( $post_id );

		return $post && $post->comment_status === 'open';
	}

	// ========================================
	// Dates & Analysis
	// ========================================

	/**
	 * Get post date.
	 *
	 * @param int    $post_id The post ID.
	 * @param string $format  Optional. Date format. Default 'Y-m-d H:i:s'.
	 *
	 * @return string|false Formatted date or false if not found.
	 */
	public static function get_date( int $post_id, string $format = 'Y-m-d H:i:s' ) {
		$post = get_post( $post_id );

		return $post ? mysql2date( $format, $post->post_date ) : false;
	}

	/**
	 * Get modified date.
	 *
	 * @param int    $post_id The post ID.
	 * @param string $format  Optional. Date format. Default 'Y-m-d H:i:s'.
	 *
	 * @return string|false Formatted modified date or false if not found.
	 */
	public static function get_modified_date( int $post_id, string $format = 'Y-m-d H:i:s' ) {
		$post = get_post( $post_id );

		return $post ? mysql2date( $format, $post->post_modified ) : false;
	}

	/**
	 * Get post age in days.
	 *
	 * @param int  $post_id           The post ID.
	 * @param bool $use_modified_date Whether to use modified date. Default false.
	 *
	 * @return int|false Number of days or false if not found.
	 */
	public static function get_age( int $post_id, bool $use_modified_date = false ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		$post_time = strtotime( $use_modified_date ? $post->post_modified_gmt : $post->post_date_gmt );

		return (int) floor( ( time() - $post_time ) / DAY_IN_SECONDS );
	}

	/**
	 * Get comment count.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return int Number of approved comments.
	 */
	public static function get_comment_count( int $post_id ): int {
		$post = get_post( $post_id );

		return $post ? (int) $post->comment_count : 0;
	}

	// ========================================
	// Hierarchy
	// ========================================

	/**
	 * Get parent post ID.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return int Parent ID (0 if no parent).
	 */
	public static function get_parent_id( int $post_id ): int {
		$post = get_post( $post_id );

		return $post ? $post->post_parent : 0;
	}

	/**
	 * Check if post is child of another post.
	 *
	 * @param int $post_id   The post ID.
	 * @param int $parent_id The parent ID to check.
	 *
	 * @return bool True if is child of specified parent.
	 */
	public static function is_child_of( int $post_id, int $parent_id ): bool {
		$post = get_post( $post_id );

		return $post && $post->post_parent === $parent_id;
	}

	/**
	 * Get immediate child posts.
	 *
	 * @param int   $post_id The parent post ID.
	 * @param array $args    Optional. Additional arguments.
	 *
	 * @return WP_Post[] Array of child post objects.
	 */
	public static function get_children( int $post_id, array $args = [] ): array {
		$default_args = [
			'post_parent'    => $post_id,
			'post_type'      => get_post_type( $post_id ),
			'posts_per_page' => - 1,
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
			'post_status'    => 'publish'
		];

		$args = wp_parse_args( $args, $default_args );

		return get_posts( $args );
	}

	// ========================================
	// Actions
	// ========================================

	/**
	 * Update post status.
	 *
	 * @param int    $post_id    The post ID.
	 * @param string $new_status The new status.
	 *
	 * @return bool True on success.
	 */
	public static function update_status( int $post_id, string $new_status ): bool {
		$result = wp_update_post( [
			'ID'          => $post_id,
			'post_status' => $new_status
		] );

		return ! is_wp_error( $result ) && $result !== 0;
	}

	/**
	 * Trash a post.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return bool True on success.
	 */
	public static function trash( int $post_id ): bool {
		return wp_trash_post( $post_id ) !== false;
	}

	/**
	 * Delete a post permanently.
	 *
	 * @param int  $post_id The post ID.
	 * @param bool $force   Whether to bypass trash.
	 *
	 * @return bool True on success.
	 */
	public static function delete( int $post_id, bool $force = false ): bool {
		return wp_delete_post( $post_id, $force ) !== false;
	}

	// ========================================
	// Admin Interface
	// ========================================

	/**
	 * Get admin edit URL for a post.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return string|null Admin URL or null if invalid.
	 */
	public static function get_admin_url( int $post_id ): ?string {
		if ( ! self::exists( $post_id ) ) {
			return null;
		}

		$url = get_edit_post_link( $post_id );

		return $url ?: null;
	}

	/**
	 * Get admin edit link for a post.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $label   Optional link text.
	 *
	 * @return string|null HTML link or null if invalid.
	 */
	public static function get_admin_link( int $post_id, string $label = '' ): ?string {
		$post = self::get( $post_id );
		if ( ! $post ) {
			return null;
		}

		if ( empty( $label ) ) {
			$label = $post->post_title;
		}

		$url = self::get_admin_url( $post_id );
		if ( ! $url ) {
			return null;
		}

		return sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html( $label ) );
	}

	// ========================================
	// Taxonomy Operations
	// ========================================

	/**
	 * Check if post has term in taxonomy.
	 *
	 * @param int    $post_id  Post ID.
	 * @param mixed  $term     Term ID, slug, or name.
	 * @param string $taxonomy Taxonomy name.
	 *
	 * @return bool True if has term.
	 */
	public static function has_term( int $post_id, $term, string $taxonomy ): bool {
		return has_term( $term, $taxonomy, $post_id );
	}

	/**
	 * Check if post is in category.
	 *
	 * @param int   $post_id  Post ID.
	 * @param mixed $category Category ID, slug, or name.
	 *
	 * @return bool True if in category.
	 */
	public static function is_in_category( int $post_id, $category ): bool {
		return has_term( $category, 'category', $post_id );
	}

	/**
	 * Check if post has tag.
	 *
	 * @param int   $post_id Post ID.
	 * @param mixed $tag     Tag ID, slug, or name.
	 *
	 * @return bool True if has tag.
	 */
	public static function has_tag( int $post_id, $tag ): bool {
		return has_term( $tag, 'post_tag', $post_id );
	}

	/**
	 * Get post terms for a taxonomy.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy name.
	 * @param array  $args     Optional arguments.
	 *
	 * @return array|null Array of terms or null on failure.
	 */
	public static function get_terms( int $post_id, string $taxonomy, array $args = [] ): ?array {
		$terms = wp_get_post_terms( $post_id, $taxonomy, $args );

		return ! is_wp_error( $terms ) ? $terms : null;
	}

}