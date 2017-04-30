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
        $childTag = $child->getTag()->name();

        // if font we need to extract the color
        if($childTag == 'font') {
          $text = "[{$child->getAttribute('style')}]: {$child->text}";
        } else {
          $text = $child->text;
        }

        echo $text . PHP_EOL;

        try {
          $child = $child->nextSibling();
        } catch(Exception $e) {
          $child = null;
        }

        $text = '';
      } while($child != null);
    }
  }
}