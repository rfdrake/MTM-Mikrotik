<?php
//© 2019 Martin Peter Madsen
namespace MTM\Mikrotik;

class Factories
{
	private static $_s=array();
	
	//USE: $aFact		= \MTM\Mikrotik\Factories::$METHOD_NAME();
	
	public static function getTools()
	{
		if (array_key_exists(__FUNCTION__, self::$_s) === false) {
			self::$_s[__FUNCTION__]	= new \MTM\Mikrotik\Factories\Tools();
		}
		return self::$_s[__FUNCTION__];
	}
}