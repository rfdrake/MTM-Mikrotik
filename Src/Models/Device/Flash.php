<?php
//� 2023 Martin Peter Madsen
namespace MTM\Mikrotik\Models\Device;

abstract class Flash extends Alpha
{
	protected $_txMaxBytes=1024;
	protected $_txDelay=7500; //delay between packets in micro sec, this can be optimized. Major reason for slow execution
	
	public function flash($fwObj, $rscPath=null)
	{
		if ($fwObj instanceof \MTM\Mikrotik\Models\Firmware\Zulu === false) {
			throw new \Exception("Invalid firmware input");
		}
		if ($rscPath !== null) {
			$rscFile			= \MTM\FS\Factories::getFiles()->getFileFromPath($rscPath);
			if ($rscFile->getExists() === false) {
				throw new \Exception("RSC file does not exist");
			}
		} else {
			$rscFile	= null;
		}
		
		$fwFile			= $fwObj->getFile();
		if ($fwFile->getExists() === false) {
			throw new \Exception("Firmware file does not exist");
		} elseif ($fwObj->getArchitecture() !== $this->getArchitecture()) {
			throw new \Exception("Firmware file does not match the architecture of the device");
		}

		//delay has to be adjusted based on the type of device and the speed of its flash
		$this->_txDelay		= $this->getFlashTxDelay();
		
		$this->flashOffer();
		$this->flashFormat();
		$this->flashFileHeader($fwFile, $fwFile->getName());
		$this->flashFile($fwFile);
		if ($rscFile !== null) {
			$this->flashFileHeader($rscFile, "autorun.scr");
			$this->flashFile($rscFile);
		}
		$this->flashComplete();
		
		return $this;
	}
	public function getFlashTxDelay()
	{
		//may have to be adjusted for remote vs. local
		if ($this->getModel() === "C52iG-5HaxD2HaxD") {
			return 1500;
		} elseif ($this->getModel() === "RBD52G-5HacD2HnD") {
			return 2500;
		} elseif ($this->getModel() === "RB4011iGS+") {
			return 1500;
		}
		return 7500;
	}
	protected function flashOffer()
	{
		try {
			
			$rObj	= $this->flashRead();
			if ($rObj->srcPos === 1 && $rObj->dstPos === 0) {
				$cmdHex				= $this->arrayToHex(array("O", "F", "F", "R", 10, 10));
				$this->flashWrite($cmdHex, 1, 0);
				$rObj	= $this->flashWait(1, 1);
			}
			
		} catch (\Exception $e) {
			throw $e;
		}
	}
	protected function flashFormat()
	{
		try {
			
			$rObj	= $this->flashRead();
			if ($rObj->srcPos === 1 && $rObj->dstPos === 1) {
				//maybe older versions reply with "Y", "A", "C", "K", 10
				$cmdHex				= $this->arrayToHex(array("Y", "A", "C", "K"));
				if ($cmdHex != bin2hex($rObj->data)) {
					throw new \Exception("Invalid Flash Offer ACK response");
				}
				$this->flashWrite("", 2, 1);
				$rObj	= $this->flashWait(2, 2, 120000); //can take a long time to format
				
			} elseif ($rObj->srcPos === 4 && $rObj->dstPos === 3) {
				
				$cmdHex				= $this->arrayToHex(array("W", "T", "R", "M"));
				if ($cmdHex != bin2hex($rObj->data)) {
					throw new \Exception("Flashing was terminated");
				} else {
					throw new \Exception("Unknown error");
				}
			}
			if ($rObj->srcPos === 2 && $rObj->dstPos === 2) {

				$cmdHex				= $this->arrayToHex(array("S", "T", "R", "T"));
				$this->flashWrite($cmdHex, 3, 2);
				$rObj		= $this->flashWait(3, 3, 15000);
			}
			if ($rObj->srcPos === 3 && $rObj->dstPos === 3) {
				$cmdHex		= $this->arrayToHex(array("R", "E", "T", "R"));
				if ($cmdHex != bin2hex($rObj->data)) {
					throw new \Exception("Invalid Flash format ACK response");
				}
			}

		} catch (\Exception $e) {
			throw $e;
		}
	}
	protected function flashFileHeader($fileObj, $fileName)
	{
		try {

			$rObj		= $this->flashRead();
			$cmdHex		= $this->arrayToHex(array_merge(array("F", "I", "L", "E", 10), str_split($fileName, 1), array(10), str_split($fileObj->getByteCount(), 1), array(10)));
			$this->flashWrite($cmdHex, ($rObj->srcPos + 1), $rObj->dstPos);
			$rObj		= $this->flashWait(($rObj->srcPos + 1), ($rObj->dstPos + 1));
			$cmdHex		= $this->arrayToHex(array("R", "E", "T", "R"));
			if ($cmdHex != bin2hex($rObj->data)) {
				throw new \Exception("Invalid Flash file header ACK response");
			}

		} catch (\Exception $e) {
			throw $e;
		}
	}
	protected function flashFile($fileObj)
	{
		try {

			$rObj		= $this->flashRead();
			$filePos	= 1;
			$srcPos		= $rObj->srcPos;
			$dstPos		= $rObj->dstPos;
			$maxPos		= $fileObj->getByteCount();
			$checkInt	= 101; //interval to check if the device has caught up
			
			while (true) {
				
				$bytes			= $fileObj->getBytes($this->_txMaxBytes, $filePos);
				if ($bytes !== null) {
					
					$srcPos++;
					$filePos	+= $this->_txMaxBytes;
					$this->flashWrite(bin2hex($bytes), $srcPos, $dstPos);
					$dstPos++;

					if ($srcPos % $checkInt === 0) {
						//make sure the device is not drowning in data
						$this->flashWait($srcPos, $dstPos);
					} else {
						//wait for a bit before sending the next frame, this can be optimized, this is the main source of delay
						//the issue may be out of order errors
						usleep($this->_txDelay);
					}

				} else {
					
					$rObj		= $this->flashWait($srcPos, $dstPos);
					$this->flashWrite("", ($rObj->srcPos + 1), $rObj->dstPos);
					$rObj		= $this->flashWait(($rObj->srcPos + 1), ($rObj->dstPos + 1));
					$cmdHex		= $this->arrayToHex(array("R", "E", "T", "R"));
					if ($cmdHex != bin2hex($rObj->data)) {
						throw new \Exception("Invalid Flash file ACK response");
					} else {
						break;
					}
				}
			}
			
		} catch (\Exception $e) {
			throw $e;
		}
	}
	protected function flashComplete()
	{
		try {
			
			$rObj		= $this->flashRead();
			$cmdHex		= $this->arrayToHex(array("R", "E", "T", "R"));
			if ($cmdHex != bin2hex($rObj->data)) {
				throw new \Exception("Invalid Flash success ACK response");
			}
			
			$cmdHex		= $this->arrayToHex(array("F", "I", "L", "E", 10));
			$this->flashWrite($cmdHex, ($rObj->srcPos + 1), $rObj->dstPos);
			
			//we should receive 3x WTERM messages
			$cmdHex		= $this->arrayToHex(array("W", "T", "R", "M"));
			for ($x=0; $x < 3; $x++) {
				try {
					$rObj	= $this->flashRead();
					if ($cmdHex != bin2hex($rObj->data)) {
						throw new \Exception("Invalid Flash complete ACK response");
					}
				} catch (\Exception $e) {
					//CRS328 returns 3 messages, AX2 seems not to
				}
			}
			
			$cmdHex		= $this->arrayToHex(array_merge(array("T", "E", "R", "M", 10), str_split("Installation successful", 1), array(10)));
			$this->flashWrite($cmdHex, ($rObj->srcPos + 1), $rObj->dstPos);

		} catch (\Exception $e) {
			throw $e;
		}
	}
	protected function flashWait($srcPos, $dstPos, $timeout=10000)
	{
		$toolObj	= \MTM\Mikrotik\Facts::getTools()->getNetInstall(2);
		$timeFact	= \MTM\Utilities\Factories::getTime();
		$cTime  	= $timeFact->getMicroEpoch();
		$tTime		= $cTime + ($timeout / 1000);
		while (true) {
			$rObj		= $this->flashRead($timeout);
			if ($rObj->srcPos === $srcPos && $rObj->dstPos === $dstPos) {
				return $rObj;
			} elseif ($timeFact->getMicroEpoch() > $tTime) {
				throw new \Exception("Did not receive a frame with the specified src and dst position in time");
			}
		}
	}
	protected function flashRead($timeout=10000)
	{
		$toolObj	= \MTM\Mikrotik\Facts::getTools()->getNetInstall(2);
		$timeFact	= \MTM\Utilities\Factories::getTime();
		$cTime  	= $timeFact->getMicroEpoch();
		$tTime		= $cTime + ($timeout / 1000);
		while (true) {
			$rObj		= $toolObj->read($timeout, false);
			if ($rObj->srcMac === $this->getMacAddress()) {
				return $rObj;
			} elseif ($timeFact->getMicroEpoch() > $tTime) {
				throw new \Exception("Did not receive a frame from the device in time");
			}
		}
	}
	protected function flashWrite($hexData, $srcPos, $dstPos)
	{
		$toolObj	= \MTM\Mikrotik\Facts::getTools()->getNetInstall(2);
		//craft transmission
		$data		= strtolower($toolObj->getMacAddress()) . strtolower($this->getMacAddress());
		
		//these 4 octets seem to be a delimitor
		$data		.= "0000";
		
		//set the payload size
		$data		.= $this->decToHex(strlen(hex2bin($hexData)));

		//set the tx position
		$data		.= $this->decToHex($srcPos) . $this->decToHex($dstPos);
		
		//now add the real payload
		$data		.= $hexData;

		//write the command
		$toolObj->write(hex2bin($data));
		return $this;
	}
	protected function decToHex($dec)
	{
		$oHex	= dechex($dec);
		$oHex	= str_repeat("0", 4 - strlen($oHex)).$oHex;
		return substr($oHex, 2, 2).substr($oHex, 0, 2);
	}
	protected function arrayToHex($array)
	{
		$hexStr	= "";
		foreach ($array as $item) {
			if (is_int($item) === true) {
				$hex	= dechex($item);
			} else {
				$hex	= dechex(ord($item));
			}
			$hexStr	.= str_repeat("0", 2 - strlen($hex)) . $hex;
		}
		return $hexStr;
	}
}