<?php

namespace CART;
/**
* Handles basic SQL Select by ID
*/
class ActionGetGroup extends Action
{
	public function __construct( $tURI )
	{
		parent::__construct( null, null );
		$this->URI = $tURI;
	}
	
	/**
	* Executes an SQL query to retreive a group, optionally with an ID and an associated user array
	* @param IAPI $tAPI API that called this function
	*/
	public function execute( IAPI $tAPI )
	{
		$tempConnection = null;
		if ( $tAPI->getConnection()->tryConnect( $tAPI, $tempConnection ) )
		{
			if ( !isset( $this->URI[1] ) )
			{
				$tData = ( new ActionGet( "groups", "*, (SELECT COUNT(*) FROM documents_groups_map AS gm WHERE gm.group_id = groups.id) AS document_count" ) )->execute( $tAPI );
				$tUsers = ( new ActionGet( "users_groups_map", "user_id AS user, group_id" ) )->execute( $tAPI );
				$tUsers = ( new BindObjects( $tUsers, array( "user"=>"users" ) ) )->execute( $tAPI );
				$tOrgs = ( new ActionGet( "organizations", "*" ) )->execute( $tAPI );
				
				for( $i = 0; $i < count( $tOrgs ); $i++ )
				{
					for( $u = 0; $u < count( $tUsers ); $u++ )
					{
						if ( $tUsers[$u]["user"]["organization_id"] == $tOrgs[$i]["id"] )
						{
							$tUsers[$u]["user"]["organization_id"] = $tOrgs[$i];
							break;
						}
					}
				}

				$tCount = count( $tData );
				for ( $i = 0; $i < $tCount; $i++ )
				{
					$tData[$i]["users"] = [];
					foreach( $tUsers as $user )
					{
						if ( $user["group_id"] == $tData[$i]["id"] )
						{
							array_push( $tData[$i]["users"], $user["user"] );
						}
						if ( $tData[$i]["owner_id"] == $user["user"]["id"] )
						{
							$tData[$i]["owner_id"] = $user["user"];
						}
					}
				}

				$tAPI->getOutput()->setData( $tData );
			}
			else
			{
				$tempLength = count( $this->URI );
				//CARTAPI/group/{groupID}
				if ( $tempLength == 2 )
				{
					// Getting a group by its ID
					if ( !is_numeric( $this->URI[1] ) )
					{
						$tAPI->getOutput()->addError( "Group ID must be an integer." );
						http_response_code( 400 );
					}
					else
					{
						$tData = ( new ActionGet( "groups", "*", "WHERE id = " . (int)$this->URI[1], "limit", "offset" ) )->execute( $tAPI );
						$tUsers = ( new ActionGet( "users_groups_map", "user_id AS user, group_id" ) )->execute( $tAPI );
						$tUsers = ( new BindObjects( $tUsers, array( "user"=>"users" ) ) )->execute( $tAPI );
						$tCount = count( $tData );
						for ( $i = 0; $i < $tCount; $i++ )
						{
							$tData[$i]["users"] = [];
							foreach( $tUsers as $user )
							{
								if ( $user["group_id"] == $tData[$i]["id"] )
								{
									array_push( $tData[$i]["users"], $user["user"] );
								}
							}
						}

						$tData = ( new BindObjects( $tData, array( "owner_id"=>"users" ) ) )->execute( $tAPI );
						$tAPI->getOutput()->setData( $tData );
					}
				}
				//CARTAPI/group/user/{userID}
				else if( $tempLength == 3 )
				{
					$tempArray = [];
					$finalArray = [];

					// Getting all groups associated with a user ID, and users that belong to the group
					if ( !is_numeric( $this->URI[2] ) )
					{
						$tAPI->getOutput()->addError( "Group ID must be an integer." );
						http_response_code( 400 );
					}
					else
					{
						$tempStatement = $tempConnection->prepare( "SELECT d.*, (SELECT COUNT(*) FROM documents_groups_map AS gm WHERE gm.group_id = d.id) AS document_count FROM groups AS d
																	JOIN users_groups_map AS ug ON ug.group_id = d.id
																	WHERE ug.user_id = :user_id
																	GROUP BY d.id");

						if ( $tempStatement->execute( [ "user_id"=>$this->URI[2] ] ) )
						{  
							$tempArray = $tempStatement->fetchAll( \PDO::FETCH_ASSOC );
						}

						if( count( $tempArray ) > 0 )
						{
							foreach( $tempArray as $i =>$item )
							{
								$tempStatement = $tempConnection->prepare( "SELECT t.id, t.name, t.email, t.image_path FROM users as t
																			JOIN users_groups_map AS dt ON t.id = dt.user_id
																			WHERE dt.group_id =:group_id" );

								if ( $tempStatement->execute( [ "group_id" => $item["id"]  ] ) )
								{ 
									$item["users"] = $tempStatement->fetchAll( \PDO::FETCH_ASSOC );
								}	
								$finalArray[] = $item;
							}
						}

						$finalArray = ( new BindObjects( $finalArray, array( "owner_id"=>"users" ) ) )->execute( $tAPI );
						$tAPI->getOutput()->setData( $finalArray );
					}
				}

				else
				{
					echo "Invalid route, see <a href='" . $tAPI->getHelpLink() . "'>API help</a> for more information";
					http_response_code( 400 );
				}
			}
		}
	}
}
?>