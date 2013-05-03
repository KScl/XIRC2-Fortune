<?php
/*
 * XIRC2 IRC Bot Module - Fortune
 * Copyright (c) 2012-2013 Inuyasha.
 * All rights reserved.
 *
 * include\fortuneSettings.php
 * Management for a single instance of a Fortune game.  This class is what actually drives most things.
 */

define('GUESS_CONSONANT', 1);
define('GUESS_VOWEL',     2);
define('GUESS_ALL',       3);
define('GUESS_DIDNTSPIN', 4);
define('GUESS_NORISK',    8);

define('DEBUG_OFF',   0);
define('DEBUG_ON',    1);
define('DEBUG_MULTI', 2);

/**
 * This class handles the actual game.
 * Note that it's an abstract class --
 * Customize the non-final functions in
 * a new class to make a custom game.
 */
abstract class fortuneSettings {
	// Game state information
	public $gamestart    =         0; // Used to determine when to ring the final bell
	public $channel      =        ""; // Channel the game is in.

	// Settings (leave these public!)
	public $debugger        = DEBUG_OFF;
	public $timeuntilbell   =    1200; // How long should a game last by default until the bell rings
	public $roundsuntilbell =       6; // Automatic speedup on round n if time is still going
	public $playerlimit     =       3;
	public $houseminimum    =    1000; // House minimum winnings
	public $showwheel       =   false; // Show the wheel after every spin? :o
	public $nostats         =   false; // Don't save end of game stats
	public $sololives       =       7; // Number of misses you get in Solo mode before the game ends

	public $baseLetters     = 'RSTLNE';
	public $defaultLetters  =  'CDMAP';
	public $vowelCost       =      250;

	private $letters     = NULL;
	private $consonants  = NULL;
	private $vowels      = NULL;
	private $usedLetters = array();

	private $contestants  =   array(); // Contestants, duh!
	private $puzzle       =      NULL;
	private $usedpuzzles  =   array(); // What puzzles have we used this game
	private $wheel        =      NULL;
	private $bonusprize   =      NULL;

	public $candie            = false; // For clean up
	public $waitingForPlayers = true;

	private $action    =      -1; // Current game action
	private $doing     = array(); // What are we doing now?
	private $round     =       0;
	private $roundtype =       0; // bit 1: jackpot, bit 2: prize puzzle
	private $starter   =       0; // Who starts the round?
	private $turn      =      -1; // Who's turn is it?
	private $winner    =      -1;

	private $roundVars = array(); // Reset every round.
	private $gameVars  = array(); // Gamewide variables set by actions
	private $delaying  = false;

	// DON'T FORGET TO SET $messages IN YOUR CLASS
	private $defMessages = array();
	public  $messages    = array();

	// Timers
	private $reminder = NULL;
	private $buzzer   = NULL;

	final public function __construct() {
		$this->letters    = str_split('ABCDEFGHIJKLMNOPQRSTUVWXYZ');
		$this->consonants = str_split('BCDFGHJKLMNPQRSTVWXYZ');
		$this->vowels     = str_split('AEIOU');

		$this->buzzer =   new Timer();
		$this->reminder = new Timer();

		$this->buzzer->setRepeat(false);
		$this->reminder->setRepeat(false);

		$this->setupDefaultMessages();
		$this->setupMessages();
	}

	// Timer update
	final public function onMainLoop() {
		if ($this->reminder != NULL) {
			if ($this->reminder->hasElapsed()===true
			&& method_exists($this,($func = $this->reminder->functionname)))
				$this->$func();
		}
		if ($this->buzzer != NULL) {
			if ($this->buzzer->hasElapsed()===true
			&& method_exists($this,($func = $this->buzzer->functionname)))
				$this->$func();
		}
	}

	// Default messages
	// Workaround for no concatenation
	final private function setupDefaultMessages() {
		$this->defMessages = array(
		'playersolo'    => 'Playing alone, eh %s?  That\'s fine.',
		'playerjoin'    => '%s joins the game!  I still need %s more contestant%s.',
		'playerleave'   => '%s has left the game, so I need %s more contestant%s.',
		'noplayersleft' => '%s has left the game.  The game has been halted because there are now no contestants.',
		'startfortune'  => '%s joins the game!  That\'s all the contestants we need; let\'s play Fortune!',
		'welcome'       => 'Welcome to Fortune!  %s',

		'tossup_generic' => 'It\'s been a little while, so why don\'t we have another little Toss-up puzzle?  This one will be worth $%s.',
		'timeup_tossup'  => 'Time\'s up.  Unfortunately, nobody guessed the right puzzle in time (%s).',
		'buzzed_tossup'  => 'Unfortunately, you all got the puzzle wrong.  The correct answer was %s.',
		'tossup_tiebreak'=> 'We\'ll settle this tie with a tiebreaker Toss-up round.  No money is at stake, but the right to play in the bonus round is.',
		'tossup_slowtie' => 'Okay, I\'m just going to sit here and wait until one of you so-called "winners" decides to finally buzz in.',

		'introduce' => 'With that over, let\'s take a quick moment to introduce our player%s today.',

		'round_fromtossup' => 'And that means %s, you\'re up for round %s.',
		'round_generic'    => 'And now we\'ll move right along to round %2$s.  %1$s is up first, this time.',

		'finalspin'      => 'Oops, that\'s the bell, which means we\'re running short on time; so I\'ll give the wheel a final spin.',
		'speedup_instr1' => 'Starting with %s, you have 10 seconds to give me a letter; if it\'s in the puzzle you\'ll have 20 seconds to solve it.  Vowels are worth nothing...',
		'speedup_botch1' => '... and consonants aren\'t worth anything either, so goodnight everybody and thank you for playing!',
		'speedup_botch2' => 'No seriously, let\'s try that again and see if we can\'t come up with something worthwhile.  Vowels are worth nothing...',
		'speedup_instr2' => '... and consonants are worth $%s apiece.  Now, %s, please pick a letter.',
		'speedup_pick'   => 'It is now %s\'s turn.  Pick a letter.',
		'nomoneyvowels'  => 'Remember, no money for vowels.',
		'speedup_solve'  => '%s has $%s, and has 20 seconds to solve the puzzle.',
		'speedup_nolett' => 'There\'s no letters left, so go ahead and just solve the puzzle.',

		'turn_new'        => 'It is now %s\'s turn.',
		'turn_continue'   => '%s has $%s, and can go again.',
		'wildcard_remind' => 'Remember, you can use your '.b().c(7,6)." WILD CARD ".r().' ("!wildcard") to pick another consonant at the same dollar amount.',

		'category' => array(array('The category this time is "%s".', 'The category for this puzzle is "%s".', '"%s" is the category this time.')),

		'prizepuzzle' => 'Oh, and it\'s a prize puzzle as well, so whoever solves the puzzle will take home an extra fabulous prize.',

		'buy_notenough'  => 'You don\'t have the money to be buying a vowel; you need $250.',
		'buy'            => array(array('Okay, t', 'Alright, t', 'T'), 'hat\'ll be $250.'),
		'wildcard_use'   => array(array('Okay,', 'Alright,'), ' hand over the '.b().c(7,6)." WILD CARD ".r().'.'),
		'doubleplay_use' => array(array('Okay,', 'Alright,'), ' hand over the '.b().c(0,4)." DOUBLE ".c(1).'$$'.c(0)." PLAY ".r().' and give the wheel a good spin.'),
		'nocard'         => 'You can\'t use what you don\'t have.',
		'badspace'       => 'You can\'t use the '.b().c(7,6)." WILD CARD ".r().' at this time.',

		'consonant' => 'Now I need a consonant.',
		'vowel'     => 'Now I need a vowel.',
		'freeplay'  => 'Now I need a letter.  Remember, consonants are worth $500 each, vowels award nothing but cost nothing, and you keep your turn even if the letter isn\'t there.',

		'noletter'    => array(array('Sorry, there ', 'There '), array('is no %s.', 'are no %ss.')),
		'freemiss'    => 'Fortunately, you can\'t lose your turn on the '.b().c(8,3)." FREE ".c(0,3)."PLAY ".r().' space, so go ahead and continue playing.',

		'letter'      => 'There is one %s.',
		'letters'     => 'There are %2$s %1$ss.',
		'worth'       => array(array('That\'s worth $%s.', 'That nets you $%s.', 'That earns you $%s.')),
		'mystery'     => 'That earns you $%s, but you can give that up for a chance at $10,000!  However, you might get BANKRUPT instead...  Press Y in the next 15 seconds to accept this offer.',
		'mystery10k'  => 'That earns you $%s, and I\'m just going to assume you\'d rather keep that instead of giving it up for a chance to win $10,000.',
		'mysterygood' => 'Pick up that wedge and turn it over...  It\'s '.b().c(1,9).' $10,000 '.r().'! Looks like your gamble paid off.',
		'mysterybad'  => 'Pick up that wedge and turn it over...  It\'s '.b().c(0,1).' BANKRUPT '.r().'.',
		'mysterydecl' => 'Taking the sure $%s?  That\'s perfectly alright.',
		'onemillion'  => 'Pick up that '.b().c(8,3).' ONE MILLION '.r().' dollar wedge.  If you can keep that for the entire game, you\'ll have a chance at winning '.b().'$1,000,000'.r().' in the bonus round!',
		'getbonus'    => 'Pick up that '.b().c(8,3).' $'.c(15)."UPER BONU".c(8).'$ '.r().' wedge.  Hold onto that for the rest of the game, and the prize values in the bonus round will be '.b().'doubled'.r().'!',
		'prize'       => 'Pick up that prize, worth $%s.',
		'wildcard'    => 'Pick up that '.b().c(7,6)." WILD CARD ".r().'.  That lets you use "!wildcard" to guess a second consonant without spinning the wheel again, at the same dollar value.',
		'doubleplay'  => 'Pick up that '.b().c(0,4)." DOUBLE ".c(1).'$$'.c(0)." PLAY ".r().'.  That lets you use "!doubleplay" to spin the wheel and double the value of any cash wedge that you land on.',
		'jackpot'     => 'If you can "!solve" the puzzle now, you could win the '.b().'JACKPOT'.r().' bonus of $%s!',

		'allvgone' => 'No more vowels are left; it\'s spin or solve from here on out.',
		'allcgone' => 'No more spinning; only vowels are left in the puzzle.  Do you know the answer?',
		'allgone'  => 'The entire puzzle is exposed; just go ahead and !solve it now.',

		'solve'       => array(array('Okay, ', 'Alright, ', ''), '%s, you have 20 seconds to solve the puzzle.'),
		'correct'     => '%s is correct!',
		'defaultwin'  => '%s wins by default!',
		'controlwin'  => '%s gains control of the board for the next round.',
		'tiebreakwin' => '%s takes the tiebreaker and will move on to the bonus round.',
		'firstwin'    => '%s is now on the board with $%s.',
		'firstwinp'   => '%s is now on the board with $%s ($%s + $%s in prizes).',
		'win'         => '%s adds $%s to their total, giving them $%s.',
		'winp'        => '%1$s adds $%2$s ($%4$s + $%5$s in prizes) to their total, giving them $%3$s.',
		'prizewin'    => '%s also gains a prize worth $%s for solving the prize puzzle.',
		'jackpotwin'  => '%s also wins the '.b().'JACKPOT'.r().' bonus of $%s!  Congratulations!',
		'solowin'     => '%s gains an extra turn for solving the puzzle successfully.',
		'incorrect'   => array(array('Sorry, ', 'No, '), 'that\'s not correct.'),
		'timeup'      => array(array('Sorry, time', 'Time', 'Oops, time'), array('\'s up.', ' is up.')),

		'delinquentwarn' => b().'WARNING'.r().' - %s: If you remain idle for too much longer you risk losing your turn due to inactivity.',
		'delinquent'     => '%s remained idle for too long and lost their turn.',

		'tiebonus'  => 'Oh dear!  It seems %s are all tied up with a score of $%s.',
		'prebonus'  => '%s is our winner today with $%s, and will go on to play the bonus round puzzle.',
		'solobonus' => 'Congratulations, %s, you have successfully made it to the bonus round with $%s.',

		'bonusstart'   => 'Now it\'s time for the bonus round, where %s can win up to $100,000 extra by solving the bonus puzzle.  We\'ll spin for a prize now, but we won\'t see what it is until the bonus round is over.',
		'superbonus'   => '%s brought the '.b().c(8,3).' $'.c(15)."UPER BONU".c(8).'$ '.r().' wedge up here with us, so all the prize values are doubled; now you can win up to $200,000!  We\'ll spin the bonus wheel for a prize now, but we\'ll find out what it is later.',
		'bonusmillion' => '%s has brought that '.b().c(8,3).' ONE MILLION '.r().' wedge all the way to the bonus round, so the previous top prize has been replaced with '.b().'$1,000,000'.r().'!  We won\'t find out what the prize is just yet, but here\'s hoping for the best.',
		'supermillion' => 'Was it luck, or was it skill, %s?  You\'ve brought both the '.b().c(8,3).' ONE MILLION '.r().' wedge and the '.b().c(8,3).' $'.c(15)."UPER BONU".c(8).'$ '.r().' wedge up here with you, meaning the top prize for you to win is now '.b().'$2,000,000'.r().'!!  We don\'t know if you\'ll get that just yet, but keep your fingers crossed.',
		'bonusinstr1'  => 'You\'re going to get a puzzle, and R S T L N and E will be filled in for you.',
		'bonusinstr2'  => 'Then you\'ll have 30 seconds to give me three more consonants and a vowel.',
		'bonusinstr2w' => 'Then you\'ll have 30 seconds to give me four more consonants (thanks to that '.b().c(7,6)." WILD CARD ".r().' you brought here) and a vowel.',
		'bonusinstr3'  => 'Afterwards, you\'ll have 20 seconds to solve the puzzle.  Now, %s, please make your letter selections.',
		'c0v1'         => 'That\'s the vowel. (%s)',
		'c1v1'         => 'That\'s one and the vowel. (%s)',
		'c2v1'         => 'And one more consonant? (%s)',
		'c1v0'         => 'That\'s one. (%s)',
		'c2v0'         => 'That\'s two. (%s)',
		'c3v0'         => 'And the vowel? (%s)',
		'wc0v1'        => 'That\'s the vowel. (%s)',
		'wc1v1'        => 'That\'s one and the vowel. (%s)',
		'wc2v1'        => 'That\'s two and the vowel. (%s)',
		'wc3v1'        => 'And one more consonant, courtesy of that '.b().c(7,6)." WILD CARD ".r().'? (%s)',
		'wc1v0'        => 'That\'s one. (%s)',
		'wc2v0'        => 'That\'s two. (%s)',
		'wc3v0'        => 'That\'s three. (%s)',
		'wc4v0'        => 'And the vowel? (%s)',
		'bonusquick'   => 'I didn\'t expect you to solve it already, but hey, it works... I guess.',
		'bonuspuzzle'  => 'The puzzle was %s.',
		'bonuswin'     => array(array('Now, let\'s take a look at what you won...', 'Let\'s see what you just won...'), '  %s!'),
		'bonuslose'    => 'Now, unfortunately, we have to see what you didn\'t win...  %s',

		'endsolo'   => '%s has used up all of their turns.',
		'endpuzzle' => 'By the way, the puzzle was %s.',
		'endmoney'  => '%s has finished this game with a total of $%s in winnings.',
		'endtime'   => 'Total game time was %s.  This game has ended; use !fortune to start a new game.',
		);
	}

	//
	// EXPOSED TO THE PUBLIC
	//
	final public function message($msg) {
		irc::message($this->channel, $msg);
	}

	final public function messageOutput($type) {
		$args = func_get_args();
		array_shift($args);
		$this->message(vsprintf($this->getMessage($type), $args));
	}

	final public function getMessage($type) {
		if (isset($this->messages[$type]))
			$base = $this->messages[$type];
		else if (isset($this->defMessages[$type]))
			$base = $this->defMessages[$type];
		else
			return "null";

		// Single string responses
		if (!is_array($base)) return $base;

		// Arrays allow for multiple responses to one type, randomly chosen
		$lines = array();
		foreach($base as $text) {
			if (!is_array($text))
				$lines[] = $text;
			else
				$lines[] = $text[random::key($text)];
		}
		return implode('',$lines);
	}

	final public function messageExists($type) {
		return (isset($this->messages[$type]) || isset($this->defMessages[$type]));
	}

	final public function textHandler(&$data, $command) {
		// Not playing
		if (($id = $this->getContestant($data->nick)) < 0) return;
		if ($this->delaying) return;

		$player = &$this->contestants[$id];

		// Throw it in
		$data->command = $command;

		$f = "{$this->doing[0]}_Handler";
		$this->$f($data,$id,$player);
	}

	final public function whatAmIDoing() {
		$text = '';
		if ($this->waitingForPlayers)
			return "Waiting for players (".count($this->contestants).'/'.$this->playerlimit.')';

		switch($this->doing[0]) {
			case 'speedup':
				$text = 'Speedup -- ';
			case 'round':
				$text .= "Round {$this->round}";
				if ($this->roundtype & 1) $m[] = "Jackpot Round";
				if ($this->roundtype & 2) $m[] = "Prize Puzzle";
				if ($m) $text .= " (".implode(', ', $m).")";
				return $text;
			case 'tossup':
				$money = number_format($this->doing[1]);
				return "\${$money} Toss-up";
			case 'introduce':
				return "Introductions";
			case 'prebonus':
				return "Pre-Bonus Round";
			case 'bonus':
				return "Bonus Round";
			case 'endgame':
				return "Cleanup";
		}
		return "Unknown ({$this->doing[0]})";
	}

	final public function getStandings($overall = false) {
		$podiums = array();

		if ($overall) {
			// Ties are possible, light multiple podiums up in that case
			$litpodiums = array();
			$maxamt = 0;

			foreach ($this->contestants as $k=>$c) {

				if ($c->banked > $maxamt) {
					$maxamt = $c->banked;
					$litpodiums = array($k);
				}
				elseif ($c->banked == $maxamt)
					$litpodiums[] = $k;
			}
		}

		foreach ($this->contestants as $k=>$c)
			$podiums[] = $c->draw($k, ($overall) ? (in_array($k, $litpodiums)) : ($k == $this->turn), $overall);

		if (!$overall && $this->doing[0] == 'round' && ($this->roundtype & 1))
			$podiums[] = c(12,10).'[ '.c(0,10).b().'JACKPOT $'.number_format($this->roundVars['jackpot']).b().c(12,10).' ]'.r();

		return implode('  ',$podiums);
	}

	final public function getLetters() {
		$tx = '';
		foreach ($this->letters as $l) {
			if ($this->isLetterUsed($l)) continue;
			if (in_array($l, $this->vowels)) $tx .= c(4).$l.r();
			else $tx .= $l;
		}
		return $tx;
	}

	final public function doAction($delay = 0) {
		$this->buzzer->stop();
		$this->reminder->stop();

		++$this->action;
		$this->doing = $this->act($this->action);

		consoleDebug("{$this->channel}: New action #{$this->action}: {$this->doing[0]} {$this->doing[1]}");

		// Delay
		if ($delay > 0) {
			$this->delaying = true;
			$this->buzzer->setInterval($delay)->setFunction("handleAction")->start();
		}
		else
			$this->handleAction();
	}

	final public function getContestant($name) {
		// When multidebugging is enabled everyone controls everyone
		if ($this->debugger == DEBUG_MULTI) {
			// Lame hack to allow buzzing in tossups
			if ($this->doing[0] == 'tossup' && $this->turn == -1)
				return count($this->roundVars['buzzedIn']);

			// Lame hack for bonuses
			if ($this->winner >= 0)
				return $this->winner;

			return $this->turn;
		}	

		foreach($this->contestants as $k=>$c) {
			if (strcasecmp($c->name, $name)) continue;
			return $k;
		}
		return -1;
	}

	final public function addContestant($name) {
		// Can't add if not waiting
		if (!$this->waitingForPlayers) return;

		for ($i = 0; $i < $this->playerlimit; ++$i) {
			if (isset($this->contestants[$i])) continue;
			$position = $i;
			break;
		}
		if ($i >= $this->playerlimit) return;

		if ($this->getContestant($name) >= 0) {
			consoleWarn("{$this->channel}: {$name} already in game");
			return;
		}

		$cts = new fortuneContestant();
		$cts->name = $name;
		if (isset(fortune::$ctsdata[strtolower($name)]))
			$cts->ctsdata = &fortune::$ctsdata[strtolower($name)];
		else {
			$cd = new fortuneContestantData();
			$cd->name = $name;
			$cts->ctsdata = &$cd;
			fortune::$ctsdata[strtolower($name)] = &$cd;
		}

		$this->contestants[$position] = &$cts;

		console("{$this->channel}: {$name} added.");
		if ($this->playerlimit == 1) {
			if ($this->debugger != DEBUG_MULTI) // stop spamming
				$this->messageOutput('playersolo', b().$name.r());

			$cts->lives = $this->sololives;
			$this->gameStartup();
		}
		elseif (count($this->contestants) < $this->playerlimit) {
			$left = $this->playerlimit - count($this->contestants);
			if ($this->debugger != DEBUG_MULTI) // stop spamming
				$this->messageOutput('playerjoin', b().$name.r(), getTextNumeral($left), (($left>1)?'s':''));
		}
		else {
			if ($this->debugger != DEBUG_MULTI) // stop spamming
				$this->messageOutput('startfortune', b().$name.r());

			$this->gameStartup();
		}
	}

	final public function removeContestant($name) {
		// Can't remove if not waiting
		if (!$this->waitingForPlayers) return;
		if (($id = $this->getContestant($name)) < 0) return;

		unset($this->contestants[$id]);
		if (count($this->contestants) < 1) $this->candie = true;

		if ($this->candie)
			$this->messageOutput('noplayersleft', b().$name.r());
		else {
			$left = $this->playerlimit - count($this->contestants);
			$this->messageOutput('playerleave', b().$name.r(), getTextNumeral($left), (($left>1)?'s':''));
		}
		console("{$this->channel}: {$name} removed.");
	}

	final public function getBonusPrizeNumber() {
		if ($this->roundVars['bonusForce'])
			return $this->roundVars['bonusForce'] - 1;
		return random::range(0, $this->getNumBonusPrizes() - 1);
	}
	//
	// END PUBLIC
	//

	//
	// PRIVATE FUNCTIONS FOR INNER WORKINGS
	//
	final private function gameStartup() {
		$this->waitingForPlayers = false;
		$this->gamestart = time();

		// sort the array so we don't get [1, 0, 2] keys if joins were out of order
		ksort($this->contestants);

		$cts = array();
		foreach ($this->contestants as $c) {
			$cts[] = b().$c->name.r();
		}
		if (count($cts) < 2)
			$text = "{$cts[0]} is alone today, with {$this->sololives} turns to complete as many puzzles as they can.";
		else
			$text = "Our contestants today are ".arrayToFormalList($cts).".";
		$this->messageOutput('welcome', $text);

		$this->starter = 0;
		$this->wheel = new fortuneWheel();

		$this->doAction(5);
	}

	final private function handleAction() {
		$this->delaying = false;
		$this->roundVars = array(); // Reset

		$type = $this->doing[0];
		$var  = $this->doing[1];

 		if (method_exists($this, ($func = "{$type}_Start")))
 			return $this->$func($var);

		$this->message(b()."ERROR: ".r()."Invalid action detected.  Ending game.");
		$this->candie = true;
	}

	final private function getNewPuzzle($type = P_NORMAL, $exclude = 0) {
		$chosen = -1;
		do {
			$acceptable = array();

			foreach (fortune::$puzzles as $k=>$p) {
				if (!in_array($k, $this->usedpuzzles)
				&& !in_array($k, fortune::$globalusedpuzzles)
				&& ($p->usability & $type) && !($p->usability & $exclude))
					$acceptable[] = $k;
			}

			consoleDebug("{$this->channel}: Acceptable puzzle count: ".count($acceptable));

			if (count($acceptable) <= 0) {
				// fallback -- clear global list
				// (don't clear local so puzzles don't get reused)
				fortune::$globalusedpuzzles = array();
				continue;
			}

			// shuffle = more harm than good?
			//shuffle($acceptable);
			$rand = random::key($acceptable);
			$chosen = $acceptable[$rand];
		} while ($chosen < 0);

		$this->usedpuzzles[] = $chosen;
		fortune::$globalusedpuzzles[] = $chosen;
		while (count(fortune::$globalusedpuzzles) > fortune::$globallistsize)
			array_shift(fortune::$globalusedpuzzles);

		$puzzle = &fortune::$puzzles[$chosen];
		consoleDebug("{$this->channel}: Puzzle used from line #{$puzzle->lineno}");
		$this->puzzle = $puzzle->copy();
	}

	final private function useLetter($guess) {
		$this->roundVars['usedLetters'][] = $guess;
	}

	final private function isLetterUsed($guess) {
		return in_array($guess, (array)$this->roundVars['usedLetters']);
	}

	final private function commonFunctions(&$data) {
		switch(strtolower($data->messageex[0])) {
			case "!scores":
				$this->message("Current standings in round ".getTextNumeral($this->round).":  ".$this->getStandings(false));
				return true;
			case "!overall":
				$this->message("Overall standings:  ".$this->getStandings(true));
				return true;
			case "!wheel":
				$wedge = $this->wheel->getCurrentWedge();
				$placeholder = str_repeat("~", $wedge->reallen);
				$msg = sprintf("Wheel position: %s", $placeholder);
				$this->message($this->wheel->displayWheelTip($msg, $placeholder, $wedge->shorttext));
				$this->message($this->wheel->displayWheel());
				return true;
			case "!turn":
				$turn = b().$this->contestants[$this->turn]->name.r();
				$this->message("It's {$turn}'s turn.");
				return true;
			case "!letters":
				$this->message($this->getLetters());
				return true;
			case "!puzzle":
				$this->message($this->puzzle->getFormattedAll());
				return true;
			}
		return false;
	}

	final private function commonCorrect(&$player) {
		$oldamount = $player->banked;
		$onhand = $player->onhand;

		$player->bank();
		foreach($this->contestants as $c)
			$c->removePrizes();

		$prizes = $player->banked - $oldamount - $onhand;

		// House min winnings
		if (!($this->roundtype & 2) && !($this->roundVars['jackpotSolve']) && $player->banked - $oldamount < $this->houseminimum) {
			$player->banked += $this->houseminimum - ($player->banked - $oldamount);
		}

		$money = number_format($onhand);
		$banked = number_format($player->banked);
		$prizes = number_format($prizes);
		$total = number_format($player->banked - $oldamount);

		$text = sprintf($this->getMessage('correct'), b().$this->puzzle->solved.r())."  ";
		if ($prizes > 0) {
			if ($oldamount == 0) $text .= sprintf($this->getMessage('firstwinp'), b().$player->name.r(), $total, $money, $prizes);
			else                 $text .= sprintf($this->getMessage('winp'), b().$player->name.r(), $total, $banked, $money, $prizes);
		}
		else {
			if ($oldamount == 0) $text .= sprintf($this->getMessage('firstwin'), b().$player->name.r(), $total);
			else                 $text .= sprintf($this->getMessage('win'), b().$player->name.r(), $total, $banked);
		}

		$this->message($text);

		if ($this->roundtype & 2) {
			$ppzl = random::range(1000,10000);
			$player->banked += $ppzl;
			$this->messageOutput('prizewin', b().$player->name.r(), number_format($ppzl));
		}
		if ($this->roundVars['jackpotSolve']) {
			$player->banked += $this->roundVars['jackpot'];
			$this->messageOutput('jackpotwin', b().$player->name.r(), number_format($this->roundVars['jackpot']));
		}

		$this->message("Overall standings:  ".$this->getStandings(true));
	}

	final private function soloTakeLife() {
		if (--$this->contestants[0]->lives <= 0) {
			$this->messageOutput('endsolo',   b().$this->contestants[0]->name.r());
			$this->messageOutput("endpuzzle", b().$this->puzzle->solved.r());
			$this->winner = 0;
			$this->doing = array("endgame",0);

			// Final score set
			$this->contestants[0]->finalscore = $this->contestants[0]->banked;

			$this->buzzer->setInterval(2)->setFunction("handleAction")->start();
			return true;
		}
		return false;
	}
	//
	// END PRIVATE
	//

	//
	// START TOSSUP FUNCTIONS
	//
	final private function tossup_Handler(&$data, $id, &$player) {
		if ($this->gameVars['holdUp']) return;

		// Someone is buzzed in
		if ($this->turn >= 0) {
			if ($this->turn != $id) return;

			// Just so people don't bitch, ignore anything
			// that doesn't start with an alphabetical character
			if (preg_match('/^[a-z]/i', $data->messageex[0]) === FALSE) return;

			$this->tossup_Answer($data, $id, $player);
			return;
		}
		// Rebuzzing
		if (in_array($id, (array)$this->roundVars['buzzedIn'])) return;

		if (in_array($data->messageex[0], array('!', '!solve')))
			$this->tossup_BuzzIn($data, $id, $player);
	}

	final private function tossup_Start($var) {
		if ($this->messageExists("tossup{$var}"))
			$this->messageOutput("tossup{$var}");
		else
			$this->messageOutput("tossup_generic", number_format($var));

		$this->roundVars['buzzedIn'] = array();
		$this->tossup_PuzzleSetup();
	}
	
	final private function tossup_Tiebreaker() {
		$this->messageOutput("tossup_tiebreak");
		$this->roundVars['tiebreak'] = true;

		$this->tossup_PuzzleSetup();
	}

	final private function tossup_PuzzleSetup() {
		$this->getNewPuzzle(P_TOSSUP);
		$this->turn = -1;
		$this->messageOutput("category", b().$this->puzzle->category.r());
		$this->buzzer->setInterval(2)->setFunction("tossup_PuzzleStart")->start();
		$this->gameVars['fromTossup'] = true;
		$this->gameVars['holdUp'] = true;
	}

	final private function tossup_PuzzleStart() {
		console("{$this->channel}: Toss-up has begun.");
		$this->gameVars['holdUp'] = false;
		$this->message($this->puzzle->getFormattedAll());

		$this->buzzer->setInterval(2.5)->setFunction("tossup_Letter")->start();
	}

	final private function tossup_Letter() {
		$ret = $this->puzzle->insertRandomLetter();
		if (!$ret) { // Out of spaces
			if ($this->roundVars['tiebreak'])
				return $this->messageOutput('tossup_slowtie');
			else
				return $this->tossup_PuzzleOver(true);
		}

		$this->message($this->puzzle->getFormattedAll());
		$this->buzzer->setInterval(2.5)->setFunction("tossup_Letter")->start();
	}

	final private function tossup_BuzzIn(&$data, $id, &$player) {
		$this->roundVars['buzzedIn'][] = $id;
		$this->turn = $id;
		if (isset($data->messageex[1]) && strlen($data->messageex[1]) > 0) { // Answer at the same time
			array_shift($data->messageex);
			$this->tossup_Answer($data, $id, $player);
		}
		else {
			$this->messageOutput("solve", b().$player->name.r());
			$this->buzzer->setInterval(23)->setFunction("tossup_TimeUp")->start();
		}
	}

	final private function tossup_LoseTurn($message) {
		$this->turn = -1;
		$this->messageOutput($message);
		if ($this->roundVars['tiebreak']) {
			if (count($this->roundVars['buzzedIn']) >= $this->playerlimit - 1)
				return $this->tossup_DefaultWin();
		}
		else {
			if (count($this->roundVars['buzzedIn']) >= $this->playerlimit)
				return $this->tossup_PuzzleOver(false);
		}
		$this->buzzer->setInterval(1.5)->setFunction("tossup_Letter")->start();
	}


	final private function tossup_TimeUp() {
		$this->tossup_LoseTurn("timeup");
	}

	final private function tossup_Answer(&$data, $id, &$player) {
		$answer = implode(' ', $data->messageex);
		$func = (($this->puzzle->check($answer)) ? "tossup_Correct" : "tossup_Wrong" );
		$this->$func($data, $id, $player);
	}

	final private function tossup_Wrong(&$data, $id, &$player) {
		$this->tossup_LoseTurn("incorrect");
	}
	
	final private function tossup_Correct(&$data, $id, &$player) {
		consoleDebug("{$this->channel}: Toss-up has ended.");
		$this->starter = $id;

		// Tiebreak winner
		if ($this->roundVars['tiebreak'])
			$this->winner = $id;

		$money = number_format($this->doing[1]);
		$banked = number_format($player->banked + (int)$this->doing[1]);

		$text = sprintf($this->getMessage('correct'), b().$this->puzzle->solved.r())."  ";
		if ($this->doing[1] == 0) {
			if ($this->roundVars['tiebreak']) $text .= sprintf($this->getMessage('tiebreakwin'), b().$player->name.r());
			else                              $text .= sprintf($this->getMessage('controlwin'),  b().$player->name.r());
		}
		else {
			if ($player->banked == 0) $text .= sprintf($this->getMessage('firstwin'), b().$player->name.r(), $money);
			else                      $text .= sprintf($this->getMessage('win'),      b().$player->name.r(), $money, $banked);
		}

		if ((int)$this->doing[1] > 0)
			$player->banked += (int)$this->doing[1];
		$this->message($text);

		if ($this->playerlimit == 1) { // Solo Mode
			$this->messageOutput("solowin", b().$player->name.r());
			++$player->lives;
		}

		// Over, go on from here.
		$this->doAction(4);
	}

	// Only used for tiebreaks
	final private function tossup_DefaultWin() {
		consoleDebug("{$this->channel}: Toss-up has ended.");

		$id = 0;
		foreach (array_keys($this->contestants) as $key) {
			if (!in_array($key, $this->roundVars['buzzedIn'])) {
				$id = $key;
				break;
			}
		}

		$this->starter = $id;
		$this->winner = $id;

		$player = &$this->contestants[$id];
		$text  = sprintf($this->getMessage('defaultwin'), b().$player->name.r())."  ";
		$text .= sprintf($this->getMessage('tiebreakwin'), b().$player->name.r());
		$this->message($text);

		$this->messageOutput("endpuzzle", b().$this->puzzle->solved.r());

		// Over, go on from here.
		$this->doAction(4);
	}

	final private function tossup_PuzzleOver($timeUp = false) {
		$this->messageOutput((($timeUp)? 'timeup': 'buzzed')."_tossup", b().$this->puzzle->solved.r());

		consoleDebug("{$this->channel}: Toss-up has ended.");

		// Over, go on from here.
		$this->doAction(4);
	}
	
	//
	// END TOSSUP FUNCTIONS
	//

	//
	// START INTRODUCE FUNCTIONS
	//
	final private function introduce_Handler() {}

	final private function introduce_Start($var) {
		$this->turn = -1;
		$this->messageOutput("introduce", (count($this->contestants) > 1) ? 's' : '');
		$this->buzzer->setInterval(2)->setFunction("introduce_Do")->start();
	}

	final private function introduce_Do() {
		// Sanity check
		if (++$this->turn >= count($this->contestants))
			return $this->doAction(2);

		$dc = false;
		if (fortune::$s->lastwinner[$this->channel] == $this->contestants[$this->turn]->name) $dc = true;
		$this->message($this->contestants[$this->turn]->introduceMe($this->turn, $this->playerlimit, $dc));

		if ($this->turn >= count($this->contestants) - 1)
			return $this->doAction(2);

		$this->buzzer->setInterval(2)->setFunction("introduce_Do")->start();
	}
	//
	// END INTRODUCE FUNCTIONS
	//

	//
	// START ROUND FUNCTIONS
	//
	final private function round_Handler(&$data, $id, &$player) {
		// Commands anyone can use at any time
		if ($data->command) {
			if ($this->commonFunctions($data)) return;
		}

		// Not your turn?
		if ($id != $this->turn) return;

		if ($this->roundVars['mode'] == 'guess') {
			if (strlen($data->messageex[0]) != 1) return;
			$guess = strtoupper($data->messageex[0]);
			if (!in_array($guess, $this->letters)) return;

			$this->round_Guess($player, $guess);
		}
		elseif ($this->roundVars['mode'] == 'solve') {
			// Just so people don't bitch, ignore anything
			// that doesn't start with an alphabetical character
			$guess = strtoupper($data->messageex[0]{0});
			if (!in_array($guess, $this->letters)) return;

			$this->round_Answer($data, $id, $player);
		}
		elseif ($this->roundVars['mode'] == 'mysteryGuess') {
			$accept = strtoupper($data->messageex[0]{0});
			if ($accept == 'Y')
				$this->round_MysteryAccept();
			else if ($accept == 'N')
				$this->round_MysteryDecline();
		}
		elseif ($this->roundVars['mode'] == 'control' && $data->command) {
			switch(strtolower($data->messageex[0])) {
				case "!s":
				case "!spin":
					if ($this->roundVars['noConsonants'])
						$this->message("You can't spin the wheel because there are no consonants left on the board.");
					else
						$this->round_Spin($data,$id,$player);
					return;
				case "!d":
				case "!dp":
				case "!doubleplay":
					if ($this->roundVars['noConsonants'])
						$this->message("You can't use a ".b().c(0,4)." DOUBLE ".c(1).'$$'.c(0)." PLAY ".r()." because there are no consonants left on the board.");
					else
						$this->round_DoublePlay($data,$id,$player);
					return;
				case "!w":
				case "!wc":
				case "!wildcard":
					if ($this->roundVars['noConsonants'])
						$this->message("You can't use a ".b().c(7,6)." WILD CARD ".r()." because there are no consonants left on the board.");
					else
						$this->round_WildCard($data,$id,$player);
					return;
				case "!b":
				case "!buy":
					if ($this->roundVars['noVowels'])
						$this->message("You can't buy a vowel; all the vowels have been revealed already.");
					else
						$this->round_Buy($data,$id,$player);
					return;
				case "!solve":
				case "!v":
					$this->round_Solve($data,$id,$player);
					return;
			}
		}
	}

	final private function round_Start($var) {
		$this->setupWheel($this->wheel, ++$this->round);

		if ($var & 1) $ptype = P_JACKPOT;
		else          $ptype = P_NORMAL;

		$pexcl = 0;
		if (!($var & 2) || $this->gameVars['prizePuzzleDone'])
			$pexcl = P_PRIZE;

		$this->getNewPuzzle($ptype, $pexcl);

		$this->roundtype  = ($var & 1);
		$this->roundtype |= (($this->puzzle->usability & P_PRIZE) ? 2 : 0);

		if ($this->roundtype & 2)
			$this->gameVars['prizePuzzleDone'] = true;

		console("{$this->channel}: Round {$this->round} has begun.");
		$turn = b().$this->contestants[$this->starter]->name.r();
		$this->turn = $this->starter;

		foreach($this->contestants as $c)
			$c->onhand = 0;

		if ($this->messageExists("round{$this->round}"))
			$this->messageOutput("round{$this->round}", $turn);
		elseif ($this->gameVars['fromTossup'] && $this->messageExists("round_fromtossup"))
			$this->messageOutput("round_fromtossup", $turn, getTextNumeral($this->round));
		else
			$this->messageOutput("round_generic", $turn, getTextNumeral($this->round));

		$this->messageOutput("category", b().$this->puzzle->category.r());

		if ($this->roundtype & 2)
			$this->messageOutput('prizepuzzle');

		$this->message($this->puzzle->getFormattedAll());

		$this->gameVars['fromTossup'] = false;
		$this->roundVars['noVowels'] = false;
		$this->roundVars['noConsonants'] = false;
		$this->roundVars['jackpot'] = 5000;

		if ($this->speedup_Check()) return;

		$this->roundVars['mode'] = 'control';
		$this->roundVars['disableWild'] = true;
 		$this->roundVars['jackpotSolve'] = false;

		$this->reminder->setInterval(110)->setFunction("round_DelinquencyReminder")->start();
		$this->buzzer->setInterval(130)->setFunction("round_DelinquencyStrike")->start();
	}

	// Go to another person
	final private function round_StartNewTurn($first = false) {
		if ($this->playerlimit == 1) {
			// Check for solo gameover
			if ($this->soloTakeLife()) return;
		}
		else {
			++$this->turn;
			$this->turn %= $this->playerlimit;
		}
		$turn = b().$this->contestants[$this->turn]->name.r();

		$this->message("Current standings in round ".getTextNumeral($this->round).":  ".$this->getStandings());
		$this->messageOutput("turn_new", $turn);
		if ($this->speedup_Check()) return;

		$this->roundVars['mode'] = 'control';
		$this->roundVars['disableWild'] = true;
 		$this->roundVars['jackpotSolve'] = false;

		$this->reminder->setInterval(110)->setFunction("round_DelinquencyReminder")->start();
		$this->buzzer->setInterval(130)->setFunction("round_DelinquencyStrike")->start();
	}

	// Remain on this person
	final private function round_ContinueTurn() {
		$player = &$this->contestants[$this->turn];

		$turn = b().$player->name.r();
		$money = number_format($player->onhand);

		$tx = sprintf($this->getMessage("turn_continue"), $turn, $money);
		if (!$this->roundVars['disableWild'] && $player->getCard('wildCard') > -1
		 && !$this->roundVars['noConsonants']) {
			$wedge = $this->wheel->getLandedWedge();
			if ($wedge->type == 'cash' && $wedge->value >= 900)
				$tx .= '  '.$this->getMessage("wildcard_remind");
		}

		$this->message($tx);
		if ($this->speedup_Check()) return;
		$this->roundVars['mode'] = 'control';

		$this->reminder->setInterval(50)->setFunction("round_DelinquencyReminder")->start();
		$this->buzzer->setInterval(70)->setFunction("round_DelinquencyStrike")->start();
	}

	final private function round_Spin(&$data, $id, &$player, $mult = 1, $accept = NULL) {
		$this->roundVars['jackpotSolve'] = false;
		$this->reminder->stop();
		$canguess = true;

		if ($this->debugger && is_numeric($data->messageex[1])) {
			$spinamt = (int) $data->messageex[1];
			$this->wheel->spin($spinamt);
			$wedge = $this->wheel->getCurrentWedge();
		}
		else do {
			$this->wheel->spin(random::range(1428, 3612)); // 1008 - 42,(1008*3)+42
			$wedge = $this->wheel->getCurrentWedge();
		} while ($accept && !in_array($wedge->type, $accept));

		$dptext = "";
		if ($mult != 1 && $wedge->type == 'cash') {
			$doublevalue = number_format($wedge->value * $mult);
			$dptext = " (\${$doublevalue})";
		}

		if ($this->showwheel) {
			$placeholder = str_repeat("~", $wedge->reallen+2);
			$msg = sprintf("%s spins %s%s.", b().$player->name.r(), $placeholder, $dptext);
			$this->message($this->wheel->displayWheelTip($msg, $placeholder, $wedge->shorttext));
			$this->message($this->wheel->displayWheel());
		}
		else
			$text = sprintf("%s spins %s%s.  ", b().$player->name.r(), $wedge->shorttext, $dptext);

		if ($wedge->type == "bankrupt") {
			$canguess = false;
			$player->bankrupt();
		}
		elseif ($wedge->type == "loseATurn")
			$canguess = false;

		if ($canguess) {
			$this->roundVars['mode'] = 'guess';
			$this->roundVars['guessWorth'] = $wedge->value * $mult;

			if ($wedge->type == 'freePlay') {
				$this->roundVars['guessType'] = GUESS_ALL|GUESS_NORISK;
				$lettertx = $this->getMessage('freeplay');
			}
			else {
				if ($wedge->type != 'cash')
					$this->roundVars['guessWorth'] = 0;
				$this->roundVars['guessType'] = GUESS_CONSONANT;
				$lettertx = $this->getMessage('consonant');
			}

			if ($this->showwheel)
				$this->message($lettertx);
			else
				$text .= $lettertx;

			$this->buzzer->setInterval(23)->setFunction("round_GuessTimeUp")->start();
		}
		else {
			$this->roundVars['mode'] = 'idle';
			$this->buzzer->setInterval(2)->setFunction("round_StartNewTurn")->start();
		}
		if (!$this->showwheel)
			$this->message($text);
	}

	final private function round_DoublePlay(&$data, $id, &$player) {
		if (($card = $player->getCard('doublePlay')) < 0) {
			$this->messageOutput("nocard");
			return;
		}

		$this->messageOutput("doubleplay_use");
		unset($player->cards[$card]);

		$this->round_Spin($data,$id,$player,2,array('cash','loseATurn','bankrupt'));
	}

	final private function round_WildCard(&$data, $id, &$player) {
		if (($card = $player->getCard('wildCard')) < 0) {
			$this->messageOutput("nocard");
			return;
		}
		$wedge = $this->wheel->getLandedWedge();
		if ($wedge->type != 'cash' || $this->roundVars['disableWild']) {
			$this->messageOutput("badspace");
			return;
		}

		if ($data->messageex[1] && strlen($data->messageex[1]) == 1) {
			$guess = strtoupper($data->messageex[1]);
			if (in_array($guess, $this->vowels)) {
				$this->message("{$guess} isn't a consonant.  Try again.");
				return;
			}
			if (!in_array($guess, $this->consonants))
				$guess = NULL;
		}

		$this->roundVars['guessType'] = GUESS_CONSONANT|GUESS_DIDNTSPIN;
		$this->roundVars['guessWorth'] = $wedge->value;

		if ($guess != NULL)
			return $this->round_Guess($player, $guess);

		$this->roundVars['mode'] = 'guess';
		$this->message($this->getMessage("wildcard_use") . '  ' . $this->getMessage('consonant'));
		unset($player->cards[$card]);
		$this->buzzer->setInterval(23)->setFunction("round_GuessTimeUp")->start();
		$this->reminder->stop();
	}

	final private function round_Buy(&$data, $id, &$player) {
		if ($player->onhand < $this->vowelCost) {
			$this->messageOutput('buy_notenough');
			return;
		}
		if ($data->messageex[1] && strlen($data->messageex[1]) == 1) {
			$guess = strtoupper($data->messageex[1]);
			if (in_array($guess, $this->consonants)) {
				$this->message("{$guess} isn't a vowel.  Try again.");
				return;
			}
			if (!in_array($guess, $this->vowels))
				$guess = NULL;
		}

		$this->roundVars['guessType'] = GUESS_VOWEL|GUESS_DIDNTSPIN;
		$this->roundVars['guessWorth'] = 0;
		$this->roundVars['jackpotSolve'] = false;
		$player->onhand -= $this->vowelCost;

		if ($guess != NULL)
			return $this->round_Guess($player, $guess);

		$this->roundVars['mode'] = 'guess';
		$this->message($this->getMessage('buy').'  '.$this->getMessage('vowel'));
		$this->buzzer->setInterval(23)->setFunction("round_GuessTimeUp")->start();
		$this->reminder->stop();
	}

	final private function round_Guess(&$player, $guess) {
		$isvowel = in_array($guess, $this->vowels);

		if ($isvowel && !($this->roundVars['guessType'] & GUESS_VOWEL)) {
			$this->message("{$guess} isn't a consonant.  Try again.");
			return;
		}
		if (!$isvowel && !($this->roundVars['guessType'] & GUESS_CONSONANT)) {
			$this->message("{$guess} isn't a vowel.  Try again.");
			return;
		}

		$this->roundVars['mode'] = 'idle';
		$this->reminder->stop();
		$wedge = $this->wheel->getCurrentWedge();

		$worth = $this->roundVars['guessWorth']; // Default
		if ($wedge->type == 'freePlay' && $isvowel)
			$worth = 0;
		elseif ($wedge->type == 'mystery' && $this->roundVars['mysteryDone'] && !($this->roundVars['guessType'] & GUESS_DIDNTSPIN))
			$worth = 1000;

		$this->roundVars['jackpot'] += $worth;

		if ($this->isLetterUsed($guess)) {
			$this->message("{$guess} was already used.  Too bad.  (Remaining letters: ".$this->getLetters().")");
			if ($this->roundVars['guessType'] & GUESS_NORISK) {
				$this->messageOutput('freemiss');
				$this->round_ContinueTurn();
				return;
			}
			$this->buzzer->setInterval(2)->setFunction("round_StartNewTurn")->start();
			return;
		}

		$this->useLetter($guess);

		if ($this->roundVars['guessType'] & GUESS_DIDNTSPIN)
			$this->roundVars['disableWild'] = true;
		elseif ($wedge->type == 'cash')
			$this->roundVars['disableWild'] = false;

		$num = $this->puzzle->insertLetter($guess);
		if ($num <= 0) {
			$this->message(sprintf($this->getMessage('noletter'), $guess));
			if ($this->roundVars['guessType'] & GUESS_NORISK) {
				$this->messageOutput('freemiss');
				$this->round_ContinueTurn();
				return;
			}
			$this->buzzer->setInterval(2)->setFunction("round_StartNewTurn")->start();
			return;
		}
		else {
			$text = sprintf($this->getMessage('letter'.(($num > 1)?'s':'')), $guess, getTextNumeral($num));
			if ($worth) {
				$player->onhand += $worth*$num;
				$text .= '  '.sprintf($this->getMessage('worth'), number_format($worth*$num));
			}

			if ($this->roundVars['guessType'] & GUESS_DIDNTSPIN); // don't pick up prizes unless you JUST landed on them
			elseif ($wedge->type == 'mystery' && !$this->roundVars['mysteryDone']) {
				$realworth = 1000*$num;
				if ($realworth < 10000) {
					$this->roundVars['mode'] = 'mysteryGuess';
					$this->roundVars['mysteryWorth'] = $realworth;
					$this->buzzer->setInterval(18)->setFunction("round_MysteryDecline")->start();
					$text .= '  '.sprintf($this->getMessage('mystery'), number_format($realworth));
				}
				else {
					$text .= '  '.sprintf($this->getMessage('mystery10k'), number_format($realworth));
					$player->onhand += $realworth;
				}
			}
			elseif ($wedge->type == 'jackpot') {
				$this->roundVars['jackpotSolve'] = true;
				$text .= '  '.sprintf($this->getMessage('jackpot'), number_format($this->roundVars['jackpot']));
			}
			elseif ($wedge->type == 'superBonus') {
				$text .= '  '.$this->getMessage('getbonus');
				$player->cards[] = new fortuneCard('superBonus');

				$this->wheel->toggleSpaces(-1, 2);
			}
			elseif ($wedge->type == 'oneMillion') {
				$text .= '  '.$this->getMessage('onemillion');
				$player->cards[] = new fortuneCard('oneMillion');

				$this->wheel->toggleSpaces(-1, 2);
			}
			elseif ($wedge->type == 'wildCard') {
				$text .= '  '.$this->getMessage('wildcard');
				$player->cards[] = new fortuneCard('wildCard');

				$this->wheel->toggleSpaces();
			}
			elseif ($wedge->type == 'doublePlay') {
				$text .= '  '.$this->getMessage('doubleplay');
				$player->cards[] = new fortuneCard('doublePlay');

				$this->wheel->toggleSpaces();
			}
			elseif ($wedge->type == 'prize') {
				$text .= '  '.sprintf($this->getMessage('prize'), number_format($wedge->value));
				$card = new fortuneCard('prize');
				$card->value = $wedge->value;
				$card->display = b().$wedge->getColorCode().'P'.r();
				$player->cards[] = &$card;

				$this->wheel->toggleSpaces();
			}

			$this->message($text);
			$this->message($this->puzzle->getFormattedAll($guess));
			$this->round_NoLetterText();
			if ($this->roundVars['mode'] == 'idle')
				$this->round_ContinueTurn();
		}
	}

	final private function round_MysteryAccept() {
		$player = &$this->contestants[$this->turn];
		$wedge = $this->wheel->getLandedWedge();
		$winnar = ($wedge->value == 1);
		$this->roundVars['mysteryDone'] = true;

		$this->wheel->toggleSpaces();

		if ($winnar) {
			$this->messageOutput('mysterygood');
			$card = new fortuneCard('prize');
			$card->value = 10000;
			$card->display = b().c(1,9).'$'.r();
			$player->cards[] = &$card;
			$this->round_ContinueTurn();
		}
		else { // LOSAR
			$this->messageOutput('mysterybad');
			$player->bankrupt();
			$this->roundVars['mode'] = 'idle';
			$this->buzzer->setInterval(2)->setFunction("round_StartNewTurn")->start();
		}
	}

	final private function round_MysteryDecline() {
		$player = &$this->contestants[$this->turn];
		$player->onhand += $this->roundVars['mysteryWorth'];
		$this->messageOutput('mysterydecl', number_format($this->roundVars['mysteryWorth']));

		$this->round_ContinueTurn();
	}

	final private function round_Solve(&$data, $id, &$player) {
		if (isset($data->messageex[1]) && strlen($data->messageex[1]) > 0) { // Answer at the same time
			array_shift($data->messageex);
			$this->round_Answer($data, $id, $player);
		}
		else {
			$this->reminder->stop();
			$this->roundVars['mode'] = 'solve';
			$this->messageOutput("solve", b().$player->name.r());

			$this->buzzer->setInterval(23)->setFunction("round_GuessTimeUp")->start();
		}
	}

	final private function round_Answer(&$data, $id, &$player) {
		$answer = implode(' ', $data->messageex);
		$func = (($this->puzzle->check($answer)) ? "round_Correct" : "round_Wrong" );
		$this->$func($data, $id, $player);
	}

	final private function round_Wrong(&$data, $id, &$player) {
		$this->messageOutput("incorrect");
		$this->roundVars['mode'] = 'idle';
		$this->buzzer->setInterval(2)->setFunction("round_StartNewTurn")->start();
	}

	final private function round_Correct(&$data, $id, &$player) {
		consoleDebug("{$this->channel}: Round has ended.");

		++$this->starter;
		$this->starter %= $this->playerlimit;

		$this->commonCorrect($player);

		// Over, go on from here.
		$this->doAction(4);
	}

	final private function round_NoLetterText() {
		$oldnv = $this->roundVars['noVowels'];
		$oldnc = $this->roundVars['noConsonants'];

		$this->roundVars['noVowels'] = !$this->puzzle->anyLettersLeft(1);
		$this->roundVars['noConsonants'] = !$this->puzzle->anyLettersLeft(0);

		if ($oldnv == $this->roundVars['noVowels'] && $oldnc == $this->roundVars['noConsonants'])
			return;

		if ($this->roundVars['noVowels'] && $this->roundVars['noConsonants'])
			$this->messageOutput('allgone');
		elseif ($this->roundVars['noConsonants'])
			$this->messageOutput('allcgone');
		elseif ($this->roundVars['noVowels'])
			$this->messageOutput('allvgone');
	}

	final private function round_GuessTimeUp() {
		$this->messageOutput("timeup");
		$this->roundVars['mode'] = 'idle';
		$this->buzzer->setInterval(2)->setFunction("round_StartNewTurn")->start();
	}

	final private function round_DelinquencyReminder() {
		if ($this->debugger) return;
		$turn = b().$this->contestants[$this->turn]->name.r();
		$this->messageOutput('delinquentwarn', $turn);
	}

	final private function round_DelinquencyStrike() {
		if ($this->debugger) return;
		$turn = b().$this->contestants[$this->turn]->name.r();
		$this->messageOutput("delinquent", $turn);
		$this->roundVars['mode'] = 'idle';
		$this->buzzer->setInterval(2)->setFunction("round_StartNewTurn")->start();
	}
	//
	// END ROUND FUNCTIONS
	//

	//
	// BEGIN SPEEDUP FUNCTIONS
	//
	final private function speedup_Handler(&$data, $id, &$player) {
		// Commands anyone can use at any time
		if ($data->command) {
			if ($this->commonFunctions($data)) return;
		}

		// Not your turn?
		if ($id != $this->turn) return;

		if ($this->roundVars['mode'] == 'guess') {
			if (strlen($data->messageex[0]) != 1) return;
			$guess = strtoupper($data->messageex[0]);
			if (!in_array($guess, $this->letters)) return;

			$this->speedup_Guess($player, $guess);
		}
		elseif ($this->roundVars['mode'] == 'solve') {
			// Just so people don't bitch, ignore anything
			// that doesn't start with an alphabetical character
			$guess = strtoupper($data->messageex[0]{0});
			if (!in_array($guess, $this->letters)) return;

			$this->speedup_Answer($data, $id, $player);
		}
	}

	final private function speedup_Check() {
		$speedup = false;
		if ($this->timeuntilbell > 0 && time() - $this->gamestart > $this->timeuntilbell) $speedup = true;
		else if ($this->roundsuntilbell > 0 && $this->round >= $this->roundsuntilbell) $speedup = true;

		if (!$speedup) return false;

		// Oops, that's the bell!
		$this->reminder->stop();
		$this->buzzer->stop();

		$this->messageOutput("finalspin");
		$this->action = 9999;
		$this->doing[0] = 'speedup';
		$this->roundVars['mode'] = 'idle';
		$this->roundVars['jackpotSolve'] = false;

		$this->speedup_FinalSpin(false);
		return true;
	}

	final private function speedup_FinalSpin($botched = true) {
		$turn = b().$this->contestants[$this->turn]->name.r();
		$message = (($botched) ? 'speedup_botch2' : 'speedup_instr1');
		$this->messageOutput($message, $turn);

		$accept = array('cash');
		if (!$botched) $accept[] = 'bankrupt';

		do {
			$this->wheel->spin(random::range(1008,1008*8));
			$wedge = $this->wheel->getCurrentWedge();
		} while (!in_array($wedge->type, $accept));

		if ($this->showwheel) {
			$placeholder = str_repeat("~", $wedge->reallen);
			$msg = sprintf("Final spin is %s.", $placeholder);
			$this->message($this->wheel->displayWheelTip($msg, $placeholder, $wedge->shorttext));
			$this->message($this->wheel->displayWheel());
		}
		else
			$this->message(sprintf("Final spin is %s.", $wedge->shorttext));

		if ($wedge->type == 'bankrupt') {
			$this->messageOutput('speedup_botch1');
			$this->buzzer->setInterval(4)->setFunction("speedup_FinalSpin");
			$this->buzzer->start();
		}
		else {
			$this->roundVars['speedupMoney'] = $wedge->value + 1000;
			$this->messageOutput('speedup_instr2', number_format($this->roundVars['speedupMoney']), $turn);

			$this->roundVars['mode'] = 'guess';
			$this->buzzer->setInterval(12)->setFunction("speedup_GuessTimeUp")->start();
		}
	}

	final private function speedup_StartNewTurn() {
		if ($this->playerlimit == 1) {
			// Check for solo gameover
			if ($this->soloTakeLife()) return;
		}
		else {
			++$this->turn;
			$this->turn %= $this->playerlimit;
		}
		$turn = b().$this->contestants[$this->turn]->name.r();

		$tx = "Current standings in round ".getTextNumeral($this->round).":  ".$this->getStandings();

		if (!$this->puzzle->anyLettersLeft(-1)) {
			$this->message($tx);
			$this->messageOutput("speedup_nolett");

			$turn = b().$this->contestants[$this->turn]->name.r();
			$money = number_format($this->contestants[$this->turn]->onhand);
			$this->messageOutput("speedup_solve", $turn, $money);
			$this->roundVars['mode'] = 'solve';

			$this->buzzer->setInterval(23)->setFunction("speedup_GuessTimeUp")->start();
			return;
		}

		$tx .= ' -- '. sprintf($this->getMessage("speedup_pick"), $turn);
		$this->message($tx);
		$this->roundVars['mode'] = 'guess';

		$this->buzzer->setInterval(12)->setFunction("speedup_GuessTimeUp")->start();
	}

	final private function speedup_Guess(&$player, $guess) {
		$isvowel = in_array($guess, $this->vowels);

		$this->reminder->stop();

		$worth = $this->roundVars['speedupMoney']; // Default
		if ($isvowel) $worth = 0; // Vowels never give money

		if ($this->isLetterUsed($guess)) {
			$this->message("{$guess} was already used.  Too bad.  (Remaining letters: ".$this->getLetters().")");
			$this->roundVars['mode'] = 'idle';
			$this->buzzer->setInterval(2)->setFunction("speedup_StartNewTurn")->start();
			return;
		}

		$this->useLetter($guess);

		$num = $this->puzzle->insertLetter($guess);
		if ($num <= 0) {
			$this->messageOutput('noletter', $guess);
			$this->roundVars['mode'] = 'idle';
			$this->buzzer->setInterval(2)->setFunction("speedup_StartNewTurn")->start();
			return;
		}
		else {
			$text = sprintf($this->getMessage('letter'.(($num > 1)?'s':'')), $guess, getTextNumeral($num));
			if ($worth) {
				$player->onhand += $worth*$num;
				$text .= '  '.sprintf($this->getMessage('worth'), number_format($worth*$num));
			}
			else
				$text .= '  '.$this->getMessage('nomoneyvowels');

			$this->message($text);
			$this->message($this->puzzle->getFormattedAll($guess));

			$turn = b().$this->contestants[$this->turn]->name.r();
			$money = number_format($this->contestants[$this->turn]->onhand);
			$this->messageOutput("speedup_solve", $turn, $money);
			$this->roundVars['mode'] = 'solve';

			$this->buzzer->setInterval(23)->setFunction("speedup_GuessTimeUp")->start();
		}
	}

	final private function speedup_Answer(&$data, $id, &$player) {
		$answer = implode(' ', $data->messageex);
		$func = (($this->puzzle->check($answer)) ? "speedup_Correct" : "speedup_Wrong" );
		$this->$func($data, $id, $player);
	}

	final private function speedup_Wrong(&$data, $id, &$player) {
		$this->messageOutput("incorrect");
		$this->roundVars['mode'] = 'idle';
		$this->buzzer->setInterval(2)->setFunction("speedup_StartNewTurn")->start();
	}

	final private function speedup_Correct(&$data, $id, &$player) {
		consoleDebug("{$this->channel}: Speedup round has ended.");

		$this->commonCorrect($player);

		// Over, go on from here.
		$this->doAction(4);
	}

	final private function speedup_GuessTimeUp() {
		$this->messageOutput("timeup");
		$this->roundVars['mode'] = 'idle';
		$this->buzzer->setInterval(1)->setFunction("speedup_StartNewTurn")->start();
	}
	//
	// END SPEEDUP FUNCTIONS
	//


	//
	// START PREBONUS FUNCTIONS
	//
	final private function prebonus_Handler() {}
	final private function prebonus_Start($var) {
		$this->turn = -1;

		$text = "prebonus";
		if ($this->playerlimit == 1) {
			$text = "solobonus";
			$this->winner = 0;
		}
		elseif ($this->winner >= 0); // Winner is already defined
		else {
			$winners = array();
			$winsum = 0;

			foreach ($this->contestants as $k=>$c) {
				if ($c->banked > $winsum) {
					$winners = array($k);
					$winsum = $c->banked;
				}
				elseif ($c->banked == $winsum)
					$winners[] = $k;
			}
			
			if (count($winners) > 1) // Tied game, go to tiebreak
				return $this->prebonus_Tie($winners);
			$this->winner = $winners[0];
		}

		// Set final scores
		foreach ($this->contestants as $c)
			$c->finalscore = $c->banked;

		$turn = b().$this->contestants[$this->winner]->name.r();
		$money = number_format($this->contestants[$this->winner]->banked);
		$this->messageOutput($text, $turn, $money);

		$this->doAction(6);
	}
	
	final private function prebonus_Tie($who) {
		$this->action = 9999;
		$this->doing = array("tossup", 0);
		
		$cts = array();
		$score = 0;
		foreach ($who as $ctid) {
			$cts[] = b().$this->contestants[$ctid]->name.r();
			$score = $this->contestants[$ctid]->banked;
		}
		$this->messageOutput('tiebonus', arrayToFormalList($cts), number_format($score));

		// Lazy way: just set the losers to already buzzed in status
		foreach (array_keys($this->contestants) as $key) {
			if (!in_array($key, $who))
				$this->roundVars['buzzedIn'][] = $key;
		}

		$this->buzzer->setInterval(3.5)->setFunction("tossup_Tiebreaker")->start();
	}
	//
	// END PREBONUS FUNCTIONS
	//

	//
	// START BONUS FUNCTIONS
	//
	final private function bonus_Handler(&$data, $id, &$player) {
		// You outliers didn't win the right to play the bonus!
		if ($id != $this->winner) return;

		if ($this->roundVars['mode'] == 'debug') {
			$force = (int)$data->messageex[0];
			if ($force < 1 || $force > $this->getNumBonusPrizes())
				return;

			$this->roundVars['bonusForce'] = $force;
			$this->bonus_Setup($player);
		}
		elseif ($this->roundVars['mode'] == 'give') {
			if (strlen($data->messageex[0]) < 1)
				return;

			$mine = strtoupper(implode('', $data->messageex));
			$this->bonus_GiveLetters($player, $mine);
		}
		elseif ($this->roundVars['mode'] == 'solve')
			$this->bonus_Answer($data, $id, $player);
	}

	final private function bonus_Start($var) {
		$player = &$this->contestants[$this->winner];
		if ($this->debugger) {
			$this->roundVars['mode'] = 'debug';

			$max = $this->getNumBonusPrizes();
			$this->message(b().$player->name.r().", please pick a prize number... [1-{$max}]");
			return;
		}

		$this->bonus_Setup($player);
	}

	final private function bonus_Setup(&$player) {
		$this->getNewPuzzle(P_BONUS);
		console("{$this->channel}: Bonus round started.");

		$turn = b().$player->name.r();
		$this->roundVars['bonusWildCard'] = false;
		$this->roundVars['bonusLetters'] = array();

		$this->messageOutput($this->getBonusRoundText($player), $turn);
		$this->bonusprize = $this->getBonusPrize($player);

		$tx = $this->getMessage('bonusinstr1');

		$ins2 = 'bonusinstr2';
		if ($player->getCard('wildCard') > -1) { // brought a wild card, +1 consonant
			$this->roundVars['bonusWildCard'] = true;
			$ins2 .= 'w';
		}
		$tx .= '  '.$this->getMessage($ins2);
		$this->message($tx);

		$this->messageOutput('bonusinstr3', $turn);

		$this->bonus_FillInBaseLetters();

		$this->roundVars['mode'] = 'give';
		$this->buzzer->setInterval(33)->setFunction("bonus_LettersTimeUp")->start();
	}

	final private function bonus_GiveLetters(&$player, $given) {
		$base    = str_split($this->baseLetters);

		$numconsonants = $numvowels = 0;
		foreach ($this->roundVars['bonusLetters'] as $bl) {
			if (in_array($bl, $this->vowels)) ++$numvowels;
			else                              ++$numconsonants;
		}

		$playergiven = array();
		foreach (str_split($given) as $l) {
			if (in_array($l, $this->vowels)) ++$numvowels;
			else                             ++$numconsonants;

			if (!in_array($l, $this->letters)) {
				$this->message("{$l} isn't a letter.  Try again.");
				return;
			}
			else if ($numvowels > 1) {
				$this->message("That's too many vowels.  Try again.");
				return;
			}
			else if ($numconsonants > 4 || ($numconsonants > 3 && !$this->roundVars['bonusWildCard'])) {
				$this->message("That's too many consonants.  Try again.");
				return;
			}
			else if (in_array($l, $base)) {
				$this->message("{$this->baseLetters} are already given to you.  Try again.");
				return;
			}
			else if (in_array($l, $this->roundVars['bonusLetters']) || in_array($l, $playergiven)) {
				$this->message("You already picked {$l}.  Try again.");
				return;
			}

			$playergiven[] = $l;
		}

		// NOW we actually add them
		foreach($playergiven as $pl)
			$this->roundVars['bonusLetters'][] = $pl;

		if (($numconsonants == 4 || ($numconsonants == 3 && !$this->roundVars['bonusWildCard']))
		&&   $numvowels == 1) {
			$this->bonus_FillInUserLetters();
		}
		else {
			$string = "c{$numconsonants}v{$numvowels}";
			if ($this->roundVars['bonusWildCard'])
				$string = "w{$string}";
			$ourletters = implode('', $this->roundVars['bonusLetters']);
			$this->messageOutput($string, $ourletters);
		}
	}


	final private function bonus_LettersTimeUp() {
		foreach ($this->roundVars['bonusLetters'] as $bl) {
			if (in_array($bl, $this->vowels)) ++$numvowels;
			else                              ++$numconsonants;
		}

		$tx = $this->getMessage("timeup");
		$tx .= '  Filling in with default letter selections: ';

		foreach(str_split($this->defaultLetters) as $dl) {
			if (in_array($dl, $this->roundVars['bonusLetters'])) continue;

			if (in_array($dl, $this->vowels)) {
				if ($numvowels >= 1) continue;
				++$numvowels;
			}
			else {
				if ($numconsonants >= 4 || ($numconsonants >= 3 && !$this->roundVars['bonusWildCard'])) continue;
				++$numconsonants;
			}

			$tx .= $dl;
			$this->roundVars['bonusLetters'][] = $dl;
		}
		$this->message($tx);
		$this->bonus_FillInUserLetters();
	}

	final private function bonus_FillInBaseLetters() {
		foreach (str_split($this->baseLetters) as $c)
			$this->puzzle->insertLetter($c);

		$this->message($this->puzzle->getFormattedAll() .r(). " ({$this->baseLetters})");
	}

	final private function bonus_FillInUserLetters() {
		$turn = b().$this->contestants[$this->winner]->name.r();

		foreach ($this->roundVars['bonusLetters'] as $c)
			$this->puzzle->insertLetter($c);

		$myletters = implode('', $this->roundVars['bonusLetters']);

		$this->messageOutput("solve", $turn);
		$this->message($this->puzzle->getFormattedAll() .r()." ({$this->baseLetters} {$myletters})");

		$this->roundVars['mode'] = 'solve';
		$this->buzzer->setInterval(23)->setFunction("bonus_TimeUp")->start();
	}

	final private function bonus_Answer(&$data, $id, &$player) {
		$answer = implode(' ', $data->messageex);

		// No penalty for wrong answers.
		if ($this->puzzle->check($answer))
			$this->bonus_Correct($data, $id, $player);
	}

	final private function bonus_Correct(&$data, $id, &$player) {
		consoleDebug("{$this->channel}: Bonus round has ended.");

		$this->messageOutput('correct', b().$this->puzzle->solved.r());
		$this->messageOutput('bonuswin', $this->bonusprize->display);
		$player->finalscore += $this->bonusprize->value;

		// Over, go on from here.
		$this->doAction(2);
	}

	final private function bonus_TimeUp() {
		consoleDebug("{$this->channel}: Bonus round has ended.");

		$tx = $this->getMessage("timeup");
		$tx .= ' '.sprintf($this->getMessage("bonuspuzzle"), b().$this->puzzle->solved.r());

		$this->message($tx);
		$this->messageOutput('bonuslose', $this->bonusprize->display);

		// Over, go on from here.
		$this->doAction(2);
	}
	//
	// END BONUS FUNCTIONS
	//

	//
	// START ENDGAME FUNCTIONS
	//
	final private function endgame_Start($var) {
		console("{$this->channel}: Game ending naturally.");
		$winner = $this->contestants[$this->winner];
		$money = number_format($winner->banked);
		$this->messageOutput("endmoney", b().$winner->name.r(), $money);

		$time = getTextTime(time() - $this->gamestart);
		$this->messageOutput("endtime", b().$time.r());

		$this->endgame_Save();
		$this->candie = true;
	}

	final private function endgame_Handler() {}
	final private function endgame_Save() {
		if ($this->nostats) return;
		$stt = &fortune::$s;

		foreach($this->contestants as $k=>$c) {
			$ctsdata = &$c->ctsdata;

			// SOLO GAME STATS
			if ($this->playerlimit == 1) {
				++$ctsdata->sologames;
				if ($c->lives > 0)
					++$ctsdata->solowins;

				if ($stt->bestsolo < $c->finalscore) {
					$stt->bestsolo = $c->finalscore;
					$stt->bestsoloname = $c->name;
					$this->message(b().$c->name.r().' set a new record for most solo winnings with $'.number_format($c->finalscore).'.');
				}
				if ($ctsdata->bestsolo < $c->finalscore)
					$ctsdata->bestsolo = $c->finalscore;
			}

			// REGULAR GAME STATS
			else {
				++$ctsdata->games;
				if ($this->winner == $k) {
					++$ctsdata->wins;
					$stt->lastwinner[$this->channel] = $c->name; // Defending champion set

					// Best winnings
					if ($stt->bestgame < $c->finalscore) {
						$stt->bestgame = $c->finalscore;
						$stt->bestgamename = $c->name;
						$this->message(b().$c->name.r().' set a new record for most winnings with $'.number_format($c->finalscore).'.');
					}

					// Best winnings (no bonus counted)
					if ($stt->bestwin < $c->banked) {
						$stt->bestwin = $c->banked;
						$stt->bestwinname = $c->name;
						$this->message(b().$c->name.r().' set a new record for most winnings before the bonus round with $'.number_format($c->banked).'.');
					}

					// player personal bests
					if ($ctsdata->bestwin < $c->banked)
						$ctsdata->bestwin = $c->banked;
				}
				else {
					// Best winnings by a loser
					if ($stt->bestloss < $c->finalscore) {
						$stt->bestloss = $c->finalscore;
						$stt->bestlossname = $c->name;
						$this->message(b().$c->name.r().' set a new record for most winnings by a non-winner with $'.number_format($c->finalscore).'.');
					}

					// player personal bests
					if ($ctsdata->bestloss < $c->finalscore)
						$ctsdata->bestloss = $c->finalscore;
 				}

				// player personal bests
				if ($ctsdata->bestgame < $c->finalscore)
					$ctsdata->bestgame = $c->finalscore;
			}

			// Update total cash
			$stt->totalcash += $c->finalscore;

			$ctsdata->lastplay = time();
			$ctsdata->money += $c->banked;
			unset($ctsdata);
		}
		++$stt->games;
		fortune::saveContestantData();
	}
	//
	// END ENDGAME FUNCTIONS
	//

	protected abstract function setupMessages();
	protected abstract function act($n);
	public abstract function setupWheel(&$wheel, $round);
	public abstract function getNumBonusPrizes();
	public abstract function getBonusPrize(&$player);
	public abstract function getBonusRoundText(&$player);
}
