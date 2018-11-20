<?php

namespace CART;
/**
* Allows for patching a group by its ID
*/
class ActionPatchDocument implements IAction
{
	/**
	* Constructor
	* @param integer $tDocID id of document to patch
	*/
	public function __construct( $tDocID, $tDocData = false )
	{
		// Set variables
		$this->docID = $tDocID;
		$this->docData = $tDocData;
	}
	/**
	* Executes SQL queries to add and delete users from this group 
	* @param IAPI $tAPI API that called this function
	*/
	public function execute( IAPI $tAPI )
	{
        $tempData = null;
		$error = false;

		if ( $tAPI->getInput()->tryGet( $tAPI, $tempData ) )
		{

            $tempConnection = null;
			if ( $tAPI->getConnection()->tryConnect( $tAPI, $tempConnection ) )
			{
				$tempConnection->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION );
                // turn off autocommit
                $tempConnection->beginTransaction();

                //Update document_data table if this field is set
				if ( isset( $tempData->document_data ) )
				{
                    ( new ActionPatch( "document_data",  [ "document_data"=>true ], " WHERE document_id=". $this->docID ) )->execute( $tAPI );
                }
                
                //Update comment_data table if this field is set
				if ( isset( $tempData->comment_data ) )
				{
                    ( new ActionPatch( "comment_data",  [ "comment_data"=>true ], " WHERE document_id=". $this->docID ) )->execute( $tAPI );
                }
                
                // Step one - UPDATE on edit root document ID    
                $tempUpdateFieldOpts = [ "status"=>true, "title"=>true, "type"=>true, "checkout"=>true, "template"=>true, "gold_master"=>true, "checkout_id"=>true, "last_modified"=>true, "organization_id"=>true ];

                ( new ActionPatch( "documents", $tempUpdateFieldOpts, " WHERE id=". $this->docID ) )->execute( $tAPI );

                //Group array add and deletes
                if( isset($tempData->groups) )
                {
                    $tempGroups = $tempData->groups;
                    $tempArray = $tempGroups->add;
                    if( !empty( $tempArray ) )
                    {
                        $error = $this->insert($tempConnection, $tAPI, $tempArray, "documents_groups_map", "group_id");
                    }
              
                    $tempDeleteArray = $tempGroups->delete;
                    if( !empty( $tempDeleteArray ) )
                    {
                        $this->deleteIDs( $tempDeleteArray, "documents_groups_map", "group_id", $tAPI, $tempConnection );
                    }
                }

                //Tag array add and deletes
                if( isset( $tempData->tags ) )
                {
                    $tempTags = $tempData->tags;
                    $tempArray = $tempTags->add;
                    if( !empty( $tempArray ) )
                    {
                        $error = $this->insert($tempConnection, $tAPI, $tempArray, "documents_tags_map", "tag_id");
                    }
                    
                    $tempDeleteArray = $tempTags->delete;
                    if( !empty( $tempDeleteArray ) )
                    {
                        $this->deleteIDs( $tempDeleteArray, "documents_tags_map", "tag_id", $tAPI, $tempConnection );
                    }
                }

                //User array add and deletes
                if( isset( $tempData->tags ) )
                {
                    $tempUsers = $tempData->users;
                    $tempArray = $tempUsers->add;
                    if( !empty( $tempArray ) )
                    {
                        $error = $this->insert($tempConnection, $tAPI, $tempArray, "documents_users_map", "user_id");
                    }
                
                    $tempDeleteArray = $tempUsers->delete;
                    if( !empty( $tempDeleteArray ) )
                    {
                        $this->deleteIDs( $tempDeleteArray, "documents_users_map", "user_id", $tAPI, $tempConnection );
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
    
    public function insert(  $tConnection, $tAPI, $tArray, $tTable,  $tField )
    {
        try{
            $tempQuery = "INSERT INTO " . $tTable . " (document_id, ".$tField.") VALUES " ;
            foreach( $tArray as $add )
            {
                $tempQuery.="(". $this->docID .",".$add ." ),";
            }
            $tempQuery = substr($tempQuery,0, -1);
            $statement = $tConnection->prepare( $tempQuery );
            $statement->execute();
            return false;
        }
        catch ( \PDOException $e )
        {
            echo "ERROR";
            $error = true;
            $tAPI->getOutput()->addError( "database error: " . $e->getCode() . " during insert" );
            $tAPI->getOutput()->addError( $e->getMessage() );
            http_response_code( 500 );
            return true;
        }
    }


    
    public function deleteIDs( $tDeleteArray, $tTable, $tField, $tAPI, $tConnection )
    {
        try{
            $tempQuery = "DELETE FROM " . $tTable . " WHERE document_id =". $this->docID." AND ".$tField." IN (";
            foreach( $tDeleteArray as $delete )
            {
                $tempQuery.=$delete.",";
            }
            $tempQuery = substr($tempQuery,0, -1);
            $tempQuery.=");";

            $statement = $tConnection->prepare( $tempQuery );
            $statement->execute();
            return false;
        }
        catch ( \PDOException $e )
        {
            $error = true;
            $tAPI->getOutput()->addError( "database error: " . $e->getCode() . " during insert" );
            $tAPI->getOutput()->addError( $e->getMessage() );
            http_response_code( 500 );
            return true;
        }     
	}
    
    // tField = field we are updating, can be document_data or comment_data
	private function updateData( $tempConnection, $tempData, $tField )
	{

        $error = false;
        try
        {
            $statement = $tempConnection->prepare( "UPDATE ". $tField ." SET " . $tField = ":" .$tField . " WHERE document_id = :document_id" );
            $statement->execute( [ $tField=>$tempData->$tField, "document_id" => $this->docID ] );
            $error = false;
        }
        catch ( \PDOException $e )
        {
            $tAPI->getOutput()->addError( "database error: " . $e->getCode() . " during document data update: " . $e->getMessage() );
            $error = true;
            //Set this here, don't even need to return to function
            http_response_code( 500 );
        }

        return $error;

	}

}
?>