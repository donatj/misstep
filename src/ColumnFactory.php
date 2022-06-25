<?php

namespace donatj\Misstep;

use donatj\Misstep\Exceptions\StructureException;
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
	 * @param string $colType
	 * @param string $colName
	 * @throws \donatj\Misstep\Exceptions\StructureException
	 * @return \donatj\MySqlSchema\Columns\Numeric\Integers\BigIntColumn|\donatj\MySqlSchema\Columns\Numeric\Integers\IntColumn|\donatj\MySqlSchema\Columns\Numeric\Integers\MediumIntColumn|\donatj\MySqlSchema\Columns\Numeric\Integers\SmallIntColumn|\donatj\MySqlSchema\Columns\Numeric\Integers\TinyIntColumn|null
	 */
	public function make( $colType, $colName ) {
		switch( $colType ) {
			case 'bool':
				$col = new TinyIntColumn($colName);
				$col->setLength(1);

				return $col;
			case 'tinyint':
				return new TinyIntColumn($colName);
			case 'smallint':
				return new SmallIntColumn($colName);
			case 'int':
				return new IntColumn($colName);
			case 'mediumint':
				return new MediumIntColumn($colName);
			case 'bigint':
				return new BigIntColumn($colName);

			case 'tinytext':
				return new TinyTextColumn($colName);
			case 'text':
				return new TextColumn($colName);
			case 'mediumtext':
				return new MediumTextColumn($colName);
			case 'longtext':
				return new LongTextColumn($colName);

			case 'char':
				return new CharColumn($colName, 255);
			case 'varchar':
				return new VarcharColumn($colName, 255); //will get overriten further down

			case 'timestamp':
				return new TimestampColumn($colName, 255);
			case 'year':
				return new YearColumn($colName, 4);
			case 'datetime':
				return new DateTimeColumn($colName);
		}

		throw new StructureException('unknown type: ' . $colType);
	}

}
