{$table_box}
  <tr>
    <th>Comments</th>
    <th class="right">{get_expand_link("id_new_client_comment")}</th>
  </tr>
  <tr>
    <td colspan="2">
      <form action="{$url_alloc_comment}" method="post">
      <div class="{$class_new_client_comment}" id="id_new_client_comment">
      <table width="100%">
        <tr>
          <td>
            <input type="hidden" name="entity" value="client">
            <input type="hidden" name="entityID" value="{$client_clientID}">
            <textarea name="comment" cols="85" rows="10" wrap="virtual">{$comment}</textarea>&nbsp;
          </td>
          <td align="right" valign="bottom">
            {$comment_buttons}
          </td>
        </tr>
      </table>
      </div>
      </form>
    </td>
  </tr>
  <tr>
    <td colspan="2">
      {$commentsR}
    </td>
  </tr>
</table>


