<?php

namespace CART;
/**
* Handles basic SQL Select by ID
*/
class ActionGetUser extends Action
{
	/**
	* @var string Name of id column in table 
	*/
	public $IDColumn;

	/**
	* @var string Primary table name to select from
	*/
	public $table;
    
    /**
	* @var string username field in input JSON
	*/
	public $userField;
        
    /**
	* @var string username field in input JSON
	*/
    public $userColumn;

	/**
	* @var string password field in input JSON
	*/
	public $passwordField;

    /**
	* @var string password table in database
	*/
	public $passwordColumn;
	
	/**
	* @var string field name to return token in
	*/
	public $tokenColumn;
	
    
	/**
	* Constructor
    * @param string $tTable Primary table name to select from
    * @param string $tPasswordField field for password
	* @param string $tPasswordTable table for password
	*/
	public function __construct( $tIDColumn, $tTable, $tUserField, $tUserColumnn, $tPasswordField, $tPasswordColumn, $tTokenColumn )
	{
		parent::__construct( $tTable, null );

		$this->IDColumn = $tIDColumn;
        $this->userField = $tUserField;
        $this->userColumn = $tUserColumnn;
        $this->passwordField = $tPasswordField;
		$this->passwordColumn = $tPasswordColumn;
		$this->tokenColumn = $tTokenColumn; 
	}

	// sourced from: https://gist.github.com/odan/1d4ff4c4088e906a5a49
	private function verify_password_hash($strPassword, $strHash)
	{
		if (function_exists('password_verify')) {
			// php >= 5.5
			$boolReturn = password_verify($strPassword, $strHash);
		} else {
			$strHash2 = crypt($strPassword, $strHash);
			$boolReturn = $strHash == $strHash2;
		}
		return $boolReturn;
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
                if ( isset( $tempData->{ $this->userColumn } ) && isset( $tempData->{ $this->passwordColumn } ) )
				{
					$tempStatement = $tempConnection->prepare( "SELECT u.*, o.id AS organization FROM users AS u 
						LEFT JOIN organizations AS o ON o.id = u.organization_id WHERE u.email=:email" );
					if ( $tempStatement->execute( [ "email" => $tempData->{ $this->userColumn } ] ) )
					{
						$tempList = $tempStatement->fetchAll( \PDO::FETCH_ASSOC );

						if ( $tempList === false || count( $tempList ) == 0 )
						{
							$tAPI->getOutput()->addError( "Invalid Email" );
							http_response_code( 401 );
						}
						else
						{
							if( verify_password_hash( $tempData->{$this->passwordColumn}, $tempList[0]["password"] ) )
							{
								$tempList = ( new BindObjects( $tempList, array( "organization"=>"organizations") ) )->execute( $tAPI );
								$tempToken = $tAPI->getAuthorization()->encode( $tempList[0]["id"], $tempList[0]["name"], $tempList[0]["email"], $tempList[0]["role"], $tempList[0]["phone"], $tempList[0]["organization"], $tempList[0]["image_path"]  );
								$tempRefreshToken = bin2hex( openssl_random_pseudo_bytes(32)) . $tempList[0]["id"];
								$this->setRefreshToken( $tempList[0]["id"], $tempRefreshToken, $tempConnection, $tAPI );

								$tempOutput =
								[
									"access_token" => $tempToken,
									"token_type" => "Bearer",
									"refresh_token" => $tempRefreshToken
								];

								$tAPI->getOutput()->setData( $tempOutput ); 
							}
							else
							{
								$tAPI->getOutput()->addError( "Invalid Password" );
								http_response_code( 401 );						
							}
						}
					}
					else
					{
						$tAPI->getOutput()->addError( $tempStatement->errorInfo() );
						http_response_code( 500 );						
					}
				}
				else
				{
					$tAPI->getOutput()->addError( "Missing Username or Password" );
					http_response_code( 400 );
				}
			}
		}
	}
	


	public function setRefreshToken( $tUserID, $tToken, $tConnection, $tAPI )
	{
		$tempStatement = $tConnection->prepare("UPDATE users SET refresh_token=:refresh_token WHERE id=:id" ); 

		if ( !( $tempStatement->execute( [ "refresh_token" => $tToken, "id" => $tUserID ] ) ) )
		{
			$tAPI->getOutput()->addError( $tConnection->error );
			http_response_code( 400 );
		}
	}


}
?>