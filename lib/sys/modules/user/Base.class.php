<?php

// 名前空間
namespace App\User;

require_once ROOT_PATH . '/lib/sys/modules/Common.class.php';

class Base extends \Common {

	/**
	 * コンストラクタ：インスタンス生成
	 *
	 * @access	public
	 * @param	$dao
	 * @return
	 */
	public function __construct($dao) {
		parent::__construct($dao);
	}
}
