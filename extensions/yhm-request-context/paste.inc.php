<?php

/**
 * Turn pasted text into a UserRequest or an Incident that matches the ones already in iTop.
 */

function YhmPasteServices(): array
{
	return [
		'Find a doctor',
		'Book a time',
		'Set up the clinic',
		'Join the list',
		'Hold the visit on video',
	];
}

function YhmPasteModels(): array
{
	return [
		'qwen3.5:2b' => 'Fast',
		'granite4.2:8b' => 'Stronger, slower',
	];
}

function YhmPasteRead(string $text, string $model): array
{
	$allowed = array_keys(YhmPasteModels());
	if (!in_array($model, $allowed, true)) {
		$model = 'qwen3.5:2b';
	}
	$url = rtrim(getenv('YHM_OLLAMA_URL') ?: 'http://127.0.0.1:11434', '/').'/api/chat';
	$services = implode(', ', YhmPasteServices());
	$system = <<<PROMPT
Read the message and write tickets from it.
title: a name for the ask, at most 8 words, taken from the message.
description: what the sender wants. If one job has numbered points, list those points.
assumption: the belief we would be acting on.
assumption_check: a visible failure, such as a copied site or a broken page. Do not restate the assumption.
value: what a patient or a clinic would notice.
feature: the change in YourHealthMatch.
service: exactly one of {$services}. A website or homepage change uses Find a doctor.
decision: do_it when the sender says what to build and why. already when it already exists. not_pilot when we should not build it. need_value only when the sender never says who benefits.
class: Incident only when something that already works is broken. Otherwise UserRequest.
One design email is one ticket. Separate problems are separate tickets. At most 6. Do not leave a field blank. Do not copy these instructions into a field.
PROMPT;
	$payload = json_encode([
		'model' => $model,
		'stream' => false,
		'format' => [
			'type' => 'object',
			'properties' => [
				'tickets' => [
					'type' => 'array',
					'items' => [
						'type' => 'object',
						'properties' => [
							'class' => ['type' => 'string'],
							'title' => ['type' => 'string'],
							'description' => ['type' => 'string'],
							'service' => ['type' => 'string'],
							'assumption' => ['type' => 'string'],
							'assumption_check' => ['type' => 'string'],
							'value' => ['type' => 'string'],
							'feature' => ['type' => 'string'],
							'decision' => ['type' => 'string'],
						],
						'required' => ['class', 'title', 'description', 'service', 'assumption', 'assumption_check', 'value', 'feature', 'decision'],
					],
				],
			],
			'required' => ['tickets'],
		],
		'think' => false,
		'options' => [
			'temperature' => 0,
			'num_ctx' => 8192,
			'num_predict' => 900,
		],
		'messages' => [
			['role' => 'system', 'content' => $system],
			['role' => 'user', 'content' => $text],
		],
	]);
	$context = stream_context_create([
		'http' => [
			'method' => 'POST',
			'header' => "Content-Type: application/json\r\n",
			'content' => $payload,
			'timeout' => 90,
			'ignore_errors' => true,
		],
	]);
	$raw = @file_get_contents($url, false, $context);
	if ($raw === false) {
		throw new RuntimeException('Ollama did not answer.');
	}
	$decoded = json_decode($raw, true);
	$content = $decoded['message']['content'] ?? '';
	$parsed = json_decode($content, true);
	if (!is_array($parsed)) {
		throw new RuntimeException('The model reply was cut off. Use the fast model, or paste a shorter message.');
	}
	if (isset($parsed['tickets']) && is_array($parsed['tickets'])) {
		$items = $parsed['tickets'];
	} elseif (isset($parsed['class']) || isset($parsed['title'])) {
		$items = [$parsed];
	} else {
		$items = array_values(array_filter($parsed, 'is_array'));
	}
	$items = array_slice($items, 0, 8);
	if ($items === []) {
		throw new RuntimeException('The model did not return a ticket.');
	}
	return $items;
}

function YhmPasteOneOf(string $value, array $allowed, string $fallback): string
{
	foreach ($allowed as $item) {
		if (strcasecmp($item, trim($value)) === 0) {
			return $item;
		}
	}
	return $fallback;
}

function YhmPasteHtml(string $text): string
{
	$safe = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	return '<p>'.nl2br($safe).'</p>';
}

function YhmPasteUseful(string $value): string
{
	$value = trim($value);
	if ($value === '') {
		return '';
	}
	if (preg_match('/^(one sentence|short sentence|under \d+ words|what they asked|what we treat as true|none|n\/a|null|tbd)\b/i', $value)) {
		return '';
	}
	return $value;
}

function YhmPastePoints(string $text): array
{
	$parts = preg_split('/\n(?=\s*\d+\.\s+)/', $text) ?: [];
	$points = [];
	foreach ($parts as $part) {
		if (!preg_match('/^\s*\d+\.\s*(.*)$/s', $part, $match)) {
			continue;
		}
		$body = trim(preg_replace('/\s+/', ' ', $match[1]) ?? '');
		if ($body === '') {
			continue;
		}
		if (strlen($body) > 360) {
			$body = substr($body, 0, 357).'…';
		}
		$points[] = $body;
	}
	return $points;
}

function YhmPasteOpening(string $text): string
{
	$lines = preg_split('/\n+/', $text) ?: [];
	$kept = [];
	foreach ($lines as $line) {
		$line = trim($line);
		if ($line === '' || preg_match('/^(dear |hi |hello |best regards|kind regards)/i', $line)) {
			continue;
		}
		if (preg_match('/^\d+\.\s+/', $line)) {
			break;
		}
		$kept[] = $line;
		if (strlen(implode(' ', $kept)) > 320) {
			break;
		}
	}
	return trim(implode(' ', $kept));
}

function YhmPasteCreate(string $text, string $hintClass, string $model = 'qwen3.5:2b'): array
{
	$text = trim($text);
	if ($text === '') {
		throw new InvalidArgumentException('Paste the email first.');
	}
	$items = YhmPasteRead($text, $model);
	$made = [];
	foreach ($items as $parsed) {
		if (!is_array($parsed)) {
			continue;
		}
		$class = YhmPasteOneOf((string) ($parsed['class'] ?? ''), ['UserRequest', 'Incident'], $hintClass === 'Incident' ? 'Incident' : 'UserRequest');
		$serviceName = YhmPasteOneOf((string) ($parsed['service'] ?? ''), YhmPasteServices(), 'Find a doctor');
		$decision = YhmPasteOneOf((string) ($parsed['decision'] ?? ''), ['need_value', 'do_it', 'already', 'not_pilot'], 'need_value');
		$title = YhmPasteUseful(trim((string) ($parsed['title'] ?? '')));
		if ($title === '' && preg_match('/\b(homepage|website|design)\b/i', $text)) {
			$title = 'Homepage design direction';
		}
		if ($title === '') {
			$title = 'Pasted message';
		}
		$title = substr($title, 0, 255);
		$points = YhmPastePoints($text);
		$opening = YhmPasteOpening($text);
		$description = YhmPasteUseful((string) ($parsed['description'] ?? ''));
		if ($description === '') {
			$description = $opening !== '' ? $opening : $title;
		}
		if (count($points) > 1) {
			$description .= "\n".implode("\n", array_map(static function ($point, $index) {
				return ($index + 1).'. '.$point;
			}, $points, array_keys($points)));
		}
		$assumption = YhmPasteUseful((string) ($parsed['assumption'] ?? ''));
		if ($assumption === '') {
			$assumption = $opening;
		}
		$value = YhmPasteUseful((string) ($parsed['value'] ?? ''));
		if ($value === '' && preg_match('/The priority is [^.]+\\./i', $text, $priority)) {
			$value = trim($priority[0]);
		}
		$check = YhmPasteUseful((string) ($parsed['assumption_check'] ?? ''));
		if ($check === '' && preg_match('/rather than copying/i', $text)) {
			$check = 'The page copies the reference site, or motion distracts from the content.';
		}
		$feature = YhmPasteUseful((string) ($parsed['feature'] ?? ''));
		if ($feature === '' && $points !== []) {
			$feature = $points[0];
		}
		if ($decision === 'need_value' && $value !== '') {
			$decision = 'do_it';
		}
		if (strlen($feature) > 160 && preg_match('/\bhomepage\b/i', $text)) {
			$feature = 'Homepage sections in the order from the email, one primary button, and minimal animation.';
		}

		$serviceSearch = new DBObjectSearch('Service');
		$serviceSearch->AddCondition('name', $serviceName);
		$service = (new DBObjectSet($serviceSearch))->Fetch();

		$ticket = MetaModel::NewObject($class);
		$ticket->Set('org_id', 2);
		$ticket->Set('caller_id', 2);
		$ticket->Set('origin', 'mail');
		$ticket->Set('title', $title);
		$ticket->Set('description', YhmPasteHtml($description));
		$ticket->Set('yhm_paste', $text);
		if ($service !== null) {
			$ticket->Set('service_id', $service->GetKey());
		}
		if ($class === 'UserRequest') {
			$ticket->Set('yhm_assumption', $assumption);
			$ticket->Set('yhm_assumption_check', $check);
			$ticket->Set('yhm_value', $value);
			$ticket->Set('yhm_feature', $feature);
			$ticket->Set('yhm_decision', $decision);
		}
		$ticket->DBInsert();
		$made[] = [
			'ticket' => $ticket,
			'class' => $class,
			'id' => (int) $ticket->GetKey(),
			'ref' => (string) $ticket->Get('ref'),
			'title' => $title,
			'service' => $serviceName,
		];
	}
	if ($made === []) {
		throw new RuntimeException('The model did not return a ticket.');
	}
	if (count($made) > 1) {
		$refs = array_column($made, 'ref');
		foreach ($made as $row) {
			$others = array_values(array_filter($refs, static function ($ref) use ($row) {
				return $ref !== $row['ref'];
			}));
			$row['ticket']->Set(
				'description',
				$row['ticket']->Get('description').'<p>Also from this message: '.htmlspecialchars(implode(', ', $others), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</p>'
			);
			$row['ticket']->DBUpdate();
		}
	}

	return [
		'ok' => true,
		'tickets' => array_map(static function ($row) {
			unset($row['ticket']);
			return $row;
		}, $made),
	];
}
