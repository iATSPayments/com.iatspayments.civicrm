{if $jobLastRunWarning == 1}
<h3>{if $jobOverdue != ''}{ts}Warning!{/ts}{else}{ts}Cron Running{/ts}{/if}</h3>
<p>The current time is {$currentTime}</p>
<p>{ts 1=$jobLastRun}Your iATS Payments cron last ran at %1.{/ts}</p>
<p>{if $jobOverdue != ''}<strong style="font-size: 120%">{ts}Your recurring contributions for iATS Payments requires a correctly setup and functioning cron job that runs daily. You need to take action now.{/ts}</strong>{else}{ts}It's all good.{/ts}{/if}</p>
{/if}

<h3>Recent transations using iATS Payments</h3>
<form method="GET">
<fieldset><legend>Filter results</legend>
<div><em>Filter your results by any part of the last 4 digits of a credit card or the authorization string</em></div> 
CC <input size="20" type="text" name="search_cc" value="{$search.cc}">
Auth <input size="20" type="text" name="search_auth_result" value="{$search.auth_result}">
<input type="submit">
</fieldset>
</form>
<table class="iats-report">
<caption>Recent transactions with the IATS Payment Processor</caption>
<tr>
  <th>{ts}Invoice Number{/ts}</th>
  <th>{ts}IP{/ts}</th>
  <th>{ts}CC{/ts}</th>
  <th>{ts}Total{/ts}</th>
  <th>{ts}Request Datetime{/ts}</th>
  <th>{ts}Auth Result{/ts}</th>
  <th>{ts}Remote ID{/ts}</th>
  <th>{ts}Response Datetime{/ts}</th>
</tr>
{foreach from=$iATSLog item=row}
<tr>
  <td><a href="{$row.contributionURL}">{$row.invoice_num}</a></td>
  <td>{$row.ip}</td>
  <td>{$row.cc}</td>
  <td>{$row.total}</td>
  <td>{$row.request_datetime}</td>
  <td>{$row.auth_result}</td>
  <td>{$row.remote_id}</td>
  <td>{$row.response_datetime}</td>
</tr> 
{/foreach}
</table>
