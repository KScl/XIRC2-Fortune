<?php
/*
 * XIRC2 IRC Bot Module - Fortune
 * Copyright (c) 2012-2013 Inuyasha.
 * All rights reserved.
 *
 * include\fortuneWedges.php
 * Handles wedges of all shapes and sizes and colors.
 */

//
// Base class (cannot be instantiated)
//
abstract class fortuneBaseWedge {
	public $length = 42;
	protected $wedge    = "      N O   T E X T   T O   S E N D       ";
	protected $colors   = array();
	protected $bgcolors = array();

	public $shorttext = " NO TEXT TO SEND ";
	public $reallen   = 0;

	public final function toggleEnabled() {
		$this->length *= -1;
		return $this;
	}

	public final function isEnabled() {
		return ($this->length > 0);
	}

	public final function displayWedge($start = 0, $end = -1) {
		if ($end < 0) $end = $this->length;

		$curc = $curb = 0;
		$lasc = $lasb = -1;

		$text = b();

		for ($i = 0; $i < $end; ++$i) {
			if (isset($this->colors[$i]))   $curc = $this->colors[$i];
			if (isset($this->bgcolors[$i])) $curb = $this->bgcolors[$i];
			if ($i < $start) continue;

			if ($lasc != $curc || $lasb != $curb) {
				$text .= c($curc,$curb);
				$lasc = $curc;
				$lasb = $curb;
			}

			$text .= $this->wedge{$i};
		}
		$text .= r();
		return $text;
	}

	public final function getColorCode() {
		return c($this->colors[0], $this->bgcolors[0]);
	}

	// Extra parenthetical text for the freewheel if needed
	public function getExtraText() {
		return '';
	}

	// Value of the wedge when a Wild Card is used on it.
	// RETURN: Postitive integer (0 included) to allow wild card with amount returned.
	//         Negative integer to disallow.
	public function getWildValue() {
		return -1;
	}

	// When the wedge is landed on.
	// Determines what types of guesses are allowed (if any).
	// RETURN: GUESS_* flags.
	// MODIFY: &$wedgetext to add a parenthetical extra after the wedge text
	// MODIFY: &$text to alter the displayed text after (Now I need a(n) x)
	public function onLand(&$game, &$player, &$wedgetext, &$text, $mult) {
		if ($mult != 1) return GUESS_RESPIN;
		return GUESS_CONSONANT;
	}

	// If you want any actions done SPECIFICALLY after landing and displaying
	// the spin text. (Rare, but happens.)
	public function onAfterLand(&$game, &$player) {
	}

	// When a correct guess (or any guess if the type was GUESS_NORISK) is given.
	// RETURN: true if you want to handle input after, false (or nothing) if not.
	// MODIFY: &$text to alter the displayed text.
	public function onGuess(&$game, &$player, &$text, $letter, $num) {
	}

	// Same as before except for guessing.
	public function onAfterGuess(&$game, &$player) {
	}

	// Player input is sent here after one of the functions
	// (onLand and onGuess) returns a result requesting to handle input.
	// Use $game->wedgeEndHandling($control); to end handling.
	// $control: true to keep player's control and false to go to the next.
	public function handleGameInput(&$game, &$player, &$data, $id) {
	}

	// If you use $game->wedgeSetTimer(n); and the timer expires,
	// this is the function that will be called.
	// Ideally you should end this with
	// $game->wedgeEndHandling();
	public function handleTimeUp(&$game, &$player) {
	}
}

//
// Main "cash" wedges
//
class fortuneCashWedge extends fortuneBaseWedge {
	private $value = 0;
	private $curvalue = 0;

	public function __construct($amount, $color, $bgcolor, $size = 42) {
		$this->value = $amount;
		$this->length = $size;
		$this->colors[0] = $color;
		$this->bgcolors[0] = $bgcolor;

		$this->shorttext = b().c($color,$bgcolor)." \${$amount} ".r();
		$this->reallen = strlen(" \${$amount} ");
		if ($size > 14*2)
			$this->wedge = str_pad(implode('  ',str_split("\${$amount}")), $size, " ", STR_PAD_BOTH);
		else if ($size > 14)
			$this->wedge = str_pad(implode(' ',str_split("\${$amount}")), $size, " ", STR_PAD_BOTH);
		else
			$this->wedge = str_pad("\${$amount}", $size, " ", STR_PAD_BOTH);

	}

	public function getWildValue() {
		return $this->curvalue;
	}

	public function onLand(&$game, &$player, &$wedgetext, &$text, $mult) {
		$this->curvalue = $this->value * $mult;
		if ($this->curvalue != $this->value)
			$wedgetext .= ' ($'.number_format($this->curvalue).')';

		$game->roundVars['jackpot'] += $this->curvalue;
		return GUESS_CONSONANT;
	}

	public function onGuess(&$game, &$player, &$text, $letter, $num) {
		$pvalue = ($this->curvalue * $num);
		$player->onhand += $pvalue;
		if ($pvalue > 0)
			$text = sprintf($game->getMessage('letter_value'), number_format($pvalue));
	}

	// Dumb hack: speedup needs to know the value so it can add +1000
	public function getValue() {
		return $this->value;
	}
}

//
// The "bad" wedges.
//
class fortuneLoseTurnWedge extends fortuneBaseWedge {
	protected $wedge    = "     L  O  S  E      A      T  U  R  N    ";
	protected $colors   = array(1);
	protected $bgcolors = array(0);

	public function __construct() {
		$this->shorttext = b().c(1,0)." LOSE A TURN ".r();
		$this->reallen = 13;
	}

	public function onLand(&$game, &$player, &$wedgetext, &$text, $mult) {
		return GUESS_ENDTURN;
	}
}

class fortuneBankruptWedge extends fortuneBaseWedge {
	protected $wedge    = "          B  A  N  K  R  U  P  T          ";
	protected $colors   = array(0);
	protected $bgcolors = array(1);

	public function __construct($small = false) {
		if ($small) {
			$this->wedge = "   BANKRUPT   ";
			$this->length = 14;
		}
		$this->shorttext = b().c(0,1)." BANKRUPT ".r();
		$this->reallen = 10;
	}

	public function onLand(&$game, &$player, &$wedgetext, &$text, $mult) {
		$player->bankrupt();
		return GUESS_ENDTURN;
	}
}

//
// Big bonuses, $1M + SuperBonus
//
class fortuneMillionWedge extends fortuneBaseWedge {
	public $length		= 14;

	protected $wedge	= ' ONE  MILLION ';
	protected $colors	= array(8);
	protected $bgcolors	= array(3);

	public function __construct() {
		$this->shorttext	= b().c(8,3)." ONE MILLION ".r();
		$this->reallen		= 13;
	}

	public function onGuess(&$game, &$player, &$text, $letter, $num) {
		$text = $game->getMessage('spec_1mil');
		$player->cards[] = new fortuneCard('oneMillion');
		$game->getWheel()->toggleSpaces(-1, 2);
	}
}

class fortuneSuperBonusWedge extends fortuneBaseWedge {
	public $length		= 14;

	protected $wedge	= ' $UPER  BONU$ ';
	protected $colors	= array(0 => 8, 2 => 15, 12 => 8);
	protected $bgcolors	= array(3);

	public function __construct() {
		$this->shorttext	= b().c(8,3).' $'.c(15)."UPER BONU".c(8).'$ '.r();
		$this->reallen		= 13;
	}

	public function onGuess(&$game, &$player, &$text, $letter, $num) {
		$text = $game->getMessage('spec_sbonus');
		$player->cards[] = new fortuneCard('superBonus');
		$game->getWheel()->toggleSpaces(-1, 2);
	}
}

//
// Cards, Wild Card + Double Play + Free Spin
//
class fortuneWildCardWedge extends fortuneBaseWedge {
	protected $wedge	= '        W  I  L  D      C  A  R  D        ';
	protected $colors	= array(7);
	protected $bgcolors	= array(6);

	public function __construct() {
		$this->shorttext = b().c(7,6).' WILD CARD '.r();
		$this->reallen = 11;
	}

	public function onGuess(&$game, &$player, &$text, $letter, $num) {
		$text = $game->getMessage('spec_wildcard');
		$player->cards[] = new fortuneCard('wildCard');
		$game->getWheel()->toggleSpaces();
	}
}

class fortuneDoublePlayWedge extends fortuneBaseWedge {
	protected $wedge	= '   D  O  U  B  L  E    $$    P  L  A  Y   ';
	protected $colors	= array(0 => 8, 19 => 1, 25 => 8);
	protected $bgcolors	= array(4);

	public function __construct() {
		$this->shorttext = b().c(0,4)." DOUBLE ".c(1).'$$'.c(0)." PLAY ".r();
		$this->reallen = 16;
	}

	public function onGuess(&$game, &$player, &$text, $letter, $num) {
		$text = $game->getMessage('spec_dplay');
		$player->cards[] = new fortuneCard('doublePlay');
		$game->getWheel()->toggleSpaces();
	}
}

//
// Generic prize
//
class fortunePrizeWedge extends fortuneBaseWedge {
	private $value = 0;
	private $prizename = '';

	public function __construct() {
		$type = array("TRIP", "CAR", "FURNITURE", "PAINTING", "FLOKATI RUG");

		// Make the flokati rug a rare joke item
		$weight = array(0,0,0,0,0,0,1,1,1,1,2,2,2,2,2,3,3,3,3,4);
		$chosentype = $weight[random::key($weight)];

		if ($chosentype == 4)
			$this->value = random::range(200, 499);
		else if ($chosentype == 1)
			$this->value = (random::range(1463, 2500)*5);
		else
			$this->value = random::range(800, 7499);

		$c = random::range(0,2);
		if     ($c == 0) $this->bgcolors[0] = 4;
		elseif ($c == 1) $this->bgcolors[0] = 3;
		else             $this->bgcolors[0] = 10;

		$c = random::range(0,1);
		if ($c == 0) $this->colors[0] = $this->colors[31] = 8;
		else         $this->colors[0] = $this->colors[31] = 15;

		$this->colors[10] = 0;

		$this->prizename = strtolower($type[$chosentype]);

		$price = number_format($this->value);
		$this->reallen = strlen(" {$type[$chosentype]} (\${$price}) ");
		$this->shorttext = b().c(0,$this->bgcolors[0])." {$type[$chosentype]} ".c($this->colors[0],$this->bgcolors[0])."(\${$price}) ".r();

		$wedgetext = str_pad($type[$chosentype], 20, ' ', STR_PAD_BOTH);
		$this->wedge = " * PRIZE * {$wedgetext} * PRIZE * ";
	}

	public function onGuess(&$game, &$player, &$text, $letter, $num) {
		$text = sprintf($game->getMessage('spec_prize'), $this->prizename, number_format($this->value));

		$card = new fortuneCard('prize', b().$this->getColorCode().'P'.r());
		$card->value = $this->value;
		$player->cards[] = &$card;

		$game->getWheel()->toggleSpaces();
	}
}

//
// The other default 'special' wedges
//
class fortuneFreePlayWedge extends fortuneBaseWedge {
	protected $wedge	= "        F  R  E  E      P  L  A  Y        ";
	protected $colors	= array(0 => 8, 18 => 0);
	protected $bgcolors	= array(3);

	private $fpinform = false;

	public function __construct() {
		$this->reallen = 11;
		$this->shorttext = b().c(8,3)." FREE ".c(0,3)."PLAY ".r();
	}

	public function onLand(&$game, &$player, &$wedgetext, &$text, $mult) {
		if ($mult != 1) return GUESS_RESPIN;
		$text = $game->getMessage('spec_fplay_land');
		return GUESS_ALL|GUESS_NORISK;
	}

	public function onGuess(&$game, &$player, &$text, $letter, $num) {
		$this->fpinform = false;
		if ($num <= 0)
			$this->fpinform = true;
		elseif (in_array($letter, $game->vowels));
		else {
			$pvalue = (500 * $num);
			$player->onhand += pvalue;
			$text = sprintf($game->getMessage('letter_value'), number_format($pvalue));
		}
	}

	public function onAfterGuess(&$game, &$player) {
		if ($this->fpinform)
			$game->messageOutput('spec_fplay_miss');
	}
}

class fortuneJackpotWedge extends fortuneBaseWedge {
	protected $wedge    = "  * $ *    J  A  C  K   P  O  T    * $ *  ";
	protected $colors   = array(0 => 8, 13 => 0, 16 => 8, 19 => 0, 23 => 8, 26 => 0, 29 => 8);
	protected $bgcolors = array(10);

	public function __construct() {
		$this->shorttext = b().c(8,10).' J'.c(0).'A'.c(8).'C'.c(0).'K'.c(8).'P'.c(0).'O'.c(8).'T '.r();
		$this->reallen = 9;
	}

	public function onGuess(&$game, &$player, &$text, $letter, $num) {
		$game->roundVars['jackpotSolve'] = true;
		$text = sprintf($game->getMessage('spec_jackpot'), number_format($game->roundVars['jackpot']));
	}
}

class fortuneMysteryWedge extends fortuneBaseWedge {
	protected $wedge	= "    (?? MYSTERY ??)      $  1  0  0  0    ";
	protected $colors	= array(0 => 8, 7 => 0, 15 => 8, 19 => 7);
	protected $bgcolors	= array(2);

	private $good = false;
	private $curvalue = 0;

	public function __construct($mystery = false) {
		$this->shorttext = b().c(0,2).' ('.c(8).'?'.c(0).')'.c(7).' $1000 '.r();
		$this->reallen = 11;

		$this->good = $mystery;
	}

	public function getExtraText() {
		return (($this->good) ? '($10,000)' : '(BANKRUPT)');
	}

	public function onGuess(&$game, &$player, &$text, $letter, $num) {
		$this->curvalue = (1000 * $num);

		if ($game->roundVars['mysteryDone']) {
			$player->onhand += $this->curvalue;
			$text = sprintf($game->getMessage('letter_value'), number_format($this->curvalue));
		}
		elseif ($this->curvalue >= 10000) {
			$text = sprintf($game->getMessage('spec_mystery_10k'), number_format($this->curvalue));
			$player->onhand += pvalue;
		}
		else {
			// +3 seconds just in case
			$game->wedgeSetTimer(18);
			$text = sprintf($game->getMessage('spec_mystery'), number_format($this->curvalue));
			return true;
		}
		return false;
	}

	public function handleGameInput(&$game, &$player, &$data, $id) {
		$accept = strtoupper($data->messageex[0]{0});
		if ($accept == 'Y')
			$this->mysteryAccepted($game, $player);
		else if ($accept == 'N')
			$this->mysteryDeclined($game, $player);
	}

	public function handleTimeUp(&$game, &$player) {
		$this->mysteryDeclined($game, $player);
	}

	private function mysteryAccepted(&$game, &$player) {
		$game->getWheel()->toggleSpaces();
		$game->roundVars['mysteryDone'] = true;

		if ($this->good) {
			$game->messageOutput('spec_mystery_good');
			$card = new fortuneCard('prize', b().c(1,9).'$'.r());
			$card->value = 10000;
			$player->cards[] = &$card;
			$game->wedgeEndHandling(true);
		}
		else { // Baad.
			$game->messageOutput('spec_mystery_bad');
			$player->bankrupt();
			$game->wedgeEndHandling(false);
		}
	}

	private function mysteryDeclined(&$game, &$player) {
		$player->onhand += $this->curvalue;
		$game->messageOutput('spec_mystery_decl', number_format($this->curvalue));
		$game->wedgeEndHandling(true);
	}
}

