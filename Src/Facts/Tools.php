<?php
//� 2019 Martin Peter Madsen
namespace MTM\Mikrotik\Facts;

class Tools extends Base
{
	public function getNetInstall($v=1)
	{
		if (array_key_exists(__FUNCTION__.$v, $this->_s) === false) {
			if ($v === 1) {
				$this->_s[__FUNCTION__.$v]	= new \MTM\Mikrotik\Tools\NetInstall\V1\API();
			} elseif ($v === 2) {
				$this->_s[__FUNCTION__.$v]	= new \MTM\Mikrotik\Tools\NetInstall\V2\Zulu();
			} else {
				throw new \Exception("Invalid version");
			}
		}
		return $this->_s[__FUNCTION__.$v];
	}
}