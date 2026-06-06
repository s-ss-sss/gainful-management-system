{extends file="admin/layout.tpl"}

{block name=content}
	<section class="u-mb-32">
		<div class="heading u-mb-24">
			<h2 class="heading__title u-mb-16">内容確認</h2>
			<p class="heading__text">
				こちらの申請を却下してよろしいですか？<br>
				問題がなければ「却下理由」を入力後<br>
				「却下する」をクリックしてください
			</p>
		</div>

		{* 全体エラー *}
		{if isset($warning)}
			{foreach from=$warning item=msg}
				<p class="error-text u-mb-16">{$msg}</p>
			{/foreach}
		{/if}

		<form method="POST" action="{$BASE_URL}admin/reject/{$form_data.bundle_id}/">

			{* state *}
			<input type="hidden" name="state" value="reject">

			{* CSRFトークン *}
			<input type="hidden" name="csrf_token" value="{$csrf_token}">

			<dl class="form-block__group u-mb-12">
				<div class="form-block__field">
					<dt class="form-block__label">氏名</dt>
					<dd class="form-block__input-wrap">{$form_data.user_name}</dd>
				</div>
				<div class="form-block__field">
					<dt class="form-block__label">取得日</dt>
					<dd class="form-block__input-wrap">
						<ul class="form-block__date-list">
							{foreach from=$form_data.dates item=d}
								<li class="table__item">{$d}</li>
							{/foreach}
						</ul>
					</dd>
				</div>
				<div class="form-block__field">
					<dt class="form-block__label">種別</dt>
					<dd class="form-block__input-wrap">{$form_data.type_label}</dd>
				</div>
				<div class="form-block__field">
					<dt class="form-block__label">事由</dt>
					<dd class="form-block__input-wrap">{$form_data.comment|nl2br nofilter}</dd>
				</div>
			</dl>

			{* 取消理由 *}
			<div class="form-block__group">
				<div class="form-block__field">
					<label for="route" class="form-block__label">却下理由</label>
					<div class="form-block__textarea-wrap">
						<textarea
							name="cancel_reason"
							class="form-block__textarea {if isset($errors.cancel_reason)}error-form{/if}"
							rows="2"
							placeholder="却下理由を入力してください"
						>{$form_data.cancel_reason}</textarea>
						{if isset($errors.cancel_reason)}
							<p class="error-text u-mt-8">{$errors.cancel_reason}</p>
						{/if}
					</div>
				</div>
			</div>

			{* ボタン群 *}
			<div class="button__wrap u-mt-24">
				<a href="{$BASE_URL}admin/" class="button button--sub">戻る</a>
				<button type="submit" class="button button--main">却下する</button>
			</div>
		</form>
	</section>
{/block}
