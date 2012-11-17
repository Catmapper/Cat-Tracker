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
define('MULTISITE', true);
define('SUBDOMAIN_INSTALL', true);
define('PATH_CURRENT_SITE', '/');
define('SITE_ID_CURRENT_SITE', 1);
define('BLOG_ID_CURRENT_SITE', 1);


// ===================
// Bootstrap WordPress
// ===================
if ( !defined( 'ABSPATH' ) )
	define( 'ABSPATH', dirname( __FILE__ ) . '/wp/' );
require_once( ABSPATH . 'wp-settings.php' );