<?php

namespace CART;
/**
* Allows for patching a group by its ID
*/
class ActionPatchGroup implements IAction
{
	/**
	* Constructor
	* @param integer $tGroupID ID of the group to patch
	*/
	public function __construct( $tGroupID )
	{
		// Set variables
		$this->groupID = $tGroupID;
	}
	/**
	* Executes SQL queries to add and delete users from this group 
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
				$tempConnection->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION );
				// turn off autocommit in case we screw up somehow
				$tempConnection->beginTransaction();
				$error = false;
				try
                {
					$statement = $tempConnection->prepare( "UPDATE groups SET name = :name, owner_id = :owner_id WHERE id = :id" );
					$statement->execute( [ "name"=>$tempData->name, "owner_id"=>$tempData->owner_id, "id" => $this->groupID ] );
                }
                catch ( \PDOException $e)
                {
                    $error = true;
					$tAPI->getOutput()->addError( $e->getMessage() );
					$tAPI->getOutput()->addError( "database error: " . $e->getCode() . " during update on root object" );
                }

				$tAdds = $tempData->add;
				foreach ( $tAdds as $add )
				{
					if ( $error ) { break; }
					try{
						$statement = $tempConnection->prepare( "INSERT INTO users_groups_map (user_id, group_id) VALUES (:user_id,:group_id)" );
						$tBinds = [
							"user_id"=>$add,
							"group_id"=>$this->groupID
						];
						$statement->execute( $tBinds );
					}
					catch ( \PDOException $e )
					{
						$error = true;
						$tAPI->getOutput()->addError( $e->getMessage() );
						$tAPI->getOutput()->addError( "database error: " . $e->getCode() . " during adds" );
						http_response_code( 500 );
						break;
					}
				}

				$tDeletes = $tempData->delete;
				foreach ( $tDeletes as $delete )
				{
					if ( $error ) { break; }
					try{
						$statement = $tempConnection->prepare( "DELETE FROM users_groups_map WHERE user_id = :user_id AND group_id = :group_id" );
						$tBinds = [
							"user_id"=>$delete,
							"group_id"=>$this->groupID
						];
						$statement->execute( $tBinds );
					}
					catch ( \PDOException $e )
					{
						$error = true;
						$tAPI->getOutput()->addError( $e->getMessage() );
						$tAPI->getOutput()->addError( "database error: " . $e->getCode() . " during adds" );
						http_response_code( 500 );
						break;
					}
				}

				if ( !$error )
				{
					$tempConnection->commit();
					http_response_code( 200 );
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