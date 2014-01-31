<?php namespace wp_orm;



class BaseModel
{	
	public $metaFields = array();
	protected $fields = array(
		'ID','post_content','post_name','post_title', 'post_status','post_type','post_author', 'ping_status', 'post_parent', 'menu_order', 'to_ping','pinged', 'post_password','guid','post_content_filtered','post_excerpt','post_date' ,'post_date_gmt', 'comment_status', 'post_category', 'tags_input', 'page_template'
	);

	private $fieldTypeDefault = array('post_date' => 'date', 'post_date_gmt' => 'date' );
	/**
	 * field => dataType mapping for typecasting, supported data types are DateTime - date
	 * Real - number
	 * Integer - int
	 * Boolean - bool
	 * @var array
	 */
	protected $fieldType = array();

	private function type($name, $value, $toWp = false)
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

	private function typeWp($name, $value)
	{
		return $this->type($name, $value, true);
	}

	

	public  function  __construct()
	{
		$this->fieldType = array_merge($this->fieldType, $this->fieldTypeDefault);
	}

	public function prepare($params)
	{	
		$fieldNames = array_merge($this->fields, $this->metaFields);

		for($i = 0; $i < count($fieldNames); $i++)
		{	
			$key = $fieldNames[$i];
			if(isset($params[$key])) {
				$this->{$key} = $this->type($key, $params[$key]);
			}
		}

		return $this;
	}

	public function save()
	{
		$data = array();
		$fieldNames = array_merge($this->fields, $this->metaFields);

		for($i = 0; $i < count($fieldNames); $i++)
		{	
			$name = $fieldNames[$i];
			$data[$name] = $this->{$name};
		}
/*
		for($i = 0; $i < count($this->metaFields); $i++)
		{	
			$name = $this->metaFields[$i];
			$data[$name] = $this->typeWp($name, $this->{$name});
		}
		*/
		
		$this->createPost($data);
	}

	/**
	 * Creates post from array of fields
	 * @param  array  $fields post fields
	 * @return objcet         
	 */
	public static function create($fields = array())
	{
		$result = new static();
		$result->createPost($fields);
		return $result;
	}

	/**
	 * Saves/Update post to database
	 * @param  array $fields post fields
	 */
	protected function createPost($fields){
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

	/**
	 * Returns object by ID
	 * @param  int  $id          post ID
	 * @param  boolean $isPublished (optional) if post_status must be 'published'. deffault true
	 * @return object               
	 */
	public static function find($id, $isPublished = true)
	{
		$query = static::where('ID', '=', $id);
		return $query->first();	
	}

	/**
	 * Returns all posts of this class
	 * @return array               
	 */
	public static function all()
	{

		return static::where()->get();
	}


	public static function where($params = null)
	{
		$query = new QueryBuilder(get_class(new static()));
		$query->where(func_get_args());
		return $query;
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