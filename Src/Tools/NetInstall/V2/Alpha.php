<?php
//� 2023 Martin Peter Madsen
namespace MTM\Mikrotik\Tools\NetInstall\V2;

abstract class Alpha extends \MTM\Utilities\Tools\Validations\V1
{
	protected $_ifName=null;
	protected $_ifMac=null;
	protected $_ifIp=null;
	
	public function getInterfaceName()
	{
		return $this->_ifName;
	}
	public function getMacAddress()
	{
		return $this->_ifMac;
	}
	public function getIpAddress()
	{
		return $this->_ifIp;
	}
}