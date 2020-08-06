{OVERALL_GAME_HEADER}

<!-- 
--------
-- BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
-- SolarStorm implementation : © Christophe Badoit <gameboardarena@tof2k.com>
-- 
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-->

<div class="ss-wrapper">

	<div class="ss-resource-reorder-dialog">
		<div class="ss-resource-reorder-dialog__title"></div>
		<div class="ss-resource-reorder-deck" id="ss-resource-reorder-deck"></div>
	</div>

	<div class="ss-damage-reorder-dialog">
		<div class="ss-damage-reorder-dialog__title"></div>
		<div class="ss-damage-reorder-deck" id="ss-damage-reorder-deck"></div>
	</div>

	<div class="ss-dice-result-dialog">
		<div class="ss-dice-result-dialog__dice ss-dice"></div>
		<div class="ss-dice-result-dialog__message"></div>
	</div>

	<div class="ss-play-area">

		<div class="ss-rooms-wrapper">
			<div class="ss-rooms"></div>
		</div>

		<div class="ss-decks-wrapper">
			<div class="ss-decks-wrapper-top">
				<div class="ss-damage-deck-wrapper">
					<div class="ss-section-title ss-damage-deck__title"></div>
					<div class="ss-damage-deck" id="ss-damage-deck"></div>
				</div>
				<div class="ss-resource-deck">
					<div class="ss-section-title ss-resource-deck__title"></div>
					<div class="ss-resource-deck__source ss-resource-deck__deck ss-resource-card">
						<div class="ss-resource-deck__deck__number"></div>
					</div>
					<div class="ss-resource-deck__source ss-resource-deck__table" id="ss-resource-deck__table"></div>
				</div>
			</div>
			<div class="ss-players-hands"></div>
		</div>

	</div>
	
	<div class="ss-players-area">
	</div>

</div>

<script type="text/javascript">
</script>  

{OVERALL_GAME_FOOTER}
