<?php

namespace CART;

//##########################
// Interface Declaration
//##########################
interface IConnection
{
	public function tryConnect( IAPI $tAPI, &$tConnection );
}

?>