<?php

####################################################################################
#  COMPATIBILITY  ##################################################################
####################################################################################
#

  //! The original imagecopyresampled function is broken. This is a fixed version of it. 
  /*!
   *  \param dst_im Destination image
   *  \param src_im Source image
   *  \param dstX X coordinate of the top left corner of the destination area
   *  \param dstY Y coordinate of the top left corner of the destination area
   *  \param srcX X coordinate of the top left corner of the source area
   *  \param srcY Y coordinate of the top left corner of the source area
   *  \param dstW Width of the destination area
   *  \param dstH Height of the destination area
   *  \param srcW Width of the source area
   *  \param srcH Height of the source area
   */
  if (!function_exists('ImageCopyResampledFixed')) {
    function ImageCopyResampledFixed(&$dst_im, &$src_im, $dstX, $dstY, $srcX, $srcY, $dstW, $dstH, $srcW, $srcH) {
    // ImageCopyResampled does not take srcX and srcY into considaration, this is a bug. This fixes this. 
      $iSrcWidth = ImageSX($src_im);
      $iSrcHeight = ImageSY($src_im);
      $imgCropped = ImageCreateTrueColor($iSrcWidth-$srcX, $iSrcHeight-$srcY);
      ImageCopy($imgCropped, $src_im, 0, 0, $srcX, $srcY, $iSrcWidth-$srcX, $iSrcHeight-$srcY);
      ImageCopyResampled($dst_im, $imgCropped, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
      ImageDestroy($imgCropped);
    }
  }
  
#
#
####################################################################################
#  THE CLASS  ######################################################################
####################################################################################
#

  class TiM_image {
    var $src, $dirname, $basename, $filename, $extension, $type, $width, $height;

    function __construct($src) {
      if ($src) $this->set_source($src);
    }
    
    function set_source($file) {
      global $messageStack;
      
    // Set sourcefile
      $this->src = realpath($file);
      
    // Make sure source is an existing file
      if (!is_file($file)) {
        $messageStack->add('<strong>[TiM_image] set_source():</strong> Image file does not exist: '. realpath($file), 'error');
        return false;
      }
      
    // Make sure the file is readable
      if (!is_readable($file)) {
        $messageStack->add('<strong>[TiM_image] set_source():</strong> Image file not readable: '. realpath($file), 'error');
        return false;
      }
      
      return true;
    }
    
    function read() {
      global $messageStack;
      
    // Create image object
      switch($this->type()) {
        case "gif":
          $this->oImage = ImageCreateFromGIF($this->src);
          break;
        case "jpg":
          $this->oImage = ImageCreateFromJPEG($this->src);
          break;
        case "png":
          $this->oImage = ImageCreateFromPNG($this->src);
          break;
        case "bmp":
          $this->oImage = ImageCreateFromWBMP($this->src);
          break;
        case "xbm":
          $this->oImage = ImageCreateFromXBM($this->src);
          break;
        case "xpm":
          $this->oImage = ImageCreateFromXPM($this->src);
          break;
        default:
          return false;
      }
      
      return true;
    }
    
    function resample($width=1024, $height=1024, $method='FIT_ONLY_BIGGER') {
      global $messageStack;
      
    // Create image object if not made previously
      if (!$this->oImage) {
        $this->read();
      }
      
    // Calculate source's dimensional ratio
      $source_ratio = $this->width() / $this->height();
      
    // Return if missing dimensions
      if ($width == 0 && $height == 0) {
        $messageStack->add('<strong>[TiM_image] resample():</strong> Both width and height cannot be set to 0.', 'error');
        return false;
      }
      
    // Complete missing single sides
      if ($width == 0) {
        $width = round($height * $source_ratio);
      }
      if ($height == 0) {
        $height = round($width / $source_ratio);
      }
      
    // Calculate new size
      switch (strtoupper($method)) {
      
        case 'CROP':
          
          $destination_width = $width;
          $destination_height = $height;
          
          break;
        
        case 'CROP_ONLY_BIGGER':
          
          $destination_width = $width;
          $destination_height = $height;
          
          if ($this->width() < $destination_width) {
            $destination_width = $this->width();
          }
          if ($this->height() < $destination_height) {
            $destination_height = $this->height();
          }
          
          break;
        
        case 'STRETCH':
        
          $destination_width = ($width == 0) ? $this->width() : $width;
          $destination_height = ($height == 0) ? $this->height() : $height;
          
          break;
          
        case 'FIT':
          $destination_width = $width;
          $destination_height = round($destination_width / $source_ratio);
          
          if ($destination_height > $height) {
            $destination_height = $height;
            $destination_width = round($destination_height * $source_ratio);
          }
          
          break;
        
        
        case 'FIT_ONLY_BIGGER':
        default:
        
          $destination_width = $width;
          $destination_height = round($destination_width / $source_ratio);
          
          if ($destination_height > $height) {
            $destination_height = $height;
            $destination_width = round($destination_height * $source_ratio);
          }
          
          if ($destination_width > $destination_height) {
            if ($destination_width > $this->width()) {
              $destination_width = $this->width();
              $destination_height = round($destination_width / $source_ratio);
            }
          } else {
            if ($destination_height > $this->height()) {
              $destination_height = $this->height();
              $destination_width = round($destination_height * $source_ratio);
            }
          }
          
          break;
      }
      
    // Calculate destination dimensional ratio
      $destination_ratio = $destination_width / $destination_height;
      
    // Initialize output image
      $oResized = ImageCreateTrueColor($destination_width, $destination_height);
      
    if ($method == 'STRETCH') {
      ImageCopyResampled($oResized, $this->oImage, 0, 0, 0, 0, $destination_width, $destination_height, $this->width(), $this->height());
    } else {
    // Perform resample
      if (($this->width() / $destination_width) > ($this->height() / $destination_height)) {
        ImageCopyResampledFixed($oResized, $this->oImage, 0, 0, ($this->width() - $destination_width * $this->height() / $destination_height) / 2, 0, $destination_width, $destination_height, $this->height() * $destination_ratio, $this->height());
      } else {
        ImageCopyResampledFixed($oResized, $this->oImage, 0, 0, 0, ($this->height() - $destination_height * $this->width() / $destination_width) / 2, $destination_width, $destination_height, $this->width(), $this->width() / $destination_ratio);
      }
    }
    
    // Set new dimensions for object
      $this->width = $destination_width;
      $this->height = $destination_height;
      
    // Destroy old object
      ImageDestroy($this->oImage);
      
    // Set resized object as main object
      $this->oImage = $oResized;
      
      return true;
    }
    
    function watermark($wmFile, $xAlign='BOTTOM', $yAlign='RIGHT', $padding='5') {
      global $messageStack;
      
    // Create image object if not made previously
      if (!$this->oImage) {
        $this->read();
      }
      
    // Create image object
      $oWatermark = new TiM_image($wmFile);
      
    // Return false on no image
      if (!$oWatermark->type()) {
        $messageStack->add('<strong>[TiM_image] watermark():</strong> Watermark file is not a valid image: '. realpath($wmFile), 'error');
        if (is_object($this->oImage)) ImageDestroy($this->oImage);
        unset($oWatermark);
        return false;
      }
      
    // Read watermark
      $oWatermark->read();
      
    // Initialize alpha channel for PNG transparency
      ImageAlphaBlending($this->oImage, true);
      
    // Align watermark and set horizontal offset
      switch (strtoupper($xAlign)) {
        case "LEFT":
          $offset_x = $padding;
          break;
        case "CENTER":
          $offset_x = round(($this->width() - $oWatermark->width()) / 2);
          break;
        case "RIGHT":
        default:
          $offset_x = $this->width() - $oWatermark->width() - $padding;
          break;
      }
      
      // Align watermark and set vertical offset
      switch (strtoupper($yAlign)) {
        case "TOP":
          $offset_y = $padding;
          break;
        case "MIDDLE":
          $offset_y = round(($this->height() - $oWatermark->height()) / 2);
          break;
        case "BOTTOM":
        default:
          $offset_y = $this->height() - $oWatermark->height() - $padding;
          break;
      }
      
      // Create the watermarked image
        ImageCopy($this->oImage, $oWatermark->oImage, $offset_x, $offset_y, 0, 0, $oWatermark->width(), $oWatermark->height());
      
      // Free some RAM memory
        ImageDestroy($oWatermark->oImage);
        unset($oWatermark);
        
        return true;
    }
    
  // Dump image data to disk (Returns true if successful)
    function write($destination, $type=false, $quality=90) {
      global $messageStack;

    // Return false if target already exists
      if (is_file($destination)) {
        $messageStack->add('<strong>[TiM_image] write():</strong> Destination already exists: '. realpath($destination), 'error');
        if (is_object($this->oImage)) ImageDestroy($this->oImage);
        return false;
      }
      
    // Create image object if not made previously
      if (!$this->oImage) {
        $this->read();
      }

    // If not set to force type, get type from target filename.
      if (!$type) {
        $extension = pathinfo($destination, PATHINFO_EXTENSION);
        if (in_array(strtolower($extension), array('gif', 'jpg', 'png'))) {
          $type = strtolower($extension);
        } else {
          $messageStack->add('<strong>[TiM_image] write():</strong> Unknown output format.', 'error');
          ImageDestroy($this->oImage);
          return false;
        }
      }
      
    // Write the image to disk
      switch(strtolower($type)) {
        case "gif":
          ImageGIF($this->oImage, $destination);
          break;
        case "jpg":
          ImageJPEG($this->oImage, $destination, $quality);
          break;
        case "png":
          ImagePNG($this->oImage, $destination);
          break;
        default:
          ImageDestroy($this->oImage);
          return false;
      }
      
      ImageDestroy($this->oImage);
      return true;
    }
    
    function set_pathinfo() {
      global $messageStack;
      
    // Get image information
      list($info['dirname'], $info['basename'], $info['extension'], $info['filename']) = array_values(pathinfo($this->src));
      
      if (!$empty(info)) {
        $messageStack->add('<strong>[TiM_image] set_pathinfo():</strong> Unable to set path info for file: '. realpath($this->src), 'error');
        return false;
      }
      
      $this->dirname = $info['dirname'];
      $this->basename = $info['basename'];
      $this->extension = $info['extension'];
      $this->filename = $info['filename'];
      
      return true;
    }
    
    function set_imginfo() {
      global $messageStack;
      
      $info = getimagesize($this->src);
      
    // Return if error
      if (empty($info)) {
        $messageStack->add('<strong>[TiM_image] set_imginfo():</strong> Unable to detect image type and dimensions for file: '. realpath($this->src), 'error');
        return false;
      }
      
    // Set possible image types
      $imgtypes = array(
        1 => 'gif',
        2 => 'jpg',
        3 => 'png',
        4 => 'swf',
        5 => 'psd',
        6 => 'bmp',
        7 => 'tif',
        8 => 'tif',
        9 => 'jpc',
        10 => 'jp2',
        11 => 'jpx',
        12 => 'jb2',
        13 => 'swc',
        14 => 'iff',
        15 => 'bmp',
        16 => 'xbm'
        );
        
      $this->width = $info[0];
      $this->height = $info[1];
      $this->type = $imgtypes[$info[2]];
      
      return true;
    }
    
    function type() {
      
      if (!$this->type) {
        $this->set_imginfo();
      }
      
      return $this->type;
    }
    
    function filesize() {
      
      if (!$this->filesize) {
        $this->filesize = filesize($file);
      }
      
      return $this->filesize;
      
    }
    
    function width() {
      
      if (!$this->width) {
        $this->set_imginfo();
      }
      
      return $this->width;
    }
    
    function height() {
      
      if (!$this->height) {
        $this->set_imginfo();
      }
      
      return $this->height;
    }
    
    function dirname() {
      
      if (!$this->dirname) {
        $this->set_pathinfo();
      }
      
      return $this->dirname;
    }
    
    function basename() {
      
      if (!$this->basename) {
        $this->set_pathinfo();
      }
      
      return $this->basename;
    }
    
    function filename() {
      
      if (!$this->filename) {
        $this->set_pathinfo();
      }
      
      return $this->filename;
    }
    
    function extension() {
      
      if (!$this->extension) {
        $this->set_pathinfo();
      }
      
      return $this->extension;
    }
  }

?>