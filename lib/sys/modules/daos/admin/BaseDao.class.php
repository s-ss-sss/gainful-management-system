<?php

// 名前空間
namespace App\Admin\Dao;

// 共通DAOファイル
require_once ROOT_PATH . '/lib/sys/modules/daos/CommonDao.class.php';

class BaseDao extends \CommonDao {

	/**
	 * コンストラクタ：DBアクセス
	 *
	 * @access	public
	 * @param	$db
	 * @return
	 */
	public function __construct($db) {
		parent::__construct($db);
	}

	/**
	 * ユーザー一覧取得
	 *
	 * @access	public
	 * @param
	 * @return	array
	 */
	public function getUsers() {
		$sql = "
			SELECT
				u.user_id, u.name, u.join_date, u.auth,
				u.mail, u.position, u.delete_flg
			FROM
				t_users u
			WHERE
				u.delete_flg = '0'
			ORDER BY
				CAST(u.user_id AS UNSIGNED) ASC
		";

		return $this->db->GetAll($sql);
	}

	/**
	 * ユーザーID存在チェック
	 *
	 * @access	public
	 * @param	$user_id, $current_id = null
	 * @return	boolean
	 */
	public function existsUserId($user_id, $current_id = null) {

		// 初期のWHERE句
		$where	= ['u.user_id = ?'];
		$params	= [$user_id];

		// 除外する場合の追加するWHERE句
		if ($current_id !== null) {
			$where[]	= 'u.user_id != ?';
			$params[]	= $current_id;
		}

		// WHERE句作成
		$where_sql = 'WHERE ' . implode(' AND ', $where);

		// ユーザーIDに一致する行数を返す
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
