<?php
class vobject_sanitize
{
  public $vobject;
  private $components = array('VEVENT', 'VTODO', 'VJOURNAL', 'VFREEBUSY', 'VTIMEZONE', 'VCARD', 'VALARM');
  private $properties = array();

  public function __construct($vobject, $properties = array())
  {
    $this->vobject = $vobject;
    $this->properties = (array) $properties;
    $this->_unfoald();
    $this->_eol();
    $this->_sanitze();
  }
  
  private function _unfoald()
  {
    $data = array();
    $content = explode("\n", $this->vobject);
    for($i = 0; $i < count($content); $i++){
      $line = rtrim($content[$i]);
      while(isset($content[$i + 1]) && strlen($content[$i + 1]) > 0 && ($content[$i+1]{0} == ' ' || $content[$i + 1]{0} == "\t" )){
        $line .= rtrim(substr($content[++$i], 1));
      }
      $data[] = $line;
    }
    $this->vobject = implode(PHP_EOL, $data);
  }
  
  private function _eol()
  {
    $this->vobject = preg_replace('/\s\s+/', PHP_EOL, $this->vobject);
  }
  
  private function _sanitze()
  {
    $tokens = array();
    foreach($this->components as $component){
      $regex = '#BEGIN:' . $component . '(?:(?!BEGIN:' . $component . ').)*?END:' . $component . '#si';
      preg_match_all($regex, $this->vobject, $matches);
      foreach($matches as $part){
        foreach($part as $match){
          $token = md5($match);
          $tokens[$token] = $match;
          $this->vobject = str_replace($match, '***' . $token . '***', $this->vobject);
        }
      }
      foreach($tokens as $token => $content){
        foreach($this->properties as $property){
          $content = preg_replace('#' . PHP_EOL . $property . ':#i', PHP_EOL . 'X-ICAL-SANITIZE-' . $property . ':', $content, 1);
          $content = preg_replace('#' . PHP_EOL . $property . ':#i', ',', $content);
          $content = str_replace(PHP_EOL . 'X-ICAL-SANITIZE-' . $property . ':', PHP_EOL . $property . ':', $content);
          $this->vobject = str_replace('***' . $token . '***', $content, $this->vobject);
        }
      }
    }
  }
}
?>