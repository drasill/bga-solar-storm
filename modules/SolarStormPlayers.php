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
			'SELECT player_id, player_name, player_color FROM player'
		);
		$playerPositions = self::getCollectionFromDb(
			'SELECT player_id, position FROM player_positions'
		);
		foreach ($playersData as $playerData) {
			$position = $playerPositions[$playerData['player_id']] ?? 4;
			$player = new SolarStormPlayer($playerData, $position);
			$this->players[$player->getId()] = $player;
		}
	}

	public function getPlayers(): array {
		return $this->players;
	}
	public function toArray(): array {
		return array_map(function ($p) {
			return $p->toArray();
		}, $this->players);
	}
}
