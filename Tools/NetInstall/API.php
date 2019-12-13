<?php
//© 2019 Martin Peter Madsen
namespace MTM\Mikrotik\Tools\NetInstall;

class API extends Flash
{
	protected $_s=array();
	protected $_rxSockObj=null;
	protected $_txSockObj=null;
	protected $_rxMaxBytes=1024;
	protected $_txIpAddr=null;
	protected $_txMacAddr=null;
	
	public function __destruct()
	{
		if (is_resource($this->_rxSockObj) === true) {
			socket_close($this->_rxSockObj);
		}
		if (is_resource($this->_txSockObj) === true) {
			socket_close($this->_txSockObj);
		}
	}
	public function setTxConfig($ipAddr, $macAddr)
	{
		//only set this if you have multiple interfaces
		//you might only be able to set this if the default route originates from the same interface as the ip
		$this->_txIpAddr	= $ipAddr;
		$this->_txMacAddr	= preg_replace("/[^a-fA-F0-9]+/", "", strtoupper(trim($macAddr)));
		return $this;
	}
	protected function getRxSocket()
	{
		if ($this->_rxSockObj === null) {
			$sockObj = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
			socket_set_option($sockObj, SOL_SOCKET, SO_REUSEADDR, 1);
			socket_bind($sockObj, "0.0.0.0", 5000);
			socket_set_nonblock($sockObj);
			$this->_rxSockObj	= $sockObj;
		}
		return $this->_rxSockObj;
	}
	protected function socketRead($timeoutMs=10000)
	{
		$tTime	    = \MTM\Utilities\Factories::getTime()->getMicroEpoch() + ($timeoutMs / 1000);
		$sockObj	= $this->getRxSocket();
		while (true) {

			$data		= socket_read($sockObj, $this->_rxMaxBytes);
			$dLen		= strlen(trim($data));
			if ($dLen > 0) {
				
				$hexData				= bin2hex($data);
				
				$rObj					= new \stdClass();
				$heads					= new \stdClass();
				$payload				= new \stdClass();
				$rObj->headers			= $heads;
				$rObj->payload			= $payload;
				$rObj->raw				= $data;
				
				$payload->bytes			= $this->hexCountToDecimal(substr($hexData, 28, 4));
				$payload->hex			= substr($hexData, 40);
				$payload->bin			= hex2bin($payload->hex);

				$heads->srcPos			= $this->hexCountToDecimal(substr($hexData, 32, 4));
				$heads->dstPos			= $this->hexCountToDecimal(substr($hexData, 36, 4));
				$heads->srcMac			= strtoupper(substr($hexData, 0, 12));
				$heads->dstMac			= strtoupper(substr($hexData, 12, 12));
				
				return $rObj;
				
			} elseif (\MTM\Utilities\Factories::getTime()->getMicroEpoch() > $tTime) {
				throw new \Exception("Read Timeout", 13465);
			} else {
				usleep(10000);
			}
		}
	}
	protected function getTxSocket()
	{
		if ($this->_txSockObj === null) {
			$sockObj = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
			socket_set_option($sockObj, SOL_SOCKET, SO_REUSEADDR, 1);
			socket_set_option($sockObj, SOL_SOCKET, SO_BROADCAST, 1);
			
			//we need to bind on the interface that holds the default gateway
			if ($this->_txIpAddr === null) {
				throw new \Exception("You must set the server ip and mac");
				//$this->_txIpAddr	= getHostByName(getHostName()); //automate in the future?
			}
			socket_bind($sockObj, $this->_txIpAddr, 5000);
			socket_set_nonblock($sockObj);
			$this->_txSockObj	= $sockObj;
		}
		return $this->_txSockObj;
	}
	protected function socketWrite($data)
	{
		socket_sendto($this->getTxSocket(), $data, strlen($data), 0, "255.255.255.255", 5000);
		return $this;
	}
	protected function decCountToHex($dec)
	{
		$oHex	= dechex($dec);
		$oHex	= str_repeat("0", 4 - strlen($oHex)) . $oHex;
		$hPs	= str_split($oHex, 2);
		$oHex	= $hPs[1] . $hPs[0];
		return $oHex;
	}
	protected function hexCountToDecimal($hex)
	{
		$dPs	= str_split($hex, 2);
		return hexdec($dPs[1] . $dPs[0]);
	}
	protected function arrayToHex($array)
	{
		$hexStr	= "";
		foreach ($array as $item) {
			
			//if you send numbers as strings they will be handled as such
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