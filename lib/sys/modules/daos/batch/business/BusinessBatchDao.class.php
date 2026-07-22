<?php

// 名前空間
namespace App\Batch\Business\Dao;

// 共通DAOファイル
require_once ROOT_PATH . '/lib/sys/modules/daos/CommonDao.class.php';

class BusinessBatchDao extends \CommonDao {

	/**
	 * コンストラクタ：DBアクセス
	 *
	 * @access	public
	 * @param	$db
	 * @return
	 */
	public function __construct($db) {
		parent::__construct($db);
	}

	// ============================================================
	// 有休付与
	// ============================================================

	/**
	 * 二重付与チェック
	 *
	 * @access	public
	 * @param	$user_id, $action_date
	 * @return	bool
	 */
	public function existsHolidayGrant($user_id, $action_date) {

		// action_type = '1'：有休付与
		$sql = "
			SELECT 1
			FROM
				t_holiday_history h
			WHERE
				h.user_id		= ? AND
				h.action_type	= '1' AND
				h.action_date	= ?
			LIMIT 1
		";

		$result = $this->db->GetOne($sql, [$user_id, $action_date]);

		return ! empty($result);
	}

	/**
	 * 有休履歴登録
	 *
	 * @access	public
	 * @param	$user_id, $action_date, $days
	 * @return
	 */
	public function insertHolidayGrant($user_id, $action_date, $days) {

		// action_type = '1'：有休付与
		$sql = "
			INSERT INTO t_holiday_history (
				user_id, action_type, action_date, number
			) VALUES (
				?, '1', ?, ?
			)
		";

		$this->db->Execute($sql, [$user_id, $action_date, $days]);
	}

	/**
	 * 有休残日数を更新
	 *
	 * @access	public
	 * @param	$user_id, $days
	 * @return
	 */
	public function addUserHoliday($user_id, $days) {
		$sql = "
			UPDATE
				t_users
			SET
				holiday_number	= holiday_number + ?,
				update_date		= NOW()
			WHERE
				user_id		= ? AND
				delete_flg	= '0'
		";

		$this->db->Execute($sql, [$days, $user_id]);
	}

	// ============================================================
	// 有休消滅
	// ============================================================

	/**
	 * 二重付与チェック
	 *
	 * @access	public
	 * @param	$user_id, $grant_date
	 * @return	bool
	 */
	public function existsHolidayExpire($user_id, $action_date) {
		$sql = "
			SELECT 1
			FROM
				t_holiday_history h
			WHERE
				h.user_id		= ? AND
				h.action_type	= '2' AND
				h.action_date	= ?
			LIMIT 1
		";

		$result = $this->db->GetOne($sql, [$user_id, $action_date]);

		return ! empty($result);
	}

	/**
	 * 消滅履歴を登録
	 *
	 * @access	public
	 * @param	$user_id, $action_date, $expire_days
	 * @return
	 */
	public function insertHolidayExpire($user_id, $action_date, $expire_days) {

		// action_type = '2'：有休消滅
		$sql = "
			INSERT INTO t_holiday_history (
				user_id, action_type, action_date, number
			) VALUES (
				?, '2', ?, ?
			)
		";

		$this->db->Execute($sql, [$user_id, $action_date, $expire_days]);
	}

	/**
	 * 有休残日数を更新
	 *
	 * @access	public
	 * @param	$user_id, $expire_days
	 * @return
	 */
	public function subtractUserHoliday($user_id, $expire_days) {
		$sql = "
			UPDATE
				t_users
			SET
				holiday_number	= holiday_number - ?,
				update_date		= NOW()
			WHERE
				user_id		= ? AND
				delete_flg	= '0'
		";

		$this->db->Execute($sql, [$expire_days, $user_id]);
	}

	/**
	 * 有効期限内に使用した有休申請日数を取得
	 *
	 * @access	public
	 * @param	$user_id , $expire_date
	 * @return	float
	 */
	public function getUsedHolidayDaysBefore($user_id, $expire_date) {
		$sql = "
			SELECT
				COALESCE(SUM(r.holiday_number), 0)
			FROM
				t_request r
			WHERE
				r.user_id			= ? AND
				r.kind				= '0' AND
				r.boss_result		= '0' AND
				r.manager_result	= '0' AND
				r.start_date		< ? AND
				r.delete_flg		= '0'
		";

		return (float)$this->db->GetOne($sql, [$user_id, $expire_date]);
	}

	/**
	 * 期限切れ後に使用した有休申請日数を取得
	 *
	 * @access	public
	 * @param	$user_id, $expire_date
	 * @return	float
	 */
	public function getFutureUsedHolidayDays($user_id, $expire_date) {
		$sql = "
			SELECT
				COALESCE(SUM(r.holiday_number), 0)
			FROM
				t_request r
			WHERE
				r.user_id			= ? AND
				r.kind				= '0' AND
				r.boss_result		= '0' AND
				r.manager_result	= '0' AND
				r.start_date 		>= ? AND
				r.delete_flg		= '0'
		";

		return (float)$this->db->GetOne($sql, [$user_id, $expire_date]);
	}

	// ============================================================
	// 特別休暇（リフレッシュ休暇）付与
	// ============================================================

	/**
	 * 二重付与チェック
	 *
	 * @access	public
	 * @param	$user_id, $action_date
	 * @return	bool
	 */
	public function existsSpecialGrant($user_id, $action_date) {
		$sql = "
			SELECT 1
			FROM
				t_special s
			WHERE
				s.user_id		= ? AND
				s.grant_date	= ? AND
				s.delete_flg	= '0'
			LIMIT 1
		";

		$result = $this->db->GetOne($sql, [$user_id, $action_date]);

		return ! empty($result);
	}

	/**
	 * 特別休暇履歴登録
	 *
	 * @access	public
	 * @param	$user_id, $action_date, $days, $comment, $expire_date
	 * @return
	 */
	public function insertSpecialGrant($user_id, $action_date, $days, $comment, $expire_date) {

		// sub_kind = '0'：リフレッシュ休暇
		$sql = "
			INSERT INTO t_special (
				user_id, sub_kind, grant_date,
				add_number, comment, expire_date
			) VALUES (
				?, '0', ?, ?, ?, ?
			)
		";

		$params = [
			$user_id,
			$action_date,
			$days,
			$comment,
			$expire_date
		];

		$this->db->Execute($sql, $params);
	}

	/**
	 * 特別休暇残日数を更新
	 *
	 * @access	public
	 * @param	$user_id, $days
	 * @return
	 */
	public function addUserSpecial($user_id, $days) {
		$sql = "
			UPDATE
				t_users
			SET
				special_number	= special_number + ?,
				update_date		= NOW()
			WHERE
				user_id		= ? AND
				delete_flg	= '0'
		";

		$this->db->Execute($sql, [$days, $user_id]);
	}

	// ============================================================
	// 特別休暇消滅
	// ============================================================

	/**
	 * 期限切れ特別休暇取得
	 *
	 * @access	public
	 * @param	$today
	 * @return	array
	 */
	public function getExpiredSpecials($today) {
		$sql = "
			SELECT
				s.id, s.user_id, s.add_number,
				COALESCE(SUM(r.special_number), 0.0) AS used_number,
				s.add_number - COALESCE(SUM(r.special_number), 0.0) AS remain_number
			FROM
				t_special s
			LEFT JOIN
				t_request r ON
					r.special_id = s.id AND
					r.delete_flg = '0'
			WHERE
				s.expire_date	< ? AND
				s.expire_flg	= '0' AND
				s.delete_flg	= '0'
			GROUP BY
				s.id, s.user_id, s.add_number
			HAVING
				s.add_number - COALESCE(SUM(r.special_number), 0.0) > 0
		";

		return $this->db->GetAll($sql, [$today]);
	}

	/**
	 * 特別休暇消滅フラグを更新
	 *
	 * @access	public
	 * @param	$special_id
	 * @return
	 */
	public function updateSpecialExpired($special_id) {
		$sql = "
			UPDATE
				t_special
			SET
				expire_flg	= '1',
				update_date	= NOW()
			WHERE
				id			= ? AND
				expire_flg	= '0' AND
				delete_flg	= '0'
		";

		$this->db->Execute($sql, [$special_id]);
	}

	/**
	 * 特別休暇残日数を更新
	 *
	 * @access	public
	 * @param	$user_id, $expire_days
	 * @return
	 */
	public function subtractUserSpecial($user_id, $expire_days) {
		$sql = "
			UPDATE
				t_users
			SET
				special_number	= special_number - ?,
				update_date		= NOW()
			WHERE
				user_id		= ? AND
				delete_flg	= '0'
		";

		$this->db->Execute($sql, [$expire_days, $user_id]);
	}

	// ============================================================
	// 共通処理
	// ============================================================

	/**
	 * 有効ユーザー一覧取得
	 *
	 * @access	public
	 * @param
	 * @return	array
	 */
	public function getActiveUsers() {
		$sql = "
			SELECT
				u.user_id, u.name, u.holiday_number, u.special_number, u.join_date
			FROM
				t_users u
			WHERE
				u.delete_flg = '0'
			ORDER BY
				u.user_id::INTEGER ASC
		";

		return $this->db->GetAll($sql);
	}
}
