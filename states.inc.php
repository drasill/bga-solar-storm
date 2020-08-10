<?php
// vim: tw=120:
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * SolarStorm implementation : © Christophe Badoit <gameboardarena@tof2k.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 */

if (!defined('ST_PLAYER_TURN')) {
	define('ST_PLAYER_START_OF_TURN', 2);
	define('ST_PLAYER_TURN', 3);

	define('ST_PLAYER_MOVE', 4);
	define('ST_PLAYER_SCAVENGE', 5);
	define('ST_PLAYER_SCAVENGE_PICK_CARDS', 6);
	define('ST_PLAYER_SHARE', 7);
	define('ST_PLAYER_REPAIR', 8);
	define('ST_PLAYER_DIVERT', 9);

	define('ST_PLAYER_ROOM_CREW_QUARTER', 10);
	define('ST_PLAYER_ROOM_CARGO_HOLD', 11);
	define('ST_PLAYER_ROOM_MESS_HALL', 12);
	define('ST_PLAYER_ROOM_ENGINE_ROOM', 13);
	define('ST_PLAYER_ROOM_REPAIR_CENTRE', 14);
	define('ST_PLAYER_ROOM_ARMOURY', 15);
	define('ST_PLAYER_ROOM_BRIDGE', 16);

	define('ST_PLAYER_ACTION_CANCEL', 19);
	define('ST_PLAYER_ACTION_DONE', 20);
	define('ST_PLAYER_PICK_RESOURCES_CARDS', 21);
	define('ST_PLAYER_ASK_ACTION_TOKENS_PLAY', 22);

	define('ST_PLAYER_END_TURN', 40);
	define('ST_PLAYER_DISCARD_RESOURCES', 41);
	define('ST_PLAYER_NEXT_PLAYER', 43);
	define('ST_PLAYER_RESTART_TURN', 44);

	define('ST_END_OF_GAME', 99);
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

	ST_PLAYER_START_OF_TURN => [
		'name' => 'playerStartOfTurn',
		'type' => 'game',
		'action' => 'stStartOfTurn',
		'transitions' => [
			'transPlayerTurn' => ST_PLAYER_TURN,
		],
	],

	ST_PLAYER_TURN => [
		'name' => 'playerTurn',
		'description' => clienttranslate('${actplayer} must choose an action'),
		'descriptionmyturn' => clienttranslate('${you} must choose an action'),
		'type' => 'activeplayer',
		'args' => 'argPlayerTurn',
		'possibleactions' => ['choose', 'useToken', 'restartTurn'],
		'transitions' => [
			'transPlayerMove' => ST_PLAYER_MOVE,
			'transPlayerScavenge' => ST_PLAYER_SCAVENGE,
			'transPlayerShare' => ST_PLAYER_SHARE,
			'transPlayerRepair' => ST_PLAYER_REPAIR,
			'transPlayerDivert' => ST_PLAYER_DIVERT,
			'transPlayerRoomCrewQuarter' => ST_PLAYER_ROOM_CREW_QUARTER,
			'transPlayerRoomCargoHold' => ST_PLAYER_ROOM_CARGO_HOLD,
			'transPlayerRoomMessHall' => ST_PLAYER_ROOM_MESS_HALL,
			'transPlayerRoomEngineRoom' => ST_PLAYER_ROOM_ENGINE_ROOM,
			'transPlayerRoomRepairCentre' => ST_PLAYER_ROOM_REPAIR_CENTRE,
			'transPlayerRoomArmoury' => ST_PLAYER_ROOM_ARMOURY,
			'transPlayerRoomBridge' => ST_PLAYER_ROOM_BRIDGE,
			'transActionDone' => ST_PLAYER_ACTION_DONE,
			'transEndOfGame' => ST_END_OF_GAME,
			'transPlayerRestartTurn' => ST_PLAYER_RESTART_TURN,
		],
	],

	ST_PLAYER_MOVE => [
		'name' => 'playerMove',
		'description' => clienttranslate('${actplayer} must choose a destination'),
		'descriptionmyturn' => clienttranslate('${you} must choose a destination'),
		'type' => 'activeplayer',
		'args' => 'argPlayerMove',
		'possibleactions' => ['move', 'cancel'],
		'transitions' => [
			'transActionDone' => ST_PLAYER_ACTION_DONE,
			'transActionCancel' => ST_PLAYER_ACTION_CANCEL,
		],
	],

	ST_PLAYER_SCAVENGE => [
		'name' => 'playerScavenge',
		'description' => clienttranslate('Scavenge: ${actplayer} must roll the dice'),
		'descriptionmyturn' => clienttranslate('Scavenge: ${you} must roll the dice'),
		'type' => 'activeplayer',
		'possibleactions' => ['rollDice', 'cancel'],
		'transitions' => [
			'transActionScavengePickCards' => ST_PLAYER_SCAVENGE_PICK_CARDS,
			'transActionScavengePickNothing' => ST_PLAYER_ACTION_DONE,
			'transActionCancel' => ST_PLAYER_ACTION_CANCEL,
		],
	],

	ST_PLAYER_SCAVENGE_PICK_CARDS => [
		'name' => 'playerScavengePickCards',
		'description' => clienttranslate('Scavenge: ${actplayer} must pick resources cards'),
		'descriptionmyturn' => clienttranslate('Scavenge: ${you} must pick resources cards'),
		'type' => 'activeplayer',
		'args' => 'argPlayerScavengePickCards',
		'possibleactions' => ['pickResource'],
		'transitions' => [
			'transActionScavengePickCards' => ST_PLAYER_SCAVENGE_PICK_CARDS,
			'transActionScavengeEnd' => ST_PLAYER_ACTION_DONE,
			'transEndOfGame' => ST_END_OF_GAME,
		],
	],

	ST_PLAYER_SHARE => [
		'name' => 'playerShare',
		'description' => clienttranslate('${actplayer} must share a resource'),
		'descriptionmyturn' => clienttranslate('${you} must share a resource'),
		'type' => 'activeplayer',
		'action' => 'stActionShare',
		'possibleactions' => ['pickResourceFromAnotherPlayer', 'giveResourceToAnotherPlayer', 'cancel'],
		'transitions' => [
			'transActionDone' => ST_PLAYER_ACTION_DONE,
			'transActionCancel' => ST_PLAYER_ACTION_CANCEL,
		],
	],

	ST_PLAYER_REPAIR => [
		'name' => 'playerRepair',
		'description' => clienttranslate('Repair: ${actplayer} must select a resource'),
		'descriptionmyturn' => clienttranslate('Repair: ${you} must select a resource'),
		'type' => 'activeplayer',
		'possibleactions' => ['selectResourceForRepair', 'cancel'],
		'transitions' => [
			'transActionDone' => ST_PLAYER_ACTION_DONE,
			'transActionCancel' => ST_PLAYER_ACTION_CANCEL,
		],
	],

	ST_PLAYER_DIVERT => [
		'name' => 'playerDivert',
		'description' => clienttranslate('Divert power: ${actplayer} must select resources'),
		'descriptionmyturn' => clienttranslate('Divert power: ${you} must select resources'),
		'type' => 'activeplayer',
		'possibleactions' => ['selectResourcesForDivert', 'cancel'],
		'transitions' => [
			'transActionDone' => ST_PLAYER_ACTION_DONE,
			'transActionCancel' => ST_PLAYER_ACTION_CANCEL,
		],
	],

	ST_PLAYER_ROOM_CREW_QUARTER => [
		'name' => 'playerRoomCrewQuarter',
		'description' => clienttranslate('Crew Quarters: ${actplayer} must select a meeple to move'),
		'descriptionmyturn' => clienttranslate('Crew Quarters: ${you} must select a meeple to move'),
		'type' => 'activeplayer',
		'possibleactions' => ['moveMeepleToRoom', 'cancel'],
		'transitions' => [
			'transActionDone' => ST_PLAYER_ACTION_DONE,
			'transActionCancel' => ST_PLAYER_ACTION_CANCEL,
		],
	],

	ST_PLAYER_ROOM_CARGO_HOLD => [
		'name' => 'playerRoomCargoHold',
		'description' => clienttranslate('Cargo Hold: ${actplayer} must reorder the next resource cards'),
		'descriptionmyturn' => clienttranslate('Cargo Hold: ${you} must reorder the next resource cards'),
		'type' => 'activeplayer',
		'action' => 'stPlayerRoomCargoHold',
		'args' => 'argPlayerRoomCargoHold',
		'possibleactions' => ['putBackResourceCardsInDeck'],
		'transitions' => [
			'transActionDone' => ST_PLAYER_ACTION_DONE,
		],
	],

	ST_PLAYER_ROOM_MESS_HALL => [
		'name' => 'playerRoomMessHall',
		'description' => clienttranslate('Mess Hall: ${actplayer} must take, give or swap a card with another player'),
		'descriptionmyturn' => clienttranslate('Mess Hall: ${you} must take, give or swap a card with another player'),
		'type' => 'activeplayer',
		'possibleactions' => [
			'pickResourceFromAnotherPlayer',
			'giveResourceToAnotherPlayer',
			'swapResourceWithAnotherPlayer',
			'cancel',
		],
		'transitions' => [
			'transActionDone' => ST_PLAYER_ACTION_DONE,
			'transActionCancel' => ST_PLAYER_ACTION_CANCEL,
		],
	],

	ST_PLAYER_ROOM_ENGINE_ROOM => [
		'name' => 'playerRoomEngineRoom',
		'description' => clienttranslate('Engine room: ${actplayer} must swap a resource card from the discard pile'),
		'descriptionmyturn' => clienttranslate('Engine room: ${you} must swap a resource card from the discard pile'),
		'type' => 'activeplayer',
		'args' => 'argPlayerRoomEngineRoom',
		'possibleactions' => ['swapResourceFromDiscard', 'cancel'],
		'transitions' => [
			'transActionDone' => ST_PLAYER_ACTION_DONE,
			'transActionCancel' => ST_PLAYER_ACTION_CANCEL,
		],
	],

	ST_PLAYER_ROOM_REPAIR_CENTRE => [
		'name' => 'playerRoomRepairCentre',
		'description' => clienttranslate('Repair centre: ${actplayer} must repair any room'),
		'descriptionmyturn' => clienttranslate('Repair centre: ${you} must select a room to repair'),
		'type' => 'activeplayer',
		'possibleactions' => ['selectResourceForRepair', 'cancel'],
		'transitions' => [
			'transActionDone' => ST_PLAYER_ACTION_DONE,
			'transActionCancel' => ST_PLAYER_ACTION_CANCEL,
		],
	],

	ST_PLAYER_ROOM_ARMOURY => [
		'name' => 'playerRoomArmoury',
		'description' => clienttranslate('Armoury: ${actplayer} must place protection tokens'),
		'descriptionmyturn' => clienttranslate('Armoury: ${you} must place protection tokens'),
		'type' => 'activeplayer',
		'args' => 'argPlayerRoomArmoury',
		'possibleactions' => ['putProtectionTokens', 'cancel'],
		'transitions' => [
			'transActionDone' => ST_PLAYER_ACTION_DONE,
			'transActionCancel' => ST_PLAYER_ACTION_CANCEL,
			'transPlayerRoomArmoury' => ST_PLAYER_ROOM_ARMOURY,
		],
	],

	ST_PLAYER_ROOM_BRIDGE => [
		'name' => 'playerRoomBridge',
		'description' => clienttranslate('Bridge: ${actplayer} must reorder the next damage cards'),
		'descriptionmyturn' => clienttranslate('Bridge: ${you} must reorder the next damage cards'),
		'type' => 'activeplayer',
		'action' => 'stPlayerRoomBridge',
		'args' => 'argPlayerRoomBridge',
		'possibleactions' => ['putBackDamageCardsInDeck'],
		'transitions' => [
			'transActionDone' => ST_PLAYER_ACTION_DONE,
		],
	],

	ST_PLAYER_ACTION_CANCEL => [
		'name' => 'actionCancel',
		'type' => 'game',
		'action' => 'stActionCancel',
		'transitions' => [
			'transPlayerTurn' => ST_PLAYER_TURN,
		],
	],

	ST_PLAYER_ACTION_DONE => [
		'name' => 'actionDone',
		'type' => 'game',
		'action' => 'stActionDone',
		'transitions' => [
			'transPlayerTurn' => ST_PLAYER_TURN,
			'transPlayerAskActionTokensPlay' => ST_PLAYER_ASK_ACTION_TOKENS_PLAY,
			'transPlayerPickResourcesCards' => ST_PLAYER_PICK_RESOURCES_CARDS,
		],
	],

	ST_PLAYER_PICK_RESOURCES_CARDS => [
		'name' => 'pickResources',
		'description' => clienttranslate('End of turn: ${actplayer} must pick resources cards'),
		'descriptionmyturn' => clienttranslate('End of turn: ${you} must pick resources cards'),
		'type' => 'activeplayer',
		'args' => 'argPlayerPickResourcesCards',
		'possibleactions' => ['pickResource', 'restartTurn'],
		'transitions' => [
			'transPlayerEndTurn' => ST_PLAYER_END_TURN,
			'transPlayerPickResourcesCards' => ST_PLAYER_PICK_RESOURCES_CARDS,
			'transEndOfGame' => ST_END_OF_GAME,
			'transPlayerRestartTurn' => ST_PLAYER_RESTART_TURN,
		],
	],

	ST_PLAYER_ASK_ACTION_TOKENS_PLAY => [
		'name' => 'playerAskActionTokensPlay',
		'description' => clienttranslate('End of turn: ${actplayer} can use their action tokens'),
		'descriptionmyturn' => clienttranslate('End of turn: ${you} can use you action tokens'),
		'type' => 'activeplayer',
		'possibleactions' => ['useToken', 'dontUseToken', 'restartTurn'],
		'transitions' => [
			'transActionDone' => ST_PLAYER_ACTION_DONE,
			'transPlayerRestartTurn' => ST_PLAYER_RESTART_TURN,
		],
	],

	ST_PLAYER_END_TURN => [
		'name' => 'endTurn',
		'type' => 'game',
		'action' => 'stEndTurn',
		'updateGameProgression' => true,
		'transitions' => [
			'transPlayerDiscardResources' => ST_PLAYER_DISCARD_RESOURCES,
			'transPlayerNextPlayer' => ST_PLAYER_NEXT_PLAYER,
			'transEndOfGame' => ST_END_OF_GAME,
		],
	],

	ST_PLAYER_DISCARD_RESOURCES => [
		'name' => 'playerDiscardResources',
		'description' => clienttranslate('End of turn: ${actplayer} must discard resources cards (max 6)'),
		'descriptionmyturn' => clienttranslate('End of turn: ${you} must discard resources cards (max 6)'),
		'type' => 'activeplayer',
		'args' => 'argPlayerDiscardResources',
		'possibleactions' => ['discardResources', 'restartTurn'],
		'transitions' => [
			'transPlayerNextPlayer' => ST_PLAYER_NEXT_PLAYER,
			'transPlayerRestartTurn' => ST_PLAYER_RESTART_TURN,
		],
	],

	ST_PLAYER_NEXT_PLAYER => [
		'name' => 'playerNextPlayer',
		'type' => 'game',
		'action' => 'stNextPlayer',
		'transitions' => [
			'transPlayerStartOfTurn' => ST_PLAYER_START_OF_TURN
		],
	],

	ST_PLAYER_RESTART_TURN => [
		'name' => 'playerRestartTurn',
		'type' => 'game',
		'action' => 'stPlayerRestartTurn',
		'transitions' => [
			'transPlayerStartOfTurn' => ST_PLAYER_START_OF_TURN
		],
	],

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
