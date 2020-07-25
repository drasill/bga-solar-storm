<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * SolarStorm implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * states.inc.php
 *
 * SolarStorm game states description
 *
 */

$machinestates = [
	// The initial state. Please do not modify.
	1 => [
		'name' => 'gameSetup',
		'description' => '',
		'type' => 'manager',
		'action' => 'stGameSetup',
		'transitions' => ['' => 2],
	],

	2 => [
		'name' => 'playerTurn',
		'description' => clienttranslate('${actplayer} must play your actions'),
		'descriptionmyturn' => clienttranslate('${you} must play their actions'),
		'type' => 'activeplayer',
		'possibleactions' => ['playCard', 'pass'],
		'transitions' => ['playCard' => 2, 'pass' => 2],
	],

	/*
    Examples:
    
    2 => [
        "name" => "nextPlayer",
        "description" => '',
        "type" => "game",
        "action" => "stNextPlayer",
        "updateGameProgression" => true,   
        "transitions" => [ "endGame" => 99, "nextPlayer" => 10 ]
    ],
    
    10 => [
        "name" => "playerTurn",
        "description" => clienttranslate('${actplayer} must play a card or pass'),
        "descriptionmyturn" => clienttranslate('${you} must play a card or pass'),
        "type" => "activeplayer",
        "possibleactions" => [ "playCard", "pass" ],
        "transitions" => [ "playCard" => 2, "pass" => 2 ]
    ], 

*/

	// Final state.
	// Please do not modify (and do not overload action/args methods).
	99 => [
		'name' => 'gameEnd',
		'description' => clienttranslate('End of game'),
		'type' => 'manager',
		'action' => 'stGameEnd',
		'args' => 'argGameEnd',
	],
];
