<?php
  require(DIR_WS_LANGUAGES . $language . '/' . FILENAME_ORDERS);
  $info_box_heading = [];
  $info_box_contents = [];
  
  $info_box_contents[] = ['text' => tep_draw_input_field('keywords',
                                                              '',
                                                              'id="keywords" onKeyUp="loadXMLDoc(this.value);" autocomplete="off" style="width: 100%"')];
  $info_box_contents[] = ['text' => '<div style="display: block; margin-left: 0%; width:100%; float: left; border:solid 1px; background-color:#CCCCCC;" id="quicksearch">' . PRODUCTS_SEARCH_RESULTS . '</div>'];

  $box = new box;
  echo $box->infoBox($info_box_heading, $info_box_contents);
?>