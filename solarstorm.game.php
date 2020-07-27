<?php
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
			// When sharing, which card the player selected to give
			'shareResourceToGive' => 13,
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
		$sql =
			'INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES ';
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
		self::setGameStateInitialValue('shareResourceToGive', 0);

		$this->rooms->generateRooms();

		$this->initializeDecks();

		// Activate first player (which is in general a good idea :) )
		$this->activeNextPlayer();

		/************ End of the game initialization *****/
	}

	private function initializeDecks() {
		// Resource cards
		$cards = [];
		foreach ($this->resourceTypes as $resourceType) {
			$nbr = 15;
			if ($resourceType['id'] === 'universal') {
				// TODO:DIFFICULTY change nbr according to difficulty
				$nbr = 8;
			}
			$cards[] = [
				'type' => $resourceType['id'],
				'type_arg' => null,
				'nbr' => $nbr,
			];
		}
		$this->resourceCards->createCards($cards, 'deck');
		$this->resourceCards->shuffle('deck');

		// Distribute initial resourceCards
		foreach ($this->ssPlayers->getPlayers() as $player) {
			// TODO:NBPLAYERS change the number of cards according to number of players
			$cards = $this->resourceCards->pickCards(2, 'deck', $player->getId());
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

	// FIXME private
	public function drawDamageCard(string $from, bool $notify = true): void {
		if (!in_array($from, ['top', 'bottom'])) {
			throw new \Exception('Invalid position to draw damage card from');
		}

		$cards = $this->damageCards->getCardsInLocation(
			'deck',
			null,
			'location_arg'
		);
		if ($from === 'bottom') {
			$card = $cards[0];
		} else {
			$card = $cards[count($cards) - 1];
		}

		$this->damageCards->moveCard($card['id'], 'discard');
		if ($notify) {
			$this->notifyAllPlayers('updateDamageDiscard', 'drawn damage card', [
				'cards' => [$card],
			]);
		}

		$roomsSlugs = $this->damageCardsInfos[$card['type']];
		$updatedRooms = [];
		foreach ($roomsSlugs as $roomsSlug) {
			$room = $this->rooms->getRoomBySlug($roomsSlug);
			$room->doDamage();
			$room->save();
			$updatedRooms[] = $room->toArray();
		}

		if ($notify) {
			$this->notifyAllPlayers('updateRooms', 'room update', [
				'rooms' => $updatedRooms,
			]);
		}
	}

	// FIXME private
	/**
	 * Assert presence of 2 resources cards on the table.
	 */
	public function assertResourceCardsOnTable(bool $notify = true): void {
		$currentCnt = $this->resourceCards->countCardInLocation('table');
		$needToDrawCnt = 2 - $currentCnt;

		if ($needToDrawCnt <= 0) {
			return;
		}

		$cards = $this->resourceCards->pickCardsForLocation(
			$needToDrawCnt,
			'deck',
			'table'
		);

		if ($notify) {
			if ($notify) {
				$this->notifyAllPlayers('addResourcesCardsOnTable', '', [
					'cards' => $cards,
				]);
			}
		}
	}

	protected function getAllDatas() {
		$result = [];

		$result['rooms'] = $this->rooms->toArray();
		$result['ssPlayers'] = $this->ssPlayers->toArray();
		$result['resourceCardsNbr'] = $this->resourceCards->countCardInLocation(
			'deck'
		);
		$result['resourceTypes'] = array_values($this->resourceTypes);

		$result['damageCardsNbr'] = $this->damageCards->countCardInLocation(
			'deck'
		);
		$result['damageCardsDiscarded'] = $this->damageCards->getCardsInLocation(
			'discard'
		);
		$result[
			'resourceCardsOnTable'
		] = $this->resourceCards->getCardsInLocation('table');

		$data = [];
		foreach ($this->ssPlayers->getPlayers() as $player) {
			$data[$player->getId()] = array_values(
				$this->resourceCards->getCardsInLocation('hand', $player->getId())
			);
		}
		$result['resourceCards'] = $data;

		return $result;
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

	private function notifyPlayerData(
		SolarStormPlayer $player,
		string $message = '',
		array $args = []
	): void {
		$this->notifyAllPlayers(
			'updatePlayerData',
			$message,
			[
				'position' => $player->getPosition(),
			] +
				$args +
				$player->getNotificationArgs()
		);
	}

	private function getPlayersIdsInTheSameRoom($excludeActive = false): array {
		$activePlayer = $this->ssPlayers->getActive();
		$playersInTheSameRoom = $this->ssPlayers->getPlayersAtPosition(
			$activePlayer->getPosition()
		);
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
			$previouslyPickedFromDeck = (bool) self::getGameStateValue(
				'resourcePickedFromDeck'
			);
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

	//////////////////////////////////////////////////////////////////////////////
	//////////// Player actions
	////////////

	public function actionChoose($actionName): void {
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
				$this->gamestate->nextState('transPlayerRepair');
				break;
			case 'room':
				$player = $this->ssPlayers->getActive();
				$room = $this->rooms->getRoomByPosition($player->getPosition());
				$roomSlug = $room->getSlug();
				switch ($roomSlug) {
					case 'crew-quarters':
						$this->gamestate->nextState('transPlayerRoomCrewQuarter');
						break;
					default:
						throw new BgaVisibleSystemException(
							"Room $roomSlug not implemented yet"
						); // NOI18N
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
				sprintf(
					self::_('You cannot move from %s to %s'),
					$currentRoom->getName(),
					$room->getName()
				)
			);
		}

		$player->setPosition($position);
		$player->incrementActions(-1);
		$player->save();
		$this->notifyPlayerData(
			$player,
			clienttranslate('${player_name} moves to ${roomName}'),
			['roomName' => $room->getName()]
		);
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
			$card = $this->resourceCards->pickCardForLocation(
				'deck',
				'hand',
				$player->getId()
			);
		} else {
			// Pick from table
			if (!in_array('table', $possibleFrom)) {
				throw new BgaUserException(
					self::_('You must pick the second card from the deck')
				);
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
			$message = clienttranslate(
				'${player_name} takes a resource from the deck : ${resourceName}'
			);
		} else {
			$message = clienttranslate(
				'${player_name} takes a resource : ${resourceName}'
			);
		}

		$this->notifyAllPlayers(
			'playerPickResource',
			$message,
			[
				'card' => $card,
				'resourceName' => $resourceName,
			] + $player->getNotificationArgs()
		);

		$this->assertResourceCardsOnTable(true);

		$stateName = $this->gamestate->state()['name'];
		if ($stateName === 'pickResources') {
			$previouslyPickedFromDeck = (bool) self::getGameStateValue(
				'resourcePickedFromDeck'
			);
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
		if (
			$card['location'] !== 'hand' ||
			$card['location_arg'] != $player->getId()
		) {
			throw new BgaVisibleSystemException('Card not in your hand'); // NOI18N
		}
		$this->resourceCards->moveCard($card['id'], 'discard');

		$resourceName = $this->resourceTypes[$card['type']]['name'];
		$this->notifyAllPlayers(
			'playerDiscardResource',
			clienttranslate(
				'${player_name} discards a resource : ${resourceName}'
			),
			[
				'card' => $card,
				'resourceName' => $resourceName,
			] + $player->getNotificationArgs()
		);
		$this->gamestate->nextState('transPlayerEndTurn');
	}

	public function actionShareResource($cardId) {
		self::checkAction('shareResource');
		$card = $this->resourceCards->getCard($cardId);
		$player = $this->ssPlayers->getActive();
		if ($card['location'] !== 'hand') {
			throw new BgaVisibleSystemException('Card not in player hand'); // NOI18N
		}

		// Check the resource is either in the active player's hand, or in a
		// players in the same room.
		$cardPlayer = $this->ssPlayers->getPlayer((int) $card['location_arg']);
		$shareAction = null;
		if ($cardPlayer->getId() === $player->getId()) {
			$shareAction = 'give';
		} else {
			$shareAction = 'take';
			if ($cardPlayer->getPosition() !== $player->getPosition()) {
				throw new BgaUserException(
					self::_('This player is not is the same room')
				);
			}
		}

		if ($shareAction === 'take') {
			$this->resourceCards->moveCard($card['id'], 'hand', $player->getId());
			$player->incrementActions(-1);
			$player->save();
			$resourceName = $this->resourceTypes[$card['type']]['name'];
			$this->notifyAllPlayers(
				'playerShareResource',
				clienttranslate(
					'${player_name} takes a resource : ${resourceName} from ${from_player_name}'
				),
				[
					'card' => $card,
					'resourceName' => $resourceName,
					'shareAction' => 'take',
					'from_player_name' => $cardPlayer->getName(),
					'from_player_id' => $cardPlayer->getId(),
				] + $player->getNotificationArgs()
			);
			$this->gamestate->nextState('transActionDone');
			return;
		}

		// Want to give card
		self::setGameStateValue('shareResourceToGive', $card['id']);
		$this->gamestate->nextState('transPlayerShareChoosePlayer');
		return;
	}

	public function actionGiveResource(int $playerId) {
		self::checkAction('giveResource');
		$card = $this->resourceCards->getCard(
			(int) $this->getGameStateValue('shareResourceToGive')
		);
		$player = $this->ssPlayers->getActive();
		$toPlayer = $this->ssPlayers->getPlayer($playerId);
		if (
			$card['location'] !== 'hand' ||
			$card['location_arg'] != $player->getId()
		) {
			throw new BgaVisibleSystemException('Card not in player hand'); // NOI18N
		}
		if ($toPlayer->getPosition() !== $player->getPosition()) {
			throw new BgaUserException(
				self::_('This player is not is the same room')
			);
		}

		$this->resourceCards->moveCard($card['id'], 'hand', $toPlayer->getId());
		$player->incrementActions(-1);
		$player->save();
		$resourceName = $this->resourceTypes[$card['type']]['name'];
		$this->notifyAllPlayers(
			'playerShareResource',
			clienttranslate(
				'${player_name} gives a resource : ${resourceName} to ${to_player_name}'
			),
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

	public function actionSelectResourceForRepair($cardId) {
		self::checkAction('selectResourceForRepair');
		$card = $this->resourceCards->getCard($cardId);
		$player = $this->ssPlayers->getActive();
		if (
			$card['location'] !== 'hand' ||
			$card['location_arg'] != $player->getId()
		) {
			throw new BgaVisibleSystemException('Card not in your hand'); // NOI18N
		}

		$room = $this->rooms->getRoomByPosition($player->getPosition());
		$room->repairWithResource($card['type']);
		$room->save();

		$this->resourceCards->moveCard($card['id'], 'discard');

		$resourceName = $this->resourceTypes[$card['type']]['name'];
		$this->notifyAllPlayers(
			'playerDiscardResource',
			clienttranslate(
				'${player_name} repairs ${roomName} with resource : ${resourceName}'
			),
			[
				'card' => $card,
				'resourceName' => $resourceName,
				'roomName' => $room->getName(),
			] + $player->getNotificationArgs()
		);

		$this->notifyAllPlayers('updateRooms', 'room update', [
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
		$player->incrementActions(-1);
		$player->save();
		$this->notifyPlayerData(
			$playerToMove,
			clienttranslate(
				'${player_name} is moved to ${roomName} by ${player_action_name} (Crew Quarters action)'
			),
			[
				'roomName' => $room->getName(),
				'player_action_name' => $player->getName(),
				'player_action_id' => $player->getId(),
			]
		);
		$this->gamestate->nextState('transActionDone');
	}

	//////////////////////////////////////////////////////////////////////////////
	//////////// Game state arguments
	////////////

	public function argPlayerTurn() {
		$player = $this->ssPlayers->getActive();
		return [
			'actions' => $player->getActions(),
		];
	}

	public function argPlayerMove() {
		$player = $this->ssPlayers->getActive();
		$room = $this->rooms->getRoomByPosition($player->getPosition());
		$possibleDestinations = $room->getPossibleDestinations();
		return [
			'possibleDestinations' => $possibleDestinations,
		];
	}

	public function argPlayerScavengePickCards() {
		return [
			'possibleSources' => $this->whereDoesPlayerCanPickResourceFrom(),
		];
	}

	public function argPlayerPickResourcesCards() {
		return [
			'possibleSources' => $this->whereDoesPlayerCanPickResourceFrom(),
		];
	}

	public function argPlayerShare() {
		return [
			'possiblePlayers' => $this->getPlayersIdsInTheSameRoom(false),
		];
	}

	public function argPlayerShareChoosePlayer() {
		return [
			'possiblePlayers' => $this->getPlayersIdsInTheSameRoom(true),
		];
	}

	//////////////////////////////////////////////////////////////////////////////
	//////////// Game state actions
	////////////

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

		$playerId = self::activeNextPlayer();
		self::giveExtraTime($playerId);
		$this->gamestate->nextState('transPlayerTurn');
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

		throw new feException(
			'Zombie mode not supported at this game state: ' . $statename
		);
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
