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
 * material.inc.php
 *
 * SolarStorm game material description
 *
 * Here, you can describe the material of your game with PHP variables.
 *
 * This file is loaded in your game logic class constructor, ie these variables
 * are available everywhere in your game logic code.
 *
 */

/*

Example:

$this->card_types = array(
    1 => array( "card_name" => ...,
                ...
              )
);

*/

$this->resourceTypes = [
	[
		'id' => 'data',
		'name' => clienttranslate('Data'),
		'nametr' => self::_('Data'),
	],
	[
		'id' => 'metal',
		'name' => clienttranslate('Metal'),
		'nametr' => self::_('Metal'),
	],
	[
		'id' => 'nanobots',
		'name' => clienttranslate('Nanobots'),
		'nametr' => self::_('Nanobots'),
	],
	[
		'id' => 'energy',
		'name' => clienttranslate('Energy'),
		'nametr' => self::_('Energy'),
	],
	[
		'id' => 'universal',
		'name' => clienttranslate('Universal'),
		'nametr' => self::_('Universal'),
	],
];

$this->roomInfos = [
	0 => [
		'slug' => 'energy-core',
		'name' => self::_('Energy Core'),
		'description' => self::_(
			'When all rooms have diverted power, get here and use 1 action to reactivate the Energy Core'
		),
	],
	1 => [
		'slug' => 'mess-hall',
		'name' => self::_('Mess Hall'),
		'description' => self::_(
			'Give, take or exchange a resource card with another player'
		),
	],
	2 => [
		'slug' => 'repair-centre',
		'name' => self::_('Repair Centre'),
		'description' => self::_(
			'Repair a damaged room by one space on the Repair Track. Discard the matching card.'
		),
	],
	3 => [
		'slug' => 'medical-bay',
		'name' => self::_('Medical Bay'),
		'description' => self::_(
			'Take two actions tokens when starting in this room'
		),
	],
	4 => [
		'slug' => 'engine-room',
		'name' => self::_('Engine Room'),
		'description' => self::_(
			'Swap a card from your hand with one from the discard pile'
		),
	],
	5 => [
		'slug' => 'crew-quarters',
		'name' => self::_('Crew Quarters'),
		'description' => self::_(
			"Move a player's meeple to a rooms that has another meeple in it"
		),
	],
	6 => [
		'slug' => 'cargo-hold',
		'name' => self::_('Cargo Hold'),
		'description' => self::_(
			'Look at the next 5 resources cards. Then put them back in any order.'
		),
	],
	7 => [
		'slug' => 'armoury',
		'name' => self::_('Armoury'),
		'description' => self::_(
			'Place 2 protection tokens on any rooms(s) (this ends at the start of your next turn)'
		),
	],
	8 => [
		'slug' => 'bridge',
		'name' => self::_('Bridge'),
		'description' => self::_(
			'Look at the next 3 Damage cards and put them back in any order.'
		),
	],
];

$this->damageCardsInfos = [
	['bridge'],
	['crew-quarters'],
	['mess-hall'],
	['repair-centre'],
	['engine-room'],
	['cargo-hold'],
	['armoury'],
	['medical-bay'],
	['engine-room', 'repair-centre'],
	['medical-bay', 'crew-quarters'],
	['repair-centre', 'cargo-hold'],
	['bridge', 'armoury'],
	['armoury', 'mess-hall'],
	['bridge', 'cargo-hold'],
	['crew-quarters', 'mess-hall'],
	['engine-room', 'medical-bay'],
	['bridge', 'medical-bay', 'mess-hall'],
	['bridge', 'engine-room', 'crew-quarters'],
	['engine-room', 'armoury', 'cargo-hold'],
	['medical-bay', 'cargo-hold', 'mess-hall'],
	['engine-room', 'repair-centre', 'mess-hall'],
	['bridge', 'repair-centre', 'crew-quarters'],
	['medical-bay', 'repair-centre', 'armoury'],
	['crew-quarters', 'armoury', 'cargo-hold'],
];