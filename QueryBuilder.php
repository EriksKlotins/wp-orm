<?php namespace wp_orm;


class QueryBuilder
{
	private $whereClause = '';
	private $orderClause = '';
	private $limitClause = '';
	private $fieldsClause = 'A.*';
	private $className = '';
	private $metaFieldsClause = '';
	private $model = null; // JC

	public function __construct($className)
	{
		global $wpdb;
		$this->className = $className;
		

		$model = new $className();
		//$model->metaFields = array();
		for($i=0;$i<count($model->metaFields);$i++)
		{
			if ($this->metaFieldsClause != '') $this->metaFieldsClause.=', ';
			$this->metaFieldsClause .= sprintf("(SELECT meta_value FROM %s WHERE post_id = A.ID AND meta_key = '%s') as `%s`", $wpdb->postmeta, $model->metaFields[$i],$model->metaFields[$i]);
		}
		$this->model = $model;// JC
		//var_dump($this->metaFieldsClause);
	}

	private function addTypeFormatting($value)
	{
		if (is_string($value)) $value = sprintf("'%s'", $value);
		if (is_numeric($value)) $value = sprintf("%s", $value);
		if (is_bool($value)) $value = sprintf("%s", DataType::toWpBoolean($value)); // JC
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
		$params[0] = addslashes($params[0]);
		$params[2] = addslashes($params[2]);
		$parts = array();

		// loģiskie operatori, kas apvieno dažādus nosacījumus
		if (in_array($operator, ['AND', 'OR']))
		{
			$parts = ['(',$this->walkArray($params[0]), $operator, $this->walkArray($params[2]),')'];
		}


		// tur kur viss ir vienkārši
		if (in_array($operator, ['>','<','=','<=','>=','IS', 'IS NOT', '<>','!=','LIKE','NOT LIKE','IN', 'NOT IN']))
		{
			$value =  $this->addTypeFormatting($params[2]);
			$parts = ['(', $params[0], $operator, $value,')'];		
		}

		

		return implode (' ', $parts);
	}


	private function getValue($sql)
	{
		global $wpdb;
		$result = $wpdb->get_var($sql, 0,0);
		return $result;

	}

	private function getItems($sql)
	{
		global $wpdb;
		$wpdb->show_errors = true;
		
		$result = $wpdb->get_results($sql,ARRAY_A);
		for($i=0;$i<count($result);$i++) 
		{
			$name = $this->className;
			$rowObject = new $name();
			$result[$i] = $rowObject->prepare($result[$i]);
		}
		return $result;

	}


	

	public function orderBy($field, $order = 'ASC')
	{
		if ($this->orderClause != '') $this->orderClause .= ', ';
		$this->orderClause .= sprintf("%s %s", $field, $order);
		return $this;
	}

	public function limit($limit, $offset = 0)
	{
		$this->limitClause = sprintf('%d, %d', $offset, $limit);
		return $this;
	}

	public function group()
	{
		die('not implemented');
	}


	private function getSQL()
	{
		$result = '';//SELECT '.$this->fieldsClause.' FROM wp_posts';
		if ($this->whereClause)
		{
			$result .= ' WHERE '.$this->whereClause;
		}

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
	 * Executes final sql
	 * @return [array] Array of items matching the query
	 */
	public function get()
	{
		global $wpdb;
		if ($this->metaFieldsClause !='')
		{
			$fieldsClause = $this->fieldsClause.', '.$this->metaFieldsClause;
		}
		else
		{
			$fieldsClause =  $this->fieldsClause;
		}
	
		$sql = 'SELECT * FROM (SELECT '.$fieldsClause. ' FROM '.$wpdb->posts.' A) B' . $this->getSQL();
		//var_dump($sql);
		echo $sql;
		return $this->getItems($sql);
	}

	/**
	 * Returns first row from where result or null
	 * @return rowType 
	 */
	public function first(){
		$rows = $this->limit(1)->get();
		return (count($rows) > 0) ? $rows[0] : null;
	}

	public function count()
	{
		global $wpdb;
		if ($this->metaFieldsClause !='')
		{
			$fieldsClause = $this->fieldsClause.', '.$this->metaFieldsClause;
		}
		$sql = 'SELECT count(*) FROM (SELECT '.$fieldsClause. ' FROM '.$wpdb->posts.' A) B' . $this->getSQL();
		return $this->getValue($sql);
	}

	public function andWhere()
	{
		$this->whereClause .= ' AND '.$this->walkArray(func_get_args());
		return $this;
	}

	public function orWhere()
	{
		$this->whereClause .= ' OR '.$this->walkArray(func_get_args());
		return $this;
	}

	public function where($params = null)
	{
		$this->whereClause .= $this->walkArray($params);
		return $this;
	}

}
