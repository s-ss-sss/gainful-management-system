<?php

// 名前空間
namespace App\Admin;

// 管理クラス共通ファイル
require_once ROOT_PATH . '/lib/sys/modules/admin/Base.class.php';

class Request extends Base {

	/**
	 * 一覧：表示
	 *
	 * @access	public
	 * @param
	 * @return
	 */
	public function displayRequestIndex() {

		// 社長権限チェック
		$this->checkManager();

		// CSRFトークン検証
		\Common::validatePostCsrf();

		// アラートフラッシュメッセージ
		$this->assignFlashMessage('申請データ');

		// ユーザー一覧取得
		$users = $this->dao->getUsers();

		// 選択ユーザー
		$selected_user = $_POST['user'] ?? '';

		// 最古年度取得
		$oldest_year = $this->dao->getRequestOldestYear();

		// 年度プルダウン生成
		$year_selector = \Common::buildYearSelector($oldest_year);

		// 年度配列
		$years = $year_selector['years'];

		// 選択年度
		$selected_year = $year_selector['selected_year'];

		// 申請データ一覧取得
		$rows = $this->dao->getApprovedRequests($selected_user, $selected_year);

		// グルーピング作成
		$requests_lists = $this->_buildRequestData($rows);

		// 追加ボタンの表示フラグ
		$add_flg = ($selected_user !== '');

		// CSRFトークン作成
		$csrf_token = \Common::generateCsrfToken();

		$this->smarty->assign([
			'csrf_token'		=> $csrf_token,
			'users'				=> $users,
			'selected_user'		=> $selected_user,
			'years'				=> $years,
			'selected_year'		=> $selected_year,
			'requests_lists'	=> $requests_lists,
			'add_flg'			=> $add_flg,
			'section'			=> 'request',
		]);

		$this->smarty->display('admin/request/index.tpl');
	}

	/**
	 * 一覧：bundle_idとユーザー毎にデータ生成
	 *
	 * @access	private
	 * @param	$rows
	 * @return	$lists
	 */
	private function _buildRequestData($rows) {

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
					'bundle_id'	=> $bundle_id,
					'dates'		=> [],
					'kind'		=> \Common::formatTypeLabel($r),
					'reason'	=> $r['comment'],
					'holiday'	=> 0.0,
					'compday'	=> 0.0,
					'special'	=> 0.0,
				];
			}

			// 参照渡しでbundleを取得
			$bundle = &$lists[$user_id]['bundles'][$bundle_id];

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
	 * 登録：表示
	 *
	 * @access	public
	 * @param	$user_id, $form_data = null, $errors = []
	 * @return
	 */
	public function displayRequestCreateIndex($user_id, $form_data = null, $errors = []) {

		// 社長権限チェック
		$this->checkManager();

		// データが不正な場合は一覧画面にリダイレクト
		if (! ctype_digit($user_id) || ! $this->dao->existsUserId($user_id)) {
			trigger_error(ERR_INVALID, E_USER_WARNING);
			return $this->displayRequestIndex();
		}

		// ユーザーIDから合致するデータ取得
		$user		= $this->dao->getUserDetailById($user_id);
		$user_name	= $user['name'];

		// フォームデータ取得
		if ($form_data === null) {
			$form_data = [
				'date'		=> $_POST['date'] ?? [],
				'section'	=> $_POST['section'] ?? [],
				'type'		=> $_POST['type'] ?? '',
				'sub_type'	=> $_POST['sub_type'] ?? '',
				'reason'	=> $_POST['reason'] ?? '',
			];
		}

		// CSRFトークン作成
		$csrf_token = \Common::generateCsrfToken();

		$this->smarty->assign([
			'csrf_token'		=> $csrf_token,
			'weekdays'			=> \Common::$weekdays,
			'sections'			=> \Common::$apply_sections,
			'types'				=> \Common::$apply_types,
			'sub_types'			=> \Common::$apply_sub_types,
			'holidays'			=> \Common::getHolidays(),
			'form_data'			=> $form_data,
			'form_date'			=> $form_data['date'],
			'form_section'		=> $form_data['section'],
			'errors'			=> $errors,
			'current_year'		=> date('Y'),
			'current_month'		=> date('n'),
			'request_user_id'	=> $user_id,
			'request_user_name'	=> $user_name,
			'section'			=> 'request',
		]);

		$this->smarty->display('admin/request/create.tpl');
	}

	/**
	 * 登録：実行
	 *
	 * @access	public
	 * @param
	 * @return
	 */
	public function createRequest() {

		// 社長権限チェック
		$this->checkManager();

		// フォームデータ取得
		$form_data = [
			'user_id'	=> $_POST['user_id'] ?? '',
			'date'		=> $_POST['date'] ?? [],
			'section'	=> $_POST['section'] ?? [],
			'type'		=> $_POST['type'] ?? '',
			'sub_type'	=> $_POST['sub_type'] ?? '',
			'reason'	=> $_POST['reason'] ?? '',
		];

		// ユーザーID取得
		$user_id = $form_data['user_id'];

		// バリデーション実行
		$errors = $this->_validateRequest($form_data);

		// エラー内容を再描画
		if (! empty($errors)) {
			return $this->displayRequestCreateIndex($user_id, $form_data, $errors);
		}

		try {

			// CSRFトークン検証
			\Common::validateCsrfToken();

			// トランザクション開始
			$this->dao->beginTransaction();

			// 申請データのメタ情報生成
			$meta = $this->_buildRequestMeta($form_data);

			// 申請日毎にINSERT
			$rows = [];
			foreach ($form_data['date'] as $i => $date) {

				// 区分を抽出
				$section = (int)($form_data['section'][$i] ?? null);

				// 1日分の申請レコード配列生成
				$row = $this->_buildRequestRecord($user_id, $meta, $date, $section);

				// 申請データ登録
				$insert_id = $this->dao->insertRequest($row);

				// レコードIDを追加
				$row['id']	= $insert_id;
				$rows[]		= $row;
			}

			// 消費日数更新
			$this->_consumeCreatedDays($rows, $user_id);

			// 成功したらコミット
			$this->dao->commitTransaction();

			// 登録フラグをセッションに保存
			$_SESSION['flash_action'] = 'create';

			// 一覧画面にリダイレクト
			header('Location: ' . BASE_URL . 'admin/request/');
			exit;

		} catch (\Exception $e) {

			// 失敗したらロールバック
			$this->dao->rollbackTransaction();
			return $this->displayRequestCreateIndex($user_id);
		}
	}

	/**
	 * 登録：メタ情報生成
	 *
	 * @access	private
	 * @param	$form_data
	 * @return	array
	 */
	private function _buildRequestMeta($form_data) {

		// パラメータを抽出
		$type		= $form_data['type'] ?? null;
		$sub_type	= trimFull($form_data['sub_type'] ?? '');
		$sub_type	= ($sub_type !== '') ? $sub_type : null;
		$reason		= $form_data['reason'] ?? '';

		// bundle_idを生成
		$bundle_id = bin2hex(random_bytes(16));

		return [
			'bundle_id'	=> $bundle_id,
			'type'		=> $type,
			'sub_type'	=> $sub_type,
			'reason'	=> $reason,
		];
	}

	/**
	 * 修正：表示
	 *
	 * @access	public
	 * @param	$user_id, $form_data = null, $errors = []
	 * @return
	 */
	public function displayRequestEditIndex($bundle_id, $form_data = null, $errors = []) {

		// 社長権限チェック
		$this->checkManager();

		// 申請データ取得
		$rows = $this->dao->getRequestByBundleId($bundle_id);

		// データが不正な場合は一覧画面にリダイレクト
		if (empty($rows)) {
			trigger_error(ERR_INVALID, E_USER_WARNING);
			return $this->displayRequestIndex();
		}

		// フォームデータ初期化
		if ($form_data === null) {
			$form_data = [
				'bundle_id'	=> $rows[0]['bundle_id'],
				'type'		=> $rows[0]['kind'],
				'sub_type'	=> ($rows[0]['kind'] === '1') ? ($rows[0]['sub_kind'] ?? '') : '',
				'reason'	=> $rows[0]['comment'],
			];
		}

		// 申請日を整形
		if (
			isset($form_data['date'], $form_data['section']) &&
			is_array($form_data['date']) &&
			is_array($form_data['section'])
		) {

			// バリデーション時の再描画データ
			$form_date		= $form_data['date'];
			$form_section	= $form_data['section'];

			// 初期表示
		} else {
			$dates = [];
			foreach ($rows as $r) {
				$dates[] = [
					'date'		=> $r['start_date'],
					'section'	=> \Common::formatSectionValue($r),
				];
			}

			// JSに渡す申請日データ
			$form_date		= array_column($dates, 'date');
			$form_section	= array_column($dates, 'section');
		}

		// CSRFトークン作成
		$csrf_token = \Common::generateCsrfToken();

		$this->smarty->assign([
			'csrf_token'		=> $csrf_token,
			'weekdays'			=> \Common::$weekdays,
			'sections'			=> \Common::$apply_sections,
			'types'				=> \Common::$apply_types,
			'sub_types'			=> \Common::$apply_sub_types,
			'holidays'			=> \Common::getHolidays(),
			'form_data'			=> $form_data,
			'form_date'			=> $form_date,
			'form_section'		=> $form_section,
			'errors'			=> $errors,
			'current_year'		=> date('Y'),
			'current_month'		=> date('n'),
			'request_user_name'	=> $rows[0]['user_name'],
			'section'			=> 'request',
		]);

		$this->smarty->display('admin/request/edit.tpl');
	}

	/**
	 * 修正：実行
	 *
	 * @access	public
	 * @param
	 * @return
	 */
	public function editRequest() {

		// 社長権限チェック
		$this->checkManager();

		// 修正対象ID取得
		$bundle_id = $_POST['bundle_id'] ?? '';

		// 修正対象IDが不正な場合は一覧画面にリダイレクト
		if (! $this->dao->existsBundleId($bundle_id)) {
			trigger_error(ERR_INVALID, E_USER_WARNING);
			return $this->displayRequestIndex();
		}

		// 旧データ取得
		$old_rows	= $this->dao->getRequestByBundleId($bundle_id);
		$user_id 	= $old_rows[0]['user_id'];

		// フォームデータ取得
		$form_data = [
			'bundle_id'	=> $bundle_id,
			'user_id'	=> $user_id,
			'date'		=> $_POST['date'] ?? [],
			'section'	=> $_POST['section'] ?? [],
			'type'		=> $_POST['type'] ?? '',
			'sub_type'	=> $_POST['sub_type'] ?? '',
			'reason'	=> $_POST['reason'] ?? '',
		];

		// バリデーション実行
		$errors = $this->_validateRequest($form_data);

		// エラー内容を再描画
		if (! empty($errors)) {
			return $this->displayRequestEditIndex($bundle_id, $form_data, $errors);
		}

		try {

			// CSRFトークン検証
			\Common::validateCsrfToken();

			// トランザクション開始
			$this->dao->beginTransaction();

			// 旧データロールバック
			$this->_rollbackConsumedDays($old_rows, $user_id);

			// 旧データ削除
			$this->dao->deleteRequest($bundle_id);

			// メタデータ生成
			$meta = $this->_buildEditMeta($form_data);

			// 申請日毎にINSERT
			$rows = [];
			foreach ($form_data['date'] as $i => $date) {

				// 区分を抽出
				$section = (int)($form_data['section'][$i] ?? null);

				// 1日分の申請レコード配列生成
				$row = $this->_buildRequestRecord($user_id, $meta, $date, $section);

				// 申請データ登録
				$insert_id = $this->dao->insertRequest($row);

				// レコードIDを追加
				$row['id']	= $insert_id;
				$rows[]		= $row;
			}

			// 消費日数更新
			$this->_consumeCreatedDays($rows, $user_id);

			// 成功したらコミット
			$this->dao->commitTransaction();

			// 修正フラグをセッションに保存
			$_SESSION['flash_action'] = 'edit';

			// 一覧画面にリダイレクト
			header('Location: ' . BASE_URL . 'admin/request/');
			exit;

		} catch (\Exception $e) {

			// 失敗したらロールバック
			$this->dao->rollbackTransaction();
			return $this->displayRequestEditIndex($bundle_id);
		}
	}

	/**
	 * 修正：メタ情報生成
	 *
	 * @access	private
	 * @param	$form_data
	 * @return	array
	 */
	private function _buildEditMeta($form_data) {
		return [
			'bundle_id'	=> $form_data['bundle_id'],
			'type'		=> $form_data['type'],
			'sub_type'	=> $form_data['sub_type'],
			'reason'	=> $form_data['reason'],
		];
	}

	/**
	 * バリデーション：共通
	 *
	 * @access	private
	 * @param	$form_data
	 * @return	$errors
	 */
	private function _validateRequest($form_data) {
		$errors = [];

		// 申請日
		$date_errors = $this->_validateRequestDates($form_data);
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
				$special_errors = $this->_validateRequestSpecial($form_data, $sub_type);
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
	private function _validateRequestSpecial($form_data, $sub_type) {
		$errors = [];

		// POSTされた値をintにキャスト
		$sub_type = (int)$sub_type;

		// 日数消費しない特別休暇はチェック不要
		if (! in_array($sub_type, \Common::$consume_sub_type_ids, true)) {
			return $errors;
		}

		// ユーザーID取得
		$user_id = $form_data['user_id'] ?? null;

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

		// 修正時は自分自身の消費日数を足し戻す
		if (! empty($form_data['bundle_id'])) {
			$current_consumed	= $this->dao->getSpecialConsumedDaysByBundleId($form_data['bundle_id'], $sub_type);
			$available			+= $current_consumed;
		}

		// 申請日の合計を計算
		$total_days = 0.0;
		$sections   = $form_data['section'] ?? [];
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
	private function _validateRequestDates($form_data) {
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

		// ユーザーID取得
		$user_id = $form_data['user_id'] ?? null;

		// 修正対象ID取得
		$bundle_id = $form_data['bundle_id'] ?? null;

		// 承認・未承認データの重複チェック用に日付をまとめる
		$unique = array_unique($form_data['date']);

		// 対象日付の承認・未承認データ取得
		$existing = $this->dao->getActiveRequestsByDates($user_id, $unique, $bundle_id);

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
	 * 削除
	 *
	 * @access	public
	 * @param
	 * @return
	 */
	public function deleteRequests() {

		// 社長権限チェック
		$this->checkManager();

		// bundle_id取得
		$bundle_id = $_POST['delete_id'] ?? '';

		// データが不正な場合は一覧画面にリダイレクト
		if ($bundle_id === '' || ! $this->dao->existsBundleId($bundle_id)) {
			trigger_error(ERR_INVALID, E_USER_WARNING);
			return $this->displayRequestIndex();
		}

		try {

			// CSRFトークン検証
			\Common::validateCsrfToken();

			// トランザクション開始
			$this->dao->beginTransaction();

			// 削除対象の行取得
			$rows		= $this->dao->getRequestByBundleId($bundle_id);
			$user_id	= $rows[0]['user_id'];

			// 日数ロールバック
			$this->_rollbackConsumedDays($rows, $user_id);

			// 申請データ削除
			$this->dao->deleteRequest($bundle_id);

			// 成功したらコミット
			$this->dao->commitTransaction();

			// 削除フラグをセッションに保存
			$_SESSION['flash_action'] = 'delete';

			// 一覧画面にリダイレクト
			header('Location: ' . BASE_URL . 'admin/request/');
			exit;

		} catch (\Exception $e) {

			// 失敗したらロールバック
			$this->dao->rollbackTransaction();
			return $this->displayRequestIndex();
		}
	}

	/**
	 * 1日分の申請レコード配列生成
	 *
	 * @access	private
	 * @param	$user_id, $meta, $date, $section
	 * @return	array
	 */
	private function _buildRequestRecord($user_id, $meta, $date, $section) {

		// 区分に応じたstartとendの振り分け
		switch ($section) {
			case 0	: $start_am_pm	= 0;	$end_am_pm	= 0;	break;	// 午前
			case 1	: $start_am_pm	= 1;	$end_am_pm	= 1;	break;	// 午後
			case 2	: $start_am_pm	= 0;	$end_am_pm	= 1;	break;	// 全休
			default	: $start_am_pm	= null;	$end_am_pm	= null;	break;
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
			'holiday_number'	=> 0.0,
			'compday_number'	=> 0.0,
			'special_number'	=> 0.0,
		];
	}

	/**
	 * 休暇日数更新
	 *
	 * @access	private
	 * @param	$rows, $user_id
	 * @return	$lists
	 */
	private function _consumeCreatedDays($rows, $user_id) {

		// ユーザーIDから合致するデータ取得
		$user = $this->dao->getUserDetailById($user_id);

		// 休暇の残日数取得
		$compday_remain	= (float)$user['compday_number'];
		$holiday_remain	= (float)$user['holiday_number'];
		$special_remain	= (float)$user['special_number'];

		// 各申請行の処理
		$lists = [];
		foreach ($rows as $r) {

			// 半休/全休の消費日数算出
			$consume_days = \Common::determineLeaveUnits($r['start_am_pm'], $r['end_am_pm']);

			// 通常
			if ($r['kind'] == '0') {

				// 代休/有休の消費日数算出
				$result 		= \Common::consumeLeaveUnits($consume_days, $compday_remain, $holiday_remain);
				$compday_remain	= $result['compday_remain'];
				$holiday_remain	= $result['holiday_remain'];

				// 代休/有休の登録用の値
				$r['compday_number']	= $result['compday_used'];
				$r['holiday_number']	= $result['holiday_used'];

				// 特別休暇
			} elseif ($r['kind'] == '1') {

				// リフレッシュ休暇と結婚の場合に消費
				if (in_array((int)$r['sub_kind'], \Common::$consume_sub_type_ids, true)) {

					// 消費可能な特別休暇取得
					$special = $this->dao->getActiveSpecialGrant($user_id, $r['sub_kind']);

					// データが見つからない場合は例外に投げる
					if (! $special) {
						throw new RuntimeException('Special grant not found');
					}

					// 特別休暇の登録用の値
					$r['special_number'] = $consume_days;

					// 特別休暇IDを紐付け
					$this->dao->linkSpecialGrantToRequest($r['id'], $special['id']);

					// t_usersテーブル更新用の値
					$special_remain -= $consume_days;
				}
			}

			// 消費日数を取得
			$consumed_days = [
				'holiday_number'	=> $r['holiday_number'] ?? 0.0,
				'compday_number'	=> $r['compday_number'] ?? 0.0,
				'special_number'	=> $r['special_number'] ?? 0.0,
				'id'				=> $r['id'],
			];

			// 消費日数を更新
			$this->dao->updateRequestConsumedDays($consumed_days);

			// 各申請行を格納
			$lists[] = $r;
		}

		// t_usersテーブル更新
		$this->dao->updateUsersDayCount($user_id, $holiday_remain, $compday_remain);
		$this->dao->updateUsersSpecialCount($user_id, $special_remain);

		// 代休リンク紐付け
		foreach ($lists as $l) {
			if ($l['compday_number'] > 0) {
				$this->linkCompdayUnits($user_id, $l['id'], $l['compday_number']);
			}
		}

		return $lists;
	}

	/**
	 * 日数ロールバック
	 *
	 * @access	public
	 * @param	$rows, $user_id
	 * @return
	 */
	public function _rollbackConsumedDays($rows, $user_id) {

		// 合計日数の集計
		$rollback_holiday	= 0.0;
		$rollback_compday	= 0.0;
		$rollback_special	= 0.0;

		foreach ($rows as $r) {
			$rollback_holiday	+= (float)$r['holiday_number'];
			$rollback_compday	+= (float)$r['compday_number'];
			$rollback_special	+= (float)$r['special_number'];
		}

		// ユーザーIDから合致するデータ取得
		$user = $this->dao->getUserDetailById($user_id);

		// ロールバック日数算出
		$new_holiday	= round((float)$user['holiday_number'] + $rollback_holiday, 1);
		$new_compday	= round((float)$user['compday_number'] + $rollback_compday, 1);
		$new_special	= round((float)$user['special_number'] + $rollback_special, 1);

		// t_usersテーブル更新
		$this->dao->updateUsersDayCount($user_id, $new_holiday, $new_compday);
		$this->dao->updateUsersSpecialCount($user_id, $new_special);

		// 代休リンク解除
		foreach ($rows as $r) {
			if ($r['compday_number'] > 0) {
				$this->dao->unlinkCompdayLink($r['id']);
			}
		}
	}
}
