<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'hello-jk_db' );

/** MySQL database username */
define( 'DB_USER', 'root' );

/** MySQL database password */
define( 'DB_PASSWORD', '' );

/** MySQL hostname */
define( 'DB_HOST', 'localhost' );

/** Database Charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The Database Collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'M;38(CF!B.(0irV`D/p^4qj]6,`W$WG#}?O}u}~0[UqkNwQal{CCp1ww6_JlcO2f' );
define( 'SECURE_AUTH_KEY',  ')VFh;@H;8b,$9~C |:cimS101m;b@/<Pes8iI&T0:TG.O|:DXDUpA%`z+cqEuL!>' );
define( 'LOGGED_IN_KEY',    'F,P,NSa)z#[.SPNriPc,M2A)acxNgc]YaR5&rqj~8RAGdF(Uc(jL,Q-P],?nE#q9' );
define( 'NONCE_KEY',        '6_T~SQ J=Qz5~_nzpj(4k6#8=VC7EOyMSh}]a>gmqz<.*wlNM 8N8*5?,E,d4$!W' );
define( 'AUTH_SALT',        '{{2_7R>u?nk5W}#2A4JB,)6K;2T6AMFB2ge]3Z2vEUX$J?l{z+YsTkHBt#B:3.Tg' );
define( 'SECURE_AUTH_SALT', 'SlFmI_;(hV}.J$eKA_Os;tm*V%eOh6JEL`9fZ2EG%lrTA``GQUV7Hdw $Z<~S*:h' );
define( 'LOGGED_IN_SALT',   '_w__x7W.2x+RDWZB@[G-Q4=r?j;3P4yi,H*|tt~.HXLyq6BF$~y<6UJuE=RUPm=1' );
define( 'NONCE_SALT',       'wd!YoqSX$9}P$P>p]xs/AsPb-2Zb6cq$#;c-`9O9e80?Wh`_yz6so]p$!Dc5B3NL' );

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';

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
define( 'WP_DEBUG', false );

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
