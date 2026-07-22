<?php

// 名前空間
namespace App\User\Dao;

// 共通DAOファイル
require_once ROOT_PATH . '/lib/sys/modules/daos/CommonDao.class.php';

class BaseDao extends \CommonDao {

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

	/**
	 * 残日数一覧取得
	 *
	 * @access	public
	 * @param	$user_id
	 * @return	array
	 */
	public function getApplyDaysSummary($user_id) {

		// 正式残日数取得
		$official = $this->_getOfficialApplyDays($user_id);

		// 未承認申請日数取得
		$pending = $this->_getPendingApplyDays($user_id);

		return [

			// 有休
			'holiday' => [
				'balance'	=> (float)$official['holiday_number'] - (float)$pending['pending_holiday'],
				'pending'	=> (float)$pending['pending_holiday'],
			],

			// 代休
			'compday' => [
				'balance'	=> (float)$official['compday_number'] - (float)$pending['pending_compday'],
				'pending'	=> (float)$pending['pending_compday'],
			],

			// 特別休暇
			'special' => [
				'balance'	=> (float)$official['special_number'] - (float)$pending['pending_special'],
				'pending'	=> (float)$pending['pending_special'],
				'details'	=> $this->_getSpecialDetails($user_id),
			],
		];
	}

	/**
	 * 正式残日数取得
	 *
	 * @access	private
	 * @param	$user_id
	 * @return	array
	 */
	private function _getOfficialApplyDays($user_id) {
		$sql = "
			SELECT 
				u.holiday_number, u.compday_number, u.special_number
			FROM 
				t_users u
			WHERE
				u.user_id		= ? AND
				u.delete_flg	= '0'
		";

		return $this->db->GetRow($sql, [$user_id]);
	}

	/**
	 * 未承認申請日数取得
	 *
	 * @access	private
	 * @param	$user_id
	 * @return	array
	 */
	private function _getPendingApplyDays($user_id) {

		// COALESCE(*****, 'x')：NULL対応
		// NULLを'x'に変換してunknownとなる判定を回避
		$sql = "
			SELECT
				COALESCE(SUM(r.holiday_number), 0) AS pending_holiday,
				COALESCE(SUM(r.compday_number), 0) AS pending_compday,
				COALESCE(SUM(r.special_number), 0) AS pending_special
			FROM
				t_request r
			WHERE
				r.user_id		= ? AND
				r.delete_flg	= '0' AND
				COALESCE(r.boss_result, 'x')	<> '1' AND
				COALESCE(r.manager_result,'x')	<> '1' AND NOT
				(COALESCE(r.boss_result, 'x')	= '0' AND COALESCE(r.manager_result,'x') = '0')
		";

		return $this->db->GetRow($sql, [$user_id]);
	}

	/**
	 * 特別休暇の内訳取得
	 *
	 * @access	private
	 * @param	$user_id
	 * @return	array
	 */
	private function _getSpecialDetails($user_id) {

		$details = [];
		foreach (\Common::$consume_sub_type_ids as $type) {

			// サブ種別毎の特別休暇の付与合計
			$granted = $this->getTotalSpecialGranted($user_id, $type);

			// サブ種別毎の承認済みの特別休暇の消費合計
			$approved = $this->getApprovedSpecialConsumed($user_id, $type);

			// サブ種別毎の承認待ち特別休暇の消費合計
			$pending = $this->_getPendingSpecialConsumedDays($user_id, $type);

			// 付与合計と承認済みの消費合計の差分
			$balance = $granted - $approved;

			// 承認待ちか差分がある場合は内訳を配列に格納
			if ($balance > 0 || $pending > 0) {
				$details[$type] = [
					'label'		=> \Common::$apply_sub_types[$type],
					'balance'	=> (float)$balance - (float)$pending,
					'pending'	=> (float)$pending,
				];
			}
		}

		return $details;
	}

	/**
	 * サブ種別毎の承認待ち特別休暇の消費合計
	 *
	 * @access	private
	 * @param	$user_id, $sub_type
	 * @return	string
	 */
	private function _getPendingSpecialConsumedDays($user_id, $sub_type) {

		// COALESCE(*****, 'x')：NULL対応
		// NULLを'x'に変換してunknownとなる判定を回避
		$sql = "
			SELECT
				COALESCE(SUM(r.special_number), 0.0)
			FROM
				t_request r
			WHERE
				r.user_id		= ? AND
				r.kind			= '1' AND
				r.sub_kind		= ? AND
				r.delete_flg	= '0' AND
				COALESCE(r.boss_result, 'x')	<> '1' AND
				COALESCE(r.manager_result, 'x')	<> '1' AND NOT
				(COALESCE(r.boss_result, 'x')	= '0' AND COALESCE(r.manager_result, 'x') = '0')
		";

		return (float)$this->db->GetOne($sql, [$user_id, $sub_type]);
	}
}
