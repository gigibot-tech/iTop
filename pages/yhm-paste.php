<?php

require_once '../approot.inc.php';
require_once APPROOT.'bootstrap.inc.php';
require_once APPROOT.'application/startup.inc.php';
require_once APPROOT.'application/loginwebpage.class.inc.php';
$sPaste = APPROOT.'env-production/yhm-request-context/paste.inc.php';
if (!is_file($sPaste)) {
	$sPaste = APPROOT.'extensions/yhm-request-context/paste.inc.php';
}
require_once $sPaste;

LoginWebPage::DoLogin();

header('Content-Type: application/json');

try {
	$text = utils::ReadParam('text', '', false, 'raw_data');
	$hint = utils::ReadParam('class', 'UserRequest', false, 'raw_data');
	$model = utils::ReadParam('model', 'qwen3.5:2b', false, 'raw_data');
	$result = YhmPasteCreate($text, $hint, $model);
	$root = utils::GetAbsoluteUrlAppRoot();
	foreach ($result['tickets'] as &$ticket) {
		$ticket['url'] = $root.'pages/UI.php?operation=modify&class='.urlencode($ticket['class']).'&id='.$ticket['id'];
	}
	unset($ticket);
	echo json_encode($result);
} catch (Exception $e) {
	http_response_code(400);
	echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
