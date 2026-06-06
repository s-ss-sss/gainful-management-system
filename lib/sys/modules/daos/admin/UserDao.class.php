<?php

// 名前空間
namespace App\Admin\Dao;

// ユーザーDAO共通ファイル
require_once ROOT_PATH . '/lib/sys/modules/daos/admin/BaseDao.class.php';

class UserDao extends BaseDao {

	/**
	 * ユーザー登録
	 *
	 * @access	public
	 * @param	$param
	 * @return
	 */
	public function insertUser($param) {
		$sql = '
			INSERT INTO t_users (
				user_id, name, join_date, auth, mail, password, holiday_number, position
			) VALUES (
				?, ?, ?, ?, ?, ?, ?, ?
			)
		';

		$params = [
			$param['user_id'],
			$param['name'],
			$param['join_date'],
			$param['auth'],
			$param['mail'],
			$param['password'],
			$param['holiday_number'],
			$param['position'],
		];

		$this->db->Execute($sql, $params);
	}

	/**
	 * ユーザー修正
	 *
	 * @access	public
	 * @param	$user_id, $param
	 * @return
	 */
	public function editUser($user_id, $param) {

		// パスワード入力がある場合
		if (! empty($param['password'])) {
			$sql = "
				UPDATE
					t_users
				SET
					user_id			= ?,
					name			= ?,
					join_date		= ?,
					auth			= ?,
					mail			= ?,
					password		= ?,
					holiday_number	= ?,
					position		= ?,
					update_date		= NOW()
				WHERE
					user_id		= ? AND
					delete_flg	= '0'
			";

			$params = [
				$param['user_id'],
				$param['name'],
				$param['join_date'],
				$param['auth'],
				$param['mail'],
				$param['password'],
				$param['holiday_number'],
				$param['position'],
				$user_id,
			];

		// パスワード入力がない場合
		} else {
			$sql = "
				UPDATE
					t_users
				SET
					user_id			= ?,
					name			= ?,
					join_date		= ?,
					auth			= ?,
					mail			= ?,
					holiday_number	= ?,
					position		= ?,
					update_date		= NOW()
				WHERE
					user_id		= ? AND
					delete_flg	= '0'
			";

			$params = [
				$param['user_id'],
				$param['name'],
				$param['join_date'],
				$param['auth'],
				$param['mail'],
				$param['holiday_number'],
				$param['position'],
				$user_id,
			];
		}

		$this->db->Execute($sql, $params);
	}

	/**
	 * ユーザー削除
	 *
	 * @access	public
	 * @param	$user_id
	 * @return
	 */
	public function deleteUser($user_id) {
		$sql = "
			UPDATE
				t_users
			SET
				delete_flg	= '1',
				update_date	= NOW()
			WHERE
				user_id = ?
		";

		$this->db->Execute($sql, [$user_id]);
	}

	/**
	 * メールアドレス存在チェック
	 *
	 * @access	public
	 * @param	$mail, $current_id = null
	 * @return	boolean
	 */
	public function existsUserMail($mail, $current_id = null) {

		// 初期のWHERE句
		$where	= ['u.mail = ?'];
		$params	= [$mail];

		// 除外する場合の追加するWHERE句
		if ($current_id !== null) {
			$where[]	= 'u.user_id != ?';
			$params[]	= $current_id;
		}

		// WHERE句作成
		$where_sql = 'WHERE ' . implode(' AND ', $where);

		// ユーザーメールに一致する行数を返す
		$sql = "
			SELECT
				COUNT(*) AS cnt
			FROM
				t_users u
			{$where_sql}
		";

		$row = $this->db->GetRow($sql, $params);

		// 1以上で存在する場合はtrueを返す
		return ((int)$row['cnt'] > 0);
	}
}
