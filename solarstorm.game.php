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
			$room->setDamage($room->getDamage() + 1);
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

	//////////////////////////////////////////////////////////////////////////////
	//////////// Player actions
	////////////

	public function actionChoose($actionName): void {
		self::checkAction('choose');
		switch ($actionName) {
			case 'move':
				$this->gamestate->nextState('transMove');
				break;
			default:
				throw new BgaUserException("Invalid action $actionName");
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

	public function actionPickResource($cardId) {
		$player = $this->ssPlayers->getActive();

		// Depending on state, player can pick from deck (facedown) and/or table (face up)
		$stateName = $this->gamestate->state()['name'];
		$canPickFromTable = true;
		if ($stateName === 'pickResources') {
			// If we are in the 'pickResources' state (phase 2)
			$previouslyPickedFromDeck = (bool) self::getGameStateValue(
				'resourcePickedFromDeck'
			);
			if ($previouslyPickedFromDeck) {
				// Now, can only pick from deck
				$canPickFromTable = false;
			}
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
			if (!$canPickFromTable) {
				throw new BgaUserException(self::_('You must pick from the deck'));
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

		if ($stateName === 'pickResources') {
			if (!$fromDeck || $previouslyPickedFromDeck) {
				// Picked from table, or second pick from deck: end state now
				self::setGameStateValue('resourcePickedFromDeck', 0);
				$this->gamestate->nextState('transPlayerEndTurn');
				return;
			}
			self::setGameStateValue('resourcePickedFromDeck', 1);
		}

		$this->gamestate->nextState('transPlayerPickResourcesCards');
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

	//////////////////////////////////////////////////////////////////////////////
	//////////// Game state actions
	////////////

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
