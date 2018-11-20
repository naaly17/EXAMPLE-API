<?php
namespace CART;
//##########################
// Interface Declaration
//##########################
interface IInput
{
	public function tryGet( IAPI $tAPI, &$tData );
}
?>