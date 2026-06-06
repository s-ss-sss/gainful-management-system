{extends file="admin/layout.tpl"}

{block name=content}
	<section class="u-mb-32">
		<div class="heading u-mb-24">
			<h2 class="heading__title u-mb-16">特別休暇管理</h2>
			<p class="heading__text">
				内容を入力後<br>
				「修正する」を押してください
			</p>
		</div>

		{* 全体エラー *}
		{if isset($warning)}
			{foreach from=$warning item=msg}
				<p class="error-text u-mb-16">{$msg}</p>
			{/foreach}
		{/if}

		<form action="{$BASE_URL}admin/special/edit/{$form_data.special_id}/" method="POST">

			{* state *}
			<input type="hidden" name="state" value="edit">

			{* CSRFトークン *}
			<input type="hidden" name="csrf_token" value="{$csrf_token}">

			{* 修正対象ID *}
			<input type="hidden" name="special_id" value="{$form_data.special_id}">

			{* フォーム *}
			<div class="form-block__group u-mb-24">

				{* 氏名 *}
				<div class="form-block__field">
					<label class="form-block__label">氏名</label>
					<div class="form-block__input-wrap">
						{$request_user_name}
					</div>
				</div>

				{* 種別 *}
				<div class="form-block__field">
					<label class="form-block__label">種別</label>
					<div class="form-block__input-wrap">
						{if $is_locked}
							{$sub_types[$form_data.sub_type]}
						{else}
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
						{/if}
						{if isset($errors.sub_type)}
							<p class="error-text u-mt-8">{$errors.sub_type}</p>
						{/if}
					</div>
				</div>

				{* 付与日 *}
				<div class="form-block__field">
					<label class="form-block__label">付与日</label>
					<div class="form-block__input-wrap">
						<div class="form-block__datepicker datepicker-wrap">
							<input
								type="text"
								name="grant_date"
								value="{$form_data.grant_date}"
								class="form-block__input js-datepicker {if isset($errors.grant_date)}error-form{/if}"
								readonly
							>
						</div>
						{if isset($errors.grant_date)}
							<p class="error-text u-mt-8">{$errors.grant_date}</p>
						{/if}
					</div>
				</div>

				{* 付与日数 *}
				<div class="form-block__field">
					<div class="form-block__label">付与日数</div>
					<div class="form-block__input-wrap">
						{if $is_locked}
							{$form_data.add_number|number_format:1}日
						{else}
							<div class="form-block__unit-wrap">
								<input
									type="number"
									step="0.5"
									name="add_number"
									value="{if is_numeric($form_data.add_number)}{$form_data.add_number|number_format:1}{/if}"
									class="form-block__input form-block__input--number js-half-input
										{if isset($errors.add_number)}error-form{/if}"
								>
								<span class="form-block__unit">日</span>
							</div>
						{/if}
						{if isset($errors.add_number)}
							<p class="error-text u-mt-8">{$errors.add_number}</p>
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
				<a href="{$BASE_URL}admin/special/" class="button button--sub">戻る</a>
				<button type="submit" class="button button--main">修正する</button>
			</div>
		</form>
	</section>
{/block}
