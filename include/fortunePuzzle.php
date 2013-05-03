<?php
/*
 * XIRC2 IRC Bot Module - Fortune
 * Copyright (c) 2012-2013 Inuyasha.
 * All rights reserved.
 *
 * include\fortunePuzzle.php
 * Management for puzzles.
 */

define(P_TOSSUP,   1); // Tossup puzzles
define(P_NORMAL,   2); // Regular rounds
define(P_JACKPOT,  4); // Jackpot round (huger puzzles)
define(P_BONUS,    8); // Bonus round (shorter puzzles)
define(P_PRIZE,   16); // Prize puzzles

class fortunePuzzle {
	public $category = "";
	public $solved = "";
	public $current = ""; // Current progress in the puzzle.
	public $usability = 0; // Where can we use this?

	public $lineno = 0; // For debugging / finding duplicates

	public function __construct($l="", $lineno=0) {
		if ($l=="") return;
		$lex = explode("|", $l, 3);
		$this->usability = bindec($lex[0]);
		$this->category  = strtoupper($lex[1]);
		$this->solved    = strtoupper($lex[2]);

		$this->lineno = $lineno;
	}

	// Guesses a letter in the puzzle, fills them in, returns number of spaces filled in
	public function insertLetter($l) {
		$len = strlen($this->solved);
		$l = strtoupper($l);

		$correct = 0;
		for ($i = 0; $i < $len; ++$i) {
			if ($this->current{$i} != '_') continue;
			if ($this->solved{$i} != $l) continue;
			$this->current{$i} = $this->solved{$i};
			++$correct;
		}
		return $correct;
	}

	// for Tossups. inserts random letter, returns false if everything is filled in, true otherwise
	public function insertRandomLetter() {
		$unchosen = array();
		foreach (str_split($this->current) as $k=>$l) {
			if ($l == '_') $unchosen[] = $k;
		}

		// Nothing left!
		if (count($unchosen) < 1) return false;

		$index = random::key($unchosen);
		$key = $unchosen[$index];
		$this->current{$key} = $this->solved{$key};
		return true;
	}


	// Gets a blank copy of this puzzle
	public function copy() {
		$t = new fortunePuzzle();
		$t->usability = $this->usability;
		$t->category = strtoupper($this->category);
		$t->solved = strtoupper($this->solved);
		$t->current = preg_replace("/[A-Z]/i", "_", $t->solved);
		return $t;
	}

	// "Solves" puzzle
	public function solve() {
		$this->current = $this->solved;
	}

	public function check($answer) {
		$answer = strtoupper($answer);
		$tmpsolved = strtoupper($this->solved);

		// remove "AND"
		$answer    = str_replace(array(' AND ', '-AND-'), ' ', $answer);
		$tmpsolved = str_replace(array(' AND ', '-AND-'), ' ', $tmpsolved);

		// remove anything not a letter
		$answer    = preg_replace('/[^A-Z]/i', '', $answer);
		$tmpsolved = preg_replace('/[^A-Z]/i', '', $tmpsolved);

		if ($answer == $tmpsolved) return true;
		return false;
	}

	public function anyLettersLeft($mode = -1) {
		$vowels = str_split('AEIOU');

		for ($i = 0; $i < strlen($this->solved); ++$i) {
			$a = $this->current{$i};
			$b = $this->solved{$i};

			if ($a != '_') continue;
			if (($mode == 0 || $mode == -1) && !in_array($b, $vowels)) return true;
			if (($mode == 1 || $mode == -1) && in_array($b, $vowels)) return true;
		}
		return false;
	}

	// gets current puzzle status, formatted
	public function getFormattedPuzzle($highlight = "") {
		$text = b().c(3,3).'<';
		$highlight = strtoupper($highlight);

		$lastcolor = 3;
		foreach(str_split($this->current) as $c) {
			if ($c == ' ') {
				$lastcolor = 3;
				$text .= c(3,3).'_';
			}
			else if ($c == '_') {
				if ($lastcolor != 15)
					$text .= c(1,$lastcolor = 15);
				$text .= '-';
			}
			else if ($c == $highlight) {
				if ($lastcolor != 11)
					$text .= c(1,$lastcolor = 11);
				$text .= $c;
			}
			else {
				if ($lastcolor != 0)
					$text .= c(1,$lastcolor = 0);
				$text .= $c;
			}
		}

		$text .= c(3,3).'>';
		return $text;
	}

	public function getFormattedCategory() {
		return c(11,12)."(".c(0,12)." ".$this->category." ".c(11,12).")".r();
	}

	public function getFormattedAll($highlight = "") {
		return $this->getFormattedCategory()." ".$this->getFormattedPuzzle($highlight);
	}
}
