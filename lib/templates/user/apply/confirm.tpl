{extends file="user/layout.tpl"}

{block name=content}
	<section class="confirm u-mb-32">
		<div class="heading u-mb-24">
			<h2 class="heading__title u-mb-16">内容確認</h2>
			<p class="heading__text">
				こちらの内容で申請してよろしいですか？<br>
				問題がなければ「申請する」を押してください
			</p>
		</div>

		{* 全体エラー *}
		{if isset($warning)}
			{foreach from=$warning item=msg}
				<p class="error-text u-mb-16">{$msg}</p>
			{/foreach}
		{/if}

		<form action="{$BASE_URL}" method="POST">

			{* state *}
			<input type="hidden" name="state" value="complete">

			{* CSRFトークン *}
			<input type="hidden" name="csrf_token" value="{$csrf_token}">

			<div class="form-block__group u-mb-16">

				{* 申請日 *}
				<div class="form-block__field">
					<label for="user_id" class="form-block__label">
						<span>申請日</span>
						<span class="form-block__apply-total">
							（{$summary.count}件 {$summary.total}日）
						</span>
					</label>
					<ul class="form-block__list-wrap">
						{foreach from=$dates item=date}
							<li class="form-block__list">
								<p class="form-block__text">
									{$date.display}
								</p>
								<input type="hidden" name="date[]" value="{$date.date}">
								<input type="hidden" name="section[]" value="{$date.section}">
							</li>
						{/foreach}
					</ul>
				</div>

				{* 種別 *}
				<div class="form-block__field">
					<label for="user_id" class="form-block__label">種別</label>
					<div class="form-block__input-wrap">
						{if $form_data.type == 1}
							{$types[$form_data.type]}：{$sub_types[$form_data.sub_type]}
							<input type="hidden" name="type" value="{$form_data.type}">
							<input type="hidden" name="sub_type" value="{$form_data.sub_type}">
						{else}
							{$types[$form_data.type]}
							<input type="hidden" name="type" value="{$form_data.type}">
						{/if}
					</div>
				</div>

				{* 事由 *}
				<div class="form-block__field">
					<label for="name" class="form-block__label">事由</label>
					<div class="form-block__input-wrap">
						{$form_data.reason|nl2br nofilter}
						<input type="hidden" name="reason" value="{$form_data.reason}">
					</div>
				</div>
			</div>

			{* ボタン群 *}
			<div class="button__wrap u-mt-24">
				<a href="{$BASE_URL}" class="button button--sub">戻る</a>
				<button type="submit" class="button button--main">申請する</button>
			</div>
		</form>
	</section>
{/block}
