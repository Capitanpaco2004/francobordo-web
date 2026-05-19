<?php

### remove duplicate translation tags in files

if (!empty($argv[1])) {
  $file = $argv[1];
}
$exclude = array('pagetitles.php','common.php','movetocommon.php','removedups.php','..','.');

if (empty($file) || !is_file($file) || in_array($file,$exclude)) {
  print 'Usage: '.$argv[0].' [file]'."\n";
  exit;
}
include $file;
$filecontents = '<?php

$lan = array(
';
foreach ($lan as $key => $val) {
  $filecontents .= "\n"."'".str_replace("'","\'",$key)."' => '".str_replace("'","\'",$val)."',";
}
$filecontents .= '
);
?>';
$dir = dirname($file);
if (0 && !is_writable($dir) || (is_file($file) && !is_writable($file))) {
  $newfile = basename($file);
  $file = '/tmp/'.$newfile;
}
print "Writing $file\n";
file_put_contents($file,$filecontents);
