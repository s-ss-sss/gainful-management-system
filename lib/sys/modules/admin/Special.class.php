<?php

// 名前空間
namespace App\Admin;

// ユーザークラス共通ファイル
require_once ROOT_PATH . '/lib/sys/modules/admin/Base.class.php';

class Special extends Base {

	/**
	 * 一覧：表示
	 *
	 * @access	public
	 * @param
	 * @return
	 */
	public function displaySpecialIndex() {

		// CSRFトークン検証
		\Common::validatePostCsrf();

		// アラートフラッシュメッセージ
		$this->assignFlashMessage('特別休暇');

		// ユーザー一覧取得
		$users = $this->dao->getUsers();

		// 選択ユーザー
		$selected_user = $_POST['user'] ?? '';

		// 最古年度取得
		$oldest_year = $this->dao->getSpecialOldestYear();

		// 年度プルダウン生成
		$year_selector = \Common::buildYearSelector($oldest_year);

		// 年度配列
		$years = $year_selector['years'];

		// 選択年度
		$selected_year = $year_selector['selected_year'];

		// 特別休暇一覧取得
		$rows = $this->dao->getSpecialList($selected_user, $selected_year);

		// グルーピング作成
		$special_lists = $this->_buildSpecialData($rows);

		// CSRFトークン作成
		$csrf_token = \Common::generateCsrfToken();

		$this->smarty->assign([
			'csrf_token'	=> $csrf_token,
			'users'			=> $users,
			'selected_user'	=> $selected_user,
			'years'			=> $years,
			'selected_year'	=> $selected_year,
			'special_lists'	=> $special_lists,
			'section'		=> 'special',
		]);

		$this->smarty->display('admin/special/index.tpl');
	}

	/**
	 * 一覧：ユーザー毎にデータ生成
	 *
	 * @access	private
	 * @param	$rows
	 * @return	$lists
	 */
	private function _buildSpecialData($rows) {

		// グルーピング作成
		$lists = [];
		foreach ($rows as $r) {

			// ユーザーID取得
			$user_id = $r['user_id'];

			// 日付データ整形
			$date_label = $this->formatAdminDateYmdLabel($r['grant_date']);

			// ユーザーのバケットが無ければ作成
			if (! isset($lists[$user_id])) {
				$lists[$user_id] = [
					'user_id'	=> $user_id,
					'user_name'	=> $r['user_name'],
					'records'	=> [],
				];
			}

			// 特別休暇レコードを追加
			$lists[$user_id]['records'][] = [
				'id'			=> $r['id'],
				'sub_type'		=> \Common::$apply_sub_types[$r['sub_kind']],
				'grant_date'	=> $date_label,
				'add_number'	=> (float)$r['add_number'],
				'reason'		=> $r['comment'],
				'remain'		=> $r['remain_number'],
			];
		}

		return $lists;
	}

	/**
	 * 登録：表示
	 *
	 * @access	public
	 * @param
	 * @return
	 */
	public function displaySpecialCreateIndex($form_data = null, $errors = []) {

		// ユーザー一覧を取得
		$users = $this->dao->getUsers();

		// フォームデータ初期化
		if ($form_data === null) {
			$form_data = [
				'user_ids'		=> [],
				'sub_type'		=> '',
				'grant_date'	=> '',
				'add_number'	=> '0.0',
				'reason'		=> '',
			];
		}

		// 種別はリフレッシュ休暇/結婚のみを設定
		$special_sub_types = [];
		foreach (\Common::$consume_sub_type_ids as $sub_type_id) {
			$special_sub_types[$sub_type_id] = \Common::$apply_sub_types[$sub_type_id];
		}

		// CSRFトークン作成
		$csrf_token = \Common::generateCsrfToken();

		$this->smarty->assign([
			'csrf_token'	=> $csrf_token,
			'apply_auth'	=> \Common::$apply_auth,
			'sub_types'		=> $special_sub_types,
			'users'			=> $users,
			'form_data'		=> $form_data,
			'errors'		=> $errors,
			'section'		=> 'special',
		]);

		$this->smarty->display('admin/special/create.tpl');
	}

	/**
	 * 登録：実行
	 *
	 * @access	public
	 * @param
	 * @return
	 */
	public function createSpecial() {

		// フォームデータ取得
		$form_data = [
			'user_ids'		=> $_POST['user_ids'] ?? [],
			'sub_type'		=> $_POST['sub_type'] ?? '',
			'grant_date'	=> $_POST['grant_date'] ?? '',
			'add_number'	=> $_POST['add_number'] ?? '',
			'reason'		=> $_POST['reason'] ?? '',
		];

		// バリデーション実行
		$errors = $this->_validateSpecial($form_data);

		// エラー内容を再描画
		if (! empty($errors)) {
			return $this->displaySpecialCreateIndex($form_data, $errors);
		}

		try {

			// CSRFトークン検証
			\Common::validateCsrfToken();

			// トランザクション開始
			$this->dao->beginTransaction();

			// ユーザーIDの配列を取得
			$user_ids = $form_data['user_ids'];

			// 有効期限の設定
			$grant_date		= strtotime($form_data['grant_date']);
			$expire_date	= $this->_calculateExpireDate($form_data['sub_type'], $grant_date);

			// フォームデータ整形
			$params = [
				'sub_type'		=> $form_data['sub_type'],
				'grant_date'	=> date('Y-m-d', $grant_date),
				'add_number'	=> (float)$form_data['add_number'],
				'reason'		=> $form_data['reason'],
				'expire_date'	=> $expire_date,
			];

			// メール送信用の配列
			$mail_queue = [];

			// ユーザー毎に1件ずつ登録
			foreach ($user_ids as $user_id) {

				// フォームデータにユーザーID追加
				$params['user_id'] = $user_id;

				// 特別休暇登録
				$this->dao->insertSpecial($params);

				// 特別休暇残数を更新
				$this->dao->updateSpecial($user_id, $params['add_number']);

				// メール送信用のデータを保存
				$mail_queue[] = [
					'user_id'		=> $user_id,
					'sub_type'		=> $params['sub_type'],
					'grant_date'	=> $this->formatAdminDateYmdLabel($params['grant_date']),
					'add_number'	=> $params['add_number'],
					'reason'		=> $params['reason'],
					'expire_date'	=> $this->formatAdminDateYmdLabel($expire_date),
				];
			}

			// 成功したらコミット
			$this->dao->commitTransaction();

			// 付与メール送信処理を実行
			$this->_sendSpecialGrantMails($mail_queue);

			// 登録フラグをセッションに保存
			$_SESSION['flash_action'] = 'create';

			// 一覧画面にリダイレクト
			header('Location: ' . BASE_URL . 'admin/special/');
			exit;

		} catch (\Exception $e) {

			// 失敗したらロールバック
			$this->dao->rollbackTransaction();
			return $this->displaySpecialIndex();
		}
	}

	/**
	 * 登録：メール送信
	 *
	 * @access	private
	 * @param	$mail_queue
	 * @return
	 */
	private function _sendSpecialGrantMails($mail_queue) {

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
				'sub_type'		=> \Common::$apply_sub_types[$data['sub_type']],
				'grant_date'	=> $data['grant_date'],
				'add_number'	=> $data['add_number'],
				'reason'		=> $data['reason'],
				'expire_date'	=> $data['expire_date'],
			]);

			// メール設定
			$subject	= '特別休暇が付与されました';
			$body		= $this->smarty->fetch('admin/mails/special.tpl');

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
	 * @param	$special_id, $form_data = null, $errors = []
	 * @return
	 */
	public function displaySpecialEditIndex($special_id, $form_data = null, $errors = []) {

		// 特別休暇データ取得
		$row = $this->dao->getSpecialBySpecialId($special_id);

		// データが不正な場合は一覧画面にリダイレクト
		if (empty($row)) {
			trigger_error(ERR_INVALID, E_USER_WARNING);
			return $this->displaySpecialIndex();
		}

		// ロック判定
		$flags		= $this->_getSpecialLockFlags($row);
		$is_locked	= $flags['is_locked'];

		// フォームデータ初期化
		if ($form_data === null) {
			$form_data = [
				'special_id'	=> $row['id'],
				'sub_type'		=> $row['sub_kind'],
				'grant_date'	=> date('Y/n/j', strtotime($row['grant_date'])),
				'add_number'	=> (float)$row['add_number'],
				'reason'		=> $row['comment'],
			];
		}

		// 種別はリフレッシュ休暇/結婚のみを設定
		$special_sub_types = [];
		foreach (\Common::$consume_sub_type_ids as $sub_type_id) {
			$special_sub_types[$sub_type_id] = \Common::$apply_sub_types[$sub_type_id];
		}

		// CSRFトークン作成
		$csrf_token = \Common::generateCsrfToken();

		$this->smarty->assign([
			'csrf_token'		=> $csrf_token,
			'sub_types'			=> $special_sub_types,
			'is_locked'			=> $is_locked,
			'form_data'			=> $form_data,
			'errors'			=> $errors,
			'request_user_name'	=> $row['user_name'],
			'section'			=> 'special',
		]);

		$this->smarty->display('admin/special/edit.tpl');
	}

	/**
	 * 修正：実行
	 *
	 * @access	public
	 * @param
	 * @return
	 */
	public function editSpecial() {

		// 修正対象ID取得
		$special_id = $_POST['special_id'] ?? '';

		// 修正対象IDが不正な場合は一覧画面にリダイレクト
		if (! $this->dao->existsSpecialId($special_id)) {
			trigger_error(ERR_INVALID, E_USER_WARNING);
			return $this->displaySpecialIndex();
		}

		// 旧データ取得
		$row = $this->dao->getSpecialBySpecialId($special_id);

		// 旧データが不正な場合は一覧画面にリダイレクト
		if (empty($row)) {
			trigger_error(ERR_INVALID, E_USER_WARNING);
			return $this->displaySpecialIndex();
		}

		// フォームデータ取得
		$form_data = [
			'special_id'	=> $special_id,
			'sub_type'		=> $_POST['sub_type'] ?? null,
			'grant_date'	=> $_POST['grant_date'] ?? '',
			'add_number'	=> $_POST['add_number'] ?? null,
			'reason'		=> $_POST['reason'] ?? '',
		];

		// バリデーション実行
		$errors = $this->_validateSpecial($form_data, $row, 'edit');

		// エラー内容を再描画
		if (! empty($errors)) {
			return $this->displaySpecialEditIndex($special_id, $form_data, $errors);
		}

		try {

			// CSRFトークン検証
			\Common::validateCsrfToken();

			// トランザクション開始
			$this->dao->beginTransaction();

			// 有効期限の設定
			$grant_date		= strtotime($form_data['grant_date']);
			$expire_date	= $this->_calculateExpireDate($form_data['sub_type'], $grant_date);

			// 更新データ作成
			$update_data = [
				'grant_date'	=> date('Y-m-d', $grant_date),
				'reason'		=> $form_data['reason'],
				'expire_date'	=> $expire_date,
			];

			// ロック判定
			$flags		= $this->_getSpecialLockFlags($row);
			$is_locked	= $flags['is_locked'];

			// 紐付けなしの場合に追加
			if (! $is_locked) {

				// 種別
				$update_data['sub_type'] = $form_data['sub_type'];

				// 付与日数
				if ($form_data['add_number'] !== null) {
					$update_data['add_number'] = (float)$form_data['add_number'];
				}

				// 差分計算
				$before = (float)$row['add_number'];
				$after  = $update_data['add_number'] ?? $before;

				// 残日数更新
				$this->_rollbackSpecial($row['user_id'], $before, $after);
			}

			// 特別休暇更新
			$this->dao->updateSpecialById($special_id, $update_data);

			// 成功したらコミット
			$this->dao->commitTransaction();

			// 修正フラグをセッションに保存
			$_SESSION['flash_action'] = 'edit';

			// 一覧画面にリダイレクト
			header('Location: ' . BASE_URL . 'admin/special/');
			exit;

		} catch (\Exception $e) {

			// 失敗したらロールバック
			$this->dao->rollbackTransaction();
			return $this->displaySpecialEditIndex($special_id);
		}
	}

	/**
	 * バリデーション
	 *
	 * @access	private
	 * @param	$form_data, $row = null, $mode = 'create'
	 * @return	$errors
	 */
	private function _validateSpecial($form_data, $row = null, $mode = 'create') {
		$errors = [];

		// ユーザーは登録時のみ
		if ($mode === 'create') {
			$user_ids = $form_data['user_ids'] ?? [];
			if ($user_ids == [] || ! is_array($user_ids)) {
				$errors['user_ids'] = 'ユーザーを選択してください';
			}
		}

		// ロック判定
		$flags = [
			'is_used'		=> false,
			'is_expired'	=> false,
		];

		if ($row !== null) {
			$flags = $this->_getSpecialLockFlags($row);
		}

		// 紐付けなしで有効期限が切れていない場合
		if (! $flags['is_used'] && ! $flags['is_expired']) {

			// 種別
			$sub_type = $form_data['sub_type'] ?? null;
			if ($sub_type === null || $sub_type === '') {
				$errors['sub_type'] = '種別を選択してください';
			} elseif (! in_array((int)$sub_type, \Common::$consume_sub_type_ids, true)) {
				$errors['sub_type'] = '種別の値が不正です';
			}

			// 付与日
			$grant_date = $form_data['grant_date'] ?? '';
			if ($grant_date === '') {
				$errors['grant_date'] = '付与日を入力してください';
			} elseif (! preg_match('/^\d{4}\/([1-9]|1[0-2])\/([1-9]|[12]\d|3[01])$/', $grant_date)) {
				$errors['grant_date'] = '付与日の形式が不正です';

			// 有効期限
			} else {
				$grant_timestamp = strtotime(str_replace('/', '-', $grant_date));
				if ($grant_timestamp !== false && empty($errors['sub_type'])) {
					$expire_date = $this->_calculateExpireDate($sub_type, $grant_timestamp);
					if ($expire_date < date('Y-m-d')) {
						$errors['grant_date'] = '有効期限が既に切れている日付は登録できません';
					}
				}
			}

			// 付与日数
			$add_number = $form_data['add_number'] ?? null;
			if ($add_number === null || $add_number === '') {
				$errors['add_number'] = '付与日数を選択してください';
			} elseif (! is_numeric($add_number)) {
				$errors['add_number'] = '付与日数の値が不正です';
			} else {

				// float変換
				$float = (float)$add_number;
				if ($float <= 0.0) {
					$errors['add_number'] = '付与日数は0.0日より大きい値で入力してください';
				} elseif (floor($float * 2) !== ($float * 2)) {
					$errors['add_number'] = '付与日数は0.5日単位で入力してください';
				}
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
	 * 有効期限の設定
	 *
	 * @access	private
	 * @param	$sub_type, $grant_date
	 * @return	string
	 */
	private function _calculateExpireDate($sub_type, $grant_date) {

		// リフレッシュ休暇は1年
		if ($sub_type === '0') {
			return date('Y-m-d', strtotime('+1 year', $grant_date));

		// 結婚は6ヶ月
		} elseif ($sub_type === '1') {
			return date('Y-m-d', strtotime('+6 months', $grant_date));
		}
	}

	/**
	 * 削除
	 *
	 * @access	public
	 * @param
	 * @return
	 */
	public function deleteSpecial() {

		// 修正対象ID取得
		$special_id = $_POST['delete_id'] ?? '';

		// 修正対象IDが不正な場合は一覧画面にリダイレクト
		if (! $this->dao->existsSpecialId($special_id)) {
			trigger_error(ERR_INVALID, E_USER_WARNING);
			return $this->displaySpecialIndex();
		}

		try {

			// CSRFトークン検証
			\Common::validateCsrfToken();

			// 削除対象の行取得
			$row = $this->dao->getSpecialBySpecialId($special_id);

			// ロック判定
			$flags		= $this->_getSpecialLockFlags($row);
			$is_locked	= $flags['is_locked'];

			// 紐付けチェック
			if ($is_locked) {

				// エラーフラグをセッションに保存
				$_SESSION['flash_action'] = $flags['is_used'] ? 'error' : 'expired';

				// 一覧画面にリダイレクト
				return $this->displaySpecialIndex();
			}

			// トランザクション開始
			$this->dao->beginTransaction();

			// 差分計算
			$before = (float)$row['add_number'];
			$after  = 0.0;

			// 残日数更新
			$this->_rollbackSpecial($row['user_id'], $before, $after);

			// 特別休暇データ削除
			$this->dao->deleteSpecial($special_id);

			// 成功したらコミット
			$this->dao->commitTransaction();

			// 削除フラグをセッションに保存
			$_SESSION['flash_action'] = 'delete';

			// 一覧画面にリダイレクト
			header('Location: ' . BASE_URL . 'admin/special/');
			exit;

		} catch (\Exception $e) {

			// 失敗したらロールバック
			$this->dao->rollbackTransaction();
			return $this->displaySpecialIndex();
		}
	}

	/**
	 * ロック判定
	 *
	 * @access	private
	 * @param	$row
	 * @return	array
	 */
	private function _getSpecialLockFlags($row) {

		// 既に申請で消費されている場合は付与日数の変更不可
		$is_used = ((float)$row['used_number'] > 0);

		// 有効期限切れの場合は付与日数の変更不可
		$is_expired = (
			! empty($row['expire_date']) &&
			$row['expire_date'] < date('Y-m-d')
		);

		return [
			'is_used'		=> $is_used,
			'is_expired'	=> $is_expired,
			'is_locked'		=> ($is_used || $is_expired),
		];
	}

	/**
	 * 特別休暇残数の差分反映
	 *
	 * @access	public
	 * @param	$user_id, $before, $after
	 * @return
	 */
	private function _rollbackSpecial($user_id, $before, $after) {
		$diff = round($after - $before, 1);

		if ($diff !== 0.0) {
			$this->dao->updateSpecial($user_id, $diff);
		}
	}
}
