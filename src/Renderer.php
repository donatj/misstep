<?php

namespace donatj\Misstep;

class Renderer {

	public function __construct(
		protected string $input,
		protected bool $inputComment,
		protected bool $dropTables,
		protected bool $disableForeignKeyChecks = true,
	) {
	}

	/**
	 * @param ParseTable[] $tables
	 */
	public function render( array $tables ) : string {
		$output = '';
		if( $this->inputComment ) {
			$outputJql = rtrim($this->input);
			$output    .= "/** [misstep-v1] \n{$outputJql}\n[eof-misstep] **/\n\n";
		}

		if( $this->disableForeignKeyChecks ) {
			$output .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
		}

		foreach( $tables as $table ) {
			if( !$table->isIsPseudo() ) {
				if( $this->dropTables ) {
					$output .= sprintf("DROP TABLE IF EXISTS `%s`;\n", $this->escapeIdentifier($table->getName()));
				}

				$output .= $table->toString();
				$output .= "\n";
			}
		}

		if( $this->disableForeignKeyChecks ) {
			$output .= "SET FOREIGN_KEY_CHECKS = 1;\n";
		}

		return $output;
	}

	// @todo make available from mysql-schema
	protected function escapeIdentifier( string $input, string $wrapChar = '`' ) : string {
		return str_replace($wrapChar, $wrapChar . $wrapChar, $input);
	}

}
