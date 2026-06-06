<?php

// 名前空間
namespace App\User;

// ユーザークラス共通ファイル
require_once ROOT_PATH . '/lib/sys/modules/user/Base.class.php';

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

		// ログイン中のユーザーIDからデータ取得
		$user		= $this->getLoginUser();
		$user_id	= $user['user_id'] ?? null;

		// 最古年度取得
		$oldest_year = $this->dao->getOldestYear($user_id);

		// 年度プルダウン生成
		$year_selector = \Common::buildYearSelector($oldest_year);

		// 年度配列
		$years = $year_selector['years'];

		// 選択年度
		$selected_year = $year_selector['selected_year'];

		// 休暇実績取得
		$rows = $this->dao->getHistoryByYear($user_id, $selected_year);

		// 取得データをbundle_idと月で生成
		$history = $this->_buildHistoryData($rows);

		// CSRFトークン作成
		$csrf_token = \Common::generateCsrfToken();

		$this->smarty->assign([
			'csrf_token'	=> $csrf_token,
			'years'			=> $years,
			'selected_year'	=> $selected_year,
			'history'		=> $history,
			'summary'		=> $this->dao->getApplyDaysSummary($user_id),
			'section'		=> 'history',
		]);

		$this->smarty->display('user/history/index.tpl');
	}

	/**
	 * 一覧：bundle_idと月毎にデータ生成
	 *
	 * @access	private
	 * @param	$rows
	 * @return	$lists
	 */
	private function _buildHistoryData($rows) {

		// 月の配列を初期化
		$months = [3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 1, 2];
		$lists = [];
		foreach ($months as $m) {
			$lists[$m] = [
				'bundles'	=> [],
				'summary'	=> [
					'holiday'	=> 0.0,
					'compday'	=> 0.0,
					'special'	=> 0.0,
				],
			];
		}

		// グルーピング作成
		foreach ($rows as $r) {

			// 日付から締日年月取得（21〜20日）
			$closing	= \Common::getClosingYearMonth($r['start_date']);
			$month		= $closing['month'];

			// bundle_id取得
			$bundle_id = $r['bundle_id'];

			// bundle_idのバケットがなければ作成
			if (! isset($lists[$month]['bundles'][$bundle_id])) {
				$lists[$month]['bundles'][$bundle_id] = [
					'bundle_id'	=> $bundle_id,
					'dates'		=> [],
					'kind'		=> \Common::formatTypeLabel($r),
					'reason'	=> $r['comment'],
				];
			}

			// 参照渡しで表示用配列を取得
			$bundle		= &$lists[$month]['bundles'][$bundle_id];
			$summary	= &$lists[$month]['summary'];

			// 日付を追加
			$bundle['dates'][] = \Common::formatDateYmdLabel($r);

			// 消費日数を追加
			$summary['holiday']	+= (float)$r['holiday_number'];	// 有休
			$summary['compday']	+= (float)$r['compday_number'];	// 代休
			$summary['special']	+= (float)$r['special_number'];	// 特別休暇
		}

		// 参照渡しをリセット
		unset($bundle, $summary);

		return $lists;
	}
}
