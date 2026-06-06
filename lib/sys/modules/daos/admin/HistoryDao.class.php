<?php

// 名前空間
namespace App\Admin\Dao;

// ユーザーDAO共通ファイル
require_once ROOT_PATH . '/lib/sys/modules/daos/admin/BaseDao.class.php';

class HistoryDao extends BaseDao {

	/**
	 * 休暇実績一覧取得
	 *
	 * @access	public
	 * @param	$user_id, $from, $to
	 * @return	array
	 */
	public function getHistoryListByPeriod($user_id, $from, $to) {

		// 初期のWHERE句
		$where = [
			'r.start_date BETWEEN ? AND ?',
			"r.boss_result		= '0'",
			"r.manager_result	= '0'",
			"r.delete_flg		= '0'",
			"u.delete_flg		= '0'",
		];

		$params = [$from, $to];

		// ユーザー検索
		if (! empty($user_id)) {
			$where[]	= 'r.user_id = ?';
			$params[]	= $user_id;
		}

		// WHERE句作成
		$where_sql = 'WHERE ' . implode(' AND ', $where);

		$sql = "
			SELECT
				r.id, r.user_id, r.bundle_id, r.start_date, r.start_am_pm, r.end_date,
				r.end_am_pm, r.kind, r.sub_kind, r.comment, r.holiday_number, r.compday_number,
				r.special_number, r.boss_result, r.manager_result, u.name AS user_name
			FROM
				t_request r
			LEFT JOIN
				t_users u ON r.user_id = u.user_id
			{$where_sql}
			ORDER BY
				CAST(r.user_id AS UNSIGNED) ASC,
				r.start_date ASC,
				r.start_am_pm ASC,
				r.end_am_pm ASC,
				r.id ASC
		";

		return $this->db->GetAll($sql, $params);
	}

	/**
	 * 休暇実績最古年度取得
	 *
	 * @access	public
	 * @param
	 * @return	array
	 */
	public function getHistoryOldestYm() {

		$sql = "
			SELECT
				MIN(r.start_date) AS oldest
			FROM
				t_request r
			WHERE
				r.delete_flg = '0'
		";

		$row = $this->db->GetRow($sql);

		// 空の場合はnullを返す
		if (empty($row['oldest'])) {
			return null;
		}

		// 日付から締日年月を返す（21〜20日）
		return \Common::getClosingYearMonth($row['oldest']);
	}
}
