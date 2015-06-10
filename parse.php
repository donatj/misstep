<?php

use donatj\MySqlSchema\Columns;
use donatj\MySqlSchema\Table;

require('vendor/autoload.php');

$jql = file_get_contents('php://stdin');

/**
 * @param $jql
 * @throws \Exception
 * @throws \donatj\Misstep\Exceptions\ParseException
 * @throws \donatj\Misstep\Exceptions\StructureException
 */
function parse( $jql ) {
	$jql = preg_replace('%^//.*$%mx', '', $jql); //remove commented lines before parse
	$jql .= "\n";

	$totalMatch = '/\#\s(?P<declaration>.*)
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

		$table = new Table($result[$i]['declaration']);
		$table->setCharset('utf8');
		$table->setCollation('utf8_general_ci');

		if( trim($result[$i]['comment']) ) {
			$table->setComment(parseComment($result[$i]['comment']));
		}

		$bodyMatch = '/(?P<signal>[-?!])[ \t](?P<colName>\S+)
		[ \t]+(?P<nullable>\*)?(?P<signed>-)?(?P<colType>[a-z]+)(?P<colLength>\d+)?(?P<colDecimal>,\d+)?
		(?:(?P<hasDefault>=)(?:\'(?P<default1>(?:\'\'|[^\'])*)\'|(?P<default2>\S+)))?
		(?P<pk>[ \t]\*?pk)?
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

			$col = null;
			switch( $bodyResult[$j]['colType'] ) {
				case 'bool':
					$col = new Columns\Numeric\Integers\TinyIntColumn($colName);
					$col->setLength(1);
					break;
				case 'tinyint':
					$col = new Columns\Numeric\Integers\TinyIntColumn($colName);
					break;
				case 'smallint':
					$col = new Columns\Numeric\Integers\SmallIntColumn($colName);
					break;
				case 'int':
					$col = new Columns\Numeric\Integers\IntColumn($colName);
					break;
				case 'mediumint':
					$col = new Columns\Numeric\Integers\MediumIntColumn($colName);
					break;
				case 'bigint':
					$col = new Columns\Numeric\Integers\BigIntColumn($colName);
					break;

				case 'tinytext':
					$col = new Columns\String\Text\TinyTextColumn($colName);
					break;
				case 'text':
					$col = new Columns\String\Text\TextColumn($colName);
					break;
				case 'mediumtext':
					$col = new Columns\String\Text\MediumTextColumn($colName);
					break;
				case 'longtext':
					$col = new Columns\String\Text\LongTextColumn($colName);
					break;

				case 'char':
					$col = new Columns\String\Character\CharColumn($colName, 255);
					break;
				case 'varchar':
					$col = new Columns\String\Character\VarcharColumn($colName, 255); //will get overriten further down
					break;


				case 'timestamp':
					$col = new Columns\Temporal\TimestampColumn($colName, 255);
					break;
				case 'year':
					$col = new Columns\Temporal\YearColumn($colName, 4);
					break;
				case 'datetime':
					$col = new Columns\Temporal\DateTimeColumn($colName);
					break;
				default:
					throw new \donatj\Misstep\Exceptions\StructureException('unknown type: ' . $bodyResult[$j]['colType']);
			}

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

	echo "SET FOREIGN_KEY_CHECKS = 0;\n\n";
	foreach( $tables as $table ) {
		echo $table->toString();
		echo "\n";
	}
	echo "SET FOREIGN_KEY_CHECKS = 1;\n";
}

try {
	parse($jql);
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