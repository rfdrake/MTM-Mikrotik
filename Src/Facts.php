<?php
//� 2019 Martin Peter Madsen
namespace MTM\Mikrotik;

class Facts
{
	private static $_s=array();
	
	//USE: $aFact		= \MTM\Mikrotik\Facts::$METHOD_NAME();
	
	public static function getTools()
	{
		if (array_key_exists(__FUNCTION__, self::$_s) === false) {
			self::$_s[__FUNCTION__]	= new \MTM\Mikrotik\Facts\Tools();
		}
		return self::$_s[__FUNCTION__];
	}
	public static function getFirmwares()
	{
		if (array_key_exists(__FUNCTION__, self::$_s) === false) {
			self::$_s[__FUNCTION__]	= new \MTM\Mikrotik\Facts\Firmwares();
		}
		return self::$_s[__FUNCTION__];
	}
	public static function getDevices()
	{
		if (array_key_exists(__FUNCTION__, self::$_s) === false) {
			self::$_s[__FUNCTION__]	= new \MTM\Mikrotik\Facts\Devices();
		}
		return self::$_s[__FUNCTION__];
	}
}