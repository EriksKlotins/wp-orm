<?php namespace wp_orm;


class BaseModel
{
	protected $fields = array(); // izmanto create/update
	//public $metaFields = array();

	protected $primaryTable = null;
	//protected $metaTable = null;

	protected $primaryKey = 'ID';	// Primārās atslegas nosaukums
	protected $deleteTimestampFieldName = 'deleted_at'; // dzešanas lauks
	
	/**
	 * masīvs ar propertijiem, kas ir uzstādīti
	 * @var [type]
	 */
	public  $touchedAttributes = [];
	/**
	 * Masīvs ar modela vertibām
	 * @var [type]
	 */
	private $attributes = [];


	/**
	 * field => dataType mapping for typecasting, supported data types are 
	 * DateTime - date
	 * Real - number
	 * Integer - int
	 * Boolean - boolean
	 * @var array
	 */
	protected $fieldType = array();


	public function __construct()
	{
 		//var_dump('Es esmu Basemodel konstruktors '.get_class($this));
	}

	public function getPrimaryTable()
	{
		return $this->primaryTable;
	}

	public function getFields()
	{
		return $this->fields;
	}
	/**
	 * Validācijas funkcija, kas tiek izsaukta pie INSERT, UPDATE un REPLACE
	 * 
	 * @param  array $fields [description]
	 * @return boolean         [description]
	 */
	public function validate($fields = array())
	{
		return true;
	}


	public function __set($name, $value)
	{
		
		if (!in_array( $name, $this->touchedAttributes))
		{
			//var_dump('setter '.$name);
			$this->touchedAttributes[] = $name;
		}
		$this->attributes[$name] = $value;
	}

	public function __get($name)
	{
		//var_dump('getter '.$name);

		if (isset($this->attributes[$name]))
		{
			return $this->attributes[$name];
		}
		else
		{
			return null;
		}
		//return isset($this->dataValues->$name) ? $this->dataValues->$name : null;
	}

	public function __isset($name)
	{
		return isset($this->attributes[$name]);
	}


	protected function type($name, $value, $toWp = false)
	{
		if ( isset($this->fieldType[$name]) )
		{
			switch($this->fieldType[$name]){
				case 'date':
					$value = ($toWp) ? DataType::toWpDate($value) : DataType::toDate($value);
				break;

				case 'number':
					$value = ($toWp) ? DataType::toWpNumber($value) : DataType::toNumber($value);
				break;

				case 'int':
					$value = ($toWp) ? DataType::toWpInt($value) : DataType::toInt($value);
				break;

				case 'boolean':
					$value = ($toWp) ? DataType::toWpBoolean($value) : DataType::toBoolean($value);
				break;
			}
		}
		return $value;
	}

	protected function typeWp($name, $value)
	{
		return $this->type($name, $value, true);
	}



	/**
	 * Returns object by ID
	 * @param  int  $id          post ID
	 * @param  boolean $isPublished (optional) if post_status must be 'published'. deffault true
	 * @return object               
	 */
	public static function find($id, $isPublished = true)
	{

		$query = static::where('ID', '=', $id);
		$result = $query->first();	
		//$result->touchedAttributes = [];
		return $result;
	}

	/**
	 * Returns all posts of this class
	 * @return array               
	 */
	public static function all()
	{
		return static::where()->get();
	}


	


	/**
	 * Creates post from array of fields
	 * @param  array  $fields post fields
	 * @return objcet         
	 */
	public static function create($fields = array())
	{
		$result = new static();

		$result->createRecord($fields);
		
		global $wpdb;
		$pk = $result->primaryKey;
		$result->{$pk} = $wpdb->insert_id;
		
		return $result;
	}

	/**
	 * izdzes ierakstu
	 * @return void
	 */
	public function delete()
	{
		//$this->{$this->deleteTimestampFieldName} = new DateTime();
		//$this->save();
		//
		$this->where($this->primaryKey,'=',$this->{$this->primaryKey})->delete();
	}


	public function softDelete()
	{
		$this->{$this->deleteTimestampFieldName} = new \DateTime();
		$this->save();
	}


	public function save()
	{	
		global $wpdb;
		// touching primary key
		$this->{$this->primaryKey} = $this->{$this->primaryKey};
		//var_dump('--- save()', $this->touchedAttributes);
		$data = array();
		
		

		$fields = array_intersect($this->touchedAttributes,$this->fields);
		//var_dump($fields);
		
		foreach ($fields as $field) 
		{
			$data[$field] = $this->{$field};
		}
		
		$this->createRecord($data);

		$pk = $this->primaryKey;
		$this->{$pk} = $wpdb->insert_id;

	}



	public function prepare($params)
	{	
		$fieldNames = array_keys($params);

		//$this->dataValues = new \stdClass;
		for($i = 0; $i < count($fieldNames); $i++)
		{	
			$key = $fieldNames[$i];
			if(isset($params[$key])) 
			{
				$this->{$key} = $this->type($key, $params[$key]);
			}
			else
			{
				$this->{$key} = null;
			}
		}
		
		//$this->touchedAttributes = array();
		//$this->{$with[0]} = $this->industry();
		//var_dump($with);
		
		

		return $this;
	}

	public static function where($params = null)
	{
		$model = new static();
		$query = new BaseQueryBuilder($model);
		if (in_array($model->deleteTimestampFieldName, $model->fields))
		{
			$query->setDefaultCondition([[$model->deleteTimestampFieldName,'>', new \DateTime()],'OR',[$model->deleteTimestampFieldName,'IS',null]]);
		}
		$query->where(func_get_args());
		return $query;
	}


	/**
	 * Parrakstām wpdb->replace
	 * @param  [type] $table  [description]
	 * @param  [type] $fields [description]
	 * @return [type]         [description]
	 */
	private function replace($table, $fields)
	{
		global $wpdb;
		$d = [];
		$f = [];
		foreach ($fields as $field => $value) 
		{
			$f[] = sprintf('`%s`',$field);
			
			if (is_numeric($value))
			{
				$d[] = sprintf('%s',$value);
			}
			elseif(is_null($value))
			{
				$d[] = sprintf("NULL",$value);
			}
			else
			{
				$value = esc_sql($value);
				$d[] = sprintf("'%s'",$value);
			}
		}
		$sql = sprintf('REPLACE INTO `%s` (%s) VALUES (%s)', $table, implode(',', $f), implode(',', $d));
	//	var_dump($sql);
		return $wpdb->query($sql);
		//die();
	}


	/**
	 * Saves/Update post to database
	 * @param  array $fields post fields
	 */
	protected function createRecord($fields)
	{
		global $wpdb;
		//var_dump('createRecord',$fields);
		$result = null;


			
			foreach ($fields as $name => $value) 
			{
				$fields[$name] = $this->typeWp($name, $value);
			}
			
			$result = $this->replace( $this->primaryTable, $fields);
			
			foreach ($fields as $name => $value) 
			{
				if (in_array($name,$this->fields))
				{
					$this->{$name} = $value;
				}
			}
		//var_dump('bopma',$this->ID,$this->primaryKey);
		
		
		return $result;
	}


}

/**
 * ======================================================================================================================
 * ======================================================================================================================
 */

class PostsModel extends BaseModel
{	
	


	protected $fields = array(
		'ID','post_content','post_name','post_title', 'post_status','post_type','post_author', 'ping_status', 'post_parent', 'menu_order', 'to_ping','pinged', 'post_password','guid','post_content_filtered','post_excerpt','post_date' ,'post_date_gmt', 'comment_status', 'post_category', 'tags_input', 'page_template'
	);
	protected $fieldType = array();

	public  function  __construct()
	{
		$this->fieldType = array_merge($this->fieldType, array('post_date' => 'date', 'post_date_gmt' => 'date' ));
		parent::__construct();
	}

	
	
	/**
	 * Saves/Update post to database
	 * @param  array $fields post fields
	 */
	protected function createRecord($fields)
	{
		foreach ($fields as $name => $value) {
			$fields[$name] = $this->typeWp($name, $value);
		}
		$fields['post_type'] = $this->postTypeName;
		$this->ID = wp_insert_post($fields);
		foreach ($fields as $name => $value) 
		{
			if (in_array($name,$this->fields))
			{
				$this->{$name} = $value;
			}
		}

		foreach ($this->metaFields as $field) 
		{
			$value = isset($fields[$field]) ? $fields[$field] : null;
			$this->{$field} = $value;
			//if field doesn't exist, add_post_meta($post_id, $meta_key, $meta_value) is called instead and its result is returned. 
			update_post_meta($this->ID, $field, $value, false);
		}
	}
}


/*

$post = array(
  'ID'             => [ <post id> ] // Are you updating an existing post?
  'post_content'   => [ <string> ] // The full text of the post.
  'post_name'      => [ <string> ] // The name (slug) for your post
  'post_title'     => [ <string> ] // The title of your post.
  'post_status'    => [ 'draft' | 'publish' | 'pending'| 'future' | 'private' | custom registered status ] // Default 'draft'.
  'post_type'      => [ 'post' | 'page' | 'link' | 'nav_menu_item' | custom post type ] // Default 'post'.
  'post_author'    => [ <user ID> ] // The user ID number of the author. Default is the current user ID.
  'ping_status'    => [ 'closed' | 'open' ] // Pingbacks or trackbacks allowed. Default is the option 'default_ping_status'.
  'post_parent'    => [ <post ID> ] // Sets the parent of the new post, if any. Default 0.
  'menu_order'     => [ <order> ] // If new post is a page, sets the order in which it should appear in supported menus. Default 0.
  'to_ping'        => // Space or carriage return-separated list of URLs to ping. Default empty string.
  'pinged'         => // Space or carriage return-separated list of URLs that have been pinged. Default empty string.
  'post_password'  => [ <string> ] // Password for post, if any. Default empty string.
  'guid'           => // Skip this and let Wordpress handle it, usually.
  'post_content_filtered' => // Skip this and let Wordpress handle it, usually.
  'post_excerpt'   => [ <string> ] // For all your post excerpt needs.
  'post_date'      => [ Y-m-d H:i:s ] // The time post was made.
  'post_date_gmt'  => [ Y-m-d H:i:s ] // The time post was made, in GMT.
  'comment_status' => [ 'closed' | 'open' ] // Default is the option 'default_comment_status', or 'closed'.
  'post_category'  => [ array(<category id>, ...) ] // Default empty.
  'tags_input'     => [ '<tag>, <tag>, ...' | array ] // Default empty.
  'tax_input'      => [ array( <taxonomy> => <array | string> ) ] // For custom taxonomies. Default empty.
  'page_template'  => [ <string> ] // Default empty.
);  

*/