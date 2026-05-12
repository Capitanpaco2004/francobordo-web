<?php
/*
$Id: export.php, version 1.0 Vendredi 6 avril 2007 Vaisonet Exp $

Contribution Export universel

http://www.vaisonet.com
Copyright © 2007 Vaisonet

Released under the GNU General Public License
*/

  require('includes/application_top.php');
  include( THEME . 'html/header.php' );
?>

<script language="Javascript">
function affiche()
{
var val;
val = "<?php echo HTTP_CATALOG_SERVER . DIR_WS_CATALOG . "export.php?format="; ?>";
val += document.ExU.format.value;
val += "&p=";
val += document.ExU.p.value;
val += "&language=";
val += document.ExU.language.value;
if (document.ExU.cache[0].checked)
  {
    val += "&cache=";
    val += document.ExU.cache[0].value;
    if (document.ExU.rep.checked) val += "&rep=1";
    val += "&fichier=";
    val += document.ExU.fichier.value;
  }
val += "&libre=";
val += document.ExU.libre.value;
document.ExU.url.value = val;
}
</script>

<table border="0" width="100%" cellspacing="2" cellpadding="2">
  <tr>
    <td width="100%" valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="0">
      <tr>
        <td width="100%"><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td class="pageHeading"><?php echo HEADING_TITLE; ?></td>
          </tr>
        </table></td>
      </tr>
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="2">
          <tr><form name="ExU">
            <td><table width="100%" border="0" cellpadding="0" cellspacing="2">
              <tr>
                <td><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
              </tr>
<?php
  function netoyage_html() {return false;}
  $comparateur = array();
  $comparateur[] = array('id' => "", 'text' => '------');
  $dir = opendir(DIR_FS_CATALOG_MODULES . "ExU/");
  while ($fichier = readdir($dir))
  {
    if(substr($fichier,-3) == "php")
    {
     include(DIR_FS_CATALOG_MODULES . "ExU/" . $fichier);
     foreach ($comp as $value)
       {
       $comparateur[] = array('id' => $fichier, 'text' => $value);
       }
    }
  }
  closedir($dir);
  
  $languages = tep_get_languages();
  $languages_array = array();
  $languages_selected = DEFAULT_LANGUAGE;
  for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
    $languages_array[] = array('id' => $languages[$i]['code'],
                               'text' => $languages[$i]['name']);
  }
   
?>
              <tr>
                <td class="main"><?php echo COMPARATEUR_SELECT; ?><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?><?php echo tep_draw_pull_down_menu('format', $comparateur, '', 'onchange ="affiche()"');?></td>
                </tr>
              <tr>
                <td><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
              </tr>
              <tr>
                <td class="main"><?php echo COMPARATEUR_LNG; ?><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?><?php echo tep_draw_pull_down_menu('language', $languages_array, '', 'onchange ="affiche()"');?></td>
                </tr>
              <tr>
                <td><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
              </tr>
              <tr>
                <td class="main"><?php echo COMPARATEUR_CODE; ?><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?><?php echo tep_draw_input_field('p', "Ciao0908", 'onblur ="affiche()"'); ?></td>                </tr>
                </tr>
              <tr>
                <td><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
              </tr>
              <tr>
                <td class="main"><?php echo COMPARATEUR_CACHE; ?><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?>
                  <input type="radio" name="cache" value="true" onChange ="affiche()" >
                  <?php echo COMPARATEUR_OUI; ?>
                  <input name="cache" type="radio" value="false" checked="checked" onChange ="affiche()" >
                  <?php echo COMPARATEUR_NON; ?> </td>
                </tr>
              <tr>
                <td><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
              </tr>
              <tr>
                <td valign="top" class="main"><?php echo COMPARATEUR_SECU; ?><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?>
                  <input name="rep" type="checkbox" id="rep" value="1" onBlur ="affiche()" >                </td>
                </tr>
              <tr>
                <td><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
              </tr>
              <tr>
                <td class="main"><?php echo COMPARATEUR_FICHIER; ?><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?><?php echo tep_draw_input_field('fichier', '', 'onchange ="affiche()"'); ?> <?php echo COMPARATEUR_OBLIG; ?></td>
                </tr>
              <tr>
                <td><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
              </tr>
              <tr>
                <td class="main"><?php echo COMPARATEUR_CHAMP; ?><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?><?php echo tep_draw_input_field('libre', '', 'onchange ="affiche()"'); ?></td>
                </tr>
                <td><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
              </tr>
              <tr>
                <td class="main"><?php echo COMPARATEUR_URL; ?><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?><?php echo tep_draw_input_field('url', '', 'size="125"'); ?></td>
                </tr>
            </table></td>
          </form></tr>
<!-- body_text_eof //-->
        </table></td>
      </tr>
    </table></td>
  </tr>
</table>
<?php require(THEME . 'html/footer.php'); ?>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
