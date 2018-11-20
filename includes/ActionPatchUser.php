<?php

namespace CART;
/**
* Handles basic SQL Select by ID
*/
class ActionPatchUser extends Action
{
	/**
	* Constructor
    * @param string $tTable Primary table name to select from
    * @param string $tPasswordField field for password
	* @param string $tPasswordTable table for password
	*/
	public function __construct( $tTable, $tUserID, $tUpdateImage, $tBinds = null )
	{
		// Inheritance
		parent::__construct( $tTable, $tBinds );

		// Set variables
		$this->userID = $tUserID;
		$this->updateImage = $tUpdateImage;
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
				if( !( $this->updateImage ) )
				{
					if( isset( $tempData->password ) && isset( $tempData->new_password ) )
					{
						if( $this->validatePassword( $tempData, $tempConnection ) )
						{
							$tempPassword = password_hash( $tempData->new_password, PASSWORD_DEFAULT );
							( new ActionUpdate( $this->table, [ "name" => true, "email" => true, "id"=>false, "role" => true,  "password" => true, "new_password" => false, "organization_id"=>true, "phone"=>true ], "WHERE id=" . $this->userID, [ "password" => $tempPassword ] ) )->execute( $tAPI );					
						}
						else
						{
							$tAPI->getOutput()->addError( "Invalid Password" );
							http_response_code( 401 );
						}
					}
					else{
						( new ActionUpdate( $this->table, [ "name" => true, "email" => true,  "id"=>false, "role" => true, "organization_id"=>true, "phone"=>true ], "WHERE id=" . $this->userID ) )->execute( $tAPI );
					}
				}
				if( $this->updateImage )
				{


					$imageFileType = $tempData->type;

					$curTime =  strtotime("now");
					// target directory
					$target_dir =  $_SERVER['DOCUMENT_ROOT'] ."/uploads/". "profile_" . (string)$this->userID . $curTime. ".". $imageFileType;

					$data = base64_decode( $tempData->data );
					$directory = "/uploads/" . "profile_" . (string)$this->userID .  $curTime . ".". $imageFileType;

					if( file_put_contents( $target_dir, $data ) )
					{
						$tempStatement = $tempConnection->prepare("UPDATE users SET image_path=:image_path WHERE id=:id" ); 

						if ( !( $tempStatement->execute( [ "image_path" => $directory, "id" => $this->userID ] ) ) )
						{
							$tAPI->getOutput()->addError( $tempConnection->error );
							http_response_code( 400 );
						}
						else
						{
							echo $directory;
							http_response_code(200);
						}
					}
				}
			}
			else
			{
				$tAPI->getOutput()->addError( $tempStatement->errorInfo() );
                http_response_code( 500 );						
            }
		}
	}
	

	public function validatePassword( $tTempData, $tConnection )
	{
		$tempPassword = password_hash( $tTempData->password, PASSWORD_DEFAULT );
		$tempStatement = $tConnection->prepare( "SELECT " . "*" . " FROM " . $this->table . " WHERE id=:id" );

		if ( $tempStatement->execute( [ "id" => $this->userID ] ) )
		{
			$tempList = $tempStatement->fetchAll( \PDO::FETCH_ASSOC );
			if( ( $tempList === false || count( $tempList ) == 0 ) )
			{
				return false;
			}
			else
			{
				return password_verify( $tTempData->password, $tempList[0]["password"] );
			}
		}
	}



}
?>