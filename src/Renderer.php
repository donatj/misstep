<?php

namespace donatj\Misstep;

class Renderer {

	/**
	 * @var string
	 */
	protected $input;

	/**
	 * @var bool
	 */
	protected $inputComment;

	/**
	 * @var bool
	 */
	protected $dropTables;

	/**
	 * @param string $input
	 * @param bool   $inputComment
	 * @param bool   $dropTables
	 */
	public function __construct( $input, $inputComment, $dropTables ) {
		$this->input        = $input;
		$this->inputComment = $inputComment;
		$this->dropTables   = $dropTables;
	}

	/**
	 * @param ParseTable[] $tables
	 * @return string
	 */
	public function render( array $tables ) {
		$output = '';
		if( $this->inputComment ) {
			$outputJql = rtrim($this->input);
			$output .= "/*\n{$outputJql}\n*/\n\n";
		}

		$output .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
		foreach( $tables as $table ) {
			if( !$table->isIsPseudo() ) {
				if( $this->dropTables ) {
					$output .= "DROP TABLE IF EXISTS `" . $this->escape($table->getName()) . "`;\n";
				}
				$output .= $table->toString();
				$output .= "\n";
			}
		}
		$output .= "SET FOREIGN_KEY_CHECKS = 1;\n";

		return $output;
	}

	// @todo make availible from mysql-schema
	protected function escape( $input, $wrapChar = '`' ) {
		return str_replace($wrapChar, $wrapChar . $wrapChar, $input);
	}

}
