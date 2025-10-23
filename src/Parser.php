<?php

namespace donatj\Misstep;

use donatj\Misstep\Exceptions\ParseException;
use donatj\Misstep\Exceptions\RuntimeException;
use donatj\Misstep\Exceptions\StructureException;
use donatj\MySqlSchema\Columns\AbstractColumn;
use donatj\MySqlSchema\Columns\Interfaces\DecimalPlacesInterface;
use donatj\MySqlSchema\Columns\Interfaces\MaxDigitsInterface;
use donatj\MySqlSchema\Columns\Interfaces\OptionalLengthInterface;
use donatj\MySqlSchema\Columns\Interfaces\PrecisionInterface;
use donatj\MySqlSchema\Columns\Interfaces\RequiredLengthInterface;
use donatj\MySqlSchema\Columns\Interfaces\SignedInterface;
use donatj\MySqlSchema\Columns\Numeric\AbstractIntegerColumn;

class Parser {

	private const TABLESET_MATCH = '/(?P<type>[#@])\s(?P<declaration>.*)
		(?P<comment>\n(?::\s.*\n?)*)
		(?P<body>
		    (?:[-!?]\s+.*\n
		    (?::\s.*\n)*)+
		)/x';

	private const COLUMN_MATCH = '/(?P<signal>[-?!])
		[ \t]+(?:(?P<ref>\S*):)?(?P<colName>\S+)
		[ \t]+(?P<nullable>\*)?(?P<signed>-)?(?P<colType>[a-z]+)(?P<colLength>\d+)?(?:,(?P<colDecimal>\d+))?
		(?:(?P<hasDefault>=)(?:\'(?P<default1>(?:\'\'|[^\'])*)\'|(?P<default2>\S+)))?
		(?P<pk>[ \t]+\*?pk)?
		(?P<keys>(?:[ \t]+k\d+(?::\d+)?)*)
		(?P<uniques>(?:[ \t]+u\d+(?::\d+)?)*)
		(?P<comment>\n(?::[ \t].*\n?)*)/x';

	public function __construct(
		protected readonly ColumnFactory $columnFactory,
	) {
	}

	/**
	 * @throws \donatj\Misstep\Exceptions\ParseException
	 * @throws \donatj\Misstep\Exceptions\StructureException
	 * @return array<string,\donatj\Misstep\ParseTable>
	 */
	public function parse( string $jql ) : array {
		$jql = preg_replace('%^//.*$%mx', '', $jql); // remove commented lines before parse
		if( $jql === null ) {
			throw new RuntimeException('failed while parsing comments');
		}

		$jql = preg_replace('/[ \t]+$/m', '', $jql); // remove trailing whitespace from lines
		if( $jql === null ) {
			throw new RuntimeException('failed while removing trailing whitespace');
		}

		$jql .= "\n";

		$this->checkForParseErrors(self::TABLESET_MATCH, $jql);

		preg_match_all(self::TABLESET_MATCH, $jql, $result, PREG_SET_ORDER);

		/**
		 * @var array{children:array<string,AbstractColumn[]>,parents:array<string,AbstractColumn>} $foreignKeys
		 */
		$foreignKeys = [ 'parents' => [], 'children' => [] ];
		/**
		 * @var array<string,\donatj\Misstep\ParseTable> $tables
		 */
		$tables = [];

		$resultCount = count($result);
		for( $i = 0; $i < $resultCount; $i++ ) {
			$body = $result[$i]['body'] . "\n";

			$table = new ParseTable($result[$i]['declaration']);

			// @todo make charset/collation configurable
			$table->setCharset('utf8mb3');
			$table->setCollation('utf8mb3_general_ci');

			$table->setIsPseudo($result[$i]['type'] === '@');

			if( trim($result[$i]['comment']) ) {
				$table->setComment($this->parseComment($result[$i]['comment']));
			}

			$tableKeys = [];

			$this->checkForParseErrors(self::COLUMN_MATCH, $body);
			preg_match_all(self::COLUMN_MATCH, $body, $bodyResult, PREG_SET_ORDER);

			$bodyResultCount = count($bodyResult);
			for( $j = 0; $j < $bodyResultCount; $j++ ) {
				$colName = $bodyResult[$j]['colName'];
				$colType = $bodyResult[$j]['colType'];

				$signal = $bodyResult[$j]['signal'];
				$ref    = $bodyResult[$j]['ref'];
				if( $signal === '-' && $ref !== '' ) {
					throw new ParseException('only foreign keys and foreign key definitions can have an explicit reference');
				}

				// replace * with table name
				$colName = preg_replace_callback('/\\\\?\*/', function ( $matches ) use ( $table ) {
					if( str_starts_with($matches[0], '\\') ) {
						return '*';
					}

					return $table->getName();
				}, $colName);

				if( $colName === null ) {
					throw new RuntimeException('failed while replacing * with table name');
				}

				$ref = $ref ?: $colName;

				$col = $this->columnFactory->make($colType, $colName);

				$colLength  = $bodyResult[$j]['colLength'] === '' ? null : intval($bodyResult[$j]['colLength']);
				$colDecimal = $bodyResult[$j]['colDecimal'] === '' ? null : intval($bodyResult[$j]['colDecimal']);
				if ($colLength !== null) {
					if (
						$colDecimal !== null
						&& $col instanceof MaxDigitsInterface
						&& $col instanceof DecimalPlacesInterface
					) {
						$col->setMaxDigits($colLength);
						$col->setDecimalPlaces($colDecimal);
					} elseif (
						$col instanceof RequiredLengthInterface
						|| $col instanceof OptionalLengthInterface
					) {
						$col->setLength($colLength);
					} elseif ($col instanceof PrecisionInterface) {
						$col->setPrecision($colLength);
					}
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
				if( trim($bodyResult[$j]['pk']) === "pk" ) {
					$table->addPrimaryKey($col);
				} elseif( trim($bodyResult[$j]['pk']) === "*pk" ) {
					if( $col instanceof AbstractIntegerColumn ) {
						$table->addAutoIncrement($col);
					} else {
						throw new StructureException('auto-increment primary keys must be an integer type, given: ' . $col->getTypeName());
					}
				}

				if( trim($bodyResult[$j]['keys']) !== '' ) {
					$splitKeys = preg_split('/[ \t]+/', trim($bodyResult[$j]['keys']));
					if( $splitKeys === false ) {
						throw new RuntimeException('failed to split keys');
					}

					$keys = array_filter($splitKeys);
					foreach( $keys as $key ) {
						$keyParts                            = explode(':', $key, 2);
						$tableKeys['NORMAL'][$keyParts[0]][] = [ $col, (int)($keyParts[1] ?? -1000) ];
					}
				}

				if( trim($bodyResult[$j]['uniques']) !== '' ) {
					$splitKeys = preg_split('/[ \t]+/', trim($bodyResult[$j]['uniques']));
					if( $splitKeys === false ) {
						throw new RuntimeException('failed to split unique keys');
					}

					$keys = array_filter($splitKeys);
					foreach( $keys as $key ) {
						$keyParts                            = explode(':', $key, 2);
						$tableKeys['UNIQUE'][$keyParts[0]][] = [ $col, (int)($keyParts[1] ?? -1000) ];
					}
				}

				if( $signal === '!' ) {
					if( !empty($foreignKeys['parents'][$ref]) ) {
						throw new StructureException("foreign key remote {$ref} already defined.");
					}

					$foreignKeys['parents'][$ref] = $col;
				} elseif( $signal === '?' ) {
					$foreignKeys['children'][$ref][] = $col;
				}
			}

			foreach( $tableKeys as $type => $tKeys ) {
				foreach( $tKeys as $tk => $tcols ) {
					usort($tcols, function ( array $a, array $b ) : int {
						return $a[1] <=> $b[1];
					});

					$keyName = $this->generateKeyName($type, $tcols, $tk);

					foreach( $tcols as $tcol ) {
						$table->addKeyColumn($keyName, $tcol[0], null, $type);
					}
				}
			}

			$tables[$table->getName()] = $table;
		}

		$this->linkForeignKeys($foreignKeys);

		return $tables;
	}

	/**
	 * @throws \donatj\Misstep\Exceptions\ParseException
	 */
	protected function checkForParseErrors( string $regex, string $data ) : void {
		$split = preg_split($regex, $data);
		if( $split === false ) {
			throw new RuntimeException('failed to split data');
		}

		foreach( $split as $i ) {
			if( trim($i) !== '' ) {
				throw new ParseException('parse error on "' . var_export(trim($i) . '"', true));
			}
		}
	}

	protected function parseComment( string $input ) : string {
		$comments = array_filter(explode("\n: ", $input));

		return trim(implode("\n", $comments));
	}

	/**
	 * @param array{children:array<string,AbstractColumn[]>,parents:array<string,AbstractColumn>} $foreignKeys
	 * @throws \donatj\Misstep\Exceptions\StructureException
	 */
	private function linkForeignKeys( array $foreignKeys ) : void {
		foreach( $foreignKeys['children'] as $name => $fks ) {
			$remote = $foreignKeys['parents'][$name] ?? null;
			if( $remote === null ) {
				throw new StructureException("unknown foreign key ref: {$name}");
			}

			foreach( $fks as $local ) {
				$fkTables = $local->getTables();
				$tbl      = current($fkTables);
				if( $tbl === false ) {
					throw new RuntimeException('failed to get table from column');
				}

				if( $local->getTypeName() !== $remote->getTypeName() ) {
					throw new StructureException("{$local->getName()} type does not match defined foreign key type");
				}

				if( $local instanceof SignedInterface && $remote instanceof SignedInterface ) {
					if( $local->isSigned() !== $remote->isSigned() ) {
						throw new StructureException("{$local->getName()} signedness does not match defined foreign key signedness");
					}
				}

				if( ($local instanceof OptionalLengthInterface && $remote instanceof OptionalLengthInterface)
					|| ($local instanceof RequiredLengthInterface && $remote instanceof RequiredLengthInterface)
				) {
					if( $local->getLength() !== $remote->getLength() ) {
						throw new StructureException("{$local->getName()} length does not match defined foreign key length");
					}
				}

				$tbl->addForeignKey($local, $remote);
			}
		}
	}

	/**
	 * @todo Handle camel case and other weird things people might do
	 */
	private function getShortColumnNameAcronym( string $columnName ) : string {
		$parts = explode('_', $columnName);

		return implode(array_map(function ( $part ) { return $part[0]; }, $parts));
	}

	/**
	 * @param "NORMAL"|"UNIQUE"               $type
	 * @param list<array{AbstractColumn,int}> $tcols
	 */
	private function generateKeyName( string $type, array $tcols, string $tk ) : string {
		$prefix = $type === 'UNIQUE' ? 'unq_' : 'idx_';

		$keyName = array_reduce(array_column($tcols, 0), function ( string $carry, AbstractColumn $item ) use ( $prefix ) {
			return ($carry ? $carry . '_and_' : $prefix) . $item->getName();
		}, '');
		$keyName .= '_' . $tk;

		if( strlen($keyName) > 64 ) {
			$keyName = array_reduce(array_column($tcols, 0), function ( string $carry, AbstractColumn $item ) use ( $prefix ) {
				$name = $this->getShortColumnNameAcronym($item->getName());

				return ($carry ? $carry . '_and_' : $prefix) . $name;
			}, '');
		}

		return $this->stablePseudorandomStringShortener($keyName, 64);
	}

	/**
	 * This function generates a stable pseudorandom substring of the given length from the input string.
	 */
	private function stablePseudorandomStringShortener(string $s, int $length) : string {
		$n = strlen($s);
		if ($n <= $length) {
			return $s;
		}

		$scores = [];
		for ($i = 0; $i < $n; $i++) {
			$h = hash('sha256', $s . ':' . $i, true);
			$scoreArray = unpack('N', substr($h, 0, 4));
			if( $scoreArray === false ) {
				throw new RuntimeException('failed to unpack hash while generating stable pseudorandom string');
			}

			$scores[] = [$i, $scoreArray[1]];
		}

		usort($scores, fn ($a, $b) => ($a[1] <=> $b[1]) ?: ($a[0] <=> $b[0]));
		$keep = array_slice(array_column($scores, 0), 0, $length);
		sort($keep, SORT_NUMERIC);

		$out = '';
		foreach ($keep as $i) {
			$out .= $s[$i];
		}

		return $out;
	}

}
