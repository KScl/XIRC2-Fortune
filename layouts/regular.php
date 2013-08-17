<?php
/*
 * XIRC2 IRC Bot Module - Fortune
 * Copyright (c) 2012-2013 Inuyasha.
 * All rights reserved.
 *
 * layouts\regular.php
 * Default Fortune layout.
 */

class fortuneLayout_regular extends fortuneSettings {
	private $progressiveTossup = 3000;

	protected function setupMessages() {
		$this->messages = array(
		'setup_gamestart'	=> '%s joins the game!  That\'s all the contestants we need; let\'s play '.c(14).'[Wheel of]'.r().' Fortune!',
		'setup_welcome'		=> 'Welcome to '.c(14).'[Wheel of]'.r().' Fortune!  %s',

		'tossup_1000' => 'Let\'s start things off with a Toss-up puzzle for $1,000.  Use "!" or "!solve" to ring in when you know the answer.',
		'tossup_2000' => 'And now we\'ll have another Toss-up puzzle, this one worth $2,000.',
		'tossup_3000' => 'Before we go on to round four, let\'s take a little break for another Toss-up puzzle -- this one worth $3,000.',

		'round_1' => 'So %s will start our first round.  Remember:  Use "!spin" to spin the wheel, "!buy" to buy a vowel, and "!solve" to solve the puzzle.',
		'round_2' => 'Now we move on to our '.b().'JACKPOT'.r().' round, and %s is up first.',
		'round_3' => 'Round three is our mystery round, and this time %s is up.',
		 );
	}

	protected function act($n) {
		switch ($n) {
			case 0: return array('tossup', 1000);
			case 1: return array('introduce', 0);
			case 2: return array('tossup', 2000);
			case 3: // drop down
			case 5:
			case 7: return array('round',     2); // 2 - normal round, could possibly have a prize puzzle
			case 4: return array('round',     3); // 3 - Jackpot round, could possibly have a prize puzzle
			case 6: return array('tossup', 3000);
			// 10000 is jumped to when game over
			case 10000: return array('prebonus', 0);
			case 10001: return array('bonus',    0);
			case 10002: return array('endgame',  0);
			default:
				if (!(($n+2) % 4)) {
					$this->progressiveTossup+=1000;
					return array('tossup', $this->progressiveTossup);
				}
				else
					return array('round',     0); // 0 - normal round
		}
	}

	public function setupWheel(&$wheel, $round) {
		$wheel->unsetWheelPosition();
		if ($round == 1) { // We need to initialize everything
			$wheel->spaces[0   ] = new fortuneLoseTurnWedge();
			$wheel->spaces[100 ] = new fortuneCashWedge(300, 0, 12);
			$wheel->spaces[200 ] = new fortuneFreePlayWedge();
			$wheel->spaces[300 ] = new fortuneCashWedge(600, 1, 6);
			$wheel->spaces[400 ] = new fortuneBankruptWedge();
			$wheel->spaces[500 ] = new fortuneCashWedge(900, 1, 13);
			$wheel->spaces[600 ] = new fortuneCashWedge(300, 1, 3);
			$wheel->spaces[700 ] = new fortuneCashWedge(500, 0, 12);
			$wheel->spaces[800 ] = new fortuneCashWedge(900, 1, 4);
			$wheel->spaces[900 ] = new fortunePrizeWedge();
			$wheel->spaces[901 ] = new fortuneCashWedge(300, 1, 13);
			$wheel->spaces[901 ]->toggleEnabled();
			$wheel->spaces[1000] = new fortuneCashWedge(400, 1, 8);
			$wheel->spaces[1100] = new fortuneCashWedge(550, 1, 6);
			$wheel->spaces[1200] = new fortuneBankruptWedge(true);
			$wheel->spaces[1201] = new fortuneMillionWedge();
			$wheel->spaces[1202] = new fortuneBankruptWedge(true);
			$wheel->spaces[1203] = new fortuneCashWedge(700, 1, 3);
			$wheel->spaces[1203]->toggleEnabled();
			$wheel->spaces[1300] = new fortuneCashWedge(500, 0, 12);
			$wheel->spaces[1400] = new fortuneCashWedge(300, 1, 4);
			$wheel->spaces[1500] = new fortuneCashWedge(500, 1, 8);
			$wheel->spaces[1600] = new fortuneCashWedge(600, 1, 3);
			$wheel->spaces[1700] = new fortuneCashWedge(2500, 0, 2);
			$wheel->spaces[1800] = new fortuneLoseTurnWedge();
			$wheel->spaces[1900] = new fortuneCashWedge(300, 1, 5);
			$wheel->spaces[2000] = new fortuneWildCardWedge();
			$wheel->spaces[2001] = new fortuneCashWedge(700, 1, 3);
			$wheel->spaces[2001]->toggleEnabled();
			$wheel->spaces[2100] = new fortuneCashWedge(450, 1, 13);
			$wheel->spaces[2200] = new fortunePrizeWedge();
			$wheel->spaces[2201] = new fortuneCashWedge(350, 1, 6);
			$wheel->spaces[2201]->toggleEnabled();
			$wheel->spaces[2300] = new fortuneCashWedge(800, 1, 4);
		}
		// Jackpot round
		else if ($round == 2) {
			$wheel->spaces[1400] = new fortuneJackpotWedge();
			$wheel->spaces[1700] = new fortuneCashWedge(3500, 1, 4);
		}
		// Mystery round
		else if ($round == 3) {
			$mysteryshit = random::range(0, 999);

			$wheel->spaces[101 ] = $wheel->spaces[100 ];
			$wheel->spaces[100 ] = new fortuneMysteryWedge(($mysteryshit < 500));
			$wheel->spaces[1301] = $wheel->spaces[1300];
			$wheel->spaces[1300] = new fortuneMysteryWedge(($mysteryshit >= 500));
			$wheel->spaces[1400] = new fortuneCashWedge(300, 1, 4);
		}
		// Final wheel configuration
		else if ($round == 4) {
			$wheel->spaces[0   ] = new fortuneBankruptWedge(true);
			$wheel->spaces[1   ] = new fortuneSuperBonusWedge();
			$wheel->spaces[2   ] = new fortuneBankruptWedge(true);
			$wheel->spaces[3   ] = new fortuneCashWedge(1000, 1, 15);
			$wheel->spaces[3   ]->toggleEnabled();
			$wheel->spaces[100 ] = new fortuneCashWedge(300, 0, 12);
			unset($wheel->spaces[101 ]); // Unset underside
			$wheel->spaces[900 ] = new fortuneCashWedge(300, 1, 13);
			unset($wheel->spaces[901 ]); // Unset underside
			$wheel->spaces[1200] = new fortuneCashWedge(800, 1, 5);
			unset($wheel->spaces[1201]); // Unset narrow spaces
			unset($wheel->spaces[1202]); // Unset narrow spaces
			unset($wheel->spaces[1203]); // Unset underside
			$wheel->spaces[1300] = new fortuneCashWedge(500, 0, 12);
			unset($wheel->spaces[1301]); // Unset underside
			$wheel->spaces[1700] = new fortuneCashWedge(5000, 1, 15);
			$wheel->spaces[2000] = new fortuneDoublePlayWedge();
			$wheel->spaces[2001] = new fortuneCashWedge(700, 1, 3);
			$wheel->spaces[2001]->toggleEnabled();
			$wheel->spaces[2200] = new fortuneCashWedge(350, 1, 4);
			unset($wheel->spaces[2201]); // Unset underside
		}
		$wheel->setWheelPosition();
	}

	public function getNumBonusPrizes() {
		return 24;
	}

	public function getBonusPrize(&$player) {
		$oneMillion = ($player->getCard('oneMillion') > -1);
		$superBonus = ($player->getCard('superBonus') > -1);

		$rand = $this->getBonusPrizeNumber();
		if ($superBonus) {
			if ($rand == 0) {
				// $100k / $1mil
				if ($oneMillion)
					return new fortuneBonusPrize(b().c(8,3).' $2,000,000 '.r(), 2000000);
				return new fortuneBonusPrize(b().c(3,15).' $200,000 '.r(), 200000);
			}
			else if ($rand == 1)
				return new fortuneBonusPrize(b().c(3,15).' $100,000 '.r(), 100000);
			else if ($rand <= 3) {
				$bigcar = ((random::range(350,550)*100)+5099) + ((random::range(350,550)*100)+5099);
				return new fortuneBonusPrize(b().c(4,15).' 2x CAR + $10,000 ($'.number_format($bigcar).') '.r(), $bigcar);
			}
			else if ($rand <= 5)
				return new fortuneBonusPrize(b().c(1,15).' $80,000 '.r(), 80000);
			else if ($rand <= 8)
				return new fortuneBonusPrize(b().c(1,15).' $70,000 '.r(), 70000);
			else if ($rand <= 15)
				return new fortuneBonusPrize(b().c(1,15).' $60,000 '.r(), 60000);
			return new fortuneBonusPrize(b().c(1,15).' $50,000 '.r(), 50000);
		}
		else {
			if ($rand == 0) {
				// $100k / $1mil
				if ($oneMillion)
					return new fortuneBonusPrize(b().c(8,3).' $1,000,000 '.r(), 1000000);
				return new fortuneBonusPrize(b().c(3,15).' $100,000 '.r(), 100000);
			}
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
	}

	public function getBonusRoundText(&$player) {
		$oneMillion = ($player->getCard('oneMillion') > -1);
		$superBonus = ($player->getCard('superBonus') > -1);

		if ($oneMillion && $superBonus) // !!!?
			return 'bonus_sm';
		else if ($oneMillion) // holy shit they brought the million here
			return 'bonus_m';
		else if ($superBonus)
			return 'bonus_s';
		else
			return 'bonus';
	}
}

