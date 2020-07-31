<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * SolarStorm implementation : © Christophe Badoit <gameboardarena@tof2k.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 */

require_once APP_BASE_PATH . 'view/common/game.view.php';

class view_solarstorm_solarstorm extends game_view {
	function getGameName() {
		return 'solarstorm';
	}
	function build_page($viewArgs) {
		// Get players & players number
		$players = $this->game->loadPlayersBasicInfos();
		$players_nbr = count($players);

		/*********** Do not change anything below this line  ************/
	}
}
