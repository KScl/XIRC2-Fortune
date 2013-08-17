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

final class fortuneWheel {
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
}
