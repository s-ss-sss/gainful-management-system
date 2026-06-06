<?php

// 名前空間
namespace App\User\Dao;

// ユーザーDAO共通ファイル
require_once ROOT_PATH . '/lib/sys/modules/daos/user/BaseDao.class.php';

class HistoryDao extends BaseDao {

	/**
	 * 休暇実績一覧取得
	 *
	 * @access	public
	 * @param	$user_id, $year
	 * @return	array
	 */
	public function getHistoryByYear($user_id, $year) {

		// 取得年度から期間算出
		$period	= \Common::getFiscalPeriod($year);
		$start	= $period['start'];
		$end	= $period['end'];

		$sql = "
			SELECT
				r.id, r.user_id, u.name AS user_name, r.bundle_id, r.start_date,
				r.start_am_pm, r.end_date, r.end_am_pm, r.kind, r.sub_kind,
				r.comment, r.holiday_number, r.compday_number, r.special_number
			FROM
				t_request r
			INNER JOIN t_users u ON r.user_id = u.user_id
			WHERE
				r.user_id			= ? AND
				r.boss_result		= '0' AND
				r.manager_result	= '0' AND
				r.delete_flg		= '0' AND
				r.start_date BETWEEN ? AND ?
			ORDER BY
				r.start_date ASC,
				r.start_am_pm ASC,
				r.id ASC
		";

		return $this->db->GetAll($sql, [$user_id, $start, $end]);
	}

	/**
	 * 休暇実績最古年度取得
	 *
	 * @access	public
	 * @param	$user_id
	 * @return	int
	 */
	public function getOldestYear($user_id) {

		$sql = "
			SELECT
				MIN(r.start_date) AS oldest
			FROM
				t_request r
			WHERE
				r.user_id			= ? AND
				r.boss_result		= '0' AND
				r.manager_result	= '0' AND
				r.delete_flg		= '0'
		";

		$row = $this->db->GetRow($sql, [$user_id]);

		// 空の場合はnullを返す
		if (empty($row['oldest'])) {
			return null;
		}

		// 最古年度を算出
		return \Common::getFiscalYearFromDate($row['oldest']);
	}
}
