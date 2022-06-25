#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

$flags        = new donatj\Flags;
$drop         =& $flags->bool('drop', false, 'Include DROP TABLE?');
$inputComment =& $flags->bool('input-comment', false, 'Include original input as a comment?');

$columnFactory = new \donatj\Misstep\ColumnFactory;

try {
	$flags->parse();
} catch( Exception $e ) {
	echo $e->getMessage() . "\n";
	echo $flags->getDefaults();

	die(1);
}

$jql = file_get_contents('php://stdin');

try {
	if( trim($jql) == '' ) {
		return '';
	}

	$parser   = new \donatj\Misstep\Parser($columnFactory);
	$renderer = new \donatj\Misstep\Renderer($jql, $inputComment, $drop);

	$tables = $parser->parse($jql);
	echo $renderer->render($tables);
} catch( \donatj\Misstep\Exceptions\UserException $e ) {
	fwrite(STDERR, "Error: " . $e->getMessage() . "\n");

	die(1);
}
