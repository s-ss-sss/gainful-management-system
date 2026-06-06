<?php

require_once ROOT_PATH . '/lib/sys/modules/daos/admin/StatusDao.class.php';
require_once ROOT_PATH . '/lib/sys/modules/admin/Status.class.php';

// 名前空間
use App\Admin\Dao\StatusDao;
use App\Admin\Status;

// インスタンス生成（依存性注入）
$statusDao	= new StatusDao($db);
$status		= new Status($statusDao);

// POSTパラメータ
$state = $_POST['state'] ?? 'default';

// パスチェック
validPathInfo(1);

switch ($state) {

	// 取消
	case 'cancel':
		$status->cancelApproval();
		break;

	// 一覧
	default:
		$status->displayStatusIndex();
		break;
}
