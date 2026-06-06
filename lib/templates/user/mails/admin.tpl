{$role_label}様

{$user_name}さんから休暇申請が提出されました。

□ 氏名：{$user_name}
{if count($dates) > 1}
□ 申請日：
{foreach from=$dates item=date}
　- {$date.display}
{/foreach}
{elseif isset($dates[0])}
□ 申請日：{$dates[0].display}
{/if}
□ 種別：{$types[$requests[0].kind]}
{if $requests[0].kind == 1}
　- {$sub_types[$requests[0].sub_kind]}
{/if}
□ 事由：{$requests[0].comment}
{if count($apply_labels) == 1}
□ 適用：{$apply_labels[0].label}{if $apply_labels[0].days != null}（{$apply_labels[0].days|number_format:1}日）{/if}

{elseif count($apply_labels) > 1}
□ 適用：
{foreach from=$apply_labels item=item}
　- {$item.label}{if $item.days != null}（{$item.days|number_format:1}日）{/if}

{/foreach}
{/if}

休暇申請の承認を行ってください。

直通URL：{$url nofilter}

※このURLの有効期限は120時間です。期限切れ後はログイン画面からログインしてください。
