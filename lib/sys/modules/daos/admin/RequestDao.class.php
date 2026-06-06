<?php

// 名前空間
namespace App\Admin\Dao;

// ユーザーDAO共通ファイル
require_once ROOT_PATH . '/lib/sys/modules/daos/admin/BaseDao.class.php';

class RequestDao extends BaseDao {

	/**
	 * 申請データ一覧取得
	 *
	 * @access	public
	 * @param	$user_id, $year
	 * @return	array
	 */
	public function getApprovedRequests($user_id, $year) {

		// 取得年度から期間算出
		$period	= \Common::getFiscalPeriod($year);
		$start	= $period['start'];
		$end	= $period['end'];

		// 初期のWHERE句
		$where = [
			'r.start_date BETWEEN ? AND ?',
			"r.boss_result		= '0'",
			"r.manager_result	= '0'",
			"r.delete_flg		= '0'",
			"u.delete_flg		= '0'",
		];

		$params = [$start, $end];

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
	 * 申請データ最古年度取得
	 *
	 * @access	public
	 * @param
	 * @return	int
	 */
	public function getRequestOldestYear() {

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

		// 最古年度を算出
		return \Common::getFiscalYearFromDate($row['oldest']);
	}

	/**
	 * 申請データ登録
	 *
	 * @access	public
	 * @param	$param
	 * @return	Insert_ID()
	 */
	public function insertRequest($param) {
		$sql = '
			INSERT INTO t_request (
				user_id, bundle_id, start_date, start_am_pm, end_date,
				end_am_pm, kind, sub_kind, comment, boss_result, manager_result
			) VALUES (
				?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
			)
		';

		$params = [
			$param['user_id'],
			$param['bundle_id'],
			$param['start_date'],
			$param['start_am_pm'],
			$param['end_date'],
			$param['end_am_pm'],
			$param['kind'],
			$param['sub_kind'],
			$param['comment'],
			0,
			0,
		];

		$this->db->Execute($sql, $params);

		// 登録したレコードIDを返す
		return $this->db->Insert_ID();
	}

	/**
	 * 消費日数更新
	 *
	 * @access	public
	 * @param	$param
	 * @return
	 */
	public function updateRequestConsumedDays($param) {
		$sql = '
			UPDATE
				t_request
			SET
				holiday_number	= ?,
				compday_number	= ?,
				special_number	= ?
			WHERE
				id = ?
		';

		$params = [
			$param['holiday_number'],
			$param['compday_number'],
			$param['special_number'],
			$param['id'],
		];

		$this->db->Execute($sql, $params);
	}

	/**
	 * 申請データ削除
	 *
	 * @access	public
	 * @param	$bundle_id
	 * @return
	 */
	public function deleteRequest($bundle_id) {
		$sql = "
			UPDATE
				t_request
			SET
				delete_flg	= '1',
				update_date	= NOW()
			WHERE
				bundle_id = ?
		";

		$this->db->Execute($sql, [$bundle_id]);
	}

	/**
	 * bundle_id存在チェック
	 *
	 * @access	public
	 * @param	$bundle_id
	 * @return	boolean
	 */
	public function existsBundleId($bundle_id) {
		$sql = "
			SELECT
				COUNT(*) AS cnt
			FROM
				t_request r
			WHERE
				r.bundle_id		= ? AND
				r.delete_flg	= '0'
		";

		$row = $this->db->GetRow($sql, [$bundle_id]);

		// 1以上で存在する場合はtrueを返す
		return ((int)$row['cnt'] > 0);
	}

	/**
	 * 代休リンク解除
	 *
	 * @access	public
	 * @param	$request_id
	 * @return
	 */
	public function unlinkCompdayLink($request_id) {
		$sql = "
			UPDATE
				t_compday
			SET
				link_request_id1	= NULLIF(link_request_id1, ?),
				link_request_id2	= NULLIF(link_request_id2, ?),
				update_date			= NOW()
			WHERE
				link_request_id1	= ? OR
				link_request_id2	= ?
		";

		$this->db->Execute($sql, [$request_id, $request_id, $request_id, $request_id]);
	}

	/**
	 * 指定bundle_idの特別休暇消費日数取得
	 *
	 * @access	public
	 * @param	$bundle_id, $sub_type
	 * @return	string
	 */
	public function getSpecialConsumedDaysByBundleId($bundle_id, $sub_type) {
		$sql = "
			SELECT
				COALESCE(SUM(special_number), 0.0)
			FROM
				t_request r
			WHERE
				r.bundle_id		= ? AND
				r.sub_kind		= ? AND
				r.delete_flg	= '0'
		";

		return (float)$this->db->GetOne($sql, [$bundle_id, $sub_type]);
	}
}
