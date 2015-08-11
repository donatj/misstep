<?php

use donatj\MySqlSchema\Columns;

require('vendor/autoload.php');

$jql = file_get_contents('php://stdin');

$flags        = new donatj\Flags();
$drop         =& $flags->bool('drop', false, 'Include DROP TABLE?');
$inputComment =& $flags->bool('input-comment', false, 'Include original input as a comment?');

$columnFactory = new \donatj\Misstep\ColumnFactory();

$flags->parse();

try {
	if( trim($jql) == '' ) {
		return '';
	}

	$parser   = new \donatj\Misstep\Parser($columnFactory);
	$renderer = new \donatj\Misstep\Renderer($jql, $inputComment, $drop);

	$tables = $parser->parse($jql);
	echo $renderer->render($tables);
} catch(\donatj\Misstep\Exceptions\UserException $e) {
	fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
	die(1);
}
