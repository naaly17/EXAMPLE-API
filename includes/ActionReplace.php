<?php

namespace CART;

/**
* Handles hierarchical batch insert/update of a template by ID
*/
class ActionReplace implements IAction
{

	public function __construct()
	{
		
	}

	public function execute( IAPI $tAPI )
	{
		$tempData = null;
		if ( $tAPI->getInput()->tryGet( $tAPI, $tempData ) )
		{
			$tempConnection = null;
			if ( $tAPI->getConnection()->tryConnect( $tAPI, $tempConnection ) )
			{
				$tempConnection->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION );
				$tempConnection->beginTransaction();
				$affectedRows = 0;
				foreach ( $tempData as $replace )
				{
					switch( $replace->type )
					{
						case "template_units":
						case "unit_info":
							$inQuery = implode(',', array_fill(0, count($replace->ids), '?' ) );
							$tQuery = "UPDATE " . $replace->type . " SET name = ? WHERE id IN ($inQuery)";
							$statement = $tempConnection->prepare( $tQuery );
							$statement->bindValue( 1, $replace->name, \PDO::PARAM_STR );
							foreach ( $replace->ids as $int => $id )
							{
								$statement->bindValue( ( $int+2 ), $id );
							}
							$statement->execute();
							$affectedRows += $statement->rowCount();
							break;
						case "equipment":
						case "personnel":
							$inQuery = implode(',', array_fill(0, count($replace->ids), '?' ) );
							$tQuery = "UPDATE " . $replace->type . " SET dis_name = ?, dis_string = ? WHERE id IN ($inQuery)";
							$statement = $tempConnection->prepare( $tQuery );
							$statement->bindValue( 1, $replace->dis_name, \PDO::PARAM_STR );
							$statement->bindValue( 2, $replace->dis_string, \PDO::PARAM_STR );
							foreach ( $replace->ids as $int => $id )
							{
								$statement->bindValue( ( $int+3 ), $id );
							}
							$statement->execute();
							$affectedRows += $statement->rowCount();
							break;
					}
				}
				$tempConnection->commit();
				header( "Content-type: application/json ");
				echo( '{"Affected rows":' . $affectedRows . '}' );
				http_response_code( 200 );
			}
			else
			{
				$tAPI->getOutput()->addError( $tempConnection->error );
				http_response_code( 500 );
			}
		}
		else
		{
			print_r( "Didn't get any input." );
		}
	}
}