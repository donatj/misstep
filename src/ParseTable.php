<?php

namespace donatj\Misstep;

use donatj\MySqlSchema\Table;

class ParseTable extends Table {

	/** @var bool */
	protected $isPseudo = false;

	/**
	 * @return bool
	 */
	public function isIsPseudo() {
		return $this->isPseudo;
	}

	/**
	 * @param bool $isPseudo
	 */
	public function setIsPseudo( $isPseudo ) {
		$this->isPseudo = $isPseudo;
	}

}
