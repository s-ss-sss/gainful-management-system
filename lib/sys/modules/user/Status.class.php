<?php

// 名前空間
namespace App\User;

// ユーザークラス共通ファイル
require_once ROOT_PATH . '/lib/sys/modules/user/Base.class.php';

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
		$this->assignFlashMessage('休暇申請');

		// ログイン中のユーザーIDからデータ取得
		$user		= $this->getLoginUser();
		$user_id	= $user['user_id'] ?? null;

		// 承認待ちリスト取得
		$rows = $this->dao->getPendingRequests($user_id);

		// 取得データをbundle_id毎に生成
		$status_lists = $this->_buildStatusData($rows);

		// CSRFトークン作成
		$csrf_token = \Common::generateCsrfToken();

		$this->smarty->assign([
			'csrf_token'	=> $csrf_token,
			'sub_types'		=> \Common::$apply_sub_types,
			'status_lists'	=> $status_lists,
			'summary'		=> $this->dao->getApplyDaysSummary($user_id),
			'section'		=> 'status',
		]);

		$this->smarty->display('user/status/index.tpl');
	}

	/**
	 * 一覧：bundle_id毎にデータ生成
	 *
	 * @access	private
	 * @param	$rows
	 * @return	$lists
	 */
	private function _buildStatusData($rows) {

		// グルーピング作成
		$lists = [];
		foreach ($rows as $r) {

			$bundle_id = $r['bundle_id'];

			// bundle_idのバケットがなければ作成
			if (! isset($lists[$bundle_id])) {
				$lists[$bundle_id] = [
					'bundle_id'			=> $bundle_id,
					'dates'				=> [],
					'kind'				=> \Common::formatTypeLabel($r),
					'reason'			=> $r['comment'],
					'holiday'			=> 0.0,
					'compday'			=> 0.0,
					'special'			=> 0.0,
					'boss_result'		=> $r['boss_result'],
					'manager_result'	=> $r['manager_result'],
				];
			}

			// 参照渡しでbundleを取得
			$bundle = &$lists[$bundle_id];

			// 日付を追加
			$bundle['dates'][] = \Common::formatDateYmdLabel($r);

			// 消費日数を追加
			$bundle['holiday']	+= (float)$r['holiday_number'];	// 有休
			$bundle['compday']	+= (float)$r['compday_number'];	// 代休
			$bundle['special']	+= (float)$r['special_number'];	// 特別休暇
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
	public function cancelRequests() {

		// ログイン中のユーザーIDからデータ取得
		$user		= $this->getLoginUser();
		$user_id	= $user['user_id'] ?? null;

		// bundle_id取得
		$bundle_id = $_POST['bundle_id'] ?? '';

		// データが不正な場合は一覧画面にリダイレクト
		if (empty($bundle_id) || ! $this->dao->existsCancelableBundle($user_id, $bundle_id)) {
			trigger_error(ERR_INVALID, E_USER_WARNING);
			return $this->displayStatusIndex();
		}

		try {

			// CSRFトークン検証
			\Common::validateCsrfToken();

			// トランザクション開始
			$this->dao->beginTransaction();

			// bundle_idからメール送信用のデータ取得
			$mail_queue = $this->dao->getRequestByBundleId($bundle_id);

			// 申請キャンセル
			$this->dao->cancelBundle($user_id, $bundle_id);

			// 成功したらコミット
			$this->dao->commitTransaction();

			// 取消メール送信処理を実行
			$this->_sendCancelMails($user, $mail_queue);

			// 削除フラグをセッションに保存
			$_SESSION['flash_action'] = 'cancel';

			// 一覧画面にリダイレクト
			header('Location: ' . BASE_URL . 'status/');
			exit;

		} catch (\Exception $e) {

			// 失敗したらロールバック
			$this->dao->rollbackTransaction();
			return $this->displayStatusIndex();
		}
	}

	/**
	 * 取消：メール送信
	 *
	 * @access	private
	 * @param	$user, $requests
	 * @return
	 */
	private function _sendCancelMails($user, $requests) {

		// ユーザー設定
		$user_name	= $user['name'] ?? '';	// 名前
		$user_auth	= $user['auth'] ?? '';	// 所属
		$user_email	= $user['mail'] ?? '';	// メールアドレス

		// 申請日データを整形
		$dates = [];
		foreach ($requests as $req) {
			$dates[] = [
				'display' => \Common::formatDateYmdLabel($req)
			];
		}

		// メールで表示させる適用ラベルの生成
		$apply_labels = \Common::buildApplyLabelsFromRequests($requests);

		$this->smarty->assign([
			'types'			=> \Common::$apply_types,
			'sub_types'		=> \Common::$apply_sub_types,
			'user_name'		=> $user_name,
			'requests'		=> $requests,
			'dates'			=> $dates,
			'apply_labels'	=> $apply_labels,
		]);

		// メール設定
		$subject	= '休暇申請が取り消されました';
		$body		= $this->smarty->fetch('user/mails/cancel.tpl');

		// メールアドレス配列
		$emails = [];

		// 本人
		if (! empty($user_email)) {
			$emails[] = $user_email;
		}

		// 所属長
		$boss_lists = $this->dao->getApplyMailByPosition(1, $user_auth);
		foreach ($boss_lists as $boss) {
			$emails[] = $boss['email'];
		}

		// 社長
		$manager_lists = $this->dao->getApplyMailByPosition(2);
		foreach ($manager_lists as $manager) {
			$emails[] = $manager['email'];
		}

		// 宛先の重複を削除
		$emails = array_unique($emails);

		// メールを個別送信
		foreach ($emails as $email) {
			\Common::sendMail($email, $subject, $body);
		}
	}
}
