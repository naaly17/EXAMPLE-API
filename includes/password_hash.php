<?php

namespace CART;
// When you need to hash a password, just feed it to the function
// and it will return the hash which you can store in your database. 
// The important thing here is that you don’t have to provide a salt 
// value or a cost parameter. The new API will take care of all of 
// that for you. And the salt is part of the hash, so you don’t 
// have to store it separately.
// 
// Links:
// http://www.sitepoint.com/hashing-passwords-php-5-5-password-hashing-api/
// http://stackoverflow.com/questions/536584/non-random-salt-for-password-hashes/536756#536756
//
// sourced from: https://gist.github.com/odan/1d4ff4c4088e906a5a49

function create_password_hash($strPassword, $numAlgo = 1, $arrOptions = array())
{
    if (function_exists('password_hash')) {
        // php >= 5.5
        $hash = password_hash($strPassword, $numAlgo, $arrOptions);
    } else {
        $salt = mcrypt_create_iv(22, MCRYPT_DEV_URANDOM);
        $salt = base64_encode($salt);
        $salt = str_replace('+', '.', $salt);
        $hash = crypt($strPassword, '$2y$10$' . $salt . '$');
    }
    return $hash;
}
function verify_password_hash($strPassword, $strHash)
{
    if (function_exists('password_verify')) {
        // php >= 5.5
        $boolReturn = password_verify($strPassword, $strHash);
    } else {
        $strHash2 = crypt($strPassword, $strHash);
        $boolReturn = $strHash == $strHash2;
    }
    return $boolReturn;
}