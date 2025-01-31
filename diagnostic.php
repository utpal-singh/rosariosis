<?php
/**
 * Diagnostic
 *
 * Check for missing PHP extensions, files or misconfigurations
 *
 * TRANSLATION: do NOT translate these error messages since they need to stay in English for technical support.
 *
 * @package RosarioSIS
 */

$error = [];

// FJ check PHP version.
if ( version_compare( PHP_VERSION, '5.4.45' ) == -1 )
{
	$error[] = 'RosarioSIS requires PHP 5.4.45 to run, your version is : ' . PHP_VERSION;
}

// FJ verify PHP extensions and php.ini.
$inipath = php_ini_loaded_file();

if ( $inipath )
{
	$inipath = ' Loaded php.ini: ' . $inipath;
}
else
	$inipath = ' Note: No php.ini file is loaded!';

// Check for pgsql extension.
if ( ! extension_loaded( 'pgsql' ) )
{
	$error[] = 'PHP extensions: RosarioSIS relies on the pgsql (PostgreSQL) extension. Please install and activate it.';
}

if ( count( $error ) )
{
	echo _ErrorMessage( $error, 'fatal' );
}


if ( ! file_exists( './Warehouse.php' ) )
{
	$error[] = 'The diagnostic.php file needs to be in the RosarioSIS directory to be able to run. Please move it there, and run it again.';
}
else
{
	if ( ! @opendir( $RosarioPath . '/functions' ) )
	{
		$error[] = 'The value for $RosarioPath in the config.inc.php file is not correct or else the functions directory does not have the correct permissions to be read by the webserver. Make sure $RosarioPath points to the RosarioSIS installation directory and that it is readable by all users.';
	}

	if ( ! function_exists( 'pg_connect' ) )
	{
		$error[] = 'The pgsql extension (see the php.ini file) is not activated OR PHP was not compiled with PostgreSQL support. You may need to recompile PHP using the --with-pgsql option for RosarioSIS to work.';
	}
	else
	{
		require_once './Warehouse.php';

		/**
		 * Fix pg_connect(): Unable to connect to PostgreSQL server:
		 * could not connect to server:
		 * No such file or directory Is the server running locally
		 * and accepting connections on Unix domain socket "/tmp/.s.PGSQL.5432"
		 *
		 * Always set host, force TCP.
		 *
		 * @since 3.8
		 */
		$connectstring = 'host=' . $DatabaseServer . ' ';

		if ( $DatabasePort !== '5432' )
		{
			$connectstring .= 'port=' . $DatabasePort .' ';
		}

		$connectstring .= 'dbname=' . $DatabaseName . ' user=' . $DatabaseUsername;

		if ( $DatabasePassword !== '' )
		{
			$connectstring .= ' password=' . $DatabasePassword;
		}

		$connection = pg_connect( $connectstring );

		if ( ! $connection )
		{
			$error[] = 'RosarioSIS cannot connect to the PostgreSQL database. Either Postgres is not running, it was not started with the -i option, or connections from this host are not allowed in the pg_hba.conf file. Last Postgres Error: ' . pg_last_error( $connection );
		}
		else
		{
			$result = @pg_exec( $connection, 'SELECT * FROM CONFIG' );

			if ( $result === false )
			{
				$errstring = pg_last_error( $connection );

				if ( mb_strpos( $errstring, 'permission denied' ) !== false )
				{
					$error[] = 'The database was created with the wrong permissions. The user specified in the config.inc.php file does not have permission to access the database. Use the super-user (postgres) or recreate the database adding \connect - YOUR_USERNAME to the top of the rosariosis.sql file.';
				}
				elseif ( mb_strpos( $errstring, 'elation "config" does not exist' ) !== false )
				{
					$error[] = 'At least one of the tables does not exist. Make sure you ran the rosariosis.sql file as described in the INSTALL.md file.';
				}
				elseif ( $errstring )
				{
					$error[] = $errstring;
				}
			}
			else
			{
				// OK, we can connect to database & CONFIG table exists.
				$result = @pg_exec( $connection, "SELECT * FROM STAFF WHERE SYEAR='" . $DefaultSyear . "'" );

				if ( ! pg_fetch_all( $result ) )
				{
					$error[] = 'The value for $DefaultSyear in the config.inc.php file is incorrect.';
				}
				else
				{
					// OK, $DefaultSyear is correct so we can login.
					if ( ( isset( $_SESSION['STAFF_ID'] )
							&& $_SESSION['STAFF_ID'] < 1 )
						|| User( 'PROFILE' ) !== 'admin' )
					{
						// @since 9.0 Restrict diagnostic access to logged in admin.
						$error[] = 'Please login as an administrator before accessing the diagnostic.php page.';

						// Exit.
						echo _ErrorMessage( $error, 'fatal' );
					}
				}
			}
		}
	}
}

if ( ! is_array( $RosarioLocales )
	|| empty( $RosarioLocales ) )
{
	$error[] = 'The value for $RosarioLocales in the config.inc.php file is not correct.';
}

// Check wkhtmltopdf binary exists.
if ( ! empty( $wkhtmltopdfPath )
	&& ( ! file_exists( $wkhtmltopdfPath )
		|| strpos( basename( $wkhtmltopdfPath ), 'wkhtmltopdf' ) !== 0 ) )
{
	$error[] = 'The value for $wkhtmltopdfPath in the config.inc.php file is not correct.';
}

// Check pg_dump binary exists.
if ( ! empty( $pg_dumpPath )
	&& ( ! file_exists( $pg_dumpPath )
		|| strpos( basename( $pg_dumpPath ), 'pg_dump' ) !== 0 ) )
{
	$error[] = 'The value for $pg_dumpPath in the config.inc.php file is not correct.';
}

// Check for gd extension.
if ( ! extension_loaded( 'gd' ) )
{
	$error[] = 'PHP extensions: RosarioSIS relies on the gd extension (used to resize and compress images). Please install and activate it.';
}

// Check for zip extension.
if ( ! extension_loaded( 'zip' ) )
{
	$error[] = 'PHP extensions: RosarioSIS relies on the zip extension (used to upload add-ons and by Import add-ons). Please install and activate it.';
}

// Check for xmlrpc extension.
if ( version_compare( PHP_VERSION, '8.0' ) == -1
	&& ! extension_loaded( 'xmlrpc' ) )
{
	$error[] = 'PHP extensions: RosarioSIS relies on the xmlrpc extension (only used to connect to Moodle). Please install and activate it.';
}

// Check for curl extension.
if ( ! extension_loaded( 'curl' ) )
{
	$error[] = 'PHP extensions: RosarioSIS relies on the curl extension (only used to connect to Moodle). Please install and activate it.';
}

// Check for intl extension.
if ( ! extension_loaded( 'intl' ) )
{
	$error[] = 'PHP extensions: RosarioSIS relies on the intl extension. Please install and activate it.';
}

// Check session.auto_start.
if ( (bool) ini_get( 'session.auto_start' ) )
{
	$error[] = 'session.auto_start is set to On in your PHP configuration. See the php.ini file to deactivate it.' . $inipath;
}


echo _ErrorMessage( $error, 'error' );

if ( ! count( $error ) )
{
	echo '<h3>Your RosarioSIS installation is properly configured.</h3>';
}


/**
 * Error Message
 *
 * Local function
 *
 * @param  array  $error Errors.
 * @param  string $code  error|fatal.
 *
 * @return string Errors HTML, exits if fatal error
 */
function _ErrorMessage( $error, $code = 'error' )
{
	if ( $error )
	{
		$return = '<table cellpadding="10"><tr><td style="text-align:left;"><p style="font-size:larger;">';

		if ( count( $error ) == 1 )
		{
			if ( $code === 'error'
				|| $code === 'fatal' )
			{
				$return .= '<b><span style="color:#CC0000">Error:</span></b> ';
			}
			else
				$return .= '<b><span style="color:#00CC00">Note:</span></b> ';

			$return .= ( ($error[0]) ? $error[0] : $error[1] );
		}
		else
		{
			if ( $code === 'error'
				|| $code === 'fatal' )
			{
				$return .= '<b><span style="color:#CC0000">Errors:</span></b>';
			}
			else
				$return .= '<b><span style="color:#00CC00">Note:</span></b>';

			$return .= '<ul>';

			foreach ( (array) $error as $value )
			{
				$return .= '<li>' . $value . '</li>';
			}

			$return .= '</ul>';
		}

		$return .= '</p></td></tr></table><br />';

		if ( $code === 'fatal' )
		{
			echo $return;

			exit;
		}

		return $return;
	}
}
