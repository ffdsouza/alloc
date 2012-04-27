{page::header()}
{page::toolbar()}
<table class="box">
  <tr>
    <th>Add Reminder - Select {$parentType}</th>
  </tr>
  <tr>
    <td>
      <form action="{$url_alloc_reminder}" method="post">
      {$parentType}:
      <select name="parentID">
        {$parentNameOptions}
      </select>
      <input type="hidden" name="parentType" value="{$parentType}">
      <input type="hidden" name="step" value="3">
      <input type="submit" name="submitParentName" value="Next">
      <input type="hidden" name="sessID" value="{$sessID}">
      </form>
    </td>
  </tr>
</table>
{page::footer()}
