<?php namespace wp_orm;
class DataType 
{
	/**
		Data type convertation functions
	*/

	/**
	 * Converts wp inner dateTime string to php date
	 * @param  string $dateTimeString string to conver
	 * @return DateTime         
	 */
	public static function toDate($dateTimeString)
	{		
		if(strlen($dateTimeString) != 0) {
			$timestamp = strtotime($dateTimeString);
			$datetime = new \DateTime();
			$datetime->setTimestamp(strtotime($dateTimeString)); 
		}
		else {
			$datetime = null;
		}
		return $datetime;
	}

	/**
	 * Converts php DateTime to wp inner date string format
	 * @param  DateTime $datetime DateTime object to conver
	 * @return string
	 */
	public static function toWpDate($datetime)
	{
		return ($datetime != null) ? $datetime->format('Y-m-d H:i:s') : '';
	}

	public static function toNumber($numberString)
	{
		return (strlen($numberString) != 0 ) ? (float) $numberString : null;
	}

	public static function toWpNumber($number)
	{
		return (!is_null($number)) ? (string) $number : '';
	}

	public static function toInt($numberString)
	{
		return (strlen($numberString) != 0 ) ? (int) $numberString : null;
	}

	public static function toWpInt($number)
	{
		return (!is_null($number)) ? (string) ((int) $number) : '';
	}

	public static function toBoolean($boolString)
	{
		return (strlen($boolString) != 0 ) ? (boolean) $boolString : null;
	}

	public static function toWpBoolean($bool)
	{
		return (!is_null($bool)) ? (($bool) ? '1' : '0') : '';
	}

}