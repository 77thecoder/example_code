function msgText(user, message) {
	eval_func = function() {
		return message;
	};

	const Spooky = require('spooky');

	const spooky = new Spooky({
		child: {
			// transport: 'http'
		},
		casper: {
			logLevel: 'debug',
			verbose: true,
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

		// spooky.start("https://web.telegram.org/#/im?p=@the_coder");

		spooky.start(`https://web.telegram.org/#/im?p=${user}`, [{
			eval_func: eval_func(),
		}, function() {
			window.message = eval_func;
		}]);

		spooky.then(function() {
			this.clearMemoryCache();
		});

		// ждем появления поля ввода текста
		spooky.then(function(){
			this.wait(2000, function() {
				this.sendKeys('.composer_rich_textarea', window.message);
				this.capture('screenshot.png');
			});
		});

		spooky.then(function () {
			this.click('button[type="submit"]');
			this.wait(1000, function() {
			// 	this.capture('screenshot1.png');
			});

		});

		spooky.then(function() {
			this.exit();
		});

		spooky.run();
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

// сообщение фото
function msgPhoto(user, message) {
		eval_func = function() {
			return message;
		};

		const Spooky = require('spooky');

		const spooky = new Spooky({
			child: {
				// transport: 'http'
			},
			casper: {
				logLevel: 'debug',
				verbose: true,
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

			// spooky.start("https://web.telegram.org/#/im?p=@the_coder");

			spooky.start(`https://web.telegram.org/#/im?p=${user}`, [{
				eval_func: eval_func(),
			}, function() {
				window.message = eval_func;
			}]);

			// ждем появления поля ввода текста
			spooky.then(function(){
				this.wait(2000, function() {
					this.waitForSelector('.composer_rich_textarea', function() {
						this.capture('screenshot.png');
					});
				});
			});

			spooky.then(function () {
				this.fillSelectors('form.im_send_form', {
					'input.im_media_attach_input': '/home/nvbs/PhpstormProjects/nodejs/botSender.dev/src/photo1.jpg'
				}, true);

				this.wait(2000, function() {
					// this.capture('screenshot1.png');
				});
			});

			spooky.run();
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

module.exports = {
	msgText: msgText,
	msgPhoto: msgPhoto
};