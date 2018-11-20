<?php

namespace CART;

define( "OrbeonPath", "http://localhost:8080/orbeon/" );

require_once( "includes/interfaces/IAction.php" );
require_once( "includes/interfaces/IAPI.php" );
require_once( "includes/interfaces/IAuthorization.php" );
require_once( "includes/interfaces/IConnection.php" );
require_once( "includes/interfaces/IInput.php" );
require_once( "includes/interfaces/IOutput.php" );

require_once( "includes/Action.php" );
require_once( "includes/ActionDelete.php" );
require_once( "includes/ActionGet.php" );
require_once( "includes/ActionGetGroup.php" );
require_once( "includes/ActionReplace.php" );
require_once( "includes/BindObjects.php" );
require_once( "includes/ActionPatch.php" );
require_once( "includes/ActionPost.php" );
require_once( "includes/ActionPostDocument.php" );
require_once( "includes/ActionUpdate.php" );
require_once( "includes/ActionBatch.php" );
require_once( "includes/ActionGetUser.php" );
require_once( "includes/ActionGetGoldMasters.php" );
require_once( "includes/ActionKeywordSearch.php" );
require_once( "includes/ActionRefreshAccessToken.php" );
require_once( "includes/ActionRevokeRefreshToken.php" );
require_once( "includes/ActionResetPassword.php" );
require_once( "includes/ActionPostUser.php" );
require_once( "includes/ActionPatchGroup.php" );
require_once( "includes/ActionPatchDocument.php" );
require_once( "includes/ActionPatchUser.php" );
require_once( "includes/ActionValidateEmail.php" );
require_once( "includes/ActionGetDocuments.php");
require_once( "includes/API.php" );
require_once( "includes/Authorization.php" );
require_once( "includes/Connection.php" );
require_once( "includes/password_hash.php" );
require_once( "includes/Output.php" );
require_once( "includes/Utility.php" );
require_once( "includes/CARTAPI.php" );
require_once( "includes/Input.php" );
require_once( "includes/InputJSON.php" );
require_once( "includes/Output.php" );
require_once( "includes/OutputJSON.php" );
require_once( "includes/ConnectionPostgres.php" );


//$tempTime = microtime( true );

// Run API
header( "Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS ");
header( "Access-Control-Allow-Origin: * ");
header( "Access-Control-Allow-Headers: content-type, Authentication" );
$tempAPI = new CARTAPI();
$tempAPI->execute();

//echo microtime( true ) - $tempTime;

?>