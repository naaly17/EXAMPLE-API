<?php

namespace CART;

/**
* Handles an UPDATE query, or a general modification using data input
*/
class ActionPatch extends Action
{
	/**
	* @var string Associative array of allowed columns
	*/
	public $columns;
	
	/**
	* @var string Condition statement
	*/
	public $conditions;
	
	/**
	* Constructor
	* @param string $tTable Table name to operate on
	* @param array $tColumns Set of allowed columns, [ "document_data"=>true]
	* @param string $tConditions (optional) Condition statement
	*/
	public function __construct( $tTable, $tColumns, $tConditions = null )
	{
		// Inheritance
		parent::__construct( $tTable );
		
		// Set variables
		$this->columns = $tColumns;
		$this->conditions = $tConditions;
	}
	
	/**
	* Executes an query to update an item
	* @param IAPI $tAPI API that called this function
	*/
	public function execute( IAPI $tAPI )
	{
		$tempData = null;
		if ( $tAPI->getInput()->tryGet( $tAPI, $tempData ) )
		{
			$tempConnection = null;
			
			//For now, create temporary array to hold binds, can also look back and use unset to use less extra space
			$tData = null;

			if ( $tAPI->getConnection()->tryConnect( $tAPI, $tempConnection ) )
			{
				// Prepare Query statement
				$tempQuery = " SET ";
				$tempCount = 0;

				if ( $tempData != null )
				{
					foreach ( $tempData as $tempKey => $tempValue )
					{
						if ( array_key_exists( $tempKey, $this->columns ) && $this->columns[ $tempKey ] )
						{
							$tempQuery .= $tempKey . "=:" . $tempKey . ",";
							$tempCount++;

							if( $tData == null )
							{
								$tData = array();
							}
							$tData[$tempKey] = $tempValue;
						}
					}
				}
				
				//If tData == null, then there is nothing to update
				if ( $tData == null )
				{
					$tAPI->getOutput()->addError( "No valid input data given" );
					http_response_code( 400 );
				}
				else
				{
					//Trim off last comma
					$tempQuery = rtrim( $tempQuery, ",");
					$tempQuery = "UPDATE " . $this->table . $tempQuery;

					if ( $this->conditions != null )
					{
						$tempQuery .= " " . $this->conditions;
					}

					$tempStatement = $tempConnection->prepare( $tempQuery );

					if ( !$tempStatement->execute( $tData ) )
					{
						$tAPI->getOutput()->addError( $tempStatement->errorInfo() );
						http_response_code( 500 );
					}
					else if ( $tempStatement->rowCount() == 0 )
					{
						http_response_code( 404 );
					}
					else
					{
						http_response_code( 204 );
					}
				}
			}
		}
	}
}

?>