<?php

SetupWebPage::AddModule(
	__FILE__,
	'yhm-request-context/1.0.0',
	[
		'label' => 'YourHealthMatch request context',
		'category' => 'business',
		'dependencies' => [
			'itop-request-mgmt-itil/3.3.0',
			'itop-service-mgmt/3.3.0',
		],
		'mandatory' => false,
		'visible' => true,
		'datamodel' => [
			'datamodel.yhm-request-context.xml',
			'main.yhm-paste.php',
		],
		'dictionaries' => [
			'en.dict.yhm-request-context.php',
			'de.dict.yhm-request-context.php',
		],
		'data.struct' => [],
		'data.sample' => [],
		'doc.manual_setup' => '',
		'doc.more_information' => '',
		'settings' => [],
	]
);
