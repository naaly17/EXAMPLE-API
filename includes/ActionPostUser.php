<?php

namespace CART;
/**
* Handles basic SQL Select by ID
*/
class ActionPostUser extends Action
{
	/**
	* Constructor
    * @param string $tTable Primary table name to select from
    * @param string $tPasswordField field for password
	* @param string $tPasswordTable table for password
	*/
	public function __construct( $tTable )
	{
		parent::__construct( $tTable, null );
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
            
			if ( $tAPI->getConnection()->tryConnect( $tAPI, $tempConnection ) )
			{
				if( ( new ActionPost( $this->table, [ "name"=>true, "email"=>true, "phone"=>true, "role"=>true, "organization_id"=>true ], null, "users_id_seq" ) )->execute( $tAPI ) )
				{
                ( new ActionResetPassword( "users", "email", "reset_password_token", "reset_password_expire", 2592000, true ) )->execute( $tAPI ) ;
				}
            }
			else
			{
				$tAPI->getOutput()->addError( $tempStatement->errorInfo() );
                http_response_code( 500 );						
            }
		}
	}
	


}
?>