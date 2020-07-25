<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * SolarStorm implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on https://boardgamearena.com.
 * See http://en.doc.boardgamearena.com/Studio for more information.
 * -----
 *
 * solarstorm.action.php
 *
 * SolarStorm main action entry point
 *
 *
 * In this file, you are describing all the methods that can be called from your
 * user interface logic (javascript).
 *
 * If you define a method "myAction" here, then you can call it from your javascript code with:
 * this.ajaxcall( "/solarstorm/solarstorm/myAction.html", ...)
 *
 */

class action_solarstorm extends APP_GameAction {
	// Constructor: please do not modify
	public function __default() {
		if (self::isArg('notifwindow')) {
			$this->view = 'common_notifwindow';
			$this->viewArgs['table'] = self::getArg('table', AT_posint, true);
		} else {
			$this->view = 'solarstorm_solarstorm';
			self::trace('Complete reinitialization of board game');
		}
	}

	// TODO: defines your action entry points there

	public function choose() {
		self::setAjaxMode();
		$actionName = self::getArg('actionName', AT_enum, true, null, ['move']);
		$this->game->actionChoose($actionName);
		self::ajaxResponse();
	}

	public function move() {
		self::setAjaxMode();
		$position = self::getArg('position', AT_posint, true);
		$this->game->actionMove((int)$position);
		self::ajaxResponse();
	}
}
