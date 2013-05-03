<?php
/*
 * XIRC2 IRC Bot Module - Fortune
 * Copyright (c) 2012-2013 Inuyasha.
 * All rights reserved.
 *
 * include\fortuneWheel.php
 * Does all the wheel display, spinning, et cetera.  Handles spaces to an extent as well.
 * Actually acts like a proper wheel, not just choosing random values, for that matter.
 */

class fortuneWheel {
	public $spaces = array();

	private $position    = 0;
	private $subposition = 0;

	private $wheelSize = 90;

	private $lastLandedId = 0;

	public function spin($amount, $setLast = true) {
		ksort($this->spaces);
		$keys = array_keys($this->spaces);

		$amount += $this->subposition;
		$this->subposition = 0;

		for (;;) {
			if (!isset($this->spaces[$keys[$this->position]])) $this->position = 0;
			$wedge = $this->spaces[$keys[$this->position]];

			if ($wedge->isEnabled()) {
				$amount -= $wedge->length;
				if ($amount <= 0) {
					$this->subposition = $wedge->length + $amount;
					break;
				}
			}
			++$this->position;
		}

		if ($setLast)
			$this->lastLandedId = $this->getCurrentWedgeId();
	}

	// Use these when you change the wheel mid-round (lifting items up)
	public function unsetWheelPosition() {
		ksort($this->spaces);
		$keys = array_keys($this->spaces);

		for ($i = 0; $i < $this->position; ++$i) {
			$wedge = $this->spaces[$keys[$i]];
			if ($wedge->isEnabled())
				$this->subposition += $wedge->length;
		}

		$this->position = 0;
	}

	public function setWheelPosition() {
		$this->spin(0, false);
	}

	public function toggleSpaces($start = 0, $end = 1) {
		$toggleslots = array();
		for ($ti = $start; $ti <= $end; ++$ti)
			$toggleslots[] = $this->getNearbyWedge($ti);

		$this->unsetWheelPosition();
		foreach ($toggleslots as $togglespace)
			$togglespace->toggleEnabled();
		$this->setWheelPosition();
	}

	public function displayWheelTip($msg, $string, $replace) {
		$t = sprintf("%-".((int)($this->wheelSize/2)-2)."s v",$msg);
		$t = str_replace($string, $replace, $t);
		return $t;
	}

	public function displayWheel() {
		$keys = array_keys($this->spaces);

		// Assume space landed on is fully visible
		$landedWedge = &$this->spaces[$keys[$this->position]];
		$wedges = array(0 => $landedWedge->displayWedge());

		// We have 120 spaces to display.
		$spacestart = (int)($this->wheelSize/2) - $this->subposition;
		$spaceend = $spacestart + $landedWedge->length;

		for ($i = -1; $i >= -8; --$i) {
			$j = $this->position + $i;
			if ($j < 0) $j+=count($this->spaces);
			$wedge = &$this->spaces[$keys[$j]];
			if (!$wedge->isEnabled()) continue;

			$spacestart -= $wedge->length;

			$end = $wedge->length;
			$start = max(0, -$spacestart);

			if ($start > $wedge->length) break;

			$wedges[$i] = $wedge->displayWedge($start,$end);
		}
		for ($i = 1; $i <= 8; ++$i) {
			$j = $this->position + $i;
			if ($j >= count($this->spaces)) $j -= count($this->spaces);
			$wedge = &$this->spaces[$keys[$j]];

			if (!$wedge->isEnabled()) continue;

			$spaceend += $wedge->length;

			// $spaceend = 102 + 42 = 144
			// > 120
			//

			$start = 0;
			if ($spaceend < $this->wheelSize) $end = $wedge->length;
			else $end = $wedge->length - ($spaceend - $this->wheelSize);

			if ($end <= 0) break;

			$wedges[$i] = $wedge->displayWedge($start,$end);
		}
		ksort($wedges);
		return implode('',$wedges);
	}

	// Get the last landed wedge.
	// If toggling has happened this may differ from the current wedge.
	public function getLandedWedge() {
		return $this->spaces[$this->lastLandedId];
	}

	public function getLandedWedgeId() {
		return $this->lastLandedId;
	}

	// Gets the wedge currently selected.
	public function getCurrentWedge() {
		return $this->spaces[$this->getCurrentWedgeId()];
	}

	public function getCurrentWedgeId() {
		$keys = array_keys($this->spaces);
		return $keys[$this->position];
	}

	public function getNearbyWedge($offset) {
		return $this->spaces[$this->getNearbyWedgeId($offset)];
	}

	public function getNearbyWedgeId($offset) {
		$keys = array_keys($this->spaces);

		$oj = 0;
		$j = $this->position + $offset;
		while ($oj != $j) {
			$oj = $j;
			if ($j < 0)                          $j += count($this->spaces);
			else if ($j >= count($this->spaces)) $j -= count($this->spaces);
		}

		return $keys[$j];
	}

	// Creates new wheel identical to this one.
	public function copy() {
		$w = new fortuneWheel();
		foreach ($this->spaces as $k=>$sp)
			$w->spaces[$k] = $sp->copy();
	}
}

/**
 * A single space on the wheel.
 */
class fortuneSpace {
	public $length  = 42;
	private $wedge    = "      N O   T E X T   T O   S E N D       ";
	private $colors   = array();
	private $bgcolors = array();

	public $shorttext = " NO TEXT TO SEND ";
	public $reallen   = 0;

	public $type = "cash";
	public $value = 0;

	function toggleEnabled() {
		$this->length *= -1;
		return $this;
	}

	function isEnabled() {
		return ($this->length > 0);
	}

	function displayWedge($start = 0, $end = -1) {
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

	function copy() {
		$fs = new fortuneSpace();
		$fs->length    = $this->length;
		$fs->wedge     = $this->wedge;
		$fs->colors    = $this->colors;
		$fs->bgcolors  = $this->bgcolors;
		$fs->shorttext = $this->shorttext;
		$fs->reallen   = $this->reallen;
		$fs->type      = $this->type;
		$fs->value     = $this->value;
	}

	function getColorCode() {
		return c($this->colors[0], $this->bgcolors[0]);
	}

	static function createMoneyWedge($amount, $color, $bgcolor, $size = 42) {
		$fs = new fortuneSpace();

		$fs->value = $amount;

		$fs->length = $size;
		$fs->shorttext = b().c($color,$bgcolor)." \${$amount} ".r();
		$fs->reallen = strlen(" \${$amount} ");
		if ($size > 14*2)
			$fs->wedge = str_pad(implode('  ',str_split("\${$amount}")), $size, " ", STR_PAD_BOTH);
		else if ($size > 14)
			$fs->wedge = str_pad(implode(' ',str_split("\${$amount}")), $size, " ", STR_PAD_BOTH);
		else
			$fs->wedge = str_pad("\${$amount}", $size, " ", STR_PAD_BOTH);
		$fs->colors[0] = $color;
		$fs->bgcolors[0] = $bgcolor;

		return $fs;
	}

	static function createSpecialWedge($type, $vars = 0) {
		$fs = new fortuneSpace();
		$fs->type   = $type;
		$fs->length = 42;

		// Wedge text, colors
		switch ($type) {
			case "superBonus":
				$fs->length      = 14;
				$fs->reallen     = 13;
				$fs->shorttext   = b().c(8,3).' $'.c(15)."UPER BONU".c(8).'$ '.r();
				$fs->wedge       = ' $UPER  BONU$ ';
				$fs->colors[0]   = $fs->colors[12] = 8;
				$fs->colors[2]   = 15;
				$fs->bgcolors[0] = 3;
				$fs->value = 1;
				break;
			case "oneMillion":
				$fs->length      = 14;
				$fs->reallen     = 13;
				$fs->shorttext   = b().c(8,3)." ONE MILLION ".r();
				$fs->wedge       = " ONE  MILLION ";
				$fs->colors[0]   = 8;
				$fs->bgcolors[0] = 3;
				$fs->value = 1;
				break;
			case "bankruptNarrow":
				$fs->type = "bankrupt";
				$fs->wedge       = "   BANKRUPT   ";
				$fs->length = 14;
			case "bankrupt":
				$fs->reallen = 10;
				$fs->shorttext = b().c(0,1)." BANKRUPT ".r();
				$fs->colors[0]   = 0;
				$fs->bgcolors[0] = 1;
				if ($type != "bankruptNarrow")
					$fs->wedge       = "          B  A  N  K  R  U  P  T          ";
				break;
			case "loseATurn":
				$fs->reallen = 13;
				$fs->shorttext = b().c(1,0)." LOSE A TURN ".r();
				$fs->colors[0]   = 1;
				$fs->bgcolors[0] = 0;
				$fs->wedge       = "     L  O  S  E      A      T  U  R  N    ";
				break;
			case "freePlay":
				$fs->reallen = 11;
				$fs->shorttext = b().c(8,3)." FREE ".c(0,3)."PLAY ".r();
				$fs->colors[0]   = 8;
				$fs->colors[18]  = 0;
				$fs->bgcolors[0] = 3;
				$fs->wedge       = "        F  R  E  E      P  L  A  Y        ";
				$fs->value = 500;
				break;
			case "jackpot":
				$fs->reallen = 9;
				$fs->shorttext = b().c(8,10).' J'.c(0).'A'.c(8).'C'.c(0).'K'.c(8).'P'.c(0).'O'.c(8).'T '.r();
				$fs->colors[13]  = $fs->colors[19] = $fs->colors[26] = 0;
				$fs->colors[0]   = $fs->colors[16] = $fs->colors[23] = $fs->colors[29] = 8;
				$fs->bgcolors[0] = 10;
				$fs->wedge       = "  * $ *    J  A  C  K   P  O  T    * $ *  ";
				break;
			case "mystery":
				$fs->reallen = 11;
				$fs->shorttext = b().c(0,2).' ('.c(8).'?'.c(0).')'.c(7).' $1000 '.r();
				$fs->colors[0]   = $fs->colors[15]  = 0;
				$fs->colors[7]   = 8;
				$fs->colors[19]  = 7;
				$fs->bgcolors[0] = 2;
				$fs->wedge       = "    (?? MYSTERY ??)      $  1  0  0  0    ";
				$fs->value = $vars;
				break;
			case "doublePlay":
				$fs->reallen = 16;
				$fs->shorttext = b().c(0,4)." DOUBLE ".c(1).'$$'.c(0)." PLAY ".r();
				$fs->colors[0] = $fs->colors[25] = 8;
				$fs->colors[19] = 1;
				$fs->bgcolors[0] = 4;
				$fs->wedge       = "   D  O  U  B  L  E    $$    P  L  A  Y   ";
				break;
			case "wildCard":
				$fs->reallen = 11;
				$fs->shorttext = b().c(7,6)." WILD CARD ".r();
				$fs->colors[0] = 7;
				$fs->bgcolors[0] = 6;
				$fs->wedge       = "        W  I  L  D      C  A  R  D        ";
				break;
			case "prize":
				$type = array("TRIP", "CAR", "FURNITURE", "PAINTING", "FLOKATI RUG");

				// Make the flokati rug a rare joke item
				$weight = array(0,0,0,0,0,0,1,1,1,1,2,2,2,2,2,3,3,3,3,4);
				$chosentype = $weight[random::key($weight)];

				if ($chosentype == 4)
					$fs->value = random::range(200, 499);
				else if ($chosentype == 1)
					$fs->value = (random::range(1463, 2500)*5);
				else
					$fs->value = random::range(800, 7499);

				$c = random::range(0,2);
				if     ($c == 0) $fs->bgcolors[0] = 4;
				elseif ($c == 1) $fs->bgcolors[0] = 3;
				else             $fs->bgcolors[0] = 10;

				$c = random::range(0,1);
				if ($c == 0) $fs->colors[0] = $fs->colors[31] = 8;
				else         $fs->colors[0] = $fs->colors[31] = 15;

				$fs->colors[10] = 0;

				$price = number_format($fs->value);
				$fs->reallen = strlen(" {$type[$chosentype]} (\${$price}) ");
				$fs->shorttext = b().c(0,$fs->bgcolors[0])." {$type[$chosentype]} ".c($fs->colors[0],$fs->bgcolors[0])."(\${$price}) ".r();

				$wedgetext = str_pad($type[$chosentype], 20, ' ', STR_PAD_BOTH);
				$fs->wedge       = " * PRIZE * $wedgetext * PRIZE * ";
				break;
		}

		return $fs;
	}
}
