<?php

namespace CART;

/**
* Component responsible for validating user authorization
*/
class Authorization implements IAuthorization
{
	
	/**
	* @var string secret key
	*/
	protected $key;

		
	/**
	* @var int ( seconds ) token is valid for
	*/
	public $expiration;

	/**
	* Constructor
    * @param string $tKey secret key for encode/decoding
	* @param int $tExpiration time duration ( seconds ) for which token is valid
	*/
	public function __construct( $tKey, $tExpiration = 3600000 )
	{
		$this->key = $tKey;
		$this->expiration = $tExpiration;
	}

	/**
	* Validates user authorization
	* @param IAPI $tAPI API that called this function
	* @return bool True if successful
	*/
	public function tryAuthorize( IAPI $tAPI )
	{
		$tempToken = null;
		if( isset( $_SERVER["HTTP_AUTHENTICATION"] ) ) 
		{
			// Break apart Authorization token into header, payload, signature
			$tempToken = explode( ".", $_SERVER["HTTP_AUTHENTICATION"] );
			if ( count( $tempToken ) >= 3 )
			{

				// Check if payload contains valid expiration
				$payload = json_decode( base64_decode( strtr( $tempToken[1],'-_','+/') ), true );
				if ( $payload && isset( $payload[ "exp" ] ) && time() < $payload[ "exp" ] )
				{	
					// Evaluate signature
					$signature = bin2hex( base64_decode( strtr( $tempToken[2],'-_','+/') ) );
					if( bin2hex( base64_decode( strtr( $tempToken[2],'-_','+/') ) ) == hash_hmac( "sha256", "$tempToken[0].$tempToken[1]", $this->key ) )
					{
						return [true, $payload["role"], $payload["user_id"]];
					}
					else{
						$tAPI->getOutput()->addError( "Invalid Token" );						
					}
				}
				else{
					$tAPI->getOutput()->addError( "Token Expired" );

				}
			}
		}
		else{
			$tAPI->getOutput()->addError( "Authentication Header Not Set" );
		}

		return [ false ] ;
	}


	/**
	* Validates user authorization
	* @param IAPI $tAPI API that called this function
	* @return bool True if successful
	*/
	public function tryAuthorizeUserID( IAPI $tAPI, $tID )
	{
		$tempToken = null;	
		if( isset( $_SERVER["HTTP_AUTHENTICATION"] ) ) 
		{
			// Break apart Authorization token into header, payload, signature
			$tempToken = explode( ".", $_SERVER["HTTP_AUTHENTICATION"] );
			if ( count( $tempToken ) >= 3 )
			{
				// Check if payload contains valid expiration
				$payload = json_decode( base64_decode( strtr( $tempToken[1],'-_','+/') ), true );
				if( isset( $payload["user_id"] ) )
				{
					    return $payload["user_id"] == $tID;
				}
				else
				{
					$tAPI->getOutput()->addError( "Invalid Token" );						
				}
			}
		}
		else
		{
			$tAPI->getOutput()->addError( "Authentication Header Not Set" );
		}

		return false;
	}



	/**
	* Helper function to properly format data to base64 encode
	* @param string $tData data to encode
	*/
	public function base64url_encode( $tData )
	{
		return rtrim( strtr ( base64_encode( $tData ), '+/', '-_'), '=');
	}

	/**
	* Create the token for the user login in the format: header.payload.signature, encode
	* @param string $tUser Username for payload information
	* @param int $tUserID User ID for payload information
	*/
	public function encode( $tUserID, $tFullName, $tEmail, $tRole, $tPhone, $tOrganization, $tImagePath )
	{

		//build the headers
		$headers = [ "alg"=>"HS256","typ"=>"JWT" ];
		$headers_encoded = $this->base64url_encode( json_encode( $headers ) );

		//build the payload
		$payload = ["user_id"=>$tUserID ,"full_name"=>$tFullName, "email"=>$tEmail, "role"=>$tRole, "phone"=>$tPhone, "organization"=>$tOrganization, "image_path"=>$tImagePath, "iat"=>time(), "exp"=>( time()*1000 + $this->expiration ) ];
		$payload_encoded = $this->base64url_encode( json_encode( $payload ) );
		//print_r( $payload );
		
		//build the signature
		$signature = hash_hmac( "SHA256", "$headers_encoded.$payload_encoded", $this->key, true );
		$signature_encoded = $this->base64url_encode( $signature );

		//build and return the token
		return "$headers_encoded.$payload_encoded.$signature_encoded";

	}

	/**
	* Pass in an array of roles to check the user for. True if any are matched, false if none are matched.
	* @param array $checkArray Role values to check for
	* 0 = Super Administrator, 1 = Editor, 2 = Approver, 3 = Administrator
	*/
	public function getRoles( $tAPI, $checkArray )
	{
		$tempCredentials = $this->tryAuthorize( $tAPI );
		if ( count( $tempCredentials ) > 1 )
		{
			foreach ( $checkArray as $role )
			{
				if ( $tempCredentials[1] == $role )
				{
					return true;
				}
			}
			return false;
		}
		else
		{
			return false;
		}
	}

	public function getPermissions( $tAPI )
	{
		$tempConnection = null;
		if ( $tAPI->getConnection()->tryConnect( $tAPI, $tConnection ) )
		{
			$tempCredentials = $this->tryAuthorize( $tAPI );
			if ( $tempCredentials[0] == 0 )
			{
				http_response_code( 401 );
				$tAPI->getOutput()->addError( "Unauthorized" );
			}
			else
			{
				//print_r( $tempCredentials );
				$user_id = $tempCredentials[2];
				$user_role = $tempCredentials[1];
				/*
				7/13/2018 from Daniel
				0 = end user, can only see authored and shared documents, can only take action on owned documents.
				1 = super user, access to see and take action on all documents
				2 = admin, can access tag/organization/user functionality. Can designate gold masters. can take actions on templates and gold masters in their views.
					can assign/change users and super users
				3 = bureaucrat, can assign/change all user roles
				*/
				$returnObj = new \stdClass;
				$returnObj->role = $user_role;
				$returnObj->viewable = [];
				$returnObj->actionable = [];

				switch ( $user_role )
				{
					case 0:
						$tempStatement = $tConnection->prepare(	
							"SELECT d.id FROM documents AS d
							JOIN documents_groups_map AS dg ON dg.document_id = d.id
							JOIN users_groups_map AS ug ON ug.group_id = dg.group_id
							WHERE ug.user_id = " . $user_role . " AND d.gold_master = FALSE
							GROUP BY d.id 
							UNION
							SELECT dg.id FROM documents AS dg WHERE dg.owner_id = " . $user_id . " AND dg.gold_master = FALSE 
							UNION
							SELECT dg.id FROM documents AS dg WHERE dg.gold_master = TRUE 
							UNION
							SELECT document_id FROM documents_users_map WHERE user_id = " . $user_id );

						$tempStatement->execute();
						$tempDocList = $tempStatement->fetchAll( \PDO::FETCH_ASSOC );
						for ( $i = 0; $i < count( $tempDocList ); $i++ )
						{
							array_push( $returnObj->viewable, $tempDocList[$i]["id"] );
						}

						$tempDocList = ( new ActionGet( "documents", "id", "WHERE owner_id = " . $user_id ) )->execute( $tAPI );
						for ( $i = 0; $i < count( $tempDocList ); $i++ )
						{
							array_push( $returnObj->actionable, $tempDocList[$i]["id"] );
						}
						break;

					default:
						$tempDocList = ( new ActionGet( "documents", "id" ) )->execute( $tAPI );
						for ( $i = 0; $i < count( $tempDocList ); $i++ )
						{
							array_push( $returnObj->viewable, $tempDocList[$i]["id"] );
							array_push( $returnObj->actionable, $tempDocList[$i]["id"] );
						}
						break;
				}


				return $returnObj;
			}
		}
	}
}
?>