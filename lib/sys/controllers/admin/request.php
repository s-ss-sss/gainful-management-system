<?php

require_once ROOT_PATH . '/lib/sys/modules/daos/admin/RequestDao.class.php';
require_once ROOT_PATH . '/lib/sys/modules/admin/Request.class.php';

// 名前空間
use App\Admin\Dao\RequestDao;
use App\Admin\Request;

// インスタンス生成（依存性注入）
$requestDao	= new RequestDao($db);
$request	= new Request($requestDao);

// URLパラメータ
$path2	= getPathInfo(1);
$path3	= getPathInfo(2);

// POSTパラメータ
$state = $_POST['state'] ?? 'default';

switch ($path2) {

	// 登録
	case 'create':
		validPathInfo(3);
		switch ($state) {
			case 'create'	: $request->createRequest();					break;	// 実行
			default			: $request->displayRequestCreateIndex($path3);	break;	// フォーム
		}
		break;

	// 修正
	case 'edit':
		validPathInfo(3);
		switch ($state) {
			case 'edit'	: $request->editRequest();						break;	// 実行
			default		: $request->displayRequestEditIndex($path3);	break;	// フォーム
		}
		break;

	// 一覧
	default:
		validPathInfo(1);
		switch ($state) {
			case 'delete'	: $request->deleteRequests();		break;	// 削除
			default			: $request->displayRequestIndex();	break;	// 一覧
		}
		break;
}
