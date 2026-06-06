$(function() {

	// ==============================
	// グローバル変数
	// ==============================
	const CALENDAR_STATE = {

		// 通常カレンダー
		single: {
			year		: null,
			month		: null,
			readonly	: false
		},

		// まとめて追加カレンダー
		multi: {
			year		: null,
			month		: null,
			startDate	: null,
			endDate		: null,
			sections	: {}
		}
	}

	// ==============================
	// Datepicker
	// ==============================
	$.datepicker.setDefaults($.datepicker.regional['ja']);
	$('.js-datepicker').datepicker({
		dateFormat: 'yy/m/d'
	});

	// ==============================
	// チェックボックスの全選択/全解除
	// ==============================
	$('.js-all-check').on('change', function() {

		// 親要素のtableを取得
		const $table = $(this).closest('table');

		// チェックボックスの全選択/全解除
		$table.find('.js-item-check').prop('checked', $(this).prop('checked'));
	});

	// ==============================
	// 入力内容クリアボタン
	// ==============================
	$(document).on('click', '.js-clear-form', function(e) {

		// フォーム送信を防止
		e.preventDefault();

		// form要素を取得
		const $form = $(this).closest('form');

		// テキスト/セレクトボックス/テキストエリアをクリア
		$form.find('input[type="text"], select, textarea').val('');

		// ラジオボタン/チェックボックスをクリア
		$form.find('input[type="radio"], input[type="checkbox"]').prop('checked', false);

		// クラスが付与されている場合は通常にチェック
		if ($form.hasClass('js-radio-check-form')) {
			$form.find('.js-type-radio[value="0"]').prop('checked', true);
		}

		// サブ種別を非表示
		$form.find('.js-special-field').hide();

		// 申請日リストを初期化
		$form.find('.form-block__list-wrap').empty();

		// 申請日件数と合計日数を更新
		updateApplyTotal();

		// カレンダーのハイライトを全削除
		clearCalendarHighlight();

		// まとめて追加カレンダーを初期化
		resetMultiUI();
		resetMultiState(currentYear, currentMonth);

		// クリックフラグを初期化
		if (typeof CALENDAR_STATE.multi.startDate	!== 'undefined') CALENDAR_STATE.multi.startDate	= null;
		if (typeof CALENDAR_STATE.multi.endDate		!== 'undefined') CALENDAR_STATE.multi.endDate	= null;

		// バリデーションのエラー出力を初期化
		$form.find('.error-text').remove();
		$form.find('.error-form').removeClass('error-form');
	});

	// ==============================
	// Ajax：PHPにデータ送信
	// ==============================
	function ajaxApplyRequests(year, month) {
		return $.ajax({
			url			: '/gainful/ajax/ajax.php',
			type		: 'POST',
			dataType	: 'json',
			data		: {year, month},
			timeout		: 10000 // 10秒でタイムアウト
		}).fail(function(xhr, status, error) {
			console.error(error);
		});
	}

	// ==============================
	// Ajax：2ヶ月分のデータ取得
	// ==============================
	function ajaxApplyRequestsPair(year, month) {

		// 翌月を算出
		let nextYear	= year;
		let nextMonth	= month + 1;

		// 12→13月になる場合は翌月を0→1月に戻す
		if (nextMonth > 11) {
			nextMonth = 0;
			nextYear++;
		}

		// PHPに月は+1スタートで渡す
		const phpMonth		= month + 1;
		const phpNextMonth	= nextMonth + 1;

		// 2ヶ月分のデータを同時に返す
		return $.when(
			ajaxApplyRequests(year, phpMonth),			// 今月
			ajaxApplyRequests(nextYear, phpNextMonth)	// 翌月
		);
	}

	// ==============================
	// Ajax：年月日をキーとした連想配列に整形
	// ==============================
	function buildRequestMap(rows) {

		// 配列を作成
		const map = {};
		if (! Array.isArray(rows)) return map;

		// 申請データを1件ずつ取得
		for (const r of rows) {

			// start_dateを取得
			const date = r.start_date;
			if (! date) continue;

			// 年月日がなければ配列を作成
			if (! map[date]) map[date] = [];

			// 年月日をキーとした配列にデータを格納
			map[date].push({
				user_id		: String(r.user_id),
				user_name	: r.user_name
			});
		}

		return map;
	}

	// ==============================
	// カレンダー：生成
	// ==============================
	function generateCalendar(year, month, options = {}) {

		const firstDate	= new Date(year, month, 1);		// 当月1日のオブジェクトを取得
		const lastDate	= new Date(year, month + 1, 0);	// 当月最終日のオブジェクトを取得
		const firstDay	= firstDate.getDay();			// 当月1日の曜日
		const lastDay	= lastDate.getDate();			// 当月最終日の日付

		// 月送り矢印と表示カレンダーの判定
		const {
			isPrev			= false,
			isNext			= false,
			readonly		= false,
			mode			= 'single',
			applyRequests	= {}
		} = options;

		// カレンダー生成用のHTML
		let html = `
			<table class="calendar">
				<thead class="calendar__thead">
					<tr class="calendar__tr">
						<th class="calendar__th" colspan="7">
							<div class="calendar__nav">
								${isPrev ? `<span class="calendar__arrow calendar__arrow--prev"></span>` : ""}
								<span class="calendar__title">${year}年 ${month + 1}月</span>
								${isNext ? `<span class="calendar__arrow calendar__arrow--next"></span>` : ""}
							</div>
						</th>
					</tr>
					<tr class="calendar__tr">
						<th class="calendar__th calendar__th--day calendar__th--red">日</th>
						<th class="calendar__th calendar__th--day">月</th>
						<th class="calendar__th calendar__th--day">火</th>
						<th class="calendar__th calendar__th--day">水</th>
						<th class="calendar__th calendar__th--day">木</th>
						<th class="calendar__th calendar__th--day">金</th>
						<th class="calendar__th calendar__th--day calendar__th--blue">土</th>
					</tr>
				</thead>
				<tbody class="calendar__tbody">
		`;

		// 日付カウンター
		let dayCount = 1;

		// 6週間までのtrを追加
		for (let i=0; i<6; i++) {
			html += '<tr class="calendar__tr">';

			// 1週間のtdを追加
			for (let j=0; j<7; j++) {

				// 月初と月末の空欄を追加
				if ((i === 0 && j < firstDay) || dayCount > lastDay) {
					html += '<td class="calendar__td">&nbsp;</td>';

				// 日付セルを追加
				} else {

					// 月日を0埋めで形成
					const monthStr	= String(month + 1).padStart(2, '0');
					const dayStr	= String(dayCount).padStart(2, '0');
					const dateStr	= `${year}-${monthStr}-${dayStr}`;

					// 曜日を取得
					const dateObj	= new Date(year, month, dayCount);
					const weekday	= dateObj.getDay();

					// 土日
					const isWeekend = (j === 0 || j === 6);

					// 祝日
					const isHoliday		= HOLIDAYS.hasOwnProperty(dateStr);
					const holidayName	= isHoliday ? HOLIDAYS[dateStr] : '';

					// 当日
					const today = new Date();
					const isToday =
						year === today.getFullYear() &&
						month === today.getMonth() &&
						dayCount === today.getDate();

					// クラスを付与
					let classes		= ['calendar__td'];
					let extraAttr	= '';
					let triangle	= '';

					// 土日祝日の場合
					if (isWeekend || isHoliday) {
						classes.push('calendar__td--disabled');

						// 祝日の場合
						if (isHoliday) {
							classes.push('calendar__td--holiday');
							extraAttr = ` data-holiday-name="${holidayName}"`;
						}

					// 平日の場合
					} else {

						// modeで付与クラスを切り替える
						const typeClass = mode === 'multi'
							? 'calendar__td--multi'
							: 'calendar__td--single';

						// クラスを付与
						if (! readonly) {
							classes.push(typeClass, 'calendar__td--active');
						}
					}

					// 当日の場合
					if (isToday) classes.push('calendar__td--today');

					// 申請データがある場合
					const reqs = applyRequests[dateStr] || [];
					if (reqs.length > 0) {

						// 申請日にクラスを付与
						classes.push('calendar__td--request');

						// 申請された名前を抽出
						const users = Array.from(new Set(reqs.map(u => u.user_name)));

						// HTMLに埋め込める形にエスケープ
						const safeNames = users.join('\n')
							.replace(/"/g, '&quot;')
							.replace(/\n/g, '&#10;');

						// データ属性と子要素を付与
						extraAttr	+= ` data-username="${safeNames}"`;
						triangle	= '<span class="calendar__triangle"></span>';
					}

					// 日付セルをHTMLに追加
					html += `
						<td class="${classes.join(' ')}"
							data-date="${year}-${monthStr}-${dayStr}"
							data-weekday="${weekday}"
							${extraAttr}>
							${dayCount}
							${triangle}
						</td>
					`;

					// 日付カウンターを更新
					dayCount++;
				}
			}
			html += '</tr>';

			// 日付の入っている週で処理終了
			if (dayCount > lastDay) break;
		}
		html += '</tbody></table>';
		return html;
	}

	// ==============================
	// カレンダー：描画
	// ==============================
	function renderCalendar(stateKey) {

		// データ取得
		const state		= CALENDAR_STATE[stateKey];
		const target	= `#js-${stateKey}-calendar`;
		const mode		= stateKey;

		// Ajaxで申請データ取得
		ajaxApplyRequestsPair(state.year, state.month).done((res1, res2) => {

			// PHPから返ったJSONデータ取得
			const dataThis	= res1[0];	// 当月
			const dataNext	= res2[0];	// 翌月

			// 年月日をキーとした連想配列のデータを取得
			const mapThis	= buildRequestMap(dataThis);
			const mapNext	= buildRequestMap(dataNext);

			// 翌月を算出
			let nextYear	= state.year;
			let nextMonth	= state.month + 1;

			// 12→13月になる場合は翌月を0→1月に戻す
			if (nextMonth > 11) {
				nextMonth = 0;
				nextYear++;
			}

			// カレンダー描画
			$(target).html(
				generateCalendar(state.year, state.month, {
					isPrev			: true,
					readonly		: state.readonly, mode,
					applyRequests	: mapThis
				}) +
				generateCalendar(nextYear, nextMonth, {
					isNext			: true,
					readonly		: state.readonly, mode,
					applyRequests	: mapNext
				})
			);

			// 通常カレンダー
			if (mode === 'single' && ! state.readonly) {

				// 申請日リストを取得
				const selectedDates = $('input[name="date[]"]').map(function() {
					return $(this).val();
				}).get();

				// ハイライト復元
				highlightSingleDates(selectedDates);
			}

			// まとめて追加カレンダー
			if (mode === 'multi') {

				// 開始日のみ選択時のハイライト復元
				if (state.startDate && ! state.endDate) {
					$(`.calendar__td--multi[data-date="${state.startDate}"]`).addClass('calendar__td--selected');
				}

				// 開始日と終了日を選択時のハイライト復元
				if (state.startDate && state.endDate) {
					highlightMultiRange(state.startDate, state.endDate);
				}
			}
		});
	}

	// ==============================
	// カレンダー：月送りスライド
	// ==============================
	function bindCalendarNav(stateKey) {

		// データ取得
		const state		= CALENDAR_STATE[stateKey];
		const target	= `#js-${stateKey}-calendar`;

		// イベント重複防止
		$(target).off('click', '.calendar__arrow--prev');
		$(target).off('click', '.calendar__arrow--next');

		// 先月移動
		$(target).on('click', '.calendar__arrow--prev', function() {
			state.month--;
			if (state.month < 0) {
				state.month = 11;
				state.year--;
			}
			renderCalendar(stateKey);
		});

		// 翌月移動
		$(target).on('click', '.calendar__arrow--next', function() {
			state.month++;
			if (state.month > 11) {
				state.month = 0;
				state.year++;
			}
			renderCalendar(stateKey);
		});
	}

	// ==============================
	// カレンダー：通常表示
	// ==============================

	// 表示年月が存在するページ用のガード
	if (typeof currentYear !== 'undefined' && typeof currentMonth !== 'undefined') {

		// 状態を初期化
		CALENDAR_STATE.single.year		= currentYear;
		CALENDAR_STATE.single.month		= currentMonth;
		CALENDAR_STATE.single.readonly	= String($('#js-single-calendar').data('readonly')).toLowerCase() === 'true';

		// カレンダーの初回描画
		renderCalendar('single');

		// カレンダーの月送りスライド
		bindCalendarNav('single');
	}

	// ==============================
	// カレンダー：区分選択
	// ==============================
	$(document).on('click', '.calendar__td--single', function() {

		// 多重クリック防止
		if ($('#js-single-modal').is(':visible')) return;

		// data属性を取得
		const selectedDate		= $(this).data('date');
		const selectedWeekday	= parseInt($(this).data('weekday'), 10);

		// 日付をYYYY/MM/DDに変換して0を削除
		const dateLabel = selectedDate
			.replaceAll('-', '/')
			.replace(/\/0(\d)/g, '/$1');

		// data属性を曜日に変換
		const weekdayLabel = WEEKDAYS[selectedWeekday];

		// モーダルのタイトルを変更
		$('#js-single-modal .modal__title').text(`${dateLabel}（${weekdayLabel}）`);

		// モーダルにdate属性を保存
		$('#js-single-modal')
			.data('selectedDate', selectedDate)
			.data('selectedWeekday', selectedWeekday)
			.fadeIn();
	});

	// ==============================
	// カレンダー：申請日追加
	// ==============================
	$(document).on('click', '#js-single-modal .modal__item', function() {

		// 区分を取得
		const sectionId		= parseInt($(this).data('section'), 10);
		const sectionLabel	= $(this).text();

		// 日付を取得
		const selectedDate		= $('#js-single-modal').data('selectedDate');
		const selectedWeekday	= parseInt($('#js-single-modal').data('selectedWeekday'), 10);

		// 日付をYYYY/MM/DDに変換して0を削除
		const dateLabel = selectedDate
			.replaceAll('-', '/')
			.replace(/\/0(\d)/g, '/$1');

		// data属性を曜日に変換
		const weekdayLabel = WEEKDAYS[selectedWeekday];

		// 追加テキスト
		const displayText = `${dateLabel}（${weekdayLabel}）${sectionLabel}`;

		// 選択した新規リストを配列に追加
		const items = [{
			date	: selectedDate,
			section	: sectionId,
			text	: displayText
		}];

		// 申請日追加
		updateRequestList(items);

		// モーダルを閉じる
		$('#js-single-modal').fadeOut();
	});

	// ==============================
	// カレンダー：選択日のハイライト
	// ==============================
	function highlightSingleDates(selectedDates = []) {

		// ハイライトクラスを削除
		// $('.calendar__td--single').removeClass('calendar__td--selected');
		clearCalendarHighlight('single');

		// ハイライトクラスを付与
		selectedDates.forEach(date => {
			$(`.calendar__td--single[data-date="${date}"]`).addClass('calendar__td--selected');
		});
	}

	// ==============================
	// カレンダー：選択日のハイライトクリア
	// ==============================
	function clearCalendarHighlight(type = null) {

		// 通常カレンダー
		if (type === 'single') {
			$('.calendar__td--single').removeClass('calendar__td--selected');

		// まとめて追加カレンダー
		} else if (type === 'multi') {
			$('.calendar__td--multi').removeClass('calendar__td--selected');

		// 全削除
		} else {
			$('.calendar__td--single, .calendar__td--multi').removeClass('calendar__td--selected');
		}
	}

	// ==============================
	// カレンダー：申請日追加
	// ==============================
	function updateRequestList(items) {

		// 既存リストを配列化
		$('.form-block__list-wrap li').each(function() {

			// 現在のリストの中身を取得
			const date		= $(this).find('input[name="date[]"]').val();
			const section	= parseInt($(this).find('input[name="section[]"]').val(), 10);
			const text		= $(this).find('.form-block__text').text();

			// 取得したリストの中身を配列に追加
			items.push({date, section, text});
		});

		// 配列を日付と区分順にソート
		items.sort((a, b) => {

			// 日付文字列をDate型に変換
			const dateA = new Date(a.date);
			const dateB = new Date(b.date);

			// 日付が異なる場合は昇順にソート
			if (dateA.getTime() !== dateB.getTime()) {
				return dateA - dateB;
			}

			// 日付が同じ場合は区分でソート
			return a.section - b.section;
		});

		// リストを再描画
		const $wrap = $('.form-block__list-wrap').empty();

		// 申請日に追加
		items.forEach(item => {
			$wrap.append(`
				<li class="form-block__list">
					<p class="form-block__text">${item.text}</p>
					<span class="form-block__delete js-delete-item"></span>
					<input type="hidden" name="date[]" value="${item.date}">
					<input type="hidden" name="section[]" value="${item.section}">
				</li>
			`);
		});

		// 申請日件数と合計日数を更新
		updateApplyTotal();

		// 対応するカレンダーセルにハイライトクラスを付与
		highlightSingleDates(items.map(item => item.date));
	}

	// ==============================
	// 初期表示でセッションデータ反映
	// ==============================
	if (typeof formDate !== 'undefined' && Array.isArray(formDate) && formDate.length > 0) {

		const items = formDate.map((date, i) => {

			// 区分を取得
			const section		= parseInt(formSection[i], 10);
			const sectionLabel	= SECTIONS[section];

			// 日付をYYYY/MM/DDに変換して0を削除
			const dateLabel = date
				.replaceAll('-', '/')
				.replace(/\/0(\d)/g, '/$1');

			// data属性を曜日に変換
			const weekdayLabel = WEEKDAYS[new Date(date).getDay()];

			// 追加テキスト
			const text = `${dateLabel}（${weekdayLabel}）${sectionLabel}`;

			// 取得したリストの中身を配列に追加
			return {date, section, text};
		});

		// リストを再描画
		updateRequestList(items);
	}

	// ==============================
	// まとめて追加：モーダルカレンダー表示
	// ==============================
	$(document).on('click', '.js-multi-button', function() {

		// 多重クリック防止
		if ($('#js-multi-modal').is(':visible')) return;

		// モーダルを開く
		$('#js-multi-modal').fadeIn();

		// 年月を設定
		CALENDAR_STATE.multi.year	= currentYear;
		CALENDAR_STATE.multi.month	= currentMonth;

		// カレンダーの初回描画
		renderCalendar('multi');

		// カレンダーの月送りスライド
		bindCalendarNav('multi');
	});

	// ==============================
	// まとめて追加：申請日追加
	// ==============================
	$(document).on('click', '.calendar__td--multi', function() {

		// クリックした日付を取得
		const selectedDate		= $(this).data('date');
		const selectedWeekday	= parseInt($(this).data('weekday'), 10);

		// 日付をYYYY/MM/DDに変換して0を削除
		const dateLabel = selectedDate
			.replaceAll('-', '/')
			.replace(/\/0(\d)/g, '/$1');

		// data属性を曜日に変換
		const weekdayLabel = WEEKDAYS[selectedWeekday];

		// ==============================
		// 1回目のクリック
		// ==============================
		if (! CALENDAR_STATE.multi.startDate) {

			// 1回目クリックフラグ
			CALENDAR_STATE.multi.startDate = selectedDate;

			// ハイライトクラスを削除
			clearCalendarHighlight('multi');

			// ハイライトクラスを付与
			$(`.calendar__td--multi[data-date="${selectedDate}"]`).addClass('calendar__td--selected');

			// モーダルの開始日に反映
			setMultiDate($('#multi-start-date'), selectedDate, `${dateLabel}（${weekdayLabel}）`);
			return;
		}

		// ==============================
		// 2回目以降のクリック
		// ==============================

		// 2回目クリックフラグ
		CALENDAR_STATE.multi.endDate = selectedDate;

		// ハイライトを呼び出し入れ替え後の日付を受け取る
		const {start, end} = highlightMultiRange(CALENDAR_STATE.multi.startDate, CALENDAR_STATE.multi.endDate);

		// 開始日と終了日が同日
		if (start === end) {

			// 2回目クリックフラグ初期化
			CALENDAR_STATE.multi.startDate	= start;
			CALENDAR_STATE.multi.endDate	= null;

			// 終了日リセット
			resetMultiEnd()

		// 通常時
		} else {

			// 日付を上書き
			CALENDAR_STATE.multi.startDate	= start;
			CALENDAR_STATE.multi.endDate	= end;

			// 日付文字列の更新
			const startLabel	= `${CALENDAR_STATE.multi.startDate.replaceAll('-', '/').replace(/\/0(\d)/g, '/$1')}（${WEEKDAYS[new Date(CALENDAR_STATE.multi.startDate).getDay()]}）`;
			const endLabel		= `${CALENDAR_STATE.multi.endDate.replaceAll('-', '/').replace(/\/0(\d)/g, '/$1')}（${WEEKDAYS[new Date(CALENDAR_STATE.multi.endDate).getDay()]}）`;

			// モーダルの開始日に反映
			setMultiDate($('#multi-start-date'), start, startLabel);

			// モーダルの終了日に反映
			setMultiDate($('#multi-end-date'), end, endLabel);
		}
	});

	// ==============================
	// まとめて追加：区分変更の保持
	// ==============================
	$(document).on('change', '.js-multi-select', function() {

		// 開始日/終了日欄
		const $wrap	= $(this).closest('#multi-start-date, #multi-end-date');

		// 開始日/終了日の区分
		const date	= $wrap.is('#multi-start-date')
			? CALENDAR_STATE.multi.startDate
			: CALENDAR_STATE.multi.endDate;

		// 開始日/終了日の区分があれば配列に保存
		if (date) CALENDAR_STATE.multi.sections[date] = $(this).val();
	});

	// ==============================
	// まとめて追加：申請日欄にデータ追加
	// ==============================
	function setMultiDate($container, date, label) {

		// 保存されている区分がなければ全休='2'に設定
		const savedSection = CALENDAR_STATE.multi.sections[date] ?? '2';

		// 申請日を追加
		$container.find('.js-multi-date').text(label);

		// セレクトボックスを表示して区分を追加
		$container.find('.js-multi-select').prop('hidden', false).val(savedSection);
	}

	// ==============================
	// まとめて追加：選択範囲のハイライト
	// ==============================
	function highlightMultiRange(startStr, endStr) {

		// クリックフラグチェック
		if (! startStr || ! endStr) return;

		// 開始日と終了日をDate型に変換
		let startDate	= new Date(startStr);
		let endDate		= new Date(endStr);

		// 開始日 > 終了日の場合は入れ替え
		if (startDate > endDate) {
			[startDate, endDate]	= [endDate, startDate];
			[startStr, endStr]		= [endStr, startStr];
		}

		// 既存のハイライトをクリア
		clearCalendarHighlight('multi');

		// 対応するカレンダーセルの期間にハイライトクラスを再描画
		$('.calendar__td--multi').each(function() {

			// 選択日の日付文字列を取得
			const dateStr = $(this).data('date');

			// 空の日付はスキップ
			if (! dateStr) return;

			// 日付文字列をDate型に変換
			const currentDate = new Date(dateStr);

			// ハイライトクラスを付与
			$(this).toggleClass('calendar__td--selected', currentDate >= startDate && currentDate <= endDate);
		});

		// 入れ替え後の値を返す
		return {start: startStr, end: endStr};
	}

	// ==============================
	// まとめて追加：申請日一括追加
	// ==============================
	$(document).on('click', '#js-multi-add', function() {

		// ハイライト付きセルを取得
		const $selectedCells = $('.calendar__td--multi.calendar__td--selected');

		// 選択日がない場合はアラート
		if ($selectedCells.length === 0) {
			alert('追加する日付を選択してください');
			return;
		}

		// 追加用の配列
		const items = [];

		// 申請日データを取得
		const startDate	= CALENDAR_STATE.multi.startDate;	// 開始日
		const endDate	= CALENDAR_STATE.multi.endDate;		// 終了日
		const sections	= CALENDAR_STATE.multi.sections;	// 区分

		// 配列に格納する処理
		$selectedCells.each(function() {

			// ハイライト付きセルからデータを取得
			const dateStr	= $(this).data('date');
			const weekday	= parseInt($(this).data('weekday'), 10);

			// 日付をYYYY/MM/DDに変換して0を削除
			const dateLabel = dateStr
				.replaceAll('-', '/')
				.replace(/\/0(\d)/g, '/$1');

			// data属性を曜日に変換
			const weekdayLabel = WEEKDAYS[weekday];

			// 区分のデフォルトは全休='2'
			let section = '2';

			// 開始日
			if (dateStr === startDate) {
				section = sections[dateStr] ?? '2';

			// 終了日
			} else if (endDate && dateStr === endDate) {
				section = sections[dateStr] ?? '2';
			}

			// 区分テキスト
			const sectionLabel = SECTIONS[section];

			// 追加テキスト
			const displayText = `${dateLabel}（${weekdayLabel}）${sectionLabel}`;

			// 配列にデータを格納
			items.push({
				date	: dateStr,
				section	: section,
				text	: displayText
			});
		});

		// 申請日追加
		updateRequestList(items);

		// 申請日件数と合計日数を更新
		updateApplyTotal();

		// モーダルを閉じた後にリセット
		$('#js-multi-modal').fadeOut(200, function() {
			resetMultiAddModal();
		});
	});

	// ==============================
	// まとめて追加：モーダル内のリセット
	// ==============================
	function resetMultiAddModal() {

		// カレンダーのハイライトを削除
		clearCalendarHighlight('multi');

		// 開始日と終了日をクリア
		resetMultiUI();

		// クリックフラグをリセット
		resetMultiState(currentYear, currentMonth);
	}

	// ==============================
	// まとめて追加：UIリセット
	// ==============================
	function resetMultiUI() {

		// 開始日リセット
		resetMultiStart();

		// 終了日リセット
		resetMultiEnd();
	}

	// ==============================
	// まとめて追加：開始日リセット
	// ==============================
	function resetMultiStart() {

		// 開始日
		$('#multi-start-date .js-multi-date').text('');							// 申請日
		$('#multi-start-date .js-multi-select').prop('hidden', true).val('2');	// 区分
	}

	// ==============================
	// まとめて追加：終了日リセット
	// ==============================
	function resetMultiEnd() {

		// 終了日
		$('#multi-end-date .js-multi-date').text('');							// 申請日
		$('#multi-end-date .js-multi-select').prop('hidden', true).val('2');	// 区分
	}

	// ==============================
	// まとめて追加：状態リセット（プロパティ上書き）
	// ==============================
	function resetMultiState(year, month) {
		CALENDAR_STATE.multi.year		= year;
		CALENDAR_STATE.multi.month		= month;
		CALENDAR_STATE.multi.startDate	= null;
		CALENDAR_STATE.multi.endDate	= null;
		CALENDAR_STATE.multi.sections	= {};
	}

	// ==============================
	// まとめて追加：入力内容クリアボタン
	// ==============================
	$(document).on('click', '#js-multi-clear', function(e) {

		// フォーム送信を防止
		e.preventDefault();

		// モーダル内のリセット
		resetMultiAddModal();
	});

	// ==============================
	// 休暇申請：モーダルを閉じる
	// ==============================
	$(document).on('click', '.js-modal-close', function() {
		$('#js-single-modal, #js-multi-modal').fadeOut();
	});

	// ==============================
	// 休暇申請：特別の選択でサブ項目表示
	// ==============================

	// 初期で特別の選択で表示（修正画面用）
	if ($('.js-type-radio:checked').val() === '1') {
		$('.js-special-field').show();
	} else {
		$('.js-special-field').hide();
	}

	$('.js-type-radio').on('change', function() {

		// プルダウンの種別を取得
		const selectedValue = $(this).val();

		// 特別の選択で表示
		if (selectedValue === '1') {
			$('.js-special-field').slideDown();

		// 通常の選択で非表示
		} else {
			$('.js-special-field').slideUp();
		}
	});

	// ==============================
	// 休暇申請：申請日リスト削除
	// ==============================
	$(document).on('click', '.js-delete-item', function() {

		// 申請日リストを取得
		const $li = $(this).closest('.form-block__list');

		// 日付を取得
		const selectedDate = $li.find('input[name="date[]"]').val();

		// 申請日リストを削除
		$li.remove()

		// 同日の日付数を取得
		const selectedDateLength = $(`input[name="date[]"][value="${selectedDate}"]`).length;

		// 申請日件数と合計日数を更新
		updateApplyTotal();

		// 同日の日付数が0の場合はハイライトクラスを削除
		if (selectedDateLength === 0) {
			$(`.calendar__td--single[data-date="${selectedDate}"]`).removeClass('calendar__td--selected');
		}
	});

	// ==============================
	// 休暇申請：申請日件数と合計日数
	// ==============================
	function updateApplyTotal() {

		// 区分を取得
		const $sections = $('input[name="section[]"]');

		// 初期状態は0を設定
		let count	= 0;
		let total	= 0;

		// 区分から合計日数算出
		$sections.each(function() {

			// 区分の値を取得
			const section = $(this).val();

			// 申請日件数カウント
			count++;

			// 全休は合計日数+1
			if (section === '2') {
				total += 1;

			// 午前/午後は合計日数+0.5
			} else {
				total += 0.5;
			}
		});

		// 0件の場合は表示しない
		if (count === 0) {
			$('#js-apply-total').text('');
			return;
		}

		// 申請日件数と合計日数を追加
		$('#js-apply-total').text(`（${count}件 ${total.toFixed(1)}日）`);
	}

	// ==============================
	// 休暇承認：承認アラート
	// ==============================
	$(document).on('click', '.js-approval-button', function(e) {

		// チェックされた申請をカウント
		const checked = $('input[name="approval[]"]:checked').length;

		// チェックがない場合は処理中断
		if (checked === 0) {
			alert('承認する申請をチェックしてください');

			// フォーム送信を防止
			e.preventDefault();
			return;
		}

		// 承認確認のポップアップ
		const proceed = confirm(`${checked}件の申請を承認してよろしいですか？`);

		// キャンセルされた場合は処理中断
		if (! proceed) {

			// フォーム送信を防止
			e.preventDefault();
			return;
		}
	});

	// ==============================
	// ユーザー管理：残日数の入力補正
	// ==============================
	$(document).on('blur', '.js-half-input', function () {

		// 入力データを数値に変換
		let num	= parseFloat($(this).val().trim());

		// 数値以外は0.0で返す
		if (isNaN(num)) {
			$(this).val('0.0');
			return;
		}

		// 整数は.0を付与
		if (Number.isInteger(num)) {
			$(this).val(num.toFixed(1));
		} else {
			$(this).val(num);
		}
	});

	// ==============================
	// 削除・取消アラート
	// ==============================
	function handleActionClick(selector, config) {
		$(document).on('click', selector, function(e) {

			// フォーム送信を防止
			e.preventDefault();

			// 削除確認のポップアップ
			if (! confirm(config.message)) {
				return;
			}

			// 削除IDを取得
			const id = $(this).data('id');

			// formを取得
			const form = $(this).closest('form');

			// hiddenを生成
			$('<input>', {
				type	: 'hidden',
				name	: config.inputName,
				value	: id
			}).appendTo(form);

			// フォーム送信
			form.submit();
		});
	}

	// 削除アラート
	handleActionClick('.js-delete-button', {
		message		: '削除してよろしいですか？',
		inputName	: 'delete_id'
	});

	// 取消アラート
	handleActionClick('.js-cancel-button', {
		message		: '取り消してよろしいですか？',
		inputName	: 'bundle_id'
	});

	// ==============================
	// ログイン画面：パスワード表示
	// ==============================
	$(document).on('click', '.login__toggle-icon', function() {

		// パスワード入力のテキストボックスを取得
		const $icon		= $(this);
		const $input	= $icon.closest('.login__control').find('input');

		// テキストボックスの属性を取得
		const isPassword = $input.attr('type') === 'password';

		// テキストボックスの属性を切り替える
		$input.attr('type', isPassword ? 'text' : 'password');

		// アイコン画像を切り替える
		$icon.attr('src', isPassword ? $icon.data('open') : $icon.data('close'));
	});
});
