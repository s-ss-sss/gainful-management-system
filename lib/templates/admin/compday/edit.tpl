{extends file="admin/layout.tpl"}

{block name=content}
	<section class="u-mb-32">
		<div class="heading u-mb-24">
			<h2 class="heading__title u-mb-16">代休管理</h2>
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

		<form action="{$BASE_URL}admin/compday/edit/{$form_data.compday_id}/" method="POST">

			{* state *}
			<input type="hidden" name="state" value="edit">

			{* CSRFトークン *}
			<input type="hidden" name="csrf_token" value="{$csrf_token}">

			{* 修正対象ID *}
			<input type="hidden" name="compday_id" value="{$form_data.compday_id}">

			{* フォーム *}
			<div class="form-block__group u-mb-24">

				{* 氏名 *}
				<div class="form-block__field">
					<label class="form-block__label">氏名</label>
					<div class="form-block__input-wrap">
						{$request_user_name}
					</div>
				</div>

				{* 休日勤務日 *}
				<div class="form-block__field">
					<label class="form-block__label">休日勤務日</label>
					<div class="form-block__input-wrap">
						<div class="form-block__datepicker datepicker-wrap">
							<input
								type="text"
								name="work_date"
								value="{$form_data.work_date}"
								class="form-block__input js-datepicker {if isset($errors.work_date)}error-form{/if}"
								readonly
							>
						</div>
						{if isset($errors.work_date)}
							<p class="error-text u-mt-8">{$errors.work_date}</p>
						{/if}
					</div>
				</div>

				{* 付与日数 *}
				<div class="form-block__field">
					<label class="form-block__label">付与日数</label>
					<div class="form-block__input-wrap">
						{if $is_linked}
							{$form_data.add_number|number_format:1}日
						{else}
							{foreach from=$add_numbers item=a}
								<label class="form-block__radio-wrap">
									<input
										type="radio"
										name="add_number"
										value="{$a|number_format:1}"
										class="form-block__radio"
										{if $form_data.add_number == $a}checked{/if}
									>{$a|number_format:1}日
								</label>
							{/foreach}
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
				<a href="{$BASE_URL}admin/compday/" class="button button--sub">戻る</a>
				<button type="submit" class="button button--main">修正する</button>
			</div>
		</form>
	</section>
{/block}
