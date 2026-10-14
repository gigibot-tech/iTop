<?php

/**
 * Read each YourHealthMatch service check and write the result onto the matching iTop service.
 * Usage: php write-checks.php
 * Optional: YHM_URL=http://127.0.0.1:5174
 */

$base = rtrim(getenv('YHM_URL') ?: 'http://127.0.0.1:5174', '/');

require_once __DIR__.'/approot.inc.php';
require_once APPROOT.'bootstrap.inc.php';
require_once APPROOT.'application/startup.inc.php';

function readJson(string $url): ?array
{
	$raw = @file_get_contents($url);
	if ($raw === false) {
		return null;
	}
	$decoded = json_decode($raw, true);
	return is_array($decoded) ? $decoded : null;
}

$list = readJson($base.'/checks');
if ($list === null || empty($list['services'])) {
	fwrite(STDERR, "YourHealthMatch did not answer at {$base}/checks\n");
	exit(1);
}

$written = 0;
foreach ($list['services'] as $row) {
	$checkUrl = $row['check'] ?? '';
	$result = $checkUrl !== '' ? readJson($checkUrl) : null;
	$serviceName = $result['service'] ?? '';
	if ($serviceName === '') {
		echo "skip {$checkUrl}\n";
		continue;
	}

	$search = new DBObjectSearch('Service');
	$search->AddCondition('name', $serviceName);
	$service = (new DBObjectSet($search))->Fetch();
	if ($service === null) {
		echo "no iTop service named {$serviceName}\n";
		continue;
	}

	$ok = !empty($result['ok']);
	$service->Set('yhm_check_url', $checkUrl);
	$service->Set('yhm_check_state', $ok ? 'ok' : 'failed');
	$service->Set('yhm_check_detail', (string) ($result['detail'] ?? ''));
	$service->Set('yhm_checked_at', date('Y-m-d H:i:s'));
	$description = (string) $service->Get('description');
	$description = preg_replace('/^Check:\\s+https?:\\S+\\s*/', '', $description) ?? $description;
	$service->Set('description', trim($description));
	$service->DBUpdate();
	$written++;
	echo ($ok ? 'passed' : 'failed')." {$serviceName}\n";
}

echo "wrote {$written}\n";
