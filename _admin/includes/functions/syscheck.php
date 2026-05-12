<?php
  function pc_process_dir($dir_name) { 
     $subdirectories = array();
     $files = array();
     if (is_dir($dir_name) && is_readable($dir_name)) {
        $d = dir($dir_name); 
        while (false != ($f = $d->read())) {
           if ( ("." == $f) || (".." == $f) ) continue;
           if (is_dir("$dir_name/$f")) {
             array_push($subdirectories, "$dir_name/$f");
           } else {
             array_push($files, "$dir_name/$f");
           }
        }
        $d->close(); 
        foreach ($subdirectories as $subdirectory) {
           $files = array_merge($files, pc_process_dir($subdirectory));
        }
     }
     return $files;
  }

  function get_dirs($dir_name) { 
     $subdirectories = array();
     if (is_dir($dir_name) && is_readable($dir_name)) {
        $d = dir($dir_name); 
        while (false != ($f = $d->read())) {
           if ( ("." == $f) || (".." == $f) ) continue;
           if (is_dir("$dir_name/$f")) {
             array_push($subdirectories, "$dir_name/$f");
           } 
        }
        $d->close(); 
        $newdirs = array();
        foreach ($subdirectories as $subdirectory) {
           $newdirs = array_merge($newdirs, get_dirs($subdirectory));
        }
        $subdirectories = array_merge($subdirectories, $newdirs); 
     }
     return $subdirectories;
  }
?>
