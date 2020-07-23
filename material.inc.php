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
