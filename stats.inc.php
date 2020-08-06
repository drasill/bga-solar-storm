<?php

/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * SolarStorm implementation : © Christophe Badoit <gameboardarena@tof2k.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 */

/*
 In this file, you are describing game statistics, that will be displayed at the end of the
 game.

 !! After modifying this file, you must use "Reload  statistics configuration" in BGA Studio backoffice
 ("Control Panel" / "Manage Game" / "Your Game")

 There are 2 types of statistics:
 _ table statistics, that are not associated to a specific player (ie: 1 value for each game).
 _ player statistics, that are associated to each players (ie: 1 value for each player in the game).

 Statistics types can be "int" for integer, "float" for floating point values, and "bool" for boolean

 Once you defined your statistics there, you can start using "initStat", "setStat" and "incStat" method
 in your game logic, using statistics names defined below.

 !! It is not a good idea to modify this file when a game is running !!

 If your game is already public on BGA, please read the following before any change:
 http://en.doc.boardgamearena.com/Post-release_phase#Changes_that_breaks_the_games_in_progress

 Notes:
 * Statistic index is the reference used in setStat/incStat/initStat PHP method
 * Statistic index must contains alphanumerical characters and no space. Example: 'turn_played'
 * Statistics IDs must be >=10
 * Two table statistics can't share the same ID, two player statistics can't share the same ID
 * A table statistic can have the same ID than a player statistics
 * Statistics ID is the reference used by BGA website. If you change the ID, you lost all historical statistic data. Do NOT re-use an ID of a deleted statistic
 * Statistic name is the English description of the statistic as shown to players

*/

$stats_type = [
	// Statistics global to table
	'table' => [
		'turns_number' => [
			'id' => 10,
			'name' => totranslate('Number of turns'),
			'type' => 'int',
		],
		'room_damaged' => [
			'id' => 11,
			'name' => totranslate('Number of damage received'),
			'type' => 'int',
		],
		'room_protected' => [
			'id' => 12,
			'name' => totranslate('Number of damage ignored (room protected)'),
			'type' => 'int',
		],
		'repair_done' => [
			'id' => 13,
			'name' => totranslate('Number of reparation'),
			'type' => 'int',
		],
		'power_diverted' => [
			'id' => 14,
			'name' => totranslate('Number of power diverted'),
			'type' => 'int',
		],
		'resources_picked' => [
			'id' => 15,
			'name' => totranslate('Number of resource cards picked'),
			'type' => 'int',
		],
	],

	// Statistics existing for each player
	'player' => [
		'turns_number' => [
			'id' => 10,
			'name' => totranslate('Number of turns'),
			'type' => 'int',
		],
		'repair_done' => [
			'id' => 11,
			'name' => totranslate('Number of reparation'),
			'type' => 'int',
		],
		'power_diverted' => [
			'id' => 12,
			'name' => totranslate('Number of power diverted'),
			'type' => 'int',
		],
		'resources_picked' => [
			'id' => 13,
			'name' => totranslate('Number of resource cards picked'),
			'type' => 'int',
		],
		'action_move' => [
			'id' => 14,
			'name' => totranslate('Number of movement to other rooms'),
			'type' => 'int',
		],

		'action_room_bridge' => [
			'id' => 15,
			'name' => sprintf(totranslate('Number of room activation (%s)'), totranslate('Bridge')),
			'type' => 'int',
		],
		'action_room_armoury' => [
			'id' => 16,
			'name' => sprintf(totranslate('Number of room activation (%s)'), totranslate('Armoury')),
			'type' => 'int',
		],
		'action_room_cargo_hold' => [
			'id' => 17,
			'name' => sprintf(totranslate('Number of room activation (%s)'), totranslate('Cargo Hold')),
			'type' => 'int',
		],
		'action_room_crew_quarters' => [
			'id' => 18,
			'name' => sprintf(totranslate('Number of room activation (%s)'), totranslate('Crew Quarters')),
			'type' => 'int',
		],
		'action_room_engine_room' => [
			'id' => 19,
			'name' => sprintf(totranslate('Number of room activation (%s)'), totranslate('Engine Room')),
			'type' => 'int',
		],
		'action_room_medical_bay' => [
			'id' => 20,
			'name' => sprintf(totranslate('Number of room activation (%s)'), totranslate('Medical Bay')),
			'type' => 'int',
		],
		'action_room_repair_centre' => [
			'id' => 21,
			'name' => sprintf(totranslate('Number of room activation (%s)'), totranslate('Repair Centre')),
			'type' => 'int',
		],
		'action_room_mess_hall' => [
			'id' => 22,
			'name' => sprintf(totranslate('Number of room activation (%s)'), totranslate('Mess Hall')),
			'type' => 'int',
		],
	],
];
