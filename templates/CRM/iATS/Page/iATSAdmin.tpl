<h3>Additional iATS-specific recent transaction information.</h3>

<p>The current time is {$currentTime}</p>

<table class="iats-report">
<tr>
  <th>{ts}Invoice Number{/ts}</th>
  <th>{ts}IP{/ts}</th>
  <th>{ts}CC{/ts}</th>
  <th>{ts}Customer Code{/ts}</th>
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
  <td>{$row.customer_code}</td>
  <td>{$row.total}</td>
  <td>{$row.request_datetime}</td>
  <td>{$row.auth_result}</td>
  <td>{$row.remote_id}</td>
  <td>{$row.response_datetime}</td>
</tr> 
{/foreach}
</table>
