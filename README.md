# WordPress Post Utilities

A lightweight WordPress library for working with posts and post operations. Provides clean APIs for post retrieval, content management, meta operations, and bulk actions with value/label formatting perfect for forms and admin interfaces.

## Features

* 🎯 **Clean API**: WordPress-style snake_case methods with consistent interfaces
* 🔍 **Built-in Search**: Post search with value/label formatting for forms
* 📋 **Form-Ready Options**: Perfect value/label arrays for selects and admin interfaces
* 🔗 **Term Management**: Easy term assignment and relationship management
* 📊 **Meta Operations**: Simple post meta handling with type safety
* 🎨 **Flexible Identifiers**: Use IDs, slugs, titles, or objects interchangeably
* ⚡ **Bulk Operations**: Efficient bulk status changes, deletions, and updates
* ➕ **Post Creation**: Create single or multiple posts with flexible options

## Requirements

* PHP 7.4 or later
* WordPress 5.0 or later

## Installation

```bash
composer require arraypress/wp-post-utils
```

## Basic Usage

### Working with Single Posts

```php
use ArrayPress\PostUtils\Post;

// Get post by ID
$post = Post::get( 123 );

// Get post by identifier (ID, slug, or title)
$post = Post::get_by_identifier( 'hello-world' );
$post = Post::get_by_slug( 'hello-world' );
$post = Post::get_by_title( 'Hello World' );

// Check if post exists
if ( Post::exists( 123 ) ) {
	// Post exists
}

// Create a new post
$post = Post::create( 'My New Post', 'This is the content' );

// Create post with additional data
$post = Post::create( 'My New Post', 'Content here', [
	'post_type'   => 'page',
	'post_status' => 'draft',
	'post_author' => 5,
	'meta_input'  => [
		'custom_field' => 'value'
	]
] );

// Create post only if it doesn't exist
$post = Post::create_if_not_exists( 'Unique Title', 'Content' );

// Get post content and meta
$title   = Post::get_title( 123 );
$content = Post::get_content( 123 );
$excerpt = Post::get_excerpt( 123 );
$url     = Post::get_url( 123 );
$slug    = Post::get_slug( 123 );
$type    = Post::get_type( 123 );

// Get author information
$author_id = Post::get_author_id( 123 );
$author    = Post::get_author( 123 ); // Returns WP_User object

// Get post meta
$meta_value        = Post::get_meta( 123, 'custom_field' );
$meta_with_default = Post::get_meta_with_default( 123, 'priority', 'normal' );

// Check post status and conditions
if ( Post::is_published( 123 ) ) {
	// Post is published
}

if ( Post::is_draft( 123 ) ) {
	// Post is draft
}

if ( Post::is_scheduled( 123 ) ) {
	// Post is scheduled
}

if ( Post::is_type( 123, 'page' ) ) {
	// Post is a page
}

if ( Post::is_type( 123, [ 'post', 'custom_post' ] ) ) {
	// Post is either a post or custom_post
}

if ( Post::has_thumbnail( 123 ) ) {
	$thumbnail = Post::get_thumbnail_url( 123 );
}

if ( Post::has_content( 123 ) ) {
	// Post has content
}

if ( Post::has_excerpt( 123 ) ) {
	// Post has manual excerpt
}

if ( Post::is_sticky( 123 ) ) {
	// Post is sticky
}

if ( Post::allows_comments( 123 ) ) {
	$comment_count = Post::get_comment_count( 123 );
}

// Content analysis
$words        = Post::count_words( 123 );
$reading_time = Post::get_reading_time( 123 ); // minutes
$age_days     = Post::get_age( 123 );

// Post management
Post::update_status( 123, 'draft' );
Post::trash( 123 );
Post::delete( 123, true ); // force delete

// Dates
$date     = Post::get_date( 123, 'Y-m-d' );
$modified = Post::get_modified_date( 123 );
```

### Working with Multiple Posts

```php
<?php

use ArrayPress\PostUtils\Posts;

// Get multiple posts
$posts = Posts::get( [ 1, 2, 3 ] );
$posts = Posts::get( [ 1, 2, 3 ], 'page' ); // Specific post type

// Create multiple posts
$result = Posts::create( [
	[
		'post_title'   => 'First Post',
		'post_content' => 'Content for first post',
		'post_type'    => 'post'
	],
	[
		'post_title'   => 'Second Post',
		'post_content' => 'Content for second post',
		'post_type'    => 'page'
	]
] );

// Create posts with shared defaults
$result = Posts::create( [
	[
		'post_title'   => 'Post One',
		'post_content' => 'Content one'
	],
	[
		'post_title'   => 'Post Two',
		'post_content' => 'Content two'
	]
], [
	'post_type'   => 'custom_post',
	'post_status' => 'draft',
	'post_author' => 5
] );

// Create posts only if they don't exist
$result = Posts::create_if_not_exists( [
	[
		'post_title' => 'About Us',
		'post_type'  => 'page'
	],
	[
		'post_title' => 'Contact',
		'post_type'  => 'page'
	]
] );

// Check results
if ( ! empty( $result['created'] ) ) {
	foreach ( $result['created'] as $post ) {
		echo "Created post: " . $post->post_title . "\n";
	}
}

if ( ! empty( $result['existing'] ) ) {
	foreach ( $result['existing'] as $post ) {
		echo "Post already exists: " . $post->post_title . "\n";
	}
}

if ( ! empty( $result['errors'] ) ) {
	foreach ( $result['errors'] as $error ) {
		echo "Error: " . $error . "\n";
	}
}

// Get posts by identifiers
$post_ids     = Posts::get_by_identifiers( [ 'hello-world', 'about-us' ] );
$post_objects = Posts::get_by_identifiers( [ 'hello-world', 'about-us' ], 'any', true );

// Search posts and get options
$options = Posts::search_options( 'technology' );
// Returns: [['value' => 1, 'label' => 'Tech Article'], ...]

// Get all posts as options
$all_options = Posts::get_options( 'post' );
// Returns: [1 => 'Hello World', 2 => 'About Us', ...]

// Get posts by author or date range
$author_posts = Posts::get_by_author( 5 );
$recent_posts = Posts::get_recent( 10 );
$range_posts  = Posts::get_by_date_range( '2024-01-01', '2024-12-31' );
```

### Post Creation Examples

```php
// Plugin activation - create default pages
function create_default_pages() {
	$default_pages = [
		[
			'post_title'   => 'Privacy Policy',
			'post_content' => 'Your privacy policy content here.',
			'post_type'    => 'page',
			'post_status'  => 'publish'
		],
		[
			'post_title'   => 'Terms of Service',
			'post_content' => 'Your terms of service content here.',
			'post_type'    => 'page',
			'post_status'  => 'publish'
		]
	];

	$result = Posts::create_if_not_exists( $default_pages );

	// Log creation results
	error_log( sprintf( 'Created %d pages, %d already existed',
		count( $result['created'] ),
		count( $result['existing'] )
	) );
}

// Import posts from external data
function import_posts_from_data( $posts_data ) {
	$posts = [];

	foreach ( $posts_data as $data ) {
		$posts[] = [
			'post_title'   => $data['title'],
			'post_content' => $data['content'],
			'post_type'    => $data['type'] ?? 'post',
			'post_status'  => $data['status'] ?? 'publish',
			'meta_input'   => $data['meta'] ?? []
		];
	}

	return Posts::create( $posts, [
		'post_author' => get_current_user_id()
	] );
}

// Create test content for development
function create_test_content() {
	$test_posts = [
		'Sample Blog Post' => 'This is a sample blog post for testing.',
		'About Page'       => 'This is the about page content.',
		'Contact Page'     => 'Contact us at info@example.com'
	];

	$posts_data = [];
	foreach ( $test_posts as $title => $content ) {
		$posts_data[] = [
			'post_title'   => $title,
			'post_content' => $content,
			'post_type'    => str_contains( $title, 'Page' ) ? 'page' : 'post'
		];
	}

	return Posts::create_if_not_exists( $posts_data );
}
```

### Meta Operations

```php
// Get meta with default
$value = Post::get_meta_with_default( 123, 'priority', 'normal' );

// Update meta only if changed
Post::update_meta_if_changed( 123, 'status', 'updated' );

// Delete meta
Post::delete_meta( 123, 'old_field' );
```

### Bulk Operations

```php
// Change status for multiple posts
$results = Posts::change_status( [ 1, 2, 3 ], 'draft' );

// Trash multiple posts
$results = Posts::trash( [ 1, 2, 3 ] );

// Delete permanently
$results = Posts::delete( [ 1, 2, 3 ], true );

// Change author
$results = Posts::change_author( [ 1, 2, 3 ], 5 );

// Check results
foreach ( $results as $post_id => $success ) {
	if ( $success ) {
		echo "Successfully updated post {$post_id}\n";
	} else {
		echo "Failed to update post {$post_id}\n";
	}
}
```

### Hierarchy Operations

```php
// Parent-child relationships
$parent_id = Post::get_parent_id( 123 );
$children  = Post::get_children( 123 );

if ( Post::is_child_of( 123, 456 ) ) {
	// Post 123 is child of 456
}

// Get children with custom args
$child_pages = Post::get_children( 123, [
	'post_status' => [ 'publish', 'private' ],
	'orderby'     => 'title',
	'order'       => 'ASC'
] );
```

### Search Functionality

```php
// Basic search
$results = Posts::search( 'wordpress' );

// Search with specific post types
$results = Posts::search( 'wordpress', [ 'post', 'page' ] );

// Search with additional arguments
$results = Posts::search( 'technology', [ 'post' ], [
	'posts_per_page' => 10,
	'meta_key'       => 'featured',
	'meta_value'     => '1'
] );

// Get search results as options for forms
$options = Posts::search_options( 'admin' );
```

## API Reference

### Post Class (Single Posts)

**Core Retrieval:**
- `get( int $post_id ): ?WP_Post`
- `get_by_identifier( $identifier, $post_type = 'any', $post_status = 'publish' ): ?WP_Post`
- `get_by_slug( string $slug, $post_type = 'post' ): ?WP_Post`
- `get_by_title( string $title, $post_type = 'post' ): ?WP_Post`
- `get_by_meta( string $meta_key, $meta_value, $post_type = 'post' ): ?WP_Post`
- `exists( int $post_id ): bool`

**Creation:**
- `create( string $title, string $content = '', array $args = [] ): ?WP_Post`
- `create_if_not_exists( string $title, string $content = '', array $args = [] ): ?WP_Post`

**Content & Basic Info:**
- `get_title( int $post_id, bool $raw = false ): string`
- `get_content( int $post_id, bool $raw = false ): string`
- `get_excerpt( int $post_id, bool $raw = false ): string`
- `get_url( int $post_id ): string|false`
- `get_slug( int $post_id ): ?string`
- `get_type( int $post_id ): string|false`
- `get_field( int $post_id, string $field )`
- `get_thumbnail_url( int $post_id ): string|false`

**Author:**
- `get_author_id( int $post_id ): ?int`
- `get_author( int $post_id ): ?WP_User`

**Content Analysis:**
- `count_words( int $post_id ): int`
- `get_reading_time( int $post_id, int $words_per_minute = 200 ): int`

**Meta Operations:**
- `get_meta( int $post_id, string $key, bool $single = true )`
- `get_meta_with_default( int $post_id, string $meta_key, $default )`
- `update_meta_if_changed( int $post_id, string $meta_key, $meta_value ): bool`
- `delete_meta( int $post_id, string $meta_key ): bool`

**Status & Conditionals:**
- `get_status( int $post_id ): string|false`
- `is_published( int $post_id ): bool`
- `is_draft( int $post_id ): bool`
- `is_private( int $post_id ): bool`
- `is_scheduled( int $post_id ): bool`
- `is_sticky( int $post_id ): bool`
- `is_type( int $post_id, string|array $post_type ): bool`
- `has_content( int $post_id ): bool`
- `has_excerpt( int $post_id ): bool`
- `has_password( int $post_id ): bool`
- `has_thumbnail( int $post_id ): bool`
- `allows_comments( int $post_id ): bool`

**Dates & Analysis:**
- `get_date( int $post_id, string $format = 'Y-m-d H:i:s' ): string|false`
- `get_modified_date( int $post_id, string $format = 'Y-m-d H:i:s' ): string|false`
- `get_age( int $post_id, bool $use_modified_date = false ): int|false`
- `get_comment_count( int $post_id ): int`

**Hierarchy:**
- `get_parent_id( int $post_id ): int`
- `is_child_of( int $post_id, int $parent_id ): bool`
- `get_children( int $post_id, array $args = [] ): array`

**Actions:**
- `update_status( int $post_id, string $new_status ): bool`
- `trash( int $post_id ): bool`
- `delete( int $post_id, bool $force = false ): bool`

### Posts Class (Multiple Posts)

**Core Retrieval:**
- `get( array $post_ids, $post_type = 'any', array $args = [] ): array`
- `get_by_identifiers( array $identifiers, $post_type = 'any', bool $return_objects = false ): array`
- `get_by_author( int $author_id, array $args = [] ): array`
- `get_recent( int $number = 5, array $args = [] ): array`
- `get_by_date_range( string $start_date, string $end_date, array $args = [] ): array`

**Creation:**
- `create( array $posts, array $defaults = [] ): array`
- `create_if_not_exists( array $posts, array $defaults = [] ): array`

**Search & Options:**
- `search( string $search, array $post_types = ['post'], array $args = [] ): array`
- `search_options( string $search, array $post_types = ['post'], array $args = [] ): array`
- `get_options( $post_type = 'post', array $args = [] ): array`

**Bulk Actions:**
- `change_status( array $post_ids, string $new_status ): array`
- `change_author( array $post_ids, int $new_author ): array`
- `trash( array $post_ids ): array`
- `delete( array $post_ids, bool $force = false ): array`

## Key Features

- **Value/Label Format**: Perfect for forms and selects
- **Search Functionality**: Built-in post search with formatting
- **Post Creation**: Create single or multiple posts with validation
- **Bulk Operations**: Efficient multi-post management
- **Meta Handling**: Simple and safe meta operations
- **Flexible Identifiers**: Use IDs, slugs, titles, or objects interchangeably
- **Content Analysis**: Word counts, reading time, age calculations
- **Hierarchy Support**: Parent-child relationship management
- **Type Checking**: Flexible post type validation with single or multiple types

## Requirements

- PHP 7.4+
- WordPress 5.0+

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the GPL-2.0-or-later License.

## Support

- [Documentation](https://github.com/arraypress/wp-post-utils)
- [Issue Tracker](https://github.com/arraypress/wp-post-utils/issues)