<?php

namespace CART;
/**
* Handles basic SQL Select by ID
*/
class ActionRefreshAccessToken implements IAction
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
	* @var string property name of access token
	*/
    public $accessTokenColumn;

	/**
	* Constructor
    * @param string $tRefreshTokenColumn property name of refrsh token json
	*/
	public function __construct( $tTable, $tRefreshTokenColumn, $tAccessTokenColumn )
	{
        $this->table = $tTable;
        $this->refreshTokenColumn = $tRefreshTokenColumn;
        $this->accessTokenColumn = $tAccessTokenColumn;
	}
	
	/**
	* Executes an SQL query to return a new access token provided a refresh token 
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
                if ( isset( $tempData->{ $this->refreshTokenColumn } ) )
				{
					//Refresh token column includes ID, so do not need to check for uniqueness with user ID
                    $tempStatement = $tempConnection->prepare( "SELECT " . "*" . " FROM " . $this->table . " WHERE ". $this->refreshTokenColumn . "=:refesh_token" );
					if ( $tempStatement->execute( [ "refesh_token" => $tempData->{ $this->refreshTokenColumn } ] ) )
			        {   
						$tempList = $tempStatement->fetchAll( \PDO::FETCH_ASSOC );
						if ( $tempList === false || count( $tempList ) == 0 )
						{
							$tAPI->getOutput()->addError( "Invalid Refresh Token" );
							http_response_code( 401 );
						}
						else
						{
							$tempToken = $tAPI->getAuthorization()->encode( $tempList[0]["id"], $tempList[0]["full_name"], $tempList[0]["email"], $tempList[0]["role"], $tempList[0]["image_path"] );
							$tempOutput =
							[
								"access_token" => $tempToken,
								"token_type" => "Bearer",
								"refresh_token" => $tempData->{ $this->refreshTokenColumn }
							];
							$tAPI->getOutput()->setData( $tempOutput ); 
						}
						//$tAPI->getOutput()->setData( json_encode( $tempToken ) );
                    }
					else
					{
						$tAPI->getOutput()->addError( $tempStatement->errorInfo() );
						http_response_code( 500 );	
					}
				}
				else
				{
					$tAPI->getOutput()->addError( "Missing refesh token" );
					http_response_code( 400 ); 
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