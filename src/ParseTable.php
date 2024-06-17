<?php

namespace donatj\Misstep;

use donatj\MySqlSchema\Table;

class ParseTable extends Table {

	protected bool $isPseudo = false;

	public function isIsPseudo() : bool {
		return $this->isPseudo;
	}

	public function setIsPseudo( bool $isPseudo ) : void {
		$this->isPseudo = $isPseudo;
	}

}
