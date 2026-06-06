<?php

require_once ROOT_PATH . '/lib/sys/modules/daos/user/ApplyDao.class.php';
require_once ROOT_PATH . '/lib/sys/modules/user/Apply.class.php';

// 名前空間
use App\User\Dao\ApplyDao;
use App\User\Apply;

// インスタンス生成（依存性注入）
$applyDao	= new ApplyDao($db);
$apply		= new Apply($applyDao);

// POSTパラメータ
$state = $_POST['state'] ?? 'default';

// パスチェック
validPathInfo(1);

switch ($state) {

	// 確認
	case 'confirm':
		$apply->displayApplyConfirm();
		break;

	// 完了
	case 'complete':
		$apply->displayApplyComplete();
		break;

	// 一覧
	default:
		$apply->displayApplyIndex();
		break;
}
