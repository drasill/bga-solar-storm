/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * SolarStorm implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * solarstorm.js
 *
 * SolarStorm user interface script
 *
 * In this file, you are describing the logic of your user interface, in Javascript language.
 *
 */

define([
	'dojo',
	'dojo/_base/declare',
	'ebg/core/gamegui',
	'ebg/counter'
], function(dojo, declare) {
	return declare('bgagame.solarstorm', ebg.core.gamegui, {
		constructor: function() {
			this.rooms = new SSRooms()
		},

		setup: function(gamedatas) {
			// Setting up player boards
			for (var player_id in gamedatas.players) {
				var player = gamedatas.players[player_id]
			}
			this.initializeRooms(gamedatas.rooms)
			this.setupNotifications()

			document
				.getElementsByClassName('ss-playarea')[0]
				.addEventListener('click', this.onPlayAreaClick.bind(this))
		},

		initializeRooms(roomsData) {
			roomsData.forEach(roomData => {
				const room = new SSRoom(
					+roomData.room,
					+roomData.position,
					+roomData.damage,
					roomData.diverted == '1'
				)
				this.rooms.addRoom(room)
			})
		},

		onPlayAreaClick(evt) {
			console.log(evt)
			const el = evt.target

			// DEBUG test
			if (el.classList.contains('ss-room')) {
				const room = this.rooms.getRoomForEl(el)
				room.setDamage((room.damage + 1) % 4)
				room.setDiverted(!room.diverted)
				dojo.stopEvent(evt)
			}
		},

		///////////////////////////////////////////////////
		//// Game & client states

		// onEnteringState: this method is called each time we are entering into a new game state.
		//                  You can use this method to perform some user interface changes at this moment.
		//
		onEnteringState: function(stateName, args) {
			console.log('Entering state: ' + stateName)

			switch (stateName) {
				/* Example:
            
            case 'myGameState':
            
                // Show some HTML block at this game state
                dojo.style( 'my_html_block_id', 'display', 'block' );
                
                break;
           */

				case 'dummmy':
					break
			}
		},

		// onLeavingState: this method is called each time we are leaving a game state.
		//                 You can use this method to perform some user interface changes at this moment.
		//
		onLeavingState: function(stateName) {
			console.log('Leaving state: ' + stateName)

			switch (stateName) {
				/* Example:
            
            case 'myGameState':
            
                // Hide the HTML block we are displaying only during this game state
                dojo.style( 'my_html_block_id', 'display', 'none' );
                
                break;
           */

				case 'dummmy':
					break
			}
		},

		// onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
		//                        action status bar (ie: the HTML links in the status bar).
		//
		onUpdateActionButtons: function(stateName, args) {
			console.log('onUpdateActionButtons: ' + stateName)

			if (this.isCurrentPlayerActive()) {
				switch (
					stateName
					/*               
                 Example:
 
                 case 'myGameState':
                    
                    // Add 3 action buttons in the action status bar:
                    
                    this.addActionButton( 'button_1_id', _('Button 1 label'), 'onMyMethodToCall1' ); 
                    this.addActionButton( 'button_2_id', _('Button 2 label'), 'onMyMethodToCall2' ); 
                    this.addActionButton( 'button_3_id', _('Button 3 label'), 'onMyMethodToCall3' ); 
                    break;
*/
				) {
				}
			}
		},

		///////////////////////////////////////////////////
		//// Utility methods

		/*
        
            Here, you can defines some utility methods that you can use everywhere in your javascript
            script.
        
        */

		///////////////////////////////////////////////////
		//// Player's action

		/*
        
            Here, you are defining methods to handle player's action (ex: results of mouse click on 
            game objects).
            
            Most of the time, these methods:
            _ check the action is possible at this game state.
            _ make a call to the game server
        
        */

		/* Example:
        
        onMyMethodToCall1: function( evt )
        {
            console.log( 'onMyMethodToCall1' );
            
            // Preventing default browser reaction
            dojo.stopEvent( evt );

            // Check that this action is possible (see "possibleactions" in states.inc.php)
            if( ! this.checkAction( 'myAction' ) )
            {   return; }

            this.ajaxcall( "/solarstorm/solarstorm/myAction.html", { 
                                                                    lock: true, 
                                                                    myArgument1: arg1, 
                                                                    myArgument2: arg2,
                                                                    ...
                                                                 }, 
                         this, function( result ) {
                            
                            // What to do after the server call if it succeeded
                            // (most of the time: nothing)
                            
                         }, function( is_error) {

                            // What to do after the server call in anyway (success or failure)
                            // (most of the time: nothing)

                         } );        
        },        
        
        */

		///////////////////////////////////////////////////
		//// Reaction to cometD notifications

		/*
            setupNotifications:
            
            In this method, you associate each of your game notifications with your local method to handle it.
            
            Note: game notification names correspond to "notifyAllPlayers" and "notifyPlayer" calls in
                  your solarstorm.game.php file.
        
        */
		setupNotifications: function() {
			console.log('notifications subscriptions setup')

			// TODO: here, associate your game notifications with local methods

			// Example 1: standard notification handling
			// dojo.subscribe( 'cardPlayed', this, "notif_cardPlayed" );

			// Example 2: standard notification handling + tell the user interface to wait
			//            during 3 seconds after calling the method in order to let the players
			//            see what is happening in the game.
			// dojo.subscribe( 'cardPlayed', this, "notif_cardPlayed" );
			// this.notifqueue.setSynchronous( 'cardPlayed', 3000 );
			//
		}

		// TODO: from this point and below, you can write your game notifications handling methods

		/*
        Example:
        
        notif_cardPlayed: function( notif )
        {
            console.log( 'notif_cardPlayed' );
            console.log( notif );
            
            // Note: notif.args contains the arguments specified during you "notifyAllPlayers" / "notifyPlayer" PHP call
            
            // TODO: play the card in the user interface.
        },    
        
        */
	})
})

class SSRooms {
	rooms = []

	addRoom(room) {
		this.rooms.push(room)
	}

	getRoomForEl(el) {
		return this.rooms.find(r => r.el === el)
	}
}

class SSRoom {
	room = null
	position = null
	damage = null
	diverted = false

	el = null
	divertedTokenEl = null

	constructor(room, position, damage, diverted) {
		this.room = room
		this.position = position
		this.assertEl()
		this.setDamage(damage)
		this.setDiverted(diverted)
	}

	assertEl() {
		let el = document.getElementsByClassName('ss-room--${this.room}')[0]
		if (el) {
			this.el = el
			return
		}
		const roomsEl = document.getElementsByClassName('ss-rooms')[0]
		el = dojo.create(
			'div',
			{
				class: `ss-room ss-room--pos-${this.position} ss-room--${this.room}`
			},
			roomsEl
		)
		if (this.room !== 0) {
			for (let i = 0; i < 3; i++) {
				dojo.create(
					'div',
					{ class: `ss-room__damage ss-room__damage--${i}` },
					el
				)
			}
			this.divertedTokenEl = dojo.create(
				'div',
				{ class: 'ss-room__diverted-token' },
				el
			)
		}
		this.el = el
	}

	setDamage(damage) {
		if (this.room === 0) {
			return
		}
		this.el.classList.remove('ss-room--damaged-0')
		this.el.classList.remove('ss-room--damaged-1')
		this.el.classList.remove('ss-room--damaged-2')
		this.el.classList.remove('ss-room--damaged-3')
		this.damage = damage
		this.el.classList.add(`ss-room--damaged-${damage}`)
	}

	setDiverted(diverted) {
		if (this.room === 0) {
			return
		}
		this.diverted = diverted
		this.divertedTokenEl.classList[diverted ? 'add' : 'remove'](
			'ss-room__diverted-token--visible'
		)
	}
}
