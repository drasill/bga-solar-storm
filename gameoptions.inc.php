<?php

/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * SolarStorm implementation : © Christophe Badoit <gameboardarena@tof2k.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * gameoptions.inc.php
 *
 * SolarStorm game options description
 *
 * In this file, you can define your game options (= game variants).
 *
 * Note: If your game has no variant, you don't have to modify this file.
 *
 * Note²: All options defined in this file should have a corresponding "game state labels"
 *        with the same ID (see "initGameStateLabels" in solarstorm.game.php)
 *
 * !! It is not a good idea to modify this file when a game is running !!
 *
 */

$game_options = [
	100 => [
		'name' => totranslate('Game difficulty'),
		'default' => 1,
		'values' => [
			1 => [
				'name' => totranslate('Easy (8 universal cards)'),
				'tmdisplay' => totranslate('Easy (8 universal cards)'),
			],
			2 => [
				'name' => totranslate('Medium (6 universal cards)'),
				'tmdisplay' => totranslate('Medium (6 universal cards)'),
			],
			3 => [
				'name' => totranslate('Hard (4 universal cards)'),
				'tmdisplay' => totranslate('Hard (4 universal cards)'),
				'nobeginner' => true,
			],
			4 => [
				'name' => totranslate('Veteran (2 universal cards)'),
				'tmdisplay' => totranslate('Veteran (2 universal cards)'),
				'nobeginner' => true,
			],
			5 => [
				'name' => totranslate('Realist (zero universal card)'),
				'tmdisplay' => totranslate('Realist (zero universal card)'),
				'nobeginner' => true,
			],
		],
	],
	101 => [
		'name' => totranslate('Realistic mode'),
		'default' => 1,
		'values' => [
			0 => [
				'name' => totranslate('No'),
				'tmdisplay' => '',
			],
			1 => [
				'name' => totranslate('Yes'),
				'tmdisplay' => totranslate('Realistic mode'),
				'description' => totranslate(
					'The number of resources cards in the deck is not shown; end of game is not easily predicted.'
				),
				'nobeginner' => true,
			],
		],
	],
];
