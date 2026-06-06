{extends file="admin/layout.tpl"}

{block name=content}
	<section class="u-mb-32">
		<div class="heading u-mb-24">
			<h2 class="heading__title u-mb-16">代休管理</h2>
			<p class="heading__text">
				対象にチェックを入れて内容を入力後<br>
				「付与する」を押してください
			</p>
		</div>

		{* 全体エラー *}
		{if isset($warning)}
			{foreach from=$warning item=msg}
				<p class="error-text u-mb-16">{$msg}</p>
			{/foreach}
		{/if}

		<form action="{$BASE_URL}admin/compday/create/" method="POST">

			{* state *}
			<input type="hidden" name="state" value="create">

			{* CSRFトークン *}
			<input type="hidden" name="csrf_token" value="{$csrf_token}">

			{* ユーザーテーブル *}
			<div class="table__wrap u-mb-16">
				<table class="table">
					<colgroup>
						<col style="width: 50px;">
						<col style="width: 87px;">
						<col style="width: auto;">
						<col style="width: auto;">
					</colgroup>
					<thead class="table__thead">
						<tr class="table__tr">
							<th class="table__th table__th--checkbox">
								<label class="table__checkbox-label">
									<input type="checkbox" class="table__checkbox js-all-check">
								</label>
							</th>
							<th class="table__th">ID</th>
							<th class="table__th">氏名</th>
							<th class="table__th">所属</th>
						</tr>
					</thead>
					<tbody class="table__tbody">
						{foreach from=$users item=u}
							<tr class="table__tr">

								{* チェックボックス *}
								<td class="table__td table__td--checkbox">
									<label class="table__checkbox-label">
										<input
											type="checkbox"
											name="user_ids[]"
											value="{$u.user_id}"
											class="table__checkbox js-item-check {if isset($errors.user_ids)}error-form{/if}"
											{if in_array($u.user_id, $form_data.user_ids)}checked{/if}
										>
									</label>
								</td>

								{* ID *}
								<td class="table__td">
									{$u.user_id}
								</td>

								{* 氏名 *}
								<td class="table__td">
									{$u.name}
								</td>

								{* 所属 *}
								<td class="table__td">
									{$apply_auth[$u.auth]}
								</td>
							</tr>
						{/foreach}
					</tbody>
				</table>
				{if isset($errors.user_ids)}
					<p class="error-text u-mt-8">{$errors.user_ids}</p>
				{/if}
			</div>

			{* フォーム *}
			<div class="form-block__group u-mb-24">

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
					<div class="form-block__label">付与日数</div>
					<div class="form-block__input-wrap">
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
				<button type="submit" class="button button--main">付与する</button>
			</div>
		</form>
	</section>
{/block}
