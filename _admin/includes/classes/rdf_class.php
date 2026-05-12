<?php
	/*####################
	# Used by contribution tracker contribution
	#	Class:		rdfFile
	#	Author:		Jason Williams
	#	License:	GPL
	#	Version:	0.1.5
	#
	#	Copyright (C) 2001-2003 Jason Williams
	#	jason@jasonandann.com
	#
	#	Usage:
	#		require ("rdf_class.php"); 
	#		$channel = new rdfFile(string filename)
	#		 (filename is a local path and filename)
	#		$channel->parse(boolean EncodingCheck); //the argument for this function turns encoding checking on
	#		 (EncodingCheck is a True/False value that turns encoding checking on or off accordingly)
	#		$channel->ReturnTable(boolean comments, string titlecolor, string maincolor[, string tablewidth]);
	#		 (comments should be either True or False
	#		  titlecolor and maincolor should be html hex-style color codes
	#		  tablewidth is optional and should be in pixles)
	#
	# This program is free software; you can redistribute it and/or
	# modify it under the terms of the GNU General Public License
	# as published by the Free Software Foundation; either version 2
	# of the License, or (at your option) any later version.
	#
	# This program is distributed in the hope that it will be useful,
	# but WITHOUT ANY WARRANTY; without even the implied warranty of
	# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	# GNU General Public License for more details.
	#
	# You should have received a copy of the GNU General Public License
	# along with this program; if not, write to the Free Software
	# Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
	#go through and search for blDescription and CurrentItemDescription, blDate
	####################*/
class rdfFile {
	
	var $xml_parser;
	var $FileName;
	var $CachePath;
	
	//Declare the booleen variables associated with the particular tags
	var $blChannel = False;
	var $blTitle = False;
	var $blDescription = False;
	var $blDate = False;
	var $blLink = False;
	var $blLanguage = False;
	var $blRating = False;
	var $blCopyright = False;
	var $blPubDate = False;
	var $blLastBuild = False;
	var $blDocs = False;
	var $blManagingEditor = False;
	var $blWebmaster = False;
	var $blImage = False;
	var $blURL = False;
	var $blWidth = False;
	var $blHeight = False;
	var $blItem = False;
	var $blTextInput = False;
	var $blName = False;
	var $blSkipHours = False;
	var $blHour = False;
	var $blSkipDays = False;
	var $blDay = False;
	
	//Declare the data storage variables
	var $Items = array();
	var $ItemsCounter;
	var $CurrentItemTitle;
	var $CurrentItemLink;
	var $CurrentItemDescription;
	var $CurrentItemDate;

	var $ChannelTitle;
	var $ChannelDescription;
	var $ChannelTitleLink;
	var $ChannelLanguage;
	var $ChannelRating;
	var $ChannelCopyright;
	var $ChannelPubDate;
	var $ChannelLastBuildDate;
	var $ChannelDocs;
	var $ChannelManagingEditor;
	var $ChannelWebmaster;
	
	var $ChannelImageTitle;
	var $ChannelImageUrl;
	var $ChannelImageLink;
	var $ChannelImageWidth;
	var $ChannelImageHeight;
	var $ChannelImageDescription;
	
	var $ChannelSkipHours = array();
	var $ChannelSkipDays = array();
	var $ChannelSkipHoursCount;
	var $ChannelSkipDaysCount;
	
	var $TextInputTitle;
	var $TextInputDescription;
	var $TextInputName;
	var $TextInputLink;
	
	//the constructor gets a local filename or url from the user
	function __construct ($LocalPath) {
		//initialize counters
		$this->ItemsCounter = 0;
		$this->ChannelSkipHoursCount = 0;
		$this->ChannelSkipDaysCount = 0;
		//get the xml parser ready
		$this->xml_parser = xml_parser_create();
		$this->FileName = $LocalPath;
	}
	function destroy() {
		xml_parser_free($this->xml_parser);
	}
	function WriteItem() {
		$this->Items[$this->ItemsCounter] = array("Title"=>$this->CurrentItemTitle, "Link"=>$this->CurrentItemLink, "Description"=>$this->CurrentItemDescription, "PubDate"=>$this->CurrentItemDate);
		$this->CurrentItemTitle="";
		$this->CurrentItemLink="";
		$this->CurrentItemDescription="";
		$this->CurrentItemDate="";
		$this->ItemsCounter++;
	}
	function StartElement($parser, $name, $attrs) {
			if ($name == "CHANNEL") {
				$this->blChannel = True;
			} elseif ($name == "TITLE") {
				$this->blTitle = True;
			} elseif ($name == "DESCRIPTION") {
				$this->blDescription = True;
			} elseif ($name == "PUBDATE") {
				$this->blDate = True;
			} elseif ($name == "LINK") {
				$this->blLink = True;
			} elseif ($name == "LANGUAGE") {
				$this->blLanguage = True;
			} elseif ($name == "RATING") {
				$this->blRating = True;
			} elseif ($name == "COPYRIGHT") {
				$this->blCopyright = True;
			} elseif ($name == "PUBDATE") {
				$this->blPubDate = True;
			} elseif ($name == "LASTBUILD") {
				$this->blLastBuild = True;
			} elseif ($name == "DOCS") {
				$this->blDocs = True;
			} elseif ($name == "MANAGINGEDITOR") {
				$this->blManagingEditor = True;
			} elseif ($name == "WEBMASTER") {
				$this->blWebmaster = True;
			} elseif ($name == "IMAGE") {
				$this->blImage = True;
			} elseif ($name == "URL") {
				$this->blURL = True;
			} elseif ($name == "WIDTH") {
				$this->blWidth = True;
			} elseif ($name == "HEIGHT") {
				$this->blHeight = True;
			} elseif ($name == "ITEM") {
				$this->blItem = True;
			} elseif ($name == "TEXTINPUT") {
				$this->blTextInput = True;
			} elseif ($name == "NAME") {
				$this->blName = True;
			} elseif ($name == "SKIPHOURS") {
				$this->blSkipHours = True;
			} elseif ($name == "HOUR") {
				$this->blHour = True;
			} elseif ($name == "SKIPDAYS") {
				$this->blSkipDays = True;
			} elseif ($name == "DAY") {
				$this->blDay = True;
			}
	}
	function EndElement($parser, $name) {
			if ($name == "CHANNEL") {
				$this->blChannel = False;
			} elseif ($name == "TITLE") {
				$this->blTitle = False;
			} elseif ($name == "DESCRIPTION") {
				$this->blDescription = False;
			} elseif ($name == "PUBDATE") {
				$this->blDate = False;
			} elseif ($name == "LINK") {
				$this->blLink = False;
			} elseif ($name == "LANGUAGE") {
				$this->blLanguage = False;
			} elseif ($name == "RATING") {
				$this->blRating = False;
			} elseif ($name == "COPYRIGHT") {
				$this->blCopyright = False;
			} elseif ($name == "PUBDATE") {
				$this->blPubDate = False;
			} elseif ($name == "LASTBUILD") {
				$this->blLastBuild = False;
			} elseif ($name == "DOCS") {
				$this->blDocs = False;
			} elseif ($name == "MANAGINGEDITOR") {
				$this->blManagingEditor = False;
			} elseif ($name == "WEBMASTER") {
				$this->blWebmaster = False;
			} elseif ($name == "IMAGE") {
				$this->blImage = False;
			} elseif ($name == "URL") {
				$this->blURL = False;
			} elseif ($name == "WIDTH") {
				$this->blWidth = False;
			} elseif ($name == "HEIGHT") {
				$this->blHeight = False;
			} elseif ($name == "ITEM") {
				$this->WriteItem();
				$this->blItem = False;
			} elseif ($name == "TEXTINPUT") {
				$this->blTextInput = False;
			} elseif ($name == "NAME") {
				$this->blName = False;
			} elseif ($name == "SKIPHOURS") {
				$this->blSkipHours = False;
			} elseif ($name == "HOUR") {
				$this->blHour = False;
			} elseif ($name == "SKIPDAYS") {
				$this->blSkipDays = False;
			} elseif ($name == "DAY") {
				$this->blDay = False;
			}
	}

	function DataHandler($parser, $data) {
	  $data = chop($data);
	  if ($data != "") {
	  	if ($this->blTitle) {
  			if ($this->blImage) {
  				$this->ChannelImageTitle .= $data;
  			} elseif ($this->blItem) {
  				$this->CurrentItemTitle .= $data;
  			} elseif ($this->blTextInput) {
  				$this->TextInputTitle .= $data;
  			} else {
  				$this->ChannelTitle .= $data;
  			}
	  	}
	  	
	  	if ($this->blLink) {
  			if ($this->blImage) {
  				$this->ChannelImageLink .= $data;
  			} elseif ($this->blItem) {
  				$this->CurrentItemLink .= $data;
  			} elseif ($this->blTextInput) {
  				$this->TextInputLink .= $data;
  			} else {
  				$this->ChannelTitleLink .= $data;
  			}
	  	}
	  	if ($this->blDescription) {
  			if ($this->blImage) {
  				$this->ChannelImageDescription .= $data;
  			} elseif ($this->blItem) {
  				$this->CurrentItemDescription .= $data;
  			} elseif ($this->blTextInput) {
  				$this->TextInputDescription .= $data;
  			} else {
  				$this->ChannelDescription .= $data;
  			}
	  	}
	  	if ($this-> blDate) {
  			if ($this->blImage) {
  				$this->ChannelImageDescription .= $data;
  			} elseif ($this->blItem) {
  				$this->CurrentItemDate .= $data;
  			} elseif ($this->blTextInput) {
  				$this->TextInputDescription .= $data;
  			} else {
  				$this->ChannelPubDate .= $data;
  			}
	  	}
		if ($this->blLanguage) {
			$this->ChannelLanguage = $data;
		} elseif ($this->blRating) {
			$this->ChannelRating = $data;
		} elseif ($this->blCopyright) {
			$this->ChannelCopyright = $data;
		} elseif ($this->blPubDate) {
			$this->ChannelPubDate = $data;
		} elseif ($this->blLastBuild) {
			$this->ChannelLastBuildDate = $data;
		} elseif ($this->blDocs) {
			$this->ChannelDocs = $data;
		} elseif ($this->blManagingEditor) {
			$this->ChannelManagingEditor = $data;
		} elseif ($this->blWebmaster) {
			$this->ChannelWebmaster = $data;
		}
		
		if ($this->blURL) {
			$this->ChannelImageUrl = $data;
		} elseif ($this->blWidth) {
			$this->ChannelImageWidth = $data;
		} elseif ($this->blHeight) {
			$this->ChannelImageHeight = $data;
		}
		
		if ($this->blName) {
			$this->TextInputName = $data;
		}
			
		if ($this->blSkipHours && $this->blHour) {
			$this->ChannelSkipHours[$this->ChannelSkipHoursCount] = $data;
			$this->ChannelSkipHoursCount++;
		}
		if ($this->blSkipDays && $this->blDay) {
			$this->ChannelSkipDays[$this->ChannelSkipDaysCount] = $data;
			$this->ChannelSkipDaysCount++;
		}
	  }
	}

	//function to check proper Latin-1 encoding
	function checkenc($data) {
		$tmp_pos = 0;
		$tmp_data = $data;
		$good_token = "&#"; //the good encoding we're looking for has this sequence after/including the crap out digit below
		$bad_token = "&"; //the digit that makes the parser crap out
		while ($tmp_pos <= strlen($tmp_data)) {
			$tmp_pos = strpos($tmp_data, $bad_token, $tmp_pos);
			if ($tmp_pos && substr($tmp_data, $tmp_pos, 2) != $good_token) { //if & is not followed by #, get rid of the &
				$tmp_data = substr_replace($tmp_data, substr($tmp_data, $tmp_pos+1, 1), $tmp_pos, 1);
				$tmp_pos++;
			} elseif ($tmp_pos && substr($tmp_data, $tmp_pos, 2) == $good_token) { //if & is followed by #, then we're okay... leave the data alone and move on
				$tmp_data = $tmp_data;
				$tmp_pos++;
			} else { //make sure the while loop will be broken
				$tmp_pos = strlen($data) + 100;
			}
		}
		return $tmp_data; //return the modified string
	}

	// Call to start the parsing of the file
	function parse($check_encoding) {
		xml_set_object($this->xml_parser, $this);
		xml_parser_set_option($this->xml_parser, XML_OPTION_CASE_FOLDING, True);
		xml_set_element_handler($this->xml_parser,"StartElement","EndElement");
		xml_set_character_data_handler($this->xml_parser,"DataHandler");
		if (!($fp = @fopen($this->FileName, "r"))) {
			die("could not open XML input");
		}
		if (!strpos($this->FileName,"://")) { rewind($fp); } //make sure this is not a url and is a local file (to avoid warning)
		//read the file into the data variable
		while(!feof($fp))
			$data = $data . fread($fp, 10000);
		if ($check_encoding) { $data = $this->checkenc($data); }
		$blTest = xml_parse($this->xml_parser, $data);
		if (!$blTest) {
			die(sprintf("XML error: %s at line %d",
			xml_error_string(xml_get_error_code($this->xml_parser)),
			xml_get_current_line_number($this->xml_parser)));
		}
		$this->destroy();
	}
	
	function ReturnTable($comments, $titlecolor, $maincolor, $tablewidth="", $borderwidth=0, $bordercolor="") {
		echo "<table width=\"" . $tablewidth . "\" bordercolor=\"" . $bordercolor . "\" border=\"" . $borderwidth . "\" cellspacing=\"0\" cellpadding=\"2\">\n";
		echo " <tr>\n";
		echo "  <td bgcolor=\"" . $titlecolor . "\"><a href=\"" . $this->ChannelTitleLink . "\">" . $this->ChannelTitle . "</a></td>\n";
		echo " </tr>\n";
		echo " <tr>\n";
		echo "  <td bgcolor=\"" . $maincolor . "\">\n";
		$tmpString = "   <a href=\"" . $this->ChannelImageLink . "\"><img src=\"" . $this->ChannelImageUrl . "\" border=\"0\"";
		if ($this->ChannelImageWidth != "" && $this->ChannelImageWidth != "") {
			$tmpString .= " width=\"" . $this->ChannelImageWidth . "\" height=\"" . $this->ChannelImageHeight . "\"";
		}
		$tmpString .= "></a>\n";
		if ($this->ChannelImageUrl != "") { echo $tmpString; }
		$countme=0;
		while ($countme < $this->ItemsCounter) {
			$thelink = $this->Items[$countme]["Link"];
			$thetitle = $this->Items[$countme]["Title"];
			$thedescription = $this->Items[$countme]["Description"];
			echo "   <li><a href=\"" . $thelink . "\">" . $thetitle . "</a></li><br>\n";
			if ($comments) {
				echo "   " . $thedescription;
			}
			$countme++;
		}
		if (! $this->TextInputLink == "") {
		 echo "   <form action=\"" . $this->TextInputLink . "\" method=\"post\"><strong>" . $this->TextInputTitle . "</strong><br><input type=\"text\" name=\"" . $this->TextInputName . "\"></form>\n";
		}
		echo "  </td>\n";
		echo " </tr>\n";
		echo "</table>\n";
	}
} //close the class bracket
?>
