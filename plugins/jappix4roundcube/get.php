<?php
$file = $_GET['f'];
$type = explode('.', $file);
$type = strtolower($type[count($type) - 1]);
$file = dirname($_SERVER['SCRIPT_FILENAME']) . '/img/' . $file;
if(file_exists($file)){
  $filemtime = filemtime($file);
  $expires = 31536000;
  header('Cache-Control: maxage=' . $expires);
  header('Expires: '.gmdate('D, d M Y H:i:s', (time() + $expires)).' GMT');
  if($type == 'gif'){
    $ctype = 'image/gif';
  }
  else if($type == 'png'){
    $ctype = 'image/png';
  }
  else{
    $ctype = 'application/octet-stream';
  }
  header('content-Type: ' . $ctype);
  header('Content-Lenght: ' . filesize($file));
  header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $filemtime) . ' GMT');
  readfile($file);
}
else{
  header('HTTP/1.1 404 Not found');
}
exit;
?>