<?php
/*
 * XIRC2 IRC Bot Module - Fortune
 * Copyright (c) 2012-2013 Inuyasha.
 * All rights reserved.
 *
 * include\fortuneContestant.php
 * Management for a contestant's data.
 */

class fortuneContestant {
	public $name = ""; // Their name!

	public $onhand     = 0; // on account for this round
	public $banked     = 0; // banked for later use
	public $finalscore = 0; // final score (after bonuses added)

	public $cards  = array();

	public $lives = 0; // In solo mode, used to determine number of turns left

	public $ctsdata; // contestant data (Extra unnecessary stats)

	public function bankrupt() {
		$this->onhand = 0;
		foreach ($this->cards as $k=>$c) {
			if (!$c->survivesBankrupt)
				unset($this->cards[$k]);
		}
	}

	public function bank() {
		$this->banked += $this->onhand;
		$this->onhand = 0;
		foreach ($this->cards as $k=>$c) {
			if ($c->type == "prize") {
				$this->banked += $c->value;
				unset($this->cards[$k]);
			}
		}
	}

	public function removePrizes() {
		foreach ($this->cards as $k=>$c) {
			if ($c->type == "prize")
				unset($this->cards[$k]);
		}
	}

	public function draw($position, $lit, $banked = false) {
		$colort = $colorb = 0;
		switch ($position) {
			case 0: // Red
				$colort = (($lit) ?  0 : 15);
				$colorb = (($lit) ?  4 :  5);
				break;
			case 1: // Yellow
				$colort = (($lit) ? 14 : 15);
				$colorb = (($lit) ?  8 :  7);
				break;
			case 2: // Blue
				$colort = (($lit) ?  0 : 15);
				$colorb = (($lit) ? 12 :  2);
				break;
			case 3: // Green
				$colort = (($lit) ? 14 : 15);
				$colorb = (($lit) ?  9 :  3);
				break;
			case 4: // Magenta
				$colort = (($lit) ?  0 : 15);
				$colorb = (($lit) ? 13 :  6);
				break;
			case 5: // Cyan
				$colort = (($lit) ? 14 : 15);
				$colorb = (($lit) ? 11 : 10);
				break;
		}
		$money = number_format(($banked) ? $this->banked : $this->onhand);
		if ($lit)
			$text = c($colort,$colorb)."> {$this->name} ".b()."\${$money}".b(). " <".r();
		else
			$text = c(1,$colorb).">".r().c($colorb)." {$this->name} ".b()."\${$money}".b()." ".r().c(1,$colorb)."<".r();

		foreach ($this->cards as $c)
			$text .= $c->display;

		if ($this->lives)
			$text .= " ".c(8,3).b()." {$this->lives}".c(0,3)." turn".(($this->lives>1)?"s":"")." ".r();


		return $text;
	}

	// Get text to introduce a player.
	function introduceMe($num, $max, $lastWinnerHere = false) {
		$text = "Running up the wheel alone is ";
		if ($max == 2) switch ($num) {
			case 0: $text = "First in line is "; break;
			case 1: $text = "Their dueling partner is "; break;
		}
		else if ($max == 3) switch ($num) {
			case 0: $text = "Closest to me is "; break;
			case 1: $text = "Stuck in the middle is "; break;
			case 2: $text = "Last but not least is "; break;
		}
		else if ($max == 4) switch ($num) {
			case 0: $text = "Closest to me is "; break;
			case 1: $text = "Right behind them is "; break;
			case 2: $text = "Up third is "; break;
			case 3: $text = "And at the end of the line is "; break;
		}
		// TODO handle this per channel
		if ($lastWinnerHere) $text .= "our defending champion, ";
		$text .= $this->name;

		$money = number_format($this->ctsdata->money);
		if ($this->ctsdata->games > 0) $text .= "; who returns with \${$money} to their name, amassed over {$this->ctsdata->games} game" .(($this->ctsdata->games > 1)?"s":""). ".";
		else                           $text .= "; who is a complete newcomer to our game.  Nevertheless, welcome!";

		return $text;
	}

	function getCard($type) {
		foreach ($this->cards as $k=>$c) {
			if ($c->type == $type) return $k;
		}
		return -1;
	}
}

// Those little things you pick up.
class fortuneCard {
	public $type             = "prize";
	public $survivesBankrupt = false; // Lost when bankrupted?
	public $value            = 0;

	public $display = "P";

	public function __construct($t, $d="") {
		$this->type = $t;

		if ($d) $this->display = $d;
		else switch ($t) {
			case 'superBonus': $this->display = b().c(15,3).'B'.r(); break;
			case 'oneMillion': $this->display = b().c(8,3).'$'.r(); break;
			case 'doublePlay': $this->display = b().c(0,4).'D'.r(); break;
			case 'wildCard': $this->display = b().c(7,6).'W'.r(); break;
		}
	}
}

// The game ending bonus prize
class fortuneBonusPrize {
	public $display = " NO TEXT TO SEND ";
	public $value   = 0;

	public function __construct($d, $v) {
		$this->display = $d;
		$this->value = $v;
	}
}
