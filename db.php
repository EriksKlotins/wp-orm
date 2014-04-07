<?php namespace wp_orm;


/**
 * Database abstraction class
 */
class DB
{
	private static $transactions = 0;


	/**
	 * Uzsāk transakciju, ņemot verā to, ka var būt arī nested transakcijas
	 * tad izpildās tikai ārejā
	 * @return [type] [description]
	 */
	public static function beginTransaction()
	{
		global $wpdb;
		static::$transactions++;
		if (static::$transactions == 1)
		{
			$wpdb->query('START TRANSACTION');
			//var_dump('transaction started');
		}
	}

	/**
	 * Izpilda commit
	 * @return [type] [description]
	 */
	public static function commit()
	{
		global $wpdb;
		if (static::$transactions == 1)
		{
			$wpdb->query('COMMIT');
			//var_dump('transaction COMMIT');
		} 
		static::$transactions--;

	}

	/**
	 * Izpilda rollback
	 * @return [type] [description]
	 */
	public static function rollback()
	{
		global $wpdb;
		if (static::$transactions == 1)
		{
			static::$transactions = 0;
			$wpdb->query('ROLLBACK');
			//var_dump('transaction rollback');
		}
		else
		{
			static::$transactions--;
		}
	}


	/**
	 * Izpilda anonīmo funkciju kā transackiju,
	 * ja funkcijā notiek exception tad transakcija nenotiek
	 * @param  Closure $callback [description]
	 * @return void            [description]
	 */
	public static function transaction(\Closure $callback)
	{
		static::beginTransaction();
		try
		{
			$callback();
			static::commit();
		}
		catch(\Exception $e)
		{
			static::rollback();
			throw $e;
		}
	}


	public static function getInstance()
	{
		global $wpdb;
		return $wpdb;
	}
}