<?php

// 名前空間
namespace App\Admin;

// 管理クラス共通ファイル
require_once ROOT_PATH . '/lib/sys/modules/admin/Base.class.php';

class User extends Base {

	/**
	 * 一覧：表示
	 *
	 * @access	public
	 * @param
	 * @return
	 */
	public function displayUserIndex() {

		// 社長権限チェック
		$this->checkManager();

		// アラートフラッシュメッセージ
		$this->assignFlashMessage('ユーザー');

		// ユーザー一覧取得
		$users = $this->dao->getUsers();

		// ラベルを整形して参照渡しで配列に追加
		foreach ($users as &$user) {
			$user['auth_label']			= \Common::$apply_auth[$user['auth']] ?? '';				// 所属
			$user['join_date_label']	= $this->_formatDateLabel($user['join_date']) ?? '';		// 入社年月日
			$user['year_label']			= $this->_determineYears($user['join_date']) . '年目' ?? '';	// 勤続年数
		}

		// 参照渡しをリセット
		unset($user);

		// CSRFトークン作成
		$csrf_token = \Common::generateCsrfToken();

		$this->smarty->assign([
			'csrf_token'	=> $csrf_token,
			'users'			=> $users,
			'section'		=> 'user',
		]);

		$this->smarty->display('admin/user/index.tpl');
	}

	/**
	 * 一覧：入社年月日整形
	 *
	 * @access	private
	 * @param	$data
	 * @return	"{$date}（{$weekday}）"
	 */
	private function _formatDateLabel($data) {

		// 承認データを整形
		$timestamp	= strtotime($data);
		$weekday	= \Common::$weekdays[date('w', $timestamp)];
		$date		= date('Y/n/j', $timestamp);

		// 表示用フォーマット
		return "{$date}（{$weekday}）";
	}

	/**
	 * 一覧：勤続年数算出
	 *
	 * @access	private
	 * @param	$join_data
	 * @return	$diff->y
	 */
	private function _determineYears($join_data) {
		$join	= new \DateTime($join_data);
		$now	= new \DateTime();
		$diff	= $join->diff($now);
		return $diff->y + 1;
	}

	/**
	 * 登録：表示
	 *
	 * @access	public
	 * @param
	 * @return
	 */
	public function displayUserCreateIndex($form_data = null, $errors = []) {

		// 社長権限チェック
		$this->checkManager();

		// フォームデータ初期化
		if ($form_data === null) {
			$form_data = [
				'user_id'			=> '',
				'name'				=> '',
				'join_date'			=> '',
				'auth'				=> '',
				'mail'				=> '',
				'holiday_number'	=> '0.0',
				'position'			=> '',
			];
		}

		// CSRFトークン作成
		$csrf_token = \Common::generateCsrfToken();

		$this->smarty->assign([
			'csrf_token'		=> $csrf_token,
			'apply_auth'		=> \Common::$apply_auth,
			'apply_position'	=> \Common::$apply_position,
			'form_data'			=> $form_data,
			'errors'			=> $errors,
			'section'			=> 'user',
		]);

		$this->smarty->display('admin/user/create.tpl');
	}

	/**
	 * 登録：実行
	 *
	 * @access	public
	 * @param
	 * @return
	 */
	public function createUser() {

		// 社長権限チェック
		$this->checkManager();

		// フォームデータ取得
		$form_data = [
			'user_id'			=> $_POST['user_id'] ?? '',
			'name'				=> $_POST['name'] ?? '',
			'join_date'			=> $_POST['join_date'] ?? '',
			'auth'				=> $_POST['auth'] ?? '',
			'mail'				=> $_POST['mail'] ?? '',
			'password'			=> $_POST['password'] ?? '',
			'holiday_number'	=> $_POST['holiday_number'] ?? '',
			'position'			=> $_POST['position'] ?? '',
		];

		// バリデーション実行
		$errors = $this->_validateUser($form_data);

		// エラー内容を再描画
		if (! empty($errors)) {
			return $this->displayUserCreateIndex($form_data, $errors);
		}

		// 登録用データに整形
		$timestamp						= strtotime($form_data['join_date']);
		$form_data['join_date']			= date('Y-m-d', $timestamp);
		$form_data['holiday_number']	= (float)$form_data['holiday_number'];
		$form_data['password']			= password_hash($form_data['password'], PASSWORD_DEFAULT);

		try {

			// デモユーザー共通ガード
			$this->guardDemo();

			// CSRFトークン検証
			\Common::validateCsrfToken();

			// トランザクション開始
			$this->dao->beginTransaction();

			// ユーザー登録
			$this->dao->insertUser($form_data);

			// 成功したらコミット
			$this->dao->commitTransaction();

			// 登録フラグをセッションに保存
			$_SESSION['flash_action'] = 'create';

			// 一覧画面にリダイレクト
			header('Location: ' . BASE_URL . 'admin/user/');
			exit;

		} catch (\Exception $e) {

			// 失敗したらロールバック
			$this->dao->rollbackTransaction();
			return $this->displayUserCreateIndex();
		}
	}

	/**
	 * 修正：表示
	 *
	 * @access	public
	 * @param	$user_id, $form_data = null, $errors = []
	 * @return
	 */
	public function displayUserEditIndex($user_id, $form_data = null, $errors = []) {

		// 社長権限チェック
		$this->checkManager();

		// データが不正な場合は一覧画面にリダイレクト
		if (! ctype_digit($user_id) || ! $this->dao->existsUserId($user_id)) {
			trigger_error(ERR_INVALID, E_USER_WARNING);
			return $this->displayUserIndex();
		}

		// ユーザーIDから合致するデータ取得
		$user	= $this->dao->getUserDetailById($user_id);
		$join	= $user['join_date'];

		// フォームデータ初期化
		if ($form_data === null) {

			// join_dateを整形
			$join_date = '';
			if (! empty($user['join_date'])) {
				$timestamp	= strtotime($join);
				$join_date	= date('Y/n/j', $timestamp);
			}

			$form_data = [
				'user_id'			=> $user['user_id'],
				'name'				=> $user['name'],
				'join_date'			=> $join_date,
				'auth'				=> $user['auth'],
				'mail'				=> $user['mail'],
				'holiday_number'	=> $user['holiday_number'],
				'compday_number'	=> $user['compday_number'],
				'special_number'	=> $user['special_number'],
				'position'			=> $user['position'],
			];
		}

		// 次年度有休付与日数
		$next_holiday = $this->_determineNextHolidayNumber($join);

		// CSRFトークン作成
		$csrf_token = \Common::generateCsrfToken();

		$this->smarty->assign([
			'csrf_token'		=> $csrf_token,
			'apply_auth'		=> \Common::$apply_auth,
			'apply_position'	=> \Common::$apply_position,
			'original_id'		=> $user_id,
			'next_holiday'		=> $next_holiday,
			'user'				=> $user,
			'form_data'			=> $form_data,
			'errors'			=> $errors,
			'section'			=> 'user',
		]);

		$this->smarty->display('admin/user/edit.tpl');
	}

	/**
	 * 修正：実行
	 *
	 * @access	public
	 * @param
	 * @return
	 */
	public function editUser() {

		// 社長権限チェック
		$this->checkManager();

		// 修正対象ID取得
		$original_id = $_POST['original_id'] ?? '';

		// 修正対象IDが不正な場合は一覧画面にリダイレクト
		if (! ctype_digit($original_id) || ! $this->dao->existsUserId($original_id)) {
			trigger_error(ERR_INVALID, E_USER_WARNING);
			return $this->displayUserIndex();
		}

		// フォームデータ取得
		$form_data = [
			'user_id'			=> $_POST['user_id'] ?? '',
			'name'				=> $_POST['name'] ?? '',
			'join_date'			=> $_POST['join_date'] ?? '',
			'auth'				=> $_POST['auth'] ?? '',
			'mail'				=> $_POST['mail'] ?? '',
			'password'			=> $_POST['password'] ?? '',
			'holiday_number'	=> $_POST['holiday_number'] ?? '',
			'position'			=> $_POST['position'] ?? '',
		];

		// バリデーション実行
		$errors = $this->_validateUser($form_data, $original_id);

		// エラー内容を再描画
		if (! empty($errors)) {
			return $this->displayUserEditIndex($original_id, $form_data, $errors);
		}

		// 登録用データに整形
		$timestamp						= strtotime($form_data['join_date']);
		$form_data['join_date']			= date('Y-m-d', $timestamp);
		$form_data['holiday_number']	= (float)$form_data['holiday_number'];

		// パスワードをハッシュ化
		if (! empty($form_data['password'])) {
			$form_data['password'] = password_hash($form_data['password'], PASSWORD_DEFAULT);
		}

		try {

			// デモユーザー共通ガード
			$this->guardDemo();

			// CSRFトークン検証
			\Common::validateCsrfToken();

			// トランザクション開始
			$this->dao->beginTransaction();

			// ユーザー修正
			$this->dao->editUser($original_id, $form_data);

			// 成功したらコミット
			$this->dao->commitTransaction();

			// 修正フラグをセッションに保存
			$_SESSION['flash_action'] = 'edit';

			// 一覧画面にリダイレクト
			header('Location: ' . BASE_URL . 'admin/user/');
			exit;

		} catch (\Exception $e) {

			// 失敗したらロールバック
			$this->dao->rollbackTransaction();
			return $this->displayUserEditIndex($original_id);
		}
	}

	/**
	 * バリデーション：共通
	 *
	 * @access	private
	 * @param
	 * @return
	 */
	private function _validateUser($form_data, $current_id = null) {
		$errors = [];

		// ID
		$user_id = $form_data['user_id'] ?? '';
		if ($user_id === '') {
			$errors['user_id'] = 'IDを入力してください';
		} elseif (! preg_match('/^[0-9]+$/', $user_id)) {
			$errors['user_id'] = 'IDは半角数字で入力してください';
		} elseif (strlen($user_id) > 10) {
			$errors['user_id'] = 'IDは10桁以内で入力してください';
		} elseif ($this->dao->existsUserId($user_id, $current_id)) {
			$errors['user_id'] = 'このIDは既に登録されています';
		}

		// 氏名
		$name = $form_data['name'] ?? '';
		if ($name === '') {
			$errors['name'] = '氏名を入力してください';
		} elseif (mb_strlen($name) > 256) {
			$errors['name'] = '氏名は256文字以内で入力してください';
		}

		// 入社年月日
		$join_date		= $form_data['join_date'] ?? '';
		if ($join_date === '') {
			$errors['join_date'] = '入社年月日を入力してください';
		} elseif (! preg_match('/^\d{4}\/([1-9]|1[0-2])\/([1-9]|[12]\d|3[01])$/', $join_date)) {
			$errors['join_date'] = '入社年月日の形式が不正です';
		}

		// 所属
		$auth = $form_data['auth'] ?? '';
		if ($auth === '') {
			$errors['auth'] = '所属を選択してください';
		} elseif (! array_key_exists($auth, \Common::$apply_auth)) {
			$errors['auth'] = '所属の値が不正です';
		}

		// メールアドレス
		$mail = $form_data['mail'] ?? '';
		if ($mail === '') {
			$errors['mail'] = 'メールアドレスを入力してください';
		} elseif (! filter_var($mail, FILTER_VALIDATE_EMAIL)) {
			$errors['mail'] = 'メールアドレスの形式が正しくありません';
		} elseif ($this->dao->existsUserMail($mail, $current_id)) {
			$errors['mail'] = 'このメールアドレスは既に使用されています';
		}

		// パスワード（新規）
		if ($current_id === null && empty($form_data['password'])) {
			$errors['password'] = 'パスワードを入力してください';
		}

		// パスワード（入力）
		if (! empty($form_data['password'])) {
			if (! preg_match('/^[\x21-\x7E]+$/', $form_data['password'])) {
				$errors['password'] = 'パスワードは半角英数記号で入力してください';
			} elseif (strlen($form_data['password']) < 8) {
				$errors['password'] = 'パスワードは8文字以上で入力してください';
			} elseif (strlen($form_data['password']) > 64) {
				$errors['password'] = 'パスワードが長すぎます';
			}
		}

		// 有休残日数
		if ($error = $this->_validateHalfDates($form_data['holiday_number'], '有休残日数')) {
			$errors['holiday_number'] = $error;
		}

		// 権限
		$position = $form_data['position'] ?? '';
		if ($position === '') {
			$errors['position'] = '権限を選択してください';
		} elseif (! array_key_exists($position, \Common::$apply_position)) {
			$errors['position'] = '権限の値が不正です';
		}

		return $errors;
	}

	/**
	 * バリデーション：残日数
	 *
	 * @access	private
	 * @param	$number, $field
	 * @return	string, null
	 */
	private function _validateHalfDates($number, $field) {

		// 入力チェック
		if ($number === '') {
			return "{$field}を入力してください";
		} elseif (! preg_match('/^-?\d+(\.\d+)?$/', $number)) {
			return "{$field}は半角数字で入力してください";
		} else {

			// float変換
			$float = (float)$number;

			// 0.5単位チェック
			if (floor($float * 2) !== ($float * 2)) {
				return "{$field}は0.5日単位で入力してください";
			}
		}

		return null;
	}

	/**
	 * 削除
	 *
	 * @access	public
	 * @param
	 * @return
	 */
	public function deleteUsers() {

		// 社長権限チェック
		$this->checkManager();

		// ユーザーID取得
		$user_id = $_POST['delete_id'] ?? '';

		// データが不正な場合は一覧画面にリダイレクト
		if (! ctype_digit($user_id) || ! $this->dao->existsUserId($user_id)) {
			trigger_error(ERR_INVALID, E_USER_WARNING);
			return $this->displayUserIndex();
		}

		try {

			// デモユーザー共通ガード
			$this->guardDemo();

			// CSRFトークン検証
			\Common::validateCsrfToken();

			// トランザクション開始
			$this->dao->beginTransaction();

			// ユーザー削除
			$this->dao->deleteUser($user_id);

			// 成功したらコミット
			$this->dao->commitTransaction();

			// 削除フラグをセッションに保存
			$_SESSION['flash_action'] = 'delete';

			// 一覧画面にリダイレクト
			header('Location: ' . BASE_URL . 'admin/user/');
			exit;

		} catch (\Exception $e) {

			// 失敗したらロールバック
			$this->dao->rollbackTransaction();
			return $this->displayUserIndex();
		}
	}

	/**
	 * 次年度有休付与日数
	 *
	 * @access	private
	 * @param	$join_date
	 * @return
	 */
	private function _determineNextHolidayNumber($join_date) {

		// 空チェック
		if (empty($join_date)) {
			return 0;
		}

		// 現在日付
		$today	= new \DateTime();

		// 入社半年後日付
		$join			= new \DateTime($join_date);
		$first_grant	= (clone $join)->modify('+6 months');

		// 半年未満の場合は次年度が10日
		if ($today < $first_grant) {
			return \Common::$grant_holiday[0];
		}

		// 経過年数を算出
		$diff_years = $first_grant->diff($today)->y;

		// 次年度付与回数
		$next_index = $diff_years + 1;

		// 次年度の上限は20日
		if ($next_index > 6) {
			$next_index = 6;
		}

		return \Common::$grant_holiday[$next_index];
	}
}
