<?php

// 名前空間
namespace App\User\Dao;

// ユーザーDAO共通ファイル
require_once ROOT_PATH . '/lib/sys/modules/daos/user/BaseDao.class.php';

class StatusDao extends BaseDao {

	/**
	 * 承認待ち一覧取得
	 *
	 * @access	public
	 * @param	$user_id
	 * @return	array
	 */
	public function getPendingRequests($user_id) {

		// COALESCE(*****, 'x')：NULL対応
		// NULLを'x'に変換してunknownとなる判定を回避
		$sql = "
			SELECT
				r.id, r.bundle_id, r.start_date, r.start_am_pm, r.end_date,
				r.end_am_pm, r.kind, r.sub_kind, r.comment, r.holiday_number,
				r.compday_number, r.special_number, r.boss_result, r.manager_result
			FROM
				t_request r
			WHERE
				r.user_id		= ? AND
				r.delete_flg	= '0' AND
				COALESCE(r.boss_result, 'x')	<> '1' AND
				COALESCE(r.manager_result, 'x')	<> '1' AND NOT
				(COALESCE(r.boss_result, 'x')	= '0' AND COALESCE(r.manager_result, 'x') = '0')
			ORDER BY
				r.start_date ASC,
				r.start_am_pm ASC,
				r.end_am_pm ASC,
				r.id ASC
		";

		return $this->db->GetAll($sql, [$user_id]);
	}

	/**
	 * 取消可能チェック
	 *
	 * @access	public
	 * @param	$user_id, $bundle_id
	 * @return	int
	 */
	public function existsCancelableBundle($user_id, $bundle_id) {

		// COALESCE(*****, 'x')：NULL対応
		// NULLを'x'に変換してunknownとなる判定を回避
		$sql = "
			SELECT
				COUNT(*) AS cnt
			FROM
				t_request r
			WHERE
				r.user_id		= ? AND
				r.bundle_id		= ? AND
				r.delete_flg	= '0' AND
				COALESCE(r.boss_result, 'x')	<> '1' AND
				COALESCE(r.manager_result, 'x')	<> '1' AND NOT
				(COALESCE(r.boss_result, 'x')	= '0' AND COALESCE(r.manager_result, 'x') = '0')
		";

		$row = $this->db->GetRow($sql, [$user_id, $bundle_id]);

		// 1以上で存在する場合はtrueを返す
		return ((int)$row['cnt'] > 0);
	}

	/**
	 * 申請キャンセル
	 *
	 * @access	public
	 * @param	$user_id, $bundle_id
	 * @return
	 */
	public function cancelBundle($user_id, $bundle_id) {

		// COALESCE(*****, 'x')：NULL対応
		// NULLを'x'に変換してunknownとなる判定を回避
		$sql = "
			UPDATE
				t_request
			SET
				delete_flg	= '1',
				update_date	= NOW()
			WHERE
				user_id		= ? AND
				bundle_id	= ? AND 
				delete_flg	= '0' AND
				COALESCE(boss_result, 'x')		<> '1' AND
				COALESCE(manager_result, 'x')	<> '1' AND NOT
				(COALESCE(boss_result, 'x')		= '0' AND COALESCE(manager_result, 'x') = '0')
		";

		$this->db->Execute($sql, [$user_id, $bundle_id]);
	}
}
