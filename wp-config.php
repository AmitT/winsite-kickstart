<?php
/**
 * The base configurations of the WordPress.
 *
 * This file has the following configurations: MySQL settings, Table Prefix,
 * Secret Keys, and ABSPATH. You can find more information by visiting
 * {@link https://codex.wordpress.org/Editing_wp-config.php Editing wp-config.php}
 * Codex page. You can get the MySQL settings from your web host.
 *
 * This file is used by the wp-config.php creation script during the
 * installation. You don't have to use the web site, you can just copy this file
 * to "wp-config.php" and fill in the values.
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', 'wp-kickstart');

/** MySQL database username */
define('DB_USER', 'root');

/** MySQL database password */
define('DB_PASSWORD', '');

/** MySQL hostname */
define('DB_HOST', 'localhost');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8mb4');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         '3&uP~rvo|71Bf[imU|@6rov^AJ1n3^HIz=$SFsk4W?:7.2*>LF|Bj|p5s:+Bdq{K');
define('SECURE_AUTH_KEY',  '^!t$y*+Rg[5xA(+tc]lSQ#u/%&4RG(PFtc{R`jYE-[-Z:^KyHEs>CZO9v.&8-cm*');
define('LOGGED_IN_KEY',    '^=->GX*O&0x{Rf{|{@F=WjYqTg5`Re71Q %|-GM=mzql^NxXk6|3feH|lQ-MI-SS');
define('NONCE_KEY',        '&VTguNKA_t]hH%b9Ze7Irte0l-J/-z<3=-|B1b?{}S0LIt=e|hk9cI?a4_CdY7Lv');
define('AUTH_SALT',        'wh*&4+|2,< d!+-sa)WUk(;RlB|F5~|^H9A^SuvLL;%E8~<W/XSJ/%-9hmaaqStT');
define('SECURE_AUTH_SALT', '/=nt5d,B>}<C@f_(mhwXp+!9,lIcm@{JL)-k+}gx<LXTiFgp4T;|z@W8~G4)E_?+');
define('LOGGED_IN_SALT',   ',BboTFAs%=+%s$$z^6I6#eq`cTc]Eg~JUCG/,TYY|467AeQ5M,J(V/luTz*0dcMH');
define('NONCE_SALT',       'Q<tU6_9vMOeKFC`E>YcnTvX`E@bR<Gc6+&Jc!L.hdt[sxxr2/^8lh%4mbe barD,');

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each a unique
 * prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = 'wpks_';

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');


/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 */
define('WP_DEBUG', true);
/** Limit post revisions to 5. */
define( 'WP_POST_REVISIONS', 5);
/** disallow wp files editor. */
define( 'DISALLOW_FILE_EDIT', true );
/** Activate DEV DNV for sage + gulp.  */
define('WP_ENV', 'development');
