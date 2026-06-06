<?php

/**
 * 共通初期化
 *
 * @access	public
 * @param	$root_path
 * @return
 */
function initApp($root_path) {
	global $smarty;

	// エラーコード出力
	ini_set('display_errors', 1);
	error_reporting(E_ALL);

	// タイムゾーン設定
	date_default_timezone_set('Asia/Tokyo');

	// ROOT_PATH定義
	define('ROOT_PATH', $root_path);

	// 共通設定読み込み
	require_once ROOT_PATH . '/lib/conf/Common.conf.php';

	// エラーログファイル定義
	define('PHP_ERROR_LOG', BASE_DIR . 'logs/error.log');

	// カスタムエラーハンドラ
	set_error_handler(function ($errno, $errstr, $errfile, $errline) {
		global $smarty;

		// ログメッセージ
		$message = "[Error {$errno}] {$errstr} in {$errfile} on line {$errline}\n";

		// 重大エラーはログ出力
		error_log($message, 3, PHP_ERROR_LOG);

		// Smartyに警告を渡す
		if (in_array($errno, [E_USER_WARNING])) {
			$smarty->append('warning', $errstr);
		}

		// NOTICE系はスルー
		return true;
	});

	// DB接続
	initDB();

	// セッション開始
	session_start();

	// Smarty初期化
	initSmarty();

	// サニタイズ処理
	$_GET		= sanitize($_GET);
	$_POST		= sanitize($_POST);
	$_COOKIE	= sanitize($_COOKIE);
}

/**
 * DB初期化
 *
 * @access	public
 * @param
 * @return
 */
function initDB() {
	global $db;
	require_once(BASE_DIR . 'packages/adodb5/adodb.inc.php');
	$db = NewADOConnection('mysqli');
	$db->Connect(DB_HOST, DB_USER, DB_PASS, DB_DATABASE);
	$db->Execute("SET NAMES utf8mb4");
}

/**
 * Smarty初期化
 *
 * @access	public
 * @param
 * @return
 */
function initSmarty() {
	global $smarty;
	require_once BASE_DIR . '/packages/smarty/libs/Smarty.class.php';
	$smarty = new Smarty();
	$smarty->setTemplateDir(BASE_DIR . '/lib/templates/');
	$smarty->setCompileDir(BASE_DIR . '/lib/templates_c/');
	$smarty->default_modifiers = ['escape:"html"'];
}

/**
 * サニタイズ処理
 *
 * @access	public
 * @param	$o
 * @return
 */
function sanitize($o) {
	if (is_array($o)) {
		return array_map('sanitize', $o);
	}
	return str_replace('\0', '', $o);
}

/**
 * 全角スペースを含めたtrim
 *
 * @access	public
 * @param	$str
 * @return
 */
function trimFull($str) {
	return preg_replace('/^[\s　]+|[\s　]+$/u', '', $str ?? '');
}

/**
 * パス情報を配列で取得
 *
 * @access	public
 * @param	$index = null
 * @return	is_numeric($index) ? ($path[$index] ?? null) : $path
 */
function getPathInfo($index = null) {
	$path = explode('/', trim($_SERVER['PATH_INFO'] ?? '', '/'));
	return is_numeric($index) ? ($path[$index] ?? null) : $path;
}

/**
 * PATH_INFOの階層チェック
 *
 * @access	public
 * @param	$path_level
 * @return
 */
function validPathInfo($path_level) {
	if (count(getPathInfo()) !== $path_level) {
		displayNotFound();
	}
}

/**
 * 404エラー
 *
 * @access	public
 * @param
 * @return
 */
function displayNotFound() {
	header('HTTP/1.1 404 Not Found');
	echo '404 Not Found';
	exit;
}
