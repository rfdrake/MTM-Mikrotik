<?php
//� 2019 Martin Peter Madsen
namespace MTM\Mikrotik\Tools\NetInstall\V1;

class Flash extends Discovery
{	
	protected $_txMaxBytes=1024;
	protected $_txDelay=7500; //delay between packets in micro sec, this can be optimized. Major reason for slow execution
	
	public function flashByMac($macAddr, $fwPath, $scriptPath=null)
	{
		//discover the device using the mac to make sure we see it on the broadcast domain
		$devObj		= $this->getDeviceByMacAddress($macAddr);
		if ($devObj === null) {
			throw new \Exception("Device not discovered with mac address: " . $macAddr);
		}
		return $this->flashByDevice($devObj, $fwPath, $scriptPath);
	}
	public function flashByDevice($devObj, $fwPath, $scriptPath=null)
	{
		$scriptFile	= null;
		//TODO: add check if the fw file matches the CPU arch, the npk holds the data starting around byte 12
		//also check the minimum version requirements
		$fwFile		= \MTM\FS\Factories::getFiles()->getFileFromPath($fwPath);
		if ($fwFile->getExists() === false) {
			throw new \Exception("Firmware file does not exist on path: " . $fwPath);
		} elseif ($scriptPath !== null) {
			$scriptFile	= \MTM\FS\Factories::getFiles()->getFileFromPath($scriptPath);
			if ($scriptFile->getExists() === false) {
				throw new \Exception("Script file does not exist on path: " . $scriptPath);
			}
		}
		$this->runflash($devObj, $fwFile, $scriptFile);
		return $this;
	}
	protected function runflash($devObj, $fwFile, $scriptFile)
	{
		$flashObj				= new \stdClass();
		$flashObj->dstMac		= $devObj->mac;
		$flashObj->srcMac		= $this->_txMacAddr;
		$flashObj->dstPos		= 0;
		$flashObj->srcPos		= 0;
		$flashObj->timeout		= 10000;
		$flashObj->retries		= 0;
		$flashObj->maxRetries	= 3;
		$flashObj->lastData		= null;

		$this->flashOffer($flashObj);
		$this->flashFormat($flashObj);
		$this->flashSpacer($flashObj);
		$this->flashFileHeader($flashObj, $fwFile);
		$this->flashFile($flashObj, $fwFile);
		
		if (is_object($scriptFile) === true) {
			$this->flashSpacer($flashObj);
			$this->flashFileHeader($flashObj, $scriptFile, "autorun.scr");
			$this->flashFile($flashObj, $scriptFile);
		}
		$this->flashSpacer($flashObj);
		$this->flashComplete($flashObj);

		$this->flashReboot($flashObj);
		return $this;
	}
	protected function flashOffer($flashObj)
	{
		try {
			
			$flashObj->dstPos	= 0;
			$flashObj->srcPos	= 1;
			
			$cmd				= array("O", "F", "F", "R", 10, 10);
			$data				= $this->arrayToHex($cmd);
			$this->flashWrite($flashObj, $data);
			
			$flashObj->dstPos	= 1;
			$respObj			= $this->flashWait($flashObj);

			$cmd				= array("Y", "A", "C", "K", 10);
			$okResp				= $this->arrayToHex($cmd);
			if ($okResp == $respObj->payload->hex) {
				$flashObj->retries	= 0;
				return;
			} else {
				throw new \Exception("Invalid Offer ACK");
			}
			
		} catch (\Exception $e) {
			if ($flashObj->retries < $flashObj->maxRetries) {
				$flashObj->retries++;
				return $this->flashOffer($flashObj);
			} else {
				throw $e;
			}
		}
	}
	protected function flashFormat($flashObj)
	{
		try {
			
			if ($flashObj->retries > 0) {
				$flashObj->srcPos--;
				$flashObj->dstPos--;
			}
			$flashObj->srcPos++;
			//tell the routerboard to wipe its flash (i think that is what this position does)
			$data				= "";
			$this->flashWrite($flashObj, $data);
			$flashObj->dstPos++;

			//wait for return, this takes longer since the disk has to format
			//have observed this takes at least 8 sec on RB750Gr3
			$respObj			= $this->flashWait($flashObj, ($flashObj->timeout + 10000));
			
			$cmd				= array("S", "T", "R", "T");
			$okResp				= $this->arrayToHex($cmd);
			if ($okResp == $respObj->payload->hex) {
				$flashObj->retries	= 0;
				return;
			} else {
				throw new \Exception("Invalid Format Start return");
			}
			
		} catch (\Exception $e) {
			if ($flashObj->retries < $flashObj->maxRetries) {
				$flashObj->retries++;
				return $this->flashFormat($flashObj);
			} else {
				throw $e;
			}
		}
	}
	protected function flashSpacer($flashObj)
	{
		try {
			
			if ($flashObj->retries > 0) {
				$flashObj->srcPos--;
				$flashObj->dstPos--;
			}
			$flashObj->srcPos++;
			
			$data				= "";
			$this->flashWrite($flashObj, $data);
			$flashObj->dstPos++;
			$respObj			= $this->flashWait($flashObj);
			
			$cmd				= array("R", "E", "T", "R");
			$okResp				= $this->arrayToHex($cmd);
			if ($okResp == $respObj->payload->hex) {
				$flashObj->retries	= 0;
				return;
			} else {
				throw new \Exception("Invalid Spacer Return");
			}
			
		} catch (\Exception $e) {
			if ($flashObj->retries < $flashObj->maxRetries) {
				$flashObj->retries++;
				return $this->flashSpacer($flashObj);
			} else {
				throw $e;
			}
		}
	}
	protected function flashFileHeader($flashObj, $fileObj, $fileName=null)
	{
		//send the file header and size
		if ($fileName === null) {
			$fileName	= $fileObj->getName();
		}
		
		try {
			
			if ($flashObj->retries > 0) {
				$flashObj->srcPos--;
				$flashObj->dstPos--;
			}
			$flashObj->srcPos++;
			
			$cmd				= array("F", "I", "L", "E", 10);
			$cmd				= array_merge($cmd, str_split($fileName, 1));
			$cmd[]				= 10;
			$cmd				= array_merge($cmd, str_split($fileObj->getByteCount(), 1));
			$cmd[]				= 10;
			$data				= $this->arrayToHex($cmd);
			$this->flashWrite($flashObj, $data);
			$flashObj->dstPos++;
			$respObj			= $this->flashWait($flashObj);
			
			$cmd				= array("R", "E", "T", "R");
			$okResp				= $this->arrayToHex($cmd);
			if ($okResp == $respObj->payload->hex) {
				$flashObj->retries	= 0;
				return;
			} else {
				throw new \Exception("Invalid File Header Return");
			}
			
		} catch (\Exception $e) {
			if ($flashObj->retries < $flashObj->maxRetries) {
				$flashObj->retries++;
				return $this->flashFileHeader($flashObj);
			} else {
				throw $e;
			}
		}
	}
	protected function flashFile($flashObj, $fileObj, $cPos=1)
	{
		//no retries on this function, if we dont make it there is no recovering
		//it takes too long to retrieve the response and re-try from the failed position
		//i have tried many different ways. So if doing flashing with high latency wrap
		//the UDP connection in a TCP based VPN to ensure delivery
		
		//if we require ZTS and spawn a seperate tread for reading it might be possible
		//or just use a language with native support for threads :)
		$maxPos		= $fileObj->getByteCount();
		while (true) {

			$flashObj->srcPos++;

			$cmd			= $fileObj->getBytes($this->_txMaxBytes, $cPos);
			$data		    = bin2hex($cmd);
			$this->flashWrite($flashObj, $data);
			
			$flashObj->dstPos++;
			$cPos			= $cPos + $this->_txMaxBytes;
			if ($cPos >= $maxPos) {
			
				try {

					$respObj			= $this->flashWait($flashObj);
					$cmd				= array("R", "E", "T", "R");
					$okResp				= $this->arrayToHex($cmd);
					if ($okResp == $respObj->payload->hex) {
						$flashObj->retries	= 0;
						return;
					} else {
						throw new \Exception("Invalid File Return");
					}

				} catch (\Exception $e) {
					throw $e;
				}
			
			} else {
				//wait for a bit before sending the next frame
				//this can be optimized, this is the main source of delay
				//the issue may be out of order errors
				usleep($this->_txDelay);
			}
		}
	}
	protected function flashComplete($flashObj)
	{
		try {
			
			if ($flashObj->retries > 0) {
				$flashObj->srcPos--;
				$flashObj->dstPos--;
			}
			$flashObj->srcPos++;
			
			$cmd				= array("F", "I", "L", "E", 10);
			$data				= $this->arrayToHex($cmd);
			$this->flashWrite($flashObj, $data);
			$flashObj->dstPos++;
			$respObj			= $this->flashWait($flashObj);
			
			$cmd				= array("W", "T", "R", "M");
			$okResp				= $this->arrayToHex($cmd);
			if ($okResp == $respObj->payload->hex) {
				$flashObj->retries	= 0;
				return;
			} else {
				throw new \Exception("Invalid Termination Return");
			}
			
		} catch (\Exception $e) {
			if ($flashObj->retries < $flashObj->maxRetries) {
				$flashObj->retries++;
				return $this->flashComplete($flashObj);
			} else {
				throw $e;
			}
		}
	}
	protected function flashReboot($flashObj)
	{	
		try {
	
			if ($flashObj->retries > 0) {
				$flashObj->srcPos--;
				$flashObj->dstPos--;
			}
			$flashObj->srcPos++;
			
			$cmd				= array("T", "E", "R", "M", 10);
			$cmd				= array_merge($cmd, str_split("Installation successful", 1));
			$cmd[]				= 10;
			$data				= $this->arrayToHex($cmd);
			$this->flashWrite($flashObj, $data);
			$flashObj->dstPos++;
			
			//the real net install seems to get a return, but a packet capture does not seem to show it
			//on UDP:5000, maybe its using another protocol to establish if the unit is really down?
			return;
			
		} catch (\Exception $e) {
			if ($flashObj->retries < $flashObj->maxRetries) {
				return $this->flashReboot($flashObj);
			} else {
				throw $e;
			}
		}
	}
	
	private function flashWrite($flashObj, $hexData)
	{
		//craft transmission
		$data		= strtolower($flashObj->srcMac) . strtolower($flashObj->dstMac);
		
		//these 4 octets seem to be a delimitor
		$data		.= "0000";
		
		//set the payload size
		$data		.= $this->decCountToHex(strlen(hex2bin($hexData)));
		
		//set the tx position
		$data		.= $this->decCountToHex($flashObj->srcPos) . $this->decCountToHex($flashObj->dstPos);
		
		//now add the real payload
		$data		.= $hexData;
		
		//send
		$this->socketWrite(hex2bin($data));
		return $this;
	}
	private function flashWait($flashObj, $timeoutMs=null)
	{
		if ($timeoutMs === null) {
			$timeoutMs	= $flashObj->timeout;
		}
		
		$timeout	= $timeoutMs;
		$sTime	    = \MTM\Utilities\Factories::getTime()->getMicroEpoch();
		while (true) {
			
			try {
			
				$dataObj		= $this->socketRead($timeout);
				if (
					$dataObj->headers->srcMac == $flashObj->dstMac
					&& (
						$dataObj->headers->dstMac == $flashObj->srcMac
						|| $dataObj->headers->dstMac == "000000000000"
					)
				) {
					
					$flashObj->lastData	= $dataObj;
					if (
						$dataObj->headers->dstPos == $flashObj->srcPos
						&& $dataObj->headers->srcPos == $flashObj->dstPos
					) {
						return $dataObj;
					}
				}
			
			} catch (\Exception $e) {
				switch ($e->getCode()) {
					case 13465:
						//timeout reading
						throw new \Exception("Flash Wait Timed out");
					default:
						throw $e;
				}
			}
			
			$cTime	    = \MTM\Utilities\Factories::getTime()->getMicroEpoch();
			$timeout	= $timeoutMs - intval(($cTime - $sTime) * 1000);
			if ($timeout < 1) {
				throw new \Exception("Flash Wait Timeout");
			}
		}
	}
}