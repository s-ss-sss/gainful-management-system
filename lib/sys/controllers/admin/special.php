<?php

require_once ROOT_PATH . '/lib/sys/modules/daos/admin/SpecialDao.class.php';
require_once ROOT_PATH . '/lib/sys/modules/admin/Special.class.php';

// 名前空間
use App\Admin\Dao\SpecialDao;
use App\Admin\Special;

// インスタンス生成（依存性注入）
$specialDao	= new SpecialDao($db);
$special	= new Special($specialDao);

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
			case 'create'	: $special->createSpecial();				break;	// 実行
			default			: $special->displaySpecialCreateIndex();	break;	// フォーム
		}
		break;

	// 修正
	case 'edit':
		validPathInfo(3);
		switch ($state) {
			case 'edit'	: $special->editSpecial();						break;	// 実行
			default		: $special->displaySpecialEditIndex($path3);	break;	// フォーム
		}
		break;

	// 一覧
	default:
		validPathInfo(1);
		switch ($state) {
			case 'delete'	: $special->deleteSpecial();		break;	// 削除
			default			: $special->displaySpecialIndex();	break;	// 一覧
		}
		break;
}
