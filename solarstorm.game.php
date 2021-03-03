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
			// Number of player turns
			'playerTurnsCount' => 11,
			// If the player has picked a resourceCard from the deck (0/1)
			'resourcePickedFromDeck' => 12,
			// When scavenging, number of cards left to pick
			'scavengeNumberOfCards' => 13,
			// Player doesn't want to use his action tokens (end of turn)
			'dontWannaUseActionsTokens' => 14,
			// Initial resource deck size
			'initialResourceDeckSize' => 15,
			// Can Restart Turn
			'canRestartTurn' => 17,
			// Player has picked action token this turn
			'hasPickedActionToken' => 18,

			// Options
			// Game difficulty (number of universal cards)
			'gameDifficulty' => 100,
			// Realistic mode (hide number of resource cards left) (0 false, 1 true)
			'hideResourcesLeft' => 101,
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

		self::setGameStateInitialValue('playerTurnsCount', 0);
		self::setGameStateInitialValue('resourcePickedFromDeck', 0);
		self::setGameStateInitialValue('scavengeNumberOfCards', 0);
		self::setGameStateInitialValue('dontWannaUseActionsTokens', 0);
		self::setGameStateInitialValue('initialResourceDeckSize', 0);
		self::setGameStateInitialValue('canRestartTurn', 0);
		self::setGameStateInitialValue('hasPickedActionToken', 0);

		// Stats
		self::initStat('table', 'turns_number', 0);
		self::initStat('table', 'repair_done', 0);
		self::initStat('table', 'power_diverted', 0);
		self::initStat('table', 'resources_picked', 0);
		self::initStat('table', 'room_protected', 0);
		self::initStat('table', 'room_damaged', 0);

		self::initStat('player', 'turns_number', 0);
		self::initStat('player', 'repair_done', 0);
		self::initStat('player', 'power_diverted', 0);
		self::initStat('player', 'resources_picked', 0);
		self::initStat('player', 'action_move', 0);
		self::initStat('player', 'action_room_bridge', 0);
		self::initStat('player', 'action_room_armoury', 0);
		self::initStat('player', 'action_room_cargo_hold', 0);
		self::initStat('player', 'action_room_crew_quarters', 0);
		self::initStat('player', 'action_room_engine_room', 0);
		self::initStat('player', 'action_room_medical_bay', 0);
		self::initStat('player', 'action_room_repair_centre', 0);
		self::initStat('player', 'action_room_mess_hall', 0);

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

		// Hull card
		array_unshift($cards, [
			'type' => 'hull',
			'type_arg' => null,
			'nbr' => 1,
		]);

		$this->damageCards->createCards($cards, 'deck');
	}

	private function drawDamageCard(string $from): void {
		if (!in_array($from, ['top', 'bottom'])) {
			throw new \Exception('Invalid position to draw damage card from'); // NOI18N
		}

		if ($this->damageCards->countCardInLocation('deck') == 0) {
			return;
		}

		$cards = $this->damageCards->getCardsInLocation('deck', null, 'location_arg');
		if ($from === 'bottom') {
			$card = $cards[1]; // Don't pick hull card
			$message = clienttranslate('Start of game: damage card drawn');
		} else {
			$card = $cards[count($cards) - 1];
			$message = clienttranslate('End of turn: ${player_name} draws the next damage card');
		}

		if ($card['type'] === 'hull') {
			$message = clienttranslate(
				'End of turn: ${player_name} draws the last damage card, the hull is now receiving damage !'
			);
		}

		$this->damageCards->insertCardOnExtremePosition($card['id'], 'discard', true);
		$player = $this->ssPlayers->getActive();
		$this->notifyAllPlayers(
			'updateDamageDiscard',
			$message,
			[
				'cards' => [$card],
			] + $player->getNotificationArgs()
		);

		if ($this->damageCards->countCardInLocation('deck') == 0) {
			return;
		}

		$roomsSlugs = $this->damageCardsInfos[$card['type']];
		$updatedRooms = [];
		$protectedRooms = [];
		foreach ($roomsSlugs as $roomsSlug) {
			$room = $this->rooms->getRoomBySlug($roomsSlug);
			if ($room->isProtected()) {
				$this->incStat(1, 'room_protected');
				$room->removeOldestProtectionToken();
				$protectedRooms[] = $room->toArray();
			} else {
				$this->incStat(1, 'room_damaged');
				if ($room->getDamageCount() === 3) {
					$room->setDestroyed(true);
					$room->save();
					$this->notifyAllPlayers('updateRooms', clienttranslate('${roomName} receive fatal damage !'), [
						'rooms' => [$room->toArray() + ['shake' => true]],
						'roomName' => $room->getSlug(),
					]);
					$this->triggerEndOfGame('damage');
					return;
				}
				$room->doDamage();
			}
			$room->save();
			$updatedRooms[] = $room->toArray() + ['shake' => true];
		}

		if (!empty($updatedRooms)) {
			$this->notifyAllPlayers('updateRooms', clienttranslate('${roomNames} receive damage'), [
				'rooms' => $updatedRooms,
				'roomNames' => array_column($updatedRooms, 'slug'),
			]);
		}
		if (!empty($protectedRooms)) {
			$this->notifyAllPlayers('message', clienttranslate('${roomNames} were protected !'), [
				'roomNames' => array_column($protectedRooms, 'slug'),
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

		self::setGameStateValue('canRestartTurn', 0);

		$cards = $this->resourceCards->pickCardsForLocation($needToDrawCnt, 'deck', 'table');
		if ($this->resourceCards->countCardInLocation('deck') == 0) {
			$this->triggerEndOfGame('resources');
			return;
		}

		if ($notify) {
			$resourceCardsNbr = $this->getNbrResourceCardsInDeck();
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
		$result['resourceCardsNbr'] = $this->getNbrResourceCardsInDeck();
		$result['resourceTypes'] = array_values($this->resourceTypes);

		$result['damageCardsInfos'] = array_values($this->damageCardsInfos);
		$result['damageCardsNbr'] = $this->damageCards->countCardInLocation('deck');
		$result['damageCardsDiscarded'] = $this->damageCards->getCardsInLocation('discard', null, 'location_arg');
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

	function getGameProgression() {
		$total = (int) self::getGameStateValue('initialResourceDeckSize') - 10;
		$left = (int) $this->resourceCards->countCardInLocation('deck');
		$progress = (int) ((($total - $left) / $total) * 100);
		if (self::getGameStateValue('hideResourcesLeft')) {
			// Ceil value to 25 (https://forum.boardgamearena.com/viewtopic.php?f=12&t=16978&p=64909#p64909)
			$progress -= $progress % 25;
		}
		return $progress;
	}

	//////////////////////////////////////////////////////////////////////////////
	//////////// Utility functions
	////////////

	private function notifyPlayerData(
		SolarStormPlayer $player,
		string $message = '',
		array $args = [],
		bool $all = false
	): void {
		$args += [
			'position' => $player->getPosition(),
			'actionsTokens' => $player->getActionsTokens(),
		];

		if ($all) {
			$args['resourceCards'] = array_values($this->resourceCards->getCardsInLocation('hand', $player->getId()));
		}

		$args += $player->getNotificationArgs();

		$this->notifyAllPlayers('updatePlayerData', $message, $args);
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
		$this->incStat(1, 'power_diverted');
		$this->incStat(1, 'power_diverted', self::getActivePlayerId());
	}

	public function checkHullBreach(): void {
		if ($this->damageCards->countCardInLocation('deck') > 0) {
			return;
		}

		$dice = bga_rand(1, 6);
		$numCardsToDiscard = 1;
		if ($dice >= 5) {
			$numCardsToDiscard = 3;
		} elseif ($dice >= 3) {
			$numCardsToDiscard = 2;
		}

		$this->resourceCards->pickCardsForLocation($numCardsToDiscard, 'deck', 'discard');

		$resourceCardsNbr = $this->getNbrResourceCardsInDeck();
		$this->notifyAllPlayers(
			'hullBreachDiscard',
			clienttranslate('Hull Breach ! Die result : ${die_result}. ${num} resource card(s) discarded.'),
			[
				'die_result' => $dice,
				'dieResult' => $dice,
				'num' => $numCardsToDiscard,
				'resourceCardsNbr' => $resourceCardsNbr,
			]
		);

		if ($this->resourceCards->countCardInLocation('deck') == 0) {
			$this->triggerEndOfGame('resources');
			return;
		}
	}

	/**
	 * Returns the number of resource cards in "deck", or null, depending on game setting
	 */
	private function getNbrResourceCardsInDeck(): ?int {
		if (self::getGameStateValue('hideResourcesLeft')) {
			return null;
		}
		return (int) $this->resourceCards->countCardInLocation('deck');
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
				$room = $this->rooms->getRoomByPosition($player->getPosition());
				if ($room->getDamageCount() > 0) {
					throw new BgaUserException(self::_('This room is damaged and can\'t have its power diverted'));
				}
				$this->gamestate->nextState('transPlayerDivert');
				break;
			case 'token':
				$this->actionGetActionToken();
				break;
			case 'room':
				$room = $this->rooms->getRoomByPosition($player->getPosition());
				if ($room->getDamageCount() > 0) {
					throw new BgaUserException(self::_('Cannot activate a damaged room, repair it first !'));
				}
				$roomSlug = $room->getSlug();
				switch ($roomSlug) {
					case 'crew-quarters':
						$this->gamestate->nextState('transPlayerRoomCrewQuarter');
						break;
					case 'cargo-hold':
						self::setGameStateValue('canRestartTurn', 0);
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
						self::setGameStateValue('canRestartTurn', 0);
						$this->damageCards->pickCardsForLocation(3, 'deck', 'reorder');
						$this->gamestate->nextState('transPlayerRoomBridge');
						break;
					case 'energy-core':
						if ($this->rooms->countTotalDiverted() < 8) {
							throw new BgaUserException(self::_('All rooms need to have their power diverted first'));
						}
						$this->notifyAllPlayers(
							'message',
							'🎉 ' . clienttranslate('${player_name} activates the Energy Core !'),
							$player->getNotificationArgs()
						);
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

	public function actionCancel(): void {
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
		$this->incStat(1, 'action_move', self::getActivePlayerId());
		$this->notifyPlayerData($player, clienttranslate('${player_name} moves to ${roomName}'), [
			'roomName' => $room->getSlug(),
		]);
		$this->gamestate->nextState('transActionDone');
	}

	public function actionRollDice(): void {
		self::checkAction('rollDice');

		$player = $this->ssPlayers->getActive();
		$player->incrementActions(-1);
		$player->save();

		$dice = bga_rand(1, 6);
		self::setGameStateValue('canRestartTurn', 0);

		$numCardsToPick = 0;
		if ($dice === 6) {
			$numCardsToPick = 2;
		} elseif ($dice > 2) {
			$numCardsToPick = 1;
		}

		$message = clienttranslate('${player_name} scavenges, rolls the die (${die_result})') . ', ';
		if ($numCardsToPick === 0) {
			$message .= clienttranslate('and finds nothing !') . ' 😒';
		} else {
			$message .= clienttranslate('and finds ${num} resource card(s) !');
		}

		$this->notifyAllPlayers(
			'playerRollsDice',
			$message,
			[
				'die_result' => $dice,
				'dieResult' => $dice,
				'num' => $numCardsToPick,
			] + $player->getNotificationArgs()
		);

		// Sadness
		if ($numCardsToPick === 0) {
			$this->gamestate->nextState('transActionScavengePickNothing');
			return;
		}

		// Let user pick card(s)
		self::setGameStateValue('scavengeNumberOfCards', $numCardsToPick);
		$this->gamestate->nextState('transActionScavengePickCards');
	}

	public function actionPickResource($cardId): void {
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
			self::setGameStateValue('canRestartTurn', 0);
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

		if ($fromDeck) {
			$message = clienttranslate('${player_name} takes a resource from the deck : ${resourceType}');
		} else {
			$message = clienttranslate('${player_name} takes a resource : ${resourceType}');
		}

		$this->incStat(1, 'resources_picked');
		$this->incStat(1, 'resources_picked', self::getActivePlayerId());

		$resourceCardsNbr = $this->getNbrResourceCardsInDeck();
		$this->notifyAllPlayers(
			'playerPickResource',
			$message,
			[
				'card' => $card,
				'resourceType' => $card['type'],
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

	public function actionDiscardResource($cardId): void {
		self::checkAction('discardResource');
		$card = $this->resourceCards->getCard($cardId);
		$player = $this->ssPlayers->getActive();
		if ($card['location'] !== 'hand' || $card['location_arg'] != $player->getId()) {
			throw new BgaVisibleSystemException('Card not in your hand'); // NOI18N
		}
		$this->resourceCards->moveCard($card['id'], 'discard');

		$this->notifyAllPlayers(
			'playerDiscardResource',
			clienttranslate('${player_name} discards a resource : ${resourceType}'),
			[
				'card' => $card,
				'resourceType' => $card['type'],
			] + $player->getNotificationArgs()
		);
		$this->gamestate->nextState('transPlayerEndTurn');
	}

	public function actionDiscardResources(array $cardIds): void {
		self::checkAction('discardResources');
		$player = $this->ssPlayers->getActive();

		$cards = [];
		foreach ($cardIds as $cardId) {
			$card = $this->resourceCards->getCard($cardId);
			if ($card['location'] !== 'hand' || $card['location_arg'] != $player->getId()) {
				throw new BgaVisibleSystemException('Card not in your hand'); // NOI18N
			}
			$cards[] = $card;
		}
		$resourceTypes = [];
		foreach ($cards as $card) {
			$this->resourceCards->moveCard($card['id'], 'discard');
			$resourceTypes[] = $card['type'];
		}

		$this->notifyAllPlayers(
			'playerDiscardResource',
			clienttranslate('${player_name} discards resources : ${resourceTypes}'),
			[
				'cards' => $cards,
				'resourceTypes' => $resourceTypes,
			] + $player->getNotificationArgs()
		);

		$this->gamestate->nextState('transPlayerNextPlayer');
	}

	public function actionGiveResourceToAnotherPlayer(int $cardId, int $playerId): void {
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
		$this->notifyAllPlayers(
			'playerShareResource',
			clienttranslate('${player_name} gives a resource : ${resourceType} to ${to_player_name}'),
			[
				'card' => $card,
				'resourceType' => $card['type'],
				'shareAction' => 'give',
				'to_player_name' => $toPlayer->getName(),
				'to_player_id' => $toPlayer->getId(),
			] + $player->getNotificationArgs()
		);
		if ($this->gamestate->state()['name'] === 'mess-hall') {
			$this->incStat(1, 'action_room_mess_hall', self::getActivePlayerId());
		}
		$this->gamestate->nextState('transActionDone');
	}

	public function actionPickResourceFromAnotherPlayer(int $cardId): void {
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
		$this->notifyAllPlayers(
			'playerShareResource',
			clienttranslate('${player_name} takes a resource : ${resourceType} from ${from_player_name}'),
			[
				'card' => $card,
				'resourceType' => $card['type'],
				'shareAction' => 'take',
				'from_player_name' => $fromPlayer->getName(),
				'from_player_id' => $fromPlayer->getId(),
			] + $player->getNotificationArgs()
		);
		if ($this->gamestate->state()['name'] === 'mess-hall') {
			$this->incStat(1, 'action_room_mess_hall', self::getActivePlayerId());
		}
		$this->gamestate->nextState('transActionDone');
	}

	public function actionSwapResourceWithAnotherPlayer(int $cardId, int $card2Id): void {
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
		$this->notifyAllPlayers(
			'playerShareResource',
			clienttranslate('${player_name} gives a resource : ${resourceType} to ${to_player_name}'),
			[
				'card' => $card,
				'resourceType' => $card['type'],
				'shareAction' => 'give',
				'to_player_name' => $withPlayer->getName(),
				'to_player_id' => $withPlayer->getId(),
			] + $player->getNotificationArgs()
		);
		$this->notifyAllPlayers(
			'playerShareResource',
			clienttranslate('${player_name} takes a resource : ${resourceType} from ${from_player_name}'),
			[
				'card' => $card2,
				'resourceType' => $card2['type'],
				'shareAction' => 'take',
				'from_player_name' => $withPlayer->getName(),
				'from_player_id' => $withPlayer->getId(),
			] + $player->getNotificationArgs()
		);
		if ($this->gamestate->state()['name'] === 'mess-hall') {
			$this->incStat(1, 'action_room_mess_hall', self::getActivePlayerId());
		}
		$this->gamestate->nextState('transActionDone');
	}

	public function actionSwapResourceFromDiscard(int $cardId, int $card2Id): void {
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
		$this->notifyAllPlayers(
			'playerPickResource',
			clienttranslate(
				'${player_name} swap a resource : ${resourceType2} from their hand with a ${resourceType} from the discard pile'
			),
			[
				'card' => $card,
				'resourceType' => $card['type'],
				'resourceType2' => $card2['type'],
			] + $player->getNotificationArgs()
		);
		$this->notifyAllPlayers(
			'playerDiscardResource',
			'',
			[
				'card' => $card2,
				'resourceType' => $card2['type'],
			] + $player->getNotificationArgs()
		);
		$this->incStat(1, 'action_room_engine_room', self::getActivePlayerId());
		$this->gamestate->nextState('transActionDone');
	}

	public function actionSelectResourceForRepair(int $cardId, ?string $typeId = null, ?int $position = null): void {
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

		$this->notifyAllPlayers(
			'playerDiscardResource',
			clienttranslate('${player_name} repairs ${roomName} with resource : ${resourceType}'),
			[
				'card' => $card,
				'resourceType' => $card['type'],
				'roomName' => $room->getSlug(),
			] + $player->getNotificationArgs()
		);

		$this->notifyAllPlayers('updateRooms', '', [
			'rooms' => [$room->toArray()],
		]);
		$player->incrementActions(-1);
		$player->save();

		$this->incStat(1, 'repair_done');
		$this->incStat(1, 'repair_done', self::getActivePlayerId());
		if ($stateName === 'playerRoomRepairCentre') {
			$this->incStat(1, 'action_room_repair_centre', self::getActivePlayerId());
		}

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

		$resourceTypes = [];
		foreach ($cards as $card) {
			$this->resourceCards->moveCard($card['id'], 'discard');
			$resourceTypes[] = $card['type'];
		}

		$this->notifyAllPlayers(
			'playerDiscardResource',
			clienttranslate('${player_name} diverts power in ${roomName} with resources : ${resourceTypes}'),
			[
				'cards' => $cards,
				'resourceTypes' => $resourceTypes,
				'roomName' => $room->getSlug(),
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
				'roomName' => $room->getSlug(),
				'player_action_name' => $player->getName(),
				'player_action_id' => $player->getId(),
			]
		);
		$this->incStat(1, 'action_room_crew_quarters', self::getActivePlayerId());
		$this->gamestate->nextState('transActionDone');
	}

	public function actionPutBackResourceCardsInDeck(array $cardIds): void {
		self::checkAction('putBackResourceCardsInDeck');
		$player = $this->ssPlayers->getActive();
		$cardIds = array_reverse($cardIds);
		$reorderedCards = [];
		foreach ($cardIds as $cardId) {
			$card = $this->resourceCards->getCard($cardId);
			if ($card['location'] !== 'reorder') {
				throw new BgaVisibleSystemException('Card not in reorder deck'); // NOI18N
			}
			$this->resourceCards->insertCardOnExtremePosition($card['id'], 'deck', true);
			$reorderedCards[] = $card;
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

		$messageStrings = [];
		$reorderedCards = array_reverse($reorderedCards);
		$this->notifyAllPlayers(
			'message',
			clienttranslate('Next resources cards will be : ${resourceTypes}'),
			[
				'num_damages' => count($cardIds),
				'resourceTypes' => array_column($reorderedCards, 'type'),
			] + $player->getNotificationArgs()
		);
		$this->gamestate->nextState('transActionDone');
	}

	public function actionPutBackDamageCardsInDeck(array $cardIds): void {
		self::checkAction('putBackDamageCardsInDeck');
		$player = $this->ssPlayers->getActive();
		$cardIds = array_reverse($cardIds);
		$roomsSlugs = [];
		foreach ($cardIds as $cardId) {
			$card = $this->damageCards->getCard($cardId);
			if ($card['location'] !== 'reorder') {
				throw new BgaVisibleSystemException('Card not in reorder deck'); // NOI18N
			}
			$this->damageCards->insertCardOnExtremePosition($card['id'], 'deck', true);
			$roomsSlugs[] = $this->damageCardsInfos[$card['type']];
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

		$messageStrings = [];
		$roomsSlugs = array_reverse($roomsSlugs);
		foreach ($roomsSlugs as $index => $roomsSlug) {
			$indexStr = $index > 0 ? $index + 1 : '';
			$messageStrings[] = "\${roomNames$indexStr}";
			$notifData["roomNames$indexStr"] = $roomsSlug;
		}
		$messageString = join(', ' . clienttranslate('then') . ' ', $messageStrings);
		$this->notifyAllPlayers(
			'message',
			clienttranslate('Next damages cards will be : ') . $messageString,
			[
				'num_damages' => count($cardIds),
			] +
				$notifData +
				$player->getNotificationArgs()
		);

		$this->gamestate->nextState('transActionDone');
	}

	private function actionGetActionToken(): void {
		$player = $this->ssPlayers->getActive();
		$tokensLeft = 8 - $this->ssPlayers->countTotalActionTokens();
		if ($tokensLeft <= 0) {
			throw new BgaUserException(self::_('No action token left'));
		}
		$tokens = $player->getActionsTokens();
		$player->setActionsTokens($tokens + 1);
		$player->incrementActions(-1);
		$player->save();
		self::setGameStateValue('hasPickedActionToken', 1);
		$this->notifyPlayerData($player, clienttranslate('${player_name} takes an action token'), []);
		$this->gamestate->nextState('transActionDone');
	}

	public function actionUseToken(): void {
		self::checkAction('useToken');
		self::setGameStateValue('dontWannaUseActionsTokens', 0);
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

	public function actionDontUseToken(): void {
		self::checkAction('dontUseToken');
		self::setGameStateValue('dontWannaUseActionsTokens', 1);
		$player = $this->ssPlayers->getActive();
		$this->gamestate->nextState('transActionDone');
	}

	public function actionPutProtectionTokens(array $positions): void {
		self::checkAction('putProtectionTokens');
		$tokensLeft = 4 - $this->rooms->countTotalProtectionTokens();
		if (count($positions) > 2 || count($positions) > $tokensLeft) {
			throw new \Exception('Invalid number of tokens'); // NOI18N
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

		$this->notifyAllPlayers(
			'updateRooms',
			clienttranslate('${player_name} puts a protection token in ${roomNames}'),
			[
				'rooms' => $updatedRooms,
				'roomNames' => array_column($updatedRooms, 'slug'),
			] + $player->getNotificationArgs()
		);

		$player->incrementActions(-1);
		$player->save();
		$this->incStat(1, 'action_room_armoury', self::getActivePlayerId());
		$this->gamestate->nextState('transActionDone');
	}

	public function actionRestartTurn(): void {
		self::checkAction('restartTurn');
		if (!self::getGameStateValue('canRestartTurn')) {
			throw new BgaUserException(self::_('Cannot restart turn now'));
		}
		$this->gamestate->nextState('transPlayerRestartTurn');
	}

	//////////////////////////////////////////////////////////////////////////////
	//////////// Game state arguments
	////////////

	public function argPlayerTurn(): array {
		$player = $this->ssPlayers->getActive();
		return [
			'canRestartTurn' => (bool) self::getGameStateValue('canRestartTurn'),
			'canUseActionTokens' => !self::getGameStateValue('hasPickedActionToken'),
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
			'canRestartTurn' => (bool) self::getGameStateValue('canRestartTurn'),
			'possibleSources' => $this->whereDoesPlayerCanPickResourceFrom(),
		];
	}

	public function argPlayerPickResourcesCards(): array {
		return [
			'canRestartTurn' => (bool) self::getGameStateValue('canRestartTurn'),
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

	public function argPlayerDiscardResources(): array {
		$player = $this->ssPlayers->getActive();
		$n = $this->resourceCards->countCardInLocation('hand', $player->getId()) - 6;
		return [
			'numCardsToDiscard' => $n,
		];
	}

	//////////////////////////////////////////////////////////////////////////////
	//////////// Game state actions
	////////////

	public function stStartOfTurn() {
		$playerTurnsCount = (int) self::getGameStateValue('playerTurnsCount');
		if ($playerTurnsCount === 0) {
			// First turn
			$this->drawDamageCard('bottom');
			$this->drawDamageCard('bottom');
		}
		self::setGameStateValue('playerTurnsCount', $playerTurnsCount + 1);

		$player = $this->ssPlayers->getActive();

		self::setGameStateValue('dontWannaUseActionsTokens', 0);

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
				clienttranslate('Protection tokens from ${player_name} are removed.'),
				[
					'rooms' => $updatedRooms,
				] + $player->getNotificationArgs()
			);
		}

		$this->saveCurrentState();

		self::setGameStateValue('canRestartTurn', 1);
		self::setGameStateValue('hasPickedActionToken', 0);
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
		$hasPickedActionToken = (bool) self::getGameStateValue('hasPickedActionToken');
		$dontWannaUseActionsTokens = (bool) self::getGameStateValue('dontWannaUseActionsTokens');

		if ($actions === 0) {
			// Check if player has usable action tokens
			if (!$dontWannaUseActionsTokens && !$hasPickedActionToken) {
				if ($player->getActionsTokens() > 0) {
					$this->gamestate->nextState('transPlayerAskActionTokensPlay');
					return;
				}
			}

			$this->gamestate->nextState('transPlayerPickResourcesCards');
		} else {
			// Initialize new save point
			if (!self::getGameStateValue('canRestartTurn')) {
				$this->saveCurrentState();
				self::setGameStateValue('canRestartTurn', 1);
			}
			$this->gamestate->nextState('transPlayerTurn');
		}
	}

	public function stActionCancel() {
		$this->gamestate->nextState('transPlayerTurn');
	}

	public function stEndTurn() {
		// Draw damage card
		$this->drawDamageCard('top');

		// Check if player has too many cards in hand
		$player = $this->ssPlayers->getActive();
		$n = $this->resourceCards->countCardInLocation('hand', $player->getId());
		if ($n > 6) {
			$this->gamestate->nextState('transPlayerDiscardResources');
			return;
		}

		// Check if hull is breached
		$this->checkHullBreach();

		$this->incStat(1, 'turns_number', self::getActivePlayerId());
		$this->incStat(1, 'turns_number');

		$this->gamestate->nextState('transPlayerNextPlayer');
	}

	public function stNextPlayer() {
		// Reset actions to 3
		$player = $this->ssPlayers->getActive();
		$player->setActions(3);
		$player->save();

		$playerId = self::activeNextPlayer();
		self::giveExtraTime($playerId);

		$player = $this->ssPlayers->getActive();
		$currentRoom = $this->rooms->getRoomByPosition($player->getPosition());
		if ($currentRoom->getSlug() === 'medical-bay') {
			if ($currentRoom->getDamageCount() > 0) {
				$this->notifyAllPlayers(
					'message',
					clienttranslate('Medical Bay: ${player_name} takes no action token, as the room is damaged.'),
					$player->getNotificationArgs()
				);
			} else {
				$tokensLeft = 8 - $this->ssPlayers->countTotalActionTokens();
				$tokensMax = min(2, $tokensLeft);
				$tokens = $player->getActionsTokens();
				$player->setActionsTokens($tokens + $tokensMax);
				$player->save();
				$this->incStat(1, 'action_room_medical_bay', self::getActivePlayerId());
				$this->notifyPlayerData(
					$player,
					clienttranslate('Medical Bay: ${player_name} takes ${num} action token(s)'),
					[
						'num' => $tokensMax,
					]
				);
			}
		}

		$this->gamestate->nextState('transPlayerStartOfTurn');
	}

	public function stPlayerRoomCargoHold() {
		$player = $this->ssPlayers->getActive();
		$player->incrementActions(-1);
		$player->save();
		$this->incStat(1, 'action_room_cargo_hold', self::getActivePlayerId());
	}

	public function stPlayerRoomBridge() {
		$player = $this->ssPlayers->getActive();
		$player->incrementActions(-1);
		$player->save();
		$this->incStat(1, 'action_room_bridge', self::getActivePlayerId());
	}

	public function stPlayerRestartTurn() {
		// TODO check
		$this->loadCurrentState();
		$this->gamestate->nextState('transPlayerStartOfTurn');
	}

	//////////////////////////////////////////////////////////////////////////////
	//////////// DEBUG
	//////////// Methods to be called from the chat window in dev studio

	/**
	 * Leave one card in the resource deck, so any deck pick will end the game
	 */
	public function debugEmptyResourceDeck($leave) {
		if (!$leave) {
			$leave = 1;
		}
		$cnt = $this->resourceCards->countCardInLocation('deck');
		if ($cnt <= $leave) {
			throw new BgaUserException("Only $cnt cards now"); // NOI18N
		}
		$this->resourceCards->pickCardsForLocation($cnt - $leave, 'deck', 'discard');
		$this->notifyAllPlayers(
			'message',
			"Leaving $leave cards in resource deck", // NOI18N
			[]
		);
	}

	/**
	 * Leave one card in the damage deck, so next one should be hull damage
	 */
	public function debugEmptyDamageDeck() {
		$cnt = $this->damageCards->countCardInLocation('deck');
		$this->damageCards->pickCardsForLocation($cnt - 1, 'deck', 'discard');
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
		$this->notifyAllPlayers(
			'updateRooms',
			'cheater !', // NOI18N
			[
				'rooms' => $updatedRooms,
			]
		);
	}

	/**
	 * Toggle Divert current room
	 */
	public function debugDivert() {
		$player = $this->ssPlayers->getActive();
		$room = $this->rooms->getRoomByPosition($player->getPosition());
		$room->setDiverted(!$room->isDiverted());
		$room->save();
		$this->notifyAllPlayers(
			'updateRooms',
			'toggle divert', // NOI18N
			[
				'rooms' => [$room->toArray()],
				'roomName' => $room->getSlug(),
			] + $player->getNotificationArgs()
		);
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
				'roomName' => $room->getSlug(),
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
		$this->notifyAllPlayers(
			'updateRooms',
			'Deprotect All', // NOI18N
			[
				'rooms' => $updatedRooms,
			]
		);
	}

	/**
	 * Draw a damage card
	 */
	public function debugDrawDamage() {
		$this->drawDamageCard('top');
	}

	/**
	 * Damage all rooms 100%
	 */
	public function debugDamageAll($repair = 0) {
		$updatedRooms = [];
		foreach ($this->rooms->getRooms() as $room) {
			if ($room->getSlug() === 'energy-core') {
				continue;
			}
			if ($repair) {
				$room->setDamage([false, false, false]);
			} else {
				$room->setDamage([true, true, true]);
			}
			$room->save();
			$updatedRooms[] = $room->toArray();
		}
		$this->notifyAllPlayers(
			'updateRooms',
			'Damage all', // NOI18N
			[
				'rooms' => $updatedRooms,
			]
		);
	}

	/**
	 * Player get a universal (from somewhere)
	 */
	public function debugUniversal() {
		$player = $this->ssPlayers->getActive();
		$cards = $this->resourceCards->getCardsOfType('universal');
		foreach ($cards as $card) {
			$this->resourceCards->moveCard($card['id'], 'hand', $player->getId());
			$this->notifyAllPlayers(
				'playerPickResource',
				'cheater finds a ${resourceType}', // NOI18N
				[
					'card' => $card,
					'resourceType' => 'universal',
				] + $player->getNotificationArgs()
			);
		}
	}

	/**
	 * Player picks resource
	 */
	public function debugPickResource() {
		$player = $this->ssPlayers->getActive();
		$card = $this->resourceCards->pickCardForLocation('deck', 'hand', $player->getId());
		$this->resourceCards->moveCard($card['id'], 'hand', $player->getId());
		$resourceCardsNbr = $this->getNbrResourceCardsInDeck();
		$this->notifyAllPlayers(
			'playerPickResource',
			'cheater picks a ${resourceType}', // NOI18N
			[
				'card' => $card,
				'resourceType' => $card['type'],
				'resourceCardsNbr' => $resourceCardsNbr,
			] + $player->getNotificationArgs()
		);
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
	////////// DB save/restore undo
	//////////

	private function saveCurrentState() {
		$this->undoSavepoint();
	}

	private function loadCurrentState() {
		$this->undoRestorePoint();
		$this->rooms = new SolarStormRooms($this);
		$this->ssPlayers = new SolarStormPlayers($this);
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
