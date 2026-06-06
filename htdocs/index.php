<?php

$root_path = realpath(__DIR__ . '/..');
require_once $root_path . '/lib/sys/common.php';
initApp($root_path);

// 認証クラスのインスタンス生成（依存性注入）
require_once ROOT_PATH . '/lib/sys/modules/daos/CommonDao.class.php';
require_once ROOT_PATH . '/lib/sys/modules/auth/Auth.class.php';
$commonDao	= new CommonDao($db);
$auth		= new Auth($commonDao);

// URLパラメータ
$action = getPathInfo(0) ?: 'apply';

// ログイン時とログアウト時以外はチェック
if (! in_array($action, ['login', 'logout'])) {
	$auth->checkLogin();
	$auth->checkSessionTimeout();
	$auth->setAdminFlag();
}

// ログイン処理
if ($action === 'login') {
	validPathInfo(1);
	$auth->displayLogin();
	exit;
}

// ログアウト処理
if ($action === 'logout') {
	validPathInfo(1);
	$auth->logout();
	exit;
}

// コントローラ一覧
$controllers = [
	'apply',
	'history',
	'status',
];

// コントローラ分岐
if (in_array($action, $controllers, true)) {
	require_once ROOT_PATH . "/lib/sys/controllers/user/{$action}.php";
} else {
	displayNotFound();
}
