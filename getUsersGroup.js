module.exports = {

	/**
	 * Получить пользователей группы
	 * @returns {string}
	 */
	start: function(_, db, bot, msg, group) {
		bot.sendMessage(msg.chat.id, `Начинаем парсить группу ${group}`);

		const Spooky = require('spooky');

		const spooky = new Spooky({
			child: {
				transport: 'http'
			},
			casper: {
				logLevel: 'debug',
				verbose: false,
				viewportSize: {
					width: 1920,
					height: 1080
				}
			}
		}, function (err) {
			if (err) {
				e = new Error('Failed to initialize SpookyJS');
				e.details = err;
				throw e;
			}

			spooky.start("https://web.telegram.org/#/im?p=" + group);

			// ждем появления заголовка группы
			spooky.then(function(){
				this.waitForSelector('.tg_head_peer_title', function() {
					// this.capture('screenshot.png');
				});
			});

			// кликаем по заголовку группы
			spooky.then(function() {
				this.click('.tg_head_peer_title')
			});

			// ждем открытия окна со списком юзеров
			spooky.then(function() {
				// if (this.exists('.md_modal_section_peers_wrap')) {
					this.waitForSelector('.md_modal_section_peers_wrap', function () {
						// this.capture('page-users-group.png');
					});
				// }/* else {
				// 	this.emit('no-users', 'no');
				// 	this.exit();
				// }*/
			});

			// собираем ники юзеров
			spooky.then(function() {
				users = this.evaluate(function () {
					return [].map.call($('div.md_modal_list_peer_name'), function(user) {
						re = /:\w+:/;
						u = user.outerText;
						user = u.replace(re, '');
						return user;
					})
				});
			});

			spooky.then(function() {
				usersname = [];
				index = 1;
				this.each(users, function (key, user) {
					// user = u.replace(/:\w+:/, '');
					this.then(function () {
						if (user !== '') {
							this.clickLabel(user, 'a');
							this.waitForSelector('.md_modal_sections', function() {
								if (this.exists('div[ng-if="user.username"]')) {
									nik = this.fetchText('div[ng-if="user.username"] span');
									// console.log('***** ' + user + ' --- ' + nik);
									usersname.push(nik);

									if (users.length === index) {
										this.emit('users', usersname);
									}

									ms = 1000;
									ms += new Date().getTime();
									while (new Date() < ms){}

								}
							});
						}

						index++;
					});

					this.then(function() {
						if (this.exists('.user_modal_wrap .md_modal_head .md_modal_title_wrap .md_modal_actions_wrap .md_modal_action_close')) {
							if (this.click('.user_modal_wrap .md_modal_head .md_modal_title_wrap .md_modal_actions_wrap .md_modal_action_close')) {
								this.waitForSelector('.md_modal_sections', function () {
									// casper.capture('page-close.png');
								});
							}
						}
					});
				});
			});

			spooky.then(function () {
				this.emit('users', usersname);
			});

			spooky.run();
		});

		spooky.on('no-users', function(m) {
			bot.sendMessage(msg.chat.id, 'Администратор группы закрыл доступ к юзерам. Парсинг не возможен.');
		});

		spooky.on('users', function(users) {
			function isUser(us, user) {
				ret = false;

				for (i = 0; i <= us.length; i++) {
					if (us[i]['userGroup'] === user) {
						ret = true;
						return true;
					}
				}

				return ret;
			}

			db.getUsersGroup(group, (err, usersGroup) => {
				users.forEach(function (user, index) {
					console.log(user);

					let u = _.find(usersGroup, function(o) {
						return o.userGroup === user;
					});

					if (!u) {
						console.log('***** Найден новый юзер ' + user);
						db.addUser(user, group, msg.from.username);
					}
				});
			});


			bot.sendMessage(msg.chat.id, '***** Сохранено юзеров: ' + users.length);
			db.checkGroup(group, msg.from.username);
		});

		spooky.on('error', function (e, stack) {
			console.error(e);
			if (stack) {
				console.log(stack);
			}
		});

		spooky.on('console', function (line) {
			console.log(line);
		});

		spooky.on('remote.message', function(message) {
			// console.log('[Inside Evaluate] ' + message);
		});
	}
};