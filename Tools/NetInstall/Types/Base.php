<?php
//© 2019 Martin Peter Madsen
namespace MTM\Mikrotik\Tools\NetInstall\Types;

class Base
{
	private $_srcPort=null;
	private $_dstPort=null;
	
	private $_discoveryPort=5000;
	
	private $_fwFile=null;
	private $_resetFile=null;
	
	//1024 is a safe value that avoids fracmentation over e.g. VPN
	//since the protocol is UDP, the name "MSS" is not a great description
	//however it explains the variable purpose well
	private $_transferMSS=1024;
	
	
	//mac addresses
	private $_srcMac=null;
	private $_dstMac=null;
	
	//ips
	private $_srcIp=null;
	private $_dstIp=null;
	
	//sockets
	private $_recvSocket=null;
	private $_sendSocket=null;
	
	//tracking
	private $_isInstall=false;
	private $_isRaw=false;
	
	//current position
	
	private $_curPos1=null;
	private $_curPos2=null;
	private $_curFilePos=null;
	
	//target position
	private $_tarPos1=null;
	private $_tarPos2=null;
	private $_tarFilePos=null;
	
	private $_retryCount=0;
	private $_maxRetries=2;
	private $_defaultTimeout=2000;
	
	private $_debugData=array();
	private $_debug=false;
	
	public function __destruct()
	{
		$this->terminateSockets();
	}
	public function setConfig($flashPort=null, $flashMac=null, $fwObj=null, $resetObj=null)
	{
		//cleanup, new config coming in
		$this->terminateSockets();
		$this->setIsInstall(false);
		$this->setIsRaw(false);
		$this->setRetryCount(0);
		
		$this->setFirmwareFile(null);
		$this->setResetFile(null);
		
		$this->setSrcPort(null);
		$this->setSrcMac(null);
		$this->setSrcIp(null);
		
		$this->setDstPort(null);
		$this->setDstMac(null);
		$this->setDstIp(null);
		
		$this->setPosition1(null);
		$this->setPosition2(null);
		$this->setFilePosition(null);
		
		//set config
		if ($flashPort !== null) {
			$this->setSrcPort($flashPort);
		}
		if ($flashMac !== null) {
			$this->setDstMac($flashMac);
		}
		if ($fwObj !== null) {
			$this->setFirmwareFile($fwObj);
		}
		if ($resetObj !== null) {
			$this->setResetFile($resetObj);
		}
	}
	public function setDebug($bool)
	{
		$this->_debug   = $bool;
	}
	public function getDebug()
	{
		return $this->_debug;
	}
	public function addDebugData($data)
	{
		if ($this->getDebug() === true) {
			$this->_debugData[]	= $data;
		}
	}
	public function getDebugData()
	{
		return $this->_debugData;
	}
	public function getRawData($maxTimeMs=10000)
	{
		//if we need to grab packets from connections that have been orphaned
		$recvObj    = null;
		$this->setIsRaw(true);
		try {
			
			$recvObj    = $this->socketReceive($maxTimeMs, __FUNCTION__);
			
		} catch (\Exception $e) {
			//timeout, no data received in the alloted time
		}
		
		$this->setIsRaw(false);
		return $recvObj;
		
	}
	public function getDevices($maxTimeMs=10000)
	{
		//get any device that is broadcasting on the port we are listning on
		//function will run until a device is seen twice, indicating we have received
		//broadcasts from all devices at least once, or the timeout is exceeded
		
		$srcPort    = $this->getSrcPort();
		$dstPort    = $this->getDstPort();
		$errObj     = null;
		
		try {
			
			$this->setIsInstall(false);
			$this->terminateSockets();
			$this->setSrcPort($this->getDiscoveryPort());
			$this->setDstPort($this->getDiscoveryPort());
			
			$sTime  = \MTM\Utilities\Factories::getTime()->getMicroEpoch();
			$tTime  = $sTime + intval($maxTimeMs / 1000);
			
			$done   = false;
			$rObjs  = array();
			while ($done === false) {
				
				//remaning time
				$cTime  = \MTM\Utilities\Factories::getTime()->getMicroEpoch();
				$rTime  = intval(($tTime - $cTime) * 1000);
				
				if ($rTime > 0) {
					
					try {
						
						$recvObj    = null;
						$recvObj	= $this->socketReceive($rTime, __FUNCTION__);
						
					} catch (\Exception $e) {
						//timeout, no data received in the alloted time
						$done   = true;
					}
					
					if ($done === false) {
						
						$lines	= explode("\n", hex2bin($recvObj->pLoad));
						if (count($lines) == 7) {
							
							$index  = $recvObj->srcMac->get();
							if (isset($rObjs[$index]) === false) {
								$rObj               = new \stdClass();
								$rObj->mac          = $recvObj->srcMac;
								$rObj->port	        = intval($recvObj->srcPort);
								$rObj->licenseId	= trim($lines[1]);
								$rObj->licenseKey	= trim($lines[2]);
								
								$rObj->type		    = trim($lines[3]);
								$rObj->arch		    = trim($lines[4]);
								//minimum version required for install
								$rObj->version		= trim($lines[5]);
								
								$rObjs[$index]      = $rObj;
								
							} else {
								//already encountered that device once before, we are done
								$done = true;
							}
							
						} else {
							//we got another type of message, carry on
						}
					}
					
				} else {
					//time is up
					$done   = true;
				}
			}
			
		} catch (\Exception $e) {
			$errObj = $e;
		}
		
		$this->terminateSockets();
		$this->setSrcPort($srcPort);
		$this->setDstPort($dstPort);
		
		if ($errObj === null) {
			return array_values($rObjs);
		} else {
			throw $errObj;
		}
	}
	public function getDeviceByMac($mac, $maxTimeMs=10000)
	{
		if ($mac instanceof \MHT\Data\Networking\IEEE802\MacAddress === false) {
			$macObj  = \MHT\Factories::getNetworking()->getMacAddress($mac);
		} else {
			$macObj  = $mac;
		}
		
		$tDevObj    = null;
		$devObjs    = $this->getDevices($maxTimeMs);
		foreach ($devObjs as $devObj) {
			if ($macObj->get() == $devObj->mac->get()) {
				//found the target
				$tDevObj   = $devObj;
				break;
			}
		}
		
		return $tDevObj;
	}
	public function getSrcPort()
	{
		return $this->_srcPort;
	}
	protected function setSrcPort($port)
	{
		if (is_int($port) === true) {
			$this->_srcPort = $port;
		} elseif ($port === null) {
			$this->_srcPort  = null;
		} else {
			throw new \MHT\MException(__METHOD__ . ">> Invalid Port");
		}
		return $this;
	}
	public function getDiscoveryPort()
	{
		return $this->_discoveryPort;
	}
	protected function setDiscoveryPort($port)
	{
		if (is_int($port) === true) {
			$this->_discoveryPort = $port;
		} elseif ($port === null) {
			$this->_discoveryPort  = null;
		} else {
			throw new \MHT\MException(__METHOD__ . ">> Invalid Port");
		}
		return $this;
	}
	public function getIsInstall()
	{
		return $this->_isInstall;
	}
	protected function setIsInstall($bool)
	{
		$this->_isInstall = $bool;
		return $this;
	}
	public function getIsRaw()
	{
		return $this->_isRaw;
	}
	protected function setIsRaw($bool)
	{
		$this->_isRaw    = $bool;
		return $this;
	}
	public function getDstPort()
	{
		return $this->_dstPort;
	}
	public function setDstPort($port)
	{
		if (is_int($port) === true) {
			$this->_dstPort = $port;
		} elseif ($port === null) {
			$this->_dstPort  = null;
		} else {
			throw new \MHT\MException(__METHOD__ . ">> Invalid Port");
		}
		return $this;
	}
	public function getDstMac()
	{
		return $this->_dstMac;
	}
	protected function setDstMac($mac)
	{
		if ($mac instanceof \MHT\Data\Networking\IEEE802\MacAddress === true) {
			$this->_dstMac  = $mac;
		} elseif ($mac === null) {
			$this->_dstMac  = null;
		} else {
			$this->_dstMac  = \MHT\Factories::getNetworking()->getMacAddress($mac);
		}
		return $this;
	}
	public function getFirmwareFile()
	{
		return $this->_fwFile;
	}
	protected function setFirmwareFile($fileObj)
	{
		$this->_fwFile   = $fileObj;
		return $this;
	}
	public function getResetFile()
	{
		return $this->_resetFile;
	}
	protected function setResetFile($fileObj)
	{
		$this->_resetFile	= $fileObj;
		return $this;
	}
	public function getMSS()
	{
		return $this->_transferMSS;
	}
	public function setMSS($int)
	{
		//allow user to set this
		if (is_int($int) === true) {
			$this->_transferMSS  = $int;
		} elseif ($int === null) {
			$this->_transferMSS  = null;
		} else {
			throw new \MHT\MException(__METHOD__ . ">> Invalid MSS value");
		}
		return $this;
	}
	public function getSrcMac()
	{
		if ($this->_srcMac === null) {
			//if a mac has not been specified then use the
			//mac of the interface that holds the default route
			$shellObj		= \MHIT\Factories::getDevices()->getShell("shared");
			$toolObj		= \MHIT\Factories::getTools()->getNetwork()->getIp();
			$ifObjs			= $toolObj->getInterfaces($shellObj);
			$routeObjs		= $toolObj->getIPv4Routes($shellObj);
			foreach ($routeObjs as $routeObj) {
				if ($routeObj->getSubnet()->getCidr() === 0) {
					$gwObj	= $routeObj->getGateways(true);
					foreach ($ifObjs as $ifObj) {
						if ($gwObj->getInterface()->getName() == $ifObj->getName()) {
							$this->setSrcMac($ifObj->getMac());
							break;
						}
					}
				}
			}
			
			if ($this->_srcMac === null) {
				throw new \MHT\MException(__METHOD__ . ">> Failed to get source mac");
			}
		}
		return $this->_srcMac;
	}
	protected function setSrcMac($mac)
	{
		if ($mac instanceof \MHT\Data\Networking\IEEE802\MacAddress === true) {
			$this->_srcMac  = $mac;
		} elseif ($mac === null) {
			$this->_srcMac  = null;
		} else {
			$this->_srcMac  = \MHT\Factories::getNetworking()->getMacAddress($mac);
		}
		return $this;
	}
	public function getSrcIp()
	{
		if ($this->_srcIp === null) {
			//by default we need to be listening for broadcast traffic
			$ipObj	= \MHT\Factories::getNetworking()->getIPv4Address("0.0.0.0");
			$this->setSrcIp($ipObj);
		}
		return $this->_srcIp;
	}
	protected function setSrcIp($ip)
	{
		if ($ip instanceof \MHT\Data\Networking\IP\V4Address === true) {
			$this->_srcIp	= $ip;
		} elseif ($ip === null) {
			$this->_srcIp	= null;
		} else {
			$this->_srcIp	= \MHT\Factories::getNetworking()->getIPv4Address($ip);
		}
		return $this;
	}
	public function getDstIp()
	{
		if ($this->_dstIp === null) {
			//by default we need to be sending broadcast traffic
			$ipObj	= \MHT\Factories::getNetworking()->getIPv4Address("255.255.255.255");
			$this->setDstIp($ipObj);
		}
		return $this->_dstIp;
	}
	protected function setDstIp($ip)
	{
		if ($ip instanceof \MHT\Data\Networking\IP\V4Address === true) {
			$this->_dstIp	= $ip;
		} elseif ($ip === null) {
			$this->_dstIp	= null;
		} else {
			$this->_dstIp	= \MHT\Factories::getNetworking()->getIPv4Address($ip);
		}
		return $this;
	}
	public function getPosition1()
	{
		return $this->_curPos1;
	}
	protected function setPosition1($int)
	{
		if (is_int($int) === true) {
			$this->_curPos1 = $int;
		} elseif ($int === null) {
			$this->_curPos1  = null;
		} else {
			throw new \MHT\MException(__METHOD__ . ">> Invalid Position 1");
		}
		return $this;
	}
	public function getPosition2()
	{
		return $this->_curPos2;
	}
	protected function setPosition2($int)
	{
		if (is_int($int) === true) {
			$this->_curPos2 = $int;
		} elseif ($int === null) {
			$this->_curPos2  = null;
		} else {
			throw new \MHT\MException(__METHOD__ . ">> Invalid Position 2");
		}
		return $this;
	}
	public function getFilePosition()
	{
		return $this->_curFilePos;
	}
	protected function setFilePosition($int)
	{
		if (is_int($int) === true) {
			$this->_curFilePos = $int;
		} elseif ($int === null) {
			$this->_curFilePos  = null;
		} else {
			throw new \MHT\MException(__METHOD__ . ">> Invalid File Position");
		}
		return $this;
	}
	public function getTarget1()
	{
		return $this->_tarPos1;
	}
	protected function setTarget1($int)
	{
		if (is_int($int) === true) {
			$this->_tarPos1 = $int;
		} elseif ($int === null) {
			$this->_tarPos1  = null;
		} else {
			throw new \MHT\MException(__METHOD__ . ">> Invalid Target Position 1");
		}
		return $this;
	}
	public function getTarget2()
	{
		return $this->_tarPos2;
	}
	protected function setTarget2($int)
	{
		if (is_int($int) === true) {
			$this->_tarPos2 = $int;
		} elseif ($int === null) {
			$this->_tarPos2  = null;
		} else {
			throw new \MHT\MException(__METHOD__ . ">> Invalid Target Position 2");
		}
		return $this;
	}
	public function getFileTarget()
	{
		return $this->_tarFilePos;
	}
	protected function setFileTarget($int)
	{
		if (is_int($int) === true) {
			$this->_tarFilePos = $int;
		} elseif ($int === null) {
			$this->_tarFilePos  = null;
		} else {
			throw new \MHT\MException(__METHOD__ . ">> Invalid File Target Position");
		}
		return $this;
	}
	public function getRetryCount()
	{
		return $this->_retryCount;
	}
	protected function setRetryCount($int)
	{
		if (is_int($int) === true) {
			$this->_retryCount  = $int;
		} else {
			throw new \MHT\MException(__METHOD__ . ">> Invalid Retry count value");
		}
		return $this;
	}
	public function getMaxRetries()
	{
		return $this->_maxRetries;
	}
	public function setMaxRetries($int)
	{
		//let user set this value
		if (is_int($int) === true) {
			$this->_maxRetries = $int;
		} else {
			throw new \MHT\MException(__METHOD__ . ">> Invalid Max Retry count value");
		}
		return $this;
	}
	public function getDefaultTimeout()
	{
		return $this->_defaultTimeout;
	}
	public function setDefaultTimeout($int)
	{
		//let user set this value
		if (is_int($int) === true) {
			$this->_defaultTimeout = $int;
		} else {
			throw new \MHT\MException(__METHOD__ . ">> Invalid Default Timeout value");
		}
		return $this;
	}
	protected function terminateSockets()
	{
		if (is_resource($this->_sendSocket) === true) {
			socket_close($this->_sendSocket);
		}
		$this->_sendSocket		= null;
		
		if (is_resource($this->_recvSocket) === true) {
			socket_close($this->_recvSocket);
		}
		$this->_recvSocket		= null;
	}
	protected function getRecvSocket()
	{
		if ($this->_recvSocket === null) {
			
			if ($this->getSrcPort() === null) {
				throw new \MHT\MException(__METHOD__ . ">> Missing required source port");
			} elseif ($this->getSrcIp() === null) {
				throw new \MHT\MException(__METHOD__ . ">> Missing required source ip");
			}
			
			$socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
			socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
			socket_bind($socket, $this->getSrcIp()->getDecimal(), $this->getSrcPort());
			
			socket_set_nonblock($socket);
			
			$this->_recvSocket	= $socket;
		}
		return $this->_recvSocket;
	}
	protected function socketReceive($timeoutMs=null, $funcName=null)
	{
		if ($timeoutMs === null) {
			$timeoutMs  = $this->getDefaultTimeout();
		}
		
		$sTime	    = \MTM\Utilities\Factories::getTime()->getMicroEpoch();
		$tTime	    = $sTime + ($timeoutMs / 1000);
		$lrObj      = null;
		$recvObj    = null;
		
		//receive data
		while (true) {
			
			$cTime		= \MTM\Utilities\Factories::getTime()->getMicroEpoch();
			$rData		= socket_read($this->getRecvSocket(), $this->getMSS());
			$pLoadLen	= strlen(trim($rData));
			if ($pLoadLen > 0) {
				
				//deconstruct the binary message
				$hexData	        = bin2hex($rData);
				
				$rObj				= new \stdClass();
				$rObj->direction	= "received";
				$rObj->exitCause	= null;
				$rObj->timeout      = $timeoutMs;
				$rObj->exeTime      = null;
				$rObj->function     = $funcName;
				
				//did we hit the target
				$rObj->onTarget		= false;
				
				//payload size
				$rObj->pSize		= $this->getCounterDec(substr($hexData, 28, 4));
				
				//counters
				$rObj->count1		= $this->getCounterDec(substr($hexData, 32, 4));
				$rObj->count2		= $this->getCounterDec(substr($hexData, 36, 4));
				$rObj->target1		= $this->getTarget1();
				$rObj->target2		= $this->getTarget2();
				
				//ports
				$rObj->srcPort		= 5000;
				$rObj->dstPort		= $this->getSrcPort();
				
				//macs
				$rObj->srcMac		= \MHT\Factories::getNetworking()->getMacAddress(substr($hexData, 0, 12));
				$rObj->dstMac		= \MHT\Factories::getNetworking()->getMacAddress(substr($hexData, 12, 12));
				
				//ips
				$rObj->srcIp		= \MHT\Factories::getNetworking()->getIPv4Address("0.0.0.0");
				$rObj->dstIp		= \MHT\Factories::getNetworking()->getIPv4Address("255.255.255.255");
				
				//we do not know the purpose of these next 4 bytes
				$rObj->unknown		= substr($hexData, 24, 4);
				
				//payload
				$rObj->pLoad		= substr($hexData, 40);
				
				if ($this->getIsRaw() === true) {
					$rObj->exitCause	= "raw";
					$recvObj            = $rObj;
				} elseif ($this->getIsInstall() === false) {
					//just looking for broadcasters pre install
					//targets havebeen set by the function to 1 and 0
					if ($rObj->count1 == 1 && $rObj->count2 == 0) {
						$rObj->exitCause	= "Pre Install";
						$rObj->onTarget		= true;
						$recvObj            = $rObj;
					}
					
				} else {
					
					if ($rObj->srcMac->get() == $this->getDstMac()->get() && $rObj->dstMac->get() == $this->getSrcMac()->get()) {
						//this packet is was destined for us
						if ($lrObj === null || $lrObj->count2 <= $rObj->count2) {
							//initial or newer
							$lrObj  = $rObj;
						}
						
						if ($rObj->count1 == $this->getTarget1() && $rObj->count2 == $this->getTarget2()) {
							//found our target
							$rObj->exitCause	= "Target match";
							$rObj->onTarget		= true;
							$recvObj            = $rObj;
						}
					}
				}
			}
			
			//timeout is seperate, from he above logic
			if ($tTime < $cTime) {
				if ($lrObj !== null) {
					//no more data pending, return last valid result we got
					$lrObj->exitCause	= "End of Data, Best match";
					$recvObj            = $lrObj;
				} else {
					throw new \MHT\MException(__METHOD__ . ">> Received no valid data, timeout");
				}
			}
			if ($recvObj !== null) {
				$eTime          = \MTM\Utilities\Factories::getTime()->getMicroEpoch();
				$rObj->exeTime  = intval(($eTime - $sTime) * 1000);
				
				//$this->addDebugData($recvObj);
				
				return $recvObj;
				
			} else {
				usleep(10000);
			}
		}
	}
	protected function getSendSocket()
	{
		if ($this->_sendSocket === null) {
			
			if ($this->getSrcPort() === null) {
				throw new \MHT\MException(__METHOD__ . ">> Missing required source port");
			} elseif ($this->getSrcIp() === null) {
				throw new \MHT\MException(__METHOD__ . ">> Missing required source ip");
			}
			
			//opening a seperate socket for sending, need to reuse
			$this->getRecvSocket();
			$socket	= socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
			socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
			socket_set_option($socket, SOL_SOCKET, SO_BROADCAST, 1);
			socket_bind($socket, $this->getSrcIp()->getDecimal(), $this->getSrcPort());
			
			socket_set_nonblock($socket);
			
			$this->_sendSocket	= $socket;
		}
		
		return $this->_sendSocket;
	}
	protected function socketSend($hexData, $funcName=null)
	{
		$rObj				= new \stdClass();
		$rObj->direction	= "transmitting";
		$rObj->function     = $funcName;
		
		//payload size
		$rObj->pSize		= strlen(hex2bin($hexData));
		$rObj->count1		= $this->getPosition1();
		$rObj->count2		= $this->getPosition2();
		$rObj->target1		= $this->getTarget1();
		$rObj->target2		= $this->getTarget2();
		
		$rObj->srcMac		= $this->getSrcMac();
		$rObj->srcIp		= $this->getSrcIp();
		$rObj->srcPort		= $this->getSrcPort();
		
		$rObj->dstMac		= $this->getDstMac();
		$rObj->dstIp		= $this->getDstIp();
		$rObj->dstPort		= $this->getDstPort();
		
		//we do not know the purpose of these next 4 bytes
		$rObj->unknown		= "0000";
		
		//payload
		$rObj->pLoad		= $hexData;
		
		//craft transmission
		$wData		= strtolower($rObj->srcMac->get()) . strtolower($rObj->dstMac->get());
		
		//these 4 octets dont seem to be in use
		$wData		.= $rObj->unknown;
		
		//set the payload size
		$wData		.= $this->getCounterHex($rObj->pSize);
		
		//set the tx position
		$wData		.= $this->getCounterHex($rObj->count1) . $this->getCounterHex($rObj->count2);
		
		//now add the real payload
		$wData		.= $rObj->pLoad;
		
		//turn hex into binary
		$binData	= hex2bin($wData);
		
		//send
		socket_sendto($this->getSendSocket(), $binData, strlen($binData), 0, $rObj->dstIp->getDecimal(), $rObj->dstPort);
		
		//$this->addDebugData($rObj);
		
		return $rObj;
	}
	
	public function flashDevice()
	{
		try {
			
			if ($this->getFirmwareFile() === null) {
				throw new \MHT\MException(__METHOD__ . ">> Firmware File Required");
			} elseif ($this->getDstMac() === null) {
				throw new \MHT\MException(__METHOD__ . ">> Destination Mac Address Required");
			}
			
			$tDevObj    = $this->getDeviceByMac($this->getDstMac());
			if ($tDevObj !== null) {
				if ($this->getDstPort() === null) {
					$this->setDstPort($tDevObj->port);
				}
			} else {
				throw new \MHT\MException(__METHOD__ . ">> No such target device");
			}
			
			//start the install
			$this->setIsInstall(true);
			$this->sendOffer();
			$this->formatDrive();
			$this->sendSpacer();
			$this->sendFirmware();
			
			if ($this->getResetFile() !== null) {
				$this->sendSpacer();
				$this->sendResetConfig();
			}
			
			$this->sendSpacer();
			$this->sendComplete();
			
		} catch (\Exception $e) {
			throw $e;
		}
	}
	protected function sendOffer()
	{
		try {
			
			//send OFFR, only function to set absolute position
			$this->setPosition1(1);
			$this->setPosition2(0);
			
			if ($this->getRetryCount() == 0) {
				$this->setTarget1($this->getPosition1());
				$this->setTarget2(($this->getPosition2() + 1));
			}
			
			$chars				= array("O", "F", "F", "R", 10, 10);
			$wData				= $this->getArrayHex($chars);
			$this->socketSend($wData, __FUNCTION__);
			
			//wait for return
			$recvObj	= $this->socketReceive(null, __FUNCTION__);
			if ($recvObj->onTarget === true) {
				
				$chars		= array("Y", "A", "C", "K", 10);
				$actExp		= $this->getArrayHex($chars);
				if ($recvObj->pLoad != $actExp) {
					//the return is not as expected
					throw new \MHT\MException(__METHOD__ . ">> Invalid ACK Result");
				} else {
					//done
					$this->setRetryCount(0);
					$this->setPosition1(($this->getTarget1() + 1));
					$this->setPosition2($this->getTarget2());
					return;
				}
				
			} else {
				throw new \MHT\MException(__METHOD__ . ">> Failed to hit target");
			}
			
		} catch(\Exception $e) {
			
			$this->setRetryCount(($this->getRetryCount() + 1));
			$this->addDebugData(__FUNCTION__ . " - " . $e->getMessage());
			
			if ($this->getRetryCount() < $this->getMaxRetries()) {
				$this->sendOffer();
			} else {
				throw $e;
			}
		}
	}
	protected function formatDrive()
	{
		try {
			
			//tell the routerboard to wipe its flash (i think that is what this position does)
			//have observed this takes at least 8 sec on RB750Gr3
			if ($this->getRetryCount() == 0) {
				$this->setTarget1($this->getPosition1());
				$this->setTarget2(($this->getPosition2() + 1));
			}
			
			$wData	= "";
			$this->socketSend($wData, __FUNCTION__);
			
			//wait for return, this takes longer since the disk has to format
			$timeoutMs  = $this->getDefaultTimeout();
			if ($timeoutMs < 30000) {
				$timeoutMs  = 30000;
			}
			$recvObj	= $this->socketReceive($timeoutMs, __FUNCTION__);
			if ($recvObj->onTarget === true) {
				
				$chars		= array("S", "T", "R", "T");
				$actExp		= $this->getArrayHex($chars);
				if ($recvObj->pLoad != $actExp) {
					//the return is not as expected
					throw new \MHT\MException(__METHOD__ . ">> Invalid STRT Result");
				} else {
					//done
					$this->setRetryCount(0);
					$this->setPosition1(($this->getTarget1() + 1));
					$this->setPosition2($this->getTarget2());
					return;
				}
				
			} else {
				throw new \MHT\MException(__METHOD__ . ">> Failed to hit target");
			}
			
		} catch(\Exception $e) {
			
			$this->setRetryCount(($this->getRetryCount() + 1));
			$this->addDebugData(__FUNCTION__ . " - " . $e->getMessage());
			
			if ($this->getRetryCount() < $this->getMaxRetries()) {
				$this->formatDrive();
			} else {
				throw $e;
			}
		}
	}
	protected function sendSpacer()
	{
		try {
			
			if ($this->getRetryCount() == 0) {
				$this->setTarget1($this->getPosition1());
				$this->setTarget2(($this->getPosition2() + 1));
			}
			
			$wData	= "";
			$this->socketSend($wData, __FUNCTION__);
			
			//wait for standard return, will throw on error
			$this->getStdReturn();
			
			//done
			$this->setRetryCount(0);
			$this->setPosition1(($this->getTarget1() + 1));
			$this->setPosition2($this->getTarget2());
			return;
			
		} catch(\Exception $e) {
			
			$this->setRetryCount(($this->getRetryCount() + 1));
			$this->addDebugData(__FUNCTION__ . " - " . $e->getMessage());
			
			if ($this->getRetryCount() < $this->getMaxRetries()) {
				$this->sendSpacer();
			} else {
				throw $e;
			}
		}
	}
	protected function getStdReturn($throw=true, $maxWait=null)
	{
		try {
			//this return is standard for many transmit functions
			$recvObj	= $this->socketReceive($maxWait, __FUNCTION__);
			if ($recvObj->onTarget === true) {
				
				$chars		= array("R", "E", "T", "R");
				$actExp		= $this->getArrayHex($chars);
				if ($recvObj->pLoad != $actExp) {
					//the return is not as expected
					if ($throw === true) {
						throw new \MHT\MException(__METHOD__ . ">> Invalid RETR Result");
					} else {
						return $recvObj;
					}
					
				} else {
					//done, updateing positions is the responsebillity of the calling function
					return;
				}
				
			} else {
				if ($throw === true) {
					throw new \MHT\MException(__METHOD__ . ">> Failed to hit target");
				} else {
					return $recvObj;
				}
			}
			
		} catch(\Exception $e) {
			//std return always throws uncaught, retries are handled by the calling function
			throw $e;
		}
	}
	protected function sendFirmware()
	{
		
		//send the npk file header and size
		$npkFile	= $this->getFirmwareFile();
		
		try {
			
			if ($this->getRetryCount() == 0) {
				$this->setTarget1($this->getPosition1());
				$this->setTarget2(($this->getPosition2() + 1));
			}
			
			$chars		= array("F", "I", "L", "E", 10);
			$fnChars	= str_split($npkFile->getName(), 1);
			$chars		= array_merge($chars, $fnChars);
			$chars[]	= 10;
			$bcChars	= str_split($npkFile->getByteCount(), 1);
			$chars		= array_merge($chars, $bcChars);
			$chars[]	= 10;
			$wData		= $this->getArrayHex($chars);
			$this->socketSend($wData, __FUNCTION__);
			
			//wait for standard return, will throw on error
			$this->getStdReturn();
			
			//header sent successfully
			$this->setRetryCount(0);
			$this->setPosition1(($this->getTarget1() + 1));
			$this->setPosition2($this->getTarget2());
			
		} catch(\Exception $e) {
			
			$this->setRetryCount(($this->getRetryCount() + 1));
			$this->addDebugData(__FUNCTION__ . " - " . $e->getMessage());
			
			if ($this->getRetryCount() < $this->getMaxRetries()) {
				$this->sendFirmware();
			} else {
				throw $e;
			}
		}
		
		//send the file itself, mush be outside catch as it will have its own retry logic
		$this->sendFile($npkFile);
	}
	protected function sendFile($fileObj)
	{
		try {
			
			//no retries on this function, if we dont make it there is no recovering
			//it takes too long to retrieve the response and re-try from the failed position
			//i have tried many different ways. So if doing flashing with high latency wrap
			//the UDP connection in a TCP based VPN to ensure delivery
			
			//send a file to the device
			$fSize		= $fileObj->getByteCount();
			$this->setFilePosition(1);
			
			//amount of packets to send before checking for return
			$burstSize          = ceil($fSize / $this->getMSS());
			
			//delay between packets in micro sec
			$sendSpace          = 7500;
			
			$sentCount          = 0;
			$done               = false;
			while ($done === false) {
				$sentCount++;
				
				//update target
				$this->setTarget1($this->getPosition1());
				$this->setTarget2(($this->getPosition2() + 1));
				
				$bytes		    = $fileObj->getBytes($this->getMSS(), $this->getFilePosition());
				$wData		    = bin2hex($bytes);
				$this->socketSend($wData, __FUNCTION__);
				
				//update file position
				$this->setFilePosition(($this->getFilePosition() + $this->getMSS()));
				
				//update send position
				$this->setPosition1(($this->getTarget1() + 1));
				$this->setPosition2($this->getTarget2());
				
				if ($sentCount == $burstSize) {
					
					//wait for standard return
					$recvObj    = $this->getStdReturn(false);
					if (is_object($recvObj) === true) {
						throw new \MHT\MException(__METHOD__ . ">> File: " . $fileObj->getName() . ". Delivery failed at position: " . $recvObj->count1 . "-" . $recvObj->count2);
					} else {
						
						//all is well
						$this->addDebugData("Success. Completed Sending file: " . $fileObj->getName() . ", Burst size: " . $sentCount . ", Spacing: " . $sendSpace);
						$done = true;
					}
					
				} else {
					//send the next packet
					usleep($sendSpace);
				}
			}
			
		} catch(\Exception $e) {
			//any error thrown here cannot be recovered from
			throw $e;
		}
	}
	protected function sendResetConfig()
	{
		//send the rsc file header and size
		$rscFile	= $this->getResetFile();
		
		try {
			
			if ($this->getRetryCount() == 0) {
				$this->setTarget1($this->getPosition1());
				$this->setTarget2(($this->getPosition2() + 1));
			}
			
			$chars		= array("F", "I", "L", "E", 10);
			$fnChars	= str_split("autorun.scr", 1);
			$chars		= array_merge($chars, $fnChars);
			$chars[]	= 10;
			$bcChars	= str_split($rscFile->getByteCount(), 1);
			$chars		= array_merge($chars, $bcChars);
			$chars[]	= 10;
			$wData		= $this->getArrayHex($chars);
			$this->socketSend($wData, __FUNCTION__);
			
			//wait for standard return, will throw on error
			$this->getStdReturn();
			
			//header sent successfully
			$this->setRetryCount(0);
			$this->setPosition1(($this->getTarget1() + 1));
			$this->setPosition2($this->getTarget2());
			
		} catch(\Exception $e) {
			
			$this->setRetryCount(($this->getRetryCount() + 1));
			$this->addDebugData(__FUNCTION__ . " - " . $e->getMessage());
			
			if ($this->getRetryCount() < $this->getMaxRetries()) {
				$this->sendResetConfig();
			} else {
				throw $e;
			}
		}
		
		//send the file itself, mush be outside catch as it will have its own retry logic
		$this->sendFile($rscFile);
	}
	protected function sendComplete()
	{
		try {
			
			if ($this->getRetryCount() == 0) {
				$this->setTarget1($this->getPosition1());
				$this->setTarget2(($this->getPosition2() + 1));
			}
			
			//tell the board we are done
			$chars		= array("F", "I", "L", "E", 10);
			$wData		= $this->getArrayHex($chars);
			$this->socketSend($wData, __FUNCTION__);
			
			//wait for standard return, will throw on error
			$this->getStdWtrm();
			
			//header sent successfully
			$this->setRetryCount(0);
			$this->setPosition1(($this->getTarget1() + 1));
			$this->setPosition2($this->getTarget2());
			
		} catch(\Exception $e) {
			
			$this->setRetryCount(($this->getRetryCount() + 1));
			$this->addDebugData(__FUNCTION__ . " - " . $e->getMessage());
			
			if ($this->getRetryCount() < $this->getMaxRetries()) {
				$this->sendComplete();
			} else {
				throw $e;
			}
		}
	}
	
	protected function getStdWtrm($throw=true)
	{
		try {
			
			$recvObj	= $this->socketReceive(null, __FUNCTION__);
			if ($recvObj->onTarget === true) {
				
				$chars		= array("W", "T", "R", "M");
				$actExp		= $this->getArrayHex($chars);
				if ($recvObj->pLoad != $actExp) {
					//the return is not as expected
					if ($throw === true) {
						throw new \MHT\MException(__METHOD__ . ">> Invalid WTRM Result");
					} else {
						return $recvObj;
					}
					
				} else {
					//done, updateing positions is the responsebillity of the calling function
					return;
				}
				
			} else {
				if ($throw === true) {
					throw new \MHT\MException(__METHOD__ . ">> Failed to hit target");
				} else {
					return $recvObj;
				}
			}
			
		} catch(\Exception $e) {
			//std return always throws uncaught, retries are handled by the calling function
			throw $e;
		}
	}
	private function getCounterHex($dec)
	{
		$oHex	= dechex($dec);
		$oHex	= str_repeat("0", 4 - strlen($oHex)) . $oHex;
		$hPs	= str_split($oHex, 2);
		$oHex	= $hPs[1] . $hPs[0];
		
		return $oHex;
	}
	private function getCounterDec($hex)
	{
		$dPs	= str_split($hex, 2);
		$oDec	= $dPs[1] . $dPs[0];
		$oDec	= hexdec($oDec);
		
		return $oDec;
	}
	private function getArrayHex($array)
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