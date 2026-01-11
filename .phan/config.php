<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['suppress_issue_types'] = array_merge(
	$cfg['suppress_issue_types'],
	[
		// Suppress issue types that currently exist in the codebase.
		// This means that Phan initially won't do much, but it allows for
		// checks to be incrementally fixed and enabled without massive changes.
		'MediaWikiNoEmptyIfDefined',
		'PhanEmptyFQSENInCallable',
		'PhanNonClassMethodCall',
		'PhanParamTooFewInPHPDoc',
		'PhanParamTooMany',
		'PhanPossiblyUndeclaredVariable',
		'PhanRedundantCondition',
		'PhanTypeArraySuspiciousNullable',
		'PhanTypeMismatchArgument',
		'PhanTypeMismatchArgumentInternal',
		'PhanTypeMismatchArgumentInternalProbablyReal',
		'PhanTypeMismatchArgumentNullable',
		'PhanTypeMismatchArgumentNullableInternal',
		'PhanTypeMismatchArgumentProbablyReal',
		'PhanTypeMismatchDimFetch',
		'PhanTypeMismatchDimFetchNullable',
		'PhanTypeSuspiciousStringExpression',
		'PhanUndeclaredClassMethod',
		'PhanUndeclaredFunctionInCallable',
		'PhanUndeclaredMethod',
		'PhanUndeclaredVariable',
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
