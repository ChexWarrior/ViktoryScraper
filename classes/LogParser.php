<?php
require ROOT . '/vendor/autoload.php';
use PHPHtmlParser\Dom;

class LogParser {

  private $log;
  private $playerInfo;

  public function __construct($rawLog, $rawPlayerInfo) {
    $this->playerInfo = $this->extractPlayerInfo(preg_split('/\r?\n/', $rawPlayerInfo));
    $this->log = $rawLog;
    $this->log = $this->parseHTML($this->log);
    $this->log = $this->separateLogByAction($this->log);
    $this->log = $this->checkLogForDuplicateEndOfTurnErrors($this->log);
    $this->log = $this->cleanLog($this->log);
    $this->log = $this->separateLogByTurn($this->log);
    
    echo json_encode($this->log);
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

      if(!empty($text)) $log[] = $text;
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
      $matches = array();

      if(!empty(trim($action))) {
        $action = str_replace('&nbsp;', ' ', $action);
        $action = preg_replace('/\,{2,}/', '', $action);
        $action = str_replace(' - ', '', $action);
        $action = str_replace('color:', '', $action);

        if(preg_match('/(#[A-Z0-9]+)/', $action, $matches) === 1) {
          $action = str_replace($matches[1], $this->matchHexCodeToColor($matches[1]), $action);
        }

        $newLog[] = $action;
      }
    }

    return $newLog;
  }

  private function matchHexCodeToColor($hexCode) {
    $color = "";
    switch($hexCode) {
      case "#BFBF00":
        $color = "Yellow";
        break;
      case "#BF0000":
        $color = "Red";
        break;
      case "#00BF00":
        $color = "Green";
        break;
      case "#00BFBF":
        $color = "Cyan";
        break;
      case "#0000BF":
        $color = "Blue";
        break;
      case "#BF00BF":
        $color = "Magenta";
        break;
      case "#FF8040":
        $color = "Orange";
        break;
      case "#AE5E5E":
        $color = "Brown";
        break;
      case "#080808":
        $color = "Black";
    }

    return $color;
  }

  /**
   * Added to catch bug discovered in log of game, where in the log a player had
   * two end of turn statements (the two log statements were also out of order)
   */
  private function checkLogForDuplicateEndOfTurnErrors($log) {
    $turnStartPattern = '/^BEGINNING OF TURN for ([a-zA-Z]+)/';
    $turnEndPattern = '/^END OF TURN/';

    for($index = 0; $index < count($log) - 1; $index += 1) {
      $nextLog = $log[$index + 1];

      // check that everytime we get the end of turn pattern the next line is the start turn pattern
      if(preg_match($turnEndPattern, $log[$index]) === 1) {
        if(preg_match($turnStartPattern, $nextLog) !== 1) {
          $log[$index] = "";
        }
      }
    }

    return $log;
  }

  private function separateLogByTurn($log) {
    $newLog = array();
    $turnStartPattern = '/^BEGINNING OF TURN for ([a-zA-Z]+)/';
    $turnCount = -1;

    foreach($log as $action) {
      $matches = array();

      if(preg_match($turnStartPattern, $action, $matches) === 1) {
        $turnCount += 1;
        $newLog[$turnCount] = array(
          'color' => $matches[1],
          'turn' => $turnCount,
          'actions' => array(),
        );
      } else {
        $newLog[$turnCount]['actions'][] = $action;
      }
    }

    return $newLog;
  }
}