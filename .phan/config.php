<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['suppress_issue_types'] = array_merge(
	$cfg['suppress_issue_types'],
	[
		// Suppress issue types that currently exist in the codebase.
		'PhanEmptyFQSENInCallable',
		'PhanNonClassMethodCall',
		'PhanParamTooFewInPHPDoc',
		'PhanTypeArraySuspiciousNullable',
		'PhanTypeMismatchArgumentInternal',
		'PhanTypeMismatchArgumentNullable',
		'PhanTypeMismatchArgumentNullableInternal',
		'PhanTypeMismatchDimFetch',
		'PhanUndeclaredClassMethod',
		'PhanUndeclaredFunctionInCallable',
		'PhanUndeclaredMethod',
		'SecurityCheck-XSS'
	]
);

$cfg['directory_list'] = array_merge(
	$cfg['directory_list'],
	[
		'../../extensions/AdminLinks',
	]
);

$cfg['exclude_analysis_directory_list'] = array_merge(
	$cfg['exclude_analysis_directory_list'],
	[
		'../../extensions/AdminLinks',
	]
);

return $cfg;
