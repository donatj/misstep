<?php

use donatj\Misstep\ColumnFactory;
use donatj\Misstep\Exceptions\ParseException;
use donatj\Misstep\Exceptions\StructureException;
use donatj\Misstep\Parser;
use PHPUnit\Framework\TestCase;

class ParserExceptionTest extends TestCase {

	private Parser $parser;

	protected function setUp() : void {
		$this->parser = new Parser(new ColumnFactory);
	}

	/**
	 * Tests that only foreign keys can have explicit references (line 100)
	 */
	public function testNormalColumnWithExplicitReferenceThrowsException() : void {
		$this->expectException(ParseException::class);
		$this->expectExceptionMessage('only foreign keys and foreign key definitions can have an explicit reference');

		$mql = <<<'MQL'
# test_table
- ref:column_name int
MQL;

		$this->parser->parse($mql);
	}

	/**
	 * Tests parse error detection for unparseable content (line 245)
	 */
	public function testUnparseableContentThrowsException() : void {
		$this->expectException(ParseException::class);
		$this->expectExceptionMessage('parse error on');

		$mql = <<<'MQL'
# test_table
This is not valid MQL syntax
MQL;

		$this->parser->parse($mql);
	}

	/**
	 * Tests that unsigned types cannot be marked as signed (line 144)
	 */
	public function testUnsignedTypeMarkedAsSignedThrowsException() : void {
		$this->expectException(StructureException::class);
		$this->expectExceptionMessage('cannot be signed');

		$mql = <<<'MQL'
# test_table
- test_col -timestamp
MQL;

		$this->parser->parse($mql);
	}

	/**
	 * Tests that varchar cannot be marked as signed
	 */
	public function testVarcharMarkedAsSignedThrowsException() : void {
		$this->expectException(StructureException::class);
		$this->expectExceptionMessage('type varchar cannot be signed');

		$mql = <<<'MQL'
# test_table
- test_col -varchar50
MQL;

		$this->parser->parse($mql);
	}

	/**
	 * Tests that text cannot be marked as signed
	 */
	public function testTextMarkedAsSignedThrowsException() : void {
		$this->expectException(StructureException::class);
		$this->expectExceptionMessage('type text cannot be signed');

		$mql = <<<'MQL'
# test_table
- test_col -text
MQL;

		$this->parser->parse($mql);
	}

	/**
	 * Tests that json cannot be marked as signed
	 */
	public function testJsonMarkedAsSignedThrowsException() : void {
		$this->expectException(StructureException::class);
		$this->expectExceptionMessage('type json cannot be signed');

		$mql = <<<'MQL'
# test_table
- test_col -json
MQL;

		$this->parser->parse($mql);
	}

	/**
	 * Tests that auto-increment primary keys must be integer types (line 171)
	 */
	public function testNonIntegerAutoIncrementThrowsException() : void {
		$this->expectException(StructureException::class);
		$this->expectExceptionMessage('auto-increment primary keys must be an integer type');

		$mql = <<<'MQL'
# test_table
- test_id varchar10 *pk
MQL;

		$this->parser->parse($mql);
	}

	/**
	 * Tests that foreign key remotes cannot be defined twice (line 203)
	 */
	public function testDuplicateForeignKeyRemoteThrowsException() : void {
		$this->expectException(StructureException::class);
		$this->expectExceptionMessage('foreign key remote user_id already defined');

		$mql = <<<'MQL'
@ users
! user_id int pk

@ duplicate
! user_id int pk

# test_table
? user_id int
MQL;

		$this->parser->parse($mql);
	}

	/**
	 * Tests that foreign key references must exist (line 264)
	 */
	public function testUnknownForeignKeyRefThrowsException() : void {
		$this->expectException(StructureException::class);
		$this->expectExceptionMessage('unknown foreign key ref: nonexistent_id');

		$mql = <<<'MQL'
# test_table
? nonexistent_id int
MQL;

		$this->parser->parse($mql);
	}

	/**
	 * Tests that foreign key types must match (line 275)
	 */
	public function testForeignKeyTypeMismatchThrowsException() : void {
		$this->expectException(StructureException::class);
		$this->expectExceptionMessage('type does not match defined foreign key type');

		$mql = <<<'MQL'
@ users
! user_id int pk

# test_table
? user_id bigint
MQL;

		$this->parser->parse($mql);
	}

	/**
	 * Tests that foreign key signedness must match (line 280)
	 */
	public function testForeignKeySignednessMismatchThrowsException() : void {
		$this->expectException(StructureException::class);
		$this->expectExceptionMessage('signedness does not match defined foreign key signedness');

		$mql = <<<'MQL'
@ users
! user_id -int pk

# test_table
? user_id int
MQL;

		$this->parser->parse($mql);
	}

	/**
	 * Tests that foreign key lengths must match (line 288)
	 */
	public function testForeignKeyLengthMismatchThrowsException() : void {
		$this->expectException(StructureException::class);
		$this->expectExceptionMessage('length does not match defined foreign key length');

		$mql = <<<'MQL'
@ users
! user_name varchar50 pk

# test_table
? user_name varchar100
MQL;

		$this->parser->parse($mql);
	}

	/**
	 * Tests parse error on malformed table definition
	 */
	public function testMalformedTableDefinitionThrowsException() : void {
		$this->expectException(ParseException::class);
		$this->expectExceptionMessage('parse error on');

		$mql = <<<'MQL'
# test_table
- column1 int
Invalid content here
- column2 varchar50
MQL;

		$this->parser->parse($mql);
	}

	/**
	 * Tests parse error on invalid column syntax
	 */
	public function testInvalidColumnSyntaxThrowsException() : void {
		$this->expectException(ParseException::class);
		$this->expectExceptionMessage('parse error on');

		$mql = <<<'MQL'
# test_table
- column_name
MQL;

		$this->parser->parse($mql);
	}

	/**
	 * Tests that foreign key definitions (!) can have explicit references
	 * This should NOT throw an exception
	 */
	public function testForeignKeyDefinitionWithExplicitReference() : void {
		$mql = <<<'MQL'
@ users
! ref:user_id int pk

# test_table
? ref:user_id int
MQL;

		$tables = $this->parser->parse($mql);
		$this->assertCount(2, $tables);
	}

	/**
	 * Tests that foreign key references (?) can have explicit references
	 * This should NOT throw an exception
	 */
	public function testForeignKeyReferenceWithExplicitReference() : void {
		$mql = <<<'MQL'
@ users
! my_ref:user_id int pk

# test_table
? my_ref:local_user_id int
MQL;

		$tables = $this->parser->parse($mql);
		$this->assertCount(2, $tables);
	}

	/**
	 * Tests duplicate foreign key remote within the same parse
	 */
	public function testDuplicateForeignKeyRemoteInSingleTableThrowsException() : void {
		$this->expectException(StructureException::class);
		$this->expectExceptionMessage('foreign key remote user_id already defined');

		$mql = <<<'MQL'
@ users
! user_id int pk
! user_id int pk
MQL;

		$this->parser->parse($mql);
	}

}
