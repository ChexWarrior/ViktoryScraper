var casper = require('casper').create({
      // verbose: true,
      // logLevel: 'debug',
      waitTimeout: 15000
    }),
    gameLogLinkSelector = '#Foundation_Elemental_2_openLog',
    gameLogPopupUrlPattern = /logPopupHtml/,
    gameLogSelector = '#Foundation_Elemental_1_log > table tr',
    spectatorUrlLink = '#Foundation_Elemental_2_spectatorAnchor',
    gameLogContents,
    gameStatus,
    playerInfo,
    siteUrl = casper.cli.has(0) ? casper.cli.get(0) : false;

function getGameStatus() {
  return casper.evaluate(function() {
    var gameStatus = document.getElementById('Foundation_Elemental_2_gameState');

    return gameStatus.innerText;
  });
}

function getPlayerInfo() {
  return casper.evaluate(function() {
    var teams = GamesByEmail.Viktory2Game.getFirst().teams,
      playerInfo = [];

    for (var i = 0; i < teams.length; i += 1) {
      playerInfo.push({
        name: teams[i].players[0].title,
        color: teams[i].title,
        turnOrder: teams[i]._index
      });
    }

    return playerInfo;
  });
}

function getGameLog() {
  return casper.evaluate(function() {
    var contents = [],
        logs = document.querySelectorAll('a[id^="Foundation_Elemental_1_viewLog_"]');

    for (var i = 1; i < logs.length; i += 1) {
      contents.push(logs[i].innerHTML);
    }

    return contents;
  });
}

if (!siteUrl) {
  casper.echo('You must specify a site url!');
  casper.exit(1);
} else {
  casper.echo(siteUrl);
  casper.echo('{{BREAK}}');
}

casper.start(siteUrl)
  .waitForSelector(gameLogLinkSelector, function() {
    if(this.exists(spectatorUrlLink)) {
      this.echo('You must enter the spectator url!');
      casper.exit(1);
    } else {
      gameStatus = getGameStatus();
      this.echo(gameStatus + '{{BREAK}}');
      playerInfo = getPlayerInfo();
      for (var i = 0; i < playerInfo.length; i += 1) {
        this.echo(playerInfo[i].name + ',' + playerInfo[i].color + ',' 
          + playerInfo[i].turnOrder);
      }
      
      this.click(gameLogLinkSelector);
    }
  })
  .waitForPopup(gameLogPopupUrlPattern, function() {
    if (!this.popups.length) {
      this.echo('Popup did not load!');
      casper.exit(1);
    }
  })
  .withPopup(gameLogPopupUrlPattern, function() {
    //this.waitForSelectorTextChange(gameLogSelector, function() {
    this.waitForSelector(gameLogSelector, function() {
      gameLogContents = getGameLog();
    });
  }).then(function() {
    for (var i = 0; i < gameLogContents.length; i += 1) {
      this.echo('{{BREAK}}' + gameLogContents[i]);
    }
  })
  .run(function() {
    this.exit();
  });
