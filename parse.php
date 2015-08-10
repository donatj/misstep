<?php

use donatj\MySqlSchema\Columns;
use donatj\MySqlSchema\Table;

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
	$jql = preg_replace('%^//.*$%mx', '', $jql); //remove commented lines before parse
	$jql = preg_replace('/[ \t]+$/m', '', $jql); //remove trailing whitespace from lines
	$jql .= "\n";

	$totalMatch = '/(?P<type>[#@])\s(?P<declaration>.*)
		(?P<comment>\n(?::\s.*\n?)*)
		(?P<body>
		    (?:[-!?]\s.*\n
		    (?::\s.*\n)*)+
		)/x';


	checkForParseErrors($totalMatch, $jql);

	preg_match_all($totalMatch, $jql, $result, PREG_SET_ORDER);

	/**
	 * @var $tables \donatj\MySqlSchema\Table[]
	 */
	$foreignKeys = [ 'parents' => [ ], 'children' => [ ] ];
	$tables      = [ ];
	for( $i = 0; $i < count($result); $i++ ) {
		$body = $result[$i]['body'] . "\n";

		$table = new \donatj\Misstep\GenerationTable($result[$i]['declaration']);
		$table->setCharset('utf8');
		$table->setCollation('utf8_general_ci');
		$table->setIsPseudo($result[$i]['type'] == '@');

		if( trim($result[$i]['comment']) ) {
			$table->setComment(parseComment($result[$i]['comment']));
		}

		$bodyMatch = '/(?P<signal>[-?!])[ \t](?P<colName>\S+)
		[ \t]+(?P<nullable>\*)?(?P<signed>-)?(?P<colType>[a-z]+)(?P<colLength>\d+)?(?P<colDecimal>,\d+)?
		(?:(?P<hasDefault>=)(?:\'(?P<default1>(?:\'\'|[^\'])*)\'|(?P<default2>\S+)))?
		(?P<pk>[ \t]+\*?pk)?
		(?P<keys>(?:[ \t]+k\d+)*)
		(?P<uniques>(?:[ \t]+u\d+)*)
		(?P<comment>\n(?::[ \t].*\n?)*)/x';

		$tableKeys = [ ];

		checkForParseErrors($bodyMatch, $body);
		preg_match_all($bodyMatch, $body, $bodyResult, PREG_SET_ORDER);
		for( $j = 0; $j < count($bodyResult); $j++ ) {

			$colName = $bodyResult[$j]['colName'];
//		if( $colName == '_' ) {
//			$colName = $table->getName() . '_id';
//		}

			$colType = $bodyResult[$j]['colType'];

			$col = $columnFactory->make($colType, $colName);

			if(
				$bodyResult[$j]['colLength'] &&
				($col instanceof Columns\Interfaces\RequiredLengthInterface
				 || $col instanceof Columns\Interfaces\OptionalLengthInterface)
			) {
				$col->setLength($bodyResult[$j]['colLength']);
			}

			if( $bodyResult[$j]['signed'] ) {
				if( $col instanceof Columns\Interfaces\SignedInterface ) {
					$col->setSigned(true);
				} else {
					throw new \donatj\Misstep\Exceptions\StructureException('type ' . $col->getTypeName() . ' cannot be signed');
				}
			}

			if( $bodyResult[$j]['nullable'] ) {
				$col->setNullable(true);
			}

			if( $bodyResult[$j]['hasDefault'] ) {
				if( $bodyResult[$j]['default2'] != '' ) {
					$col->setDefault($bodyResult[$j]['default2']);
				} else {
					$col->setDefault(str_replace("''", "'", $bodyResult[$j]['default1']));
				}
			}

			if( trim($bodyResult[$j]['comment']) ) {
				$col->setComment(parseComment($bodyResult[$j]['comment']));
			}

			$table->addColumn($col);
			if( $bodyResult[$j]['pk'] == " pk" ) {
				$table->addPrimaryKey($col);
			} elseif( $bodyResult[$j]['pk'] == " *pk" ) {
				$table->addAutoIncrement($col);
			}

			if( trim($bodyResult[$j]['keys']) != '' ) {
				$keys = array_filter(explode(' ', $bodyResult[$j]['keys']));
				foreach( $keys as $key ) {
					$tableKeys['NORMAL'][$key][] = $col;
				}
			}

			if( trim($bodyResult[$j]['uniques']) != '' ) {
				$keys = array_filter(explode(' ', $bodyResult[$j]['uniques']));
				foreach( $keys as $key ) {
					$tableKeys['UNIQUE'][$key][] = $col;
				}
			}

			if( $bodyResult[$j]['signal'] == '!' ) {
				if( !empty($foreignKeys['parents'][$col->getName()]) ) {
					throw new \donatj\Misstep\Exceptions\StructureException("foreign key remote {$col->getName()} already defined.");
				}
				$foreignKeys['parents'][$col->getName()] = $col;
			} elseif( $bodyResult[$j]['signal'] == '?' ) {
				$foreignKeys['children'][$col->getName()][] = $col;
			}
		}
// see($tableKeys);
		foreach( $tableKeys as $type => $tKeys ) {
			foreach( $tKeys as $tk => $tcols ) {
//			see('x', $type);
				$keyName = array_reduce($tcols, function ( $carry, Columns\AbstractColumn $item ) use ( $type ) {
					return ($carry ? $carry . '_and_' : ($type == 'UNIQUE' ? 'unq_' : 'idx_')) . $item->getName();
				}, '');
				$keyName .= '_' . $tk;

				foreach( $tcols as $tcol ) {
					$table->addKeyColumn($keyName, $tcol, null, $type);
				}
			}
		}

		$tables[$table->getName()] = $table;
	}

// Link up foreign keys
	foreach( $foreignKeys['children'] as $name => $fks ) {
		if( !isset($foreignKeys['parents'][$name]) ) {
			throw new \donatj\Misstep\Exceptions\StructureException("unknown foreign key: {$name}");
		}

		$remote = $foreignKeys['parents'][$name];

		/**
		 * @var $local Columns\AbstractColumn
		 * @var $tbl Table
		 */
		foreach( $fks as $local ) {
			$fkTables = $local->getTables();
			$tbl      = current($fkTables);

			$tbl->addForeignKey($local, $remote);
		}
	}

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


/**
 * @param $regex
 * @param $data
 * @return mixed
 * @throws \Exception
 */
function checkForParseErrors( $regex, $data ) {
	$split = preg_split($regex, $data);
	foreach( $split as $i ) {
		if( trim($i) ) {
			throw new \donatj\Misstep\Exceptions\ParseException('parse error on "' . var_export(trim($i) . '"', true));
		}
	}
}

/**
 * @param $input
 * @return string
 */
function parseComment( $input ) {
	$comments = array_filter(explode("\n: ", $input));
	$comment  = trim(implode("\n", $comments));

	return $comment;
}