@props(['label'])
<table class="panel" role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
<tr>
<td class="panel-content code-box" align="center" bgcolor="#fafafa">
<p class="label">{{ $label }}</p>
<p class="code">{{ $slot }}</p>
</td>
</tr>
</table>
