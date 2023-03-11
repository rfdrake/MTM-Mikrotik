<?php
//� 2023 Martin Peter Madsen
namespace MTM\Mikrotik\Models\Device;

abstract class Alpha extends \MTM\Utilities\Tools\Validations\V1
{
	protected $_macAddr=null;
	protected $_licenseId=null;
	protected $_licenseKey=null;
	protected $_modelNbr=null;
	protected $_cpuArch=null;
	protected $_minVer=null;
	protected $_curVer=null;
	
	public function setMacAddress($val)
	{
		$this->isMacAddr($val, true);
		$this->_macAddr			= strtolower($val);
		return $this;
	}
	public function getMacAddress()
	{
		return $this->_macAddr;
	}
	public function setLicenseId($val)
	{
		$this->isStr($val, true);
		$this->_licenseId		= trim($val);
		return $this;
	}
	public function getLicenseId()
	{
		return $this->_licenseId;
	}
	public function setLicenseKey($val)
	{
		$this->_licenseKey		= $val;
		return $this;
	}
	public function getLicenseKey()
	{
		return $this->_licenseKey;
	}
	public function setModel($val)
	{
		$this->isStr($val, true);
		$this->_modelNbr		= trim($val);
		return $this;
	}
	public function getModel()
	{
		return $this->_modelNbr;
	}
	public function setArchitecture($val)
	{
		$this->isStr($val, true);
		$this->_cpuArch		= trim($val);
		return $this;
	}
	public function getArchitecture()
	{
		return $this->_cpuArch;
	}
	public function setMinimumVersion($val)
	{
		$this->isStr($val, true);
		$this->_minVer		= trim($val);
		return $this;
	}
	public function getMinimumVersion()
	{
		return $this->_minVer;
	}
	public function setCurrentVersion($val)
	{
		$this->isStr($val, true);
		$this->_curVer		= trim($val);
		return $this;
	}
	public function getCurrentVersion()
	{
		return $this->_curVer;
	}
}