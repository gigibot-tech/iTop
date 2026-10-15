<?php

$file = '/var/www/html/conf/production/config-itop.php';
if (!is_file($file)) {
	fwrite(STDERR, "iTop config is missing\n");
	exit(1);
}

include $file;
if (!isset($MySettings) || !is_array($MySettings)) {
	fwrite(STDERR, "iTop config has no settings\n");
	exit(1);
}

$host = getenv('DB_HOST') ?: '';
$port = getenv('DB_PORT') ?: '';
$user = getenv('DB_USER') ?: '';
$password = getenv('DB_PASSWORD');
$name = getenv('DB_NAME') ?: '';

if ($host !== '') {
	$MySettings['db_host'] = ($port !== '' && $port !== '3306') ? $host.':'.$port : $host;
}
if ($user !== '') {
	$MySettings['db_user'] = $user;
}
if ($password !== false && $password !== '') {
	$MySettings['db_pwd'] = $password;
}
if ($name !== '') {
	$MySettings['db_name'] = $name;
}

$domain = getenv('RAILWAY_PUBLIC_DOMAIN') ?: '';
if ($domain !== '') {
	$MySettings['app_root_url'] = 'https://'.$domain.'/';
}
$MySettings['behind_reverse_proxy'] = true;

$moduleSettings = (isset($MyModuleSettings) && is_array($MyModuleSettings)) ? $MyModuleSettings : array();
$modules = (isset($MyModules) && is_array($MyModules)) ? $MyModules : array();

$out = "<?php\n\n";
$out .= '$MySettings = '.var_export($MySettings, true).";\n\n";
$out .= '$MyModuleSettings = '.var_export($moduleSettings, true).";\n\n";
$out .= '$MyModules = '.var_export($modules, true).";\n";
file_put_contents($file, $out);
