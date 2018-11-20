<?php

namespace CART;

//##########################
// Interface Declaration
//##########################
interface IAuthorization
{
	public function tryAuthorize( IAPI $tAPI );
}

?>