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
    $this->log = $this->groupActionsByPlayer($this->log);
    $this->log = $this->determineGameTime($this->log, $this->playerInfo);
    $this->log = $this->parseNonBattleActions($this->log);
    echo json_encode($this->log);
  }

  private function extractPlayerInfo($playerInfo) {
    $processedPlayerInfo = array();

    foreach($playerInfo as $player) {
      if(!empty($player)) {
        $temp = explode(',', $player);
        $processedPlayerInfo[] = array(
          'name' => $temp[0],
          'color' => $temp[1],
          'order' => $temp[2],
        );
      }
    }

    return $processedPlayerInfo;
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

  private function groupActionsByPlayer($log) {
    $newLog = array();
    $turnStartPattern = '/^BEGINNING OF TURN for ([a-zA-Z]+)/';
    $turnCount = -1;

    foreach($log as $action) {
      $matches = array();

      if(preg_match($turnStartPattern, $action, $matches) === 1) {
        $turnCount += 1;
        $newLog[$turnCount] = array(
          'color' => $matches[1],
          'actions' => array(),
        );
      } else {
        $newLog[$turnCount]['actions'][] = $action;
      }
    }

    return $newLog;
  }

  private function determineGameTime($log, $playerInfo) {
    $turnCount = 0;
    $roundCount = 0;
    $turnOrder = array();
    $nextExpectedPlayer = false;
    $processedLog = array();

    // determine turn order of players
    for($index = 0; $index < count($playerInfo); $index += 1) {
      $prevPlayer = ($index - 1 < 0) 
                  ? $playerInfo[count($playerInfo) - 1]
                  : $playerInfo[$index - 1];

      $nextPlayer = ($index + 1 > count($playerInfo) - 1)
                  ? $playerInfo[0]
                  : $playerInfo[$index + 1];

      $turnOrder[$playerInfo[$index]['color']] = array(
        'isFirst' => $index == 0,
        'next' => $nextPlayer['color'],
        'prev' => $prevPlayer['color'],
      ); 
    }

    foreach($log as $playerTurn) {
      $currentPlayer = $playerTurn['color'];

      // if current player is not equal to what was expected last turn
      // then a player has been eliminated and order must be changed
      if($nextExpectedPlayer && $nextExpectedPlayer != $currentPlayer) {
        $turnOrder[$lastPlayer]['next'] = $currentPlayer;

        // if the expected player was first make this player the first player
        if(!$turnOrder[$currentPlayer]['isFirst']) {
          $turnOrder[$currentPlayer]['isFirst'] = 
            $turnOrder[$nextExpectedPlayer]['isFirst'] ? true : false;
        }

        unset($turnOrder[$nextExpectedPlayer]);
      }

      // pull next player color for comparison on next iteration
      $lastPlayer = $currentPlayer;
      $nextExpectedPlayer = $turnOrder[$currentPlayer]['next'];

      // update turns and rounds
      $turnCount += 1;
      $roundCount = $turnOrder[$currentPlayer]['isFirst'] 
                  ? $roundCount + 1
                  : $roundCount;
                  
      $playerTurn['round'] = $roundCount;
      $playerTurn['turn'] = $turnCount;
      $processedLog[] = $playerTurn;
    }

    return $processedLog;
  }

  /**
   * Uses regex to extract info of non-battle actions
   */
  private function parseNonBattleActions($log) {
    $playerActions = array(
      array(
        'name' => 'revealedHexes',
        'pattern' => '/Revealed ([0-9]+) hex(es)?/',
        'data' => array(
            'amount',
        ),
      ),
      array(
        'name' => 'unitsPlacedFromReserve',
        'pattern' => '/Placed reserve (infantry|cavalry|artillery) in (town|city) at ([0-9]+)/',
        'data' => array(
            'unitType',
            'structureType',
            'targetHexID',
        ),
      ),
      array(
        'name' => 'unitsPlacedFromReserve',
        'pattern' => '/Placed reserve (frigate) at ([0-9]+) for (town|city) at ([0-9]+)/',
        'data' => array(
            'unitType',
            'targetHexID',
            'structureType',
            'structureHexID',
        ),
      ),
      array(
        'name' => 'hexPlaced',
        'pattern' => '/Placed a (forest|water|mountain|grassland|plains) hex at ([0-9]+)/',
        'data' => array(
            'hexType',
            'targetHexID',
        ),
      ),
      array(
        'name' => 'capitalPlaced',
        'pattern' => '/Placed capital at ([0-9]+)/',
        'data' => array(
            'targetHexID',
        ),
      ),
      array(
        'name' => 'townPlaced',
        'pattern' => '/Built town at ([0-9]+)/',
        'data' => array(
            'targetHexID',
        ),
      ),
      array(
        'name' => 'cityPlaced',
        'pattern' => '/Upgraded town to city at ([0-9]+)/',
        'data' => array(
            'targetHexID',
        ),
      ),
      array(
        'name' => 'unitMoved',
        'pattern' => '/Moved (infantry|cavalry|artillery|frigate) from ([0-9]+) ([0-9]+) spaces? to ([0-9]+)/',
        'data' => array(
            'unitType',
            'sourceHexID',
            'spacesMoved',
            'targetHexID',
        ),
      ),
      array(
        'name' => 'tradeWaterHex',
        'pattern' => '/Traded a water hex for a (forest|water|mountain|grassland|plains)/',
        'data' => array(
            'hexType',
        ),
      ),
      array(
        'name' => 'placeUnitInReserve',
        'pattern' => '/Moved (infantry|cavalry|artillery|frigate) at ([0-9]+) to reserve/',
        'data' => array(
            'unitType',
            'sourceHexID',
        ),
      ),
      array(
        'name' => 'loadUnitInFrigate',
        'pattern' => '/Loaded (infantry|cavalry|artillery|frigate) at ([0-9]+) on to frigate at ([0-9]+)/',
        'data' => array(
            'unitType',
            'sourceHexID',
            'targetHexID',
        ),
      ),
      array(
        'name' => 'unloadUnitFromFrigate',
        'pattern' => '/Unloaded (infantry|cavalry|artillery|frigate) from frigate at ([0-9]+) to ([0-9]+)/',
        'data' => array(
            'unitType',
            'sourceHexID',
            'targetHexID',
        ),
      ),
      array(
        'name' => 'playerResigned',
        'pattern' => '/([a-zA-Z]+) resigns/',
        'data' => array(
            'player',
        ),
      ),
      array(
        'name' => 'bombard',
        'pattern' => '/Bombarding \[([a-zA-Z]+)\] ([0-9]+) [a-zA-Z]+ units? in ([0-9]+) with (artillery|frigate) in ([0-9]+)/',
        'data' => array(
            'defender',
            'unitAmount',
            'defenderHexID',
            'attackingUnitType',
            'attackerHexID',
        ),
      ),
      array(
        'name' => 'attackRollNoHits',
        'pattern' => '/([0-9]+) attacking units? rolled scoring no hits/',
        'data' => array(
          'unitAmount',
        ),
      ),
      array(
        'name' => 'attackRoll',
        'pattern' => '/([0-9]+) attacking units? rolled scoring ([0-9]+) tactical and ([0-9]+) nontactical hits?\./',
        'data' => array(
          'unitAmount',
          'tacticalHits',
          'nontacticalHits',
        ),
      ),
      array(
        'name' => 'attackRoll',
        'pattern' => '/([0-9]+) attacking units? rolled scoring ([0-9]+) tactical hits?\./',
        'data' => array(
          'unitAmount',
          'tacticalHits',
        ),
      ),
      array(
        'name' => 'attackRoll',
        'pattern' => '/([0-9]+) attacking units? rolled scoring ([0-9]+) nontactical hits?\./',
        'data' => array(
          'unitAmount',
          'nontacticalHits',
        ),
      ),
      array(
        'name' => 'attackRoll',
        'pattern' => '/([0-9]+) attacking units? rolled scoring ([0-9]+) nontactical hits? supp?ressing ([0-9]+) defensive rolls?\./',
        'data' => array(
          'unitAmount',
          'nontacticalHits',
          'suppressedRolls',
        ),
      ),
      array(
        'name' => 'attackRoll',
        'pattern' => '/([0-9]+) attacking units? rolled scoring ([0-9]+) tactical hits? supp?ressing ([0-9]+) defensive rolls?\./',
        'data' => array(
          'unitAmount',
          'tacticalHits',
          'suppressedRolls',
        ),
      ),
      array(
        'name' => 'attackRoll',
        'pattern' => '/([0-9]+) attacking units? rolled scoring ([0-9]+) tactical and ([0-9]+) nontactical hits? supp?ressing ([0-9]+) defensive rolls?\./',
        'data' => array(
          'unitAmount',
          'tacticalHits',
          'nontacticalHits',
          'suppressedRolls',
        ),
      ),
      array(
        'name' => 'defenderRollNoHits',
        'pattern' => '/([0-9]+) defending units? rolled scoring no hits/',
        'data' => array(
          'unitAmount',
        ),
      ),
      array(
        'name' => 'defenderRoll',
        'pattern' => '/([0-9]+) defending units? rolled scoring ([0-9]+) tactical and ([0-9]+) nontactical hits?\./',
        'data' => array(
          'unitAmount',
          'tacticalHits',
          'nontacticalHits',
        ),
      ),
      array(
        'name' => 'defenderRoll',
        'pattern' => '/([0-9]+) defending units? rolled scoring ([0-9]+) tactical hits?\./',
        'data' => array(
          'unitAmount',
          'tacticalHits',
        ),
      ),
      array(
        'name' => 'defenderRoll',
        'pattern' => '/([0-9]+) defending units? rolled scoring ([0-9]+) nontactical hits?\./',
        'data' => array(
          'unitAmount',
          'nontacticalHits',
        ),
      ),
      array(
        'name' => 'battleStart',
        'pattern' => '/\[([a-zA-Z]+)\] Resolving battle in ([0-9]+)/',
        'data' => array(
          'defenderColor',
          'targetHexID',
        ),
      ),
      array(
        'name' => 'battleResult',
        'pattern' => '/(Attacker|Defender) returned ([0-9]+) (infantry|cavalry|artillery|frigates?)(?: and)?( [0-9]+)?( infantry| cavalry| artillery| frigates?)?(?: and)?( [0-9]+)?( infantry| cavalry| artillery| frigates?)?(?: and)?( [0-9]+)?( infantry| cavalry| artillery| frigates?)? to their reserve/',
        'data' => array(
          'target',
          'amountLost1',
          'unitType1',
          'amountLost2',
          'unitType2',
          'amountLost3',
          'unitType3',
          'amountLost4',
          'unitType4',
        ),
      ),
      array(
        'name' => 'battleEnd',
        'pattern' => '/(Attacker|Defender) prevails (?:retains|gains) control of hex ([0-9]+)/',
        'data' => array(
          'victor',
          'targetHexID',
        ),
      ),
      array(
        'name' => 'battleEnd',
        'pattern' => '/Both sides eliminated hex ([0-9]+)/',
        'data' => array(
          'targetHexID',
        ),
      ),
      array(
        'name' => 'retreat',
        'pattern' => '/Retreated (infantry|cavalry|artillery|frigate) from ([0-9]+) to ([0-9]+)/',
        'data' => array(
          'unitType',
          'sourceHexID',
          'targetHexID',
        ),
      ),
    );

    foreach($log as &$playerTurn) {
      foreach($playerTurn['actions'] as &$action) {
        $origAction = $action;
        foreach($playerActions as $playerAction) {
          $action = $this->parseNonBattleActionStats(
              $action,
              $playerAction['pattern'],
              $playerAction['name'],
              $playerAction['data']
            );

          if($action !== $origAction) break;
        }
      }
    }

    return $log;
  }

  /**
   * Stores information about each action as array
   */
  private function parseNonBattleActionStats($actionString, $pattern, $actionName, $data) {
    $matches = array();
    $result = array();

    if(preg_match($pattern, $actionString, $matches) === 1) {
      $result['type'] = $actionName;

      for($index = 0; $index < count($data) && $index < count($matches) - 1; $index += 1) {
        $result[$data[$index]] = $matches[$index + 1];
      }

      return $result;
    }

    return $actionString;
  }
}