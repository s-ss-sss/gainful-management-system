<?php

// 名前空間
namespace App\Admin\Dao;

// ユーザーDAO共通ファイル
require_once ROOT_PATH . '/lib/sys/modules/daos/admin/BaseDao.class.php';

class SpecialDao extends BaseDao {

	/**
	 * 特別休暇一覧取得
	 *
	 * @access	public
	 * @param	$user_id, $year
	 * @return	array
	 */
	public function getSpecialList($user_id, $year) {

		// 取得年度から期間算出
		$period	= \Common::getFiscalPeriod($year);
		$start	= $period['start'];
		$end	= $period['end'];

		// 初期のWHERE句
		$where = [
			's.grant_date BETWEEN ? AND ?',
			"s.delete_flg	= '0'",
			"u.delete_flg	= '0'",
		];

		$params = [$start, $end];

		// ユーザー検索
		if (! empty($user_id)) {
			$where[]	= 's.user_id = ?';
			$params[]	= $user_id;
		}

		// WHERE句作成
		$where_sql = 'WHERE ' . implode(' AND ', $where);

		$sql = "
			SELECT
				s.id, s.user_id, s.sub_kind, s.grant_date,
				s.add_number, s.comment, u.name AS user_name,
				COALESCE(SUM(r.special_number), 0.0) AS used_number,
				CASE
					WHEN
						s.expire_date >= CURRENT_DATE THEN
						s.add_number - COALESCE(SUM(r.special_number), 0.0)
					ELSE
						0.0
				END AS remain_number
			FROM
				t_special s
			LEFT JOIN
				t_users u ON s.user_id = u.user_id
			LEFT JOIN
				t_request r ON
					r.special_id	= s.id AND
					r.delete_flg	= '0'
			{$where_sql}
			GROUP BY
				s.id, s.user_id, s.sub_kind, s.grant_date,
				s.add_number, s.comment, s.expire_date, u.name
			ORDER BY
				CAST(s.user_id AS UNSIGNED) ASC,
				s.grant_date ASC,
				s.id ASC
		";

		return $this->db->GetAll($sql, $params);
	}

	/**
	 * 特別休暇データ最古年度取得
	 *
	 * @access	public
	 * @param
	 * @return	int
	 */
	public function getSpecialOldestYear() {
		$sql = "
			SELECT
				MIN(s.grant_date) AS oldest
			FROM
				t_special s
			WHERE
				s.delete_flg = '0'
		";

		$row = $this->db->GetRow($sql);

		// 空の場合はnullを返す
		if (empty($row['oldest'])) {
			return null;
		}

		// 最古年度を算出
		return \Common::getFiscalYearFromDate($row['oldest']);
	}

	/**
	 * 特別休暇登録
	 *
	 * @access	public
	 * @param	$param
	 * @return
	 */
	public function insertSpecial($param) {
		$sql = "
			INSERT INTO t_special (
				user_id, sub_kind, grant_date, add_number, comment, expire_date
			) VALUES (
				?, ?, ?, ?, ?, ?
			)
		";

		$params = [
			$param['user_id'],
			$param['sub_type'],
			$param['grant_date'],
			$param['add_number'],
			$param['reason'],
			$param['expire_date'],
		];

		$this->db->Execute($sql, $params);
	}

	/**
	 * 特別休暇日数更新
	 *
	 * @access	public
	 * @param	$user_id, $add_number
	 * @return
	 */
	public function updateSpecial($user_id, $add_number) {
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

		$params = [
			$add_number,
			$user_id,
		];

		$this->db->Execute($sql, $params);
	}

	/**
	 * 特別休暇個別取得
	 *
	 * @access	public
	 * @param	$special_id
	 * @return
	 */
	public function getSpecialBySpecialId($special_id) {
		$sql = "
			SELECT
				s.id, s.user_id, s.sub_kind, s.grant_date, s.add_number, s.comment, s.expire_date,
				u.name AS user_name, COALESCE(SUM(r.special_number), 0.0) AS used_number,
				s.add_number - COALESCE(SUM(r.special_number), 0.0) AS remain_number
			FROM
				t_special s
			LEFT JOIN
				t_users u ON s.user_id = u.user_id
			LEFT JOIN
				t_request r ON
					r.special_id	= s.id AND
					r.delete_flg	= '0'
			WHERE
				s.id			= ? AND
				s.delete_flg	= '0' AND
				u.delete_flg	= '0'
			GROUP BY
				s.id, s.user_id, s.sub_kind, s.grant_date,
				s.add_number, s.comment, s.expire_date, u.name
		";

		return $this->db->GetRow($sql, [$special_id]);
	}

	/**
	 * 特別休暇ID存在チェック
	 *
	 * @access	public
	 * @param	$special_id
	 * @return	boolean
	 */
	public function existsSpecialId($special_id) {
		$sql = "
			SELECT
				COUNT(*) AS cnt
			FROM
				t_special s
			WHERE
				s.id			= ? AND
				s.delete_flg	= '0'
		";

		$row = $this->db->GetRow($sql, [$special_id]);

		// 1以上で存在する場合はtrueを返す
		return ((int)$row['cnt'] > 0);
	}

	/**
	 * 特別休暇日数修正
	 *
	 * @access	public
	 * @param	$special_id, $param
	 * @return	boolean
	 */
	public function updateSpecialById($special_id, $param) {

		$sets	= [];
		$params	= [];

		// 休日勤務日を追加
		if (isset($param['sub_type'])) {
			$sets[]		= 'sub_kind = ?';
			$params[]	= $param['sub_type'];
		}

		// 付与日を追加
		if (isset($param['grant_date'])) {
			$sets[]		= 'grant_date = ?';
			$params[]	= $param['grant_date'];
		}

		// 付与日数を追加
		if (isset($param['add_number'])) {
			$sets[]		= 'add_number = ?';
			$params[]	= $param['add_number'];
		}

		// 事由を追加
		if (isset($param['reason'])) {
			$sets[]		= 'comment = ?';
			$params[]	= $param['reason'];
		}

		// 有効期限を追加
		if (isset($param['expire_date'])) {
			$sets[]		= 'expire_date = ?';
			$params[]	= $param['expire_date'];
		}

		$sets[] = 'update_date = NOW()';

		$sql = "
			UPDATE
				t_special
			SET
				" . implode(', ', $sets) . "
			WHERE
				id			= ? AND
				delete_flg	= '0'
		";

		// 特別休暇IDを追加
		$params[] = $special_id;

		$this->db->Execute($sql, $params);
	}

	/**
	 * 特別休暇削除
	 *
	 * @access	public
	 * @param	$special_id
	 * @return
	 */
	public function deleteSpecial($special_id) {
		$sql = "
			UPDATE
				t_special
			SET
				delete_flg	= '1',
				update_date	= NOW()
			WHERE
				id = ?
		";

		$this->db->Execute($sql, [$special_id]);
	}
}
