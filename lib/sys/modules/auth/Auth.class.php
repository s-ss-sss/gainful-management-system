<?php

require_once ROOT_PATH . '/lib/sys/modules/Common.class.php';

class Auth extends \Common {

	/**
	 * コンストラクタ：インスタンス生成
	 *
	 * @access	public
	 * @param	$dao
	 * @return
	 */
	public function __construct($dao) {
		parent::__construct($dao);
	}

	/**
	 * 未ログイン時のリダイレクト処理
	 *
	 * @access	private
	 * @param
	 * @return
	 */
	public function checkLogin() {
		if (empty($_SESSION['gainful']['UserID'])) {
			header('Location: ' . BASE_URL . 'login');
			exit;
		}
	}

	/**
	 * セッションタイムアウト
	 *
	 * @access	public
	 * @param
	 * @return
	 */
	public function checkSessionTimeout() {

		// ログインしていなければ処理を抜ける
		if (empty($_SESSION['gainful']['UserID'])) {
			return;
		}

		// 30分に設定
		$timeout = 60 * 30;

		if (! empty($_SESSION['gainful']['last_active']) &&
			(time() - $_SESSION['gainful']['last_active']) > $timeout) {

			// ログアウト処理
			$this->logout();
		}

		// アクセスがあればセッション更新
		$_SESSION['gainful']['last_active'] = time();
	}

	/**
	 * 管理者フラグの取得
	 *
	 * @access	public
	 * @param
	 * @return
	 */
	public function setAdminFlag() {

		// ユーザーID取得
		$user_id = $_SESSION['gainful']['UserID'] ?? null;

		// 管理者フラグを取得
		$user = $this->dao->getUserById($user_id);

		// セッションに管理者フラグ保存
		$_SESSION['gainful']['is_admin'] = (! empty($user) && ($user['position'] == '1' || $user['position'] == '2')) ? '1' : '0';
	}

	/**
	 * ログイン認証
	 *
	 * @access	private
	 * @param	$email, $password
	 * @return	$user
	 */
	private function _login($email, $password) {

		// ログイン時のパスワード認証
		$user = $this->_authenticate($email, $password);

		// 認証失敗時はfalseで抜ける
		if (! $user) {
			return false;
		}

		// 新しいセッションに入れ替える
		session_regenerate_id(true);

		// ログインデータをセッションに保存
		$_SESSION['gainful'] = [
			'UserID'		=> $user['user_id'],
			'UserName'		=> $user['name'],
			'is_admin'		=> ($user['position'] == '1' || $user['position'] == '2') ? '1' : '0',
			'is_demo'		=> ($user['mail'] === MAIL_DEMO),
			'last_active'	=> time(),
		];

		return $user;
	}

	/**
	 * ログイン時のパスワード認証
	 *
	 * @access	public
	 * @param	$email, $password
	 * @return	$user
	 */
	private function _authenticate($email, $password) {

		// ログイン時のユーザーデータの取得
		$user = $this->dao->getUserByEmail($email);

		// ユーザーデータの取得とパスワード照合
		if (! $user || ! password_verify($password, $user['password'])) {
			return false;
		}

		return $user;
	}

	/**
	 * ログイン画面表示
	 *
	 * @access	public
	 * @param
	 * @return
	 */
	public function displayLogin() {

		// ログイン済みならトップページにリダイレクト
		if (! empty($_SESSION['gainful']['UserID'])) {
			header('Location: ' . BASE_URL);
			exit;
		}

		// ログイン実行時の処理
		$form_data	= [];
		$errors		= [];
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {

			// フォームデータ取得
			$form_data = [
				'email'		=> trimFull($_POST['email'] ?? ''),
				'password'	=> trimFull($_POST['password'] ?? ''),
			];

			// バリデーション実行
			$errors = $this->_validateLogin($form_data);

			// エラー内容を再描画
			if (! empty($errors)) {
				return $this->_renderLogin($form_data, $errors);
			}

			try {

				// CSRFトークン検証
				$this->validateCsrfToken();

				// ログイン認証
				$user = $this->_login($form_data['email'], $form_data['password']);

				// 失敗時はログ出力
				if (! $user) {
					$errors['auth'] = true;
					trigger_error('メールアドレスまたはパスワードが正しくありません', E_USER_WARNING);
					return $this->_renderLogin($form_data, $errors);
				}

				// 成功時はトップページにリダイレクト
				header('Location: ' . BASE_URL);
				exit;

			} catch (Exception $e) {
				return $this->_renderLogin($form_data, $errors);
			}
		}

		return $this->_renderLogin($form_data, $errors);
	}

	/**
	 * ログイン画面描画
	 *
	 * @access	public
	 * @param	$form_data, $errors
	 * @return
	 */
	private function _renderLogin($form_data, $errors) {
		global $smarty;

		$smarty->assign([
			'csrf_token'	=> $this->generateCsrfToken(),
			'form_data'		=> $form_data,
			'errors'		=> $errors
		]);

		$smarty->display('auth/login.tpl');
	}

	/**
	 * バリデーション：ログイン
	 *
	 * @access	public
	 * @param	$form_data
	 * @return	$errors
	 */
	private function _validateLogin($form_data) {
		$errors = [];

		// メールアドレス
		if (empty($form_data['email'])) {
			$errors['email'] = 'メールアドレスを入力してください';
		}

		// パスワード
		if (empty($form_data['password'])) {
			$errors['password'] = 'パスワードを入力してください';
		}

		return $errors;
	}

	/**
	 * ログアウト処理
	 *
	 * @access	public
	 * @param
	 * @return
	 */
	public function logout() {

		// セッション削除
		session_destroy();

		// Cookie削除
		setcookie(session_name(), '', time() - 3600, '/');

		// ログイン画面にリダイレクト
		header('Location: ' . BASE_URL . 'login');
		exit;
	}
}
