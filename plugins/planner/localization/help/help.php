<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"><title>Planer</title>
<head>
<?php
  $skin = trim($_GET['_skin']);
  $css = 'common';
  if($skin == 'larry'){
    $css = 'styles';
  }
  if(file_exists('../../../../plugins/compressor/cache/' . md5($skin . $css . '.css') . '.css')){
    $path = '../../../../plugins/compressor/cache';
    $css = md5($skin . $css . '.css');
    $skin = '';
  }
  else{
    $path = '../../../../skins/';
    if(file_exists('../../skins/' . $skin . '/' . $css . '.css')){
      $path = '../../skins/';
    }
    if(!is_dir($path . $skin)){
      $skin = 'default';
    }
  }
?>
<link rel="stylesheet" type="text/css" href="<?php echo $path . $skin ?>/<?php echo $css ?>.css" />
</head>
<body>
<?php
  $hl = trim($_GET['_hl']);
  if(file_exists("$hl.html"))
    echo file_get_contents("$hl.html");
  else
    echo file_get_contents("en_US.html");
?>
</body>
</html>