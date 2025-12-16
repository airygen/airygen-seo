<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'wordpress' );

/** Database username */
define( 'DB_USER', 'wp_user' );

/** Database password */
define( 'DB_PASSWORD', 'wp_pass' );

/** Database hostname */
define( 'DB_HOST', 'db:3306' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',          '&lyVGGs,n7,vCZ|uEU.$F?qJTWlD;A.(q3_t/#$||Ak~gHGlm>D_4@X&V(fm:]2Q' );
define( 'SECURE_AUTH_KEY',   ':`rrj].tfZ S3O]Z%O,=|f3nUxL8<qON8OIQvmy)Wf*PNmu_]QWWHuALGC[{pl+<' );
define( 'LOGGED_IN_KEY',     '31x*x*(e+|(#.[}w*+M[;IZ2G-1WV$CeUa8TSH tn%=A);+U9B=+D ;&{T7c`c9+' );
define( 'NONCE_KEY',         '@]iIIBBzi=,qp4u~>s]+hni^s%;VYBT!bHAqntvDc]an|%4;Ah9B`m2.O#|vGG8Z' );
define( 'AUTH_SALT',         ' h$ UgYJqj%@XKQy1]K#~{iWd6]%+ .0F6}#l=1eLPXN!:lF0,dDD]YIppdinK!N' );
define( 'SECURE_AUTH_SALT',  '8_6g,E(#E9Vtv(T)cBkOY5:,]>C6uE /T.$:F=j}ADsC7M>-O:GRz/isM`D:L:U]' );
define( 'LOGGED_IN_SALT',    '*[jU`mW!3.kKQDZ5r6yje4l9.N}NX,lW^ZUjVbM%&5+kIoTnouq0$GQ2-3wn^A0C' );
define( 'NONCE_SALT',        '3ZEfUNB>/n$Y*=5eObj;cgmnt2!?+0oPz5ipooW_xsxZmb~_]/*FK0-(%nE,hLO8' );
define( 'WP_CACHE_KEY_SALT', '@d#^<q3,~73*L6Kh#y+.E.K%3+l%&Bco P!XuZu*DGdf9),#uwR1BF&N2a$Y]9|E' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';


/* Add any custom values between this line and the "stop editing" line. */



/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', true );
}

define( 'WP_DEBUG_LOG', 'wp-content/logs/debug.log' );
define( 'WP_DEBUG_DISPLAY', false );
define( 'FS_METHOD', 'direct' );
define( 'DISALLOW_FILE_MODS', false );


define( 'WP_ALLOW_MULTISITE', true );
define( 'MULTISITE', true );
define( 'SUBDOMAIN_INSTALL', false );
$base = '/';
define( 'DOMAIN_CURRENT_SITE', 'localhost:9000' );
define( 'PATH_CURRENT_SITE', '/' );
define( 'SITE_ID_CURRENT_SITE', 1 );
define( 'BLOG_ID_CURRENT_SITE', 1 );

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
