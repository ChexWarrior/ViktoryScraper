<?php
use PHPUnit\Framework\TestCase;
define('ROOT', dirname(dirname(__FILE__)));
require_once(ROOT . '/scripts/LogParser.php');

class LogParserTest extends TestCase {
  public function testDetermineGameTime() {
    $playerInfo = array(
      array(
        'name' => 'Cao Cao',
        'color' => 'Blue',
        'order' => 0,
      ),
      array(
        'name' => 'Liu Bei',
        'color' => 'Green',
        'order' => 1,
      ),
      array(
        'name' => 'Sun Quan',
        'color' => 'Red',
        'order' => 2,
      ),
    );

    $log = array(
      // round 1
      array('color' => 'Blue'),
      array('color' => 'Green'),
      array('color' => 'Red'),
      // round 2
      array('color' => 'Blue'),
      array('color' => 'Red'),
      // round 3
      array('color' => 'Blue'),
      array('color' => 'Red'),
      // round 4
      array('color' => 'Blue'),
    );

    $logParser = new LogParser('', '', '', true);
    $results = $logParser->determineGameTime($log, $playerInfo);
    $this->assertEquals(4, end($results)['round']);
    $this->assertEquals(8, end($results)['turn']);
  }

  public function testParseActions() {
    $log = array(
      array(
        'actions' => array(
          'Revealed 10 hexes',
          'Revealed 1000 hex',
        ),
      )   
    );

    $logParser = new LogParser('', '', '', true);
    $results = $logParser->parseActions($log);
    $this->assertEquals($results[0]['actions'][0]['type'], 'revealedHexes');
    $this->assertEquals($results[0]['actions'][0]['amount'], '10');
    $this->assertEquals($results[0]['actions'][1]['type'], 'revealedHexes');
    $this->assertEquals($results[0]['actions'][1]['amount'], '1000');
  }
}
