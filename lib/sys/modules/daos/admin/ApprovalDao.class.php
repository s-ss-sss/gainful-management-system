<?php

// 名前空間
namespace App\Admin\Dao;

// ユーザーDAO共通ファイル
require_once ROOT_PATH . '/lib/sys/modules/daos/admin/BaseDao.class.php';

class ApprovalDao extends BaseDao {

	/**
	 * 承認待ち一覧取得
	 *
	 * @access	public
	 * @param	$position
	 * @return	array
	 */
	public function getApprovalLists($position) {

		// WHERE句を動的に作成
		$where = [
			"r.delete_flg = '0'",
			"(r.boss_result IS NULL OR r.boss_result		= '0')",
			"(r.manager_result IS NULL OR r.manager_result	= '0')"
		];

		// 所属長
		if ($position == '1') {
			$where[] = 'r.boss_result IS NULL';

		// 社長
		} elseif ($position == '2') {
			$where[] = 'r.manager_result IS NULL';
		}

		// WHERE句にANDを追加
		$where_sql = implode(' AND ', $where);

		$sql = "
			SELECT
				r.id, r.user_id, u.name AS user_name, r.bundle_id, r.start_date, r.start_am_pm,
				r.end_date, r.end_am_pm, r.kind, r.sub_kind, r.comment, r.boss_result, r.manager_result
			FROM
				t_request r
			INNER JOIN
				t_users u ON r.user_id = u.user_id
			WHERE
				{$where_sql}
			ORDER BY
				CAST(r.user_id AS UNSIGNED) ASC,
				r.start_date ASC,
				r.start_am_pm ASC,
				r.end_am_pm ASC,
				r.id ASC
		";

		// 実行
		$rs = $this->db->Execute($sql);

		// 実行に失敗した場合は空配列を返す
		if (! $rs) {
			return [];
		}

		// 行番号ジャンプエラー回避のため手動で取得
		$rows = [];
		while (! $rs->EOF) {
			$rows[] = $rs->fields;
			$rs->MoveNext();
		}

		return $rows;
	}

	/**
	 * 所属長の承認/却下
	 *
	 * @access	public
	 * @param	$bundle_id, $value, $comment = null
	 * @return
	 */
	public function updateBossResult($bundle_id, $value, $comment = null) {
		$sql = "
			UPDATE
				t_request
			SET
				boss_result		= ?,
				boss_comment	= ?,
				update_date		= NOW()
			WHERE
				bundle_id	= ? AND
				delete_flg	= '0'
		";

		$this->db->Execute($sql, [$value, $comment, $bundle_id]);
	}

	/**
	 * 社長の承認/却下
	 *
	 * @access	public
	 * @param	$bundle_id, $value, $comment = null
	 * @return
	 */
	public function updateManagerResult($bundle_id, $value, $comment = null) {
		$sql = "
			UPDATE
				t_request
			SET
				manager_result	= ?,
				manager_comment	= ?,
				update_date		= NOW()
			WHERE
				bundle_id	= ? AND
				delete_flg	= '0'
		";

		$this->db->Execute($sql, [$value, $comment, $bundle_id]);
	}
}
