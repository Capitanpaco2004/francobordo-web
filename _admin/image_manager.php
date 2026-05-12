<?php
/*
  $Id: define_mainpage.php,v 1.2 2002/04/06 00:00:00 mattice Exp $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2002 osCommerce

  Released under the GNU General Public License
*/

  require('includes/application_top.php');
  
  require(DIR_WS_LANGUAGES . $language . '/' . FILENAME_IMAGE_MANAGER);
 
$_GET['map'] = str_replace(".", "", $_GET['map']);
 
if(IsSet($_GET['map'])) {
	$dir  = DIR_FS_CATALOG_IMAGES . $_GET['map'];
	$http = DIR_WS_CATALOG_IMAGES . $_GET['map'];
} else {
	$dir  = DIR_FS_CATALOG_IMAGES;
	$http = DIR_WS_CATALOG_IMAGES;
}

	if($_SERVER['REQUEST_METHOD'] == "POST") {
		// hernoem
		// verwijder
		// uploadondernaam
		if($_POST['actie'] == "hernoem") {
			// naam
			$_POST['naam'] = str_replace("'", "", $_POST['naam']);
			if(rename($dir . stripslashes($_POST['oldname']), $dir . stripslashes($_POST['naam']))) {
				$melding = FILE_RENAME_SUCCESS;
			} else {
				$melding = FILE_RENAME_ERR;
			}				
		} elseif($_POST['actie'] == "verwijder") {
			if(unlink($dir . stripslashes($_POST['naam']))) {
				$melding = FILE_DELETE_SUCCESS;
			} else {
				$melding = FILE_DELETE_ERR;
			}
		} elseif($_POST['actie'] == "uploadondernaam") {
					$max = "3145728";
					$ext = "jpg JPG gif GIF png PNG jpeg JPEG";

				    if (!$_FILES['ufile']) {
				        $melding = FILE_NO_FILE; 
				    } else {  
				        // Bestands naam opvragen 
				        $bestand2 = explode("\\", $_FILES['ufile']['name']);  
				        $laatste = count($bestand2) - 1;  
				        $bestand2 = "$bestand2[$laatste]";   
				        
				        // Extensie van bestand opvragen 
				        $bestand3 = explode(".", $bestand2);  
				        $laatste = count($bestand3) - 1;  
				        $bestand3 = "$bestand3[$laatste]";   
				        $bestand3 = strtolower($bestand3);  
				        
				        // Toegestaande extensies opvragen 
				        
				        $ext = strtolower($ext);  
				        $ext = explode(" ", $ext);  
				        $aantal = count($ext);
						$bestandsnaam = time().".".$bestand3;
				        
				        for ($tel = 0;$tel < $aantal; $tel++) {  
				            if ($bestand3 == $ext[$tel]) {  
				                $extfout = FILE_EXTENSION_WRONG;  
				            }
				        }  
				    
				        if (!$extfout) {  
							$melding = FILE_EXTENSION_NOT_SUPPORTED;
				        } else {
							if(($_FILES['ufile']['error'] == "1") ||($_FILES['ufile']['size'] > $max)) {
								$melding = FILE_TOO_LARGE;
							} else {
				                // Opslaan van het bestand 
								unlink($dir.$dir.$_POST['filename']);
								move_uploaded_file($_FILES['ufile']['tmp_name'], $dir.$_POST['filename']);
								
								$melding = FILE_UPLOADED;
							}
				        }
				    }
		} elseif($_POST['actie'] == "upload") {
			function Upload($nummer) {
				global $dir;
				global $melding;
				$max = "3145728";
				$ext = "jpg JPG gif GIF png PNG jpeg JPEG";

				if ((empty($_FILES['uploadfile'.$nummer])) && (!$_FILES['uploadfile'.$nummer]['error'] == 0)) {
					$melding .= FILE_NO_FILE.'<br />'; 
				} else {
					// Bestands naam opvragen 
					$bestand2 = explode("\\", $_FILES['uploadfile'.$nummer]['name']);  
					$laatste = count($bestand2) - 1;  
					$bestand2 = "$bestand2[$laatste]";   
					
					// Extensie van bestand opvragen 
					$bestand3 = explode(".", $bestand2);  
					$laatste = count($bestand3) - 1;  
					$bestand3 = "$bestand3[$laatste]";   
					$bestand3 = strtolower($bestand3);  
					
					// Toegestaande extensies opvragen 
					
					$ext = strtolower($ext);  
					$ext = explode(" ", $ext);  
					$aantal = count($ext);
					$bestandsnaam = time().".".$bestand3;
					
					for ($tel = 0;$tel < $aantal; $tel++) {  
						if ($bestand3 == $ext[$tel]) {  
							$extfout = FILE_EXTENSION_WRONG;  
						}
					}  
				
					if (!$extfout) {
						$melding .= FILE_EXTENSION_NOT_SUPPORTED.'<br />';
					} else {
						if(($_FILES['uploadfile'.$nummer]['error'] == "1") ||($_FILES['uploadfile'.$nummer]['size'] > $max)) {
							$melding .= FILE_TOO_LARGE.'<br />';
						} else {
							// Opslaan van het bestand 
							$_FILES['uploadfile'.$nummer]['name'] = str_replace("'", "", $_FILES['uploadfile'.$nummer]['name']);
							move_uploaded_file($_FILES['uploadfile'.$nummer]['tmp_name'], $dir.$_FILES['uploadfile'.$nummer]['name']);
							
							$melding .= FILE_UPLOADED.'<br />';
						}
					}
				}
				return $melding;
			}
			for($upload = 1; $upload < 10; $upload++) {
				Upload($upload);
			}
		}
	}

$dirinhoud = scandir($dir);
$dirshow = "";
$notallowed = array(".", "..", "fonts");
$omhoog = explode("/", $_GET['map']);
$aantal = count($omhoog);
$weg = array_slice($omhoog, -2, -1);
$link = str_replace($weg[0]."/", "", $_GET['map']);
if($_GET['map'] != "") {
	$dirshow = "<a href='".FILENAME_IMAGE_MANAGER."?map=".$link."'>".tep_image(DIR_WS_IMAGES . 'icons/folder.png', FILE_DIR_ROOT, '16', '16')."&nbsp;".FILE_DIR_ROOT."</a><br />";
}

//$dirshow = $omhoog;
foreach($dirinhoud as $waarde => $inhoud) {
	if(!in_array($inhoud, $notallowed)) {
		if(is_dir($dir.$inhoud)) {
			$dirshow .= "<a href='".FILENAME_IMAGE_MANAGER."?map=".$_GET['map'].$inhoud."/' style='padding-left: 10px;'>".tep_image(DIR_WS_IMAGES . 'icons/folder.png', FILE_DIR_ROOT, '16', '16')."&nbsp;".$inhoud . "</a>&nbsp;|";
		}
	}
}  

$images = "";
$divsize = "";
$extensies = array("gif", "jpeg", "jpg", "png");

$uitlezen=scandir($dir);

$bestanden=array();
$c = 0;
//while(($file = readdir($uitlezen))!==false){
foreach($uitlezen as $waarde => $file) {
$arrayVar = explode(".", $file);
$extension = end($arrayVar);
if(in_array($extension, $extensies)&&is_file($dir.$file)) { $c++; }
	if(IsSet($_GET['start'])) { $nav = $_GET['start']; } else { $nav = "0"; }
	if(($c > $nav)&&($c < $nav+11)) {
		$link = $dir . $file;
		$hlink = $http . $file;
		$ext = explode(".", $file);
		if(in_array(end($ext), $extensies)&&is_file($link)) {
		$p++;
			if(file_exists($link)) {
			list($width, $height, $type, $attr) = getimagesize($link);
			if($height > SMALL_IMAGE_HEIGHT) { $hoogte = SMALL_IMAGE_HEIGHT; } else { $hoogte = $height; }
			if($width > SMALL_IMAGE_WIDTH) { $breed = SMALL_IMAGE_WIDTH; } else { $breed = $width; }
 
			if($width > SMALL_IMAGE_WIDTH && $height > SMALL_IMAGE_HEIGHT) { $box = ' rel="lightbox[roadtrip]"'; } else { $box = ' target="_BLANK"'; }

		$images .= '<tr class="fm">
						<td width="' . (SMALL_IMAGE_WIDTH + 10) . '" align="center" height="' . (SMALL_IMAGE_HEIGHT + 10) . '" class="fm">
							<a href="'.$hlink.'?tid='.time().'"'.$box.'><img src="'.$hlink.'" height="'.$hoogte.'" width="'.$breed.'" border="0"></a>
						</td>
						<td valign="top" class="fm" style="vertical-align: top;">
							<span onclick="Toggle(\'edit\', \''.addslashes($file).'\');"><strong>'.IMAGE_FILE_NAME.'</strong> '.$file.'</span><br />
							<strong>'.IMAGE_PICTURE_SIZE.'</strong> '.$width.' x '.$height.'<br />
							<strong>'.IMAGE_FILE_SIZE.'</strong> '.round(filesize($link)/1024,2).' Kb<br />
							<strong>'.IMAGE_LAST_UPDATE.'</strong> '.date("d-m-Y", filemtime($link)).'
						</td>
						<td valign="top" class="fm" style="vertical-align: top;">
							<div id="edit-'.addslashes($file).'1"><a href="#" onclick="Toggle(\'edit\', \''.addslashes($file).'\'); return false;">'.IMAGE_ACTION_RENAME.'</a></div>
							<div id="edit-'.addslashes($file).'2" style="display:none; border:1px dashed #cccccc; padding: 4px;">
								<form action="'.$_SERVER['REQUEST_URI'].'" method="post">
								<input type="text" name="naam" value="'.addslashes($file).'"><br />
								<input type="hidden" name="oldname" value="'.addslashes($file).'">
								<input type="hidden" name="actie" value="hernoem">
								'.tep_image_submit('button_edit.png', IMAGE_EDIT).'<a href="#" onclick="Toggle(\'ann\', \''.addslashes($file).'\'); return false;">' . tep_image_button("button_reset.png", IMAGE_RESET).'</a>
								</form>
							</div>
							<div id="del-'.addslashes($file).'1">
								<a href="#" onclick="Toggle(\'del\', \''.addslashes($file).'\'); return false;">'.IMAGE_ACTION_DELETE.'</a>
							</div>
							<div id="del-'.addslashes($file).'2" style="display: none; border:1px dashed #cccccc; padding: 4px;">
								<form action="'.$_SERVER['REQUEST_URI'].'" method="post">'.IMAGE_ACTION_DELETE_CONFIRM.'<br />
								<input type="hidden" name="actie" value="verwijder">
								<input type="hidden" name="naam" value="'.addslashes($file).'">
								'.tep_image_submit('button_delete.png', IMAGE_DELETE) . '<a href="#" onclick="Toggle(\'ann\', \''.addslashes($file).'\'); return false;">' . tep_image_button("button_reset.png", IMAGE_RESET).'</a>
								</form>
							</div>
							<div id="upload-'.addslashes($file).'1"><a href="#" onclick="Toggle(\'upload\', \''.addslashes($file).'\'); return false;">'.IMAGE_ACTION_UPLOAD.'</a></div>
							<div id="upload-'.addslashes($file).'2" style="display:none; border:1px dashed #cccccc; padding: 4px;">
									<form enctype="multipart/form-data" method="post" action="'.$_SERVER['REQUEST_URI'].'">'.IMAGE_ACTION_UPLOAD_HINT.'<br />
									<input type="file" name="ufile"><br />
									<input type="hidden" name="filename" value="'.addslashes($file).'">
									<input type="hidden" name="actie" value="uploadondernaam">
									'.tep_image_submit("button_upload.png", IMAGE_UPLOAD) . '<a href="#" onclick="Toggle(\'ann\', \''.addslashes($file).'\'); return false;">' . tep_image_button("button_reset.png", IMAGE_RESET).'</a>
									</form>
							</div>
							<div id="show'.addslashes($file).'"><a href="'.$hlink.'" target="_BLANK">'.IMAGE_ACTION_PREVIEW.'</a></div>
						</td>
					</tr>';
			}
		}
	}
}

if(!($_GET['start'] < 10)) {
	if($_GET['start']-10 == 0) {
		$begin = '<a href="'.FILENAME_IMAGE_MANAGER.'?map='.$_GET['map'].'">&lt;&lt;&nbsp;'.PAGE_PREVIOUS.'</a>';
	} else {
		$begin = '<a href="'.FILENAME_IMAGE_MANAGER.'?map='.$_GET['map'].'&start='.($_GET['start']-10).'">&lt;&lt;&nbsp;'.PAGE_PREVIOUS.'</a>';
	}
} elseif ($_GET['start'] < 10) {
	if($_GET['start'] != 0) {
		$begin = '<a href="'.FILENAME_IMAGE_MANAGER.'?map='.$_GET['map'].'">&lt;&lt;&nbsp;'.PAGE_PREVIOUS.'</a>';
	}
}
if($c > $_GET['start']+11) {
	if($p != "0" || $p != "") {
		$eind = '<a href="'.FILENAME_IMAGE_MANAGER.'?map='.$_GET['map'].'&start='.($_GET['start']+10).'">'.PAGE_NEXT.'&nbsp;&gt;&gt;</a>';
	}
}
if((IsSet($begin))&&(IsSet($eind))) {
	$navigatie = $begin . "&nbsp;|&nbsp;" . $eind;
} elseif(IsSet($begin)||IsSet($eind)) {
	if(empty($begin)) {
		$navigatie = tep_draw_separator("pixel_trans.png", '63', '18') . $eind;
	} elseif(empty($eind)) {
		$navigatie = $begin;
	}
}

if($p == "0"||$p == "") {
	$melding = EMPTY_DIRECTORY_TEXT;
}

$closediv = '<div style="position: absolute; right: 7px; top: 7px; cursor: pointer;" onclick="Toggle2(\'upload\'); return false;">'.tep_image(DIR_WS_IMAGES."cal_close_small.png", CLOSE_WINDOW_TEXT).'</div>';
?>

<?php require(THEME . 'html/header.php'); ?>

<script type="text/javascript">
function Toggle(actie, bestand) {
	if(actie == "edit") {
		document.getElementById("edit-" + bestand + "2").style.display = "block";
		document.getElementById("edit-" + bestand + "1").style.display = "none";
		document.getElementById("del-" + bestand + "2").style.display = "none";
		document.getElementById("del-" + bestand + "1").style.display = "block";
		document.getElementById("upload-" + bestand + "2").style.display = "none";
		document.getElementById("upload-" + bestand + "1").style.display = "block";
	} else if(actie == "del") {
		document.getElementById("edit-" + bestand + "2").style.display = "none";
		document.getElementById("edit-" + bestand + "1").style.display = "block";
		document.getElementById("del-" + bestand + "2").style.display = "block";
		document.getElementById("del-" + bestand + "1").style.display = "none";
		document.getElementById("upload-" + bestand + "2").style.display = "none";
		document.getElementById("upload-" + bestand + "1").style.display = "block";
	} else if(actie == "upload") {
		document.getElementById("edit-" + bestand + "2").style.display = "none";
		document.getElementById("edit-" + bestand + "1").style.display = "block";
		document.getElementById("del-" + bestand + "2").style.display = "none";
		document.getElementById("del-" + bestand + "1").style.display = "block";
		document.getElementById("upload-" + bestand + "2").style.display = "block";
		document.getElementById("upload-" + bestand + "1").style.display = "none";
	} else if(actie == "ann") {
		document.getElementById("edit-" + bestand + "2").style.display = "none";
		document.getElementById("del-" + bestand + "2").style.display = "none";
		document.getElementById("upload-" + bestand + "2").style.display = "none";
		document.getElementById("edit-" + bestand + "1").style.display = "block";
		document.getElementById("del-" + bestand + "1").style.display = "block";
		document.getElementById("upload-" + bestand + "1").style.display = "block";
	}
}

function Toggle2(nummer) {
	if(document.getElementById(nummer + "1").style.display == "none") {
		document.getElementById(nummer + "2").style.display = "none";
		document.getElementById(nummer + "1").style.display = "block";
	} else {
		document.getElementById(nummer + "1").style.display = "none";
		document.getElementById(nummer + "2").style.display = "block";
	}
}
</script>



<table border="0" width="100%" cellspacing="2" cellpadding="2">
  <tr>
    <td width="<?php echo BOX_WIDTH; ?>" valign="top"><table border="0" width="<?php echo BOX_WIDTH; ?>" cellspacing="1" cellpadding="1" class="columnLeft">
<!-- left_navigation //-->
<?php require(DIR_WS_INCLUDES . 'column_left.php'); ?>
<!-- left_navigation_eof //-->
    </table></td>
<!-- body_text //-->
    <td width="100%" valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="2">
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
			<tr><td>
			<div style="float: right;">
				<div id="upload1"><a href="#" onclick="Toggle2('upload'); return false;"><?php echo NEW_FILE_UPLOAD_TEXT; ?></a></div>
				<div id="upload2" style="display: none; position: absolute; height: 20px; width: 200px; right: 5.3%;">
					<div style="position: relative; padding: 4px; border:1px dashed #cccccc; background-color: #f6f8f8;">
						<form enctype="multipart/form-data" method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
						<?php echo IMAGE_ACTION_UPLOAD_HINT . $closediv; ?><br />
						<input type="file" name="uploadfile1"><br />
						<input type="file" name="uploadfile2"><br />
						<input type="file" name="uploadfile3"><br />
						<input type="file" name="uploadfile4"><br />
						<input type="file" name="uploadfile5"><br />
						<input type="file" name="uploadfile6"><br />
						<input type="file" name="uploadfile7"><br />
						<input type="file" name="uploadfile8"><br />
						<input type="file" name="uploadfile9"><br />
						<input type="file" name="uploadfile10"><br />
						<input type="hidden" name="actie" value="upload">
						<?php echo tep_image_submit("button_upload.png", IMAGE_UPLOAD) . '<a href="#" onclick="Toggle2(\'upload\'); return false;">' . tep_image_button("button_reset.png", IMAGE_RESET).'</a>'; ?>
						<!--<input type="submit" value="Upload!"><input type="reset" onclick="Toggle2('upload');" value="Annuleren">-->
						</form>
					</div>
				</div>
			</div>
						<div style="float: left;">
				<?php echo $dirshow; ?><br />
				<?php echo $navigatie; ?><?php if(IsSet($melding)) { echo "<br>".tep_image(DIR_WS_CATALOG_IMAGES."error.png", $melding, '10', '10')."&nbsp;".$melding; } ?></div>
			<?php if(!($p == "0"||$p == "")) {	?>
			<table width="100%" cellspacing="0" cellpadding="0" height="100%">
				<tr class="dataTableHeadingRow" height="15">
					<td width="<?php echo SMALL_IMAGE_WIDTH + 10;?>" align="center" class="dataTableHeadingContent"><?php echo TABLE_HEADER_IMAGE; ?></td>
					<td width="300" class="dataTableHeadingContent"><?php echo TABLE_HEADER_DATA; ?></td>
					<td class="dataTableHeadingContent"><?php echo TABLE_HEADER_ACTIONS; ?></td>
				</tr>
				<?php
						echo $images . "<br />";
				?>
			</table>
			<?php } ?>
			<div>
				<?php echo $navigatie; ?>
			</div>
			</td></tr>
        </table></td>
      </tr>
    </table></td>
<!-- body_text_eof //-->
  </tr>
</table>


<?php require(THEME . 'html/footer.php'); ?>

<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>