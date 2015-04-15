<?php
class vobject_sanitize
{
  public $vobject;
  private $components = array('VEVENT', 'VTODO', 'VJOURNAL', 'VFREEBUSY', 'VTIMEZONE', 'VCARD', 'VALARM');
  private $properties = array();

  public function __construct($vobject, $properties = array(), $method = 'serialize')
  {
    $this->vobject = $vobject;
    $this->properties = (array) $properties;
    $this->_unfoald();
    $this->_eol();
    switch($method){
      case 'serialize':
        $this->_serialize();
        break;
      case 'unserialize':
        $this->_unserialize();
        break;
      }
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
  
  private function _serialize()
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
  
  private function _unserialize()
  {
    foreach($this->properties as $property){
      preg_match_all('#' . PHP_EOL . $property . '.*:.*,.*' . PHP_EOL . '#i', $this->vobject, $matches);
      $content = $this->vobject;
      if(is_array($matches)){
        foreach($matches[0] as $match){
          $temp = explode(':', $match, 2);
          $field = $temp[0];
          $values = $temp[1];
          $properties = explode(';', $field);
          $tz = false;
          foreach($properties as $idx => $property){
            if(strtolower(substr($property, 0, 5)) == 'tzid='){
              $temp = explode('=', $property, 2);
              $tz = $temp[1];
              unset($properties[$idx]);
            }
            if(strtolower(substr($property, 0, 6)) == 'value='){
              $temp = explode('=', $property, 2);
              $daot = $temp[1];
            }
          }
          $field = implode(';', $properties);
          $values = explode(',', $values);
          $line = '';
          foreach($values as $value){
            if($tz){
              $datetime = new DateTime($value, new DateTimeZone($tz));
              if(strtolower($daot) == 'date-time'){
                $ts = $datetime->format('U');
                $value = gmdate('Ymd\THis\Z', $ts);
              }
            }
            $line .= $field . ':' . $value . PHP_EOL;
          }
          $content = preg_replace('/\s\s+/', PHP_EOL, str_replace($match, $line, $content));
        }
      }
      $this->vobject = $content;
    }
  }
}
?>