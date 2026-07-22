<?php

// 名前空間
namespace App\Admin\Dao;

// ユーザーDAO共通ファイル
require_once ROOT_PATH . '/lib/sys/modules/daos/admin/BaseDao.class.php';

class CompdayDao extends BaseDao {

	/**
	 * 代休一覧取得
	 *
	 * @access	public
	 * @param	$user_id, $year
	 * @return	array
	 */
	public function getCompdays($user_id, $year) {

		// 取得年度から期間算出
		$period	= \Common::getFiscalPeriod($year);
		$start	= $period['start'];
		$end	= $period['end'];

		// 初期のWHERE句
		$where = [
			'c.work_date BETWEEN ? AND ?',
			"c.delete_flg	= '0'",
			"u.delete_flg	= '0'",
		];

		$params = [$start, $end];

		// ユーザー検索
		if (! empty($user_id)) {
			$where[]	= 'c.user_id = ?';
			$params[]	= $user_id;
		}

		// WHERE句作成
		$where_sql = 'WHERE ' . implode(' AND ', $where);

		$sql = "
			SELECT
				c.id, c.user_id, c.work_date, c.add_number, c.comment,
				c.link_request_id1, c.link_request_id2, u.name AS user_name
			FROM
				t_compday c
			LEFT JOIN
				t_users u ON c.user_id = u.user_id
			{$where_sql}
			ORDER BY
				CAST(c.user_id AS UNSIGNED) ASC,
				c.work_date ASC,
				c.id ASC
		";

		return $this->db->GetAll($sql, $params);
	}

	/**
	 * 代休データ最古年度取得
	 *
	 * @access	public
	 * @param
	 * @return	int
	 */
	public function getCompdayOldestYear() {
		$sql = "
			SELECT
				MIN(c.work_date) AS oldest
			FROM
				t_compday c
			WHERE
				c.delete_flg = '0'
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
	 * 代休登録
	 *
	 * @access	public
	 * @param	$param
	 * @return
	 */
	public function insertCompday($param) {
		$sql = "
			INSERT INTO t_compday (
				user_id, work_date, add_number, comment
			) VALUES (
				?, ?, ?, ?
			)
		";

		$params = [
			$param['user_id'],
			$param['work_date'],
			$param['add_number'],
			$param['reason'],
		];

		$this->db->Execute($sql, $params);
	}

	/**
	 * 代休日数更新
	 *
	 * @access	public
	 * @param	$user_id, $add_number
	 * @return
	 */
	public function updateCompday($user_id, $add_number) {
		$sql = "
			UPDATE
				t_users
			SET
				compday_number	= compday_number + ?,
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
	 * 代休個別取得
	 *
	 * @access	public
	 * @param	$compday_id
	 * @return
	 */
	public function getCompdayByCompdayId($compday_id) {
		$sql = "
			SELECT
				c.id, c.user_id, c.work_date, c.add_number, c.comment,
				c.link_request_id1, c.link_request_id2, u.name AS user_name
			FROM
				t_compday c
			LEFT JOIN
				t_users u ON c.user_id = u.user_id
			WHERE
				c.id			= ? AND
				c.delete_flg	= '0' AND
				u.delete_flg	= '0'
		";

		return $this->db->GetRow($sql, [$compday_id]);
	}

	/**
	 * 代休ID存在チェック
	 *
	 * @access	public
	 * @param	$compday_id
	 * @return	boolean
	 */
	public function existsCompdayId($compday_id) {
		$sql = "
			SELECT
				COUNT(*) AS cnt
			FROM
				t_compday c
			WHERE
				c.id			= ? AND
				c.delete_flg	= '0'
		";

		$row = $this->db->GetRow($sql, [$compday_id]);

		// 1以上で存在する場合はtrueを返す
		return ((int)$row['cnt'] > 0);
	}

	/**
	 * 代休日数修正
	 *
	 * @access	public
	 * @param	$compday_id, $param
	 * @return	boolean
	 */
	public function updateCompdayById($compday_id, $param) {

		$sets	= [];
		$params	= [];

		// 休日勤務日を追加
		if (isset($param['work_date'])) {
			$sets[]		= 'work_date = ?';
			$params[]	= $param['work_date'];
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

		$sets[] = 'update_date = NOW()';

		$sql = "
			UPDATE
				t_compday
			SET
				" . implode(', ', $sets) . "
			WHERE
				id			= ? AND
				delete_flg	= '0'
		";

		// 代休IDを追加
		$params[] = $compday_id;

		$this->db->Execute($sql, $params);
	}

	/**
	 * 代休削除
	 *
	 * @access	public
	 * @param	$compday_id
	 * @return
	 */
	public function deleteCompday($compday_id) {
		$sql = "
			UPDATE
				t_compday
			SET
				delete_flg	= '1',
				update_date	= NOW()
			WHERE
				id = ?
		";

		$this->db->Execute($sql, [$compday_id]);
	}
}
