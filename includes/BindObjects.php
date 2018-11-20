<?php

namespace CART;

class BindObjects
{
	// Allows organizations and users to be included with results

	/**
	* @var array Key value pairs of columns to tables
	* The bindings will consist of an "id" column in the array and the table with related data for that column
	* $tBindings = array( "user"=>"users", "organization"=>"organizations" );
	*/
	public $columnBindings;

	/**
	* @var array An array of results from a MySQL query to bind additional data to
	*/
	public $resultsArray;

	public function __construct( $tArray, $tBindings )
	{
		$this->columnBindings = $tBindings;
		$this->resultsArray = $tArray;
	}

	public function execute( IAPI $tAPI )
	{
		$tempConnection = null;
		if ( $tAPI->getConnection()->tryConnect( $tAPI, $tempConnection ) )
		{
			foreach ( $this->columnBindings as $key => $value )
			{
				$tIDs = [];
				foreach ( $this->resultsArray as $row )
				{
					if ( !in_array( $row{ $key }, $tIDs) && $row{ $key } != null )
					{
						array_push( $tIDs, $row{ $key } );
					}
				}

				$tQuery = "";
				if ( $value == "users" )
				{
					// The users table needs special treatment to prevent from retrieving authentication info
					$tQuery = "SELECT id, name, email, phone, image_path, organization_id FROM ";
				}
				else
				{
					$tQuery = "SELECT * FROM ";
				}
				$tQuery .= $value . " WHERE id IN (";
				foreach ( $tIDs as $id )
				{
					$tQuery .= $id . ',';
				}
				$tQuery = rtrim( $tQuery, ',' );
				$tQuery .= ")";
				$tempQueryResult = $tempConnection->prepare( $tQuery );
				$tempQueryResult->execute();
				$results = $tempQueryResult->fetchAll(\PDO::FETCH_ASSOC);
				// Create a dictionary of results for easy use with IDs as keys
				$resultDict = [];
				foreach ( $results as $result )
				{
					$resultDict[ $result["id"] ] = $result;
				}
				$tCount = count( $this->resultsArray );
				for ( $i = 0; $i < $tCount; $i++ )
				{
					if ( $this->resultsArray[$i][$key] != null )
					{
						$this->resultsArray[$i][$key] = $resultDict{ $this->resultsArray[$i][$key] };
					}
				}
			}

			return $this->resultsArray;
		}
		else
		{
			$this->getOutput()->addError( "Database error during BindObjects process" );
			http_response_code( 500 );
		}
	}
}

?>