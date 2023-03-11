<?php
//� 2019 Martin Peter Madsen
namespace MTM\Mikrotik\Facts;

class Firmwares extends Base
{
	protected $_basePath=null;
	
	public function setBasePath($dirObj)
	{
		//this directory holds all the RouterOS and Switch OS firmwares using the standard file names as tehy
		//apper when downloaded from Mikrotik.com e.g.:
		//routeros-7.8-arm.npk
		//routeros-7.7-arm64.npk
		if ($dirObj instanceof \MTM\FS\Models\Directory === false) {
			if ($this->isStr($dirObj, false) === true) {
				$dirObj			= \MTM\FS\Factories::getDirectories()->getDirectory($dirObj);
			} else {
				throw new \Exception("Invalid input");
			}
		}
		if ($dirObj->getExists() === false) {
			throw new \Exception("Directory does not exist");
		}
		$this->_basePath	= $dirObj;
		return $this;
	}
	public function getBasePath()
	{
		return $this->_basePath;
	}
	public function getByDevice($devObj, $type="routeros")
	{
		//will return the newest version, need to expand with channel
		
		if ($devObj instanceof \MTM\Mikrotik\Models\Device\Alpha === false) {
			throw new \Exception("Invalid device input");
		} elseif ($this->getBasePath() === null) {
			throw new \Exception("Firmware base directory has not been set");
		}
		$this->isStr($type, true);
		if ($type !== "routeros" && $type !== "swos") {
			throw new \Exception("Not handled for type: '".$type."'");
		}
		
		//TODO: add check if the fw file matches the CPU arch, the npk holds the data in the first few bytes
		//also check the minimum version requirements
		
		$curVer			= 0;
		$file			= null;
		$fileNames		= $this->getBasePath()->getFileNames();
		foreach ($fileNames as $fileName) {
			if (preg_match("/^".$type."\-(.+)\.(npk|bin)$/", $fileName, $raw) === 1) {
				
				if ($type === "routeros") {
					if (preg_match("/^(.+)-(.+)$/", $raw[1], $raw2) === 1) {
						
						if ($raw2[2] === $devObj->getArchitecture() && $raw2[1] > $curVer) {
							$curVer		= $raw2[1];
							$file		= $fileName;
						}
						
					} else {
						throw new \Exception("Not handled for filename: '".$fileName."'");
					}
					
				} elseif ($type === "swos") {
					throw new \Exception("Not handled for type: '".$type."'");
				} else {
					throw new \Exception("Not handled for type: '".$type."'");
				}
			}
		}
		
		if ($curVer !== null) {
			if ($curVer < $devObj->getMinimumVersion()) {
				throw new \Exception("The newest available firmware for model: '".$devObj->getModel()."', is not new enough. Device requires: '".$devObj->getMinimumVersion()."'. Newest version we found was: '".$curVer."' for architecture: '".$devObj->getArchitecture()."'");
			}
		} else {
			throw new \Exception("Unable to find suitable firmware for model: '".$devObj->getModel()."'");
		}
		
		if (array_key_exists($file, $this->_s) === false) {
			$fileObj			= \MTM\FS\Factories::getFiles()->getFile($file, $this->getBasePath());
			$rObj				= new \MTM\Mikrotik\Models\Firmware\Zulu();
			$rObj->setArchitecture($devObj->getArchitecture())->setVersion($curVer)->setFile($fileObj);
			$this->_s[$file]	= $rObj;
		}
		return $this->_s[$file];
	}
}

