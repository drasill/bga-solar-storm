<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * SolarStorm implementation : © Christophe Badoit <gameboardarena@tof2k.com>
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
		$actionName = self::getArg('actionName', AT_enum, true, null, ['move', 'scavenge', 'share', 'repair', 'room', 'divert', 'token']);
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

	public function discardResources() {
		self::setAjaxMode();
		$cardIds = self::getArg('cardIds', AT_numberlist, true);
		$cardIds = preg_split('/\D/', $cardIds, -1, PREG_SPLIT_NO_EMPTY);
		$this->game->actionDiscardResources($cardIds);
		self::ajaxResponse();
	}

	public function selectResourceForRepair() {
		self::setAjaxMode();
		$cardId = (int) self::getArg('cardId', AT_posint, true);
		$typeId = self::getArg('resourceType', AT_enum, false, null, ['energy', 'nanobots', 'metal', 'data']);
		$position = self::getArg('position', AT_posint, false, null);
		$this->game->actionSelectResourceForRepair($cardId, $typeId, $position);
		self::ajaxResponse();
	}

	public function selectResourcesForDivert() {
		self::setAjaxMode();
		$cardIds = self::getArg('cardIds', AT_numberlist, true);
		$cardIds = preg_split('/\D/', $cardIds, -1, PREG_SPLIT_NO_EMPTY);
		$this->game->actionSelectResourcesForDivert($cardIds);
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

	public function putBackDamageCardsInDeck() {
		self::setAjaxMode();
		$cardIds = self::getArg('cardIds', AT_numberlist, true);
		$cardIds = preg_split('/\D/', $cardIds, -1, PREG_SPLIT_NO_EMPTY);
		$this->game->actionPutBackDamageCardsInDeck($cardIds);
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

	public function useToken() {
		self::setAjaxMode();
		$this->game->actionUseToken();
		self::ajaxResponse();
	}

	public function dontUseToken() {
		self::setAjaxMode();
		$this->game->actionDontUseToken();
		self::ajaxResponse();
	}

	public function putProtectionTokens() {
		self::setAjaxMode();
		$positions = self::getArg('positions', AT_numberlist, true);
		$positions = preg_split('/\D/', $positions, -1, PREG_SPLIT_NO_EMPTY);
		$this->game->actionPutProtectionTokens($positions);
		self::ajaxResponse();
	}
}
