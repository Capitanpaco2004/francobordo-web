<tr>
        <td class="main"><b><?php  if($fromlogin!=1){echo CATEGORY_CREATE_ACCOUNT;}else{echo CATEGORY_PASSWORD;} ?></b></td>
      </tr>
      <tr>
        <td><table border="0" width="100%" cellspacing="1" cellpadding="2" class="infoBox">
          <tr class="infoBoxContents">
            <td><table border="0" cellspacing="2" cellpadding="2">
            <?php
if($fromlogin!=1){
?>
              <tr>
			   <td class="main"><?php echo ENTRY_CREATEACCOUNT. (tep_not_null(ENTRY_GENDER_TEXT) ? '<span class="inputRequirement">' . ENTRY_GENDER_TEXT . '</span>': ''); ?></td>
			   </tr>
			 <tr>

               <td class="main"><?php echo tep_draw_checkbox_field('createaccount', 'Y',$checked = true ,'id="notoggle"' ) . '&nbsp;&nbsp;' . YES_ACCOUNT ; ?></td>
             </tr>
			 <tr>
			    <td class="main">Por favor elija una contraseña de 5 o más caracteres</td>
             </tr></table><table>
<?php
  }
?>
			  <tr>
			    <td class="main"><?php echo ENTRY_PASSWORD; ?></td>
                <td class="main"><?php echo tep_draw_password_field('password') . '&nbsp;' . (tep_not_null(ENTRY_PASSWORD_TEXT) ? '<span class="inputRequirement">' . ENTRY_PASSWORD_TEXT . '</span>': ''); ?></td>
              </tr>
              <tr>
                <td class="main"><?php echo ENTRY_PASSWORD_CONFIRMATION; ?></td>
                <td class="main"><?php echo tep_draw_password_field('confirmation') . '&nbsp;' . (tep_not_null(ENTRY_PASSWORD_CONFIRMATION_TEXT) ? '<span class="inputRequirement">' . ENTRY_PASSWORD_CONFIRMATION_TEXT . '</span>': ''); ?></td>
              </tr>
            </table></td>
          </tr>
        </table></td>
      </tr>