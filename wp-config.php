<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'mammart_db' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', '' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

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
define( 'AUTH_KEY',         'O&B/g}J>vzXa{c*Yq]lNoxd*l8x#m5Mf`q#>:sJomU%;|Y[}^5MNg+/}`3zJq+zq' );
define( 'SECURE_AUTH_KEY',  '1r-|2g=kF_KsYoecBJ9Lp7RwAXQ(NjN!~F||S9Zv&JXnw)l!{<)6;%]S,(sq3[5(' );
define( 'LOGGED_IN_KEY',    '0~~ra9OOW3Ue$C(;71hZkjWDZ7%5_o!AuHaCzu;yWq=b:UB,nt}Zg(,hOQg<McQ ' );
define( 'NONCE_KEY',        'V)DT_ZWE1J!I=D.`{5haF;qg]zq/]X RB/fn%piH|a;ww5jWVoMH/>ePjb[7Gs9<' );
define( 'AUTH_SALT',        ',+)450Qf#:_[:jnMR*sIow*F-?0/Adjdr*1)H8>]Z8-[<m0tNO<apwJkFa+3rav4' );
define( 'SECURE_AUTH_SALT', '#`bm*wb}|J/u8/3wB6w69%}M1IFB~MBX6 6S 0z]R<1@cW%10_<d#F%U0G3f#f2#' );
define( 'LOGGED_IN_SALT',   'y-T#k*=t3*!Mz5=4wX=P(>/;I95ruoU<=dLfO*.<cc!<;95kL1IcOIf11jEY*xA7' );
define( 'NONCE_SALT',       'kpW?E`9Ti(teo.)9M]Z=i(Z*$1km9W]LqqQ47B).W}r2M+]ykPjc2B~U`+se7=f<' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
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
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
