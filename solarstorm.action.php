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

	public function choose() {
		self::setAjaxMode();
		$actionName = self::getArg('actionName', AT_enum, true, null, ['move', 'scavenge', 'share', 'repair', 'room']);
		$this->game->actionChoose($actionName);
		self::ajaxResponse();
	}

	public function move() {
		self::setAjaxMode();
		$position = self::getArg('position', AT_posint, true);
		$this->game->actionMove((int) $position);
		self::ajaxResponse();
	}

	public function rollDice() {
		self::setAjaxMode();
		$this->game->actionRollDice();
		self::ajaxResponse();
	}

	public function cancel() {
		self::setAjaxMode();
		$this->game->actionCancel();
		self::ajaxResponse();
	}

	public function pickResource() {
		self::setAjaxMode();
		$cardId = (int) self::getArg('cardId', AT_posint, true);
		$this->game->actionPickResource($cardId);
		self::ajaxResponse();
	}

	public function discardResource() {
		self::setAjaxMode();
		$cardId = (int) self::getArg('cardId', AT_posint, true);
		$this->game->actionDiscardResource($cardId);
		self::ajaxResponse();
	}

	public function selectResourceForRepair() {
		self::setAjaxMode();
		$cardId = (int) self::getArg('cardId', AT_posint, true);
		$this->game->actionSelectResourceForRepair($cardId);
		self::ajaxResponse();
	}

	public function moveMeepleToRoom() {
		self::setAjaxMode();
		$playerId = (int) self::getArg('playerId', AT_posint, true);
		$position = (int) self::getArg('position', AT_posint, true);
		$this->game->actionMoveMeepleToRoom($playerId, $position);
		self::ajaxResponse();
	}

	public function putBackResourceCardsInDeck() {
		self::setAjaxMode();
		$cardIds = self::getArg('cardIds', AT_numberlist, true);
		$cardIds = preg_split('/\D/', $cardIds, -1, PREG_SPLIT_NO_EMPTY);
		$this->game->actionPutBackResourceCardsInDeck($cardIds);
		self::ajaxResponse();
	}

	public function putBackDamageCardInDeck() {
		self::setAjaxMode();
		$cardId = (int) self::getArg('cardId', AT_posint, true);
		$this->game->actionPutBackDamageCardInDeck($cardId);
		self::ajaxResponse();
	}

	public function pickResourceFromAnotherPlayer() {
		self::setAjaxMode();
		$cardId = (int) self::getArg('cardId', AT_posint, true);
		$this->game->actionPickResourceFromAnotherPlayer($cardId);
		self::ajaxResponse();
	}

	public function giveResourceToAnotherPlayer() {
		self::setAjaxMode();
		$cardId = (int) self::getArg('cardId', AT_posint, true);
		$playerId = (int) self::getArg('playerId', AT_posint, true);
		$this->game->actionGiveResourceToAnotherPlayer($cardId, $playerId);
		self::ajaxResponse();
	}

	public function swapResourceWithAnotherPlayer() {
		self::setAjaxMode();
		$cardId = (int) self::getArg('cardId', AT_posint, true);
		$card2Id = (int) self::getArg('card2Id', AT_posint, true);
		$this->game->actionSwapResourceWithAnotherPlayer($cardId, $card2Id);
		self::ajaxResponse();
	}

	public function swapResourceFromDiscard() {
		self::setAjaxMode();
		$cardId = (int) self::getArg('cardId', AT_posint, true);
		$card2Id = (int) self::getArg('card2Id', AT_posint, true);
		$this->game->actionSwapResourceFromDiscard($cardId, $card2Id);
		self::ajaxResponse();
	}
}
