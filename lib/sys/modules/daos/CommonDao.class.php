<?php

class CommonDao {

	protected $db;

	/**
	 * コンストラクタ：DBアクセス
	 *
	 * @access	public
	 * @param	$db
	 * @return
	 */
	public function __construct($db) {
		$this->db = $db;
	}

	/**
	 * トランザクション：開始
	 *
	 * @access	public
	 * @param
	 * @return
	 */
	public function beginTransaction() {
		$this->db->StartTrans();
	}

	/**
	 * トランザクション：コミット
	 *
	 * @access	public
	 * @param
	 * @return
	 */
	public function commitTransaction() {
		$this->db->CompleteTrans();
	}

	/**
	 * トランザクション：終了
	 *
	 * @access	public
	 * @param
	 * @return
	 */
	public function rollbackTransaction() {
		$this->db->FailTrans();
		$this->db->CompleteTrans();
	}

	/**
	 * SSOからユーザー取得
	 *
	 * @access	public
	 * @param	$id
	 * @return	array
	 */
	public function getUserById($id) {
		$sql = "
			SELECT
				u.user_id, u.name, u.join_date, u.auth, u.mail, u.holiday_number,
				u.compday_number, u.special_number, u.position, u.delete_flg
			FROM
				t_users u
			WHERE
				u.user_id		= ? AND
				u.delete_flg	= '0'
		";

		// 実行
		$user = $this->db->GetRow($sql, [$id]);

		// ユーザー取得できない場合はnullを返す
		if (empty($user)) {
			return null;
		}

		return $user;
	}

	/**
	 * ログイン時のユーザーデータの取得
	 *
	 * @access	public
	 * @param	$email
	 * @return	array
	 */
	public function getUserByEmail($email) {

		// SQL文
		$sql = "
			SELECT
				u.user_id, u.name, u.mail, u.password, u.position
			FROM
				t_users u
			WHERE
				u.mail			= ? AND
				u.delete_flg	= '0'
		";

		// 実行
		$user = $this->db->GetRow($sql, [$email]);

		// ユーザーが取得できない場合はfullを返す
		if ($user == false) {
			return null;
		}

		return $user;
	}

	/**
	 * 直通URLのデータ取得
	 *
	 * @access	public
	 * @param	$key, $user_id
	 * @return	array
	 */
	public function getRequestByUrlExpireAt($key, $user_id) {
		$sql = "
			SELECT
				r.url_expire_date
			FROM
				t_request r
			WHERE
				(
					(r.url_key_boss		= ? AND ? IN (SELECT u.user_id FROM t_users u WHERE u.position = '1')) OR
					(r.url_key_manager	= ? AND ? IN (SELECT u.user_id FROM t_users u WHERE u.position = '2'))
				) AND
				r.delete_flg = '0'
			LIMIT 1
		";

		return $this->db->GetRow($sql, [$key, $user_id, $key, $user_id]);
	}

	/**
	 * 当月の申請データ取得
	 *
	 * @access	public
	 * @param	$year, $month
	 * @return	array
	 */
	public function getApplyRequestsByMonth($year, $month) {

		// 取得した日付の整形
		$start	= sprintf('%04d-%02d-01', $year, $month);
		$end	= date('Y-m-d', strtotime("$start +1 month"));

		$sql = "
			SELECT
				r.user_id, u.name AS user_name, r.start_date
			FROM
				t_request r
			INNER JOIN
				t_users u ON r.user_id = u.user_id
			WHERE
				r.start_date		>= ? AND
				r.start_date		< ? AND
				r.boss_result		= '0' AND
				r.manager_result	= '0' AND
				r.delete_flg		= '0' AND
				u.delete_flg		= '0'
		";

		// 実行
		$rs = $this->db->Execute($sql, [$start, $end]);

		// 実行に失敗した場合は空配列を返す
		if (! $rs) {
			return [];
		}

		// 行番号ジャンプエラー回避のため手動で取得
		$rows = [];
		while (! $rs->EOF) {
			$rows[] = $rs->fields;
			$rs->MoveNext();
		}

		return $rows;
	}

	/**
	 * 権限毎のメール宛先取得
	 *
	 * @access	public
	 * @param	$position
	 * @return	array
	 */
	public function getApplyMailByPosition($position, $auth = null) {

		// 初期のWHERE句
		$where = [
			'u.position		= ?',
			"u.delete_flg	= '0'",
		];

		$params = [$position];

		// 所属が指定されている場合に追加
		if ($auth !== null) {
			$where[]	= 'u.auth = ?';
			$params[]	= $auth;
		}

		// WHERE句作成
		$where_sql = 'WHERE ' . implode(' AND ', $where);

		$sql = "
			SELECT
				u.user_id, u.name, u.mail
			FROM
				t_users u
			{$where_sql}
		";

		// 実行
		$rs = $this->db->Execute($sql, $params);

		// 実行に失敗した場合は空配列を返す
		if (! $rs) {
			return [];
		}

		// 行番号ジャンプエラー回避のため手動で取得
		$rows = [];
		while (! $rs->EOF) {
			$row = $rs->fields;

			// 連想配列で返す
			$rows[] = [
				'user_id'	=> $row['user_id'] ?? '',
				'email'		=> $row['mail'] ?? '',
				'name'		=> $row['name'] ?? '',
			];

			$rs->MoveNext();
		}

		return $rows;
	}

	/**
	 * 申請データ取得
	 *
	 * @access	public
	 * @param	$bundle_id
	 * @return	array
	 */
	public function getRequestByBundleId($bundle_id) {
		$sql = "
			SELECT
				r.id, r.user_id, u.name AS user_name, u.auth AS user_auth, r.bundle_id, r.start_date, r.start_am_pm,
				r.end_date, r.end_am_pm, r.kind, r.sub_kind, r.comment, r.holiday_number, r.compday_number,
				r.special_number, r.boss_result, r.manager_result, r.boss_comment, r.manager_comment
			FROM
				t_request r
			INNER JOIN
				t_users u ON r.user_id = u.user_id
			WHERE
				r.bundle_id		= ? AND
				r.delete_flg	= '0'
			ORDER BY
				r.start_date ASC,
				r.start_am_pm ASC,
				r.id ASC
		";

		// 実行
		$rs = $this->db->Execute($sql, [$bundle_id]);

		// 実行に失敗した場合は空配列を返す
		if (! $rs) {
			return [];
		}

		// 行番号ジャンプエラー回避のため手動で取得
		$rows = [];
		while (! $rs->EOF) {
			$rows[] = $rs->fields;
			$rs->MoveNext();
		}

		return $rows;
	}

	/**
	 * サブ種別毎の特別休暇の付与合計
	 *
	 * @access	public
	 * @param	$user_id, $sub_type
	 * @return	string
	 */
	public function getTotalSpecialGranted($user_id, $sub_type) {
		$sql = "
			SELECT
				COALESCE(SUM(add_number), 0.0)
			FROM
				t_special s
			WHERE
				s.user_id		= ? AND
				s.sub_kind		= ? AND
				s.expire_date	>= CURRENT_DATE AND
				s.expire_flg	= '0' AND
				s.delete_flg	= '0'

		";

		return (float)$this->db->GetOne($sql, [$user_id, $sub_type]);
	}

	/**
	 * サブ種別毎の承認済みの特別休暇の消費合計
	 *
	 * @access	public
	 * @param	$user_id, $sub_type
	 * @return	string
	 */
	public function getApprovedSpecialConsumed($user_id, $sub_type) {
		$sql = "
			SELECT
				COALESCE(SUM(special_number), 0.0)
			FROM
				t_request r
			INNER JOIN
				t_special s ON
					s.id			= r.special_id AND
					s.expire_date	>= CURRENT_DATE AND
					s.expire_flg	= '0' AND
					s.delete_flg	= '0'
			WHERE
				r.user_id			= ? AND
				r.sub_kind			= ? AND
				r.boss_result		= '0' AND
				r.manager_result	= '0' AND
				r.delete_flg		= '0'
		";

		return (float)$this->db->GetOne($sql, [$user_id, $sub_type]);
	}

	/**
	 * サブ種別毎の承認待ち特別休暇の消費合計
	 *
	 * @access	public
	 * @param	$user_id, $sub_type
	 * @return	array
	 */
	public function getPendingSpecialConsume($user_id, $sub_type) {

		// COALESCE(*****, 'x')：NULL対応
		// NULLを'x'に変換してunknownとなる判定を回避
		$sql = "
			SELECT
				r.start_am_pm,
				r.end_am_pm
			FROM
				t_request r
			WHERE
				r.user_id		= ? AND
				r.kind			= '1' AND
				r.sub_kind		= ? AND
				r.delete_flg	= '0' AND
				COALESCE(r.boss_result, 'x')	<> '1' AND
				COALESCE(r.manager_result, 'x')	<> '1' AND NOT
				(COALESCE(r.boss_result, 'x')	= '0' AND COALESCE(r.manager_result, 'x') = '0')
		";

		return $this->db->GetAll($sql, [$user_id, $sub_type]);
	}

	/**
	 * 有効な特別休暇の有効期限を取得
	 *
	 * @access	public
	 * @param	$user_id, $sub_type
	 * @return	array
	 */
	public function getActiveSpecialExpireDate($user_id, $sub_type) {
		$sql = "
			SELECT
				MAX(s.expire_date)
			FROM
				t_special s
			WHERE
				s.user_id		= ? AND
				s.sub_kind		= ? AND
				s.expire_flg	= '0'AND
				s.delete_flg	= '0'
		";

		$result = $this->db->GetOne($sql, [$user_id, $sub_type]);
		return $result ?: null;
	}

	/**
	 * 対象日付の承認・未承認データ取得
	 *
	 * @access	public
	 * @param	$user_id, $dates
	 * @return	array
	 */
	public function getActiveRequestsByDates($user_id, $dates, $bundle_id = null) {

		// 対象日付の空チェック
		if (empty($dates)) {
			return [];
		}

		// 対象日付の数に応じたプレースホルダー生成
		$placeholders = implode(',', array_fill(0, count($dates), '?'));

		// 初期のWHERE句
		// COALESCE(*****, 'x')：NULL対応
		// NULLを'x'に変換してunknownとなる判定を回避
		$where = [
			'r.user_id = ?',
			"r.start_date IN ($placeholders)",
			"r.delete_flg = '0'",
			"COALESCE(r.boss_result, 'x') <> '1'",
			"COALESCE(r.manager_result, 'x') <> '1'",
		];

		// 対象日付毎に配列を作成
		$params	= [$user_id];
		$params	= array_merge($params, $dates);

		// 修正時は修正対象IDを除く
		if ($bundle_id !== null) {
			$where[]	= 'r.bundle_id != ?';
			$params[]	= $bundle_id;
		}

		// WHERE句作成
		$where_sql = 'WHERE ' . implode(' AND ', $where);

		$sql = "
			SELECT
				r.start_date, r.start_am_pm, r.end_am_pm
			FROM
				t_request r
			{$where_sql}
		";

		return $this->db->GetAll($sql, $params);
	}

	/**
	 * 所属長の存在判定
	 *
	 * @access	public
	 * @param	$user_auth
	 * @return
	 */
	public function existsBossInGroup($user_auth) {
		$sql = "
			SELECT
				COUNT(*) AS cnt
			FROM
				t_users u
			WHERE
				u.auth			= ? AND
				u.position		= '1' AND
				u.delete_flg	= '0'
		";

		$row = $this->db->GetRow($sql, [$user_auth]);

		// 1以上で存在する場合はtrueを返す
		return ((int)$row['cnt'] > 0);
	}

	/**
	 * 申請行の日数消費
	 *
	 * @access	public
	 * @param	$request_id, $counts
	 * @return
	 */
	public function updateRequestDayCount($request_id, $counts) {

		// SET句を動的に作成
		$set	= [];
		$params	= [];

		foreach ($counts as $key => $value) {
			$set[]		= "{$key} = ?";
			$params[]	= $value;
		}

		$set[] = "update_date = NOW()";

		$sql = "
			UPDATE
				t_request
			SET
				" . implode(', ', $set) . "
			WHERE
				id			= ? AND
				delete_flg	= '0'
		";

		$params[] = $request_id;

		$this->db->Execute($sql, $params);
	}

	/**
	 * ユーザー個別取得
	 *
	 * @access	public
	 * @param	$user_id
	 * @return	array
	 */
	public function getUserDetailById($user_id) {
		$sql = "
			SELECT
				u.user_id, u.name, u.join_date, u.auth, u.mail, u.holiday_number,
				u.compday_number, u.special_number, u.position, u.delete_flg
			FROM
				t_users u
			WHERE
				u.user_id		= ? AND
				u.delete_flg	= '0'
		";

		return $this->db->GetRow($sql, [$user_id]);
	}

	/**
	 * 代休リンクの紐付け可能枠取得
	 *
	 * @access	public
	 * @param	$user_id
	 * @return	array
	 */
	public function getCompdayLink($user_id) {
		$sql = "
			SELECT
				c.id, c.add_number, c.link_request_id1, c.link_request_id2
			FROM
				t_compday c
			WHERE
				c.user_id		= ? AND
				c.delete_flg	= '0' AND
				(c.link_request_id1 IS NULL OR c.link_request_id2 IS NULL)
			ORDER BY
				c.regist_date ASC,
				c.id ASC
		";

		return $this->db->GetAll($sql, [$user_id]);
	}

	/**
	 * 代休リンク更新
	 *
	 * @access	public
	 * @param	$user_id, $request_id, $lot_id, $link
	 * @return	array
	 */
	public function updateCompdayLink($user_id, $request_id, $lot_id, $link) {

		// 代休リンク設定
		$link_column = "link_request_id{$link}";

		$sql = "
			UPDATE
				t_compday
			SET
				{$link_column}	= ?,
				update_date		= NOW()
			WHERE
				id			= ? AND
				user_id		= ? AND
				delete_flg	= '0' AND
				{$link_column} IS NULL
		";

		$this->db->Execute($sql, [$request_id, $lot_id, $user_id]);

		return $this->db->Affected_Rows();
	}

	/**
	 * 有休/代休の残日数消費
	 *
	 * @access	public
	 * @param	$user_id, $holiday_remain, $compday_remain
	 * @return
	 */
	public function updateUsersDayCount($user_id, $holiday_remain, $compday_remain) {
		$sql = "
			UPDATE
				t_users
			SET
				holiday_number	= ?,
				compday_number	= ?,
				update_date		= NOW()
			WHERE
				user_id		= ? AND
				delete_flg	= '0'
		";

		$this->db->Execute($sql, [$holiday_remain, $compday_remain, $user_id]);
	}

	/**
	 * 消費可能な特別休暇取得
	 *
	 * @access	public
	 * @param	$user_id, $sub_type
	 * @return	array
	 */
	public function getActiveSpecialGrant($user_id, $sub_type) {
		$sql = "
			SELECT
				s.id, s.add_number, s.expire_date,
				s.add_number - COALESCE(SUM(r.special_number), 0.0) AS remain_number
			FROM
				t_special s
			LEFT JOIN
				t_request r ON
					r.special_id = s.id AND
					r.delete_flg = '0'
			WHERE
				s.user_id		= ? AND
				s.sub_kind		= ? AND
				s.expire_date	>= CURRENT_DATE AND
				s.expire_flg	= '0' AND
				s.delete_flg	= '0'
			GROUP BY
				s.id, s.add_number, s.expire_date
			HAVING
				(s.add_number - COALESCE(SUM(r.special_number), 0.0)) > 0
			ORDER BY
				s.expire_date ASC,
				s.id ASC
			LIMIT 1
		";

		return $this->db->GetRow($sql, [$user_id, $sub_type]);
	}

	/**
	 * 特別休暇と申請の紐付け
	 *
	 * @access	public
	 * @param	$request_id, $special_id
	 * @return
	 */
	public function linkSpecialGrantToRequest($request_id, $special_id) {
		$sql = "
			UPDATE
				t_request
			SET
				special_id	= ?,
				update_date	= NOW()
			WHERE
				id = ?
		";

		$this->db->Execute($sql, [$special_id, $request_id]);
	}

	/**
	 * 特別休暇の残日数消費
	 *
	 * @access	public
	 * @param	$user_id, $special_remain
	 * @return
	 */
	public function updateUsersSpecialCount($user_id, $special_remain) {
		$sql = "
			UPDATE
				t_users
			SET
				special_number	= ?,
				update_date		= NOW()
			WHERE
				user_id		= ? AND
				delete_flg	= '0'
		";

		$this->db->Execute($sql, [$special_remain, $user_id]);
	}
}
