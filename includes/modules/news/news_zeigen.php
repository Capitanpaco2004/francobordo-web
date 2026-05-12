<?php
/*
  $Id: news_zeigen.php,v 1.0 2002/05/04 17:00:00 dgw_ Exp $

  The Exchange Project - Community Made Shopping!
  http://www.theexchangeproject.org

  Modified by Konstanze Staud

  Released under the GNU General Public License
*/
$news_query=("SELECT * FROM " . TABLE_NEWS . " WHERE id_news=$id_news");

$erg=tep_db_query($news_query)
     or die (tep_db_error());

while($datensatz=tep_db_fetch_array($erg))
     {
      $id_neu=$datensatz[id_news];
      $ueberschrift=$datensatz[ueberschrift];
      $autor=$datensatz[autor];
      $kurztext=$datensatz[kurztext];
      $langtext=$datensatz[langtext];
      $von=$datensatz[von];
      $bis=$datensatz[bis];
      $mehr=$datensatz[weiter];
      $bild=$datensatz[bild];
      }


?>
