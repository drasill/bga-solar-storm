<?php
// vim: tw=120:
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * SolarStorm implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 */

require_once APP_GAMEMODULE_PATH . 'module/table/table.game.php';

require_once 'modules/SolarStormRoom.php';
require_once 'modules/SolarStormRooms.php';
require_once 'modules/SolarStormPlayer.php';
require_once 'modules/SolarStormPlayers.php';

class SolarStorm extends Table {
	/** @var SolarStormRooms */
	private $rooms;
	/** @var SolarStormPlayers */
	private $ssPlayers;
	/** @var Deck */
	private $resourceCards;
	/** @var Deck */
	private $damageCards;

	function __construct() {
		parent::__construct();
		self::initGameStateLabels([
			// If the player has picked a resourceCard from the deck (0/1)
			'resourcePickedFromDeck' => 11,
			// When scavenging, number of cards left to pick
			'scavengeNumberOfCards' => 12,
			// Initial resource deck size
			'initialResourceDeckSize' => 14,

			// Options
			// Game difficulty (number of universal cards)
			'gameDifficulty' => 100,
		]);
		$this->rooms = new SolarStormRooms($this);
		$this->ssPlayers = new SolarStormPlayers($this);

		$this->resourceCards = self::getNew('module.common.deck');
		$this->resourceCards->init('resource_card');

		$this->damageCards = self::getNew('module.common.deck');
		$this->damageCards->init('damage_card');
	}

	protected function getGameName() {
		// Used for translations and stuff. Please do not modify.
		return 'solarstorm';
	}

	/*
	 * setupNewGame:
	 * This method is called only once, when a new game is launched.
	 * In this method, you must setup the game according to the game rules, so that
	 * the game is ready to be played.
	 */
	protected function setupNewGame($players, $options = []) {
		$gameinfos = self::getGameinfos();
		$defaultColors = $gameinfos['player_colors'];

		// Create players
		// Note: if you added some extra field on "player" table in the database (dbmodel.sql), you can initialize it there.
		$sql = 'INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES ';
		$values = [];
		foreach ($players as $playerId => $player) {
			$color = array_shift($defaultColors);
			$values[] =
				"('" .
				$playerId .
				"','$color','" .
				$player['player_canal'] .
				"','" .
				addslashes($player['player_name']) .
				"','" .
				addslashes($player['player_avatar']) .
				"')";
		}
		$sql .= implode($values, ',');
		self::DbQuery($sql);
		self::reloadPlayersBasicInfos();
		$this->ssPlayers->load();

		/************ Start the game initialization *****/

		self::setGameStateInitialValue('resourcePickedFromDeck', 0);
		self::setGameStateInitialValue('scavengeNumberOfCards', 0);
		self::setGameStateInitialValue('initialResourceDeckSize', 0);

		$this->rooms->generateRooms();

		$this->initializeDecks();

		// Activate first player (which is in general a good idea :) )
		$this->activeNextPlayer();

		/************ End of the game initialization *****/
	}

	private function initializeDecks() {
		// Resource cards
		$cards = [];
		$difficultyMap = [
			1 => 8,
			2 => 6,
			3 => 4,
			4 => 2,
			5 => 0,
		];
		$gameDifficulty = self::getGameStateValue('gameDifficulty');
		$total = 0;
		foreach ($this->resourceTypes as $resourceType) {
			$nbr = 15;
			if ($resourceType['id'] === 'universal') {
				$nbr = $difficultyMap[$gameDifficulty] ?? 0;
			}
			if ($nbr <= 0) {
				continue;
			}
			$cards[] = [
				'type' => $resourceType['id'],
				'type_arg' => null,
				'nbr' => $nbr,
			];
			$total += $nbr;
		}

		self::setGameStateValue('initialResourceDeckSize', $total);
		$this->resourceCards->createCards($cards, 'deck');
		$this->resourceCards->shuffle('deck');

		// Distribute initial resourceCards
		$numCardsByPlayer = 2;
		switch (count($this->ssPlayers->getPlayers())) {
			case 3:
				$numCardsByPlayer = 3;
				break;
			case 2:
				$numCardsByPlayer = 4;
				break;
		}
		foreach ($this->ssPlayers->getPlayers() as $player) {
			$cards = $this->resourceCards->pickCards($numCardsByPlayer, 'deck', $player->getId());
		}
		// Reveal 2 cards on table
		$this->assertResourceCardsOnTable(false);

		// Damage cards
		$cards = [];
		$types = ['1room', '2room', '3room'];
		foreach ($types as $index => $nbRoom) {
			$cards[$nbRoom] = [];
			for ($i = 0; $i < 8; $i++) {
				$cards[$nbRoom][] = [
					'type' => $index * 8 + $i,
					'type_arg' => null,
					'nbr' => 1,
				];
				shuffle($cards[$nbRoom]);
			}
		}
		$cards = array_merge($cards['3room'], $cards['2room'], $cards['1room']);

		$this->damageCards->createCards($cards, 'deck');

		$this->drawDamageCard('bottom', false);
		$this->drawDamageCard('bottom', false);
	}

	private function drawDamageCard(string $from, bool $notify = true): void {
		if (!in_array($from, ['top', 'bottom'])) {
			throw new \Exception('Invalid position to draw damage card from');
		}

		$cards = $this->damageCards->getCardsInLocation('deck', null, 'location_arg');
		if ($from === 'bottom') {
			$card = $cards[0];
		} else {
			$card = $cards[count($cards) - 1];
		}

		$this->damageCards->moveCard($card['id'], 'discard');
		if ($notify) {
			$player = $this->ssPlayers->getActive();
			$this->notifyAllPlayers(
				'updateDamageDiscard',
				'${player_name} draws the next damage card',
				[
					'cards' => [$card],
				] + $player->getNotificationArgs()
			);
		}

		$roomsSlugs = $this->damageCardsInfos[$card['type']];
		$updatedRooms = [];
		$protectedRooms = [];
		foreach ($roomsSlugs as $roomsSlug) {
			$room = $this->rooms->getRoomBySlug($roomsSlug);
			if ($room->isProtected()) {
				$room->removeOldestProtectionToken();
				$protectedRooms[] = $room->toArray();
			} else {
				if ($room->getDamageCount() === 3) {
					$this->triggerEndOfGame('damage');
					return;
				}
				$room->doDamage();
			}
			$room->save();
			$updatedRooms[] = $room->toArray();
		}

		if (!empty($updatedRooms) && $notify) {
			$roomNames = join(
				', ',
				array_map(function ($r) {
					return $r['name'];
				}, $updatedRooms)
			);
			$this->notifyAllPlayers('updateRooms', clienttranslate('The room(s) ${roomNames} receive damage'), [
				'rooms' => $updatedRooms,
				'roomNames' => $roomNames,
			]);
		}
		if (!empty($protectedRooms) && $notify) {
			$roomNames = join(
				', ',
				array_map(function ($r) {
					return $r['name'];
				}, $protectedRooms)
			);
			$this->notifyAllPlayers('message', clienttranslate('The room(s) ${roomNames} were protected !'), [
				'roomNames' => $roomNames,
			]);
		}
	}

	/**
	 * Assert presence of 2 resources cards on the table.
	 */
	private function assertResourceCardsOnTable(bool $notify = true): void {
		$currentCnt = $this->resourceCards->countCardInLocation('table');
		$needToDrawCnt = 2 - $currentCnt;

		if ($needToDrawCnt <= 0) {
			return;
		}

		$cards = $this->resourceCards->pickCardsForLocation($needToDrawCnt, 'deck', 'table');
		if ($this->resourceCards->countCardInLocation('deck') == 0) {
			$this->triggerEndOfGame('resources');
			return;
		}

		if ($notify) {
			$resourceCardsNbr = $this->resourceCards->countCardInLocation('deck');
			if ($notify) {
				$this->notifyAllPlayers('addResourcesCardsOnTable', '', [
					'cards' => $cards,
					'resourceCardsNbr' => $resourceCardsNbr,
				]);
			}
		}
	}

	protected function getAllDatas() {
		$result = [];

		$result['rooms'] = $this->rooms->toArray();
		$result['ssPlayers'] = $this->ssPlayers->toArray();
		$result['resourceCardsNbrInitial'] = (int) self::getGameStateValue('initialResourceDeckSize');
		$result['resourceCardsNbr'] = $this->resourceCards->countCardInLocation('deck');
		$result['resourceTypes'] = array_values($this->resourceTypes);

		$result['damageCardsNbr'] = $this->damageCards->countCardInLocation('deck');
		$result['damageCardsDiscarded'] = $this->damageCards->getCardsInLocation('discard');
		$result['resourceCardsOnTable'] = $this->resourceCards->getCardsInLocation('table');

		$data = [];
		foreach ($this->ssPlayers->getPlayers() as $player) {
			$data[$player->getId()] = array_values($this->resourceCards->getCardsInLocation('hand', $player->getId()));
		}
		$result['resourceCards'] = $data;

		return $result;
	}

	private function triggerEndOfGame(string $reason): void {
		$message = '';
		switch ($reason) {
			case 'damage':
				$message = clienttranslate('End of game ! All players lose, as a fully damaged room receives new damage.');
				break;
			case 'resources':
				$message = clienttranslate('End of game ! All players lose, as the resources deck is empty.');
				break;
			case 'energy-core':
				$message = clienttranslate('End of game ! All players win, congratulations !.');
				$sql = 'UPDATE player SET player_score = 1';
				self::DbQuery($sql);
				break;
		}
		$this->notifyAllPlayers('endOfGame', $message, []);
		$this->gamestate->nextState('transEndOfGame');
	}

	/*
        getGameProgression:
        
        Compute and return the current game progression.
        The number returned must be an integer beween 0 (=the game just started) and
        100 (= the game is finished or almost finished).
    
        This method is called each time we are in a game state with the "updateGameProgression" property set to true 
        (see states.inc.php)
    */
	function getGameProgression() {
		// TODO: compute and return the game progression

		return 0;
	}

	//////////////////////////////////////////////////////////////////////////////
	//////////// Utility functions
	////////////

	private function notifyPlayerData(SolarStormPlayer $player, string $message = '', array $args = []): void {
		$this->notifyAllPlayers(
			'updatePlayerData',
			$message,
			[
				'position' => $player->getPosition(),
				'actionsTokens' => $player->getActionsTokens(),
			] +
				$args +
				$player->getNotificationArgs()
		);
	}

	private function getPlayersIdsInTheSameRoom($excludeActive = false): array {
		$activePlayer = $this->ssPlayers->getActive();
		$playersInTheSameRoom = $this->ssPlayers->getPlayersAtPosition($activePlayer->getPosition());
		$ids = [];
		foreach ($playersInTheSameRoom as $player) {
			if ($excludeActive && $activePlayer->getId() === $player->getId()) {
				continue;
			}
			$ids[] = $player->getId();
		}
		return $ids;
	}

	private function whereDoesPlayerCanPickResourceFrom(): array {
		$stateName = $this->gamestate->state()['name'];
		$from = [];
		if ($stateName === 'pickResources') {
			// If we are in the 'pickResources' state (phase 2)
			$from[] = 'deck';
			$previouslyPickedFromDeck = (bool) self::getGameStateValue('resourcePickedFromDeck');
			if (!$previouslyPickedFromDeck) {
				$from[] = 'table';
			}
		} elseif ($stateName === 'playerScavengePickCards') {
			// If we are in the 'playerScavengePickCards' state (player action)
			$from[] = 'deck';
			$from[] = 'table';
		}
		return $from;
	}

	public function repairRoomWithResource(SolarStormRoom $room, string $resourceType): void {
		$resourceInfo = $this->resourceTypes[$resourceType];

		if ($room->isDiverted()) {
		}

		foreach ($room->getResources() as $resIndex => $resType) {
			if ($resType !== $resourceType) {
				continue;
			}
			$damage = $room->getDamage();
			if (!$room->isDiverted() && !$damage[$resIndex]) {
				throw new BgaUserException(
					sprintf(self::_('This room cannot be repaired with resource %s'), $resourceInfo['nametr'])
				);
			}

			if ($room->isDiverted()) {
				$room->setDamage([false, false, false]);
			} else {
				$damage[$resIndex] = false;
				$room->setDamage($damage);
			}
			return;
		}
		throw new BgaUserException(
			sprintf(self::_('Cannot repair this room with the resource %s'), $resourceInfo['nametr'])
		);
	}

	private function divertRoomWithResources(SolarStormRoom $room, array $cards): void {
		if ($room->isDiverted()) {
			throw new BgaUserException(self::_('This room already has its power diverted'));
		}
		if ($room->getDamageCount() > 0) {
			throw new BgaUserException(self::_('This room is damaged and can\'t have its power diverted'));
		}

		$cards = array_combine(array_column($cards, 'id'), $cards);

		// For each needed resource ...
		foreach ($room->getDivertResources() as $needed) {
			$resourceInfo = $this->resourceTypes[$needed];
			// Find first matching resource
			$card = current(
				array_filter($cards, function ($card) use ($needed) {
					return $card['type'] === $needed;
				})
			);
			// Fallback on first universal resource
			if (!$card) {
				$card = current(
					array_filter($cards, function ($card) {
						return $card['type'] === 'universal';
					})
				);
			}
			if (!$card) {
				throw new BgaUserException(
					sprintf(self::_('This room needs a resource %s to be diverted'), $resourceInfo['nametr'])
				);
			}
			unset($cards[$card['id']]);
		}
		$room->setDiverted(true);
	}

	//////////////////////////////////////////////////////////////////////////////
	//////////// Player actions
	////////////

	public function actionChoose($actionName): void {
		$player = $this->ssPlayers->getActive();
		self::checkAction('choose');
		switch ($actionName) {
			case 'move':
				$this->gamestate->nextState('transPlayerMove');
				break;
			case 'scavenge':
				$this->gamestate->nextState('transPlayerScavenge');
				break;
			case 'share':
				$this->gamestate->nextState('transPlayerShare');
				break;
			case 'repair':
				if ($this->resourceCards->countCardInLocation('hand', $player->getId()) <= 0) {
					throw new BgaUserException(self::_('You have no resource card'));
				}
				$this->gamestate->nextState('transPlayerRepair');
				break;
			case 'divert':
				if ($this->resourceCards->countCardInLocation('hand', $player->getId()) < 3) {
					throw new BgaUserException(self::_('You need 3 resource cards'));
				}
				$this->gamestate->nextState('transPlayerDivert');
				break;
			case 'token':
				$this->actionGetActionToken();
				break;
			case 'room':
				$room = $this->rooms->getRoomByPosition($player->getPosition());
				$roomSlug = $room->getSlug();
				switch ($roomSlug) {
					case 'crew-quarters':
						$this->gamestate->nextState('transPlayerRoomCrewQuarter');
						break;
					case 'cargo-hold':
						$this->resourceCards->pickCardsForLocation(5, 'deck', 'reorder');
						$this->gamestate->nextState('transPlayerRoomCargoHold');
						break;
					case 'mess-hall':
						$this->gamestate->nextState('transPlayerRoomMessHall');
						break;
					case 'engine-room':
						if ($this->resourceCards->countCardInLocation('discard') <= 0) {
							throw new BgaUserException(self::_('No resource card have been discared'));
						}
						if ($this->resourceCards->countCardInLocation('hand', $player->getId()) <= 0) {
							throw new BgaUserException(self::_('You have no resource card'));
						}
						$this->gamestate->nextState('transPlayerRoomEngineRoom');
						break;
					case 'repair-centre':
						if ($this->resourceCards->countCardInLocation('hand', $player->getId()) <= 0) {
							throw new BgaUserException(self::_('You have no resource card'));
						}
						$this->gamestate->nextState('transPlayerRoomRepairCentre');
						break;
					case 'armoury':
						$tokensLeft = 4 - $this->rooms->countTotalProtectionTokens();
						if ($tokensLeft <= 0) {
							throw new BgaUserException(self::_('No protection token available'));
						}
						$this->gamestate->nextState('transPlayerRoomArmoury');
						break;
					case 'bridge':
						$this->damageCards->pickCardsForLocation(3, 'deck', 'reorder');
						$this->gamestate->nextState('transPlayerRoomBridge');
						break;
					case 'energy-core':
						if ($this->rooms->countTotalDiverted() < 8) {
							throw new BgaUserException(self::_('All rooms need to have their power diverted first'));
						}
						$this->triggerEndOfGame('energy-core');
						break;
					default:
						throw new BgaVisibleSystemException("Room $roomSlug not implemented yet"); // NOI18N
				}
				break;
			default:
				throw new BgaVisibleSystemException("Invalid action $actionName"); // NOI18N
				break;
		}
	}

	public function actionCancel() {
		self::checkAction('cancel');
		$this->gamestate->nextState('transActionCancel');
	}

	public function actionMove(int $position): void {
		self::checkAction('move');
		$player = $this->ssPlayers->getActive();
		$currentRoom = $this->rooms->getRoomByPosition($player->getPosition());
		$room = $this->rooms->getRoomByPosition($position);

		// Check position is valid
		if (!in_array($position, $currentRoom->getPossibleDestinations())) {
			throw new BgaUserException(
				sprintf(self::_('You cannot move from %s to %s'), $currentRoom->getName(), $room->getName())
			);
		}

		$player->setPosition($position);
		$player->incrementActions(-1);
		$player->save();
		$this->notifyPlayerData($player, clienttranslate('${player_name} moves to ${roomName}'), [
			'roomName' => $room->getName(),
		]);
		$this->gamestate->nextState('transActionDone');
	}

	public function actionRollDice() {
		self::checkAction('rollDice');

		$player = $this->ssPlayers->getActive();
		$player->incrementActions(-1);
		$player->save();

		$dice = bga_rand(1, 6);

		$this->notifyAllPlayers(
			'playerRollsDice',
			clienttranslate('${player_name} rolls the dice : ${dice_result}'),
			[
				'dice_result' => $dice,
			] + $player->getNotificationArgs()
		);

		$numCardsToPick = 0;
		if ($dice === 6) {
			$numCardsToPick = 2;
		} elseif ($dice > 2) {
			$numCardsToPick = 1;
		}

		// Sadness
		if ($numCardsToPick === 0) {
			$this->notifyAllPlayers(
				'message',
				clienttranslate('${player_name} finds nothing while scavenging'),
				$player->getNotificationArgs()
			);
			$this->gamestate->nextState('transActionScavengePickNothing');
			return;
		}

		// Let user pick card(s)
		$this->notifyAllPlayers(
			'message',
			clienttranslate('${player_name} can pick ${num} resource card(s)'),
			[
				'num' => $numCardsToPick,
			] + $player->getNotificationArgs()
		);
		self::setGameStateValue('scavengeNumberOfCards', $numCardsToPick);
		$this->gamestate->nextState('transActionScavengePickCards');
	}

	public function actionPickResource($cardId) {
		self::checkAction('pickResource');
		$player = $this->ssPlayers->getActive();

		$possibleFrom = $this->whereDoesPlayerCanPickResourceFrom();
		if (empty($possibleFrom)) {
			throw new BgaVisibleSystemException('Nowhere to pick from'); // NOI18N
		}

		$fromDeck = false;
		if ($cardId === 9999) {
			$fromDeck = true;
			// Pick from deck
			$card = $this->resourceCards->pickCardForLocation('deck', 'hand', $player->getId());
			if ($this->resourceCards->countCardInLocation('deck') == 0) {
				$this->triggerEndOfGame('resources');
				return;
			}
		} else {
			// Pick from table
			if (!in_array('table', $possibleFrom)) {
				throw new BgaUserException(self::_('You must pick the second card from the deck'));
			}
			$card = $this->resourceCards->getCard($cardId);
			// Check resource is on table
			if ($card['location'] !== 'table') {
				throw new BgaVisibleSystemException('Card not on table !'); // NOI18N
			}
		}

		$this->resourceCards->moveCard($card['id'], 'hand', $player->getId());

		$resourceName = $this->resourceTypes[$card['type']]['name'];
		if ($fromDeck) {
			$message = clienttranslate('${player_name} takes a resource from the deck : ${resourceName}');
		} else {
			$message = clienttranslate('${player_name} takes a resource : ${resourceName}');
		}

		$resourceCardsNbr = $this->resourceCards->countCardInLocation('deck');
		$this->notifyAllPlayers(
			'playerPickResource',
			$message,
			[
				'card' => $card,
				'resourceName' => $resourceName,
				'resourceCardsNbr' => $resourceCardsNbr,
			] + $player->getNotificationArgs()
		);

		$this->assertResourceCardsOnTable(true);

		$stateName = $this->gamestate->state()['name'];
		if ($stateName === 'pickResources') {
			$previouslyPickedFromDeck = (bool) self::getGameStateValue('resourcePickedFromDeck');
			if (!$fromDeck || $previouslyPickedFromDeck) {
				// Picked from table, or second pick from deck: end state now
				self::setGameStateValue('resourcePickedFromDeck', 0);
				$this->gamestate->nextState('transPlayerEndTurn');
				return;
			}
			self::setGameStateValue('resourcePickedFromDeck', 1);
		}

		if ($stateName === 'playerScavengePickCards') {
			// Action "scavenge", check number of picks
			$num = (int) self::getGameStateValue('scavengeNumberOfCards');
			$num--;
			if ($num <= 0) {
				// End
				$this->gamestate->nextState('transActionScavengeEnd');
				return;
			}
			self::setGameStateValue('scavengeNumberOfCards', $num);
			$this->gamestate->nextState('transActionScavengePickCards');
			return;
		}

		$this->gamestate->nextState('transPlayerPickResourcesCards');
	}

	public function actionDiscardResource($cardId) {
		self::checkAction('discardResource');
		$card = $this->resourceCards->getCard($cardId);
		$player = $this->ssPlayers->getActive();
		if ($card['location'] !== 'hand' || $card['location_arg'] != $player->getId()) {
			throw new BgaVisibleSystemException('Card not in your hand'); // NOI18N
		}
		$this->resourceCards->moveCard($card['id'], 'discard');

		$resourceName = $this->resourceTypes[$card['type']]['name'];
		$this->notifyAllPlayers(
			'playerDiscardResource',
			clienttranslate('${player_name} discards a resource : ${resourceName}'),
			[
				'card' => $card,
				'resourceName' => $resourceName,
			] + $player->getNotificationArgs()
		);
		$this->gamestate->nextState('transPlayerEndTurn');
	}

	public function actionGiveResourceToAnotherPlayer(int $cardId, int $playerId) {
		self::checkAction('giveResourceToAnotherPlayer');
		$player = $this->ssPlayers->getActive();
		$card = $this->resourceCards->getCard($cardId);
		$toPlayer = $this->ssPlayers->getPlayer($playerId);
		if ($card['location'] !== 'hand' || $card['location_arg'] != $player->getId()) {
			throw new BgaVisibleSystemException('Card not in player hand'); // NOI18N
		}
		$stateName = $this->gamestate->state()['name'];
		if ($stateName !== 'playerRoomMessHall') {
			if ($toPlayer->getPosition() !== $player->getPosition()) {
				throw new BgaUserException(self::_('This player is not is the same room'));
			}
		}
		$this->resourceCards->moveCard($card['id'], 'hand', $toPlayer->getId());
		$player->incrementActions(-1);
		$player->save();
		$resourceName = $this->resourceTypes[$card['type']]['name'];
		$this->notifyAllPlayers(
			'playerShareResource',
			clienttranslate('${player_name} gives a resource : ${resourceName} to ${to_player_name}'),
			[
				'card' => $card,
				'resourceName' => $resourceName,
				'shareAction' => 'give',
				'to_player_name' => $toPlayer->getName(),
				'to_player_id' => $toPlayer->getId(),
			] + $player->getNotificationArgs()
		);
		$this->gamestate->nextState('transActionDone');
	}

	public function actionPickResourceFromAnotherPlayer(int $cardId) {
		self::checkAction('pickResourceFromAnotherPlayer');
		$player = $this->ssPlayers->getActive();
		$card = $this->resourceCards->getCard($cardId);
		if ($card['location'] !== 'hand') {
			throw new BgaVisibleSystemException('Card not in a player hand'); // NOI18N
		}
		$fromPlayer = $this->ssPlayers->getPlayer((int) $card['location_arg']);
		if ($fromPlayer->getId() === $player->getId()) {
			throw new BgaVisibleSystemException('Card cannot be in player hand'); // NOI18N
		}
		$stateName = $this->gamestate->state()['name'];
		if ($stateName !== 'playerRoomMessHall') {
			if ($fromPlayer->getPosition() !== $player->getPosition()) {
				throw new BgaUserException(self::_('This player is not is the same room'));
			}
		}
		$this->resourceCards->moveCard($card['id'], 'hand', $player->getId());
		$player->incrementActions(-1);
		$player->save();
		$resourceName = $this->resourceTypes[$card['type']]['name'];
		$this->notifyAllPlayers(
			'playerShareResource',
			clienttranslate('${player_name} takes a resource : ${resourceName} from ${from_player_name}'),
			[
				'card' => $card,
				'resourceName' => $resourceName,
				'shareAction' => 'take',
				'from_player_name' => $fromPlayer->getName(),
				'from_player_id' => $fromPlayer->getId(),
			] + $player->getNotificationArgs()
		);
		$this->gamestate->nextState('transActionDone');
	}

	public function actionSwapResourceWithAnotherPlayer(int $cardId, int $card2Id) {
		self::checkAction('swapResourceWithAnotherPlayer');
		$player = $this->ssPlayers->getActive();
		$card = $this->resourceCards->getCard($cardId);
		$card2 = $this->resourceCards->getCard($card2Id);
		if ($card['location'] !== 'hand' || $card2['location'] !== 'hand') {
			throw new BgaVisibleSystemException('Card not in a player hand'); // NOI18N
		}
		$withPlayer = $this->ssPlayers->getPlayer((int) $card2['location_arg']);
		if ($withPlayer->getId() === $player->getId()) {
			throw new BgaVisibleSystemException('Card self swap'); // NOI18N
		}
		$this->resourceCards->moveCard($card['id'], 'hand', $withPlayer->getId());
		$this->resourceCards->moveCard($card2['id'], 'hand', $player->getId());
		$player->incrementActions(-1);
		$player->save();
		$resourceName = $this->resourceTypes[$card['type']]['name'];
		$this->notifyAllPlayers(
			'playerShareResource',
			clienttranslate('${player_name} gives a resource : ${resourceName} to ${to_player_name}'),
			[
				'card' => $card,
				'resourceName' => $resourceName,
				'shareAction' => 'give',
				'to_player_name' => $withPlayer->getName(),
				'to_player_id' => $withPlayer->getId(),
			] + $player->getNotificationArgs()
		);
		$resourceName = $this->resourceTypes[$card2['type']]['name'];
		$this->notifyAllPlayers(
			'playerShareResource',
			clienttranslate('${player_name} takes a resource : ${resourceName} from ${from_player_name}'),
			[
				'card' => $card2,
				'resourceName' => $resourceName,
				'shareAction' => 'take',
				'from_player_name' => $withPlayer->getName(),
				'from_player_id' => $withPlayer->getId(),
			] + $player->getNotificationArgs()
		);
		$this->gamestate->nextState('transActionDone');
	}

	public function actionSwapResourceFromDiscard(int $cardId, int $card2Id) {
		self::checkAction('swapResourceFromDiscard');
		$player = $this->ssPlayers->getActive();
		$card = $this->resourceCards->getCard($cardId);
		$card2 = $this->resourceCards->getCard($card2Id);
		if ($card['location'] !== 'discard') {
			throw new BgaVisibleSystemException('Card not in a discard'); // NOI18N
		}
		if ($card2['location'] !== 'hand' && $card2['location_arg'] != $player->getId()) {
			throw new BgaVisibleSystemException('Card not in your hand'); // NOI18N
		}
		$this->resourceCards->moveCard($card['id'], 'hand', $player->getId());
		$this->resourceCards->moveCard($card2['id'], 'discard');
		$player->incrementActions(-1);
		$player->save();
		$resourceName = $this->resourceTypes[$card['type']]['name'];
		$resourceName2 = $this->resourceTypes[$card2['type']]['name'];
		$this->notifyAllPlayers(
			'playerPickResource',
			clienttranslate(
				'${player_name} swap a resource : ${resourceName2} from their hand with a ${resourceName} from the discard pile'
			),
			[
				'card' => $card,
				'resourceName' => $resourceName,
				'resourceName2' => $resourceName2,
			] + $player->getNotificationArgs()
		);
		$this->notifyAllPlayers(
			'playerDiscardResource',
			'',
			[
				'card' => $card2,
				'resourceName' => $resourceName,
			] + $player->getNotificationArgs()
		);
		$this->gamestate->nextState('transActionDone');
	}

	public function actionSelectResourceForRepair(int $cardId, ?string $typeId = null, ?int $position = null) {
		self::checkAction('selectResourceForRepair');
		$card = $this->resourceCards->getCard($cardId);
		$player = $this->ssPlayers->getActive();
		if ($card['location'] !== 'hand' || $card['location_arg'] != $player->getId()) {
			throw new BgaVisibleSystemException('Card not in your hand'); // NOI18N
		}

		$stateName = $this->gamestate->state()['name'];
		if ($stateName === 'playerRoomRepairCentre' && $position !== null) {
			$room = $this->rooms->getRoomByPosition($position);
		} else {
			$room = $this->rooms->getRoomByPosition($player->getPosition());
		}

		$cardType = $card['type'];
		if ($cardType === 'universal') {
			$cardType = $typeId;
		}
		$this->repairRoomWithResource($room, $cardType);
		$room->save();

		$this->resourceCards->moveCard($card['id'], 'discard');

		$resourceName = $this->resourceTypes[$card['type']]['name'];
		$this->notifyAllPlayers(
			'playerDiscardResource',
			clienttranslate('${player_name} repairs ${roomName} with resource : ${resourceName}'),
			[
				'card' => $card,
				'resourceName' => $resourceName,
				'roomName' => $room->getName(),
			] + $player->getNotificationArgs()
		);

		$this->notifyAllPlayers('updateRooms', '', [
			'rooms' => [$room->toArray()],
		]);
		$player->incrementActions(-1);
		$player->save();
		$this->gamestate->nextState('transActionDone');
	}

	public function actionSelectResourcesForDivert(array $cardIds): void {
		self::checkAction('selectResourcesForDivert');
		$player = $this->ssPlayers->getActive();

		$cards = [];
		foreach ($cardIds as $cardId) {
			$card = $this->resourceCards->getCard($cardId);
			if ($card['location'] !== 'hand' || $card['location_arg'] != $player->getId()) {
				throw new BgaVisibleSystemException('Card not in your hand'); // NOI18N
			}
			$cards[] = $card;
		}

		$room = $this->rooms->getRoomByPosition($player->getPosition());
		$this->divertRoomWithResources($room, $cards);
		$room->save();

		$resourceNames = [];
		foreach ($cards as $card) {
			$this->resourceCards->moveCard($card['id'], 'discard');
			$resourceNames[] = $this->resourceTypes[$card['type']]['name'];
		}

		$this->notifyAllPlayers(
			'playerDiscardResources',
			clienttranslate('${player_name} diverts power in ${roomName} with resources : ${resourceNames}'),
			[
				'cards' => $cards,
				'resourceNames' => $resourceNames,
				'roomName' => $room->getName(),
			] + $player->getNotificationArgs()
		);

		$this->notifyAllPlayers('updateRooms', '', [
			'rooms' => [$room->toArray()],
		]);
		$player->incrementActions(-1);
		$player->save();
		$this->gamestate->nextState('transActionDone');
	}

	public function actionMoveMeepleToRoom(int $playerId, int $position): void {
		self::checkAction('moveMeepleToRoom');
		$player = $this->ssPlayers->getActive();
		$playerToMove = $this->ssPlayers->getPlayer($playerId);
		$room = $this->rooms->getRoomByPosition($position);

		if ($playerToMove->getPosition() === $position) {
			throw new BgaUserException(self::_('Player is already in this room'));
		}

		// Check position is valid (destination room already has a meeple)
		if (empty($this->ssPlayers->getPlayersAtPosition($position))) {
			throw new BgaUserException(self::_('Cannot move to an empty room'));
		}

		$playerToMove->setPosition($position);
		$playerToMove->save();
		$player->incrementActions(-1);
		$player->save();
		$this->notifyPlayerData(
			$playerToMove,
			clienttranslate('${player_name} is moved to ${roomName} by ${player_action_name} (Crew Quarters action)'),
			[
				'roomName' => $room->getName(),
				'player_action_name' => $player->getName(),
				'player_action_id' => $player->getId(),
			]
		);
		$this->gamestate->nextState('transActionDone');
	}

	public function actionPutBackResourceCardsInDeck(array $cardIds) {
		self::checkAction('putBackResourceCardsInDeck');
		$player = $this->ssPlayers->getActive();
		$cardIds = array_reverse($cardIds);
		foreach ($cardIds as $cardId) {
			$card = $this->resourceCards->getCard($cardId);
			if ($card['location'] !== 'reorder') {
				throw new BgaVisibleSystemException('Card not in reorder deck'); // NOI18N
			}
			$this->resourceCards->insertCardOnExtremePosition($card['id'], 'deck', true);
		}
		if ($this->resourceCards->countCardInLocation('reorder') != 0) {
			throw new BgaVisibleSystemException('Reorder deck not empty'); // NOI18N
		}
		$this->notifyAllPlayers(
			'message',
			clienttranslate('${player_name} has reordered ${num_resources} resources on the top of the deck'),
			[
				'num_resources' => count($cardIds),
			] + $player->getNotificationArgs()
		);
		$this->gamestate->nextState('transActionDone');
	}

	public function actionPutBackDamageCardsInDeck(array $cardIds) {
		self::checkAction('putBackDamageCardsInDeck');
		$player = $this->ssPlayers->getActive();
		$cardIds = array_reverse($cardIds);
		foreach ($cardIds as $cardId) {
			$card = $this->damageCards->getCard($cardId);
			if ($card['location'] !== 'reorder') {
				throw new BgaVisibleSystemException('Card not in reorder deck'); // NOI18N
			}
			$this->damageCards->insertCardOnExtremePosition($card['id'], 'deck', true);
		}
		if ($this->damageCards->countCardInLocation('reorder') != 0) {
			throw new BgaVisibleSystemException('Reorder deck not empty'); // NOI18N
		}
		$this->notifyAllPlayers(
			'message',
			clienttranslate('${player_name} has reordered ${num_damages} damages cards on the top of the deck'),
			[
				'num_damages' => count($cardIds),
			] + $player->getNotificationArgs()
		);
		$this->gamestate->nextState('transActionDone');
	}

	public function actionGetActionToken() {
		$player = $this->ssPlayers->getActive();
		$tokensLeft = 8 - $this->ssPlayers->countTotalActionTokens();
		if ($tokensLeft <= 0) {
			throw new BgaUserException(self::_('No action token left'));
		}
		$tokens = $player->getActionsTokens();
		$player->setActionsTokens($tokens + 1);
		$player->incrementActions(-1);
		$player->save();
		$this->notifyPlayerData($player, clienttranslate('${player_name} takes an action token'), []);
		$this->gamestate->nextState('transActionDone');
	}

	public function actionUseToken() {
		$player = $this->ssPlayers->getActive();
		$tokens = $player->getActionsTokens();
		if ($tokens <= 0) {
			throw new BgaVisibleSystemException('No action token available'); // NOI18N
		}
		$player->setActionsTokens($tokens - 1);
		$player->incrementActions(1);
		$player->save();
		$this->notifyPlayerData($player, clienttranslate('${player_name} uses an action token'), []);
		$this->gamestate->nextState('transActionDone');
	}

	public function actionPutProtectionTokens(array $positions) {
		$tokensLeft = 4 - $this->rooms->countTotalProtectionTokens();
		if (count($positions) > 2 || count($positions) > $tokensLeft) {
			throw new \Exception('Invalid number of tokens');
		}
		$player = $this->ssPlayers->getActive();
		$updatedRooms = [];
		foreach ($positions as $position) {
			$room = $this->rooms->getRoomByPosition($position);
			$room->addProtection($player);
			$room->save();
			$updatedRooms[$room->getPosition()] = $room->toArray();
		}

		$updatedRooms = array_values($updatedRooms);

		$roomNames = join(
			', ',
			array_map(function ($r) {
				return $r['name'];
			}, $updatedRooms)
		);
		$this->notifyAllPlayers(
			'updateRooms',
			clienttranslate('${player_name} puts a protection token in ${roomNames}'),
			[
				'rooms' => $updatedRooms,
				'roomNames' => $roomNames,
			] + $player->getNotificationArgs()
		);

		$player->incrementActions(-1);
		$player->save();
		$this->gamestate->nextState('transActionDone');
	}

	//////////////////////////////////////////////////////////////////////////////
	//////////// Game state arguments
	////////////

	public function argPlayerTurn(): array {
		$player = $this->ssPlayers->getActive();
		return [
			'actions' => $player->getActions(),
		];
	}

	public function argPlayerMove(): array {
		$player = $this->ssPlayers->getActive();
		$room = $this->rooms->getRoomByPosition($player->getPosition());
		$possibleDestinations = $room->getPossibleDestinations();
		return [
			'possibleDestinations' => $possibleDestinations,
		];
	}

	public function argPlayerScavengePickCards(): array {
		return [
			'possibleSources' => $this->whereDoesPlayerCanPickResourceFrom(),
		];
	}

	public function argPlayerPickResourcesCards(): array {
		return [
			'possibleSources' => $this->whereDoesPlayerCanPickResourceFrom(),
		];
	}

	public function argPlayerRoomCargoHold(): array {
		$nextCards = $this->resourceCards->getCardsInLocation('reorder');
		return [
			'_private' => [
				'active' => [
					'resourceCards' => $nextCards,
				],
			],
		];
	}

	public function argPlayerRoomArmoury(): array {
		$tokensLeft = 4 - $this->rooms->countTotalProtectionTokens();
		return [
			'tokensLeft' => $tokensLeft,
		];
	}

	public function argPlayerRoomBridge(): array {
		$nextCards = $this->damageCards->getCardsInLocation('reorder');
		return [
			'_private' => [
				'active' => [
					'damageCards' => $nextCards,
				],
			],
		];
	}

	public function argPlayerRoomEngineRoom(): array {
		$discardedCards = $this->resourceCards->getCardsInLocation('discard');
		return [
			'resourceCards' => $discardedCards,
		];
	}

	//////////////////////////////////////////////////////////////////////////////
	//////////// Game state actions
	////////////

	public function stStartOfTurn() {
		$player = $this->ssPlayers->getActive();
		$room = $this->rooms->getRoomByPosition($player->getPosition());

		if ($room->getSlug() === 'medical-bay') {
			$tokensLeft = 8 - $this->ssPlayers->countTotalActionTokens();
			$tokensMax = min(2, $tokensLeft);
			$tokens = $player->getActionsTokens();
			$player->setActionsTokens($tokens + $tokensMax);
			$player->save();
			$this->notifyPlayerData($player, clienttranslate('Medical Bay: ${player_name} takes ${num} action token(s)'), [
				'num' => $tokensMax,
			]);
		}

		// Remove protection tokens from this player
		$updatedRooms = [];
		foreach ($this->rooms->getRooms() as $room) {
			$protection = $room->getProtection();
			if (in_array($player->getId(), $protection)) {
				$room->setProtection(
					array_values(
						array_filter($protection, function ($p) use ($player) {
							return $p !== $player->getId();
						})
					)
				);
				$room->save();
				$updatedRooms[] = $room->toArray();
			}
		}
		if (!empty($updatedRooms)) {
			$this->notifyAllPlayers(
				'updateRooms',
				'Protection tokens from ${player_name} are removed.',
				[
					'rooms' => $updatedRooms,
				] + $player->getNotificationArgs()
			);
		}

		$this->gamestate->nextState('transPlayerTurn');
	}

	public function stActionShare() {
		$ids = $this->getPlayersIdsInTheSameRoom(true);
		if (empty($ids)) {
			throw new BgaUserException(self::_('You are alone in the room'));
		}
	}

	public function stActionDone() {
		$player = $this->ssPlayers->getActive();
		$actions = $player->getActions();
		if ($actions === 0) {
			$this->gamestate->nextState('transPlayerPickResourcesCards');
		} else {
			$this->gamestate->nextState('transPlayerTurn');
		}
	}

	public function stActionCancel() {
		$this->gamestate->nextState('transPlayerTurn');
	}

	public function stEndTurn() {
		$player = $this->ssPlayers->getActive();
		$player->setActions(3);
		$player->save();

		// Check if player has too many cards in hand
		$n = $this->resourceCards->countCardInLocation('hand', $player->getId());
		if ($n > 6) {
			$this->gamestate->nextState('transPlayerDiscardResources');
			return;
		}

		// Draw damage card
		$this->drawDamageCard('top', true);

		$playerId = self::activeNextPlayer();
		self::giveExtraTime($playerId);
		$this->gamestate->nextState('transPlayerStartOfTurn');
	}

	public function stPlayerRoomCargoHold() {
		$player = $this->ssPlayers->getActive();
		$player->incrementActions(-1);
		$player->save();
	}

	public function stPlayerRoomBridge() {
		$player = $this->ssPlayers->getActive();
		$player->incrementActions(-1);
		$player->save();
	}

	//////////////////////////////////////////////////////////////////////////////
	//////////// DEBUG
	//////////// Methods to be called from the chat window in dev studio

	/**
	 * Leave one card in the resource decks, so any deck pick will end the game
	 */
	public function debugEmptyResourceDeck() {
		$cnt = $this->resourceCards->countCardInLocation('deck');
		$this->resourceCards->pickCardsForLocation($cnt - 1, 'deck', 'discard');
	}

	/**
	 * Divert all rooms so we can win :)
	 */
	public function debugDivertAllRooms() {
		$updatedRooms = [];
		foreach ($this->rooms->getRooms() as $room) {
			if ($room->getSlug() === 'energy-core') {
				continue;
			}
			$room->setDiverted(true);
			$room->save();
			$updatedRooms[] = $room->toArray();
		}
		$this->notifyAllPlayers('updateRooms', 'cheater !', [
			'rooms' => $updatedRooms,
		]);
	}

	/**
	 * Give lotta actions
	 */
	public function debugNitro($num = 10) {
		$player = $this->ssPlayers->getActive();
		$player->incrementActions((int) $num);
		$player->save();
	}

	/**
	 * Protect current room
	 */
	public function debugProtect() {
		$player = $this->ssPlayers->getActive();
		$room = $this->rooms->getRoomByPosition($player->getPosition());
		$room->addProtection($player);
		$room->save();
		$this->notifyAllPlayers(
			'updateRooms',
			'protectaaate',
			[
				'rooms' => [$room->toArray()],
				'roomName' => $room->getName(),
			] + $player->getNotificationArgs()
		);
	}

	/**
	 * Unprotectall
	 */
	public function debugUnprotect() {
		$updatedRooms = [];
		foreach ($this->rooms->getRooms() as $room) {
			if (!$room->isProtected()) {
				continue;
			}
			$room->setProtection([]);
			$room->save();
			$updatedRooms[] = $room->toArray();
		}
		$this->notifyAllPlayers('updateRooms', 'Deprotect All', [
			'rooms' => $updatedRooms,
		]);
	}

	/**
	 * Unprotectall
	 */
	public function debugDrawDamage() {
		$this->drawDamageCard('top', true);
	}

	//////////////////////////////////////////////////////////////////////////////
	//////////// Zombie
	////////////

	/*
        zombieTurn:
        
        This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
        You can do whatever you want in order to make sure the turn of this player ends appropriately
        (ex: pass).
        
        Important: your zombie code will be called when the player leaves the game. This action is triggered
        from the main site and propagated to the gameserver from a server, not from a browser.
        As a consequence, there is no current player associated to this action. In your zombieTurn function,
        you must _never_ use getCurrentPlayerId() or getCurrentPlayerName(), otherwise it will fail with a "Not logged" error message. 
    */

	function zombieTurn($state, $active_player) {
		$statename = $state['name'];

		if ($state['type'] === 'activeplayer') {
			switch ($statename) {
				default:
					$this->gamestate->nextState('zombiePass');
					break;
			}

			return;
		}

		if ($state['type'] === 'multipleactiveplayer') {
			// Make sure player is in a non blocking status for role turn
			$this->gamestate->setPlayerNonMultiactive($active_player, '');

			return;
		}

		throw new feException('Zombie mode not supported at this game state: ' . $statename);
	}

	///////////////////////////////////////////////////////////////////////////////////:
	////////// DB upgrade
	//////////

	/*
        upgradeTableDb:
        
        You don't have to care about this until your game has been published on BGA.
        Once your game is on BGA, this method is called everytime the system detects a game running with your old
        Database scheme.
        In this case, if you change your Database scheme, you just have to apply the needed changes in order to
        update the game database and allow the game to continue to run with your new version.
    
    */

	function upgradeTableDb($from_version) {
		// $from_version is the current version of this game database, in numerical form.
		// For example, if the game was running with a release of your game named "140430-1345",
		// $from_version is equal to 1404301345
		// Example:
		//        if( $from_version <= 1404301345 )
		//        {
		//            // ! important ! Use DBPREFIX_<table_name> for all tables
		//
		//            $sql = "ALTER TABLE DBPREFIX_xxxxxxx ....";
		//            self::applyDbUpgradeToAllDB( $sql );
		//        }
		//        if( $from_version <= 1405061421 )
		//        {
		//            // ! important ! Use DBPREFIX_<table_name> for all tables
		//
		//            $sql = "CREATE TABLE DBPREFIX_xxxxxxx ....";
		//            self::applyDbUpgradeToAllDB( $sql );
		//        }
		//        // Please add your future database scheme changes here
		//
		//
	}
}
