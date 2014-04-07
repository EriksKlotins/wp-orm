<?php namespace wp_orm;

class BaseQueryBuilder
{
	protected $whereClause = '';
	protected $rawWhereClause = '';
	protected $orderClause = '';
	protected $limitClause = '';
	
	protected $joins = array();



	protected $fieldsClause = 'A.*';
	protected $className = '';
	protected $model = null; // JC
	protected $primaryTable = '';
	protected $defaultCondition = null; // Šo pievieno klāt visiem where neatkarīgi no tā, kas ir WhereClause


	/**
	 * satur Modela metodes, ko izsaukt buvejot to
	 * sanāk tā kā relācijas
	 * @var array
	 */
	protected $with = [];


	/**
	 * Nosaka, kuras relacijas izpildīt
	 * Modelī ir jābūt definetām metodem, kas atgriez saistītos ierakstus
	 * @param  array|string $params metodes, ko izsaukt
	 * @return object         $this
	 */
	public function with($params)
	{
		//var_dump('es esmu with');
		$this->with = $params;
		return $this;
	}


	public function __construct($model)
	{

		$className  = get_class($model);
		$this->className = $className;
		$this->primaryTable = $model->getPrimaryTable();
		$model = new $className();
		$this->model = $model;// JC
	}

	public function setDefaultCondition($condition)
	{
		$this->defaultCondition = $condition;
	}


	public function delete()
	{
		global $wpdb;
		$sql = 'DELETE FROM '.$this->primaryTable . $this->getSQL();
		//var_dump($sql);
		return $wpdb->query($sql);
	}



	public function orderBy($field, $order = 'ASC')
	{
		if (!$field) return $this;
		
		if (!in_array($field, $this->model->getFields())) return $this;
		// apstrādājam gadījumus, kad lauks ir
		if (!is_numeric($field))
		{
			$field = sprintf('`%s`',$field);
		}
		if ($this->orderClause != '') $this->orderClause .= ', ';
		$this->orderClause .= sprintf("%s %s", $field, $order);
		return $this;
	}

	public function limit($limit, $offset = 0)
	{
		$this->limitClause = sprintf('%d, %d', $offset, $limit);
		return $this;
	}

	/*
		Pieliek jaunu lauku sarakstā
		izmanto, lai apietu kolizijas ar lauku nosaukumiem
	 */
	// public function fieldAlias ($field, $alias)
	// {
	// 	$this->fieldsClause = $this->fieldsClause.', '.$field.' AS '.$alias;
	// 	return $this;
	// }

	public function group()
	{
		die('not implemented');
	}

	private function addTypeFormatting($value)
	{
		if (is_string($value)) $value = sprintf("'%s'", addslashes($value));
		if (is_numeric($value)) $value = sprintf("%s", $value);
		if (is_bool($value)) $value = sprintf("%s", DataType::toWpBoolean($value)); // JC
		if (is_null($value)) $value = 'NULL';
		if ($value instanceof \DateTime) $value = sprintf("'%s'",  DataType::toWpDate($value));// JC 
		
		// if is_date()...
		
		if (is_array($value))
		{
			for($i=0;$i<count($value); $i++)
			{
				$value[$i] = $this->addTypeFormatting($value[$i]);
			}
			$value = '('.implode(',',$value).')';
		}

		return $value;
	}

	private function walkArray($params)
	{
		if ($params == null) return '';

		
		$operator = strtoupper(trim($params[1]));
	
		$parts = array();
		//var_dump($params, $operator);
		// loģiskie operatori, kas apvieno dažādus nosacījumus
		if (in_array($operator, ['AND', 'OR']))
		{

			$parts = ['(',$this->walkArray($params[0]), $operator, $this->walkArray($params[2]),')'];
		}

		// tur kur viss ir vienkārši
		if (in_array($operator, ['>','<','=','<=','>=','IS', 'IS NOT', '<>','!=','LIKE','NOT LIKE','IN', 'NOT IN']))
		{
			$params[0] = addslashes($params[0]);
			$value =  $this->addTypeFormatting($params[2]);
			$parts = ['(','`'. $params[0].'`', $operator, $value,')'];		
		}

		

		return implode (' ', $parts);
	}



	protected function getSQL()
	{
		$result = '';
		if ($this->whereClause)
		{
			$default = $this->walkArray($this->defaultCondition);
			if ($default != '')
			{
				$result .= sprintf(' WHERE (%s) AND (%s)',$this->whereClause, $default);
			}
			else
			{
				$result .= ' WHERE '.$this->whereClause;
			}
		}

		$result .= $this->rawWhereClause;



		if ($this->orderClause)
		{
			$result .= ' ORDER BY '.$this->orderClause;
		}

		if ($this->limitClause)
		{
			$result .= ' LIMIT '.$this->limitClause;
		}
		return  $result;
	}


	/**
	 * Returns first row from where result or null
	 * @return rowType 
	 */
	public function first()
	{
		$rows = $this->limit(1)->get();
		return (count($rows) > 0) ? $rows[0] : null;
	}

	
	public function rawWhere($clause)
	{
		$this->rawWhereClause = $clause;
		return $this;
	}

	/*
		Returns sum over specified $field
	 */
	public function sum($field)
	{

		$fieldsClause =  $this->fieldsClause;
		
	
		$sql = 'SELECT SUM('.$field.') FROM (SELECT '.$fieldsClause. ' FROM '.$this->primaryTable.' A) B' . $this->getSQL();
		//var_dump($sql);
		//echo $sql;
		$value = $this->getValue($sql);
	//	var_dump($value === null ? (float) 0 : (float) $value);
		return $value === null ? (float) 0 : (float) $value;
	}

	public function count()
	{
		$sql = 'SELECT count(*) FROM '.$this->primaryTable.' A' . $this->getSQL();
		return (int) $this->getValue($sql);
	}

	public function andWhere()
	{
		$params = func_get_args();

		$this->whereClause .= ' AND '.$this->walkArray($params);
		return $this;
	}

	public function orWhere()
	{
		$params = func_get_args();
	
		$this->whereClause .= ' OR '.$this->walkArray($params);
		return $this;
	}

	public function where($params = null)
	{

	

		$this->whereClause .= $this->walkArray($params);
		return $this;
	}



	/**
	 * Executes final sql
	 * @return [array] Array of items matching the query
	 */
	public function get()
	{

		
		$fieldsClause =  $this->fieldsClause;
		
		
		$sql = 'SELECT * FROM '.$this->primaryTable.' A' . $this->getSQL();
		//var_dump($sql);
		//echo $sql;
		return $this->getItems($sql);
	}


	protected function getValue($sql)
	{
		global $wpdb;
		$result = $wpdb->get_var($sql, 0,0);
		return $result;

	}

	protected function getItems($sql)
	{
		global $wpdb;
		//$wpdb->show_errors = true;
		//var_dump($sql);
		$result = $wpdb->get_results($sql,ARRAY_A);
		for($i=0;$i<count($result);$i++) 
		{
			$name = $this->className;
			$rowObject = new $name();
			$result[$i] = $rowObject->prepare($result[$i]);
			//$result[$i]->touchedAttributes = [];
			foreach ((array) $this->with as $method) 
			{
				$a = $rowObject->$method();
				$result[$i]->{$method} = $a;
			}
			//$method = $this->with;
			
			
		
		}
		return $result;

	}

}
	