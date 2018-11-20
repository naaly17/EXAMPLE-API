<?php

namespace CART;
/**
* Handles basic SQL Select by ID
*/
class ActionGetDocuments extends Action
{
	public function __construct( $tURI, $tType )
	{
		parent::__construct( null, null );
		$this->URI = $tURI;
		$this->type = $tType;
	}
	
	/**
	* Executes an SQL query to retreive documents given a user ID, must first get all groups user is associated with
	* @param IAPI $tAPI API that called this function
	*/
	public function execute( IAPI $tAPI )
	{
		$tempConnection = null;
		
		if ( $tAPI->getConnection()->tryConnect( $tAPI, $tempConnection ) )
		{
			switch( $this->type )
			{
				case "users":
					$this->getAllDocumentsUser( $tAPI, $tempConnection );
					break;
				case "groups":
					$this->getAllDocumentsGroup( $tAPI, $tempConnection );
					break;
				case "all":
					$this->getAllDocuments( $tAPI, $tempConnection );
					break;
				default:
					break;
			}
		}
	}

	//CARTAPI/documents/users/userID
	public function getAllDocumentsUser( $tAPI, $tConnection ) 
	{
		if ( isset( $this->URI[2] ) )
		{
			$tempConnection = null;
			if ( $tAPI->getConnection()->tryConnect( $tAPI, $tempConnection ) )
			{
				//Get a list of all documents the user is owner of, all gold master documents, all documents shared with user and documents associated with group user is in 
				$statement = $tempConnection->prepare( "SELECT json_agg( json_build_object( 'id', d.id, 'type', d.type, 'status', d.status, 'owner_id', (
							SELECT json_build_object( 'id', u.id, 'name', u.name, 'email', u.email, 'phone', u.phone, 'image_path', u.image_path, 'organization_id', u.organization_id )
							FROM users u WHERE u.id = d.owner_id
						), 'title', d.title, 'last_modified', to_char( d.last_modified, 'YYYY-MM-DD HH24:MI:SSOF' ), 'gold_master', d.gold_master, 'checkout', d.checkout, 'organization_id', (
							SELECT json_build_object( 'id', o.id, 'name', o.name )
							FROM organizations o WHERE o.id = d.organization_id
						), 'checkout_id', d.checkout_id, 'template', d.template, 'tags', (
							SELECT COALESCE( json_agg( json_build_object( 'id', t.id, 'name', t.name ) ), '[]' )
							FROM documents_tags_map tm JOIN tags t ON t.id = tm.tag_id WHERE tm.document_id = d.id
						), 'users', (
							SELECT COALESCE( json_agg( json_build_object( 'id', u.id, 'name', u.name, 'email', u.email, 'image_path', u.image_path ) ), '[]' )
							FROM documents_users_map um JOIN users u ON u.id = um.user_id WHERE um.document_id = d.id
						), 'groups', (
							SELECT COALESCE( json_agg( json_build_object( 'id', g.id, 'name', g.name, 'owner_id', g.owner_id ) ), '[]' )
							FROM documents_groups_map gm JOIN groups g ON g.id = gm.group_id WHERE gm.document_id = d.id
						)
					) ) FROM documents d LEFT JOIN documents_users_map gu ON gu.document_id = d.id WHERE gu.user_id = :user_id 
						OR d.gold_master = TRUE OR d.owner_id = :user_id;" );
				$statement->execute( [ "user_id"=>$this->URI[2] ] );
				$tData = $statement->fetchAll();
				$tData = json_decode( $tData[0]["json_agg"] );
				$tAPI->getOutput()->setData( $tData );
			}
		}
	}


	//CARTAPI/documents/groups/{groupID}
	//Get all documents associated with groupID
	//OR
	//CARTAPI/documents/groups/{groupID}/users/{userID}
	//Get all documents associated with groupID & {userID}
	public function getAllDocumentsGroup( $tAPI, $tConnection ) 
	{
		if ( isset( $this->URI[2] ) && is_numeric( $this->URI[2] ) )
		{
			$tempArray = [];
			$tempUniqueArray = [];
			
			//CARTAPI/documents/groups/{groupID}
			//Get a list of all documents associated with a group 			
			$tempStatement = $tConnection->prepare(	"SELECT d.* FROM documents AS d
													JOIN documents_groups_map AS dg ON dg.document_id = d.id
													WHERE dg.group_id = :group_id AND d.gold_master = FALSE AND d.template = FALSE
													GROUP BY d.id");

			if ( $tempStatement->execute( [ "group_id" => $this->URI[2] ] ) )
			{  
				$tempArray = $tempStatement->fetchAll( \PDO::FETCH_ASSOC );
			}

			if( count( $tempArray ) > 0 )
			{
				foreach( $tempArray as $i =>$item )
				{
					$tempStatement = $tConnection->prepare( "SELECT t.* FROM tags as t
															JOIN documents_tags_map AS dt ON t.id = dt.tag_id
															WHERE dt.document_id =:document_id" );
					if ( $tempStatement->execute( [ "document_id" => $item["id"]  ] ) )
					{ 
						$item["tags"] = $tempStatement->fetchAll( \PDO::FETCH_ASSOC );
					}	
					if( ! $item["gold_master"] )
					{
						$tempStatement = $tConnection->prepare( "SELECT d.id, d.name, d.email, d.image_path FROM users AS d
																JOIN documents_users_map AS dg ON dg.user_id = d.id
																WHERE dg.document_id = :document_id" );

						if ( $tempStatement->execute( [ "document_id" => $item["id"]  ] ) )
						{ 
							$item["users"] = $tempStatement->fetchAll( \PDO::FETCH_ASSOC );
						}
						//USER ID
						if( count( $this->URI ) == 5 )
						{
							$tempStatement = $tConnection->prepare( "SELECT d.* FROM groups AS d
																	JOIN documents_groups_map AS dg ON dg.group_id = d.id
																	JOIN users_groups_map AS ug ON ug.group_id = dg.group_id
																	WHERE dg.document_id =:document_id AND ug.user_id = :user_id" );
											
							if ( $tempStatement->execute( [ "document_id" => $item["id"], "user_id" => $this->URI[4]  ] ) )
							{
								$item["groups"] = $tempStatement->fetchAll( \PDO::FETCH_ASSOC );
							}
						}
						//ADMIN
						else
						{
							$tempStatement = $tConnection->prepare( "SELECT d.* FROM groups AS d
																	JOIN documents_groups_map AS dg ON dg.group_id = d.id
																	JOIN users_groups_map AS ug ON ug.group_id = dg.group_id
																	WHERE dg.document_id =:document_id" );
											
							if ( $tempStatement->execute( [ "document_id" => $item["id"] ] ) )
							{
								$item["groups"] = $tempStatement->fetchAll( \PDO::FETCH_ASSOC );
							}	
						}
					$tempUniqueArray[ $item["id"] ] = $item; 
					}		
				}
			}

			$tempUniqueArray = array_values( $tempUniqueArray );
			$tempUniqueArray = ( new BindObjects( $tempUniqueArray, array( "owner_id"=>"users", "organization_id"=>"organizations" ) ) )->execute( $tAPI );
			$tAPI->getOutput()->setData( $tempUniqueArray );
		}
	}

	//CARTAPI/documents/
	//ADMIN - get ALL documents
	public function getAllDocuments( $tAPI, $tConnection ) 
	{
		$tempConnection = null;
		if ( $tAPI->getConnection()->tryConnect( $tAPI, $tempConnection ) )
		{
			$statement = $tempConnection->prepare( "SELECT json_agg( json_build_object( 'id', d.id, 'type', d.type, 'status', d.status, 'owner_id', (
						SELECT json_build_object( 'id', u.id, 'name', u.name, 'email', u.email, 'phone', u.phone, 'image_path', u.image_path, 'organization_id', u.organization_id )
						FROM users u WHERE u.id = d.owner_id
					), 'title', d.title, 'last_modified', to_char( d.last_modified, 'YYYY-MM-DD HH24:MI:SSOF' ), 'gold_master', d.gold_master, 'checkout', d.checkout, 'organization_id', (
						SELECT json_build_object( 'id', o.id, 'name', o.name )
						FROM organizations o WHERE o.id = d.organization_id
					), 'checkout_id', d.checkout_id, 'template', d.template, 'tags', (
						SELECT COALESCE( json_agg( json_build_object( 'id', t.id, 'name', t.name ) ), '[]' )
						FROM documents_tags_map tm JOIN tags t ON t.id = tm.tag_id WHERE tm.document_id = d.id
					), 'users', (
						SELECT COALESCE( json_agg( json_build_object( 'id', u.id, 'name', u.name, 'email', u.email, 'image_path', u.image_path ) ), '[]' )
						FROM documents_users_map um JOIN users u ON u.id = um.user_id WHERE um.document_id = d.id
					), 'groups', (
						SELECT COALESCE( json_agg( json_build_object( 'id', g.id, 'name', g.name, 'owner_id', g.owner_id ) ), '[]' )
						FROM documents_groups_map gm JOIN groups g ON g.id = gm.group_id WHERE gm.document_id = d.id
					)
				) ) FROM documents d;" );
			$statement->execute();
			$tData = $statement->fetchAll();
			$tData = json_decode( $tData[0]["json_agg"] );
			$tAPI->getOutput()->setData( $tData );
		}
	}


}
?>