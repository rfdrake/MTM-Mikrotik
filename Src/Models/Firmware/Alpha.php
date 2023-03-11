<?php
//� 2023 Martin Peter Madsen
namespace MTM\Mikrotik\Models\Firmware;

abstract class Alpha extends \MTM\Utilities\Tools\Validations\V1
{
	protected $_cpuArch=null;
	protected $_version=null;
	protected $_fileObj=null;
	
	public function setArchitecture($val)
	{
		$this->isStr($val, true);
		$this->_cpuArch		= $val;
		return $this;
	}
	public function getArchitecture()
	{
		return $this->_cpuArch;
	}
	public function setVersion($val)
	{
		$this->isStr($val, true);
		$this->_version		= $val;
		return $this;
	}
	public function getVersion()
	{
		return $this->_version;
	}
	public function setFile($fileObj)
	{
		if ($fileObj instanceof \MTM\FS\Models\File === false) {
			throw new \Exception("Invalid input");
		}
		$this->_fileObj		= $fileObj;
		return $this;
	}
	public function getFile()
	{
		return $this->_fileObj;
	}
}