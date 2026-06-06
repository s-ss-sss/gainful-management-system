<?php

// 名前空間
namespace App\Admin;

// 管理クラス共通ファイル
require_once ROOT_PATH . '/lib/sys/modules/admin/Base.class.php';

class History extends Base {

	/**
	 * 一覧：表示
	 *
	 * @access	public
	 * @param
	 * @return
	 */
	public function displayHistoryIndex() {

		// CSRFトークン検証
		\Common::validatePostCsrf();

		// ユーザー一覧取得
		$users = $this->dao->getUsers();

		// 選択ユーザー
		$selected_user = $_POST['user'] ?? '';

		// 年月検索のプルダウン
		$ym = $this->_buildYearMonthSelector();

		// 表示年月の配列
		$years	= $ym['years'];
		$months	= $ym['months'];

		// 選択年月の値
		$selected_year	= $ym['selected_year'];
		$selected_month	= $ym['selected_month'];

		// 検索期間生成
		[$from, $to] = \Common::buildClosingPeriod($selected_year, $selected_month);

		// 表示用期間生成
		$display_from	= $this->formatAdminDateYmdLabel($from);
		$display_to		= $this->formatAdminDateYmdLabel($to);

		// 休暇実績一覧取得
		$rows = $this->dao->getHistoryListByPeriod($selected_user, $from, $to);

		// グルーピング作成
		$history_lists = $this->_buildHistoryData($rows);

		// CSRFトークン作成
		$csrf_token = \Common::generateCsrfToken();

		$this->smarty->assign([
			'csrf_token'		=> $csrf_token,
			'users'				=> $users,
			'selected_user'		=> $selected_user,
			'years'				=> $years,
			'months'			=> $months,
			'selected_year'		=> $selected_year,
			'selected_month'	=> $selected_month,
			'from'				=> $from,
			'to'				=> $to,
			'display_from'		=> $display_from,
			'display_to'		=> $display_to,
			'history_lists'		=> $history_lists,
			'section'			=> 'history',
		]);

		$this->smarty->display('admin/history/index.tpl');
	}

	/**
	 * 一覧：年月検索のプルダウン
	 *
	 * @access	private
	 * @param
	 * @return
	 */
	private function _buildYearMonthSelector() {

		// 最古年取得
		$oldest = $this->dao->getHistoryOldestYm();

		// 現在年月取得
		$current_year	= (int)date('Y');
		$current_month	= (int)date('n');
		$current_day	= (int)date('j');

		// 21日以降は翌月
		if ($current_day >= 21) {
			$default_year	= ($current_month === 12) ? $current_year + 1 : $current_year;
			$default_month	= ($current_month === 12) ? 1 : $current_month + 1;

			// 20日以前は当月
		} else {
			$default_year	= $current_year;
			$default_month	= $current_month;
		}

		// 選択年月取得
		$selected_year	= (int)($_POST['year'] ?? $default_year);
		$selected_month	= (int)($_POST['month'] ?? $default_month);

		// 年プルダウンの配列を生成
		if ($oldest === null) {
			$years = [$default_year];
		} else {
			$start	= min($oldest['year'], $default_year);
			$end	= max($oldest['year'], $default_year);
			$years = range($start, $end);
		}

		// 選択年が不正な場合は現在年に補正
		if (! in_array($selected_year, $years, true)) {
			$selected_year = $default_year;
		}

		// 月プルダウンの配列を生成
		$months = range(1, 12);

		// 選択月が不正な場合は現在月に補正
		if ($selected_month < 1 || $selected_month > 12) {
			$selected_month = $default_month;
		}

		return [
			'years'				=> $years,
			'months'			=> $months,
			'selected_year'		=> $selected_year,
			'selected_month'	=> $selected_month,
		];
	}

	/**
	 * 一覧：ユーザー毎にデータ生成
	 *
	 * @access	private
	 * @param	$rows
	 * @return	$lists
	 */
	private function _buildHistoryData($rows) {

		// グルーピング作成
		$lists = [];
		foreach ($rows as $r) {

			$user_id	= $r['user_id'];
			$bundle_id	= $r['bundle_id'];

			// ユーザーのバケットが無ければ作成
			if (! isset($lists[$user_id])) {
				$lists[$user_id] = [
					'user_id'	=> $user_id,
					'user_name'	=> $r['user_name'],
					'bundles'	=> [],
					'total'		=> [
						'holiday'	=> 0.0,
						'compday'	=> 0.0,
						'special'	=> 0.0,
					],
				];
			}

			// bundle_idのバケットがなければ作成
			if (! isset($lists[$user_id]['bundles'][$bundle_id])) {
				$lists[$user_id]['bundles'][$bundle_id] = [
					'bundle_id'	=> $bundle_id,
					'dates'		=> [],
					'kind'		=> \Common::formatTypeLabel($r),
					'reason'	=> $r['comment'],
					'holiday'	=> 0.0,
					'compday'	=> 0.0,
					'special'	=> 0.0,
				];
			}

			// 参照渡しでbundleを取得
			$bundle = &$lists[$user_id]['bundles'][$bundle_id];

			// 日付を追加
			$bundle['dates'][] = \Common::formatDateMdLabel($r);

			// 消費日数を追加
			$bundle['holiday']	+= (float)$r['holiday_number'];	// 有休
			$bundle['compday']	+= (float)$r['compday_number'];	// 代休
			$bundle['special']	+= (float)$r['special_number'];	// 特別休暇

			// ユーザー合計を追加
			$lists[$user_id]['total']['holiday']	+= (float)$r['holiday_number'];	// 有休
			$lists[$user_id]['total']['compday']	+= (float)$r['compday_number'];	// 代休
			$lists[$user_id]['total']['special']	+= (float)$r['special_number'];	// 特別休暇
		}

		// 参照渡しをリセット
		unset($bundle);

		return $lists;
	}
}
