<?php

require_once ROOT_PATH . '/lib/sys/modules/daos/admin/CompdayDao.class.php';
require_once ROOT_PATH . '/lib/sys/modules/admin/Compday.class.php';

// 名前空間
use App\Admin\Dao\CompdayDao;
use App\Admin\Compday;

// インスタンス生成（依存性注入）
$compdayDao	= new CompdayDao($db);
$compday	= new Compday($compdayDao);

// URLパラメータ
$path2	= getPathInfo(1);
$path3	= getPathInfo(2);

// POSTパラメータ
$state = $_POST['state'] ?? 'default';

switch ($path2) {

	// 登録
	case 'create':
		validPathInfo(2);
		switch ($state) {
			case 'create'	: $compday->createCompday();				break;	// 実行
			default			: $compday->displayCompdayCreateIndex();	break;	// フォーム
		}
		break;

	// 修正
	case 'edit':
		validPathInfo(3);
		switch ($state) {
			case 'edit'	: $compday->editCompday();						break;	// 実行
			default		: $compday->displayCompdayEditIndex($path3);	break;	// フォーム
		}
		break;

	// 一覧
	default:
		validPathInfo(1);
		switch ($state) {
			case 'delete'	: $compday->deleteCompday();		break;	// 削除
			default			: $compday->displayCompdayIndex();	break;	// 一覧
		}
		break;
}
