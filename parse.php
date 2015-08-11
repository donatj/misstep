<?php

use donatj\MySqlSchema\Columns;

require('vendor/autoload.php');

$jql = file_get_contents('php://stdin');

$flags        = new donatj\Flags();
$drop         =& $flags->bool('drop', false, 'Include DROP TABLE?');
$inputComment =& $flags->bool('input-comment', false, 'Include original input as a comment?');

$columnFactory = new \donatj\Misstep\ColumnFactory();

$flags->parse();

// @todo make availible from mysql-schema
function escape( $input, $wrapChar = '`' ) {
	return str_replace($wrapChar, $wrapChar . $wrapChar, $input);
}

/**
 * @param $jql
 * @throws \Exception
 * @throws \donatj\Misstep\Exceptions\ParseException
 * @throws \donatj\Misstep\Exceptions\StructureException
 */
function parse( $jql, \donatj\Misstep\ColumnFactory $columnFactory, $drop, $inputComment ) {
	$parser = new \donatj\Misstep\Parser($columnFactory);

	$tables = $parser->parse($jql);

	if( $inputComment ) {
		$outputJql = rtrim($jql);
		echo "/*\n{$outputJql}\n*/\n\n";
	}

	echo "SET FOREIGN_KEY_CHECKS = 0;\n\n";
	foreach( $tables as $table ) {
		if( !$table->isIsPseudo() ) {
			if( $drop ) {
				echo "DROP TABLE IF EXISTS `" . escape($table->getName()) . "`;\n";
			}
			echo $table->toString();
			echo "\n";
		}
	}
	echo "SET FOREIGN_KEY_CHECKS = 1;\n";
}

try {
	if( trim($jql) == '' ) {
		return '';
	}
	parse($jql, $columnFactory, $drop, $inputComment);
} catch(\donatj\Misstep\Exceptions\UserException $e) {
	fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
	die(1);
}
