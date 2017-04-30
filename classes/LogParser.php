<?php
require ROOT . '/vendor/autoload.php';
use PHPHtmlParser\Dom;

class LogParser {

  private $log;

  public function __construct($rawLog) {
    $this->log = $rawLog;
    $this->parseHTML($this->log);
  }

  private function parseHTML($rawLog) {
    $dom = new Dom;

    foreach($rawLog as $line) {
      $dom->load($line);
      $html = $dom->find('font', 0);
      $child = $html->firstChild();
      
      do {
        echo $child->getTag()->name() . PHP_EOL;

        try {
          $child = $child->nextSibling();
        } catch(Exception $e) {
          break;
        }
      } while(!empty($child));
    }
  }
}