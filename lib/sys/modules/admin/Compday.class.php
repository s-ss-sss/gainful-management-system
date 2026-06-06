<?php

// 名前空間
namespace App\Admin;

// 管理クラス共通ファイル
require_once ROOT_PATH . '/lib/sys/modules/admin/Base.class.php';

class Compday extends Base {

	/**
	 * 一覧：表示
	 *
	 * @access	public
	 * @param
	 * @return
	 */
	public function displayCompdayIndex() {

		// CSRFトークン検証
		\Common::validatePostCsrf();

		// アラートフラッシュメッセージ
		$this->assignFlashMessage('代休');

		// ユーザー一覧取得
		$users = $this->dao->getUsers();

		// 選択ユーザー
		$selected_user = $_POST['user'] ?? '';

		// 最古年度取得
		$oldest_year = $this->dao->getCompdayOldestYear();

		// 年度プルダウン生成
		$year_selector = \Common::buildYearSelector($oldest_year);

		// 年度配列
		$years = $year_selector['years'];

		// 選択年度
		$selected_year = $year_selector['selected_year'];

		// 代休一覧取得
		$rows = $this->dao->getCompdays($selected_user, $selected_year);

		// グルーピング作成
		$compday_lists = $this->_buildCompdayData($rows);

		// CSRFトークン作成
		$csrf_token = \Common::generateCsrfToken();

		$this->smarty->assign([
			'csrf_token'	=> $csrf_token,
			'users'			=> $users,
			'selected_user'	=> $selected_user,
			'years'			=> $years,
			'selected_year'	=> $selected_year,
			'compday_lists'	=> $compday_lists,
			'section'		=> 'compday',
		]);

		$this->smarty->display('admin/compday/index.tpl');
	}

	/**
	 * 一覧：ユーザー毎にデータ生成
	 *
	 * @access	private
	 * @param	$rows
	 * @return	$lists
	 */
	private function _buildCompdayData($rows) {

		// グルーピング作成
		$lists = [];
		foreach ($rows as $r) {

			// ユーザーID取得
			$user_id = $r['user_id'];

			// 日付データ整形
			$date_label = $this->formatAdminDateYmdLabel($r['work_date']);

			// 代休残日数計算
			$add_number	= (float)$r['add_number'];	// 0.5 or 1.0
			$remain		= $this->_determineCompdayRemain($add_number, $r['link_request_id1'], $r['link_request_id2']);

			// ユーザーのバケットが無ければ作成
			if (! isset($lists[$user_id])) {
				$lists[$user_id] = [
					'user_id'	=> $user_id,
					'user_name'	=> $r['user_name'],
					'records'	=> [],
				];
			}

			// 代休レコードを追加
			$lists[$user_id]['records'][] = [
				'id'			=> $r['id'],
				'work_date'		=> $date_label,
				'add_number'	=> $add_number,
				'reason'		=> $r['comment'],
				'remain'		=> $remain,
			];
		}

		return $lists;
	}

	/**
	 * 一覧：代休残日数計算
	 *
	 * @access	private
	 * @param	$add_number, $link1, $link2
	 * @return  max($remain, 0.0)
	 */
	private function _determineCompdayRemain($add_number, $link1, $link2) {

		// 代休スロット判定
		$slot1		= empty($link1) ? 0 : 1;
		$slot2		= empty($link2) ? 0 : 1;
		$used_slots	= $slot1 + $slot2;

		// 残日数計算
		$remain = $add_number - ($used_slots * 0.5);

		// 計算結果
		return max($remain, 0.0);
	}

	/**
	 * 登録：表示
	 *
	 * @access	public
	 * @param	$form_data = null, $errors = []
	 * @return
	 */
	public function displayCompdayCreateIndex($form_data = null, $errors = []) {

		// ユーザー一覧を取得
		$users = $this->dao->getUsers();

		// フォームデータ初期化
		if ($form_data === null) {
			$form_data = [
				'user_ids'		=> [],
				'work_date'		=> '',
				'add_number'	=> '',
				'reason'		=> '',
			];
		}

		// CSRFトークン作成
		$csrf_token = \Common::generateCsrfToken();

		$this->smarty->assign([
			'csrf_token'	=> $csrf_token,
			'apply_auth'	=> \Common::$apply_auth,
			'users'			=> $users,
			'add_numbers'	=> \Common::$grant_compday,
			'form_data'		=> $form_data,
			'errors'		=> $errors,
			'section'		=> 'compday',
		]);

		$this->smarty->display('admin/compday/create.tpl');
	}

	/**
	 * 登録：実行
	 *
	 * @access	public
	 * @param
	 * @return
	 */
	public function createCompday() {

		// フォームデータ取得
		$form_data = [
			'user_ids'		=> $_POST['user_ids'] ?? [],
			'work_date'		=> $_POST['work_date'] ?? '',
			'add_number'	=> $_POST['add_number'] ?? '',
			'reason'		=> $_POST['reason'] ?? '',
		];

		// バリデーション実行
		$errors = $this->_validateCompday($form_data);

		// エラー内容を再描画
		if (! empty($errors)) {
			return $this->displayCompdayCreateIndex($form_data, $errors);
		}

		try {

			// CSRFトークン検証
			\Common::validateCsrfToken();

			// トランザクション開始
			$this->dao->beginTransaction();

			// フォームデータ整形
			$user_ids	= $form_data['user_ids'];
			$params		= [
				'work_date'		=> date('Y-m-d', strtotime($form_data['work_date'])),
				'add_number'	=> (float)$form_data['add_number'],
				'reason'		=> $form_data['reason'],
			];

			// メール送信用の配列
			$mail_queue = [];

			// ユーザー毎に1件ずつ登録
			foreach ($user_ids as $user_id) {

				// フォームデータにユーザーID追加
				$params['user_id'] = $user_id;

				// 代休スロット登録
				$this->dao->insertCompday($params);

				// 代休残数を更新
				$this->dao->updateCompday($user_id, $params['add_number']);

				// メール送信用のデータを保存
				$mail_queue[] = [
					'user_id'		=> $user_id,
					'work_date'		=> $this->formatAdminDateYmdLabel($params['work_date']),
					'add_number'	=> $params['add_number'],
					'reason'		=> $params['reason'],
				];
			}

			// 成功したらコミット
			$this->dao->commitTransaction();

			// 付与メール送信処理を実行
			$this->_sendCompdayGrantMails($mail_queue);

			// 登録フラグをセッションに保存
			$_SESSION['flash_action'] = 'create';

			// 一覧画面にリダイレクト
			header('Location: ' . BASE_URL . 'admin/compday/');
			exit;

		} catch (\Exception $e) {

			// 失敗したらロールバック
			$this->dao->rollbackTransaction();
			return $this->displayCompdayIndex();
		}
	}

	/**
	 * 登録：メール送信
	 *
	 * @access	private
	 * @param	$mail_queue
	 * @return
	 */
	private function _sendCompdayGrantMails($mail_queue) {

		// メール送信
		foreach ($mail_queue as $data) {

			// ユーザー設定
			$user_id	= $data['user_id'];
			$user		= $this->dao->getUserDetailById($user_id);
			$user_name	= $user['name'] ?? '';	// 名前
			$user_auth	= $user['auth'] ?? '';	// 所属
			$user_email	= $user['mail'] ?? '';	// メールアドレス

			$this->smarty->assign([
				'user_name'		=> $user_name,
				'work_date'		=> $data['work_date'],
				'add_number'	=> $data['add_number'],
				'reason'		=> $data['reason'],
			]);

			// メール設定
			$subject	= '代休が付与されました';
			$body		= $this->smarty->fetch('admin/mails/compday.tpl');

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

	/**
	 * 修正：表示
	 *
	 * @access	public
	 * @param	$compday_id, $form_data = null, $errors = []
	 * @return
	 */
	public function displayCompdayEditIndex($compday_id, $form_data = null, $errors = []) {

		// 代休データ取得
		$row = $this->dao->getCompdayByCompdayId($compday_id);

		// データが不正な場合は一覧画面にリダイレクト
		if (empty($row)) {
			trigger_error(ERR_INVALID, E_USER_WARNING);
			return $this->displayCompdayIndex();
		}

		// 付与日数
		$add_numbers = \Common::$grant_compday;

		// 紐付け判定フラグ
		$is_linked = (
			! empty($row['link_request_id1']) ||
			! empty($row['link_request_id2'])
		);

		// フォームデータ初期化
		if ($form_data === null) {
			$form_data = [
				'compday_id'	=> $row['id'],
				'work_date'		=> date('Y/n/j', strtotime($row['work_date'])),
				'add_number'	=> (float)$row['add_number'],
				'reason'		=> $row['comment'],
			];
		}

		// CSRFトークン作成
		$csrf_token = \Common::generateCsrfToken();

		$this->smarty->assign([
			'csrf_token'		=> $csrf_token,
			'add_numbers'		=> $add_numbers,
			'is_linked'			=> $is_linked,
			'form_data'			=> $form_data,
			'errors'			=> $errors,
			'request_user_name'	=> $row['user_name'],
			'section'			=> 'compday',
		]);

		$this->smarty->display('admin/compday/edit.tpl');
	}

	/**
	 * 修正：実行
	 *
	 * @access	public
	 * @param
	 * @return
	 */
	public function editCompday() {

		// 修正対象ID取得
		$compday_id = $_POST['compday_id'] ?? '';

		// 修正対象IDが不正な場合は一覧画面にリダイレクト
		if (! $this->dao->existsCompdayId($compday_id)) {
			trigger_error(ERR_INVALID, E_USER_WARNING);
			return $this->displayCompdayIndex();
		}

		// 旧データ取得
		$row = $this->dao->getCompdayByCompdayId($compday_id);

		// 旧データが不正な場合は一覧画面にリダイレクト
		if (empty($row)) {
			trigger_error(ERR_INVALID, E_USER_WARNING);
			return $this->displayCompdayIndex();
		}

		// フォームデータ取得
		$form_data = [
			'compday_id'	=> $compday_id,
			'work_date'		=> $_POST['work_date'] ?? '',
			'add_number'	=> $_POST['add_number'] ?? null,
			'reason'		=> $_POST['reason'] ?? '',
		];

		// バリデーション実行
		$errors = $this->_validateCompday($form_data, $row, 'edit');

		// エラー内容を再描画
		if (! empty($errors)) {
			return $this->displayCompdayEditIndex($compday_id, $form_data, $errors);
		}

		try {

			// CSRFトークン検証
			\Common::validateCsrfToken();

			// トランザクション開始
			$this->dao->beginTransaction();

			// 更新データ作成
			$update_data = [
				'work_date'	=> date('Y-m-d', strtotime($form_data['work_date'])),
				'reason'	=> $form_data['reason'],
			];

			// 既に申請で消費されている場合は付与日数の変更不可
			$is_linked = (
				! empty($row['link_request_id1']) ||
				! empty($row['link_request_id2'])
			);

			// 紐付けなしの場合は付与日数追加
			if (! $is_linked && $form_data['add_number'] !== null) {
				$update_data['add_number'] = (float)$form_data['add_number'];
			}

			// 差分計算
			$before = (float)$row['add_number'];
			$after  = $update_data['add_number'] ?? $before;

			// 残日数更新
			$this->_rollbackCompday($row['user_id'], $before, $after);

			// 代休更新
			$this->dao->updateCompdayById($compday_id, $update_data);

			// 成功したらコミット
			$this->dao->commitTransaction();

			// 修正フラグをセッションに保存
			$_SESSION['flash_action'] = 'edit';

			// 一覧画面にリダイレクト
			header('Location: ' . BASE_URL . 'admin/compday/');
			exit;

		} catch (\Exception $e) {

			// 失敗したらロールバック
			$this->dao->rollbackTransaction();
			return $this->displayCompdayEditIndex($compday_id);
		}
	}

	/**
	 * バリデーション
	 *
	 * @access	private
	 * @param	$form_data, $row = null, $mode = 'create'
	 * @return	$errors
	 */
	private function _validateCompday($form_data, $row = null, $mode = 'create') {
		$errors = [];

		// ユーザーは登録時のみ
		if ($mode === 'create') {
			$user_ids = $form_data['user_ids'] ?? [];
			if ($user_ids == [] || ! is_array($user_ids)) {
				$errors['user_ids'] = 'ユーザーを選択してください';
			}
		}

		// 休日勤務日
		$work_date = $form_data['work_date'] ?? '';
		if ($work_date === '') {
			$errors['work_date'] = '休日勤務日を入力してください';
		} elseif (! preg_match('/^\d{4}\/([1-9]|1[0-2])\/([1-9]|[12]\d|3[01])$/', $work_date)) {
			$errors['work_date'] = '休日勤務日の形式が不正です';
		}

		// 紐付けチェック
		$is_linked = false;
		if ($row !== null) {
			$is_linked = (
				! empty($row['link_request_id1']) ||
				! empty($row['link_request_id2'])
			);
		}

		// 付与日数
		if (! $is_linked) {
			$add_number = $form_data['add_number'] ?? null;
			if ($add_number === null || $add_number === '') {
				$errors['add_number'] = '付与日数を選択してください';
			} elseif (! in_array((float)$add_number, \Common::$grant_compday, true)) {
				$errors['add_number'] = '付与日数の値が不正です';
			}
		}

		// 事由
		$reason = $form_data['reason'] ?? '';
		if ($reason === '') {
			$errors['reason'] = '事由を入力してください';
		} elseif (mb_strlen($reason) > 256) {
			$errors['reason'] = '事由は256文字以内で入力してください';
		}

		return $errors;
	}

	/**
	 * 削除
	 *
	 * @access	public
	 * @param
	 * @return
	 */
	public function deleteCompday() {

		// 削除対象ID取得
		$compday_id = $_POST['delete_id'] ?? '';

		// データが不正な場合は一覧画面にリダイレクト
		if ($compday_id === '' || ! $this->dao->existsCompdayId($compday_id)) {
			trigger_error(ERR_INVALID, E_USER_WARNING);
			return $this->displayCompdayIndex();
		}

		try {

			// CSRFトークン検証
			\Common::validateCsrfToken();

			// 削除対象の行取得
			$row = $this->dao->getCompdayByCompdayId($compday_id);

			// 紐付け判定フラグ
			$is_linked = (
				! empty($row['link_request_id1']) ||
				! empty($row['link_request_id2'])
			);

			// 紐付けチェック
			if ($is_linked) {

				// エラーフラグをセッションに保存
				$_SESSION['flash_action'] = 'error';

				// 一覧画面にリダイレクト
				return $this->displayCompdayIndex();
			}

			// トランザクション開始
			$this->dao->beginTransaction();

			// 差分計算
			$before	= (float)$row['add_number'];
			$after	= 0.0;

			// 残日数更新
			$this->_rollbackCompday($row['user_id'], $before, $after);

			// 代休データ削除
			$this->dao->deleteCompday($compday_id);

			// 成功したらコミット
			$this->dao->commitTransaction();

			// 削除フラグをセッションに保存
			$_SESSION['flash_action'] = 'delete';

			// 一覧画面にリダイレクト
			header('Location: ' . BASE_URL . 'admin/compday/');
			exit;

		} catch (\Exception $e) {

			// 失敗したらロールバック
			$this->dao->rollbackTransaction();
			return $this->displayCompdayIndex();
		}
	}

	/**
	 * 代休残数の差分反映
	 *
	 * @access	public
	 * @param   $user_id, $before, $after
	 * @return
	 */
	private function _rollbackCompday($user_id, $before, $after) {
		$diff = round($after - $before, 1);

		if ($diff !== 0.0) {
			$this->dao->updateCompday($user_id, $diff);
		}
	}
}
