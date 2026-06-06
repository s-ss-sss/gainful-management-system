<?php

// 名前空間
namespace App\User\Dao;

// ユーザーDAO共通ファイル
require_once ROOT_PATH . '/lib/sys/modules/daos/user/BaseDao.class.php';

class ApplyDao extends BaseDao {

	/**
	 * 休暇申請登録
	 *
	 * @access	public
	 * @param	$param
	 * @return
	 */
	public function insertApply($param) {
		$sql = '
			INSERT INTO t_request (
				user_id, bundle_id, start_date, start_am_pm, end_date, end_am_pm,
				kind, sub_kind, comment, holiday_number, compday_number, special_number,
				boss_result, manager_result, url_key_boss, url_key_manager, url_expire_date
			) VALUES (
				?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
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
			$param['holiday_number'],
			$param['compday_number'],
			$param['special_number'],
			$param['boss_result'],
			$param['manager_result'],
			$param['url_key_boss'],
			$param['url_key_manager'],
			$param['url_expire_date'],
		];

		$this->db->Execute($sql, $params);
	}
}
