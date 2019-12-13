<?php
//© 2019 Martin Peter Madsen
namespace MTM\Mikrotik\Tools\NetInstall;

class Discovery
{	
	public function getDeviceList($timeoutMs=10000)
	{
		$timeout	= $timeoutMs;
		$tObjs		= array();
		$rObjs		= array();
		$sTime  	= \MTM\Utilities\Factories::getTime()->getMicroEpoch();
		while (true) {
			
			try {
				
				$dataObj	= $this->socketRead($timeout);
				$rows		= explode("\n", $dataObj->payload->bin);
				if (count($rows) == 7) {
					
					$macAddr	= $dataObj->headers->srcMac;
					if (array_key_exists($macAddr, $tObjs) === false) {
						$tObjs[$macAddr]		= "Handled"; //tracking set
						$devObj					= new \stdClass();
						$licObj					= new \stdClass();
						$rbObj					= new \stdClass();
						
						$devObj->mac			= $macAddr;
						$devObj->license		= $licObj;
						$devObj->routerboard	= $rbObj;
						
						$licObj->id				= null;
						$licObj->key			= null;
						if (trim($rows[1]) != "") {
							$licObj->id			= trim($rows[1]);
						}
						if (trim($rows[2]) != "") {
							$licObj->key			= base64_decode(trim($rows[2]));
						}

						$rbObj->model		    = trim($rows[3]);
						$rbObj->architecture	= trim($rows[4]);
						$rbObj->minOs			= trim($rows[5]);//minimum version required for install
						
						//make sure we do not already discovered this device on another of its interfaces
						$exist		= false;
						$nMacVal	= hexdec(substr($macAddr, -8)); //on 32bit systems we cannot represent a 48 bit value
						foreach ($rObjs as $rId => $eObj) {
							if ($licObj->id == $eObj->license->id) {
								if ($nMacVal < hexdec(substr($eObj->mac, -8))) {
									//this interface has a lower index, lets use it instead
									//mikrotik allows flashing on ether1
									$rObjs[$rId]	= $devObj;
								}
								$exist	= true;
								break;
							}
						}
						if ($exist === false) {
							$rObjs[]      = $devObj;
						}

					} else {
						//already encountered that device once before, we are done
						break;
					}
					
				} else {
					//we got another type of message
				}

				//set a new timeout value, read returns any frame it receives
				$cTime  	= \MTM\Utilities\Factories::getTime()->getMicroEpoch();
				$timeout	= $timeoutMs - intval(($cTime - $sTime) * 1000);
				if ($timeout < 1) {
					//we have run out of time
					break;
				}
				
			} catch (\Exception $e) {
				switch ($e->getCode()) {
					case 13465:
						//timeout reading
						break;
					default:
						throw $e;
				}
			}
		}
		
		return $rObjs;
	}
	public function getDeviceByMacAddress($macAddr, $timeoutMs=10000)
	{
		$macAddr	= strtoupper(trim($macAddr));
		$devObjs	= $this->getDeviceList($timeoutMs);
		foreach ($devObjs as $devObj) {
			if ($devObj->mac == $macAddr) {
				return $devObj;
			}
		}
		return null;
	}
}