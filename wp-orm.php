<?php

/*
Plugin Name: WP ORM
Plugin URI: http://localhost
Description: Adds Eloquent like functionalty to Wordpress DB operations 
Version: 1.0
Author: J.Cibankovs & E.Klotins
Author URI: #
License: GPLv2 or later
*/

require dirname(__FILE__).'/DataType.php';
require dirname(__FILE__).'/QueryBuilder.php';
require dirname(__FILE__).'/BaseModel.php';


class WP_ORM extends \wp_orm\BaseModel
{
	
}


