<?php
/*
 * XIRC2 IRC Bot Module - Fortune
 * Copyright (c) 2012-2013 Inuyasha.
 * All rights reserved.
 *
 * layouts\randomized.php
 * Example "randomized" Fortune layout.  Plays a game of Fortune with the spaces completely randomized.
 */

class fortuneLayout_randomized extends fortuneSettings {
	private $intnRound = 0;

	protected function setupMessages() {
		$this->jackpotRound = random::range(1,3);

		$this->messages = array(
		'startfortune' => '%s joins the game!  That\'s all the contestants we need; let\'s play Randomized Fortunes!',
		'welcome'      => 'Welcome to Randomized Fortunes!  %s',

		'introduce' => 'First thing\'s first, let\'s take a quick moment to introduce our player%s today.',

		'tossup0'        => 'With introductions out of the way, we\'re going to do is do a Toss-up for control of the board in the first round.  No money at stake, however.',
		'tossup_generic' => 'Well, everyone\'s gotten a chance to start first on a puzzle now, so it\'s time for a Toss-up!  This one will be worth $%s.',

		'round_fromtossup' => 'For winning the Toss-up, %s gets first chance at round %s, where the '.b().'JACKPOT'.r().' bonus awaits.',
		 );
	}

	protected function act($n) {
		$tossupVal = random::range(1,10) * 500;
		switch ($n) {
			case 0: return array('introduce', 0);
			case 1: return array('tossup', 0);
			// 10000 is jumped to when game over
			case 10000: return array('prebonus', 0);
			case 10001: return array('bonus',    0);
			case 10002: return array('endgame',  0);

			default:
				$roundvar = (($n+3)%4);
				if ($roundvar == 0)
					return array('tossup', $tossupVal);
				else {
					++$this->intnRound;
					if ($roundvar == 1) return array('round', 1);
					else                return array('round', 0);
				}
		}
	}

	public function setupWheel(&$wheel, $round) {
		$wheel->unsetWheelPosition();
		$wheel->spaces = array();

		$wildCarded = false;
		$doublePlayed = false;

		$wheel->spaces[0] = fortuneSpace::createSpecialWedge("bankrupt");
		if (($round % 3) == 1)
			$wheel->spaces[1300] = fortuneSpace::createSpecialWedge("jackpot");

		for ($i = 100; $i < 2400; $i += 100) {
			if (in_array($i, array(400, 1300, 1600, 2100))) continue;
			if (isset($wheel->spaces[$i])) continue;
			$type = random::range(-21, 6);
			switch ($type) {
				case 0: case 1:
					$wheel->spaces[$i] = fortuneSpace::createSpecialWedge("prize");
					break;
				case 3:
					if ($wildCarded) break;
					$wildCarded = true;
					$wheel->spaces[$i] = fortuneSpace::createSpecialWedge("wildCard");
					break;
				case 5:
					if ($doublePlayed) break;
					$doublePlayed = true;
					$wheel->spaces[$i] = fortuneSpace::createSpecialWedge("doublePlay");
					break;
				case 7:
					$wheel->spaces[$i] = fortuneSpace::createSpecialWedge("freePlay");
					break;
				case -9:
					$wheel->spaces[$i] = fortuneSpace::createSpecialWedge("loseATurn");
					break;
			}
		}

		for ($i = 100; $i < 2400; $i += 100) {
			$coverUp = 0;
			if (isset($wheel->spaces[$i])) {
				if ($wheel->spaces[$i]->type == 'doublePlay'
				|| $wheel->spaces[$i]->type == 'wildCard'
				|| $wheel->spaces[$i]->type == 'prize')
					$coverUp = 1;
				else
					continue;
			}

			$bgcolor = random::range(2, 15);
			if (in_array($bgcolor, array(2, 5, 6, 10, 12, 14))) $color = 0;
			else $color = 1;

			if (random::range(0,7) == 1 || $i == 1300) {
				$priceA = 400 - (int)pow(random::range(0, 27270900), 1/3);
				$priceB = 400 - (int)pow(random::range(0, 27270900), 1/3);
			}
			else {
				$priceA = 200 - (int)pow(random::range(0, 7189057), 1/3);
				$priceB = 200 - (int)pow(random::range(0, 7189057), 1/3);
			}
			$priceA *= 25;
			$priceB *= 25;
			$price = min($priceA, $priceB);

			$wheel->spaces[$i+$coverUp] = fortuneSpace::createMoneyWedge($price, $color, $bgcolor);
			if ($coverUp) {
				$wheel->spaces[$i+$coverUp]->toggleEnabled();
			}
		}

//		foreach ($wheel->spaces as $k=>$tmps)
//			consoleDebug(sprintf("[%4d] => %s %d", $k, $tmps->type, $tmps->vars));

		$wheel->setWheelPosition();
	}

	public function getNumBonusPrizes() {
		return 24;
	}

	public function getBonusPrize(&$player) {
		// bonuses not attainable in this game
		$rand = $this->getBonusPrizeNumber();

		if ($rand == 0)
			return new fortuneBonusPrize(b().c(3,15).' $100,000 '.r(), 100000);
		else if ($rand == 1)
			return new fortuneBonusPrize(b().c(1,15).' $50,000 '.r(), 50000);
		else if ($rand <= 3) {
			$bigcar = (random::range(350,550)*100)+5099;
			return new fortuneBonusPrize(b().c(4,15).' CAR + $5,000 ($'.number_format($bigcar).') '.r(), $bigcar);
		}
		else if ($rand <= 5)
			return new fortuneBonusPrize(b().c(1,15).' $40,000 '.r(), 40000);
		else if ($rand <= 8)
			return new fortuneBonusPrize(b().c(1,15).' $35,000 '.r(), 35000);
		else if ($rand <= 15)
			return new fortuneBonusPrize(b().c(1,15).' $30,000 '.r(), 30000);
		return new fortuneBonusPrize(b().c(1,15).' $25,000 '.r(), 25000);
	}

	public function getBonusRoundText(&$player) {
		return 'bonusstart';
	}
}
