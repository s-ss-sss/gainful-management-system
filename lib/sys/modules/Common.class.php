<?php

class Common {

	protected $dao, $smarty;

	// 曜日
	public static $weekdays = ['日', '月', '火', '水', '木', '金', '土'];

	// 区分
	public static $apply_sections = [
		0 => '午前',
		1 => '午後',
		2 => '全休',
	];

	// 種別
	public static $apply_types = [
		0 => '通常',
		1 => '特別',
	];

	// サブ種別（特別休暇）
	public static $apply_sub_types = [
		0 => 'リフレッシュ休暇',
		1 => '結婚',
		2 => '配偶者の出産',
		3 => '葬儀（2親等まで）',
		4 => '傷病（入社後半年以内）',
		5 => 'その他',
	];

	// 日数消費する特別休暇
	public static $consume_sub_type_ids = [
		0,
		1,
	];

	// 所属
	public static $apply_auth = [
		0 => '営業G',
		1 => 'ITインフラG',
		2 => 'Web制作G',
		3 => 'システム開発G',
		4 => 'その他',
	];

	// 権限
	public static $apply_position = [
		0 => '一般社員',
		1 => '所属長（休暇承認）',
		2 => '社長（休暇承認・ユーザー管理・申請データ管理）',
	];

	// 有休付与日数
	public static $grant_holiday = [
		0 => 10,	// 0.5年後
		1 => 11,	// 1.5年後
		2 => 12,	// 2.5年後
		3 => 14,	// 3.5年後
		4 => 16,	// 4.5年後
		5 => 18,	// 5.5年後
		6 => 20,	// 6.5年後
	];

	// 代休付与日数
	public static $grant_compday = [0.5, 1.0];

	/**
	 * コンストラクタ
	 *
	 * @access	public
	 * @param	$dao
	 * @return
	 */
	public function __construct($dao) {
		global $smarty;

		// インスタンス生成（依存性注入）
		$this->dao		= $dao;
		$this->smarty	= $smarty;

		// 管理画面のアクセス判定
		$admin_script = (strpos($_SERVER['REQUEST_URI'] ?? '', '/admin/') !== false);

		// 直通URLのパラメータ判定
		$direct_params = (isset($_GET['key']) && isset($_GET['u']));

		// ログインチェック実行判定
		$direct_access = ($admin_script && $direct_params);

		// 直通URL判定
		if ($direct_access) {

			// アクセスフラグ取得
			$auth = $_GET['auth'] ?? null;

			// 初回アクセスは中間ページに遷移
			if (empty($auth)) {
				$this->_showLoginGuide();
			}

			// 中間ページからのアクセスは直通URLアクセス実行
			$this->_handleDirectAccess();
		}

		// 共通変数をSmartyにアサイン
		$this->_assignCommonVars();
	}

	/**
	 * 直通URLの管理画面ログイン処理
	 *
	 * @access	private
	 * @param
	 * @return
	 */
	private function _showLoginGuide() {

		// 現在のURLを取得
		$current_url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

		$this->smarty->assign([
			'BASE_URL'		=> BASE_URL,
			'redirect_url'	=> $current_url,
		]);

		$this->smarty->display('auth/direct.tpl');
		exit;
	}

	/**
	 * 直通URLアクセス
	 *
	 * @access	private
	 * @param
	 * @return
	 */
	private function _handleDirectAccess() {

		// GETパラメータ
		$key		= $_GET['key'] ?? null;
		$user_id	= $_GET['u'] ?? null;

		// GETパラメータチェック
		if (! $key || ! $user_id) {
			displayNotFound();
		}

		// 該当データの有効期限取得
		$request = $this->dao->getRequestByUrlExpireAt($key, $user_id);

		// データチェック
		if (! $request) {
			echo 'URLが不正です。';
			exit;
		}

		// 有効期限チェック
		if (time() <= strtotime($request['url_expire_date'])) {

			// ログインセッション付与
			$_SESSION['gainful']['UserID'] = $user_id;

			// 承認一覧に遷移
			header('Location: ' . BASE_URL . 'admin/');
			exit;
		} else {
			echo 'URLの有効期限が切れています。ログイン画面からログインしてください。';
			exit;
		}
	}

	/**
	 * 共通変数をSmartyにアサイン
	 *
	 * @access	private
	 * @param
	 * @return
	 */
	private function _assignCommonVars() {

		// ログイン中のユーザーIDからデータ取得
		$user = $this->getLoginUser();

		// 初期値
		$user_name	= '';
		$position	= 0;

		// ユーザー情報取得
		if (! empty($user)) {
			$user_name	= $user['name'] ?? '';
			$position	= (int)($user['position'] ?? 0);
		}

		$this->smarty->assign([
			'BASE_URL'	=> BASE_URL,
			'SITE_NAME'	=> SITE_NAME,
			'user_name'	=> $user_name,
			'position'	=> $position,
		]);
	}

	/**
	 * ログインユーザー取得
	 *
	 * @access	public
	 * @param
	 * @return	$user
	 */
	public function getLoginUser() {

		// SSOからユーザーID取得
		$user_id = $_SESSION['gainful']['UserID'] ?? null;

		// ユーザーID確認
		if (empty($user_id)) {
			return null;
		}

		// ユーザー情報取得
		$user = $this->dao->getUserById($user_id);

		// ユーザー情報確認
		if (empty($user)) {
			return null;
		}

		return $user;
	}

	/**
	 * アラートフラッシュメッセージ
	 *
	 * @access	protected
	 * @param	$default_target = ''
	 * @return
	 */
	public function assignFlashMessage($default_target = '') {

		// セッションフラグがなければ終了
		if (empty($_SESSION['flash_action'])) {
			return;
		}

		// セッションから取得
		$action	= $_SESSION['flash_action'];
		$target	= $_SESSION['flash_target'] ?? $default_target;
		$count	= $_SESSION['flash_count'] ?? '';

		// メッセージ作成
		$map = [
			'approve'	=> "{$count}件の申請を承認しました",
			'reject'	=> '申請を却下しました',
			'create'	=> "{$target}を登録しました",
			'edit'		=> "{$target}を修正しました",
			'delete'	=> "{$target}を削除しました",
			'cancel'	=> "{$target}を取り消しました",
			'error'		=> '申請と紐付いているため削除できません',
			'expired'	=> '有効期限が切れているため削除できません',
		];

		// メッセージ生成
		$flash_message = $map[$action] ?? '';

		// Smartyにアサイン
		if ($flash_message !== '') {
			$this->smarty->assign('flash_message', $flash_message);
		}

		// セッションクリア
		unset($_SESSION['flash_action'], $_SESSION['flash_target'], $_SESSION['flash_count']);
	}

	/**
	 * CSRFトークン作成
	 *
	 * @access	public
	 * @param
	 * @return	$csrf_token
	 */
	public static function generateCsrfToken() {
		$csrf_token 			= bin2hex(random_bytes(32));
		$_SESSION['csrf_token']	= $csrf_token;
		return $csrf_token;
	}

	/**
	 * CSRFトークン検証
	 *
	 * @access	public
	 * @param
	 * @return
	 */
	public static function validateCsrfToken() {

		// CSRFトークン検証
		$post_token 	= $_POST['csrf_token'] ?? '';
		$session_token 	= $_SESSION['csrf_token'] ?? '';

		// CSRFトークン一致チェック
		if (empty($post_token) || empty($session_token) || ! hash_equals($session_token, $post_token)) {
			trigger_error(ERR_CSRF, E_USER_WARNING);
			throw new Exception(ERR_CSRF);
		}
	}

	/**
	 * プルダウンPOST時のCSRF検証
	 *
	 * @access	public
	 * @param
	 * @return
	 */
	public static function validatePostCsrf() {
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			try {
				self::validateCsrfToken();
			} catch (Exception $e) {
				return;
			}
		}
	}

	/**
	 * 社内APIから祝日データ取得
	 *
	 * @access	public
	 * @param	$start = null, $end = null
	 * @return	$holidays
	 */
	public static function getHolidays($start = null, $end = null) {

		// 祝日データURL
		$url = HOLIDAYS_API_URL;

		// パラメータ設定
		$params = [];

		// 開始日
		if ($start) {
			$params[] = "start={$start}";
		}

		// 終了日
		if ($end) {
			$params[] = "end={$end}";
		}

		// URL形成
		if (! empty($params)) {
			$url .= '?' . implode('&', $params);
		}

		// APIからデータ取得
		$json = @file_get_contents($url);
		if ($json === false) {
			return [];
		}

		// JSONをデコード
		$data = json_decode($json, true);
		if (! is_array($data) || ($data['status'] ?? '') !== 'OK') {
			return [];
		}

		// 祝日データ整形
		$holidays = [];
		foreach ($data['list'] as $holiday) {
			$day	= date('Y-m-d', strtotime($holiday['day'])) ?? '';	// 年月日
			$name	= $holiday['name'] ?? '';							// 祝日名
			if ($day && $name) {
				$holidays[$day] = $name;
			}
		}

		return $holidays;
	}

	/**
	 * メール送信
	 *
	 * @access	public
	 * @param
	 * @return	$headers
	 */
	public static function sendMail($to, $subject, $body) {

		// デモユーザーは送信せず処理を抜ける
		if ($to === MAIL_DEMO) {
			return true;
		}

		// 文字コード設定
		mb_language('Japanese');
		mb_internal_encoding('UTF-8');

		// エンコード
		$encoded_from	= mb_encode_mimeheader(SITE_NAME, 'ISO-2022-JP', 'B');	// 差出人
		$encoded_body	= mb_convert_encoding($body, 'ISO-2022-JP', 'UTF-8');	// 本文

		// ヘッダー生成
		$headers	= [];
		$headers[]	= "From: {$encoded_from} <" . MAIL_FROM . '>';
		$headers[]	= 'Reply-To: ' . MAIL_FROM;
		$headers[]	= 'Content-Type: text/plain; charset=ISO-2022-JP';
		$header		= implode("\r\n", $headers);

		// メール送信
		if (! mb_send_mail($to, $subject, $encoded_body, $header, '-f '.MAIL_FROM)) {
			trigger_error(ERR_MAIL, E_USER_WARNING);
			return false;
		}

		return true;
	}

	/**
	 * 日付データ整形：年/月/日（曜日）区分
	 *
	 * @access	public
	 * @param	$data
	 * @return	"{$date}（{$weekday}）{$section}"
	 */
	public static function formatDateYmdLabel($data) {

		// 日付を整形
		$timestamp	= strtotime($data['start_date']);
		$weekday	= self::$weekdays[date('w', $timestamp)];
		$date		= date('Y/n/j', $timestamp);

		// 区分を整形
		$section = self::formatSectionLabel($data);

		// 表示用フォーマット
		return "{$date}（{$weekday}）{$section}";
	}

	/**
	 * 日付データ整形：月/日（曜日）区分
	 *
	 * @access	public
	 * @param	$data
	 * @return	"{$date}（{$weekday}）{$section}"
	 */
	public static function formatDateMdLabel($data) {

		// 日付を整形
		$timestamp	= strtotime($data['start_date']);
		$weekday	= self::$weekdays[date('w', $timestamp)];
		$date		= date('n/j', $timestamp);

		// 区分を追加
		$section = self::formatSectionLabel($data);

		// 表示用フォーマット
		return "{$date}（{$weekday}）{$section}";
	}

	/**
	 * 日付データ整形：日（曜日）区分
	 *
	 * @access	public
	 * @param	$data
	 * @return	"{$date}日（{$weekday}）{$section}"
	 */
	public static function formatDateDLabel($data) {

		// 日付を整形
		$timestamp	= strtotime($data['start_date']);
		$weekday	= self::$weekdays[date('w', $timestamp)];
		$date		= date('j', $timestamp);

		// 区分を追加
		$section = self::formatSectionLabel($data);

		// 表示用フォーマット
		return "{$date}日（{$weekday}）{$section}";
	}

	/**
	 * 区分データ整形
	 *
	 * @access	public
	 * @param	$data
	 * @return	string
	 */
	public static function formatSectionLabel($data) {

		// マスタデータ
		$section = self::$apply_sections;

		if ($data['start_am_pm'] == '0' && $data['end_am_pm'] == '0') {
			return $section[0];
		} elseif ($data['start_am_pm'] == '1' && $data['end_am_pm'] == '1') {
			return $section[1];
		} else {
			return $section[2];
		}
	}

	/**
	 * 区分データの値整形
	 *
	 * @access	public
	 * @param	$data
	 * @return	string
	 */
	public static function formatSectionValue($data) {
		if ($data['start_am_pm'] == '0' && $data['end_am_pm'] == '0') {
			return 0;
		} elseif ($data['start_am_pm'] == '1' && $data['end_am_pm'] == '1') {
			return 1;
		} else {
			return 2;
		}
	}

	/**
	 * 種別データ整形
	 *
	 * @access	public
	 * @param	$data
	 * @return	string
	 */
	public static function formatTypeLabel($data) {

		// マスタデータ
		$type		= self::$apply_types;
		$sub_type	= self::$apply_sub_types;

		if ($data['kind'] == '1') {
			return $type[1] . '：' . $sub_type[$data['sub_kind']];
		} else {
			return $type[0];
		}
	}

	/**
	 * 代休/有休の消費日数算出
	 *
	 * @access	public
	 * @param	$consume_days, $compday_remain, $holiday_remain
	 * @return	array
	 */
	public static function consumeLeaveUnits($consume_days, $compday_remain, $holiday_remain) {

		// 半休/全休の消費日数
		$remain_days = $consume_days;

		// 代休はマイナス禁止で算出
		$compday_used	 = min($remain_days, $compday_remain);
		$compday_remain	-= $compday_used;
		$remain_days	-= $compday_used;

		// 有休はマイナス許容で算出
		$holiday_used	 = $remain_days;
		$holiday_remain	-= $holiday_used;

		return [
			'compday_used'		=> $compday_used,
			'holiday_used'		=> $holiday_used,
			'compday_remain'	=> $compday_remain,
			'holiday_remain'	=> $holiday_remain,
		];
	}

	/**
	 * 半休/全休の消費日数算出
	 *
	 * @access	private
	 * @param	$start, $end
	 * @return	float
	 */
	public static function determineLeaveUnits($start, $end) {

		// 午前/午後の場合は全休
		if ($start == '0' && $end == '1') {
			return 1.0;
		}

		// 上記以外は半休
		return 0.5;
	}

	/**
	 * メールで表示させる適用ラベルの生成
	 *
	 * @access	public
	 * @param	$requests
	 * @return	array
	 */
	public static function buildApplyLabelsFromRequests($requests) {

		// メールで表示させる適用カウントを更新
		$total_holiday	= 0.0;
		$total_compday	= 0.0;
		$total_special	= 0.0;

		foreach ($requests as $req) {
			$total_holiday	+= (float) $req['holiday_number'];
			$total_compday	+= (float) $req['compday_number'];
			$total_special	+= (float) $req['special_number'];
		}

		// メールで表示させる適用ラベルの振り分け
		$labels = [];

		// 有休
		if ($total_holiday > 0) {
			$labels[] = [
				'label'	=> '有休',
				'days'	=> number_format($total_holiday, 1),
			];
		}

		// 代休
		if ($total_compday > 0) {
			$labels[] = [
				'label'	=> '代休',
				'days'	=> number_format($total_compday, 1),
			];
		}

		// 特別休暇
		if ($total_special > 0) {
			$labels[] = [
				'label'	=> '特別休暇',
				'days'	=> number_format($total_special, 1),
			];
		}

		// 日数消費なし
		if (empty($labels)) {
			$labels[] = [
				'label'	=> '日数消費なし',
				'days'	=> null,
			];
		}

		return $labels;
	}

	/**
	 * 指定年度から期間算出
	 *
	 * @access	public
	 * @param	$year
	 * @return	array
	 */
	public static function getFiscalPeriod($year) {
		return [
			'start'	=> sprintf('%d-02-21', $year),
			'end'	=> sprintf('%d-02-20', $year + 1),
		];
	}

	/**
	 * 現在年月日から現在年度算出
	 *
	 * @access	public
	 * @param	$year
	 * @return	int
	 */
	public static function getFiscalYearFromDate($date) {

		// 現在年月日
		$today = new DateTime($date);

		// 現在年
		$year = (int)$today->format('Y');

		// 締日（現在年の2月21日）
		$cutoff = new DateTime("{$year}-02-21");

		// 現在年月日が締日より前なら1年戻す
		if ($today < $cutoff) {
			return $year - 1;
		}

		// 現在年を返す
		return $year;
	}

	/**
	 * 年度プルダウン生成
	 *
	 * @access	public
	 * @param	$oldest_year
	 * @return	array
	 */
	public static function buildYearSelector($oldest_year) {

		// 現在年度取得
		$current_year = self::getFiscalYearFromDate(date('Y-m-d'));

		// 年度プルダウン生成
		if ($oldest_year === null) {
			$years = [$current_year];
		} else {
			$years = range($oldest_year, $current_year);
		}

		// 選択年度
		$selected_year = $_POST['year'] ?? $current_year;

		// 不正な年度は現在年度に補正
		if (! in_array($selected_year, $years)) {
			$selected_year = $current_year;
		}

		return [
			'years'			=> $years,
			'selected_year'	=> $selected_year,
		];
	}

	/**
	 * 日付から締日年月取得（21〜20日）
	 *
	 * @access	public
	 * @param	$date
	 * @return	int
	 */
	public static function getClosingYearMonth($date) {

		$ts		= strtotime($date);
		$year	= (int)date('Y', $ts);
		$month	= (int)date('n', $ts);
		$day	= (int)date('j', $ts);

		// 20日以降は+1ヶ月
		if ($day > 20) {
			$month++;

			// 13なら1月に補正
			if ($month === 13) {
				$month = 1;
				$year++;
			}
		}

		return [
			'year'	=> $year,
			'month'	=> $month
		];
	}

	/**
	 * 年月から検索期間生成
	 *
	 * @access	public
	 * @param	$year, $month
	 * @return	array
	 */
	public static function buildClosingPeriod($year, $month) {

		// 当月20日
		$to = date('Y-m-d', strtotime(sprintf('%d-%02d-20', $year, $month)));

		// 前月21日
		$from = date('Y-m-d', strtotime("$to -1 month +1 day"));

		return [$from, $to];
	}

	/**
	 * 承認：休暇日数消費
	 *
	 * @access	private
	 * @param	$requests
	 * @return
	 */
	public function consumeApprovalDays($requests) {

		// ユーザーIDから合致するデータ取得
		$user_id	= $requests[0]['user_id'];
		$user		= $this->dao->getUserDetailById($user_id);

		// 休暇の残日数取得
		$compday_remain	= (float)$user['compday_number'];
		$holiday_remain	= (float)$user['holiday_number'];
		$special_remain	= (float)$user['special_number'];

		// 各申請行の処理
		foreach ($requests as $req) {

			// 半休/全休の消費日数算出
			$consume_days = self::determineLeaveUnits($req['start_am_pm'], $req['end_am_pm']);

			// 通常
			if ($req['kind'] == '0') {

				// 代休/有休の残日数算出
				$result 		= self::consumeLeaveUnits($consume_days, $compday_remain, $holiday_remain);
				$compday_used	= $result['compday_used'];
				$holiday_used	= $result['holiday_used'];
				$compday_remain	= $result['compday_remain'];
				$holiday_remain	= $result['holiday_remain'];

				// t_requestテーブル更新
				$this->dao->updateRequestDayCount($req['id'], [
					'holiday_number'	=> $holiday_used,
					'compday_number'	=> $compday_used,
				]);

				// 代休を消費した場合はt_compdayテーブル更新
				if ($compday_used > 0) {
					$this->linkCompdayUnits($user_id, $req['id'], $compday_used);
				}

				// t_usersテーブル更新
				$this->dao->updateUsersDayCount($user_id, $holiday_remain, $compday_remain);

				// 特別休暇
			} elseif ($req['kind'] == '1') {

				// リフレッシュ休暇と結婚の場合に消費
				if (in_array((int)$req['sub_kind'], self::$consume_sub_type_ids, true)) {

					// 消費可能な特別休暇取得
					$special = $this->dao->getActiveSpecialGrant($user_id, $req['sub_kind']);

					// データが見つからない場合は例外に投げる
					if (! $special) {
						throw new RuntimeException('Special grant not found');
					}

					// t_requestテーブル更新
					$this->dao->updateRequestDayCount($req['id'], [
						'special_number' => $consume_days
					]);

					// 特別休暇IDを紐付け
					$this->dao->linkSpecialGrantToRequest($req['id'], $special['id']);

					// t_usersテーブル更新
					$special_remain -= $consume_days;
					$this->dao->updateUsersSpecialCount($user_id, $special_remain);
				}
			}
		}
	}

	/**
	 * 代休リンク付与
	 *
	 * @access	protected
	 * @param	$user_id, $request_id, $compday_used
	 * @return
	 */
	public function linkCompdayUnits($user_id, $request_id, $compday_used) {

		// 代休の消費日数を0.5で1単位と数える
		$units = round($compday_used * 2);

		// 代休のリンクを紐付け可能枠を取得
		$lots = $this->dao->getCompdayLink($user_id);

		foreach ($lots as $lot) {

			// 紐付け可能枠が0以下で終了
			if ($units <= 0) {
				break;
			}

			// link_request_id1を埋める
			if (empty($lot['link_request_id1']) && $units > 0) {

				// 代休リンク更新
				$affected = $this->dao->updateCompdayLink($user_id, $request_id, $lot['id'], 1);

				// 更新がある場合は紐付け可能枠を減らす
				if ($affected === 1) {
					$units--;
				}
			}

			// link_request_id2を埋める
			if (empty($lot['link_request_id2']) && $units > 0 && (float)$lot['add_number'] === 1.0) {

				// 代休リンク更新
				$affected = $this->dao->updateCompdayLink($user_id, $request_id, $lot['id'], 2);

				// 更新がある場合は紐付け可能枠を減らす
				if ($affected === 1) {
					$units--;
				}
			}
		}
	}

	/**
	 * デモユーザー共通ガード
	 *
	 * @access	public
	 * @param
	 * @return
	 */
	public function guardDemo() {
		if (! empty($_SESSION['gainful']['is_demo'])) {
			trigger_error(ERR_DEMO, E_USER_WARNING);
			throw new Exception(LOG_DEMO);
		}
	}
}
