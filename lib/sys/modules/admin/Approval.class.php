<?php

// 名前空間
namespace App\Admin;

// 管理クラス共通ファイル
require_once ROOT_PATH . '/lib/sys/modules/admin/Base.class.php';

class Approval extends Base {

	/**
	 * 一覧：表示
	 *
	 * @access	public
	 * @param
	 * @return
	 */
	public function displayApprovalIndex() {

		// アラートフラッシュメッセージ
		$this->assignFlashMessage();

		// ログイン中のユーザーIDからデータ取得
		$user		= $this->getLoginUser();
		$position	= $user['position'] ?? '0';

		// 承認待ち一覧取得
		$approval_data = $this->dao->getApprovalLists($position);

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
			'holidays'			=> \Common::getHolidays(),
			'current_year'		=> date('Y'),
			'current_month'		=> date('n'),
			'approval_lists'	=> $approval_lists,
			'bundle_count'		=> $bundle_count,
			'section'			=> 'approval',
		]);

		$this->smarty->display('admin/approval/index.tpl');
	}

	/**
	 * 一覧：bundle_idとユーザー毎にデータ生成
	 *
	 * @access	private
	 * @param	$rows
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
	 * 承認
	 *
	 * @access	public
	 * @param
	 * @return
	 */
	public function approveRequests() {

		// ログイン中のユーザーIDからデータ取得
		$user		= $this->getLoginUser();
		$position	= $user['position'] ?? '0';

		// 申請毎のID取得
		$bundle_ids = $_POST['approval'] ?? [];

		try {

			// CSRFトークン検証
			\Common::validateCsrfToken();

			// トランザクション開始
			$this->dao->beginTransaction();

			// メール送信用の配列
			$mail_queue = [];

			// t_requestの承認
			foreach ($bundle_ids as $bundle_id) {

				// 承認処理を実行してメール送信用の結果を返す
				$mail_queue[$bundle_id] = $this->_approveSingleRequest($bundle_id, $position);
			}

			// 成功したらコミット
			$this->dao->commitTransaction();

			// 承認結果メール送信処理を実行
			$this->_sendApprovalMails($mail_queue);

			// 承認フラグと承認カウントをセッションに保存
			$_SESSION['flash_action']	= 'approve';
			$_SESSION['flash_count']	= count($bundle_ids);

			// 一覧画面にリダイレクト
			header('Location: ' . BASE_URL . 'admin/');
			exit;

		} catch (\Exception $e) {

			// 失敗したらロールバック
			$this->dao->rollbackTransaction();
			return $this->displayApprovalIndex();
		}
	}

	/**
	 * 承認：実行
	 *
	 * @access	private
	 * @param	$bundle_id, $position
	 * @return	array
	 */
	private function _approveSingleRequest($bundle_id, $position) {

		// 承認前の申請データ取得
		$before			= $this->dao->getRequestByBundleId($bundle_id);
		$user_auth		= $before[0]['user_auth'];		// 所属
		$boss_result	= $before[0]['boss_result'];	// 所属長承認結果
		$manager_result	= $before[0]['manager_result'];	// 社長承認結果

		// 所属長の更新処理
		if ($position == '1') {
			$this->dao->updateBossResult($bundle_id, '0');
			$boss_result = '0';

		// 社長の更新処理
		} elseif ($position == '2') {
			$this->dao->updateManagerResult($bundle_id, '0');
			$manager_result = '0';

			// 所属長が存在しなければ自動承認
			if (! $this->dao->existsBossInGroup($user_auth)) {
				$this->dao->updateBossResult($bundle_id, '0');
				$boss_result = '0';
			}
		}

		// 所属長と社長が承認
		if ($boss_result == '0' && $manager_result == '0') {

			// 休暇日数消費
			$this->consumeApprovalDays($before);
		}

		// 更新後の最新データを再取得
		$after = $this->dao->getRequestByBundleId($bundle_id);

		// メール送信用の結果を保存
		return [
			'requests'			=> $after,
			'boss_result'		=> $after[0]['boss_result'],
			'manager_result'	=> $after[0]['manager_result'],
		];
	}

	/**
	 * 承認：メール送信
	 *
	 * @access	private
	 * @param	$mail_queue
	 * @return
	 */
	private function _sendApprovalMails($mail_queue) {

		// メール送信
		foreach ($mail_queue as $value) {

			// メール送信用の結果
			$requests		= $value['requests'];
			$boss_result	= $value['boss_result'];
			$manager_result	= $value['manager_result'];

			// 所属長と社長が承認
			if ($boss_result == '0' && $manager_result == '0') {

				// 承認メール送信
				$this->_sendApprovalMail($requests);

				// 所属長と社長のどちらかが却下
			} elseif (
				($boss_result == '1' && $manager_result == '0') ||
				($boss_result == '0' && $manager_result == '1')
			) {

				// 却下メール送信
				$this->_sendRejectMail($requests);
			}
		}
	}

	/**
	 * 却下：表示
	 *
	 * @access	public
	 * @param	$bundle_id, $form_data = [], $errors = []
	 * @return
	 */
	public function displayApprovalRejectForm($bundle_id, $form_data = null, $errors = []) {

		// 申請データ取得
		$rows = $this->dao->getRequestByBundleId($bundle_id);

		// 申請日を生成
		$dates = [];
		foreach ($rows as $row) {
			$dates[] = \Common::formatDateYmdLabel($row);
		}

		// 表示データ
		$meta = [
			'user_id'		=> $rows[0]['user_id'],
			'user_name'		=> $rows[0]['user_name'],
			'bundle_id'		=> $bundle_id,
			'dates'			=> $dates,
			'kind'			=> $rows[0]['kind'],
			'type_label'	=> \Common::formatTypeLabel($rows[0]),
			'comment'		=> $rows[0]['comment'],
			'cancel_reason'	=> '',
		];

		// フォームデータ初期化
		if ($form_data === null) {
			$form_data = [
				'cancel_reason'	=> ''
			];
		}

		// 表示データとフォームデータをマージ
		$form_data = array_merge($meta, $form_data);

		// CSRFトークン作成
		$csrf_token = \Common::generateCsrfToken();

		$this->smarty->assign([
			'csrf_token'	=> $csrf_token,
			'form_data'		=> $form_data,
			'errors'		=> $errors,
			'section'		=> 'approval',
		]);

		$this->smarty->display('admin/approval/reject.tpl');
	}

	/**
	 * 却下：実行
	 *
	 * @access	public
	 * @param	$bundle_id
	 * @return
	 */
	public function rejectApproval($bundle_id) {

		// ログイン中のユーザーIDからデータ取得
		$user		= $this->getLoginUser();
		$position	= $user['position'] ?? '0';

		// フォームデータ取得
		$form_data = [
			'cancel_reason' => $_POST['cancel_reason'] ?? ''
		];

		// バリデーション実行
		$errors = $this->_validateRejectApproval($form_data);

		// エラー内容を再描画
		if (! empty($errors)) {
			return $this->displayApprovalRejectForm($bundle_id, $form_data, $errors);
		}

		try {

			// CSRFトークン検証
			\Common::validateCsrfToken();

			// トランザクション開始
			$this->dao->beginTransaction();

			// 却下更新をして結果を返す
			$result = $this->_rejectSingleRequest($bundle_id, $position, $form_data['cancel_reason']);

			// 成功したらコミット
			$this->dao->commitTransaction();

			// 却下メール送信判定
			$this->_determineRejectMail($result, $position);

			// 却下フラグをセッションに保存
			$_SESSION['flash_action'] = 'reject';

			// 一覧画面にリダイレクト
			header('Location: ' . BASE_URL . 'admin/');
			exit;

		} catch (\Exception $e) {

			// 失敗したらロールバック
			$this->dao->rollbackTransaction();
			return $this->displayApprovalRejectForm($bundle_id);
		}
	}

	/**
	 * 却下：バリデーション
	 *
	 * @access	public
	 * @param	$form_data
	 * @return	$errors
	 */
	private function _validateRejectApproval($form_data) {
		$errors = [];

		// 却下理由
		$cancel_reason = $form_data['cancel_reason'] ?? '';
		if ($cancel_reason === '') {
			$errors['cancel_reason'] = '却下理由を入力してください';
		} elseif (mb_strlen($cancel_reason) > 256) {
			$errors['cancel_reason'] = '却下理由は256文字以内で入力してください';
		}

		return $errors;
	}

	/**
	 * 却下：更新
	 *
	 * @access	private
	 * @param	$bundle_id, $position, $cancel_reason
	 * @return	array
	 */
	private function _rejectSingleRequest($bundle_id, $position, $cancel_reason) {

		// 更新前の承認/却下を取得
		$before = $this->dao->getRequestByBundleId($bundle_id);

		// 所属長の更新処理
		if ($position == '1') {
			$this->dao->updateBossResult($bundle_id, '1', $cancel_reason);

			// 社長の更新処理
		} elseif ($position == '2') {
			$this->dao->updateManagerResult($bundle_id, '1', $cancel_reason);
		}

		// 更新後の承認/却下を取得
		$after = $this->dao->getRequestByBundleId($bundle_id);

		return [
			'before'	=> $before,
			'after'		=> $after,
		];
	}

	/**
	 * 却下：メール送信
	 *
	 * @access	private
	 * @param	$data, $position
	 * @return
	 */
	private function _determineRejectMail($result, $position) {

		// 更新前後の承認/却下結果
		$before_boss	= $result['before'][0]['boss_result'];
		$before_manager	= $result['before'][0]['manager_result'];
		$after_boss		= $result['after'][0]['boss_result'];
		$after_manager	= $result['after'][0]['manager_result'];

		// 却下メール送信フラグ
		$send_mail = false;

		// 所属長の却下
		if ($position == '1') {

			// 操作前が未承認/操作後が却下/社長が未承認か承認
			if (
				is_null($before_boss) && $after_boss == '1' &&
				(is_null($after_manager) || $after_manager == '0')
			) {
				$send_mail = true;
			}

			// 社長の却下
		} elseif ($position == '2') {

			// 操作前が未承認/操作後が却下/所属長が未承認か承認
			if (
				is_null($before_manager) && $after_manager == '1' &&
				(is_null($after_boss) || $after_boss == '0')
			) {
				$send_mail = true;
			}
		}

		// 却下メール送信
		if ($send_mail) {
			$this->_sendRejectMail($result['after']);
		}
	}

	/**
	 * メール送信：共通
	 *
	 * @access	private
	 * @param	$requests
	 * @return	$user_email
	 */
	private function _prepareRequestMailData($requests) {

		// ユーザーIDから合致するデータ取得
		$user_id	= $requests[0]['user_id'];
		$user		= $this->dao->getUserDetailById($user_id);
		$user_email	= $user['mail'] ?? '';

		// 申請日データを整形
		$dates = $this->_formatDatesLabel($requests);

		// メールで表示させる適用ラベルの生成
		$apply_labels = \Common::buildApplyLabelsFromRequests($requests);

		// メールで判定する承認/却下フラグの整形
		$status = [
			'boss'		=> $this->_determineStatus($requests[0]['boss_result']),
			'manager'	=> $this->_determineStatus($requests[0]['manager_result']),
		];

		$this->smarty->assign([
			'types'			=> \Common::$apply_types,
			'sub_types'		=> \Common::$apply_sub_types,
			'requests'		=> $requests,
			'dates'			=> $dates,
			'apply_labels'	=> $apply_labels,
			'status'		=> $status,
		]);

		return $user_email;
	}

	/**
	 * メール送信：承認
	 *
	 * @access	private
	 * @param	$requests
	 * @return
	 */
	private function _sendApprovalMail($requests) {

		// ユーザー設定
		$user_email		= $this->_prepareRequestMailData($requests);
		$subject_user	= '休暇申請が承認されました';
		$body_user		= $this->smarty->fetch('admin/mails/approval.tpl');

		// ユーザーに個別送信
		\Common::sendMail($user_email, $subject_user, $body_user);
	}

	/**
	 * メール送信：却下
	 *
	 * @access	private
	 * @param	$requests
	 * @return
	 */
	private function _sendRejectMail($requests) {

		// ユーザー設定
		$user_email		= $this->_prepareRequestMailData($requests);
		$subject_user	= '休暇申請は却下されました';
		$body_user		= $this->smarty->fetch('admin/mails/reject.tpl');

		// ユーザーに個別送信
		\Common::sendMail($user_email, $subject_user, $body_user);
	}

	/**
	 * 申請日データ整形
	 *
	 * @access	private
	 * @param	$requests
	 * @return	$dates
	 */
	private function _formatDatesLabel($requests) {

		// 申請日を生成
		$dates = [];
		foreach ($requests as $req) {

			// 区分を整形
			$section = \Common::formatSectionLabel($req);

			// 申請日を整形
			$display = \Common::formatDateYmdLabel($req);

			// 申請日データを配列に格納
			$dates[] = [
				'date'		=> $req['start_date'],
				'section'	=> $section,
				'display'	=> $display,
			];
		}

		return $dates;
	}

	/**
	 * 承認/却下のフラグ整形
	 *
	 * @access	private
	 * @param	$result
	 * @return	string
	 */
	private function _determineStatus($result) {

		// NULL
		if (is_null($result)) {
			return 'pending';
		}

		// 却下
		if ($result == '1') {
			return 'rejected';
		}

		// 承認
		if ($result == '0') {
			return 'approved';
		}

		// 想定外の保険
		return 'pending';
	}
}
