<?php

namespace CART;
/**
* Handles basic SQL Select by ID
*/
class ActionResetPassword implements IAction
{
    
    /**
	* @var string table we are querying
	*/
    public $table;

    /**
	* @var string property name of refresh token json
	*/
    public $resetColumn;

    /**
	* @var string property name of refresh token json
	*/
    public $resetTokenColumn;
    
    /**
	* @var string property name of access token
	*/
	public $resetTokenExpireColumn;
	
	/**
	* @var integer amount of time until expiration of token
	*/
	public $expireTime;
	
	/**
	* @var boolean true if this is for adding a new user
	*/
    public $newUser;

	/**
	* Constructor
    * @param string $tRefreshTokenColumn property name of refrsh token json
	*/
	public function __construct( $tTable, $tResetColumn, $tResetTokenColumn, $tResetTokenExpireColumn, $tExpireTime, $tNewUser )
	{
        $this->table = $tTable;
        $this->resetColumn = $tResetColumn;
        $this->resetTokenColumn = $tResetTokenColumn;
		$this->resetTokenExpireColumn = $tResetTokenExpireColumn;
		$this->expireTime = $tExpireTime;
		$this->newUser = $tNewUser;
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
                if ( isset( $tempData->{ $this->resetColumn } ) )
				{
					$this->sendReset( $tempData, $tempConnection, $tAPI );
				}
				else if( isset( $tempData->reset_token ) && isset( $tempData->new_password ) )
				{
					$this->resetPassword( $tempData, $tempConnection, $tAPI );
				}
			}
            else
			{
				$tAPI->getOutput()->addError( $tempConnection->error );
				http_response_code( 500 );
			}
        }
        
	}
	
	public function resetPassword( $tTempData, $tConnection, $tAPI )
	{
		$tempStatement = $tConnection->prepare( "UPDATE " . $this->table . " SET password = :new_password WHERE reset_password_token = :reset_token AND reset_password_expire > :reset_expire" );
		$tempPassword =  create_password_hash( $tTempData->new_password, PASSWORD_DEFAULT );
		if ( $tempStatement->execute( [ "new_password" => $tempPassword, "reset_token" => $tTempData->reset_token, "reset_expire" => time() ] ) )
		{   
			$rowCount = $tempStatement->rowCount();
			if ( $rowCount == 0 )
			{
				$tAPI->getOutput()->addError( "Invalid reset token" );
				http_response_code( 401 );
			}
			else
			{
				$tempStatement = $tConnection->prepare( "UPDATE " . $this->table . " SET reset_password_token = null, reset_password_expire = null WHERE reset_password_token = :reset_token" );
				$tempStatement->execute( [ "reset_token"  => $tTempData->reset_token ] ) ;
				http_response_code( 204 );
			}
		}
	}
	
	public function sendReset( $tTempData, $tConnection, $tAPI )
	{
		$tempStatement = $tConnection->prepare("SELECT " . "*" . " FROM " . $this->table . " WHERE ". $this->resetColumn . "=:reset_column"  );

		if ( $tempStatement->execute( [ "reset_column" => $tTempData->{ $this->resetColumn } ] ) )
		{   
			$tempList = $tempStatement->fetchAll( \PDO::FETCH_ASSOC );
			if ( $tempList === false || count( $tempList ) == 0 )
			{
				$tAPI->getOutput()->addError( "" );
				http_response_code( 401 );
			}
			else
			{
				$this->sendResetEmail( $tTempData->{ $this->resetColumn }, bin2hex( openssl_random_pseudo_bytes( 32 ) ), $tempList[0]["full_name"], $tConnection, $tAPI );
			}
			//$tAPI->getOutput()->setData( json_encode( $tempToken ) );
		}
	}


    public function sendResetEmail( $tEmail, $tToken, $tFullName, $tConnection, $tAPI )
	{
		$tempExpire = time() + $this->expireTime;
		$tempStatement = $tConnection->prepare( "UPDATE " . $this->table . " SET reset_password_token=:reset_token , reset_password_expire=:reset_expire WHERE email=:email" );
		$msg = "";
		if ( $tempStatement->execute( [ "reset_token" => $tToken, "reset_expire" => $tempExpire, "email" => $tEmail] ) )
        {   
			if( $this->newUser )
			{
				$msg .= $tFullName . ", a Force Structure account has been created with your email. To login for the first time: \n\n";
			}
            //create email message
			$msg .= "username: ". $tEmail . "\n \n Please use temporary token to reset password at: http://weg-dev.idsi.com/FSAdmin/password-reset/" . $tToken .
				 "\n \n This email was sent from an unmonitored address, please do not reply.";
			$headers = "From: odin.support@idsi.com" . "\r\n";
            //$msg = wordwrap( $msg, 70 );
            // send email
			mail( $tEmail, "Request to Reset Your Force Structure Password", $msg, $headers );
			if( $this->newUser )
			{
				http_response_code( 200 );
			}
			else{
				http_response_code( 204 );
			}
        }
        else
        {
            $tAPI->getOutput()->addError( "Invalid Email" );
            http_response_code( 401 );
        }
	}

}
?>