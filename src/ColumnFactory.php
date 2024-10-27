<?php

namespace donatj\Misstep;

use donatj\Misstep\Exceptions\StructureException;
use donatj\MySqlSchema\Columns\AbstractColumn;
use donatj\MySqlSchema\Columns\Json\JsonColumn;
use donatj\MySqlSchema\Columns\Numeric\Integers\BigIntColumn;
use donatj\MySqlSchema\Columns\Numeric\Integers\IntColumn;
use donatj\MySqlSchema\Columns\Numeric\Integers\MediumIntColumn;
use donatj\MySqlSchema\Columns\Numeric\Integers\SmallIntColumn;
use donatj\MySqlSchema\Columns\Numeric\Integers\TinyIntColumn;
use donatj\MySqlSchema\Columns\String\Character\CharColumn;
use donatj\MySqlSchema\Columns\String\Character\VarcharColumn;
use donatj\MySqlSchema\Columns\String\Text\LongTextColumn;
use donatj\MySqlSchema\Columns\String\Text\MediumTextColumn;
use donatj\MySqlSchema\Columns\String\Text\TextColumn;
use donatj\MySqlSchema\Columns\String\Text\TinyTextColumn;
use donatj\MySqlSchema\Columns\Temporal\DateTimeColumn;
use donatj\MySqlSchema\Columns\Temporal\TimestampColumn;
use donatj\MySqlSchema\Columns\Temporal\YearColumn;

class ColumnFactory {

	/**
	 * @throws \donatj\Misstep\Exceptions\StructureException
	 */
	public function make( string $colType, string $colName ) : DateTimeColumn|TinyTextColumn|SmallIntColumn|IntColumn|CharColumn|MediumIntColumn|TimestampColumn|MediumTextColumn|YearColumn|LongTextColumn|TinyIntColumn|BigIntColumn|VarcharColumn|TextColumn|JsonColumn {

		$makeBool = function( string $colName ) : TinyIntColumn {
			$col = new TinyIntColumn($colName);
			$col->setLength(1);

			return $col;
		};

		return match ($colType) {
			'bool'       => $makeBool($colName),
			'tinyint'    => new TinyIntColumn($colName),
			'smallint'   => new SmallIntColumn($colName),
			'int'        => new IntColumn($colName),
			'mediumint'  => new MediumIntColumn($colName),
			'bigint'     => new BigIntColumn($colName),
			'tinytext'   => new TinyTextColumn($colName),
			'text'       => new TextColumn($colName),
			'mediumtext' => new MediumTextColumn($colName),
			'longtext'   => new LongTextColumn($colName),
			'char'       => new CharColumn($colName, 255),
			'varchar'    => new VarcharColumn($colName, 255),
			'timestamp'  => new TimestampColumn($colName),
			'year'       => new YearColumn($colName, 4),
			'datetime'   => new DateTimeColumn($colName),
			'json'       => new JsonColumn($colName),
			default      => throw new StructureException('unknown type: ' . $colType),
		};
	}

}
