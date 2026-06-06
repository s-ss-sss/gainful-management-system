<?php

// 名前空間
namespace App\Batch\Demo\Dao;

class DemoBatchDao {

	private $db;

	/**
	 * コンストラクタ：DBアクセス
	 *
	 * @access	public
	 * @param	$db
	 * @return
	 */
	public function __construct($db) {
		$this->db = $db;
	}

	// ============================================================
	// 共通処理
	// ============================================================

	/**
	 * トランザクション開始
	 *
	 * @access	public
	 * @param
	 * @return
	 */
	public function BeginTrans() {
		$this->db->BeginTrans();
	}

	/**
	 * コミット
	 *
	 * @access	public
	 * @param
	 * @return
	 */
	public function CommitTrans() {
		$this->db->CommitTrans();
	}

	/**
	 * ロールバック
	 *
	 * @access	public
	 * @param
	 * @return
	 */
	public function RollbackTrans() {
		$this->db->RollbackTrans();
	}

	// ============================================================
	// デモデータ削除
	// ============================================================

	/**
	 * デモデータ削除
	 *
	 * @access	public
	 * @param
	 * @return
	 */
	public function deleteDemoData() {

		// 外部キー無効化
		$this->db->Execute('SET FOREIGN_KEY_CHECKS = 0');

		// 削除対象テーブル
		$tables = [
			't_compday',			// 代休データ
			't_request',			// 休暇申請データ
			't_special',			// 特別休暇データ
			't_holiday_history',	// 有休履歴データ
			't_users',				// ユーザー宛先データ
		];

		try {

			// 削除実行
			foreach ($tables as $table) {
				if ($this->db->Execute("TRUNCATE TABLE {$table}") === false) {
					throw new Exception("Failed to truncate {$table}");
				}
			}

		} finally {

			// 外部キー有効化
			$this->db->Execute('SET FOREIGN_KEY_CHECKS = 1');
		}
	}

	// ============================================================
	// デモデータ再投入
	// ============================================================

	/**
	 * デモデータ再投入
	 *
	 * @access	public
	 * @param	$today
	 * @return	array
	 */
	public function insertDemoSeedData($today) {

		// デモデータ投入
		$users		= $this->_insertUsers($today);		// ユーザーデータ
		$special	= $this->_insertSpecial($today);	// 特別休暇データ
		$request	= $this->_insertRequest($today);	// 休暇申請データ
		$compday	= $this->_insertCompday($today);	// 代休データ

		return [
			'users'		=> $users,
			'special'	=> $special,
			'request'	=> $request,
			'compday'	=> $compday,
		];
	}

	/**
	 * ユーザーデータ投入
	 *
	 * @access	private
	 * @param	$today
	 * @return	$count
	 */
	private function _insertUsers($today) {

		// 今月の1日を取得
		$first_day = $today->modify('first day of this month');

		// 投入するユーザーデータ
		$users = [
			[1, 'デモユーザー', $first_day->format('Y-m-d'), 0, MAIL_DEMO, password_hash(DEMO_PASSWORD, PASSWORD_DEFAULT), 10.0, 1.0, 5.0, 2],
			[2, '社長', $first_day->modify('-2 years')->format('Y-m-d'), 0, MAIL_ADMIN, password_hash(ADMIN_PASSWORD, PASSWORD_DEFAULT), 0.0, 0.0, 0.0, 2],
			[3, '所属長', $first_day->modify('-1 year')->format('Y-m-d'), 0, $this->_buildAliasMail(MAIL_ADMIN, '1'), password_hash(ADMIN_PASSWORD, PASSWORD_DEFAULT), 0.0, 0.0, 0.0, 1],
			[4, '一般社員', $first_day->format('Y-m-d'), 0, $this->_buildAliasMail(MAIL_ADMIN, '2'), password_hash(ADMIN_PASSWORD, PASSWORD_DEFAULT), 10.0, 1.0, 5.0, 0],
		];

		// SQL文
		$sql = '
			INSERT INTO t_users (
				user_id, name, join_date, auth, mail, password,
				holiday_number, compday_number, special_number, position
			) VALUES (
				?, ?, ?, ?, ?, ?, ?, ?, ?, ?
			)
		';

		// データ投入
		$count = 0;
		foreach ($users as $user) {
			if ($this->db->Execute($sql, $user) === false) {
				throw new Exception('Failed to insert user');
			}
			$count++;
		}

		// 投入件数を返す
		return $count;
	}

	/**
	 * メールアドレスのエイリアス化
	 *
	 * @access	private
	 * @param	$mail, $suffix
	 * @return	string
	 */
	private function _buildAliasMail($mail, $suffix) {

		// @でメールアドレスを分割
		[$local, $domain] = explode('@', $mail);

		// aaa+b@cccの形式でエイリアス化
		return "{$local}+{$suffix}@{$domain}";
	}

	/**
	 * 特別休暇データ投入
	 *
	 * @access	private
	 * @param	$today
	 * @return	$count
	 */
	private function _insertSpecial($today) {

		// 今月の1日を取得
		$first_day = $today->modify('first day of this month');

		// 投入する特別休暇データ
		$specials = [
			[1, 1, $first_day->format('Y-m-d'), 5.0, '結婚休暇', $first_day->modify('+6 months')->format('Y-m-d')],
			[4, 1, $first_day->format('Y-m-d'), 5.0, '結婚休暇', $first_day->modify('+6 months')->format('Y-m-d')],
		];

		// SQL文
		$sql = '
			INSERT INTO t_special (
				user_id, sub_kind, grant_date, add_number, comment, expire_date
			) VALUES (
				?, ?, ?, ?, ?, ?
			)
		';

		// データ投入
		$count = 0;
		foreach ($specials as $special) {
			if ($this->db->Execute($sql, $special) === false) {
				throw new Exception('Failed to insert special');
			}
			$count++;
		}

		// 投入件数を返す
		return $count;
	}

	/**
	 * 休暇申請データ投入
	 *
	 * @access	private
	 * @param	$today
	 * @return	$count
	 */
	private function _insertRequest($today) {

		// 今月の1日を取得
		$first_day = $today->modify('first day of this month');

		// 投入する休暇申請データ
		$requests = [
			[4, bin2hex(random_bytes(16)), $first_day->format('Y-m-d'), 0, $first_day->format('Y-m-d'), 1, 0, '体調不良', 1.0],
		];

		// SQL文
		$sql = '
			INSERT INTO t_request (
				user_id, bundle_id, start_date, start_am_pm,
				end_date, end_am_pm, kind, comment, holiday_number
			) VALUES (
				?, ?, ?, ?, ?, ?, ?, ?, ?
			)
		';

		// データ投入
		$count = 0;
		foreach ($requests as $request) {
			if ($this->db->Execute($sql, $request) === false) {
				throw new Exception('Failed to insert request');
			}
			$count++;
		}

		// 投入件数を返す
		return $count;
	}

	/**
	 * 代休データ投入
	 *
	 * @access	private
	 * @param	$today
	 * @return	$count
	 */
	private function _insertCompday($today) {

		// 今月の2週目の日曜日を取得
		$first_day		= $today->modify('first day of this month');
		$second_sunday	= $first_day->modify('second sunday of this month');

		// 投入する代休データ
		$compdays = [
			[1, $second_sunday->format('Y-m-d'), 1.0, '休日出勤'],
			[4, $second_sunday->format('Y-m-d'), 1.0, '休日出勤'],
		];

		// SQL文
		$sql = '
			INSERT INTO t_compday (
				user_id, work_date, add_number, comment
			) VALUES (
				?, ?, ?, ?
			)
		';

		// データ投入
		$count = 0;
		foreach ($compdays as $compday) {
			if ($this->db->Execute($sql, $compday) === false) {
				throw new Exception('Failed to insert compday');
			}
			$count++;
		}

		// 投入件数を返す
		return $count;
	}
}
