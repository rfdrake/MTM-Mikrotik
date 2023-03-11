<?php
//� 2023 Martin Peter Madsen
namespace MTM\Mikrotik\Tools\NetInstall\V2;

abstract class Initialize extends Discover
{
	public function setInterface($name, $mac, $ip)
	{
		$this->isStr($name, true);
		$this->isMacAddr($mac, true);
		$this->isIpV4($ip, true);
		$this->_ifName	= $name;
		$this->_ifMac	= strtolower($mac);
		$this->_ifIp	= $ip;
		return $this;
	}

	public function autoPopulateInterface()
	{
		$this->_ifName	= null;
		$this->_ifMac	= null;
		$this->_ifIp	= null;
		
		if (PHP_OS_FAMILY === "Linux") {
			
			$strCmd		= "ip -j address";
			$rData		= shell_exec($strCmd);
			$ifObjs		= json_decode($rData);
			foreach ($ifObjs as $ifObj) {
				if ($ifObj->ifname === "lo") {
					//dont want the loop back
				} else {
					
					$ip		= null;
					foreach ($ifObj->addr_info as $addr) {
						if ($addr->family === "inet") {
							$ip		= $addr->local;
							break;
						}
					}
					if ($ip !== null) {
						$this->setInterface($ifObj->ifname, str_replace(array(":"), array(""), $ifObj->address), $ip);
						break;
					}
				}
			}
			if ($this->_ifName === null) {
				throw new \Exception("Failed to determine interface information");
			}
			
			
		} else {
			throw new \Exception("Not handled for OS Family: '".PHP_OS_FAMILY."'");
		}
		return $this;
	}
}