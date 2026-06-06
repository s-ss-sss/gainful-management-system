{if isset($flash_message)}
	<script>
		document.addEventListener('DOMContentLoaded', function() {
			setTimeout(() => alert('{$flash_message|escape:"javascript"}'), 100);
		});
	</script>
{/if}
