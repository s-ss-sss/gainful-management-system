<?php

// 名前空間
namespace App\Admin;

// 管理クラス共通ファイル
require_once ROOT_PATH . '/lib/sys/modules/admin/Base.class.php';

class Status extends Base {

	/**
	 * 一覧：表示
	 *
	 * @access	public
	 * @param
	 * @return
	 */
	public function displayStatusIndex() {

		// アラートフラッシュメッセージ
		$this->assignFlashMessage('承認');

		// ログイン中のユーザーIDからデータ取得
		$user		= $this->getLoginUser();
		$position	= $user['position'] ?? '0';

		// 承認待ち一覧取得
		$approval_data = $this->dao->getPendingStatusLists($position);

		// グルーピング作成
		$approval_lists = $this->_buildApprovalData($approval_data);

		// 表示用のbundle_idの件数をカウント
		$bundle_count = 0;
		foreach ($approval_lists as $row) {
			$bundle_count += count($row['bundles']);
		}

		// CSRFトークン作成
		$csrf_token = \Common::generateCsrfToken();

		$this->smarty->assign([
			'csrf_token'		=> $csrf_token,
			'weekdays'			=> \Common::$weekdays,
			'sections'			=> \Common::$apply_sections,
			'sub_types'			=> \Common::$apply_sub_types,
			'approval_lists'	=> $approval_lists,
			'bundle_count'		=> $bundle_count,
			'section'			=> 'status',
		]);

		$this->smarty->display('admin/status/index.tpl');
	}

	/**
	 * 一覧：bundle_idとユーザー毎にデータ生成
	 *
	 * @access	private
	 * @param	$rows, $position
	 * @return	$lists
	 */
	private function _buildApprovalData($rows) {

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
				];
			}

			// bundle_idのバケットがなければ作成
			if (! isset($lists[$user_id]['bundles'][$bundle_id])) {

				$lists[$user_id]['bundles'][$bundle_id] = [
					'bundle_id'			=> $bundle_id,
					'dates'				=> [],
					'kind'				=> \Common::formatTypeLabel($r),
					'reason'			=> $r['comment'],
					'boss_result'		=> $r['boss_result'],
					'manager_result'	=> $r['manager_result'],
				];
			}

			// 参照渡しでbundleを取得
			$bundle = &$lists[$user_id]['bundles'][$bundle_id];

			// 日付を追加
			$bundle['dates'][] = \Common::formatDateYmdLabel($r);
		}

		// 参照渡しをリセット
		unset($bundle);

		return $lists;
	}

	/**
	 * 取消
	 *
	 * @access	public
	 * @param
	 * @return
	 */
	public function cancelApproval() {

		// ログイン中のユーザーIDからデータ取得
		$user		= $this->getLoginUser();
		$position	= $user['position'] ?? '0';

		// bundle_id取得
		$bundle_id = $_POST['bundle_id'] ?? '';

		// データが不正な場合は一覧画面にリダイレクト
		if (empty($bundle_id) || ! $this->dao->existsCancelableApproval($bundle_id, $position)) {
			trigger_error(ERR_INVALID, E_USER_WARNING);
			return $this->displayStatusIndex();
		}

		try {

			// CSRFトークン検証
			\Common::validateCsrfToken();

			// トランザクション開始
			$this->dao->beginTransaction();

			// 承認キャンセル
			$this->dao->cancelApproval($bundle_id, $position);

			// 成功したらコミット
			$this->dao->commitTransaction();

			// 削除フラグをセッションに保存
			$_SESSION['flash_action'] = 'cancel';

			// 一覧画面にリダイレクト
			header('Location: ' . BASE_URL . 'admin/status/');
			exit;

		} catch (\Exception $e) {

			// 失敗したらロールバック
			$this->dao->rollbackTransaction();
			return $this->displayStatusIndex();
		}
	}
}
