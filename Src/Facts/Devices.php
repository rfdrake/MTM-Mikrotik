<?php
//� 2019 Martin Peter Madsen
namespace MTM\Mikrotik\Facts;

class Devices extends Base
{
	public function get()
	{
		$rObj	= new \MTM\Mikrotik\Models\Device\Zulu();
		return $rObj;
	}
}