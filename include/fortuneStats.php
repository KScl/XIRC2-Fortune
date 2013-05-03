<?php
/*
 * XIRC2 IRC Bot Module - Fortune
 * Copyright (c) 2012-2013 Inuyasha.
 * All rights reserved.
 *
 * include\fortuneStats.php
 * Statistic keeping classes.
 */

class fortuneContestantData {
	public $name       = '';
	public $lastplay   = 0;

	public $money      = 0;

	public $bestgame   = 0;
	public $bestwin    = 0;
	public $bestloss   = 0;
	public $bestsolo   = 0;

	public $games      = 0;
	public $sologames  = 0;
	public $wins       = 0;
	public $solowins   = 0;

	// serialize/unserialize for saving and loading
}

class fortuneStats {
	public $bestgame     =  0;
	public $bestgamename = 'nobody';

	public $bestwin      =  0;
	public $bestwinname  = 'nobody';

	public $bestloss     =  0;
	public $bestlossname = 'nobody';

	public $bestsolo     =  0;
	public $bestsoloname = 'nobody';

	public $totalcash    =  0;
	public $games        =  0;

	public $lastwinner = array();

	// serialize/unserialize for saving and loading
}