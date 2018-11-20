<?php

namespace CART;
/**
* Handles basic SQL Select by ID
*/
class ActionRevokeRefreshToken implements IAction
{
    
    /**
	* @var string table we are querying
	*/
    public $table;

    /**
	* @var string property name of refresh token json
	*/
    public $refreshTokenColumn;
    

	/**
	* Constructor
    * @param string $tRefreshTokenColumn property name of refrsh token json
	*/
	public function __construct( $tTable, $tRefreshTokenColumn )
	{
        $this->table = $tTable;
        $this->refreshTokenColumn = $tRefreshTokenColumn;
	}
	
	/**
	* Executes an SQL query to retreive a user ID given the user ID and password, also issues/returns a token 
	* @param IAPI $tAPI API that called this function
	*/
	public function execute( IAPI $tAPI )
	{
        if( isset( $_GET[ "token" ] ) ) 
        {
            $tempConnection = null;
		    if ( $tAPI->getConnection()->tryConnect( $tAPI, $tempConnection ) )
            {
                $tempStatement = $tempConnection->prepare( "UPDATE " . $this->table . " SET " . $this->refreshTokenColumn . "= NULL" . " WHERE ". $this->refreshTokenColumn . "=:token" );
                
				if ( $tempStatement->execute( [ "token" => $_GET["token"] ] ) )
				{
                    if( $tempStatement->rowCount() == 1 )
                    {
                        http_response_code( 204 );
                    }
                    else
                    {
                        $tAPI->getOutput()->addError( "Invalid Refresh Token" );
                        http_response_code( 401 );
                    }
                }
                else
                {
                    $tAPI->getOutput()->addError( $tempStatement->errorInfo() );
                    http_response_code( 400 );			
                }
            }
        }
        else
        {
            $tAPI->getOutput()->addError( "Missing refresh token parameter" );
            http_response_code( 400 );
        }
    }

}
?>