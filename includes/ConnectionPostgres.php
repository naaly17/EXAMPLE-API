<?php

namespace CART;

/**
* Component responsible for connecting to an SQL database
*/
class ConnectionPostgres extends Connection
{
	/**
	* Constructor
	* @param string $tHost Host address
	* @param string $tDatabase Database name
	* @param string $tUser Database user name
	* @param string $tPassword Database user password
	* @param string $tCharSet (optional) Character set to use
	*/
	public function __construct( $tHost, $tDatabase, $tUser, $tPassword, $tCharSet = null )
	{
		// Generate DSN
		$tempDSN = "pgsql:dbname=" . $tDatabase . ";host=" . $tHost . ';port=5432';
		if ( !empty( $tCharSet ) )
		{
			$tempDSN .= ";charset=" . $tCharSet;
		}
		
		// Inheritance
		parent::__construct( $tempDSN, $tUser, $tPassword );
	}
}

?>