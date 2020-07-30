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
			],
			4 => [
				'name' => totranslate('Veteran (2 universal cards)'),
				'tmdisplay' => totranslate('Veteran (2 universal cards)'),
			],
			5 => [
				'name' => totranslate('Realist (zero universal card)'),
				'tmdisplay' => totranslate('Realist (zero universal card)'),
			],
		],
	],
];
