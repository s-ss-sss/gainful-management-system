<?php

$root_path = realpath(__DIR__ . '/../..');
require_once $root_path . '/lib/sys/common.php';
initApp($root_path);

// 認証クラスのインスタンス生成（依存性注入）
require_once ROOT_PATH . '/lib/sys/modules/daos/CommonDao.class.php';
require_once ROOT_PATH . '/lib/sys/modules/auth/Auth.class.php';
$commonDao	= new CommonDao($db);
$auth		= new Auth($commonDao);

// URLパラメータ
$action = getPathInfo(0) ?: 'approval';

// ログアウト時以外はチェック
if (! in_array($action, ['logout'])) {
	$auth->checkLogin();
	$auth->checkSessionTimeout();
	$auth->setAdminFlag();
}

// ログアウト処理
if ($action === 'logout') {
	validPathInfo(1);
	$auth->logout();
	exit;
}

// コントローラ一覧
$controllers = [
	'approval'	=> 'approval',
	'reject'	=> 'approval',
	'status'	=> 'status',
	'user'		=> 'user',
	'request'	=> 'request',
	'compday'	=> 'compday',
	'special'	=> 'special',
	'history'	=> 'history',
];

// コントローラ分岐
if (isset($controllers[$action])) {
	require_once ROOT_PATH . "/lib/sys/controllers/admin/{$controllers[$action]}.php";
} else {
	displayNotFound();
}
