
I assume your device has been served vmlinux from a tftp server and is ready for net install.
one way to do this is using <a href="./Examples/ISC-DHCP/dhcpd.conf">ISC DHCP</a> with a bootp config to serve up the vmlinux image.
if you just want to test, use the regular net install for this and once the device shows as "ready" you can proceed

### Version 1:

#### ready the tool object:

```

require_once "/path/to/mtm-mikrotik/Enable.php";
$toolObj	= \MTM\Mikrotik\Facts::getTools()->getNetInstall(2);

```

#### set environment:

Set the interface name, ip and mac from the SERVER interface you wish to use

```
$toolObj	= \MTM\Mikrotik\Facts::getTools()->getNetInstall(2);

//auto populate, good if you only have one interface
$toolObj->autoPopulateInterface();

//else manual
$name	= "eth0";
$ip		= "10.155.9.46";
$mac	= "000c2917c6fb";
$toolObj->setInterface($name, $mac, $ip);

```

#### Identify the routerboard you want to NetInstall:

```

$devObjs	= $toolObj->discover();
print_r($devObjs);
$devObj		= reset($devObjs);

```

#### Flash a device

```

//ready the firmware factory
$fwFact	= \MTM\Mikrotik\Facts::getFirmwares();
$fwFact->setBasePath("/path/to/folder/with/RouterOS/npk/files");

//OPTIONAL: path to the script that should be set as the default config
$scriptPath	= "/path/to/my/script/resetToDefaults.rsc";

//get the firmware you want to flash
$fwObj		= $fwFact->getByDevice($devObj);
$devObj->flash($fwObj, $scriptPath);

```

done, unit will reboot and trigger the default config script if needed


### Version 1:


#### ready the tool object:

```

require_once "/path/to/mtm-mikrotik/Enable.php";

$toolObj	= \MTM\Mikrotik\Facts::getTools()->getNetInstall();

```

#### set environment:

Set the ip and mac from the SERVER interface you wish to use
these are the values from the server the PHP script is running on
remember the server must have L2 access to the routerboard, L2 VPN access if fine

```

$ip		= "10.155.9.46";
$mac	= "00:0c:29:17:c6:fb";
$toolObj->setTxConfig($ip, $mac);

```

#### Identify the routerboard you want to NetInstall:

if you dont know the mac, or want to validate the device is ready for netInstall use this, otherwise skip this step:

```
$devObjs	= $toolObj->getDeviceList();
print_r($devObjs);
```

#### Flash a device

```
//MANDATORY: MAC address of Ether1 on the routerboard
$macAddr		= "B969B4234D37";

//MANDATORY: path to the NPK file downloaded from MT
$fwPath		= "/path/to/npk/file/routeros-arm-6.44.6.npk";

//OPTIONAL: path to the script that should be set as the default config
$scriptPath	= "/path/to/my/script/resetToDefaults.rsc";
	
//execute the net install
$toolObj->flashByMac($macAddr, $fwPath, $scriptPath);
```

done, unit will reboot and trigger the default config script if needed

