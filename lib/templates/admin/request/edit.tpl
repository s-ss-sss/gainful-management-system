{extends file="admin/layout.tpl"}

{block name=content}
	<section class="u-mb-32">
		<div class="heading u-mb-24">
			<h2 class="heading__title u-mb-16">申請データ管理</h2>
			<h3 class="heading__title u-mb-16">
				【{$request_user_name}さんの申請データ修正】
			</h3>
			<p class="heading__text">
				追加する日付を<br>
				カレンダーから選択してください
			</p>
		</div>

		{* 全体エラー *}
		{if isset($warning)}
			{foreach from=$warning item=msg}
				<p class="error-text u-mb-16">{$msg}</p>
			{/foreach}
		{/if}

		<form action="{$BASE_URL}admin/request/edit/{$form_data.bundle_id}/" method="POST" class="js-radio-check-form">

			{* state *}
			<input type="hidden" name="state" value="edit">

			{* CSRFトークン *}
			<input type="hidden" name="csrf_token" value="{$csrf_token}">

			{* 修正対象ID *}
			<input type="hidden" name="bundle_id" value="{$form_data.bundle_id}">

			{* カレンダー *}
			<div id="js-single-calendar" class="calendar__wrap u-mb-16"></div>

			{* まとめて追加 *}
			<div class="button__wrap button__wrap--right u-mb-24">
				<button type="button" class="button button--main js-multi-button">まとめて追加</button>
			</div>

			{* 取得日 *}
			<div class="form-block__group u-mb-16">
				<div class="form-block__field">
					<label class="form-block__label">
						<span>取得日<span/>
						<span id="js-apply-total" class="form-block__apply-total"></span>
					</label>
					<ul class="form-block__list-wrap"></ul>
				</div>
			</div>

			{* 取得日エラー *}
			{if isset($errors.date)}
				<p class="error-text u-mt--8 u-mb-16">{$errors.date}</p>
			{/if}

			{* フォーム *}
			<div class="form-block__group u-mb-24">

				{* 氏名 *}
				<div class="form-block__field">
					<label class="form-block__label">氏名</label>
					<div class="form-block__input-wrap">{$request_user_name}</div>
				</div>

				{* 種別 *}
				{assign var=selected_type value=$form_data.type|default:0}
				<div class="form-block__field u-border-bottom-none">
					<label class="form-block__label">種別</label>
					<div class="form-block__input-wrap">
						{foreach from=$types key=num item=type}
							<label class="form-block__radio-wrap">
								<input
									type="radio"
									name="type"
									value="{$num}"
									class="form-block__radio js-type-radio"
									{if $selected_type == $num}checked{/if}
								>{$type}
							</label>
						{/foreach}
						{if isset($errors.type)}
							<p class="error-text u-mt-8">{$errors.type}</p>
						{/if}
					</div>
				</div>

				{* サブ種別 *}
				<div class="form-block__field u-border-top-dashed js-special-field" style="display: none;">
					<label class="form-block__label"></label>
					<div class="form-block__input-wrap">
						<select
							name="sub_type"
							class="form-block__select {if isset($errors.sub_type)}error-form{/if}"
						>
							<option value="">選択してください</option>
							{foreach from=$sub_types key=num item=sub_type}
								<option
									value="{$num}"
									{if $form_data.sub_type == $num}selected{/if}
								>{$sub_type}</option>
							{/foreach}
						</select>
						{if isset($errors.sub_type)}
							<p class="error-text u-mt-8">{$errors.sub_type}</p>
						{/if}
					</div>
				</div>

				{* 事由 *}
				<div class="form-block__field">
					<label class="form-block__label">事由</label>
					<div class="form-block__textarea-wrap">
						<textarea
							name="reason"
							class="form-block__textarea {if isset($errors.reason)}error-form{/if}"
							rows="2"
							placeholder="事由を入力してください"
						>{$form_data.reason}</textarea>
						{if isset($errors.reason)}
							<p class="error-text u-mt-8">{$errors.reason}</p>
						{/if}
					</div>
				</div>
			</div>

			{* ボタン群 *}
			<div class="button__wrap">
				<a href="{$BASE_URL}admin/request/" class="button button--sub">戻る</a>
				<button type="button" class="button button--sub js-clear-form">内容をクリア</button>
				<button type="submit" class="button button--main">修正する</button>
			</div>
		</form>

		{* モーダル：取得日追加 *}
		<div id="js-single-modal" class="modal" style="display: none;">
			<div class="modal__overlay js-modal-close"></div>
			<div class="modal__content">
				<div class="modal__close js-modal-close"></div>
				<h2 class="modal__title u-mb-16"></h2>
				<p class="modal__text u-mb-24">取得日の区分を選択してください</p>
				<ul class="modal__list">
					{foreach from=$sections key=num item=section}
						<li class="modal__item" data-section="{$num}">{$section}</li>
					{/foreach}
				</ul>
			</div>
		</div>

		{* モーダル：まとめて追加 *}
		<div id="js-multi-modal" class="modal" style="display: none;">
			<div class="modal__overlay js-modal-close"></div>
			<div class="modal__content modal__content--wide">
				<div class="modal__close js-modal-close"></div>
				<h2 class="modal__title u-mb-16">まとめて追加</h2>
				<p class="modal__text u-mb-24">
					開始日と終了日を<br>
					カレンダーからクリックしてください
				</p>

				{* カレンダー：まとめて追加 *}
				<div id="js-multi-calendar" class="calendar__wrap u-mb-16"></div>

				{* フォーム *}
				<div class="form-block__group u-mb-24">
					<div class="form-block__field u-border-bottom-dashed">
						<label class="form-block__label">開始日</label>
						<div id="multi-start-date" class="form-block__input-wrap form-block__multi">
							<span class="js-multi-date"></span>
							<select name="start_section" class="form-block__multi-select js-multi-select" hidden>
								<option value="0">午前</option>
								<option value="1">午後</option>
								<option value="2" selected>全休</option>
							</select>
						</div>
					</div>

					<div class="form-block__field u-border-top-none">
						<label class="form-block__label">終了日</label>
						<div id="multi-end-date" class="form-block__input-wrap form-block__multi">
							<span class="js-multi-date"></span>
							<select name="end_section" class="form-block__multi-select js-multi-select" hidden>
								<option value="0">午前</option>
								<option value="1">午後</option>
								<option value="2" selected>全休</option>
							</select>
						</div>
					</div>
				</div>

				{* ボタン群 *}
				<div class="button__wrap">
					<button type="button" id="js-multi-clear" class="button button--sub">内容をクリア</button>
					<button type="button" id="js-multi-add" class="button button--main">取得日に追加</button>
				</div>
			</div>
		</div>
	</section>
{/block}

{block name=footer_script}
	<script>
		const WEEKDAYS		= {$weekdays|@json_encode nofilter};
		const SECTIONS		= {$sections|@json_encode nofilter};
		const HOLIDAYS		= {$holidays|@json_encode nofilter};
		const formDate		= {$form_date|@json_encode nofilter};
		const formSection	= {$form_section|@json_encode nofilter};
		const currentYear	= {$current_year|escape:'javascript'};
		const currentMonth	= {$current_month|escape:'javascript'} - 1; // JSは0スタート
	</script>
{/block}
