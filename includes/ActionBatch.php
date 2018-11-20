<?php

namespace CART;

/**
* Handles hierarchical batch insert/update of a template by ID
*/
class ActionBatch implements IAction
{
	/**
	* @var string ID for updating a template
	*/
	protected $id;
	/**
	* @var string Name of the template column for closures
	*/
	protected $templateColumn;
	/**
	* @var string Name of units table
	*/
	protected $unitTable;
	/**
	* @var string Name of the units parent column
	*/
	protected $unitParentColumn;
	/**
	* @var string Expected name of input/output data property for units
	*/
	protected $unitField;
	/**
	* @var string Name of binds table
	*/
	protected $tUnitColumn;
	/**
	* @var string Name of binds table
	*/
	protected $equipmentTable;
	/**
	* @var string Expected name of input/output property for equipment
	*/
	protected $equipmentField;
	/**
	* @var string Name of personnel table
	*/
	protected $personnelTable;
	/**
	* @var string Expected name of input/output property for personnel
	*/
	protected $personnelField;
	/**
	* @var string Name of personnel equipment table
	*/
	protected $personnelEquipmentTable;
	/**
	* @var string Expected name of input/output property for personnel equipment
	*/
	protected $personnelEquipmentField;
	
	/**
	* Constructor
	* @param string $tTemplateTable Name of templates table
	* @param string $tTemplateField Field for input of templates
	* @param string $tTemplateColumn Name of the template column for closures
	* @param string $tUnitTable Name of units table
	* @param string $tUnitParentColumn Name of the units parent column
	* @param string $tUnitField Expected name of input/output data property for units
	* @param string $tBindTable Name of binds table
	* @param string $tBindField Expected name of unit/template binds data property
	* @param string $tEquipmentTable Name of equipment table
	* @param string $tEquipmentField Expected name of input/output property for equipment
	* @param string $tPersonnelTable Name of personnel table
	* @param string $tPersonnelField Expected name of input/output property for personnel
	* @param string $tPersonnelEquipmentTable Name of personnel equipment table
	* @param string $tPersonnelEquipmentField Expected name of input/output property for personnel equipment
	*/
	
	public function __construct( $tIDColumn, $tTemplateTable, $tTemplateField, $tTemplateColumn, $tUnitTable, $tUnitParentColumn, $tUnitField, $tUnitColumn, $tEquipmentTable, $tEquipmentField, $tPersonnelTable, $tPersonnelField, $tPersonnelEquipmentTable, $tPersonnelEquipmentField )
	{
		$this->IDColumn = $tIDColumn;
		$this->templateTable = $tTemplateTable;
		$this->templateField = $tTemplateField;
		$this->templateColumn = $tTemplateColumn;
		$this->unitTable = $tUnitTable;
		$this->unitParentColumn = $tUnitParentColumn;
		$this->unitField = $tUnitField;
		$this->unitColumn = $tUnitColumn;
	//	$this->bindTable = $tBindTable;
	//	$this->bindField = $tBindField;
		$this->equipmentTable = $tEquipmentTable;
		$this->equipmentField = $tEquipmentField;
		$this->personnelTable = $tPersonnelTable;
		$this->personnelField = $tPersonnelField;
		$this->personnelEquipmentTable = $tPersonnelEquipmentTable;
		$this->personnelEquipmentField = $tPersonnelEquipmentField;
	}

	/**
	* Executes an SQL query to update a template hierarchy using an input ID
	* @param IAPI $tAPI API that called this function
	* @param IRoute $tRoute Route that called this function
	*/
	public function execute( IAPI $tAPI )
	{
		$tempData = null;
		if ( $tAPI->getInput()->tryGet( $tAPI, $tempData ) )
		{
			$tempConnection = null;
			if ( $tAPI->getConnection()->tryConnect( $tAPI, $tempConnection ) )
			{
				//Templates 
				$tempTemplates = null;
				if ( isset( $tempData->{ $this->templateField } ) )
				{
					$this->processItems( $tAPI, $tempConnection, $tempData->{ $this->templateField }, $this->templateTable, null, null, $tempTemplates, null );
				}

				// Units
				$tempUnits = null;
				if ( isset( $tempData->{ $this->unitField } ) )
				{
					$this->processItems( $tAPI, $tempConnection, $tempData->{ $this->unitField }, $this->unitTable, $this->unitParentColumn, $tempUnits, $tempUnits, $tempTemplates );
				}
				
				// Equipment
				$tempEquipment = null;
				if ( isset( $tempData->{ $this->equipmentField } ) )
				{
					$this->processItems( $tAPI, $tempConnection, $tempData->{ $this->equipmentField }, $this->equipmentTable, $this->unitColumn, $tempUnits, $tempEquipment, $tempTemplates );
				}
				
				// Personnel
				$tempPersonnel = null;
				if ( isset( $tempData->{ $this->personnelField } ) )
				{
					$this->processItems( $tAPI, $tempConnection, $tempData->{ $this->personnelField }, $this->personnelTable, $this->equipmentField, $tempEquipment, $tempPersonnel, $tempTemplates );
				}
				
				// Personnel Equipment
				$tempPersonnelEquipment = null;
				if ( isset( $tempData->{ $this->personnelEquipmentField } ) )
				{
					$this->processItems( $tAPI, $tempConnection, $tempData->{ $this->personnelEquipmentField }, $this->personnelEquipmentTable, $this->personnelField, $tempPersonnel, $tempPersonnelEquipment, $tempTemplates );
				}
				
				// Output
				$tempIsTemplates = $tempTemplates != null;
				$tempIsUnits = $tempUnits != null;
				$tempIsEquipment = $tempEquipment != null;
				$tempIsPersonnel = $tempPersonnel != null;
				$tempIsPersonnelEquipment = $tempPersonnelEquipment != null;
				if ( $tempIsTemplates || $tempIsUnits || $tempIsEquipment || $tempIsPersonnel || $tempIsPersonnelEquipment )
				{ 
					$tempOuput = [];
					
					if ( $tempIsTemplates )
					{
						$tempOuput[ $this->templateField ] = $tempTemplates;
					}

					if ( $tempIsUnits )
					{
						$tempOuput[ $this->unitField ] = $tempUnits;
					}
					
					if ( $tempIsEquipment )
					{
						$tempOuput[ $this->equipmentField ] = $tempEquipment;
					}
					
					if ( $tempIsPersonnel )
					{
						$tempOuput[ $this->personnelField ] = $tempPersonnel;
					}
					
					if ( $tempIsPersonnelEquipment )
					{
						$tempOuput[ $this->personnelEquipmentField ] = $tempPersonnelEquipment;
					}
					
					$tAPI->getOutput()->setData( $tempOuput );
				}
				else
				{
					http_response_code( 204 );
				}
			}
			else
			{
				$tAPI->getOutput()->addError( $tempConnection->error );
				http_response_code( 500 );
			}
			
			$tempConnection->close();
		}
	}

	
	/**
	* Fork for processing data input either for inserting new items, updating existing, or deleting
	* @param IAPI $tAPI API that called this function
	* @param IConnection $tConnection Reference to Connection instance
	* @param stdclass[] $tData Data array to process
	* @param string $tTable Table name to process on
	* @param string $tParentColumn Name of parent column in relationship to the table being operated on
	* @param stdclass $tParentIDs Associative array with user input IDs as keys and SQL-generated IDs as values for client syncing
	* @param stdclass $tNewIDs Reference to associative array that gets filled with user input IDs as keys and SQL-generated IDs as values for client syncing
	*/
	protected function processItems( IAPI $tAPI, \mysqli $tConnection, $tData, $tTable, $tParentColumn, $tParentIDs, &$tNewIDs, $tNewTemplateIDs )
	{
		if ( isset( $tData->insert ) )
		{
			$this->insertItems( $tAPI, $tConnection, $tData->insert, $tTable, $tParentColumn, $tParentIDs, $tNewIDs, $tNewTemplateIDs );
		}
		
		if ( isset( $tData->update ) )
		{
			$this->updateItems( $tAPI, $tConnection, $tData->update, $tTable, $tParentColumn, $tParentIDs, $tNewTemplateIDs );
		}
		
		if ( isset( $tData->delete ) )
		{
			$this->deleteItems( $tAPI, $tConnection, $tData->delete, $tTable );
		}
	}
	
	/**
	* Processes an input array for inserting new items and sets up an assocative array of SQL-generated IDs for client syncing
	* @param IAPI $tAPI API that called this function
	* @param IConnection $tConnection Reference to Connection instance
	* @param stdclass[] $tData Data array to process
	* @param string $tTable Table name to process on
	* @param string $tParentColumn Name of parent column in relationship to the table being operated on
	* @param stdclass $tParentIDs Associative array with user input IDs as keys and SQL-generated IDs as values for client syncing
	* @param stdclass $tNewIDs Reference to associative array that gets filled with user input IDs as keys and SQL-generated IDs as values for client syncing
	*/
	protected function insertItems( IAPI $tAPI, \mysqli $tConnection, $tData, $tTable, $tParentColumn, $tParentIDs, &$tNewIDs, $tNewTemplateIDs  )
	{
		if ( is_array( $tData ) )
		{

			$tempListLength = count( $tData );
			if ( $tempListLength == 0 )
			{
				$tAPI->getOutput()->addError( "insert into " . $tTable . " table: empty array" );
			}
			else
			{
				// Generate query and parent binding
				$tempOldIDs = null;
				$tempQuery = null;
				$tempIsSubsequent = false;
				for ( $i = 0; $i < $tempListLength; ++$i )
				{
					if ( isset( $tData[$i]->{ $this->IDColumn } ) )
					{
						// Template Column exists, update ID if < 0
						if ( isset( $tData[$i]->{ $this->templateColumn } ) )
						{
							$tempTemplateID = $tData[$i]->{ $this->templateColumn };
							if ( $tempTemplateID < 0 )
							{
								if ( $tNewTemplateIDs != null && isset( $tNewTemplateIDs->{ $tempTemplateID } ) )
								{
									$tData[$i]->{ $this->templateColumn } = $tNewTemplateIDs->{ $tempTemplateID };
								}
								else
								{
									$tAPI->getOutput()->addError( "insert into " . $tTable . " table: missing template on element " . $i );
									continue;
								}
							}
						}

						if ( $tempOldIDs == null )
						{
							$tempOldIDs = [];
						}
						$tempOldIDs[] = $tData[$i]->{ $this->IDColumn };
						unset( $tData[$i]->{ $this->IDColumn } );

						if ( $tParentColumn != null && isset( $tData[$i]->{ $tParentColumn } ) )
						{
							$tempParentID = $tData[$i]->{ $tParentColumn };
							if ( $tempParentID < 0 )
							{
								if ( $tParentIDs != null && isset( $tParentIDs->{ $tempParentID } ) )
								{
									$tData[$i]->{ $tParentColumn } = $tParentIDs->{ $tempParentID };
								}
								else
								{
									unset( $tData[$i]->{ $tParentColumn } );
								}
							}
						}
						
						if ( $tempIsSubsequent )
						{
							$tempQuery .= " INSERT INTO " . $tTable . " " . Utility::SQLKVPs( $tConnection, $tData[$i] ) . ";";
						}
						else
						{
							$tempQuery = "INSERT INTO " . $tTable . " " . Utility::SQLKVPs( $tConnection, $tData[$i] ) . ";";
							$tempIsSubsequent = true;
						}
					}
					else
					{
						$tAPI->getOutput()->addError( "insert into " . $tTable . " table: missing temporary id on element " . $i );
					}
				}

				// Query
				if ( $tempQuery == null )
				{
					$tAPI->getOutput()->addError( "insert into " . $tTable . " table: no valid rows to insert" );
				}
				else
				{
					// First
					if ( $tConnection->multi_query( $tempQuery ) )
					{
						$tNewIDs = new \stdclass();
						$tNewIDs->{ $tempOldIDs[0] } = $tConnection->insert_id;
					}
					else
					{
						$tAPI->getOutput()->addError( "insert into " . $tTable . " table at ID " . $tempOldIDs[0] . ": " . $tConnection->error );
					}
					
					// Subsequent
					if ( $tConnection->more_results() )
					{						
						$i = 1;
						do
						{
							if ( $tConnection->next_result() )
							{
								if ( $tNewIDs == null )
								{
									$tNewIDs = new \stdclass();
								}
								
								$tNewIDs->{ $tempOldIDs[$i] } = $tConnection->insert_id;
							}
							else
							{
								$tAPI->getOutput()->addError( "insert into " . $tTable . " table at ID " . $tempOldIDs[$i] . ": " . $tConnection->error );
							}
							
							++$i;
						} while ( $tConnection->more_results() );
					}
				}
			}
		}
		else
		{
			$tAPI->getOutput()->addError( "insert into " . $tTable . " table: not an array" );
		}
	}
	
	/**
	* Processes an input array for updating existing items
	* @param IAPI $tAPI API that called this function
	* @param IConnection $tConnection Reference to Connection instance
	* @param stdclass[] $tData Data array to process
	* @param string $tTable Table name to process on
	* @param string $tParentColumn Name of parent column in relationship to the table being operated on
	* @param stdclass $tParentIDs Associative array with user input IDs as keys and SQL-generated IDs as values for client syncing
	*/
	protected function updateItems( IAPI $tAPI, \mysqli $tConnection, $tData, $tTable, $tParentColumn, $tParentIDs, $tNewTemplateIDs )
	{
		if ( is_array( $tData ) )
		{
			$tempListLength = count( $tData );
			if ( $tempListLength == 0 )
			{
				$tAPI->getOutput()->addError( "update " . $tTable . " table: empty array" );
			}
			else
			{
				// Generate query and parent binding
				$tempQuery = null;
				$tempIsSubsequent = false;
				for ( $i = 0; $i < $tempListLength; ++$i )
				{
					if ( isset( $tData[$i]->{ $this->IDColumn } ) )
					{
						$tempID = $tData[$i]->{ $this->IDColumn };
						// Template Column exists, update ID if < 0
						if ( isset( $tData[$i]->{ $this->templateColumn } ) )
						{
							$tempTemplateID = $tData[$i]->{ $this->templateColumn };
							if ( $tempTemplateID < 0 )
							{
								if ( $tNewTemplateIDs != null && isset( $tNewTemplateIDs->{ $tempTemplateID } ) )
								{
									$tData[$i]->{ $this->templateColumn } = $tNewTemplateIDs->{ $tempTemplateID };
								}
								else
								{
									$tAPI->getOutput()->addError( "update into " . $tTable . " table: missing template on element " . $i );
									continue;
								}
							}
						}
						
						if ( isset( $tData[$i]->{ $tParentColumn } ) )
						{
							$tempParentID = $tData[$i]->{ $tParentColumn };
							if ( $tempParentID < 0 )
							{
								if ( $tParentIDs != null && isset( $tParentIDs->{ $tempParentID } ) )
								{
									$tData[$i]->{ $tParentColumn } = $tParentIDs->{ $tempParentID };
								}
								else
								{
									unset( $tData[$i]->{ $tParentColumn } );
								}
							}
						}
						
						if ( $tempIsSubsequent )
						{
							$tempQuery .= " UPDATE " . $tTable . " " . Utility::SQLSet( $tConnection, $tData[$i] ) . " WHERE " . $this->IDColumn . "=" . $tempID . ";";
						}
						else
						{
							$tempQuery = "UPDATE " . $tTable . " " . Utility::SQLSet( $tConnection, $tData[$i] ) . " WHERE " . $this->IDColumn . "=" . $tempID . ";";
							$tempIsSubsequent = true;
						}
					}
					else
					{
						$tAPI->getOutput()->addError( "update " . $tTable . " table: missing id on element " . $i );
					}
				}

				// Query
				if ( $tempQuery == null )
				{
					$tAPI->getOutput()->addError( "update " . $tTable . " table: no valid rows to update" );
				}
				else
				{
					// First
					if ( !$tConnection->multi_query( $tempQuery ) )
					{
						$tAPI->getOutput()->addError( "update " . $tTable . " table: " . $tConnection->error );
					}
					
					// Subsequent
					if ( $tConnection->more_results() )
					{
						$i = 1;
						do
						{
							if ( !$tConnection->next_result() )
							{
								$tAPI->getOutput()->addError( "update " . $tTable . " table: " . $tConnection->error );
							}
							
							++$i;
						} while ( $tConnection->more_results() );
					}
				}
			}
		}
		else
		{
			$tAPI->getOutput()->addError( "update " . $tTable . " table: not an array" );
		}
	}
	
	/**
	* Processes an input array for deleting existing items
	* @param IAPI $tAPI API that called this function
	* @param IConnection $tConnection Reference to Connection instance
	* @param IRoute $tRoute Route that called this function
	* @param stdclass[] $tData Data array to process
	* @param string $tTable Table name to process on
	*/
	protected function deleteItems( IAPI $tAPI, \mysqli $tConnection, $tData, $tTable )
	{
		if ( is_array( $tData ) )
		{
			if ( !$tConnection->query( "DELETE FROM " . $tTable . " WHERE " . $this->IDColumn . " IN " . Utility::SQLArray( $tConnection, $tData ) ) )
			{
				$tAPI->getOutput()->addError( "delete from " . $tTable. " table: " . $tConnection->error );
			}
			else if ( $tConnection->affected_rows == 0 )
			{
				$tAPI->getOutput()->addError( "delete from " . $tTable . " table: no records found" );
			}
		}
		else
		{
			$tAPI->getOutput()->addError( "delete from " . $tTable . " table: not an array" );
		}
	}
}

?>