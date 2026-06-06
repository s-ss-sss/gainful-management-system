<?php

// 名前空間
namespace App\Admin\Dao;

// ユーザーDAO共通ファイル
require_once ROOT_PATH . '/lib/sys/modules/daos/admin/BaseDao.class.php';

class StatusDao extends BaseDao {

	/**
	 * 承認待ち一覧取得
	 *
	 * @access	public
	 * @param
	 * @return	array
	 */
	public function getPendingStatusLists($position) {

		// WHERE句を動的に作成
		$where = ["r.delete_flg = '0'"];

		// 所属長
		if ($position == '1') {
			$where[]	= "r.boss_result = '0'";
			$where[]	= 'r.manager_result IS NULL';

		// 社長
		} elseif ($position == '2') {
			$where[]	= 'r.boss_result IS NULL';
			$where[]	= "r.manager_result = '0'";
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
	 * 取消可能チェック
	 *
	 * @access	public
	 * @param	$bundle_id, $position
	 * @return	int
	 */
	public function existsCancelableApproval($bundle_id, $position) {

		// WHERE句を動的に作成
		$where = [
			'r.bundle_id	= ?',
			"r.delete_flg	= '0'"
		];

		// 所属長
		if ($position == '1') {
			$where[]	= "r.boss_result = '0'";
			$where[]	= 'r.manager_result IS NULL';

			// 社長
		} elseif ($position == '2') {
			$where[]	= 'r.boss_result IS NULL';
			$where[]	= "r.manager_result = '0'";
		}

		// WHERE句にANDを追加
		$where_sql = implode(' AND ', $where);

		$sql = "
			SELECT
				COUNT(*) AS cnt
			FROM
				t_request r
			WHERE
				{$where_sql}
		";

		$row = $this->db->GetRow($sql, [$bundle_id]);

		// 1以上で存在する場合はtrueを返す
		return ((int)$row['cnt'] > 0);
	}

	/**
	 * 承認キャンセル
	 *
	 * @access	public
	 * @param	$bundle_id, $position
	 * @return
	 */
	public function cancelApproval($bundle_id, $position) {

		// SET句を動的に作成
		$set = ['update_date = NOW()'];

		// WHERE句を動的に作成
		$where = [
			'bundle_id	= ?',
			"delete_flg	= '0'"
		];

		// 所属長
		if ($position == '1') {
			$set[]		= 'boss_result = NULL';
			$where[]	= "boss_result = '0'";
			$where[]	= 'manager_result IS NULL';

		// 社長
		} elseif ($position == '2') {
			$set[]		= 'manager_result = NULL';
			$where[]	= 'boss_result IS NULL';
			$where[]	= "manager_result = '0'";
		}

		// SET句に,を追加
		$set_sql = implode(', ', $set);

		// WHERE句にANDを追加
		$where_sql = implode(' AND ', $where);

		$sql = "
			UPDATE
				t_request
			SET
				{$set_sql}
			WHERE
				{$where_sql}
		";

		$this->db->Execute($sql, [$bundle_id]);
	}
}
