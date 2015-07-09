<?php

namespace donatj\Misstep;

use donatj\MySqlSchema\Table;

class GenerationTable extends Table {

	/**
	 * @var bool
	 */
	protected $isPseudo = false;

	/**
	 * @return boolean
	 */
	public function isIsPseudo() {
		return $this->isPseudo;
	}

	/**
	 * @param boolean $isPseudo
	 */
	public function setIsPseudo( $isPseudo ) {
		$this->isPseudo = $isPseudo;
	}

}
