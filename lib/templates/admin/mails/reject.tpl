残念ですが、下記の申請は却下されました。

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

{if $status.boss == 'rejected'}
□ 所属長：却下
□ 却下理由：{$requests[0].boss_comment}

{elseif $status.boss == 'approved'}
□ 所属長：承認

{/if}
{if $status.manager == 'rejected'}
□ 社長：却下
□ 却下理由：{$requests[0].manager_comment}
{elseif $status.manager == 'approved'}
□ 社長：承認
{/if}
