<?php

namespace CART;
/**
* Handles basic SQL Select by ID
*/
class ActionValidateEmail extends Action
{

    	
	/**
    * @var string
    * column to validate for uniqueness
	*/
    public $tEmail;
    
	/**
	* Constructor
    * @param string $tTable Primary table name to select from
    * @param string $tPasswordField field for password
	* @param string $tPasswordTable table for password
	*/
	public function __construct( $tTable, $tEmail, $tBinds = null )
	{
		// Inheritance
		parent::__construct( $tTable, $tBinds );
		
		// Set variables
		$this->email = $tEmail;
	}
	/**
	* Executes an SQL query to retreive a user ID given the user ID and password, also issues/returns a token 
	* @param IAPI $tAPI API that called this function
	*/
	public function execute( IAPI $tAPI )
	{
        $tempData = null;

		if ( $tAPI->getInput()->tryGet( $tAPI, $tempData ) )
		{
            $tempConnection = null;
			if( isset( $tempData->email ) )
			{
				if ( $tAPI->getConnection()->tryConnect( $tAPI, $tempConnection ) )
				{
					$tempStatement = $tempConnection->prepare("SELECT " . "*" . " FROM " . $this->table . " WHERE ". $this->email . " = :email"  );
					$tempStatement->execute( [ "email" => $tempData->email ] ) ;
				
					$tempList = $tempStatement->fetchAll( \PDO::FETCH_ASSOC );
					echo ( $tempList === false || count( $tempList ) == 0 );
					http_response_code( 200 );						
					
				//$tAPI->getOutput()->setData( json_encode( $tempToken ) );
				}
				else
				{
					$tAPI->getOutput()->addError( $tempStatement->errorInfo() );
					http_response_code( 500 );						
				
				}
			}
			else{
				$tAPI->getOutput()->addError( "Missing Email" );
				http_response_code( 400 );
			}
		}
	}
	


}
?>