<?php

namespace CART;
/**
* Handles the retrieval of gold masters for the gold_masters endpoint
*/
class ActionGetGoldMasters extends Action
{
	public function __construct()
	{
		parent::__construct( null, null );
	}
	
	/**
	* Retrieves all buckets and the documents associated with them
	* @param IAPI $tAPI API that called this function
	*/
	public function execute( IAPI $tAPI )
	{
		$tempConnection = null;
		if ( $tAPI->getConnection()->tryConnect( $tAPI, $tempConnection ) )
		{
			$statement = $tempConnection->prepare( "SELECT json_agg( json_build_object( 'id', b.id, 'name', b.name, 'gold_masters', (
						SELECT json_agg( json_build_object( 'id', d.id, 'type', d.type, 'owner_id', (
							SELECT json_build_object( 'id', u.id, 'name', u.name, 'email', u.email, 'phone', u.phone )
							FROM users u WHERE u.id = d.owner_id
						), 'title', d.title, 'last_modified', d.last_modified, 'organization_id', (
							SELECT json_build_object( 'id', o.id, 'name', o.name )
							FROM organizations o WHERE o.id = d.organization_id
						), 'tags', (
							SELECT json_agg( json_build_object( 'id', t.id, 'name', t.name ) )
							FROM documents_tags_map tm JOIN tags t ON t.id = tm.tag_id WHERE tm.document_id = d.id
						)
					) )  
					FROM documents d JOIN documents_buckets_map bm ON d.id = bm.document_id
					WHERE bm.bucket_id = b.id AND d.gold_master = TRUE
					) )
				) FROM buckets b;" );
			$statement->execute();
			$tData = $statement->fetchAll();
			$tData = json_decode( $tData[0]["json_agg"] );
			$tAPI->getOutput()->setData( $tData );
		}
	}
}