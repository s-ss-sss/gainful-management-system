<?php

// 名前空間
namespace App\Admin;

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

		// 管理者チェック
		$this->_checkAdmin();
	}

	/**
	 * 管理者チェック
	 *
	 * @access	private
	 * @param
	 * @return
	 */
	private function _checkAdmin() {

		// ユーザーデータ取得
		$user = $this->getLoginUser();

		// 所属長/社長の権限
		$positions = ['1', '2'];

		// ユーザーデータが空か権限がない場合はユーザー画面にリダイレクト
		if (empty($user) || ! in_array($user['position'], $positions, true)) {
			header('Location: ' . BASE_URL);
			exit;
		}
	}

	/**
	 * 社長権限チェック
	 *
	 * @access	protected
	 * @param
	 * @return
	 */
	protected function checkManager() {

		// ユーザーデータ取得
		$user = $this->getLoginUser();

		// ユーザーデータが空か社長権限がない場合は一覧画面にリダイレクト
		if (empty($user) || $user['position'] !== '2') {
			header('Location: ' . BASE_URL . 'admin/');
			exit;
		}
	}

	/**
	 * 日付データ整形：年/月/日（曜日）
	 *
	 * @access	protected
	 * @param	$date
	 * @return	"{$date}（{$weekday}）"
	 */
	protected function formatAdminDateYmdLabel($date) {
		$timestamp	= strtotime($date);
		$weekday	= \Common::$weekdays[date('w', $timestamp)];
		$date		= date('Y/n/j', $timestamp);
		return "{$date}（{$weekday}）";
	}

	/**
	 * 日付データ整形：月/日（曜日）
	 *
	 * @access	protected
	 * @param	$date
	 * @return	"{$date}（{$weekday}）"
	 */
	protected function formatAdminDateMdLabel($date) {
		$timestamp	= strtotime($date);
		$weekday	= \Common::$weekdays[date('w', $timestamp)];
		$date		= date('n/j', $timestamp);
		return "{$date}（{$weekday}）";
	}
}
