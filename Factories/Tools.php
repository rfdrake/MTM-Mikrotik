<?php
//© 2019 Martin Peter Madsen
namespace MTM\Mikrotik\Factories;

class Tools extends Base
{
	public function getNetInstall()
	{
		if (array_key_exists(__FUNCTION__, $this->_s) === false) {
			$this->_s[__FUNCTION__]	= new \MTM\Mikrotik\Tools\NetInstall\API();
		}
		return $this->_s[__FUNCTION__];
	}
}