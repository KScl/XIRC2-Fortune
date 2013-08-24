<?php
/*
 * XIRC2 IRC Bot Module - Fortune
 * Copyright (c) 2012-2013 Inuyasha.
 * All rights reserved.
 *
 * fortune.php
 * Entry point, module management, etc.
 */

require_once (COMMON_LIBS."timer.php");

define('FORTUNE_DIR',         dirname(__FILE__).DIRECTORY_SEPARATOR);
define('FORTUNE_DATA_DIR',    FORTUNE_DIR."data".DIRECTORY_SEPARATOR);
define('FORTUNE_LAYOUT_DIR',  FORTUNE_DIR."layouts".DIRECTORY_SEPARATOR);
define('FORTUNE_INCLUDE_DIR', FORTUNE_DIR."include".DIRECTORY_SEPARATOR);

require_once (FORTUNE_INCLUDE_DIR."fortuneStats.php");
require_once (FORTUNE_INCLUDE_DIR."fortunePuzzle.php");
require_once (FORTUNE_INCLUDE_DIR."fortuneContestant.php");
require_once (FORTUNE_INCLUDE_DIR."fortuneWheel.php");
require_once (FORTUNE_INCLUDE_DIR."fortuneWedges.php");
require_once (FORTUNE_INCLUDE_DIR."fortuneSettings.php");

/*
 * The top half of this class is static so it can be accessed easily by other classes
 * The rest is bot handling stuff
 */
class fortune implements XIRC_Module {
	static $puzzles = array();
	static $globalusedpuzzles = array();
	static $globallistsize = 1;

	// Stats
	static $stats;

	// Data storage files
	private static $puzzlefile;
	private static $statsfile;

	// Moderation
	private static $superops;

	/*
	 * Internal statistics and puzzle loading and saving
	 */
	static public function loadPuzzles($file = NULL) {
		if ($file)
			self::$puzzlefile = $file;

		//timekeeping
		$time = microtime(true);
		self::$puzzles = array();

		$qt = file_get_contents(self::$puzzlefile);
		$qt = explode("\r\n",$qt);

		foreach($qt as $k=>$s) {
			$s = trim($s);
			if ($s == "" || $s{0} == '#') continue;
			self::$puzzles[] = new fortunePuzzle($s, $k+1);
		}
		// Shuffling puzzle array: Does it really help with randomness? Answer: Probably not.
		//shuffle(self::$puzzles);

		$timetaken = sprintf("%1.7f",microtime(true)-$time);
		console(count(self::$puzzles) ." puzzles loaded in $timetaken seconds.");
		self::$globallistsize = (int)(count(self::$puzzles) * 0.6);
		self::$globalusedpuzzles = array();
		console("Global used puzzles list maximum size set to ".self::$globallistsize.".");
	}

	static public function loadContestantData($file = NULL) {
		if ($file)
			self::$statsfile = $file;

		//timekeeping
		$time = microtime(true);
		self::$stats = array();

		$qt = @file_get_contents(self::$statsfile);
		if ($qt === false)
			return;

		$qt = explode("\r\n",$qt);
		if (count($qt) < 2)
			return;

		/// ***TIME
		array_shift($qt);

		$curlayout = 'regular';

		foreach ($qt as $s) {
			if ($s == "")
				break;

			$savedata = explode("\t", $s);

			if ($savedata[0] === '***LAYOUT')
				$curlayout = $savedata[1];
			elseif ($savedata[0] === '***GAMEDATA')
				self::$stats[$curlayout]->gamedata = unserialize($savedata[1]);
			else
				self::$stats[$curlayout]->ctsdata[$savedata[0]] = unserialize($savedata[1]);
		}

		$timetaken = sprintf("%1.7f",microtime(true)-$time);
		console("Stats loaded in {$timetaken} seconds.");
	}

	static public function saveContestantData() {
		$ln = array("***TIME\t".date('r'));

		foreach (self::$stats as $k=>$d) {
			$ln[] = "***LAYOUT\t".$k;
			$ln[] = "***GAMEDATA\t".serialize($d->gamedata);

			foreach ($d->ctsdata as $ck=>$cd) {
				if ($cd->lastplay == 0) // never actually played, don't save
					unset($d->ctsdata[$ck]);
				else
					$ln[] = "{$ck}\t".serialize($cd);
			}
		}
		file_put_contents(self::$statsfile, implode("\r\n", $ln));
	}

	static public function getLayoutStats($layout) {
		$layout = strtolower($layout);
		if (!isset(self::$stats[$layout])) {
			self::$stats[$layout] = new fortuneStats();
			self::$stats[$layout]->gamedata = new fortuneGameData();
			self::$stats[$layout]->ctsdata = array();
		}
		return self::$stats[$layout];
	}

	static public function layoutStatsExist($layout) {
		$layout = strtolower($layout);
		return isset(self::$stats[$layout]);
	}
	//
	// End static section
	//


	// fortuneSettings class objects that determines how the game flows
	// [channel] => [object]
	// Flawlessly supports multiple games in multiple channels
	private $gamesettings = array();

	// Test wheel that anyone can use when games aren't running
	private $freewheel = NULL;
	private $defaultlayout;

	// Channels to IGNORE.
	private $exclude = array();

	/*
	 * Setup functions.
	 */
	private function setupFreeWheel() {
		$layout = $this->defaultlayout;
		$round = config::read('Fortune', 'freewheelround');

		$olderp = error_reporting(E_ERROR|E_PARSE);
		$r = include_once (FORTUNE_LAYOUT_DIR."{$layout}.php");
		error_reporting($olderp);

		if ($r === FALSE) // bad.
			die(consoleError("Game layout file \"{$layout}\" (default, used for free wheel) doesn't seem to exist."));

		$this->freewheel = new fortuneWheel();

		$layoutClass = 'fortuneLayout_'.$layout;
		$game = new $layoutClass($layout);

		for ($i = 1; $i <= $round; ++$i)
			$game->setupWheel($this->freewheel, $i);

		unset($game);
	}

	private function spinFreeWheel(&$data, $amount = -1) {
		if ($amount < 0) $amount = random::range(1428, 3612); //random::range(1008 - 42,(1008*3)+42);

		$this->freewheel->spin($amount);
		$wedge = $this->freewheel->getLandedWedge();

		//$placeholder = str_repeat("~", $wedge->reallen);
		//$msg = sprintf("%s spins %s.", $data->nick, $placeholder);
		//irc::message($data->channel, $this->freewheel->displayWheelTip($msg, $placeholder, $wedge->shorttext));
		//irc::message($data->channel, $this->freewheel->displayWheel());

		$extratext = $wedge->getExtraText();
		if ($extratext !== '')
			$extratext = ' '.$extratext;

		$msg = sprintf("%s spins %s%s.", $data->nick, $wedge->shorttext, $extratext);
		irc::message($data->channel, $msg);
	}

	/*
	 * Get information from game states.
	 */
	public function isRunning($chan) {
		$chan = strtolower($chan);
		return (isset($this->gamesettings[$chan]));
	}

	public function getGame($chan) {
		$chan = strtolower($chan);
		if (isset($this->gamesettings[$chan]))
			return $this->gamesettings[$chan];
		return NULL;
	}

	private function setGame($chan, &$game) {
		$chan = strtolower($chan);
		$this->gamesettings[$chan] = $game;
	}

	/*
	 * Events.
	 */
	public function __construct() {
		$this->defaultlayout = config::read('Fortune', 'freewheellayout');
		$this->setupFreeWheel();

		$this->exclude = config::read('Fortune', 'ignore', array());

		self::$superops = config::read('Fortune', 'superops', array());

		self::loadPuzzles(FORTUNE_DATA_DIR."puzzles.txt");
		self::loadContestantData(FORTUNE_DATA_DIR."contestants.txt");
	}

	public function onIrcInit($myName) {
		//event
		events::hook($myName, EVENT_CHANNEL_MESSAGE, 'onChat');
		events::hook($myName, EVENT_MESSAGE,         'onQuery');
	}

	public function onMainLoop() {
		foreach ($this->gamesettings as $k=>$s) {
			$s->onMainLoop();

			if ($s->candie)
				unset($this->gamesettings[$k]);
		}
	}

	public function onChat(&$data) {
		if (in_array($data->channel, $this->exclude)) return;
		$functionCalls = array(
			'!fortune' => 'startWOF',
			'!leave'   => 'leaveWOF',
			'!player'  => 'showPlayerData',
			'!stats'   => 'showGameData',
			'!top'     => 'showTop',
			'!top5'    => 'showTop',
			'!spin'    => 'freeSpin',
			//Op only
			'!endgame' => 'endWOF',
			'!skip'    => 'skipAction',
		);
		if ($func = $functionCalls[$data->messageex[0]]) {
			if ($this->$func($data))
				return;
		}

		if (($game = $this->getGame($data->channel)) == NULL) return;
		if ($game->waitingForPlayers) return;

		$command = ($data->message{0} == '!');
		$game->textHandler($data, $command);
	}

	public function onQuery(&$data) {
		$functionCalls = array(
			'reload'  => 'reloadPuzzles',
			'running' => 'showRunning',
		);
		if ($func = $functionCalls[$data->messageex[0]])
			$this->$func($data);
	}

	/*
	 * Administrative fun stuff
	 */
	public function reloadPuzzles(&$data) {
		consoleWarn("{$data->nick} tried to reload puzzles");
		if (!in_array($data->nick, self::$superops)) return; //get outta here!

		self::loadPuzzles();
		irc::message($data->nick, "Puzzles reloaded from ".fortune::$puzzlefile.". (".count(fortune::$puzzles)." puzzles)");
	}

	public function showRunning(&$data) {
		consoleWarn("{$data->nick} tried to show running games");
		if (!in_array($data->nick, self::$superops)) return; //get outta here!

		if (count($this->gamesettings) < 1) {
			irc::message($data->nick, "No games running.");
			return;
		}
		foreach($this->gamesettings as $s)
			irc::message($data->nick, "{$s->channel}: ".$s->whatAmIDoing());
	}

	public function skipAction(&$data) {
		consoleWarn("{$data->channel}: {$data->nick} tried to skip an action");
		if (!in_array($data->nick, self::$superops) && !irc::hasOp($data->channel,$data->nick))
			return false; //get outta here!

		$game = $this->getGame($data->channel);
		if (!$game) return true;

		$game->message("Skipping this section of the game and advancing on.");
		$game->doAction();
		return true;
	}

	public function endWOF(&$data) {
		consoleWarn("{$data->channel}: {$data->nick} tried to end game");
		if (!in_array($data->nick, self::$superops) && !irc::hasOp($data->channel,$data->nick))
			return false; //get outta here!

		$game = $this->getGame($data->channel);
		if (!$game) return true;

		$game->candie = true;
		$game->message(b().$data->nick.r()." ended the game.");
		return true;
	}

	/*
	 * Commands directly handling games of Fortune
	 */
	public function startWOF(&$data) {
		array_shift($data->messageex);

		if (($game = $this->getGame($data->channel)) == NULL) { // starting a new game
			$options = array("layout" => $this->defaultlayout);
			$needsparam = array("layout","rounds","time","players","minimum","lives");

			while (count($data->messageex) > 0) {
				$elem = array_shift($data->messageex);
				if ($elem{0} != '-') continue;
				$elem = substr($elem, 1);

				if (in_array($elem, $needsparam)) {
					if (!count($data->messageex)) continue;
					$var = array_shift($data->messageex);
					$options[$elem] = $var;
				}
				else
					$options[$elem] = true;
			}

			// This stops people fucking things up (../../../lol)
			$t = explode(DIRECTORY_SEPARATOR, $options['layout']);
			$options['layout'] = array_pop($t);

			// Don't @ this. Parse errors suck to debug with that added.
			// Use error_reporting instead.
			$olderp = error_reporting(E_ERROR|E_PARSE);
			$r = include_once (FORTUNE_LAYOUT_DIR."{$options[layout]}.php");
			error_reporting($olderp);

			if ($r === FALSE) { // bad.
				irc::notice($data->nick, "Game layout file \"{$options[layout]}\" doesn't seem to exist.");
				return true;
			}

			$layoutClass = 'fortuneLayout_'.$options['layout'];
			$game = new $layoutClass($options['layout']);

			$msgs = array();
			foreach($options as $elem=>$var) {
				switch ($elem) {
					case "debug":
						$game->debugger = DEBUG_ON;
						$game->nostats = true;
						$msgs['d'] = "Put your hard hats on!  Debugging in progress.";
						break;
					case "multidebug":
						$game->debugger = DEBUG_MULTI;
						$game->nostats = true;
						$msgs['d'] = "Everyone get out your hard hats!  Channel wide debugging initiated.";
						break;

					case "players":
						$game->playerlimit = min(max((int)$var, 2),6);
						break;
					case "solo":
						$game->playerlimit = 1;
						break;

					case "rounds":
						$var = min((int)$var, 20);
						$game->roundsuntilbell = $var;
						$game->nostats = true;
						$msgs['r'] = (($var > 0) ? "Speedup will occur after ".getTextNumeral($var)." rounds." : "Round limit disabled.");
						break;
					case "time":
						$var = min((int)$var, 3600);
						$game->timeuntilbell = $var;
						$game->nostats = true;
						$msgs['t'] = (($var > 0) ? "Speedup will occur after ".getTextTime($var)."." : "Time limit disabled.");
						break;
					case "minimum":
						$var = min((int)$var, 10000);
						$game->houseminimum = $var;
						$game->nostats = true;
						$msgs['m'] = (($var > 0) ? "Minimum round winnings set to \$".number_format($game->houseminimum)."." : "Minimum round winnings disabled.");
						break;
					case "lives":
						$game->sololives = min(max((int)$var, 2),19);
						$game->nostats = true;
						break;

					case "wheel":
						$msgs['w'] = 'Displaying the full wheel after every spin.  Now you can see exactly how close you are to bankruptcy...';
						$game->showwheel = true;
						break;
				}
			}

			if (($errormsg = $game->checkOptionValidity()) !== true) {
				irc::notice($data->nick, $errormsg);
				unset($game);
				return true;
			}

			$game->channel = $data->channel;
			$this->setGame($data->channel, $game);
			foreach($msgs as $msg)
				$game->message($msg);
			if ($game->nostats)
				$game->message(b()."WARNING: ".b()."Custom settings used.  Statistics are disabled for this game!");
		}

		if ($game->debugger == DEBUG_MULTI) {
			// Fill game to playerlimit
			for ($pl = 0; $pl < $game->playerlimit; ++$pl)
				$game->addContestant("Contestant".chr(ord('A')+$pl));
		}
		else // Add the starter
			$game->addContestant($data->nick);
		return true;
	}

	public function leaveWOF(&$data) {
		$game = $this->getGame($data->channel);
		if (!$game) return true;
		$game->removeContestant($data->nick);
		return true;
	}

	/*
	 * Statskeeping commands, et cetera
	 */
	public function showPlayerData(&$data) {
		if (!$data->messageex[1])
			$nick = $data->nick;
		else
			$nick = $data->messageex[1];

		if ($data->messageex[2])
			$layoutname = $data->messageex[2];
		else
			$layoutname = $this->defaultlayout;

		if (!self::layoutStatsExist($layoutname)) {
			irc::message($data->channel, "No layout with the name '{$layoutname}' exists.");
			return true;
		}
		$stats = self::getLayoutStats($layoutname);

		if (!($pdata = $stats->ctsdata[strtolower($nick)])) {
			irc::message($data->channel, "{$nick} either doesn't exist or hasn't played on that layout yet.");
			return true;
		}

		$date = date("F jS Y, g:i a", $pdata->lastplay);
		$time = b().getShortTextTime(time() - $pdata->lastplay).r();
		irc::message($data->channel, "{$nick} last played on {$date} ({$time} ago).");

		irc::message($data->channel, "{$nick} has ".b()."{$pdata->wins}".b()." wins in ".b()."{$pdata->games}".b()." regular games,"
			." and ".b()."{$pdata->solowins}".b()." wins in ".b()."{$pdata->sologames}".b()." solo games.");

		$place = 1;
		$money = number_format($pdata->money);

		foreach ($stats->ctsdata as $ct)
			if ($ct->money > $pdata->money)
				++$place;

		$ord = getOrdinal($place);
		irc::message($data->channel, "{$nick} is ".b()."{$ord}".b()." in total winnings with ".b()."\${$money}".b().".");

		$place = 1;
		$money = number_format($pdata->bestgame);

		foreach ($stats->ctsdata as $ct)
			if ($ct->bestgame > $pdata->bestgame)
				++$place;

		$ord = getOrdinal($place);
		irc::message($data->channel, "{$nick} is ".b()."{$ord}".b()." in best single-game winnings with ".b()."\${$money}".b().".");

		$place = 1;
		$money = number_format($pdata->bestsolo);

		foreach ($stats->ctsdata as $ct)
			if ($ct->bestsolo > $pdata->bestsolo)
				++$place;

		$ord = getOrdinal($place);
		irc::message($data->channel, "{$nick} is ".b()."{$ord}".b()." in best solo winnings with ".b()."\${$money}".b().".");
		return true;
	}

	public function showGameData(&$data) {
		if ($data->messageex[1])
			$layoutname = $data->messageex[1];
		else
			$layoutname = $this->defaultlayout;

		$totalcash = 0;
		$numgames = 0;

		$bestgame = array(0, '');
		$bestwin  = array(0, '');
		$bestloss = array(0, '');
		$bestsolo = array(0, '');

		if (!strcasecmp($layoutname, 'all')) {
			foreach(self::$stats as $k=>$s) {
				$stats = &$s->gamedata;

				$totalcash += $stats->totalcash;
				$numgames += $stats->games;

				if ($bestgame[0] < $stats->bestgame)
					$bestgame = array($stats->bestgame, "{$stats->bestgamename} [{$k}]");
				if ($bestwin [0] < $stats->bestwin )
					$bestwin  = array($stats->bestwin , "{$stats->bestwinname} [{$k}]");
				if ($bestloss[0] < $stats->bestloss)
					$bestloss = array($stats->bestloss, "{$stats->bestlossname} [{$k}]");
				if ($bestsolo[0] < $stats->bestsolo)
					$bestsolo = array($stats->bestsolo, "{$stats->bestsoloname} [{$k}]");
			}
		}
		elseif (!self::layoutStatsExist($layoutname)) {
			irc::message($data->channel, "No layout with the name '{$layoutname}' exists.");
			return true;
		}
		else {
			$stats = &self::getLayoutStats($layoutname)->gamedata;

			$totalcash = $stats->totalcash;
			$numgames = $stats->games;

			$bestgame = array($stats->bestgame, $stats->bestgamename);
			$bestwin  = array($stats->bestwin , $stats->bestwinname);
			$bestloss = array($stats->bestloss, $stats->bestlossname);
			$bestsolo = array($stats->bestsolo, $stats->bestsoloname);
		}

		$money = b().'$'.number_format($totalcash).r();
		$games = b().$numgames.r();
		irc::message($data->channel, "To date, Fortuna has given out {$money} in fake e-cash over {$games} games.");

		$money = b().'$'.number_format($bestgame[0]).r();
		$name = b().$bestgame[1].r();
		irc::message($data->channel, "The best single-game score is {$money}, held by {$name}.");

		$money = b().'$'.number_format($bestwin[0]).r();
		$name = b().$bestwin[1].r();
		irc::message($data->channel, "The best single-game score before the bonus round is {$money}, held by {$name}.");

		$money = b().'$'.number_format($bestloss[0]).r();
		$name = b().$bestloss[1].r();
		irc::message($data->channel, "The best single-game score that didn't win a game is {$money}, held by {$name}.");

		$money = b().'$'.number_format($bestsolo[0]).r();
		$name = b().$bestsolo[1].r();
		irc::message($data->channel, "The best solo single-game score is {$money}, held by {$name}.");

		return true;
	}

	public function showTop(&$data) {
		if ($data->messageex[2])
			$layoutname = $data->messageex[2];
		else
			$layoutname = $this->defaultlayout;

		if (!self::layoutStatsExist($layoutname)) {
			irc::message($data->channel, "No layout with the name '{$layoutname}' exists.");
			return true;
		}
		$stats = self::getLayoutStats($layoutname);

		$format = '$%s';
		switch ($data->messageex[1]) {
			case 'total':
				irc::message($data->channel, "Top 5 players, by most total winnings:");
				$var = 'money';
				break;
			case 'best':
				irc::message($data->channel, "Top 5 players, by best single-game winnings:");
				$var = 'bestgame';
				break;
			case 'bestwin':
				irc::message($data->channel, "Top 5 players, by best single-game winnings before the bonus round:");
				$var = 'bestwin';
				break;
			case 'bestloss':
				irc::message($data->channel, "Top 5 players, by best single-game winnings on lost games:");
				$var = 'bestloss';
				break;
			case 'bestsolo':
				irc::message($data->channel, "Top 5 players, by best solo single-game winnings:");
				$var = 'bestsolo';
				break;
			case 'wins':
				irc::message($data->channel, "Top 5 players, by most wins:");
				$var = 'wins';
				$format = '%s';
				break;
			case 'solowins':
				irc::message($data->channel, "Top 5 players, by most solo game wins:");
				$var = 'solowins';
				$format = '%s';
				break;
			case 'games':
				irc::message($data->channel, "Top 5 players, by most games played:");
				$var = 'games';
				$format = '%s';
				break;
			case 'sologames':
				irc::message($data->channel, "Top 5 players, by most solo games played:");
				$var = 'sologames';
				$format = '%s';
				break;
			default:
				irc::message($data->channel, "Top 5 type list: [total, best, bestwin, bestloss, bestsolo, wins, solowins, games, sologames]");
				return;
		}

		$players = array();
		foreach ($stats->ctsdata as &$ct)
			$players[$ct->name] = $ct->$var;
		arsort($players);

		$i = 0;
		foreach($players as $nm=>$va) {
			if (++$i > 5) break;
			$ord = getOrdinal($i);

			$strvar = number_format($va);
			irc::message($data->channel, b()."{$ord}".b().": ".sprintf('%24s', $nm).", with ".b().sprintf($format, $strvar));
		}
		return true;
	}

	public function freeSpin(&$data) {
		// no free spins while running
		if ($this->isRunning($data->channel)) return false;
		$this->spinFreeWheel($data);
		return true;
	}	
}
