<?php
//� 2023 Martin Peter Madsen
namespace MTM\Mikrotik\Tools\NetInstall\V2;

abstract class Process extends Initialize
{
	protected $_rxSock=null;
	protected $_txSock=null;
	protected $_rxMaxBytes=1024;
	
	public function write($data)
	{
		socket_sendto($this->getTxSock(), $data, strlen($data), 0, "255.255.255.255", 5000);
		return $this;
	}
	public function read($timeoutMs=10000, $throw=false)
	{
		$this->isUsign32Int($timeoutMs, true);
		$this->isBoolean($throw, true);
		$rObj				= new \stdClass();
		$rObj->srcMac		= null;
		$rObj->dstMac		= null;
		$rObj->srcPos		= null;
		$rObj->dstPos		= null;
		$rObj->size			= null;
		$rObj->data			= null;
		
		$timeFact	= \MTM\Utilities\Factories::getTime();
		$cTime  	= $timeFact->getMicroEpoch();
		$tTime		= $cTime + ($timeoutMs / 1000);
		$sockObj	= $this->getRxSock();
		while (true) {
			
			$rData		= trim(socket_read($sockObj, $this->_rxMaxBytes));
			if ($rData != "") {
				$rData	= bin2hex($rData);
				if (strlen($rData) > 39) {
					//minimum of 40 so we have all the parts of a net install frame
					
					$rObj				= new \stdClass();
					$rObj->srcMac		= strtolower(substr($rData, 0, 12));
					$rObj->dstMac		= strtolower(substr($rData, 12, 12));
					
					//$val				= substr($rData, 24, 4)
					//is always 0000, dunno the use
					
					//position. Think this is were in the process / flashing process the device is
					$rObj->srcPos		= intval(hexdec(substr($rData, 34, 2).substr($rData, 32, 2)));
					$rObj->dstPos		= intval(hexdec(substr($rData, 38, 2).substr($rData, 36, 2)));
					
					//payload size
					$rObj->size			= hexdec(substr($rData, 30, 2).substr($rData, 28, 2));
					$rObj->data			= hex2bin(substr($rData, 40));

					break;
				}
			}
			
			$cTime  	= $timeFact->getMicroEpoch();
			if ($cTime > $tTime) {
				if ($throw === true) {
					throw new \Exception("Read Timeout", 13465);
				} else {
					break;
				}
			} else {
				usleep(10000);
			}
		}
		return $rObj;
	}
	protected function getTxSock()
	{
		if ($this->_txSock === null) {
			
			if ($this->getIpAddress() === null) {
				throw new \Exception("Missing ip address");
			}
			
			$sockObj = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
			socket_set_option($sockObj, SOL_SOCKET, SO_REUSEADDR, true);
			socket_set_option($sockObj, SOL_SOCKET, SO_BROADCAST, true);
			socket_bind($sockObj, $this->getIpAddress(), 5000);
			socket_set_nonblock($sockObj);
			$this->_txSock	= $sockObj;
		}
		return $this->_txSock;
	}
	protected function getRxSock()
	{
		if ($this->_rxSock === null) {
			$sockObj = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
			socket_set_option($sockObj, SOL_SOCKET, SO_REUSEADDR, true);
			socket_bind($sockObj, "0.0.0.0", 5000);
			socket_set_nonblock($sockObj);
			$this->_rxSock	= $sockObj;
		}
		return $this->_rxSock;
	}
}