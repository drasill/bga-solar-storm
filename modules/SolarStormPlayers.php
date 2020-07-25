<?php

declare(strict_types=1);

class SolarStormPlayers extends APP_GameClass {
	/** @var SolarStormPlayer[] */
	private $players = [];

	/** @var SolarStorm */
	private $table;

	/**
	 * Constructor
	 */
	public function __construct(SolarStorm $table) {
		$this->table = $table;
		$this->load();
	}

	public function load() {
		$this->players = [];

		$playersData = self::getCollectionFromDb(
			'SELECT player_id, player_name, player_color, player_no FROM player'
		);
		foreach ($playersData as $playerData) {
			$player = new SolarStormPlayer($playerData);
			$this->players[$player->getId()] = $player;
		}
	}

	public function getPlayers(): array {
		return $this->players;
	}

	public function getActive(): SolarStormPlayer {
		$playerId = $this->table->getActivePlayerId();
		return $this->players[$playerId];
	}

	public function toArray(): array {
		return array_values(
			array_map(function ($p) {
				return $p->toArray();
			}, $this->players)
		);
	}
}
