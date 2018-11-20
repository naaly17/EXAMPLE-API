<?php

namespace CART;
/**
* Handles basic SQL Select by ID
*/
class ActionGet implements IAction
{
	/**
	* @var string Primary table name to select from
	*/
	public $table;
	
	/**
	* @var string Terms of selection
	*/
	public $selection;
	
	/**
	* @var string Conditions for selecting
	*/
	public $conditions;
	
	/**
	* @var string Name of URI parameter limit variable in URI query
	*/
	public $limitVariable;
	
	/**
	* @var string Name of URI parameter offset variable in URI query
	*/
	public $offsetVariable;
	
	/**
	* @var int Default range limit
	*/
	public $defaultLimit;
	
	/**
	* @var int Default range offset
	*/
	public $defaultOffset;
	
	/**
	* Constructor
	* @param string $tTable Primary table name to select from
	* @param string $tSelection Terms of selection
	* @param string $tConditions (optional) Conditions for selecting
	* @param string $tLimitVariable (optional) Name of URI parameter limit variable in URI query
	* @param string $tOffsetVariable (optional) Name of URI parameter offset variable in URI query
	* @param int $tDefaultLimit (optional) Default range limit, defaults to 500
	* @param int $tDefaultOffset (optional) Default range offset, defaults to 0
	*/
	public function __construct( $tTable, $tSelection, $tConditions = null, $tLimitVariable = null, $tOffsetVariable = null, $tDefaultLimit = 500, $tDefaultOffset = 0, $tBinds = null )
	{
		$this->table = $tTable;
		$this->selection = $tSelection;
		$this->conditions = $tConditions;
		$this->limitVariable = $tLimitVariable;
		$this->offsetVariable = $tOffsetVariable;
		$this->defaultLimit = $tDefaultLimit;
		$this->defaultOffset = $tDefaultOffset;
		$this->binds = $tBinds;
	}
	
	/**
	* Executes an SQL query to get an item(s) using an input ID
	* @param IAPI $tAPI API that called this function
	*/
	public function execute( IAPI $tAPI )
	{
		$tempConnection = null;
		if ( $tAPI->getConnection()->tryConnect( $tAPI, $tempConnection ) )
		{
			$tempConnection->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION );
			$tempQuery = "SELECT " . $this->selection . " FROM " . $this->table;
			if ( $this->conditions != null )
			{
				$tempQuery .= " " . $this->conditions;
			}
			
			// Limit and offset
			if ( !empty( $this->limitVariable ) )
			{
				$tempLimit = isset( $_GET[ $this->limitVariable ] ) && is_numeric( $_GET[ $this->limitVariable ] ) ? $_GET[ $this->limitVariable ] : null;		
				if ( $tempLimit == null )
				{
					$tempLimit = $this->defaultLimit;
				}
				
				$tempQuery .= " LIMIT " . $tempLimit;
			}
			
			if ( !empty( $this->offsetVariable ) )
			{
				$tempOffset = isset( $_GET[ $this->offsetVariable ] ) && is_numeric( $_GET[ $this->offsetVariable ] ) ? $_GET[ $this->offsetVariable ] : null;
				if ( $tempOffset == null )
				{
					$tempOffset = $this->defaultOffset;
				}
				
				$tempQuery .= " OFFSET " . $tempOffset;
			}

			try
			{
				$tempQueryResult = $tempConnection->prepare( $tempQuery );
				if ( $this->binds == null )
				{
					$tempQueryResult->execute();
				}
				else
				{
					$tempQueryResult->execute( $this->binds );
				}
				$results = $tempQueryResult->fetchAll(\PDO::FETCH_ASSOC);
				return $results;
			}
			catch (\PDOException $e)
			{
				$tAPI->getOutput()->addError( $e );
				http_response_code( 500 );
			}
		}
	}
}
?>