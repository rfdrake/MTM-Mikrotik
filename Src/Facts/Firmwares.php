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
		
		$curVer			= array_map("intval", explode(".", "0.0.0"));
		$file			= null;
		$fileNames		= $this->getBasePath()->getFileNames();
		foreach ($fileNames as $fileName) {
			if (preg_match("/^(.+)\-(.+)\-(.+)\.(npk)$/", $fileName, $raw) === 1) {
				if ($raw[1] === $type && $devObj->getArchitecture() === $raw[3]) {
					
					$replace	= false;
					$fileVer	= array_map("intval", explode(".", $raw[2]));
					if (array_key_exists(1, $fileVer) === false) {
						$fileVer[1]		= 0;
					}
					if (array_key_exists(2, $fileVer) === false) {
						$fileVer[2]		= 0;
					}
					if ($curVer[0] < $fileVer[0]) {
						$replace	= true;
					} elseif ($curVer[0] === $fileVer[0] && $curVer[1] < $fileVer[1]) {
						$replace	= true;
					} elseif ($curVer[0] === $fileVer[0] && $curVer[1] === $fileVer[1] && $curVer[2] < $fileVer[2]) {
						$replace	= true;
					}
					
					if ($replace === true) {
						$curVer		= $fileVer;
						$file		= $fileName;
					}
				}
			}
		}
		if ($curVer[1] !== 0) {
			
			$accept		= false;
			$minVer		= array_map("intval", explode(".", $devObj->getMinimumVersion()));
			if (array_key_exists(1, $minVer) === false) {
				$minVer[1]		= 0;
			}
			if (array_key_exists(2, $minVer) === false) {
				$minVer[2]		= 0;
			}
			if ($minVer[0] < $fileVer[0]) {
				$accept		= true;
			} elseif ($minVer[0] === $curVer[0] && $minVer[1] < $curVer[1]) {
				$accept		= true;
			} elseif ($minVer[0] === $curVer[0] && $minVer[1] === $curVer[1] && $minVer[2] <= $curVer[2]) {
				$accept		= true;
			}
			if ($accept === false) {
				throw new \Exception("The newest available firmware for model: '".$devObj->getModel()."', is not new enough. Device requires: '".$devObj->getMinimumVersion()."'. Newest version we found was: '".implode(".", $curVer)."' for architecture: '".$devObj->getArchitecture()."'");
			}

		} else {
			throw new \Exception("Unable to find suitable firmware for model: '".$devObj->getModel()."'");
		}
		
		if (array_key_exists($file, $this->_s) === false) {
			$fileObj			= \MTM\FS\Factories::getFiles()->getFile($file, $this->getBasePath());
			$rObj				= new \MTM\Mikrotik\Models\Firmware\Zulu();
			$rObj->setArchitecture($devObj->getArchitecture())->setVersion(implode(".", $curVer))->setFile($fileObj);
			$this->_s[$file]	= $rObj;
		}
		return $this->_s[$file];
	}
}

