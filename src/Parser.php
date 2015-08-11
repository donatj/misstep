<?php

namespace donatj\Misstep;

use donatj\Misstep\Exceptions\ParseException;
use donatj\Misstep\Exceptions\StructureException;
use donatj\MySqlSchema\Columns\AbstractColumn;
use donatj\MySqlSchema\Columns\Interfaces\OptionalLengthInterface;
use donatj\MySqlSchema\Columns\Interfaces\RequiredLengthInterface;
use donatj\MySqlSchema\Columns\Interfaces\SignedInterface;

class Parser {

	const TABLESET_MATCH = '/(?P<type>[#@])\s(?P<declaration>.*)
		(?P<comment>\n(?::\s.*\n?)*)
		(?P<body>
		    (?:[-!?]\s.*\n
		    (?::\s.*\n)*)+
		)/x';

	const COLUMN_MATCH = '/(?P<signal>[-?!])[ \t](?P<colName>\S+)
		[ \t]+(?P<nullable>\*)?(?P<signed>-)?(?P<colType>[a-z]+)(?P<colLength>\d+)?(?P<colDecimal>,\d+)?
		(?:(?P<hasDefault>=)(?:\'(?P<default1>(?:\'\'|[^\'])*)\'|(?P<default2>\S+)))?
		(?P<pk>[ \t]+\*?pk)?
		(?P<keys>(?:[ \t]+k\d+)*)
		(?P<uniques>(?:[ \t]+u\d+)*)
		(?P<comment>\n(?::[ \t].*\n?)*)/x';

	/**
	 * @var ColumnFactory
	 */
	protected $columnFactory;

	function __construct( ColumnFactory $columnFactory ) {
		$this->columnFactory = $columnFactory;
	}

	/**
	 * @param string $jql
	 * @return \donatj\Misstep\ParseTable[]
	 * @throws \donatj\Misstep\Exceptions\ParseException
	 * @throws \donatj\Misstep\Exceptions\StructureException
	 */
	public function parse( $jql ) {
		$jql = preg_replace('%^//.*$%mx', '', $jql); //remove commented lines before parse
		$jql = preg_replace('/[ \t]+$/m', '', $jql); //remove trailing whitespace from lines
		$jql .= "\n";

		$this->checkForParseErrors(self::TABLESET_MATCH, $jql);

		preg_match_all(self::TABLESET_MATCH, $jql, $result, PREG_SET_ORDER);

		/**
		 * @var $tables ParseTable[]
		 */
		$foreignKeys = [ 'parents' => [ ], 'children' => [ ] ];
		$tables      = [ ];
		for( $i = 0; $i < count($result); $i++ ) {
			$body = $result[$i]['body'] . "\n";

			$table = new ParseTable($result[$i]['declaration']);
			$table->setCharset('utf8');
			$table->setCollation('utf8_general_ci');
			$table->setIsPseudo($result[$i]['type'] == '@');

			if( trim($result[$i]['comment']) ) {
				$table->setComment($this->parseComment($result[$i]['comment']));
			}

			$tableKeys = [ ];

			$this->checkForParseErrors(self::COLUMN_MATCH, $body);
			preg_match_all(self::COLUMN_MATCH, $body, $bodyResult, PREG_SET_ORDER);
			for( $j = 0; $j < count($bodyResult); $j++ ) {

				$colName = $bodyResult[$j]['colName'];

				$colType = $bodyResult[$j]['colType'];

				$col = $this->columnFactory->make($colType, $colName);

				if(
					$bodyResult[$j]['colLength'] &&
					($col instanceof RequiredLengthInterface
					 || $col instanceof OptionalLengthInterface)
				) {
					$col->setLength($bodyResult[$j]['colLength']);
				}

				if( $bodyResult[$j]['signed'] ) {
					if( $col instanceof SignedInterface ) {
						$col->setSigned(true);
					} else {
						throw new StructureException('type ' . $col->getTypeName() . ' cannot be signed');
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
					$col->setComment($this->parseComment($bodyResult[$j]['comment']));
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
						throw new StructureException("foreign key remote {$col->getName()} already defined.");
					}
					$foreignKeys['parents'][$col->getName()] = $col;
				} elseif( $bodyResult[$j]['signal'] == '?' ) {
					$foreignKeys['children'][$col->getName()][] = $col;
				}
			}

			foreach( $tableKeys as $type => $tKeys ) {
				foreach( $tKeys as $tk => $tcols ) {
					$keyName = array_reduce($tcols, function ( $carry, AbstractColumn $item ) use ( $type ) {
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

		$this->linkForeignKeys($foreignKeys);

		return $tables;
	}

	/**
	 * @param string $regex
	 * @param string $data
	 * @throws \donatj\Misstep\Exceptions\ParseException
	 */
	protected function checkForParseErrors( $regex, $data ) {
		$split = preg_split($regex, $data);
		foreach( $split as $i ) {
			if( trim($i) ) {
				throw new ParseException('parse error on "' . var_export(trim($i) . '"', true));
			}
		}
	}

	/**
	 * @param string $input
	 * @return string
	 */
	protected function parseComment( $input ) {
		$comments = array_filter(explode("\n: ", $input));
		$comment  = trim(implode("\n", $comments));

		return $comment;
	}

	/**
	 * @param $foreignKeys
	 * @throws \donatj\Misstep\Exceptions\StructureException
	 */
	private function linkForeignKeys( array $foreignKeys ) {
		foreach( $foreignKeys['children'] as $name => $fks ) {
			if( !isset($foreignKeys['parents'][$name]) ) {
				throw new StructureException("unknown foreign key: {$name}");
			}

			$remote = $foreignKeys['parents'][$name];

			/**
			 * @var $local AbstractColumn
			 * @var $tbl ParseTable
			 */
			foreach( $fks as $local ) {
				$fkTables = $local->getTables();
				$tbl      = current($fkTables);

				$tbl->addForeignKey($local, $remote);
			}
		}
	}

}
