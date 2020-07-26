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

if (!defined('ST_PLAYER_TURN')) {
	define('ST_PLAYER_TURN', 2);
	define('ST_PLAYER_MOVE', 3);
	define('ST_PLAYER_SCAVENGE', 4);
	define('ST_PLAYER_SCAVENGE_PICK_CARDS', 5);
	define('ST_PLAYER_ACTION_DONE', 20);
	define('ST_PLAYER_PICK_RESOURCES_CARDS', 21);
	define('ST_PLAYER_END_TURN', 40);
}

$machinestates = [
	// The initial state. Please do not modify.
	1 => [
		'name' => 'gameSetup',
		'description' => '',
		'type' => 'manager',
		'action' => 'stGameSetup',
		'transitions' => ['' => 2],
	],

	ST_PLAYER_TURN => [
		'name' => 'playerTurn',
		'description' => clienttranslate('${actplayer} must choose an action'),
		'descriptionmyturn' => clienttranslate('${you} must choose an action'),
		'type' => 'activeplayer',
		'args' => 'argPlayerTurn',
		'possibleactions' => ['choose'],
		'transitions' => [
			'transMove' => ST_PLAYER_MOVE,
		],
	],

	ST_PLAYER_MOVE => [
		'name' => 'playerMove',
		'description' => clienttranslate(
			'${actplayer} must choose a destination'
		),
		'descriptionmyturn' => clienttranslate(
			'${you} must choose a destination'
		),
		'type' => 'activeplayer',
		'args' => 'argPlayerMove',
		'possibleactions' => ['move', 'cancel'],
		'transitions' => [
			'transActionDone' => ST_PLAYER_ACTION_DONE,
			'transActionCancel' => ST_PLAYER_TURN,
		],
	],

	ST_PLAYER_SCAVENGE => [
		'name' => 'playerScavenge',
		'description' => clienttranslate('${actplayer} must roll the dice'),
		'descriptionmyturn' => clienttranslate('${you} must roll the dice'),
		'type' => 'activeplayer',
		'args' => 'argPlayerScavenge',
		'possibleactions' => ['roll', 'cancel'],
		'transitions' => [
			'transActionRoll' => ST_PLAYER_SCAVENGE_PICK_CARDS,
			'transActionRollNone' => ST_PLAYER_TURN,
			'transActionCancel' => ST_PLAYER_TURN,
		],
	],

	ST_PLAYER_SCAVENGE_PICK_CARDS => [
		'name' => 'playerScavenge',
		'description' => clienttranslate(
			'${actplayer} must pick resources cards'
		),
		'descriptionmyturn' => clienttranslate(
			'${you} must pick resources cards'
		),
		'type' => 'activeplayer',
		'args' => 'argPlayerScavenge',
		'possibleactions' => ['pick'],
		'transitions' => [
			'transActionRoll' => ST_PLAYER_SCAVENGE_PICK_CARDS,
			'transActionRollNone' => ST_PLAYER_TURN,
			'transActionCancel' => ST_PLAYER_TURN,
		],
	],

	ST_PLAYER_ACTION_DONE => [
		'name' => 'actionDone',
		'type' => 'game',
		'action' => 'stActionDone',
		'updateGameProgression' => true,
		'transitions' => [
			'transPlayerTurn' => ST_PLAYER_TURN,
			'transPlayerPickResourcesCards' => ST_PLAYER_PICK_RESOURCES_CARDS,
		],
	],

	ST_PLAYER_PICK_RESOURCES_CARDS => [
		'name' => 'pickResources',
		'description' => clienttranslate(
			'${actplayer} must pick resources cards'
		),
		'descriptionmyturn' => clienttranslate(
			'${you} must pick resources cards'
		),
		'type' => 'activeplayer',
		'possibleactions' => ['pickResource'],
		'transitions' => [
			'transPlayerEndTurn' => ST_PLAYER_END_TURN,
			'transPlayerPickResourcesCards' => ST_PLAYER_PICK_RESOURCES_CARDS,
		],
	],

	ST_PLAYER_END_TURN => [
		'name' => 'endTurn',
		'type' => 'game',
		'action' => 'stEndTurn',
		'updateGameProgression' => true,
		'transitions' => [
			'transPlayerTurn' => ST_PLAYER_TURN,
		],
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
