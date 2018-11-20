<?php

namespace CART;
//NOTE: need to clean-up/combine code

class CARTAPI extends API
{
	
	protected function createConnection()
	{
		return new ConnectionPostgres( "localhost", "database_name", "user", "password" );
	}
	
	protected function createAuthorization()
	{
		return new Authorization("secret OAuth key **&6%%%@# rakforeakfpoIAKKKak943i0943mlkremflkEEamf");
	}
	
	protected function createInput()
	{
		return new InputJSON();
	}
	
	protected function createOutput()
	{
		return new OutputJSON();
	}
	
	/**
	 * return help link to API documentation
	 */
	public static function getHelpLink()
	{
  		return "http://" . $_SERVER[ "HTTP_HOST" ] . $_SERVER[ "SCRIPT_NAME" ] . "/../help/";
	}

	/**
	* Sets a 405 Method Not Allowed response, and sets the Access-Control-Allow-Methods header with the allowed methods for this endpoint
	* @param String $tMethods A string of allowed methods for this endpoint
	*/
	public static function methodNotAllowed( $tMethods )
	{
		header( "Access-Control-Allow-Methods: " . $tMethods );
		http_response_code( 405 );
	}

	/**
	* Sets and returns a 400 Invalid Route response
	*/
	public static function invalidRoute( )
	{
		echo "Invalid route, see <a href='" . CARTAPI::getHelpLink() . "'>API help</a> for more information";
		http_response_code( 400 );
	}


	/**
	 * Validate data type
	 * @param tValue : type unknown, variable to  be checked for numeric
	 * @param $tReponse: id that is not valid
	 */
	public function validateType( $tValue, $tResponse )
	{
		if( is_numeric( $tValue ))
		{
			return True;
		}

		//else False, return 400 error
		http_response_code( 400 );
		$this->getOutput()->addError(  $tReponse . " ID must be an integer." );
	}


	//Access URI[0]
	public function executeRoute( $tURI )
	{
        if ( $tURI == null || empty( $tURI[0] ) )
		{
			echo "No route specified, see <a href='" . CARTAPI::getHelpLink() . "'>API help</a> for more information";
			http_response_code( 400 );
		}
		else
		{
			switch ( strtolower( $tURI[0] ) )
			{
				case "documents":
					$this->routeDocuments( $tURI );
					break;
				case "gold_masters":
					$this->routeGoldMasters( $tURI );
					break;
				case "groups":
					$this->routeGroups( $tURI );
					break;
				case "keywords":
					$this->routeKeywordSearch( $tURI );
					break;
				case "organizations":
					$this->routeOrganizations( $tURI );
					break;
				case "tags":
					$this->routeTags( $tURI );
					break;
				case "templates":
					$this->routeTemplates( $tURI );
					break;
				case "users":
					$this->routeUsers( $tURI );
					break;
				default:
					CARTAPI::invalidRoute();
					break;
			}
		}
	}

	//CARTAPI/documents
	public function routeDocuments( $tURI )
	{
		$tempLength = count( $tURI );
		switch ( $_SERVER[ "REQUEST_METHOD" ] )
		{
			case "GET":
				switch( $tempLength )
				{
					case 1:
						//CARTAPI/documents/
						( new ActionGetDocuments( $tURI, "all" ) )->execute( $this );
						break;
					case 2:
						//CARTAPI/documents/{docID}
						//Get information associated with an individual doc ID
						if ( $this->validateType( $tURI[1], "Document" ))
						{
							$tData = ( new ActionGet( "documents AS d JOIN documents_data AS dd ON dd.document_id = d.id LEFT JOIN comments AS dc ON dc.document_id = d.id", "d.*, dd.document_data, dc.id AS comment_id, dc.comment_data", " WHERE d.id = " . (int)$tURI[1], "limit", "offset" ) )->execute( $this );	
							$tData = ( new BindObjects( $tData, array( "owner_id"=>"users", "organization_id"=>"organizations" ) ) )->execute( $this );
							$tTags = ( new ActionGet( "documents_tags_map", "tag_id AS tag, document_id", " WHERE document_id = " . (int)$tURI[1], "limit", "offset"  ) )->execute( $this );
							$tTags = ( new BindObjects( $tTags, array( "tag"=>"tags" ) ) )->execute( $this );
							$tCount = count( $tData );
							for ( $i = 0; $i < $tCount; $i++ )
							{
								$tData[$i]["tags"] = [];
								foreach( $tTags as $tag )
								{
									array_push( $tData[$i]["tags"], $tag["tag"] );
								}
							}
							$this->getOutput()->setData( $tData[0] );
							http_response_code( 200 );
						}
						break;
					case 3:
						//CARTAPI/documents/groups/{groupID}
						//Get all documents associated with a group ID: ADMIN
						if( $tURI[1] == "groups" )
						{
							( new ActionGetDocuments( $tURI, "groups" ) )->execute( $this );
						}
						//CARTAPI/documents/users/{userID}
						//Get all documents associated with a user ID: USER
						else if( $tURI[1] == "users" )
						{
							if ( $this->validateType( $tURI[2], "User" ))
							{
								( new ActionGetDocuments( $tURI, "users" ) )->execute( $this );
							}
						}
						//CARTAPI/documents/{docID}/tags
						//Get all tags associated with a document ID : USER OR ADMIN
						else if( $tURI[2] == "tags" )
						{
							$tData = ( new ActionGet( "documents_tags_map as dt JOIN tags AS nt ON dt.tag_id = nt.id", "dt.id, dt.tag_id, nt.name", "WHERE dt.document_id = " . (int)$tURI[1], "limit", "offset" ) )->execute( $this );	
							$this->getOutput()->setData( $tData );
						}
						
						break;
					case 4:
						//CARTAPI/documents/groups/{groupID}/count
						//get Group's documents count : 
						if( $tURI[1] == "groups" && $tURI[3] == "count")
						{
							$tData = ( new ActionGet( "documents_groups_map", "COUNT(*)", "WHERE group_id = " . (int)$tURI[2], "limit", "offset" ) )->execute( $this );	
							$this->getOutput()->setData( $tData );
						}
						//CARTAPI/documents/tags/{tagID}/count
						//get Tag's documents count
						else if( $tURI[1] == "tags" && $tURI[3] == "count")
						{
							$tData = ( new ActionGet( "documents_tags_map", "COUNT(*)", "WHERE tag_id = " . (int)$tURI[2], "limit", "offset" ) )->execute( $this );	
							$this->getOutput()->setData( $tData[0] );
						}
						break;
					case 5:
						//CARTAPI/documents/groups/{groupID}/users/{userID}
						//Get all documents associated with a groupID and userID
						if ( $this->validateType( $tURI[2], "Group") && $this->validateType( $tURI[4], "Document" ))
						{
							return ( new ActionGetDocuments( $tURI, "groups" ) )->execute( $this );
						}
						break;
					default:
						CARTAPI::invalidRoute();
						break;
				}
				break;
			case "POST":
				$tAuth = $this->getAuthorization()->tryAuthorize( $this );
				// $tAuth = [ authorized, roleInt, user_id ]
				if ( $tAuth[0] == 1 )
				{
					// Both methods of creating documents require a title, so check for it once.
					if ( !isset( $_GET[ "title" ] ) )
					{
						http_response_code( 400 );
						$this->getOutput()->addError( "Must specify a new document title with the 'title' request URI." );
						return;
					}
					$tempCount = count( $tURI );
					
					// Duplicate
					// documents/{docID}/duplicate?title=""
					if ( $tempCount == 3 && $tURI[2] == "duplicate" )
					{
						if ( $this->validateType( $tURI[1], "Document" ))
						{
							( new ActionPostDocument( (int)$tURI[1], $tAuth[2], $_GET[ "title" ], null ) )->execute( $this );
							return;
						}
					}
					else if ( $tempCount == 2 && $tURI[1] == "new" )
					{
						// Create New
						// documents/new?title=""?type=""
						if ( !isset( $_GET[ "type" ] ) )
						{
							http_response_code( 400 );
							$this->getOutput()->addError( "Must specify a document type with parameters 'type' in request URI." );
							return;
						}
						( new ActionPostDocument( null, $tAuth[2], $_GET[ "title" ], $_GET[ "type" ] ) )->execute( $this );
						return;
					}
					else
					{
						CARTAPI::invalidRoute();
					}
				}
				else
				{
					http_response_code( 401 );
				}
				break;
			case "PATCH":
				//CARTAPI/documents/{docID}
				//patch (add/remove) document (tags, users, groups) by {docID} ADMIN or USER
				if ( $tempLength == 2 && $this->validateType( $tURI[1], "Document" ))
				{
					 ( new ActionPatchDocument( (int)$tURI[1] ) )->execute( $this );	
					 return;
				}	
							
				CARTAPI::invalidRoute();
				break;

			case "DELETE":
				//CARTAPI/documents/{docID}
				$tempLength = count( $tURI );
				if ( $tempLength == 2 && $this->validateType( $tURI[1], "Document" ))
				{
					 ( new ActionDelete( "documents", "WHERE id=".(int)$tURI[1] ) )->execute( $this );
					 return;
				}
				CARTAPI::invalidRoute();
				break;
			case "OPTIONS":
				header( "Allow: GET, POST, PATCH, DELETE, OPTIONS" );
				http_response_code( 204 );
				break;
			default:
				$this->methodNotAllowed( "GET, POST, PATCH, DELETE, OPTIONS" );
				break;
		}
	}



	public function routeGoldMasters( $tURI )
	{
		switch ( $_SERVER[ "REQUEST_METHOD" ] )
		{
			case "GET":
				if ( count( $tURI ) != 1 )
				{
					CARTAPI::invalidRoute();
					return;
				}
				( new ActionGetGoldMasters() )->execute( $this );
				break;
			case "OPTIONS":
				header( "Access-Control-Allow-Methods: GET, OPTIONS" );
				http_response_code( 200 );
				break;
			default:
				$this->methodNotAllowed( "GET, OPTIONS" );
				break;
		}
	}

	public function routeDeleteForm( $tURI )
	{
		switch ( $_SERVER[ "REQUEST_METHOD" ] )
		{
			case "DELETE":
				$this->getAuthorization();
				$docs = $this->authorization->getPermissions( $this );
				// We need to make sure that the user is authorized to delete the form that they intend to
				if ( count( $tURI ) != 2 )
				{
					// should be deleteform/orbrefstring
					CARTAPI::invalidRoute();
				}
				else
				{
					$tData = ( new ActionGet( "documents", "*", "WHERE orb_ref = '" . $tURI[1] . "'" ) )->execute( $this );
					if ( count( $tData ) == 1 )
					{
						if ( in_array( $tData[0]["id"], $docs->actionable ) )
						{
							( new ActionDeleteForm( $tData[0] ) )->execute( $this );
						}
						else
						{
							http_response_code( 401 );
							$this->getOutput()->addError( "You are not authorized to delete this document." );
						}
					}
					else if ( count( $tData ) == 0 )
					{
						http_response_code( 404 );
						$this->getOutput()->addError( "No entry found in documents." );
					}
					else
					{
						http_response_code( 500 );
						$this->getOutput()->addError( "You have more than one entry for this document ID - please contact a developer." );
					}
				}
				break;
			case "OPTIONS":
				header( "Access-Control-Allow-Methods: DELETE, OPTIONS" );
				http_response_code( 204 );
				break;
			default:
				$this->methodNotAllowed( "DELETE, OPTIONS" );
				break;
		}
	}

	public function routeTemplates( $tURI )
	{
		switch ( $_SERVER[ "REQUEST_METHOD" ] )
		{
			case "GET":
				$tempConnection = null;
				if ( $this->getConnection()->tryConnect( $this, $tempConnection ) )
				{
					$statement = $tempConnection->prepare( "SELECT json_agg( json_build_object('id', d.id, 'title', d.title, 'type', d.type, 'tags', (
						SELECT json_agg( json_build_object( 'id', t.id, 'name', t.name ) )
						FROM documents_tags_map tm JOIN tags t ON t.id = tm.tag_id
						WHERE tm.document_id = d.id
						)
					) ) FROM documents d WHERE d.template = TRUE;" );
					$statement->execute();
					$tData = $statement->fetchAll();
					$tData = json_decode( $tData[0]["json_agg"] );

					$this->getOutput()->setData( $tData );
				}
				break;
			case "OPTIONS":
				header( "Access-Control-Allow-Methods: GET, OPTIONS" );
				http_response_code( 204 );
				break;
			default:
				$this->methodNotAllowed( "GET, OPTIONS" );
				break;
		}
	}

	public function routeKeywordSearch( $tURI )
	{
		switch ( $_SERVER[ "REQUEST_METHOD" ] )
		{
			//CARTAPI/keyword/documents
			//OR
			//CARTAPI/keyword/templates
			case "GET":
				$this->getAuthorization();
				$docs = $this->authorization->getPermissions( $this );
				if ( $this->validateQueryStrings( [ "search"=>["string","Must provide a search term."] ] ) )
				{
					( new ActionKeywordSearch( $tURI, $docs->viewable ) )->execute( $this );
				}
				break;
			case "OPTIONS":
				header( "Access-Control-Allow-Methods: GET, OPTIONS" );
				http_response_code( 204 );
				break;
			default:
				$this->methodNotAllowed( "GET, OPTIONS" );
				break;
		}
	}

	
	

	public function routeTags( $tURI )
	{
		switch ( $_SERVER[ "REQUEST_METHOD" ] )
		{
			case "GET":
				if ( $this->getConnection()->tryConnect( $this, $tempConnection ) )
				{
					$statement = $tempConnection->prepare( "SELECT t.*, COALESCE(m.tag_count,0) AS tag_count FROM tags t
								LEFT JOIN 
								( SELECT tag_id, COUNT(*) AS tag_count 
									FROM documents_tags_map GROUP BY tag_id
								) AS m ON m.tag_id = t.id;" );
					$statement->execute();
					$tData = $statement->fetchAll(\PDO::FETCH_ASSOC);

					$this->getOutput()->setData( $tData );
				}
				break;
			case "POST":
				( new ActionPost( "tags", [ "name"=>true ], null, "tags_id_seq" ) )->execute( $this );
				break;
			case "PATCH":
				$tempLength = count( $tURI );
				if ( $tempLength == 2 && $this->validateType( $tURI[1], "Tag" ))
				{
					return ( new ActionPatch( "tags",  [ "name"=>true ], "WHERE id=".(int)$tURI[1] ) )->execute( $this );
				}
				CARTAPI::invalidRoute();
				break;
			case "DELETE":
				$tempLength = count( $tURI );
				if ( $tempLength == 2 && $this->validateType( $tURI[1], "Tag" ))
				{
					return ( new ActionDelete( "tags",  [ "name"=>true ], "WHERE id=".(int)$tURI[1] ) )->execute( $this );
				}
				CARTAPI::invalidRoute();
				break;
			case "OPTIONS":
				header( "Allow: GET, POST, PATCH, DELETE, OPTIONS" );
				http_response_code( 204 );
				break;
			default:
				$this->methodNotAllowed( "GET, POST, PATCH, DELETE, OPTIONS" );
				break;
		}
	}


	//CARTAPI/groups/
	public function routeGroups( $tURI )
	{
		switch ( $_SERVER[ "REQUEST_METHOD" ] )
		{
			//CARTAPI/groups/
			case "GET":
				( new ActionGetGroup( $tURI ) )->execute( $this );
				break;
			case "POST":
				$tempConnection = null;
				if ( $this->getInput()->tryGet( $this, $tempData ) )
				{
					if ( $this->getConnection()->tryConnect( $this, $tempConnection ) )
					{
						$statement = $tempConnection->prepare( "INSERT INTO groups (name, owner_id) VALUES (:name, :owner_id)" );
						$tBinds = [
							"name"=>$tempData->name,
							"owner_id"=>$tempData->owner_id
						];
						$statement->execute( $tBinds );
						$group_id = $tempConnection->lastInsertId( "groups_id_seq" );
						$statement = $tempConnection->prepare( "INSERT INTO users_groups_map (user_id, group_id) VALUES (:user_id, :group_id)" );
						$tBinds = [
							"user_id"=>$tempData->owner_id,
							"group_id"=>$group_id
						];
						$statement->execute( $tBinds );
						$tData = ( new ActionGet( "groups", "*", "WHERE id = " . $group_id ) )->execute( $this );
						if ( count( $tData ) == 0 )
						{
							http_response_code( 500 );
							$this->getOutput()->addError( "The group was not successfully created." );
						}
						else
						{
							$tData = ( new BindObjects( $tData, array( "owner_id"=>"users" ) ) )->execute( $this );
							$this->getOutput()->setData( $tData );
						}
					}
				}
				break;
			//CARTAPI/groups/{groupID}
			case "PATCH":
				$tempLength = count( $tURI );
				if ( $tempLength == 2 && $this->validateType( $tURI[1], "Group" ))
				{
					return ( new ActionPatchGroup( (int)$tURI[1] ) )->execute( $this );
				}
				CARTAPI::invalidRoute();
				break;
			case "DELETE":
				//CARTAPI/groups/{groupID}
				$tempLength = count( $tURI );
				if ( $tempLength == 2 && $this->validateType( $tURI[1], "Group" ))
				{
					return ( new ActionDelete( "groups", "WHERE id=".(int)$tURI[1] ) )->execute( $this );
				}
				CARTAPI::invalidRoute();
				break;
			case "OPTIONS":
				header( "Access-Control-Allow-Methods: GET, POST, OPTIONS" );
				http_response_code( 204 );
				break;
			default:
				$this->methodNotAllowed( "GET, POST, OPTIONS" );
				break;
		}
	}

	//CARTAPI/organizations/
	public function routeOrganizations( $tURI )
	{
		switch ( $_SERVER[ "REQUEST_METHOD" ] )
		{
			case "GET":
				$tempLength = count( $tURI );
				if ( $tempLength > 2 )
				{
					CARTAPI::invalidRoute();
					return;
				}
				else
				{
					if ( !isset( $tURI[1] ) || ( isset( $tURI[1] ) && is_numeric( $tURI[1] ) ) )
					{
						$tempConnection = null;
						if ( $this->getConnection()->tryConnect( $this, $tempConnection ) )
						{
							$tQuery = "SELECT o.*, COALESCE(u.user_count,0) AS user_count, COALESCE(d.document_count,0)
								AS document_count FROM organizations o 
								LEFT JOIN ( SELECT organization_id, COUNT(*) AS user_count 
									FROM users GROUP BY organization_id
								) AS u ON u.organization_id = o.id 
								LEFT JOIN (
								SELECT organization_id, COUNT(*) AS document_count 
									FROM documents GROUP BY organization_id
								) AS d ON d.organization_id = o.id ";

							if ( isset( $tURI[1] ) )
							{
								$tQuery .= " WHERE o.id = " . (int)$tURI[1];
							}

							$statement = $tempConnection->prepare( $tQuery );
							$statement->execute();
							$tData = $statement->fetchAll(\PDO::FETCH_ASSOC);

							$tDataLength = count( $tData );

							$this->getOutput()->setData( $tData );
						}
					}
					else
					{
						http_response_code( 400 );
						$this->getOutput()->addError( "Organization ID must be an integer." );
					}
				}
				break;
			case "POST":
				( new ActionPost( "organizations", [ "name"=>true ], null, "organizations_id_seq" ) )->execute( $this );
				break;
			case "PATCH":
				$tempLength = count( $tURI );
				if ( $tempLength == 2 && $this->validateType( $tURI[1], "Organization" ))
				{
					return ( new ActionPatch( "organizations",  [ "name"=>true], "WHERE id=".(int)$tURI[1] ) )->execute( $this );
				}
				CARTAPI::invalidRoute();
				break;
			case "DELETE":
				$tempLength = count( $tURI );
				if ( $tempLength != 2 )
				{
					CARTAPI::invalidRoute();
				}
				else
				{
					if ( !is_numeric( $tURI[1] ) )
					{
						http_response_code( 400 );
						$this->getOutput()->addError( "Organization ID must be an integer." );
					}
					else
					{
						$userCount = ( new ActionGet( "documents", "COUNT(*)", "WHERE organization_id = " . (int)$tURI[1] ) )->execute( $this );
						$docCount = ( new ActionGet( "users", "COUNT(*)", "WHERE organization_id = " . (int)$tURI[1] ) )->execute( $this );
						if ( $userCount[0]["count"] == 0 && $docCount[0]["count"] == 0 )
						{
							( new ActionDelete( "organizations", "WHERE id=".(int)$tURI[1] ) )->execute( $this );
						}
						else
						{
							$this->getOutput()->addError( "This organization cannot be deleted because it is still active." );
							http_response_code( 400 );
						}
					}
				}
				break;
			case "OPTIONS":
				header( "Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS" );
				http_response_code( 204 );
				break;
			default:
				$this->methodNotAllowed( "GET, POST, DELETE, OPTIONS" );
				break;
		}
	}


	public function routeUsersLogin( $tURI )
	{
		switch ( $_SERVER[ "REQUEST_METHOD" ] )
		{
			case "POST":
				( new ActionGetUser( "id", "users", "email", "email", "password", "password", "token" ) )->execute( $this ) ;
				break;
			case "OPTIONS":
				header( "Access-Control-Allow-Methods: POST, OPTIONS" );
				http_response_code( 204 );
				break;
			default:
				$this->methodNotAllowed( "POST, OPTIONS" );
				break;
		}
	}

	//user/{userID}/image
	//upload image
	//(should probably make a generic function for all of these)
	public function routeUsersFile( $tURI )
	{
		switch ( $_SERVER[ "REQUEST_METHOD" ] )
		{
			case "POST":
				( new ActionPatchUser( "users", ( int ) $tURI[1] , true ))->execute( $this );
				break;	
			case "OPTIONS":
				header( "Access-Control-Allow-Methods: POST, OPTIONS" );
				http_response_code( 204 );
				break;
			default:
				$this->methodNotAllowed( "POST, OPTIONS" );
				break;
		}
	}

	public function routeUsersFunctions( $tURI )
	{
		switch ( $_SERVER[ "REQUEST_METHOD" ] )
		{
				// Get a list of users ALL
				// users
			case "GET":
				// by organization ID
				// users/organization/{organizationID}
				$tConditions = null;
				if ( count($tURI )  == 3 && is_numeric($tURI[2]) )
				{
					$tConditions = "WHERE organization_id = " . $tURI[2];
				}
				$tData = ( new ActionGet( "users", "id, name, email, role, phone, organization_id AS organization, image_path", $tConditions, "limit", "offset" ) )->execute( $this );
				$tData = ( new BindObjects( $tData, array( "organization"=>"organizations" ) ) )->execute( $this );
				$this->getOutput()->setData( $tData );
				break;
			case "POST":
				( new ActionPostUser( "users" ) )->execute( $this );
				break;
			case "PATCH":
				( new ActionPatchUser( "users", ( int ) $tURI[1], false ))->execute( $this );
				break;
			case "OPTIONS":
				header( "Access-Control-Allow-Methods: GET, POST, PATCH, OPTIONS" );
				http_response_code( 204 );
				break;
			default:
				$this->methodNotAllowed( "GET, POST, PATCH, OPTIONS" );
				break;
		}
	}
	
	public function routeValidateEmail( $tURI )
	{
		switch ( $_SERVER[ "REQUEST_METHOD" ] )
		{
			case "POST":
				( new ActionValidateEmail( "users", "email" ) )->execute( $this );
				break;
			case "OPTIONS":
				header( "Access-Control-Allow-Methods: POST, OPTIONS" );
				http_response_code( 204 );
				break;
			default:
				$this->methodNotAllowed( "POST, OPTIONS" );
				break;
		}
	}
	


	public function routeUsers( $tURI )
	{
		if ( empty( $tURI[1] ) )
		{
			$this->routeUsersFunctions( $tURI );
		}
		else
		{
			$tempLength = count( $tURI );
			switch ( $tURI[1] )
			{
				case "login":
					if( $tempLength > 2 && $tURI[2] == "refresh" )
					{
						$this->routeRefreshToken( $tURI );
					}
					else
					{
						$this->routeUsersLogin( $tURI );
					}
					break;
				// Get a list of users by organization ID
				// users/organization/{organizationID}
				case "organizations":
					$this->routeUsersFunctions( $tURI );
					break;
				case "logout":
					$this->routeRefreshToken( $tURI );
					break;
				case "reset":
					$this->routeResetPassword( $tURI );
					break;
				case "validate":
					if( $tempLength > 2 && $tURI[2] == "email" )
					{
						$this->routeValidateEmail( $tURI );
					}
					else{
                        CARTAPI::invalidRoute();
					}
					break;
				case "groups":
					$tData = ( new ActionGet( "groups as g JOIN users_groups_map u ON u.group_id = g.id", "g.id, g.name, g.owner_id AS owner", "WHERE u.user_id = " . (int)$tURI[2], "limit", "offset" ) )->execute( $this );
					$tData = ( new BindObjects( $tData, array( "owner"=>"users" ) ) )->execute( $this );
					$this->getOutput()->setData( $tData );
					break;
					//I did this really dumb, should re-do (from Nadia)
				default:
                    if( is_numeric( $tURI[1] ) ) //user/{id}
                    {
						//user/{id}/image
						if( $tempLength == 3 && $tURI[2] == "image")
						{
							$this->routeUsersFile( $tURI );
							break;
						}
						$this->routeUsersFunctions( $tURI );
                    }
                    else{
                        CARTAPI::invalidRoute();
                    }
					break;
			}
		}
	}
	

	public function routeResetPassword( $tURI )
	{
		switch ( $_SERVER[ "REQUEST_METHOD" ] )
		{
			case "POST":
				( new ActionResetPassword( "users", "email", "reset_password_token", "reset_password_expire", 86400, false ) )->execute( $this ) ;
				break;
			case "OPTIONS":
				header( "Access-Control-Allow-Methods: POST, OPTIONS" );
				http_response_code( 204 );
				break;
			default:
				$this->methodNotAllowed( "POST, OPTIONS" );
				break;
		}
	}

	public function routeRefreshToken( $tURI )
	{
		switch ( $_SERVER[ "REQUEST_METHOD" ] )
		{
			case "POST":
				( new ActionRefreshAccessToken( "users", "refresh_token", "access_token" ) )->execute( $this ) ;
				break;
			case "GET":
				( new ActionRevokeRefreshToken( "users", "refresh_token") )->execute( $this ) ;
				break;
			case "OPTIONS":
				header( "Access-Control-Allow-Methods: POST, GET, OPTIONS" );
				http_response_code( 204 );
				break;
			default:
				$this->methodNotAllowed( "POST, GET OPTIONS" );
				break;
		}
	}

	public function authenticateUser( $tID )
	{
		$tempAuth = $this->getAuthorization();
		if( $tempAuth->tryAuthorizeUserID( $this, $tID) )
		{
			return true;
		}
		else
		{
			$this->getOutput()->addError( "Unauthorized" );
			http_response_code( 401 );
		}
	}

	// $parameters is an array of key values, example:
	// [ "doc_id"=>["int","Must provide a doc_id as an integer."] ]
	// This function checks that there is a querystring called doc_id. If $value[0] is "int", it makes sure that querystring is numeric
	// $key=>[1] is the error message supplied in the object
	public function validateQueryStrings( $parameters )
	{
		foreach( $parameters as $key => $value )
		{
			if ( !isset( $_GET[ $key ] ) )
			{
				$this->getOutput()->addError( $value[1] );
				http_response_code( 400 );
				return false;
			}

			if ( $value[0] == "int" && !is_numeric( $_GET[ $key ] ) )
			{
				$this->getOutput()->addError( $value[1] );
				http_response_code( 400 );
				return false;
			}
		}
		return true;
	}

	// [ "length"=>2, "positions"=>[ "string"=>"testURI", "string"=>"derf1", "int" ] ]
	// /testURI/derf1/1
	public function validateURI( $tURI, $checks )
	{
		if ( count( $tURI ) != $checks["length"] )
		{
			return false;
		}

		foreach( $checks["positions"] as $key => $value )
		{

		}

		return true;
	}


}