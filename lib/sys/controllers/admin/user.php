<?php

require_once ROOT_PATH . '/lib/sys/modules/daos/admin/UserDao.class.php';
require_once ROOT_PATH . '/lib/sys/modules/admin/User.class.php';

// 名前空間
use App\Admin\Dao\UserDao;
use App\Admin\User;

// インスタンス生成（依存性注入）
$userDao	= new UserDao($db);
$user		= new User($userDao);

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
			case 'create'	: $user->createUser();				break;	// 実行
			default			: $user->displayUserCreateIndex();	break;	// フォーム
		}
		break;

	// 修正
	case 'edit':
		validPathInfo(3);
		switch ($state) {
			case 'edit'	: $user->editUser();					break;	// 実行
			default		: $user->displayUserEditIndex($path3);	break;	// フォーム
		}
		break;

	// 一覧
	default:
		validPathInfo(1);
		switch ($state) {
			case 'delete'	: $user->deleteUsers();			break;	// 削除
			default			: $user->displayUserIndex();	break;	// 一覧
		}
		break;
}
