<?php
require ROOT . '/vendor/autoload.php';
use PHPHtmlParser\Dom;

class LogParser {

  private $log;

  public function __construct($rawLog) {
    $this->log = $rawLog;
    $this->log = $this->parseHTML($this->log);
    $this->log = $this->separateLogByAction($this->log);
    $this->log = $this->cleanLog($this->log);
  }

  // pulls text out of html
  private function parseHTML($rawLog) {
    $dom = new Dom;
    $log = array();

    foreach($rawLog as $line) {
      $text = '';
      $dom->load($line);
      $html = $dom->find('font', 0);
      $child = $html->firstChild();
      
      do {
        $childTag = $child->getTag()->name();

        // if font we need to extract the color
        if($childTag == 'font') {
          $text .= "[{$child->getAttribute('style')}]: {$child->text}";
        } else {
          $text .= trim($child->text);
        }

        try {
          $child = $child->nextSibling();
        } catch(Exception $e) {
          $child = null;
        }

      } while($child != null);

      if(!empty($text)) array_push($log, $text);
    }

    return $log;
  }

  private function separateLogByAction($log) {
    $newLog = array();

    foreach($log as $line) {
      $actions = explode('.', $line);
      $newLog = array_merge($newLog, $actions);
    }

    return $newLog;
  }

  private function cleanLog($log) {
    $newLog = array();

    foreach($log as $action) {
      if(!empty(trim($action))) {
        $action = str_replace('&nbsp;', ' ', $action);
        $action = preg_replace('/\,{2,}/', '', $action);
        $action = str_replace(' - ', '', $action);
        echo $action . PHP_EOL;
      }
    }

    return $newLog;
  }
}