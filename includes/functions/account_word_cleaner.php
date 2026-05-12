<?php
/*
  $Id: account_word_cleaner.php,v 1.4 11/28/2007 05:45:00 Sloppy Words Cleaner Exp $
  http://www.gokartsrus.com

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2003 osCommerce

  Released under the GNU General Public License
*/

 function loweraccent($str)
    {
		return $str;
	
        $low=array("Á" => "á", "É" => "é", "Í" => "í", "Ó" => "ó", "Ú" => "ú",
        	"Ä" => "ä","Ö" => "ö","Ü" => "ü","Ë" => "ë","´Ï" => "ï",
        	"À" => "à","È" => "è","Ì" => "ì","Ò" => "ò","Ù" => "ù",
        	"Â" => "â","Ê" => "ê","Î" => "î","Ô" => "ô","Û" => "û",
        	"Â" => "å","Ã" => "ã","Æ" => "æ","Ø" => "ø",
        	"Ç" => "ç","Ñ" => "ñ");
        return strtolower(strtr(mb_convert_encoding($str ?? '', 'ISO-8859-1', 'UTF-8'),$low));
    }

function RemoveShouting($str, $is_name=false) {
return $str;
    $str = loweraccent($str);
// exceptions to standard case conversion
   if ($is_name) {
       $all_uppercase = '';
       $all_lowercase = 'Or|And|De|La|Del|Las|Los|Du';
   } else {
// address abreviations and anything else
       $all_uppercase = 'Aly|Anx|Apt|Ave|Bch|Blvd|Bldg|Bsmt|Byu|Ch|Cors|Cswy|Cr|Crk|Crt|Cts|Cv|Cvs|Est|Ests|Expy|Frnt|Fl|Frks|Fwy|Gdn|Gtwy|Hbr|Hbrs|Hts|Hwy|Ii|Iii|Iv|Jct|Jcts|Lk|Lks|Ln|Ldg|Mnt|Mnr|Mnrs|Msn|Mtwy|Mtn|Mtns|Ne|Nw|Pkwy|Pl|Pln|Plns|Ph|Po|Pob|P.o.b.|P.O.b.|p.O.b.|p.o.B.|p.O.B.|Rm|Rr|Se|Skwy|Smt|Sw|Sta|Ste|Sq|Ter|Tpke|Trpk|Trlr|Trl|Trwy|Vl|Vlg|Vlgs|Vly|Vlys|Vi|Vii|Viii|Xi|Xing|Xrd';
       $all_lowercase = 'A|And|As|By|In|Of|Or|To|Y|E|I|En|De|La|Del|Las|Los|Du|Am|An|Der|Die|Das|Von';
   }
   $specials = 'ä|ö|ü|ß|á|é|ó|ú|í|ñ|à|è|ò|ù|ì|â|ê|ô|û|î|ë|ï|å|ã|æ|ø|ç|Ä|Ö|Ü|Á|É|Ó|Ú|Í|À|È|Ò|Ù|Ì|Â|Ê|Ô|Û|Î|Ñ|Ë|Ï|Å|Ã|Æ|Ø|Ç';
   $specials_first = 'ä|ö|ü|ß|á|é|ó|ú|í|ñ|à|è|ò|ù|ì|â|ê|ô|û|î|ë|ï|å|ã|æ|ø|ç';
   $prefixes = '';
   $suffixes = "'S";
   
// captialize all first letters
   $str = preg_replace('/\\b(\\w)/e', 'mb_strtoupper("$1")', mb_strtolower(trim($str)));

// captialize all first accent letters
   $str = preg_replace("/\\B($specials_first)\\b/e", 'mb_strtoupper("$1")', $str);

   if ($all_uppercase) {
// capitalize acronymns and initialisms i.e. PO
       $str = preg_replace("/\\b($all_uppercase)\\b/e", 'mb_strtoupper("$1")', $str);
   }
   if ($all_lowercase) {
// decapitalize short words i.e. and
       if ($is_name) {
// all occurences will be changed to lowercase
           $str = preg_replace("/\\b($all_lowercase)\\b/e", 'mb_strtolower("$1")', $str);
       } else {
// first and last word will not be changed to lower case (i.e. titles)
           $str = preg_replace("/(?<=\\W)($all_lowercase)(?=\\W)/e", 'mb_strtolower("$1")', $str);
       }
   }
   
    if ($specials) {
// decapitalize letter after special caracters i.e. 'ä,á,ñ'
       $str = preg_replace("/\\b($specials)(\\w)/e", '"$1".mb_strtolower("$2")', $str);
       $str = preg_replace("/\\B($specials)(\\w)/e", '"$1".mb_strtolower("$2")', $str);
   }

   if ($prefixes) {
// capitalize letter after certain name prefixes i.e. 'Mc'
       $str = preg_replace("/\\b($prefixes)(\\w)/e", '"$1".mb_strtoupper("$2")', $str);
   }

   if ($suffixes) {
// decapitalize certain word suffixes i.e. 's
       $str = preg_replace ("/(\\w)($suffixes)\\b/e", '"$1".mb_strtolower(stripslashes("$2"))', $str);
   }
   return mb_convert_encoding($str ?? '', 'UTF-8', 'ISO-8859-1');
}

// Last Name, edit to suite your needs
function RemoveShoutingLN($str, $is_name=false) {
return $str;
	$str = loweraccent($str);
   if ($is_name) {
       $all_uppercase = '';
       $all_lowercase = 'De La|De Las|Del|De Los|Y|E|I|Der|Van De|Van Der|Vit De|Von|Or|And|Y|En|De|La|Del|Du|Am|An|Der|Die|Das';
   } else {
       $all_uppercase = '';
       $all_lowercase = 'A|And|As|By|In|Of|Or|To';
   }
   $specials = 'ä|ö|ü|ß|á|é|ó|ú|í|ñ|à|è|ò|ù|ì|â|ê|ô|û|î|ë|ï|å|ã|æ|ø|ç|Ä|Ö|Ü|Á|É|Ó|Ú|Í|À|È|Ò|Ù|Ì|Â|Ê|Ô|Û|Î|Ñ|Ë|Ï|Å|Ã|Æ|Ø|Ç';
   $specials_first = 'ä|ö|ü|ß|á|é|ó|ú|í|ñ|à|è|ò|ù|ì|â|ê|ô|û|î|ë|ï|å|ã|æ|ø|ç';
   $prefixes = 'Mc|Mac';
   $suffixes = "'S";
   $str = preg_replace('/\\b(\\w)/e', 'mb_strtoupper("$1")', mb_strtolower(trim($str)));

   if ($all_uppercase) {
       $str = preg_replace("/\\b($all_uppercase)\\b/e", 'mb_strtoupper("$1")', $str);
   }
   if ($all_lowercase) {
       if ($is_name) {
           $str = preg_replace("/\\b($all_lowercase)\\b/e", 'mb_strtolower("$1")', $str);
       } else {
           $str = preg_replace("/(?<=\\W)($all_lowercase)(?=\\W)/e", 'mb_strtolower("$1")', $str);
       }
   }

// captialize all first accent letters
   $str = preg_replace("/\\B($specials_first)\\b/e", 'mb_strtoupper("$1")', $str);

    if ($specials) {
// decapitalize letter after special caracters i.e. 'ä,á,ñ'
       $str = preg_replace("/\\b($specials)(\\w)/e", '"$1".mb_strtolower("$2")', $str);
       $str = preg_replace("/\\B($specials)(\\w)/e", '"$1".mb_strtolower("$2")', $str);
   }
   
   if ($prefixes) {
       $str = preg_replace("/\\b($prefixes)(\\w)/e", '"$1".mb_strtoupper("$2")', $str);
   }
   if ($suffixes) {
       $str = preg_replace ("/(\\w)($suffixes)\\b/e", '"$1".mb_strtolower(stripslashes("$2"))', $str);
   }
   return $str;
}

// Company Name, edit to suite your needs
function RemoveShoutingCN($str, $is_name=false) {
return $str;
    $str = loweraccent($str);
   if ($is_name) {
       $all_uppercase = '';
       $all_lowercase = 'De La|De Las|Del|De Los|Der|Van De|Van Der|Vit De|Von|Or|And';
   } else {
       $all_uppercase = 'S.a.|S.l.|3m|Aa|Aaa|Ab|Abc|Abn|Aflac|Ag|Akso|Amd|Aol|Basf|Bb|Bbb|Bmw|Ca|Cbs|Cc|Ccc|Csx|Cvs|Dd|Ddd|Dec|Dhl|Ee|Eee|Ff|Fff|Ftd|Gg|Ggg|Ge|Gm|Gnc|Hh|Hhh|Hsbc|Ii|Iii|Ibm|Jj|Jjj|Jal|Jbl|Jvc|Kk|Kkk|Kfc|Klm|Lcl|Ll|Lll|Ltd|Lg|Mbna|Mips|Mm|Mmm|Mci|Mgm|Mvc|Ncr|Nn|Nnn|Nec|Oo|Ooo|Pmc|Pp|Ppp|Pg&e|Qq|Qqq|Qantas|Qvc|Rca|Reo|Rr|Rrr|Rsa|Sa|Saab|Sap|Sas|Scb|Sco|Sega|Skf|Snk|Ss|Sss|Stx|Tcl|Tcs|Tnt|Tt|Ttt|Twa|Uu|Uuu|Ua|Ubl|Ubs|Ul|Ups|Usx|Vv|Vvv|Vw|Ww|Www|Xx|Xxx|Yy|Yyy|Zz|Zzz';
       $all_lowercase = 'A|And|As|By|In|Of|Or|To|The';
   }
   $specials = 'ä|ö|ü|ß|á|é|ó|ú|í|ñ|à|è|ò|ù|ì|â|ê|ô|û|î|ë|ï|å|ã|æ|ø|ç|Ä|Ö|Ü|Á|É|Ó|Ú|Í|À|È|Ò|Ù|Ì|Â|Ê|Ô|Û|Î|Ñ|Ë|Ï|Å|Ã|Æ|Ø|Ç';
   $specials_first = 'ä|ö|ü|ß|á|é|ó|ú|í|ñ|à|è|ò|ù|ì|â|ê|ô|û|î|ë|ï|å|ã|æ|ø|ç';
   $prefixes = '';
   $suffixes = "'S";
   $str = preg_replace('/\\b(\\w)/e', 'mb_strtoupper("$1")', mb_strtolower(trim($str)));

   if ($all_uppercase) {
       $str = preg_replace("/\\b($all_uppercase)\\b/e", 'mb_strtoupper("$1")', $str);
   }
   if ($all_lowercase) {
       if ($is_name) {
           $str = preg_replace("/\\b($all_lowercase)\\b/e", 'mb_strtolower("$1")', $str);
       } else {
           $str = preg_replace("/(?<=\\W)($all_lowercase)(?=\\W)/e", 'mb_strtolower("$1")', $str);
       }
   }

// captialize all first accent letters
   $str = preg_replace("/\\B($specials_first)\\b/e", 'mb_strtoupper("$1")', $str);
   
    if ($specials) {
// decapitalize letter after special caracters i.e. 'ä,á,ñ'
       $str = preg_replace("/\\b($specials)(\\w)/e", '"$1".mb_strtolower("$2")', $str);
       $str = preg_replace("/\\B($specials)(\\w)/e", '"$1".mb_strtolower("$2")', $str);
   }
   
   if ($prefixes) {
       $str = preg_replace("/\\b($prefixes)(\\w)/e", '"$1".mb_strtoupper("$2")', $str);
   }
   if ($suffixes) {
       $str = preg_replace ("/(\\w)($suffixes)\\b/e", '"$1".mb_strtolower(stripslashes("$2"))', $str);
   }
   return $str;
}
   
?>