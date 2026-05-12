<?php
/*
  $Id: cache.php 1739 2007-12-20 00:52:16Z hpdl $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2003 osCommerce

  Released under the GNU General Public License
*/

  require('includes/application_top.php');

  $action = (isset($_GET['action']) ? $_GET['action'] : '');

  if (tep_not_null($action)) {
    if ($action == 'reset') {
      tep_reset_cache_block($_GET['block']);
    }

    tep_redirect(tep_href_link(FILENAME_CACHE));
  }

// check if the cache directory exists
  if (is_dir(DIR_FS_CACHE)) {
    if (!tep_is_writable(DIR_FS_CACHE)) $messageStack->add(ERROR_CACHE_DIRECTORY_NOT_WRITEABLE, 'error');
  } else {
    $messageStack->add(ERROR_CACHE_DIRECTORY_DOES_NOT_EXIST, 'error');
  }
?>

<?php require(THEME . 'html/header.php'); ?>

<script language="javascript" src="includes/general.js"></script>

    <table border="0" width="100%" cellspacing="0" cellpadding="2">
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td class="pageHeading"><?php echo HEADING_TITLE; ?></td>
          </tr>
        </table></td>
      </tr>
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="2">
              <tr class="dataTableHeadingRow">
                <td class="dataTableHeadingContent"><?php echo TABLE_HEADING_CACHE; ?></td>
                <td class="dataTableHeadingContent" align="right"><?php echo TABLE_HEADING_DATE_CREATED; ?></td>
                <td class="dataTableHeadingContent" align="right"><?php echo TABLE_HEADING_ACTION; ?>&nbsp;</td>
              </tr>
<?php
	if ($messageStack->size < 1) {
		$languages = tep_get_languages();

		$dir = dir(DIR_FS_CACHE);
		$cached_file_array = array();

		while ($cache_file = $dir->read()) {
			if (strstr($cache_file, '.cache')) {
				$cached_file_array[] = explode('.cache' , $cache_file);
			}
		}

		$dir->close();

		for ($i=0, $n=sizeof($cache_blocks); $i<$n; $i++) {
			$cache_mtime = TEXT_FILE_DOES_NOT_EXIST;
			$cache_block_file = preg_replace('/-language/i', '-' . $language, $cache_blocks[$i]['file']);

			foreach($cached_file_array as $key => $sub_cached_file_array) {
			// if the file name starts with the kind of file we are looking for, example: categories_box-english
				if (strpos($sub_cached_file_array[0], $cache_block_file, 0) !== false) {
					$name_of_file = $sub_cached_file_array[0] . '.cache' . $sub_cached_file_array[1];
					$cache_mtime = date('d/m/Y H:i:s', filemtime(DIR_FS_CACHE . $name_of_file));
					// if one file per kind exist, then we know if there is at least one cache file so break
					break;
				}
	    	}
?>
              <tr class="dataTableRow" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)">
                <td class="dataTableContent"><?php echo $cache_blocks[$i]['title']; ?></td>
                <td class="dataTableContent" align="right"><?php echo $cache_mtime; ?></td>
                <td class="dataTableContent" align="right"><?php echo '<a href="' . tep_href_link(FILENAME_CACHE, 'action=reset&block=' . $cache_blocks[$i]['code'], 'NONSSL') . '">' . tep_image(DIR_WS_IMAGES . 'icon_reset.gif', 'Reset', 13, 13) . '</a>'; ?>&nbsp;</td>
              </tr>
<?php
    }
  }
?>
              <tr>
                <td class="smallText" colspan="3"><?php echo TEXT_CACHE_DIRECTORY . ' ' . DIR_FS_CACHE; ?></td>
              </tr>
            </table></td>
          </tr>
        </table></td>
      </tr>
    </table>

<?php require(THEME . 'html/footer.php'); ?>

<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>