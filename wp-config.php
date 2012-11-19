<?php
// ===================================================
// Load database info and environment specific development parameters
// ===================================================
if ( ! file_exists( dirname( __FILE__ ) . '/local-config.php' ) )
	return;

include( dirname( __FILE__ ) . '/local-config.php' );

// ========================
// Custom Content Directory
// ========================
define( 'WP_CONTENT_DIR', dirname( __FILE__ ) . '/content' );
define( 'WP_CONTENT_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/content' );

// ================================================
// You almost certainly do not want to change these
// ================================================
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

// ================================
// Language
// Leave blank for American English
// ================================
define( 'WPLANG', '' );

// ===================
// Multisite
// ===================
define( 'WP_ALLOW_MULTISITE', true );
define( 'MULTISITE', true );
define( 'SUBDOMAIN_INSTALL', true );
define( 'SITE_ID_CURRENT_SITE', 1 );
define( 'BLOG_ID_CURRENT_SITE', 1 );
if ( strpos( $_SERVER['REQUEST_URI'], '/wp-admin/network/' ) || ( isset( $_GET['redirect_to'] ) && strpos( $_GET['redirect_to'], 'network' ) ) )
	define( 'PATH_CURRENT_SITE', '/' );
else
	define( 'PATH_CURRENT_SITE', '/wp/' );

// ===================
// Security, trash & memory
// ===================
define( 'EMPTY_TRASH_DAYS', 5 );
define( 'WP_POST_REVISIONS', 10 );
define( 'WP_MEMORY_LIMIT', '128M' );
define( 'DISALLOW_FILE_EDIT', true );

// ===================
// Bootstrap WordPress
// ===================
if ( !defined( 'ABSPATH' ) )
	define( 'ABSPATH', dirname( __FILE__ ) . '/wp/' );
require_once( ABSPATH . 'wp-settings.php' );