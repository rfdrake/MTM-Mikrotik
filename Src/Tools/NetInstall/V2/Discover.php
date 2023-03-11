<?php
//� 2023 Martin Peter Madsen
namespace MTM\Mikrotik\Tools\NetInstall\V2;

abstract class Discover extends Alpha
{
	public function discover($timeoutMs=10000)
	{
		$this->isUsign32Int($timeoutMs, true);
		$timeFact	= \MTM\Utilities\Factories::getTime();
		$cTime  	= $timeFact->getMicroEpoch();
		$tTime		= $cTime + ($timeoutMs / 1000);
		$devObjs	= array();
		while (true) {
			
			try {
				
				$rObj		= $this->read($timeoutMs, false);
				if ($rObj->data !== null) {
					
					$rows				= explode("\n", $rObj->data);
					if (array_key_exists(1, $rows) === true) {
						
						$licId	= strtolower(trim($rows[1]));
						if ($licId != "") {
							if (array_key_exists($licId, $devObjs) === false) {

								$devObj				= \MTM\Mikrotik\Facts::getDevices()->get();
								$devObj->setMacAddress($rObj->srcMac);
								$devObj->setLicenseId(trim($rows[1]));
	
								if (array_key_exists(2, $rows) === true) {
									$devObj->setLicenseKey(base64_decode(trim($rows[2])));
								}
								if (array_key_exists(3, $rows) === true) {
									$devObj->setModel(trim($rows[3]));
								}
								if (array_key_exists(4, $rows) === true) {
									$devObj->setArchitecture(trim($rows[4]));
								}
								if (array_key_exists(5, $rows) === true) {
									$devObj->setMinimumVersion(trim($rows[5]));
								}
								if (array_key_exists(6, $rows) === true) {
									$devObj->setCurrentVersion(trim($rows[6]));
								}
								$devObjs[$licId]	= $devObj;
								
							} else {
								
								//there could be multiple ports plugged in
								$devObj		= $devObjs[$licId];
								$newMac		= hexdec($rObj->srcMac);
								$curMac		= hexdec($devObj->getMacAddress());
								
								
								$wantLower	= true;
								if ($devObj->getModel() === "C52iG-5HaxD2HaxD") {
									$wantLower	= true;
								}
								if ($wantLower === true && $newMac < $curMac) {
									$devObj->setMacAddress($rObj->srcMac);
								} elseif ($wantLower === false && $newMac > $curMac) {
									$devObj->setMacAddress($rObj->srcMac);
								} else {
									//keep the current mac
								}
							}
						}
					}
				}
				
			} catch (\Exception $e) {
				switch ($e->getCode()) {
					default:
						throw $e;
				}
			}
			$cTime  	= $timeFact->getMicroEpoch();
			if ($cTime > $tTime) {
				//we have run out of time
				break;
			}
		}
		
		return array_values($devObjs);
	}
}