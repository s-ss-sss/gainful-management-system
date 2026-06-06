<?php

// 名前空間
namespace App\User;

// ユーザークラス共通ファイル
require_once ROOT_PATH . '/lib/sys/modules/user/Base.class.php';

class Apply extends Base {

	/**
	 * 一覧：表示
	 *
	 * @access	public
	 * @param
	 * @return
	 */
	public function displayApplyIndex($form_data = null, $errors = []) {

		// ログイン中のユーザーIDからデータ取得
		$user		= $this->getLoginUser();
		$user_id	= $user['user_id'] ?? null;

		// フォームデータ取得
		if ($form_data === null) {
			$form_data = $_SESSION['form_data'] ?? [
				'date'		=> [],
				'section'	=> [],
				'type'		=> '',
				'sub_type'	=> '',
				'reason'	=> '',
			];

			// セッションクリア
			unset($_SESSION['form_data']);
		}

		// CSRFトークン作成
		$csrf_token = \Common::generateCsrfToken();

		$this->smarty->assign([
			'csrf_token'	=> $csrf_token,
			'weekdays'		=> \Common::$weekdays,
			'sections'		=> \Common::$apply_sections,
			'types'			=> \Common::$apply_types,
			'sub_types'		=> \Common::$apply_sub_types,
			'holidays'		=> \Common::getHolidays(),
			'form_data'		=> $form_data,
			'form_date'		=> $form_data['date'],
			'form_section'	=> $form_data['section'],
			'errors'		=> $errors,
			'current_year'	=> date('Y'),
			'current_month'	=> date('n'),
			'summary'		=> $this->dao->getApplyDaysSummary($user_id),
			'section'		=> 'apply',
		]);

		// 完了のリダイレクトフラグがあれば完了画面に遷移
		if (! empty($_SESSION['redirect_complete'])) {

			// セッションクリア
			unset($_SESSION['redirect_complete']);

			return $this->smarty->display('user/apply/complete.tpl');
		}

		$this->smarty->display('user/apply/index.tpl');
	}

	/**
	 * 確認：表示
	 *
	 * @access	public
	 * @param
	 * @return
	 */
	public function displayApplyConfirm() {

		// フォームデータ取得
		$form_data = [
			'date'		=> $_POST['date'] ?? [],
			'section'	=> $_POST['section'] ?? [],
			'type'		=> $_POST['type'] ?? '',
			'sub_type'	=> $_POST['sub_type'] ?? '',
			'reason'	=> $_POST['reason'] ?? '',
		];

		// バリデーション実行
		$errors = $this->_validateApply($form_data);

		// エラー内容を再描画
		if (! empty($errors)) {
			return $this->displayApplyIndex($form_data, $errors);
		}

		try {
			// CSRFトークン検証
			\Common::validateCsrfToken();

			$form_data = $_POST;

			// 種別が通常の場合はサブ種別をリセット
			if (isset($form_data['type']) && $form_data['type'] === '0') {
				$form_data['sub_type'] = '';
			}

			// 申請日フォーマットを整形
			$dates = $this->_formatDatesLabel($form_data);

			// 申請日件数と合計日数計算
			$summary = $this->_calculateTotalDays($form_data['date'], $form_data['section']);

			// セッションにフォームデータ保存
			$_SESSION['form_data'] = $form_data;

			// CSRFトークン作成
			$csrf_token = \Common::generateCsrfToken();

			$this->smarty->assign([
				'csrf_token'	=> $csrf_token,
				'sections'		=> \Common::$apply_sections,
				'types'			=> \Common::$apply_types,
				'sub_types'		=> \Common::$apply_sub_types,
				'form_data'		=> $form_data,
				'dates'			=> $dates,
				'summary'		=> $summary,
				'section'		=> 'apply',
			]);

			$this->smarty->display('user/apply/confirm.tpl');

		} catch (\Exception $e) {
			return $this->displayApplyIndex();
		}
	}

	/**
	 * 確認：申請日件数と合計日数計算
	 *
	 * @access	private
	 * @param	$dates, $sections
	 * @return	array
	 */
	private function _calculateTotalDays($dates, $sections) {

		// 申請日件数カウント
		$count = count($dates);

		// 合計日数の初期値は0
		$total = 0;

		// 区分から合計日数の算出
		foreach ($sections as $section) {

			// 全休は合計日数+1
			if ((string)$section === '2') {
				$total += 1;

			// 午前/午後は;0.5
			} else {
				$total += 0.5;
			}
		}

		// 申請日件数と合計日数を返す
		return [
			'count'	=> $count,
			'total'	=> number_format($total, 1)
		];
	}

	/**
	 * バリデーション：共通
	 *
	 * @access	private
	 * @param	$form_data
	 * @return	$errors
	 */
	private function _validateApply($form_data) {
		$errors = [];

		// 申請日
		$date_errors = $this->_validateApplyDates($form_data);
		if (! empty($date_errors)) {
			$errors = array_merge($errors, $date_errors);
		}

		// 種別
		$type = $form_data['type'] ?? null;
		if (! isset($type) || $type == '') {
			$errors['type'] = '種別を選択してください';
		} elseif (! array_key_exists($type, \Common::$apply_types)) {
			$errors['type'] = '種別の値が不正です';
		}

		// サブ種別
		if ($type == '1') {

			$sub_type = $form_data['sub_type'] ?? null;
			if (! isset($sub_type) || $sub_type == '') {
				$errors['sub_type'] = '特別休暇の種別を選択してください';
			} elseif (! array_key_exists($sub_type, \Common::$apply_sub_types)) {
				$errors['sub_type'] = '特別休暇の値が不正です';

				// リフレッシュ休暇/結婚
			} elseif ($sub_type == '0' || $sub_type == '1') {

				// 特別休暇残日数
				$special_errors = $this->_validateApplySpecial($form_data, $sub_type);
				if (! empty($special_errors)) {
					$errors = array_merge($errors, $special_errors);
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
	 * バリデーション：特別休暇残日数
	 *
	 * @access	private
	 * @param	$form_data
	 * @return	$errors
	 */
	private function _validateApplySpecial($form_data, $sub_type) {
		$errors = [];

		// POSTされた値をintにキャスト
		$sub_type = (int)$sub_type;

		// 日数消費しない特別休暇はチェック不要
		if (! in_array($sub_type, \Common::$consume_sub_type_ids, true)) {
			return $errors;
		}

		// ログイン中のユーザーIDからデータ取得
		$user		= $this->getLoginUser();
		$user_id	= $user['user_id'] ?? null;

		// 表示用ラベルを作成
		$label = \Common::$apply_sub_types[$sub_type] ?? '特別休暇';

		// 特別休暇の有効期限をチェック
		$expire_errors = $this->_validateSpecialExpireDate($user_id, $sub_type, $form_data, $label);
		if (! empty($expire_errors)) {
			return $expire_errors;
		}

		// サブ種別毎の特別休暇の付与合計
		$granted = $this->dao->getTotalSpecialGranted($user_id, $sub_type);

		// サブ種別毎の承認済みの特別休暇の消費合計
		$approved = $this->dao->getApprovedSpecialConsumed($user_id, $sub_type);

		// サブ種別毎の承認待ち特別休暇の消費日数
		$pending = $this->dao->getPendingSpecialConsume($user_id, $sub_type);

		// 承認待ち特別休暇の消費合計
		$pending_days = 0.0;
		foreach ($pending as $r) {
			$pending_days += \Common::determineLeaveUnits($r['start_am_pm'], $r['end_am_pm']);
		}

		// 残日数
		$available = $granted - $approved - $pending_days;

		// 申請日の合計を計算
		$total_days = 0.0;
		$sections = $form_data['section'] ?? [];
		foreach ($sections as $sec) {

			// 全休=2の場合は1.0で午前=0/午後=1は0.5消費
			switch ($sec) {
				case '2'	: $total_days += 1.0; break;	// 全休
				case '0'	:								// 午前
				case '1'	: $total_days += 0.5; break;	// 午後
				default		: break;
			}
		}

		// 特別休暇残日数判定
		if ($total_days > $available) {
			$errors['sub_type'] = "{$label}の残日数が不足しています";
		}

		return $errors;
	}

	/**
	 * バリデーション：特別休暇の有効期限
	 *
	 * @access	private
	 * @param	$user_id, $sub_type, $form_data, $label
	 * @return	$errors
	 */
	private function _validateSpecialExpireDate($user_id, $sub_type, $form_data, $label) {
		$errors = [];

		// 特別休暇の有効期限を取得
		$expire_date = $this->dao->getActiveSpecialExpireDate($user_id, $sub_type);

		// 有効期限がなければチェックしない
		if ($expire_date !== null) {

			// 特別休暇の有効期限をチェック
			$expire	= new \DateTimeImmutable($expire_date);
			$date	= $form_data['date'] ?? [];
			foreach ($date as $d) {
				$apply_date = new \DateTimeImmutable($d);

				// 特別休暇の有効期限判定
				if ($apply_date > $expire) {
					$errors['sub_type'] = "{$label}の有効期限（{$expire->format('Y/n/j')}）が過ぎている日付では申請できません";
					return $errors;
				}
			}
		}

		return $errors;
	}

	/**
	 * バリデーション：申請日
	 *
	 * @access	private
	 * @param	$form_data
	 * @return	$errors
	 */
	private function _validateApplyDates($form_data) {
		$errors = [];

		// 入力チェック
		if (empty($form_data['date'])) {
			$errors['date'] = '申請日を選択してください';
			return $errors;
		}

		// 現在申請中の重複チェック用の配列
		$seen		= [];	// 日付＋区分の配列
		$sections	= [];	// 区分の配列

		// 重複チェック
		foreach ($form_data['date'] as $i => $date) {

			// 区分を取得
			$section = isset($form_data['section'][$i]) ? $form_data['section'][$i] : '';

			// フォーマットチェック
			if (! preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/', $date)) {
				$errors['date'] = '申請日の形式が不正です';
				break;
			}

			// 同日チェック
			$key = "{$date}_{$section}";
			if (isset($seen[$key])) {
				$errors['date'] = '申請日が重複しています';
				break;
			}
			$seen[$key] = true;

			// 初回は初期化
			if (! isset($sections[$date])) {
				$sections[$date] = [];
			}

			// 全休の取得
			$has_full = in_array(2, $sections[$date], true);

			// 午前/午後の取得
			$has_half = array_filter($sections[$date], fn($s) => (int)$s != '2');

			// 全休＋午前/午後チェック
			if (
				($has_full && $section != '2') ||
				(! empty($has_half) && $section == '2')
			) {
				$errors['date'] = '全休と他の区分は同日に申請できません';
				break;
			}

			// 区分を登録
			$sections[$date][] = $section;
		}

		// 現在申請中の重複があれば返す
		if (! empty($errors)) {
			return $errors;
		}

		// 承認・未承認データの重複チェック
		$active_errors = $this->_validateActiveRequestsByDates($form_data);

		// 承認・未承認データの重複があれば返す
		if (! empty($active_errors)) {
			return $active_errors;
		}

		return $errors;
	}

	/**
	 * バリデーション：承認・未承認データの重複チェック
	 *
	 * @access	private
	 * @param	$form_data
	 * @return	$errors
	 */
	private function _validateActiveRequestsByDates($form_data) {
		$errors = [];

		// ログイン中のユーザーIDからデータ取得
		$user		= $this->getLoginUser();
		$user_id	= $user['user_id'] ?? null;

		// 承認・未承認データの重複チェック用に日付をまとめる
		$unique = array_unique($form_data['date']);

		// 対象日付の承認・未承認データ取得
		$existing = $this->dao->getActiveRequestsByDates($user_id, $unique);

		// 申請日チェック
		foreach ($form_data['date'] as $i => $date) {

			// 区分を取得
			$section = isset($form_data['section'][$i]) ? $form_data['section'][$i] : '';

			// 区分から時間帯を決定
			switch ($section) {
				case '2'	: $new_start = '0'; $new_end = '1'; break;				// 全休
				default		: $new_start = $section; $new_end = $section; break;	// 午前/午後
			}

			// 承認・未承認データの重複チェック
			foreach ($existing as $row) {

				// 重複日がなければ処理を抜けて続行
				if ($row['start_date'] !== $date) {
					continue;
				}

				// 開始時間と終了時間を取得
				$exist_start	= $row['start_am_pm'];
				$exist_end		= $row['end_am_pm'];

				// 重なり判定
				if (! ($exist_end < $new_start || $new_end < $exist_start)) {
					$errors['date'] = 'この期間は既に申請済みの休暇申請があります';

					// 二重ループを抜ける
					break 2;
				}
			}
		}

		return $errors;
	}

	/**
	 * 完了：表示
	 *
	 * @access	public
	 * @param
	 * @return
	 */
	public function displayApplyComplete() {

		// 休暇申請登録
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			return $this->_registApply();
		}

		// 通常アクセスで完了画面
		$this->smarty->assign('section', 'apply');
		$this->smarty->display('user/apply/complete.tpl');
	}

	/**
	 * 登録
	 *
	 * @access	private
	 * @param
	 * @return
	 */
	private function _registApply() {

		// ログイン中のユーザーIDからデータ取得
		$user		= $this->getLoginUser();
		$user_id	= $user['user_id'] ?? null;

		// 権限判定
		$user_position	= $user['position'] ?? '0';
		$is_boss		= ($user_position == '1');	// 所属長権限
		$is_manager		= ($user_position == '2');	// 社長権限

		// 自動承認判定
		$boss_result	= null;
		$manager_result	= null;

		// 所属長の自動承認
		if ($is_boss) {
			$boss_result = '0';

		// 社長の自動承認
		} elseif ($is_manager) {
			$manager_result = '0';

			// 所属長が存在しなければ自動承認
			if (! $this->dao->existsBossInGroup($user['auth'])) {
				$boss_result = '0';
			}
		}

		// セッションからフォームデータ取得
		$form_data = $_SESSION['form_data'] ?? [];

		// セッションデータがない場合はトップページにリダイレクト
		if (empty($user_id) || empty($form_data)) {
			header('Location: /gainful/');
			exit;
		}

		try {
			// CSRFトークン検証
			\Common::validateCsrfToken();

			// トランザクション開始
			$this->dao->beginTransaction();

			// 申請データのメタ情報生成
			$meta = $this->_buildApplyMeta($form_data);

			// 残日数一覧取得
			$summary		= $this->dao->getApplyDaysSummary($user_id);
			$holiday_remain	= (float)$summary['holiday']['balance'];
			$compday_remain	= (float)$summary['compday']['balance'];

			// 申請日毎にINSERT
			foreach ($form_data['date'] as $i => $date) {

				// 区分を抽出
				$section = (int)($form_data['section'][$i] ?? null);

				// 1日分の申請レコード配列生成
				$row = $this->_buildRequestRecord($user_id, $meta, $date, $section, $compday_remain, $holiday_remain, $boss_result, $manager_result);

				// 休暇申請登録
				$this->dao->insertApply($row);
			}

			// 所属長が存在しない場合の社長の自動承認
			if ($boss_result == '0' && $manager_result == '0') {

				// 登録された申請データを取得
				$requests = $this->dao->getRequestByBundleId($meta['bundle_id']);

				// 休暇日数消費
				$this->consumeApprovalDays($requests);
			}

			// 成功したらコミット
			$this->dao->commitTransaction();

			// 登録された最終的な申請データを再取得
			$requests = $this->dao->getRequestByBundleId($meta['bundle_id']);

			// URLキーを格納
			$result = [
				'url_key_boss'		=> $meta['url_key_boss'],
				'url_key_manager'	=> $meta['url_key_manager'],
			];

			// メール送信
			$this->_sendApplyMail($user, $requests, $result, $is_boss, $is_manager);

			// セッションクリア
			unset($_SESSION['form_data']);

			// セッションに完了のリダイレクトフラグ付与
			$_SESSION['redirect_complete'] = true;

			// 完了画面にリダイレクト
			header('Location: /gainful/');
			exit;

		} catch (\Exception $e) {

			// 失敗したらロールバック
			$this->dao->rollbackTransaction();

			// CSRFトークン作成
			$csrf_token = \Common::generateCsrfToken();

			$this->smarty->assign([
				'csrf_token'	=> $csrf_token,
				'form_data'		=> $form_data,
				'section'		=> 'apply',
			]);

			return $this->smarty->display('user/apply/confirm.tpl');
		}
	}

	/**
	 * 登録：メタ情報生成
	 *
	 * @access	private
	 * @param	$form_data
	 * @return	array
	 */
	private function _buildApplyMeta($form_data) {

		// パラメータを抽出
		$type		= $form_data['type'] ?? null;
		$sub_type	= trimFull($form_data['sub_type'] ?? '');
		$sub_type	= ($sub_type !== '') ? $sub_type : null;
		$reason		= $form_data['reason'] ?? '';

		// bundle_idを生成
		$bundle_id = bin2hex(random_bytes(16));

		// URLキーを生成
		$url_key_boss		= bin2hex(random_bytes(16));
		$url_key_manager	= bin2hex(random_bytes(16));

		// 承認URLの有効期限を設定
		$url_expire_date = date('Y-m-d H:i:s', strtotime('+120 hours'));

		return [
			'bundle_id'			=> $bundle_id,
			'type'				=> $type,
			'sub_type'			=> $sub_type,
			'reason'			=> $reason,
			'url_key_boss'		=> $url_key_boss,
			'url_key_manager'	=> $url_key_manager,
			'url_expire_date'	=> $url_expire_date,
		];
	}

	/**
	 * 登録：1日分の申請レコード配列生成
	 *
	 * @access	private
	 * @param	$user_id , $meta, $date, $section, &$compday_remain, &$holiday_remain, $boss_result, $manager_result
	 * @return	array
	 */
	private function _buildRequestRecord($user_id, $meta, $date, $section, &$compday_remain, &$holiday_remain, $boss_result, $manager_result) {

		// 区分に応じたstartとendの振り分け
		switch ($section) {
			case 0	: $start_am_pm = 0; $end_am_pm = 0; $day_count = 0.5; break;	// 午前
			case 1	: $start_am_pm = 1; $end_am_pm = 1; $day_count = 0.5; break;	// 午後
			case 2	: $start_am_pm = 0; $end_am_pm = 1; $day_count = 1.0; break;	// 全休
			default	: $start_am_pm = null; $end_am_pm = null; $day_count = 0.0; break;
		}

		// 種別に応じた日数の振り分け
		$holiday_number	= 0.0;
		$compday_number	= 0.0;
		$special_number	= 0.0;

		// 通常の場合は有休に振り分け
		if ($meta['type'] == '0') {

			// 代休/有休の消費日数算出
			$result = \Common::consumeLeaveUnits($day_count, $compday_remain, $holiday_remain);

			// 消費日数
			$holiday_number	= $result['holiday_used'];
			$compday_number	= $result['compday_used'];

			// 更新日数
			$holiday_remain	= $result['holiday_remain'];
			$compday_remain	= $result['compday_remain'];

			// リフレッシュ休暇と結婚は特別に振り分け
		} elseif ($meta['type'] == '1' && in_array($meta['sub_type'], ['0', '1'])) {
			$special_number = $day_count;
		}

		return [
			'user_id'			=> $user_id,
			'bundle_id'			=> $meta['bundle_id'],
			'start_date'		=> $date,
			'start_am_pm'		=> $start_am_pm,
			'end_date'			=> $date,
			'end_am_pm'			=> $end_am_pm,
			'kind'				=> $meta['type'],
			'sub_kind'			=> $meta['sub_type'],
			'comment'			=> $meta['reason'],
			'holiday_number'	=> $holiday_number,
			'compday_number'	=> $compday_number,
			'special_number'	=> $special_number,
			'boss_result'		=> $boss_result,
			'manager_result'	=> $manager_result,
			'url_key_boss'		=> $meta['url_key_boss'],
			'url_key_manager'	=> $meta['url_key_manager'],
			'url_expire_date'	=> $meta['url_expire_date'],
			'day_count'			=> $day_count,
		];
	}

	/**
	 * メール送信：共通
	 *
	 * @access	private
	 * @param	$user, $requests, $result, $is_boss, $is_manager
	 * @return
	 */
	private function _sendApplyMail($user, $requests, $result, $is_boss, $is_manager) {

		// ログイン中のユーザーIDからデータ取得
		$user_name	= $user['name'] ?? '';	// 名前
		$user_auth	= $user['auth'] ?? '';	// 所属
		$user_email	= $user['mail'] ?? '';	// メールアドレス

		// 権限毎の宛先取得
		$boss_lists		= $this->dao->getApplyMailByPosition(1, $user_auth);	// 所属長
		$manager_lists	= $this->dao->getApplyMailByPosition(2);				// 社長

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

		// ユーザー設定
		$subject_user	= '休暇申請を受け付けました';
		$body_user		= $this->smarty->fetch('user/mails/user.tpl');

		// ユーザーに個別送信
		\Common::sendMail($user_email, $subject_user, $body_user);

		// 所属長に個別送信
		if (! $is_boss) {
			$this->_sendApprovalRequestMail($boss_lists, $result['url_key_boss'], '所属長');
		}

		// 社長に個別送信
		if (! $is_manager) {
			$this->_sendApprovalRequestMail($manager_lists, $result['url_key_manager'], '社長');
		}
	}

	/**
	 * メール送信：所属長/社長
	 *
	 * @access	private
	 * @param	$approvers, $url_key, $role_label
	 * @return
	 */
	private function _sendApprovalRequestMail($approvers, $url_key, $role_label) {
		foreach ($approvers as $r) {

			// メール件名
			$subject = '休暇申請が提出されました';

			// 直通URLの生成
			$url = BASE_URL . 'admin/?key=' . $url_key . '&u=' . $r['user_id'];

			$this->smarty->assign([
				'url'			=> $url,
				'role_label'	=> $role_label,
			]);

			// メール本文
			$body = $this->smarty->fetch("user/mails/admin.tpl");

			// メール送信
			\Common::sendMail($r['email'], $subject, $body);
		}
	}

	/**
	 * 申請日フォーマット整形
	 *
	 * @access	private
	 * @param	$form_data
	 * @return	$dates
	 */
	private function _formatDatesLabel($form_data) {

		// マスタデータ取得
		$weekdays	= \Common::$weekdays;
		$sections	= \Common::$apply_sections;

		// 申請日を生成
		$dates = [];
		foreach ($form_data['date'] ?? [] as $i => $date) {

			// 申請日データを整形
			$timestamp		= strtotime($date);
			$weekday		= $weekdays[date('w', $timestamp)];
			$date_label		= date('Y/n/j', $timestamp);
			$section_label	= $sections[$form_data['section'][$i]] ?? '';

			// 申請日データを配列に格納
			$dates[] = [
				'date'		=> $date,
				'section'	=> $form_data['section'][$i] ?? '',
				'display'	=> "{$date_label}（{$weekday}）{$section_label}",
			];
		}

		return $dates;
	}
}
