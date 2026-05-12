<?php
/*
  $Id: news.php,v 1.0 2002/08/30 13:52:20 kst Exp $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2002 osCommerce

  Released under the GNU General Public License
*/

   require('includes/application_top.php');
   include( THEME . 'html/header.php' );
?>

<table border="0" width="100%" cellspacing="2" cellpadding="2">
  <tr>
    <td width="100%" valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="2">
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td class="pageHeading"><?php echo HEADING_TITLE; ?></td>
          </tr>
        </table></td>
      </tr>
<?php

// Anlegen
  if ($_GET['action'] == 'newinsert') {
  echo '<!-- newinsert -->';
       if ( $langtext != '' ){ $weiter='Y';} else { $weiter='N'; }

        if ( ($bild != 'none') && ($bild != '') ) {
        $bild_location = DIR_WS_CATALOG_NEWS_IMAGES . $bild_name;
        if (file_exists($image_location)) @unlink($image_location);
        copy($bild, $bild_location);

     } else {
       $name_bild = '';

      }

  $sql_data_array = array('id_news' => '',
                          'ueberschrift' => $ueberschrift,
                          'kurztext' => $kurztext,
                          'autor' => $autor,
                          'von' => 'now()',
                          'bis' => 'now()',
                          'langtext' => $langtext,
                          'bild' => $bild_name,
                          'weiter' => $weiter);

    tep_db_perform(TABLE_NEWS, $sql_data_array);

?>
 <table width='100%' border='0' cellpadding='1' cellspacing='0' class='infoBox'>
<tr>
    <td class="main">

       <? echo TEXT_NEWS_INSERT_NEWS_SUCCEESS;?>     <br>    <br>
        <a href="<?echo FILENAME_NEWS .'">'. tep_image_button('button_back.png', IMAGE_BACK) ;?></a>

    </td>
  </tr>
</table>
<br>

<?
}
// Anlegen
  if ($_GET['action'] == 'new') {
  echo '<!-- new -->';
?>
<link rel="stylesheet" type="text/css" href="includes/javascript/spiffyCal/spiffyCal_v2_1.css">
<script language="JavaScript" src="includes/javascript/spiffyCal/spiffyCal_v2_1.js"></script>
<script language="javascript">
  var von = new ctlSpiffyCalendarBox("von", "new_news", "vondate","btnDate1","<?php echo $pInfo->von; ?>",scBTNMODE_CUSTOMBLUE);
</script>
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td class="pageHeading"><?php echo 'News eintragen'; ?></td>
          </tr>
        </table></td>
      </tr>
      <tr>
        <td><?php echo tep_draw_separator('pixel_trans.png', '1', '10'); ?></td>
      </tr>
      <tr><?php echo tep_draw_form('new_news', FILENAME_NEWS, 'cPath=' . $cPath . '&pID=' . $_GET['pID'] . '&action=newinsert', 'post', 'enctype="multipart/form-data"'); ?>
        <td>
            <table border="0" cellspacing="0" cellpadding="2">
              <tr>
                <td colspan="2">&nbsp;</td>
              </tr>
              <tr>
                <td class="main">
                  <?php echo TEXT_NEWS_INSERT_DATE; ?>
                  <br>
                  <small>(YYYY-MM-DD)</small></td>
                <td class="main">
                  <?php echo tep_draw_separator('pixel_trans.png', '24', '15') . '&nbsp;'; ?>
                  <script language="javascript">von.writeControl(); von.dateFormat="yyyy-MM-dd";</script>
                </td>
              </tr>
              <tr>
                <td colspan="2">
                  <?php echo tep_draw_separator('pixel_trans.png', '1', '10'); ?>
                </td>
              </tr>


              <tr>
                <td class="main">
                  <?php  echo TEXT_NEWS_INSERT_NEWS_HEADING; ?>
                </td>
                <td class="main">
                  <?php echo tep_draw_input_field('ueberschrift', $nInfo->ueberschrift, ''); ?>
                </td>
              </tr>

              <tr>
                <td colspan="2">
                  <?php echo tep_draw_separator('pixel_trans.png', '1', '10'); ?>
                </td>
              </tr>

              <tr>
                <td class="main" valign="top">
                  <?php echo TEXT_NEWS_INSERT_NEWS_SHORT; ?>
                </td>
                <td>
                  <table border="0" cellspacing="0" cellpadding="0">
                    <tr>
                      <td class="main" valign="top">

                        &nbsp;</td>
                      <td class="main">
                        <?php echo tep_draw_textarea_field('kurztext', 'soft', '70', '5', $nInfo->kurztext); ?>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
              <tr>
                <td class="main" valign="top">
                  <?php echo TEXT_NEWS_INSERT_NEWS_LONG; ?>
                </td>
                <td>
                  <table border="0" cellspacing="0" cellpadding="0">
                    <tr>
                      <td>&nbsp;</td>
                      <td width=100% align=middle>
                        <?php /* include('includes/htmlbar.php');*/?>
                      </td>
                    </tr>
                    <tr>
                      <td class="main" valign="top">

                        &nbsp;</td>
                      <td class="main">
                        <?php echo tep_draw_textarea_field('langtext', 'soft', '70', '15', $nInfo->langtext); ?>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>

              <tr>
                <td colspan="2">
                  <?php echo tep_draw_separator('pixel_trans.png', '1', '10'); ?>
                </td>
              </tr>
              <tr>
                <td class="main">
                  <?php echo TEXT_NEWS_INSERT_NEWS_AUTHOR; ?>
                </td>
                <td class="main">
                  <?php echo tep_draw_separator('pixel_trans.png', '24', '15') . '&nbsp;' . tep_draw_input_field('autor', $pInfo->autor); ?>
                </td>
              </tr>
              <tr>
                <td colspan="2">
                  <?php echo tep_draw_separator('pixel_trans.png', '1', '10'); ?>
                </td>
              </tr>
              <tr>
                <td class="main">
                  <?php echo TEXT_NEWS_INSERT_NEWS_PIC; ?>
                </td>
                <td class="main">
                  <?php echo tep_draw_separator('pixel_trans.png', '24', '15') . '&nbsp;' . tep_draw_file_field('bild') . '<br>' . tep_draw_separator('pixel_trans.png', '24', '15') . '&nbsp;' . $pInfo->bild ; ?>
                </td>
              </tr>

              <tr>
                <td colspan="2">&nbsp;</td>
              </tr>
            </table>
          </td>
      </tr>
      <tr>
        <td><?php echo  tep_image_submit('button_save.png', IMAGE_SAVE) . '&nbsp;&nbsp;<a href="' . tep_href_link(FILENAME_NEWS, 'page=' . $_GET['page'] . '&nID=' . $_GET['nID']) . '&action=">' . tep_image_button('button_cancel.png', IMAGE_CANCEL) . '</a>'; ?></td>
      </tr>
      <tr>
          <td class="main" align="right">&nbsp;</td>
      </form></tr>
<?
  }
// Anschauen
  elseif ($_GET['action'] == 'view') {
echo '<!-- preview -->';
$news_query = tep_db_query("select id_news, ueberschrift, von, autor, kurztext, langtext, bild from " . TABLE_NEWS . " where id_news = " . $nID . "");
$news = tep_db_fetch_array($news_query)  ;
?>
<table width='100%' border='0' cellpadding='1' cellspacing='0' class='infoBox'>
<tr>
    <td>
      <table cellspacing='0' cellpadding='4' width='100%' border='0'>
        <tr>
          <td width="100%" class="infoBoxHeading"> <b><? echo $news[ueberschrift] ?></b> <br>
            <? echo TEXT_NEWS_VIEW_HEADING . $news[autor] ?> </td>
        </tr>
      </table>
      <table width='100%' border='0' cellspacing='0' cellpadding='4' class='infoBoxContents'>
        <tr>
         <td class='smallText'>
<?
if ($news[bild] !='')
    {
     echo '    <img src="'. DIR_WS_CATALOG_NEWS_IMAGES_ADM .  $news[bild] . '" alt='. $news[ueberschrift] .' hspace=5  align="left" vspace="5" border="0">';
     }

echo            nl2br($news[langtext]) ;
?>
          <br></td>
        </tr>
      </table>
      <table cellspacing='0' cellpadding='4' width='100%' border='0'>
        <tr>
          <td class='infoBoxHeading'><? echo nl2br($news[kurztext]); ?></td></tr>
          <tr>
           <td>
            <a href="<?echo FILENAME_NEWS .'?page='. $page .'&nID='. $nID .'">'. tep_image_button('button_back.png', IMAGE_BACK) ;?></a>
      </table>
    </td>
  </tr>
</table>
<br>

<?
  }
// Löschen
  elseif ($_GET['action'] == 'delete') {
  echo '<!-- delete -->';
tep_db_query("delete from " . TABLE_NEWS . " where id_news = " . $nID . "");

?>
<table width='100%' border='0' cellpadding='1' cellspacing='0' class='infoBox'>
<tr>
    <td class="main">

       <? echo TEXT_NEWS_DELETE_NEWS_SUCCEESS;?>     <br>    <br>
        <a href="<?echo FILENAME_NEWS .'">'. tep_image_button('button_back.png', IMAGE_BACK) ;?></a>

    </td>
  </tr>
</table>
<br>
<?
  }
  else {
?>
<tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="2">
              <tr class="dataTableHeadingRow">
                <td class="dataTableHeadingContent">Subject</td>
                <td class="dataTableHeadingContent" align="right">Author</td>
                <td class="dataTableHeadingContent" align="center">Date</td>
                <td class="dataTableHeadingContent" align="right">Action</td>
              </tr>

<?php
    $news_query_raw = "select id_news, ueberschrift, von, autor from " . TABLE_NEWS . " order by von desc";
    $news_split = new splitPageResults($_GET['page'], MAX_DISPLAY_SEARCH_RESULTS, $news_query_raw, $news_query_numrows);
    $news_query = tep_db_query($news_query_raw);
    while ($news = tep_db_fetch_array($news_query)) {
      if (((!$_GET['nID']) || (@$_GET['nID'] == $news['id_news'])) && (!$nInfo) && (substr($_GET['action'], 0, 3) != 'new')) {
        $nInfo = new objectInfo($news);
      }

      if ( (is_object($nInfo)) && ($news['id_news'] == $nInfo->id_news) ) {
        echo '                  <tr class="dataTableRowSelected" onmouseover="this.style.cursor=\'hand\'" onclick="document.location.href=\'' . tep_href_link(FILENAME_NEWS, 'page=' . $_GET['page'] . '&nID=' . $nInfo->id_news . '&action=view') . '\'">' . "\n";
      } else {
        echo '                  <tr class="dataTableRow" onmouseover="this.className=\'dataTableRowOver\';this.style.cursor=\'hand\'" onmouseout="this.className=\'dataTableRow\'" onclick="document.location.href=\'' . tep_href_link(FILENAME_NEWS, 'page=' . $_GET['page'] . '&nID=' . $news['id_news']) . '\'">' . "\n";
      }
?>

                <td class="dataTableContent"><?php echo '<a href="' . tep_href_link(FILENAME_NEWS, 'page=' . $_GET['page'] . '&nID=' . $news['id_news'] . '&action=view') . '">'.$news['ueberschrift'].'</a>&nbsp;'; ?></td>
                <td class="dataTableContent" align="right"><?php echo $news['autor']; ?></td>
                <td class="dataTableContent" align="right"><?php echo $news['von']; ?></td>

                <td class="dataTableContent" align="right"><?php if ( (is_object($nInfo)) && ($news['id_news'] == $nInfo->id_news) ) { echo tep_image(DIR_WS_IMAGES . 'icon_arrow_right.png', ''); } else { echo '<a href="' . tep_href_link(FILENAME_NEWS, 'page=' . $_GET['page'] . '&nID=' . $news['id_news']) . '">' . tep_image(DIR_WS_IMAGES . 'icon_info.png', IMAGE_ICON_INFO) . '</a>'; } ?></td>
              </tr>
<?php
    }
?>
              <tr>
                <td colspan="6"><table border="0" width="100%" cellspacing="0" cellpadding="2">
                  <tr>
                    <td class="smallText" valign="top"><?php echo $news_split->display_count($news_query_numrows, MAX_DISPLAY_SEARCH_RESULTS, $_GET['page'], TEXT_DISPLAY_NUMBER_OF_NEWS); ?></td>
                    <td class="smallText" align="right"><?php echo $news_split->display_links($news_query_numrows, MAX_DISPLAY_SEARCH_RESULTS, MAX_DISPLAY_PAGE_LINKS, $_GET['page']); ?></td>
                  </tr>
                  <tr>
                    <td align="right" colspan="2"><?php echo '<a href="' . tep_href_link(FILENAME_NEWS, 'action=new') . '">' . tep_image_button('button_insert.png', IMAGE_INSERT) . '</a>'; ?></td>
                  </tr>
                </table></td>
              </tr>
            </table></td>

<?php

  $heading = array();
  $contents = array();
  switch ($_GET['action']) {


    default:
      if (is_object($nInfo)) {
        $heading[] = array('text' => '<b>Option\'s</b>');

        $contents[] = array('align' => 'center', 'text' => '<a href="' . tep_href_link(FILENAME_NEWS, 'page=' . $_GET['page'] . '&nID=' . $nInfo->id_news . '&action=view') . '">' . tep_image_button('button_edit.png', IMAGE_EDIT) . '</a><a href="' . tep_href_link(FILENAME_NEWS, 'page=' . $_GET['page'] . '&nID=' . $nInfo->id_news . '&action=delete') . '">' . tep_image_button('button_delete.png', IMAGE_DELETE) . '</a>');

       }
      break;
  }

  if ( (tep_not_null($heading)) && (tep_not_null($contents)) ) {
    echo '            <td width="25%" valign="top">' . "\n";

    $box = new box;
    echo $box->infoBox($heading, $contents);

    echo '            </td>' . "\n";
  }
?>
          </tr>
        </table></td>
      </tr>

    </table></td>
          </tr>
        </table></td>
      </tr>
<?php
}
?>
    </table></td>
</table></td>
<!-- body_text_eof //-->
  </tr>
</table>
<?php require(THEME . 'html/footer.php'); ?>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>