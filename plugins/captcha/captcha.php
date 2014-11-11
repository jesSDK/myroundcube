<?php

/**
 * captcha
 *
 * @version 3.2.12 - 09.11.2014
 * @author Roland 'rosali' Liebl
 * @website http://myroundcube.com
 *
 **/

#-- RoundCube Localization wrapper
function captcha_translate($str = ""){
  return rcube_label('captcha.' . str_replace(" ","",strtolower(trim($str))));
}

#-- config
define( 'EWIKI_FONT_DIR', dirname(__FILE__));  // which fonts to use
define( 'CAPTCHA_INVERSE', 0);                  // white or black(=1)
define( 'CAPTCHA_TIMEOUT', 5000);               // in seconds (=max 4 hours)
define( 'CAPTCHA_MAXSIZE', 4500);               // preferred image size

/* static - (you could instantiate it, but...) */
class captcha extends rcube_plugin {

  public $task = "login|logout";
  public $noframe = true;
  
    /* unified plugin properties */
  static private $plugin = 'captcha';
  static private $author = 'myroundcube@mail4us.net';
  static private $authors_comments = '<a href="http://myroundcube.com/myroundcube-plugins/captcha-plugin" target="_blank">Documentation</a>';
  static private $version = '3.2.12';
  static private $date = '09-11-2014';
  static private $licence = 'GPL';
  static private $requirements = array(
    'Roundcube' => '1.0',
    'PHP' => '5.3'
  );
  static private $prefs = null;
  static private $config_dist = 'config.inc.php.dist';

  function init()
  {
    $rcmail = rcmail::get_instance();
    if(!in_array('global_config', $rcmail->config->get('plugins'))){
      $this->load_config();
    }
    $this->add_texts('localization/', true);
    $this->add_hook('template_object_captcha', array($this, 'show_captcha'));
    $this->add_hook('template_object_loginform', array($this, 'insert_captcha'));
    $this->add_hook('template_object_insertcaptcha', array($this, 'insert_captcha'));
    $this->add_hook('login_after', array($this, 'check_captcha'));
    $this->add_hook('render_page', array($this, 'check_captcha'));
    $this->add_hook('startup', array($this, 'reload_captcha'));
    $this->add_hook('startup', array($this, 'validate_captcha'));
    $this->add_hook('startup', array($this, 'download_captcha'));
    $this->include_script('captcha.js');
  }

  static public function about($keys = false){
    $requirements = self::$requirements;
    foreach(array('required_', 'recommended_') as $prefix){
      if(is_array($requirements[$prefix.'plugins'])){
        foreach($requirements[$prefix.'plugins'] as $plugin => $method){
          if(class_exists($plugin) && method_exists($plugin, 'about')){
            /* PHP 5.2.x workaround for $plugin::about() */
            $class = new $plugin(false);
            $requirements[$prefix.'plugins'][$plugin] = array(
              'method' => $method,
              'plugin' => $class->about($keys),
            );
          }
          else{
            $requirements[$prefix.'plugins'][$plugin] = array(
              'method' => $method,
              'plugin' => $plugin,
            );
          }
        }
      }
    }
    $config = array();
    if(is_string(self::$config_dist)){
      if(is_file($file = INSTALL_PATH . 'plugins/' . self::$plugin . '/' . self::$config_dist))
        include $file;
      else
        write_log('errors', self::$plugin . ': ' . self::$config_dist . ' is missing!');
    }
    $ret = array(
      'plugin' => self::$plugin,
      'version' => self::$version,
      'date' => self::$date,
      'author' => self::$author,
      'comments' => self::$authors_comments,
      'licence' => self::$licence,
      'requirements' => $requirements,
    );
    if(is_array(self::$prefs))
      $ret['config'] = array_merge($config, array_flip(self::$prefs));
    else
      $ret['config'] = $config;
    if(is_array($keys)){
      $return = array('plugin' => self::$plugin);
      foreach($keys as $key){
        $return[$key] = $ret[$key];
      }
      return $return;
    }
    else{
      return $ret;
    }
  }
  
  function download_captcha(){
    $rcmail = rcmail::get_instance();
    if($rcmail->action == 'plugin.captcha'){
      $id = get_input_value('_id', RCUBE_INPUT_GET);
      $data = $_SESSION['captcha'][$id];
      header('Content-Length: '.strlen($data));
      header("Content-type: image/jpg");
      echo $data;
      exit;
    }
  }
  
  function reload_captcha(){
    $rcmail = rcmail::get_instance();
    if($rcmail->action == 'plugin.captcha_reload'){
      $ret = array();
      
      #-- prepare image text
      $pw = captcha::mkpass ();
      $hash = captcha::hash($pw);
      $alt = str_replace('"', "'", htmlentities(captcha::textual_riddle($pw),ENT_NOQUOTES,'UTF-8'));

      #-- image
      $img = captcha::image($pw, 175, 60, CAPTCHA_INVERSE, CAPTCHA_MAXSIZE);
      $img_fn = './?_action=plugin.captcha&_id=' . captcha::store_image($img);
      echo $hash . '|' . $alt . '|' . $img_fn . '|' . $pw;
      exit;
    }
  }
  
  function validate_captcha(){
    $rcmail = rcmail::get_instance();
    if($rcmail->action == 'plugin.captcha_validate'){
      echo captcha::check();
      exit;
    }
  }
  
  function insert_captcha($p){
    $rcmail = rcmail::get_instance();
    $plugins = $rcmail->config->get('plugins',array());
    $fplugins = array_flip($plugins);
    if(isset($fplugins['lang_sel'])){
      foreach($plugins as $key){
        if($key == 'captcha'){
          die('ERROR: lang_sel plugin must be registered before captcha plugin.');
        }
        if($key == 'lang_sel'){
          break;
        }
      }
    }
    if($rcmail->config->get('captcha_auth',false)){
      $i = $this->show_captcha($p);
      $p['content'] .= $i['content'];
    }
    return $p;  
  }
  
  function show_captcha($p){
  
    $rcmail = rcmail::get_instance();
    $skin = $rcmail->config->get('skin','classic');
    
    if(!file_exists("plugins/captcha/skins/$skin/captcha.css"))
      $skin = "classic";

    $this->include_stylesheet('skins/' . $skin . '/captcha.css'); 
    
    $p['content'] = captcha::form($rcmail->config->get('captcha_auth',false));
    return $p;
    
  }

  function get_demo($string){

    $temparr = explode("@",$string);
    return preg_replace ('/[0-9 ]/i', '', $temparr[0]) . "@" . $temparr[count($temparr)-1];

  }
  
  function check_captcha($args){
   
    $rcmail = rcmail::get_instance();
        
    // never check on demo accounts
    if($this->get_demo($_SESSION['username']) == sprintf($rcmail->config->get('demo_user_account'),"")){
      return $args;
    }
    
    // never check on captcha page
    if(
      isset($args['template']) &&
      ($args['template'] == 'captcha.captcha' || $rcmail->task == 'logout')
      ){
           
      return $args;
      
    }

    $auth = $rcmail->config->get('captcha_auth',false);
    
    // check authenticated state
    if(!isset($_SESSION['user_id'])){
      $page = $rcmail->config->get('captcha_page',array("contactus.contactus","pwtools.recpw","register.register"));
      $page = array_flip($page);
      if(!isset($page[$args['template']])){
         return $args;
      }
    }

    if(isset($_SESSION['user_id']) && $auth === false){

      return $args;

    }

    // perform the check
    if(captcha::check()){
      return $args;
    }

    // check failed
    if(isset($_POST['captcha_input']))
      $rcmail->output->show_message('captcha.captchafailed','error');
    else
      $rcmail->output->show_message('captcha.captchainput');
    $rcmail->output->set_env('keyboard_shortcuts','disabled');
    if($rcmail->config->get('captcha_auth',false) && $rcmail->action == 'login'){
      $rcmail->kill_session();
      $rcmail->output->send('login');
    }
    else{
      $rcmail->output->send('captcha.captcha');
    }
    exit;
  
  }

   /* gets parameter from $_REQUEST[] array (POST vars) and so can
      verify input, @returns boolean
   */
   function check () {
      if(isset($_SESSION['captcha_solved'])){
         return(true);
      }
      if (($hash = $_REQUEST['captcha_hash'])
      and ($pw = trim($_REQUEST['captcha_input']))) {
         $r = (captcha::hash($pw)==$hash) || (captcha::hash($pw,-1)==$hash);
         if ($r) {
            $_SESSION['captcha_solved'] = true;
         }
         return($r);
      }
   }


   /* yields <input> fields html string (no complete form), with captcha
      image already embedded as data:-URI
   */
   function form ($force = false) {

      #-- stop if user already verified
      if(isset($_SESSION['captcha_solved']) && !$force){
        if(isset($_GET['_task']) && $_GET['_task'] != 'logout' && $_GET['_task'] != 'login' ){
          return "";
        }
      }
      
      $more = captcha_translate ( 'Enter security code' );
      #-- prepare image text
      $pw = captcha::mkpass ();
      $hash = captcha::hash($pw);
      $alt = str_replace('"', "'" , htmlentities(captcha::textual_riddle($pw),ENT_NOQUOTES,'UTF-8'));

      #-- image
      $img = captcha::image($pw, 175, 60, CAPTCHA_INVERSE, CAPTCHA_MAXSIZE);
      $img_fn = './?_action=plugin.captcha&_id=' . captcha::store_image($img);

      #-- emit html form
      $html = '
        <script type="text/javascript">
        function hide_riddle(){
          $("#riddle").hide();
        }
        function show_riddle(){';
        $rcmail = rcmail::get_instance();
        if($rcmail->config->get('captcha_onclick','riddle') == 'entity_encoded'){
          $html .= '
          rcmail.display_message(phrase,"notice");';
        }
        else{
          $html .= '
            $("#riddle").show();';
        }
        $title = $this->gettext('showriddle');
        if($rcmail->config->get('captcha_onclick','riddle') == 'entity_encoded'){
          $title = $this->gettext('showphrase');
        }
        $html .= '
        }
        </script>
        <div id="captcha">
          <table border="0" summary="captcha input" cellspacing="0" cellpadding="0"><tr>
            <td class="title" colspan="2"><small>'.$more.'&nbsp;</small></td></tr><tr>
            <td nowrap>
              <img onclick="show_riddle()" name="captcha_image" id="captcha_image" src="' .$img_fn.'" alt="' .$alt. '" title="' . $title . '"/>
              <div style="display:inline;margin-left:-20px;"><img onclick="hide_riddle();captcha_ajax()" src="plugins/captcha/' . $this->local_skin_path() . '/refresh.png" title="' . $this->gettext('captcha.reload') . '" /></div>
            </td>
            <td nowrap>
              <input name="captcha_input" type="text" size="7" maxlength="16" autocomplete="off" />
              <input id="captcha_hash" name="captcha_hash" type="hidden" value="'.$hash. '" />
            </td></tr>
          </table>
          <div id="riddle" style="display:none;background-color:#ffffff;color:#000000;border: 1px solid #666;padding:5px 5px 5px 5px;"><div onclick="hide_riddle()" title="' . $this->gettext('hideriddle') . '" style="float:right"><a style="text-decoration:none;" href="#"><small>[x]</small></a></div><pre id="pre_riddle">' . $alt . '</pre></div>
        </div>
      ';
            
      return($html);
   }


   /* generates alternative (non-graphic), human-understandable
      representation of the passphrase
   */
   function textual_riddle($phrase) {
      $rcmail = rcmail::get_instance();
      if($rcmail->config->get('captcha_onclick','riddle') == 'entity_encoded'){
   
        $captcha = "";
        for($i=0; $i<strlen($phrase);$i++){
          $captcha .= "&#" . ord(strtoupper(substr($phrase,$i,1))) . ";";
        }
        $rcmail->output->add_script("var phrase=\"" . strtoupper($captcha) . "\";");
      }

      $symbols0 = '"\'-/_:';
      $symbols1 = array ("\n,",
        "\n;",
        ";",
        "\n&",
        "\n-",
        ",",
        ",",
        "\n" . captcha_translate("andthen"),
        "\n" . captcha_translate("followedby"),
        "\n" . captcha_translate("and"),
        "\n" . captcha_translate("andnota") . "\n\"".chr(65+rand(0,26))."\",\n" . captcha_translate("but"));
      $s = captcha_translate("guess") . "\n--\n";
      for ($p=0; $p<strlen($phrase); $p++) {
         $c = $phrase[$p];
         $add = "";
         #-- asis
         if (!rand(0,3)) {
            $i = $symbols0[rand(0,strlen($symbols0)-1)];
            $add = "$i$c$i";
         }
         #-- letter
         elseif ($c >= 'A') {
            $type = ($c >= 'a' ? captcha_translate("small") . " " : "");
            do {
               $n = rand(-3,3);
               $c2 = chr((ord($c) & 0x5F) + $n);
            }
            while (($c2 < 'A') || ($c2 > 'Z'));
            if ($n < 0) {
               $n = -$n;
               $add .= "$type'$c2' +$n " . captcha_translate("letters");
            }
            else {
               $add .= "$n " . captcha_translate("charsbefore") . " $type$c2";
            }
         }
         #-- number
         else {
            $add = "???";
            $n = (int) $c;
            do {
               do { $x = rand(1, 10); } while (!$x);
               $op = rand(0,11);
               if ($op <= 2) {
                  $add = "($add * $x)"; $n *= $x;
               }
               elseif ($op == 3) {
                  $x = 2 * rand(1,2);
                  $add = "($add / $x)"; $n /= $x;
               }
               elseif ( ! empty ( $sel ) && $sel % 2) {
                  $add = "($add + $x)"; $n += $x;
               }
               else {
                  $add = "($add - $x)"; $n -= $x;
               }
            }
            while (rand(0,1));
            $add .= " = $n";
         }
         $s .= "$add";
         $s .= $symbols1[rand(0,count($symbols1)-1)] . "\n";
      }
      return($s);
   }


   /* returns jpeg file stream with unscannable letters encoded
      in front of colorful disturbing background
   */
   function image($phrase, $width=200, $height=60, $inverse=0, $maxsize=0xFFFFF) {

      #-- initialize in-memory image with gd library
      srand(microtime ()*21017);
      $img = imagecreatetruecolor($width, $height);
      $R = $inverse ? 0xFF : 0x00;
      imagefilledrectangle($img, 0,0, $width,$height, captcha::random_color($img, 222^$R, 255^$R));
      $c1 = rand(150^$R, 185^$R);
      $c2 = rand(195^$R, 230^$R);

      #-- configuration
      $fonts = array (
        // "COLLEGE.ttf",
      );
      $fonts += glob(EWIKI_FONT_DIR."/*.ttf");

      #-- encolour bg
      $wd = 20;
      $x = 0;
      $y = $height;
      while ($x < $width) {
         imagefilledrectangle($img, $x, 0, $x+=$wd, $height, captcha::random_color($img, 222^$R, 255^$R));
         $wd += max(10, rand(0, 20) - 10);
      }

      #-- make interesting background I, lines
      $wd = 4;
      $w1 = 0;
      $w2 = 0;
      for ($x=0; $x<$width; $x+=(int)$wd) {
         if ($x < $width) {   // verical
            imageline($img, $x+$w1, 0, $x+$w2, $height-1, captcha::random_color($img,$c1,$c2));
         }
         if ($x < $height) {  // horizontally ("y")
            imageline($img, 0, $x-$w2, $width-1, $x-$w1, captcha::random_color($img,$c1,$c2));
         }
         $wd += rand(0,8) - 4;
         if ($wd < 1) { $wd = 2; }
         $w1 += rand(0,8) - 4;
         $w2 += rand(0,8) - 4;
         if (($x > $height) && ($y > $height)) {
            break;
         }
      }

      #-- more disturbing II, random letters
      $limit = rand(30,90);
      for ($n=0; $n<$limit; $n++) {
         $letter = "";
         do {
            $letter .= chr(rand(31,125)); // random symbol
         } while (rand(0,1));
         $size = rand(5, $height/2);
         $half = (int) ($size / 2);
         $x = rand(-$half, $width+$half);
         $y = rand(+$half, $height);
         $rotation = rand(60, 300);
         $c1  = captcha::random_color($img, 130^$R, 240^$R);
         $font = $fonts[rand(0, count($fonts)-1)];
         imagettftext($img, $size, $rotation, $x, $y, $c1, $font, $letter);
      }

      #-- add the real text to it
      $len = strlen($phrase);
      $w1 = 10;
      $w2 = $width / ($len+1);
      for ($p=0; $p<$len; $p++) {
         $letter = $phrase[$p];
         $size = rand(18, $height/2.2);
         $half = (int) $size / 2;
         $rotation = rand(-33, 33);
         $y = rand($size+3, $height-3);
         $x = $w1 + $w2*$p;
         $w1 += rand(-$width/90, $width/40);  // @BUG: last char could be +30 pixel outside of image
         $font = $fonts[rand(0, count($fonts)-1)];
         $r=rand(30,99); $g=rand(30,99); $b=rand(30,99); // two colors for shadow
         $c1  = imagecolorallocate($img, $r*1^$R, $g*1^$R, $b*1^$R);
         $c2  = imagecolorallocate($img, $r*2^$R, $g*2^$R, $b*2^$R);
         imagettftext($img, $size, $rotation, $x+1, $y, $c2, $font, $letter);
         imagettftext($img, $size, $rotation, $x, $y-1, $c1, $font, $letter);
      }

      #-- let JFIF stream be generated
      $quality = 67;
      $s = array ();
      do {
         ob_start (); ob_implicit_flush(0);
         imagejpeg($img, NULL, (int)$quality);
         $jpeg = ob_get_contents (); ob_end_clean ();
         $size = strlen($jpeg);
         $s_debug[] = ((int)($quality*10)/10) . "%=$size";
         $quality = $quality * ($maxsize/$size) * 0.93 - 1.7;  // -($quality/7.222)*
      }
      while (($size > $maxsize) && ($quality >= 16));
      imagedestroy($img);
      return($jpeg);
   }


   /* helper code */
   function random_color($img, $a,$b) {
      return imagecolorallocate($img, rand($a,$b), rand($a,$b), rand($a,$b));
   }


   /* creates temporary file, returns basename */
   function store_image($data) {
      $id = md5($data);
      $_SESSION['captcha'][$id] = $data;
      return $id;
   }

   /* unreversable hash from passphrase, with time () slice encoded */
   function hash($text, $dtime=0) {
      $text = strtolower($text);
      $pfix = (int) (time () / CAPTCHA_TIMEOUT) + $dtime;
      return md5("captcha::$pfix:$text::".__FILE__.":$_SERVER[SERVER_NAME]:80");
   }


   /* makes string of random letters for embedding into image and for
      encoding as hash, later verification
   */
   function mkpass () {
      $s = "";
      for ($n=0; $n<10; $n++) {
         $s .= chr(rand(0, 255));
      }
      $s = base64_encode($s);   // base64-set, but filter out unwanted chars
      $s = preg_replace("/[+\/=IG0ODQR]/i", "", $s);  // (depends on YOUR font)
      $s = substr ($s, 0, rand(5,7));
      return($s);
   }
}

?>