<?php

use donatj\Misstep\ColumnFactory;
use donatj\Misstep\Parser;
use donatj\Misstep\Renderer;
use PHPUnit\Framework\TestCase;

class ParserApprovalTest extends TestCase {

	public function testParserApproval() : void {
		$ddls = glob(__DIR__ . '/parser.approval.stubs/*.mql');
		if( $ddls === false ) {
			$this->fail("Unable to read parser approval stubs");
		}

		foreach( $ddls as $ddl ) {
			$sql = __DIR__ . '/parser.approval.stubs/' . pathinfo($ddl, PATHINFO_FILENAME) . '.sql';
			if( !file_exists($sql) ) {
				$this->fail("Missing approval file for " . basename($ddl) . ": " . $sql);
			}

			$mql = file_get_contents($ddl);
			if( $mql === false ) {
				$this->fail("Unable to read MQL file: " . $ddl);
			}

			$sql = file_get_contents($sql);
			if( $sql === false ) {
				$this->fail("Unable to read SQL file: " . $sql);
			}

			$parser   = new Parser(
				new ColumnFactory,
			);
			$renderer = new Renderer(
				$mql,
				false,
				false,
				false,
			);

			$tables = $parser->parse($mql);
			$output = $renderer->render($tables);

			$this->assertSame(
				trim($sql),
				trim($output),
				"Approval test failed for " . basename($ddl),
			);
		}
	}

}
