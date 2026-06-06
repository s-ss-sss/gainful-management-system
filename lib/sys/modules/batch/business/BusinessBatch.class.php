<?php

// 名前空間
namespace App\Batch\Business;

class BusinessBatch {

	private $today, $dao;

	// 5年半までの固定付与日数
	private static $grant_map = [
		6	=> 10,	// 6ヶ月（半年）
		18	=> 11,	// 18ヶ月（1年半）
		30	=> 12,	// 30ヶ月（2年半）
		42	=> 14,	// 42ヶ月（3年半）
		54	=> 16,	// 54ヶ月（4年半）
		66	=> 18,	// 66ヶ月（5年半）
	];

	/**
	 * コンストラクタ
	 *
	 * @access	public
	 * @param	$today, $dao
	 * @return
	 */
	public function __construct($today, $dao) {
		$this->today	= $today;
		$this->dao		= $dao;
	}

	/**
	 * バッチ処理
	 *
	 * @access	public
	 * @param
	 * @return
	 */
	public function run() {

		// 有休付与
		$this->_runHolidayGrant();

		// 有休消滅
		$this->_runHolidayExpire();

		// 特別休暇（リフレッシュ休暇）付与
		$this->_runSpecialGrant();

		// 特別休暇消滅
		$this->_runSpecialExpire();
	}

	// ============================================================
	// 有休付与
	// ============================================================

	/**
	 * 有休付与
	 *
	 * @access	private
	 * @param
	 * @return
	 */
	private function _runHolidayGrant() {

		// 対象ユーザー取得
		$users = $this->dao->getActiveUsers();

		// ログ出力カウント
		$total		= 0;	// ユーザー数
		$granted	= 0;	// 付与数

		// ユーザー毎に有休付与
		foreach ($users as $user) {
			$total++;
			if ($this->_grantHolidayToUser($user)) {
				$granted++;
			}
		}

		// ログ出力
		echo "[Holiday Grant] total={$total}, granted={$granted}" . PHP_EOL;
	}

	/**
	 * ユーザー毎に有休付与
	 *
	 * @access	private
	 * @param	$user
	 * @return	bool
	 */
	private function _grantHolidayToUser($user) {

		// 入社年月日を取得
		$join_date = new \DateTimeImmutable($user['join_date']);

		// 入社年月日から経過月数取得
		$months	= $this->_calcElapsedMonths($join_date);

		// 付与日を判定
		if (! $this->_isGrantMonth($months)) {
			return false;
		}

		// 有休付与日を算出
		$grant_date = $this->_calcGrantDate($join_date, $months);

		// 付与年月日
		$action_date = $grant_date->format('Y-m-d');

		// 有休付与日と今日が一致するか判定
		if ($action_date === $this->today->format('Y-m-d')) {

			// 有休付与日数を算出
			$days = $this->_calcGrantDays($months);

			// 有休付与チェック
			if ($days <= 0) {
				return false;
			}

			// 二重付与チェック
			if ($this->dao->existsHolidayGrant($user['user_id'], $action_date)) {
				return false;
			}

			// 有休付与処理
			$this->_applyGrant($user, $days, $action_date);

			// ログカウント用の戻り値
			return true;
		}

		return false;
	}

	/**
	 * 付与日を判定
	 *
	 * @access	private
	 * @param	$months
	 * @return	bool
	 */
	private function _isGrantMonth($months) {

		// 5年半までの固定付与月
		$fixed = [6, 18, 30, 42, 54, 66];
		if (in_array($months, $fixed, true)) {
			return true;
		}

		// 6年半以降は12ヶ月毎に付与
		if ($months >= 78 && ($months - 78) % 12 === 0) {
			return true;
		}

		return false;
	}

	/**
	 * 有休付与処理
	 *
	 * @access	private
	 * @param	$user, $days, $action_date
	 * @return
	 */
	private function _applyGrant($user, $days, $action_date) {

		// トランザクション開始
		$this->dao->beginTransaction();

		try {

			// 有休付与日数をログテーブルに登録
			$this->dao->insertHolidayGrant($user['user_id'], $action_date, $days);

			// 有休残日数を更新
			$this->dao->addUserHoliday($user['user_id'], $days);

			// 成功したらコミット
			$this->dao->commitTransaction();

		} catch (Exception $e) {

			// 失敗したらロールバック
			$this->dao->rollbackTransaction();
		}
	}

	// ============================================================
	// 有休消滅
	// ============================================================

	/**
	 * 有休消滅
	 *
	 * @access	private
	 * @param
	 * @return
	 */
	private function _runHolidayExpire() {

		// 対象ユーザー取得
		$users = $this->dao->getActiveUsers();

		// ログ出力カウント
		$total		= 0;	// ユーザー数
		$expired	= 0;	// 消滅数

		// ユーザー毎に有休付与
		foreach ($users as $user) {
			$total++;
			if ($this->_expireHolidayToUser($user)) {
				$expired++;
			}
		}

		// ログ出力
		echo "[Holiday Expire] total={$total}, expired={$expired}" . PHP_EOL;
	}

	/**
	 * ユーザー毎に有休消滅
	 *
	 * @access	private
	 * @param	$user
	 * @return	bool
	 */
	private function _expireHolidayToUser($user) {

		// 入社年月日を取得
		$join_date = new \DateTimeImmutable($user['join_date']);

		// 入社年月日から経過月数取得
		$months	= $this->_calcElapsedMonths($join_date);

		// 消滅対象となる付与月数を24ヶ月戻す
		$expire_months = $months - 24;

		// 6ヶ月未満は消滅対象外
		if ($expire_months < 6) {
			return false;
		}

		// 6ヶ月起点の12ヶ月周期のみ消滅対象
		if (($expire_months - 6) % 12 !== 0) {
			return false;
		}

		// 現在の残日数（バッチ処理の有休付与後）
		$current_days = (float)$user['holiday_number'];

		// 最大保持日数
		$max_days = $this->_calcMaxHoldHolidayDays($months);

		// 最大保持日数の超過日数
		$excess_days = max(0, $current_days - $max_days);

		// 消滅対象の付与日
		$expire_grant_date = $this->_calcGrantDate($join_date, $expire_months);

		// 有効期限
		$expire_date = $expire_grant_date->modify('+2 years');

		// 消滅年月日
		$action_date = $expire_date->format('Y-m-d');

		// 有休消滅日と今日が一致するか判定
		if ($action_date === $this->today->format('Y-m-d')) {

			// 期限切れ後の使用日数
			$expired_used_days = $this->dao->getFutureUsedHolidayDays($user['user_id'], $action_date);

			// 消滅の合計日数
			$expire_days = $excess_days + $expired_used_days;

			// 有休消滅チェック
			if ($expire_days <= 0) {
				return false;
			}

			// 二重消滅チェック
			if ($this->dao->existsHolidayExpire($user['user_id'], $action_date)) {
				return false;
			}

			// 有休消滅処理
			$this->_applyHolidayExpire($user, $expire_days, $action_date);

			// ログカウント用の戻り値
			return true;
		}

		return false;
	}

	/**
	 * 有休消滅処理
	 *
	 * @access	private
	 * @param	$months
	 * @return	int
	 */
	private function _calcMaxHoldHolidayDays($months) {

		// 現在の付与日数
		$current_grant = $this->_calcGrantDays($months);

		// 前回の付与日数
		$prev_grant = $this->_calcGrantDays($months - 12);

		return $current_grant + $prev_grant;
	}

	/**
	 * 有休消滅処理
	 *
	 * @access	private
	 * @param	$user, $expire_days, $max_days
	 * @return
	 */
	private function _applyHolidayExpire($user, $expire_days, $action_date) {

		// トランザクション開始
		$this->dao->beginTransaction();

		try {

			// 消滅履歴を登録
			$this->dao->insertHolidayExpire($user['user_id'], $action_date, $expire_days);

			// 有休残日数を更新
			$this->dao->subtractUserHoliday($user['user_id'], $expire_days);

			// 成功したらコミット
			$this->dao->commitTransaction();

		} catch (Exception $e) {

			// 失敗したらロールバック
			$this->dao->rollbackTransaction();
		}
	}

	// ============================================================
	// 特別休暇（リフレッシュ休暇）付与
	// ============================================================

	/**
	 * 特別休暇（リフレッシュ休暇）付与
	 *
	 * @access	private
	 * @param
	 * @return
	 */
	private function _runSpecialGrant() {

		// 対象ユーザー取得
		$users = $this->dao->getActiveUsers();

		// ログ出力カウント
		$total		= 0;	// ユーザー数
		$granted	= 0;	// 付与数

		// ユーザー毎に有休付与
		foreach ($users as $user) {
			$total++;
			if ($this->_grantSpecialToUser($user)) {
				$granted++;
			}
		}

		// ログ出力
		echo "[Special Grant] total={$total}, granted={$granted}" . PHP_EOL;
	}

	/**
	 * ユーザー毎に特別休暇付与
	 *
	 * @access	private
	 * @param	$user
	 * @return	bool
	 */
	private function _grantSpecialToUser($user) {

		// 入社年月日を取得
		$join_date = new \DateTimeImmutable($user['join_date']);

		// 入社年月日から経過年数取得
		$years = $join_date->diff($this->today)->y;

		// リフレッシュ休暇付与の10年と20年以外は処理しない
		if ($years !== 10 && $years !== 20) {
			return false;
		}

		// 入社年月日の10年後/20年後を算出
		$base = $join_date->modify("+{$years} years");

		// 特別休暇付与日を翌月1日に設定
		$grant_date = $base->modify('first day of next month');

		// 付与年月日
		$action_date = $grant_date->format('Y-m-d');

		// 有休付与日と今日が一致するか判定
		if ($action_date === $this->today->format('Y-m-d')) {

			// 特別休暇付与日数を算出
			$days = $this->_calcSpecialGrantDays($years);

			// 特別休暇付与チェック
			if ($days <= 0) {
				return false;
			}

			// 二重付与チェック
			if ($this->dao->existsSpecialGrant($user['user_id'], $action_date)) {
				return false;
			}

			// 事由
			$comment = ($years === 10) ? '自動付与（10年目）' : '自動付与（20年目）';

			// 有効期限：付与年月日から+1年を設定
			$expire_date = (new \DateTimeImmutable($action_date))
				->modify('+1 year')->format('Y-m-d');

			// 特別休暇付与処理
			$this->_applySpecialGrant($user, $days, $action_date, $comment, $expire_date);

			// ログカウント用の戻り値
			return true;
		}

		return false;
	}

	/**
	 * 特別休暇の付与日数算出
	 *
	 * @access	private
	 * @param	$years
	 * @return	int
	 */
	private function _calcSpecialGrantDays($years) {

		// 10年で3日付与
		if ($years === 10) {
			return 3.0;

		// 20年で5日付与
		} elseif ($years === 20) {
			return 5.0;
		}

		// 想定外の値は0を返す
		return 0;
	}

	/**
	 * 特別休暇付与処理
	 *
	 * @access	private
	 * @param	$user, $days, $action_date, $comment, $expire_date
	 * @return
	 */
	private function _applySpecialGrant($user, $days, $action_date, $comment, $expire_date) {

		// トランザクション開始
		$this->dao->beginTransaction();

		try {

			// 特別休暇付与日数をログテーブルに登録
			$this->dao->insertSpecialGrant(
				$user['user_id'],
				$action_date,
				$days,
				$comment,
				$expire_date
			);

			// 特別休暇残日数を更新
			$this->dao->addUserSpecial($user['user_id'], $days);

			// 成功したらコミット
			$this->dao->commitTransaction();

		} catch (Exception $e) {

			// 失敗したらロールバック
			$this->dao->rollbackTransaction();
		}
	}

	// ============================================================
	// 特別休暇消滅
	// ============================================================

	/**
	 * 特別休暇消滅
	 *
	 * @access	private
	 * @param
	 * @return
	 */
	private function _runSpecialExpire() {

		// 期限切れ特別休暇取得
		$expired_specials = $this->dao->getExpiredSpecials($this->today->format('Y-m-d'));

		// ログ出力カウント
		$total		= 0;	// ユーザー数
		$expired	= 0;	// 消滅数

		// 期限切れ特別休暇を1件ずつ消滅
		foreach ($expired_specials as $expired_special) {
			$total++;
			if ($this->_expireSpecial($expired_special)) {
				$expired++;
			}
		}

		// ログ出力
		echo "[Special Expire] total={$total}, expired={$expired}" . PHP_EOL;
	}

	/**
	 * 特別休暇消滅処理
	 *
	 * @access	private
	 * @param	$special
	 * @return	bool
	 */
	private function _expireSpecial($special) {

		// 消滅日数を取得
		$expire_days = (float)$special['remain_number'];

		// 消滅日数がなければ終了
		if ($expire_days <= 0) {
			return false;
		}

		// トランザクション開始
		$this->dao->beginTransaction();

		try {

			// 消滅フラグを更新
			$this->dao->updateSpecialExpired($special['id']);

			// 有休残日数を更新
			$this->dao->subtractUserSpecial($special['user_id'], $expire_days);

			// 成功したらコミット
			$this->dao->commitTransaction();

			// ログカウント用の戻り値
			return true;

		} catch (Exception $e) {

			// 失敗したらロールバック
			$this->dao->rollbackTransaction();
			return false;
		}
	}

	// ============================================================
	// 共通処理
	// ============================================================

	/**
	 * 入社年月日から経過月数取得
	 *
	 * @access	private
	 * @param	$join_date
	 * @return	int
	 */
	private function _calcElapsedMonths($join_date) {

		// 経過月を判定
		$diff = $join_date->diff($this->today);
		return $diff->y * 12 + $diff->m;
	}

	/**
	 * 有休付与日を算出
	 *
	 * @access	private
	 * @param	$user
	 * @return
	 */
	private function _calcGrantDate($join_date, $month) {

		// 経過後の年月
		$base = $join_date->modify("+{$month} months");

		// 入社日の日
		$join_day = (int)$join_date->format('j');

		// 経過後の月末日
		$last_day = (int)$base->format('t');

		// 日付の末日補正
		$day = min($join_day, $last_day);

		// 付与日を生成
		return $base->setDate(
			(int)$base->format('Y'),
			(int)$base->format('n'),
			$day
		);
	}

	/**
	 * 有休付与日数を算出
	 *
	 * @access	private
	 * @param	$months
	 * @return	int
	 */
	private function _calcGrantDays($months) {

		// 固定付与日数を返す
		if (isset(self::$grant_map[$months])) {
			return self::$grant_map[$months];
		}

		// 6年半以降は12ヶ月毎に20日付与
		if ($months >= 78 && ($months - 78) % 12 === 0) {
			return 20;
		}

		// 想定外の値は0を返す
		return 0;
	}
}
