<?php

namespace CART;
/**
* Handles basic SQL Select by ID
*/
class ActionPostDocument extends Action
{
	public function __construct( $tDocID, $tUserID, $tTitle, $tType )
	{
		parent::__construct( null, null );
		$this->DocID = $tDocID;
		$this->user_id = $tUserID;
		$this->title = $tTitle;
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
			$error = false;
			$newDocID = null;
			$tempConnection->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION );
			$tempConnection->beginTransaction();
			if ( $this->DocID == null )
			{
				// Fresh document
				try
				{
					$statement = $tempConnection->prepare( "INSERT INTO documents (type, status, owner_id, title, last_modified, gold_master, checkout, organization_id, template)
						SELECT :type, 0, :user_id1, :title, current_timestamp, FALSE, FALSE, o.id, FALSE FROM
						( SELECT o.id FROM organizations o JOIN users u ON u.organization_id = o.id WHERE u.id = :user_id2 ) AS o;" );
					$statement->execute( [ "type"=>$this->type, "user_id1"=>$this->user_id, "title"=>$this->title, "user_id2"=>$this->user_id ] );
					$newDocID = $tempConnection->lastInsertId( "docs_id_seq" );

					// Now to copy the previous document's data into the documents_data table for this ID
					$statement = $tempConnection->prepare( "INSERT INTO documents_data (document_id, document_data) 
						VALUES ( :new_doc_id, '{}' )" );
					$statement->execute( [ "new_doc_id"=>$newDocID ] );
				}
				catch ( \PDOException $e )
				{
					$error = true;
					$tAPI->getOutput()->addError( $e->getMessage() );
					$tAPI->getOutput()->addError( "database error: " . $e->getCode() . " during creation of fresh document" );
				}
			}
			else
			{
				// Duplicate an existing document
				// Check to make sure the document id they want to duplicate actually exists
				$tData = ( new ActionGet( "documents", "id", " WHERE id = " . $this->DocID, "limit", "offset" ) )->execute( $tAPI );
				if ( count( $tData ) != 1 )
				{
					//Document ID not found in the database.
					http_response_code( 400 );
					$tAPI->getOutput()->addError( "No document found for the specified ID." );
					return;
				}

				try
				{
					$statement = $tempConnection->prepare( "INSERT INTO documents (type, status, owner_id, title, last_modified, gold_master, checkout, organization_id, template)
						SELECT d.type, 0, :user_id1, :title, current_timestamp, FALSE, FALSE, o.id, FALSE FROM
						( SELECT type FROM documents WHERE id = :doc_id ) AS d,
						( SELECT o.id FROM organizations o JOIN users u ON u.organization_id = o.id WHERE u.id = :user_id2 ) AS o;" );
						
					$statement->execute( [ "user_id1"=>$this->user_id, "title"=>$this->title, "doc_id"=>$this->DocID, "user_id2"=>$this->user_id ] );
					$newDocID = $tempConnection->lastInsertId( "docs_id_seq" );

					// Now to copy the previous document's data into the documents_data table for this ID
					$statement = $tempConnection->prepare( "INSERT INTO documents_data (document_id, document_data) 
						SELECT :new_doc_id, dd.document_data FROM documents_data dd WHERE dd.id = :old_doc_id" );
					$statement->execute( [ "new_doc_id"=>$newDocID, "old_doc_id"=>$this->DocID ] );
				}
				catch( \PDOException $e )
				{
					$error = true;
					$tAPI->getOutput()->addError( $e->getMessage() );
					$tAPI->getOutput()->addError( "database error: " . $e->getCode() . " during creation of duplicate document" );
				}
			}

			// Need to set an entry for document comments up
			try
			{
				$statement = $tempConnection->prepare( "INSERT INTO comments (document_id, comment_data) 
					VALUES ( :new_doc_id, '{}' )" );
				$statement->execute( [ "new_doc_id"=>$newDocID ] );
			}
			catch( \PDOException $e )
			{
				$error = true;
				$tAPI->getOutput()->addError( $e->getMessage() );
				$tAPI->getOutput()->addError( "database error: " . $e->getCode() . " during creation of comment field" );
			}

			if ( !$error )
			{
				$tempConnection->commit();
				$tAPI->getOutput()->setData( $newDocID );
				http_response_code( 201 );
			}
		}
		else
		{
			http_response_code( 500 );
			$tAPI->getOutput()->addError( "Database connection error" );
		}
	}
}