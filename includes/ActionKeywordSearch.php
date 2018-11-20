<?php

namespace CART;
/**
* Handles basic SQL Select by ID
*/
class ActionKeywordSearch extends Action
{
	public function __construct( $tURI, $tDocIDs )
	{
		parent::__construct( null, null );
		$this->search = null;
		$this->section = null;
		$this->URI = $tURI;
		// An array of integers that represents the document IDs the authenticated users has access to 
		$this->docIDs = $tDocIDs;
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
			if ( count( $this->URI ) == 2 )
			{
				if ( strtolower( $this->URI[1] ) != "template" && strtolower( $this->URI[1] ) != "document" && strtolower( $this->URI[1] ) != "goldmaster" )
				{
					echo "Invalid route, see <a href='" . $tAPI->getHelpLink() . "'>API help</a> for more information";
					http_response_code( 400 );
					return;
				}

				$this->search = strtolower( $_GET[ "search" ] );
				if ( isset( $_GET[ "section" ] ) )
				{
					$this->section = $_GET[ "section" ];
				}
				$query = "SELECT d.id, d.title, dd.document_data, d.owner_id, d.checkout_id, d.title, d.last_modified, d.organization_id, d.type
				FROM public.documents AS d 
				JOIN public.documents_data AS dd ON dd.document_id = d.id ";

				// keyword/template or keyword/document 
				switch ( strtolower( $this->URI[1] ) )
				{
					case "template":
						$query .= "WHERE dd.document_data::text ILIKE '%' || :keyword || '%' AND template = true";
						break;
					case "document":
						$query .= "WHERE dd.document_data::text ILIKE '%' || :keyword || '%' AND template = false AND d.id IN (";

						for ( $i = 0; $i < count( $this->docIDs ); $i++ )
						{
							$query .= $this->docIDs[$i] . ",";
						}
						
						$query = rtrim( $query, ',' );
						$query .= ")";
						break;
					case "goldmaster":
						$query = "WHERE dd.document_data::text ILIKE '%' || :keyword || '%' AND gold_master = true";
						break;
					default:
						echo "Invalid route, see <a href='" . $tAPI->getHelpLink() . "'>API help</a> for more information";
						http_response_code( 400 );
						exit;
				}

				if ( $this->section != null )
				{
					$query .= " AND dd.document_data::text ILIKE '%' || :section || '%'";
				}

				$statement = $tempConnection->prepare( $query );
				$statement->bindValue( ":keyword", $this->search );
				if ( $this->section != null )
				{
					$statement->bindValue( ":section", $this->section );
				}
				$statement->execute();
				$results = $statement->fetchAll(\PDO::FETCH_ASSOC);
				$tDocIDs = [];
				$resultCount = count( $results );
				// if the user specified a section title to search in, throw out documents that don't have the keyword in the specifed section
				if ( isset( $_GET[ "section" ] ) )
				{
					foreach ( $results as $key => $result )
					{
						$found = false;
						$sectionMatches = [];
						//recursively search through this result for matches to the search phrase in its content
						$this->searchForTitle( json_decode( $result["document_data"] ), $found, $sectionMatches );
						if ( $found == false )
						{
							unset( $results[ $key ] );
						}
						else
						{
							// If they are doing a keyword search, they just need section matches within each document and not the whole thing.
							unset( $results[ $key ]["document_data"] );
							$results[ $key ][ "matches" ] = $sectionMatches;
						}
					}
					$resultCount = count( $results );
				}


				if ( $resultCount > 0 )
				{
					for ( $i = 0; $i < $resultCount; $i++ )
					{
						array_push( $tDocIDs, $results[$i]["id"] );
					}
				}
				
				if ( $resultCount > 0 )
				{
					// Real data was found, bind that stuff
					// Build a WHERE clause for doc IDs IN
					$docWHERE = "WHERE document_id IN (";
					for ( $w = 0; $w < $resultCount; $w++ )
					{
						$docWHERE .= $tDocIDs[$w] . ",";
					}
					$docWHERE = rtrim( $docWHERE, "," );
					$docWHERE .= ")";

					// Get tags associated with documents
					$tTags = ( new ActionGet( "documents_tags_map", "tag_id AS tag, document_id", $docWHERE ) )->execute( $tAPI );
					$tTags = ( new BindObjects( $tTags, array( "tag"=>"tags" ) ) )->execute( $tAPI );
					// Get a list of shared user IDs only for document results
					$tUsers = ( new ActionGet( "documents_users_map", "user_id AS user, document_id", $docWHERE ) )->execute( $tAPI );
					$tUsers = ( new BindObjects( $tUsers, array( "user"=>"users" ) ) )->execute( $tAPI );
					// Get a list of groups
					$tGroups = ( new ActionGet( "documents_groups_map", "group_id AS group, document_id", $docWHERE ) )->execute( $tAPI );
					$tGroups = ( new BindObjects( $tGroups, array( "group"=>"groups" ) ) )->execute( $tAPI );
					for ( $i = 0; $i < $resultCount; $i++ )
					{
						$results[$i]["users"] = [];
						$results[$i]["tags"] = [];
						$results[$i]["groups"] = [];

						foreach( $tUsers as $user )
						{
							if ( $user["document_id"] == $results[$i]["id"] )
							{
								array_push( $results[$i]["users"], $user["user"] );
							}
						}
						foreach( $tTags as $tag )
						{
							if ( $tag["document_id"] == $results[$i]["id"] )
							{
								array_push( $results[$i]["tags"], $tag["tag"] );
							}
						}
						foreach( $tGroups as $group )
						{
							if ( $group["document_id"] == $results[$i]["id"] )
							{
								array_push( $results[$i]["groups"], $group["group"] );
							}
						}
					}
				}

				switch ( strtolower( $this->URI[1] ) )
				{
					case "template":
						$results = ( new BindObjects( $results, array( "organization_id"=>"organizations" ) ) )->execute( $tAPI );
						break;
					case "document":
						$results = ( new BindObjects( $results, array( "owner_id"=>"users", "organization_id"=>"organizations", "checkout_id"=>"users" ) ) )->execute( $tAPI );
						break;
				}
				$tAPI->getOutput()->setData( $results );
			}
			else
			{
				echo "Invalid route, see <a href='" . $tAPI->getHelpLink() . "'>API help</a> for more information";
				http_response_code( 400 );
			}
		}
		else
		{
			$tAPI->getOutput()->addError( "Database connection error." );
			http_response_code( 500 );
		}
	}

	private function searchForTitle( $tData, &$found, &$tMatches )
	{
		if ( isset( $tData->content ) )
		{
			if ( $tData->main == $this->section )
			{
				if ( strpos( strtolower( $tData->content ), $this->search ) >= 0 )
				{
					$found = true;
					array_push( $tMatches, array( "main"=>$tData->main, "content"=>$tData->content ) );
				}
			}
		}

		if ( isset( $tData->sections ) )
		{
			// At the top level, documents can have an arbitrary number of sections in each sections array, iterate over those first
			foreach( $tData->sections as $subsection )
			{
				$this->searchForTitle( $subsection, $found, $tMatches );
			}
		}

		if ( isset( $tData->subsection ) )
		{
			foreach( $tData->subsection as $subsection )
			{
				$this->searchForTitle( $subsection, $found, $tMatches );
			}
		}
	}
}
?>