<?php
//© 2019 Martin Peter Madsen
if (defined("MTM_MIKROTIK_BASE_PATH") === false) {
	define("MTM_MIKROTIK_BASE_PATH", __DIR__ . DIRECTORY_SEPARATOR);
	spl_autoload_register(function($className)
	{
		if (class_exists($className) === false) {
			$cPath		= array_values(array_filter(explode("\\", $className)));
			if (array_shift($cPath) == "MTM" && array_shift($cPath) == "Mikrotik") {
				$filePath	= MTM_MIKROTIK_BASE_PATH . implode(DIRECTORY_SEPARATOR, $cPath) . ".php";
				if (is_readable($filePath) === true) {
					require_once $filePath;
				}
			}
		}
	});
	function loadMtmMikrotik()
	{
		
	}
	loadMtmMikrotik();
}