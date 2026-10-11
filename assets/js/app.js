// import './closest-polyfill';

class onyxAcfPoll {
	constructor() {
		this.response = [];
		this.prefix = 'onyx-poll';
		this.mpoll = document.querySelector(`#onyx-poll-modal`);
		this.polls = document.querySelectorAll(`.onyx-poll-widget`);
		this.element = {};
		this.pendingVote = false;
		this.name = {
			parent: this.prefix,
			modal: `${this.prefix}-modal`,
			widget: `${this.prefix}-widget`,
			wrapper: `${this.prefix}-wrapper`,
			question: `${this.prefix}-question`,
			list: `${this.prefix}-choices`,
			choice: `${this.prefix}-choice`,
			footer: `${this.prefix}-footer`,

			message: `${this.prefix}-message`,
			total: `${this.prefix}-total`,

			voteButton: `${this.prefix}-vote`,
			viewButton: `${this.prefix}-view`,
			closeButton: `${this.prefix}-close`,

			loader: `${this.prefix}-loader`,
			events: false,
		};

		this.submitVote = this.submitVote.bind(this);
		this.preparePolls();
	}

	preparePolls() {
		const promisses = [];

		if (this.mpoll && ! this.getCookie('onyx_poll_modal')) {
			promisses.push(this.requestPoll(this.mpoll.getAttribute('data-poll'))
				.then((data) => {
					if (data.code !== 'error') {
						this.events = this.renderTemplate(data, this.mpoll);
					} else {
						this.mpoll = this.mpoll.remove();
						console.log(`Warn Modal: ${data.message}`);
					}
				})
				.catch((error) => console.log(error)));
		}

		if (this.polls.length >= 1) {
			this.polls.forEach((poll) => {
				promisses.push(this.requestPoll(poll.getAttribute('data-poll'))
					.then((data) => {
						if (data.code !== 'error') {
							this.events = this.renderTemplate(data, poll);
						} else {
							console.log(`Warn: ${data.message}`);
							poll.classList.add('onyx-poll-invalid'); // temp
							poll.textContent = data.message;
						}
					})
					.catch((error) => console.log(error)));
			});
		}

		Promise.all(promisses).then(() => {
			if (this.events) {
				this.eventHandlers();
			}
		});
	}

	requestPoll(pollID) {
		const getUrl = new URL(onyxpoll.listurl);
		getUrl.searchParams.set('id', pollID);
		// Fetch a session-bound token at runtime, independently of page/CDN caches.
		getUrl.searchParams.set('_onyx', Date.now());
		return new Promise((resolve, reject) => {
			if (! /^[1-9][0-9]*$/.test(String(pollID))) {
				return reject({ message: onyxpoll.labels.error });
			}

			const self = this;
			const xhr = new XMLHttpRequest();
			xhr.open('GET', getUrl);
			xhr.timeout = 15000;
			xhr.onerror = xhr.ontimeout = xhr.onabort = () => reject({ message: onyxpoll.labels.error });
			xhr.send(null);
			xhr.onload = function() {
				if (this.status >= 200 && this.status < 400) {
					let json;
					try {
						json = JSON.parse(this.response);
					} catch (error) {
						return reject({ message: onyxpoll.labels.error });
					}
					self.response[json.id] = json;
					resolve(json);
				} else {
					reject({ message: onyxpoll.labels.error });
				}
			};
		});
	}

	requestVote(choice) {
		const voteOptions = {
			choice: choice.getAttribute('data-choice'),
			poll: choice.getAttribute('data-poll'),
		};
		const displayedVersion = Number(choice.getAttribute('data-version'));
		return this.requestPoll(voteOptions.poll).then((poll) => {
			if (! poll.vote_token || poll.version !== displayedVersion || String(poll.id) !== voteOptions.poll) {
				return Promise.reject({ message: onyxpoll.labels.refresh });
			}
			return new Promise((resolve, reject) => {
				const self = this;
				const xhr = new XMLHttpRequest();
				xhr.open('POST', onyxpoll.voteurl);
				xhr.setRequestHeader('Content-Type', 'application/json');
				xhr.setRequestHeader('X-Onyx-Poll-Token', poll.vote_token);
				xhr.timeout = 15000;
				xhr.onerror = xhr.ontimeout = xhr.onabort = () => reject({ message: onyxpoll.labels.error });
				xhr.send(JSON.stringify(voteOptions));

				xhr.onload = function() {
					let json;
					try {
						json = JSON.parse(this.response);
					} catch (error) {
						return reject({ message: onyxpoll.labels.error });
					}
					if (this.status >= 200 && this.status < 400) {
						self.response[json.poll] = json;
						resolve(json);
					} else {
						reject(json);
					}
				};
			});
		});
	}

	renderTemplate(data, poll) {
		const cookieLimit = data.limit_vote === 1 ? '' : this.getCookie(`onyx_poll_limit_${data.id}`);

		// poll template
		poll.innerHTML = `
			<div id='${this.name.wrapper}-${data.id}' class="${this.name.wrapper}">
				<p class='${this.name.question}'>${this.escapeHTML(data.title)}</p>
				<ul class='${this.name.list}'></ul>
				<div class="${this.name.footer}">
					<p class='${this.name.message}'></p>
					${data.results ? `
						<p class='${this.name.total}'>
							<strong>${onyxpoll.labels.total}: </strong>
							<span></span>
						</p>
					` : ``}
					${(typeof data.results != 'undefined' && ! data.expired && ! cookieLimit) ? `
						<a href="#" class="onyx-poll-ft-btn ${this.name.voteButton}">${onyxpoll.labels.vote}</a>
						<a href="#" class="onyx-poll-ft-btn ${this.name.viewButton}">${onyxpoll.labels.view}</a>
					` : ``}
				</div>
			</div>
			<span class="${this.name.loader}"><span class="spinner"></span></span>
			${(poll.id === this.name.modal) ? `
				<span class="${this.name.closeButton}"></span>
			` : ``}
		`;

		// add poll choices
		poll.list = poll.querySelector(`.${this.name.list}`);
		data.answers.forEach((choice) => {
			const choiceEl = document.createElement('li');
			choiceEl.setAttribute('data-choice', choice.option);
			choiceEl.setAttribute('data-poll', data.id);
			choiceEl.setAttribute('data-version', data.version);
			choiceEl.innerHTML = `
				${data.has_image ? `<span class='image'><img loading='lazy' src='${this.escapeHTML(choice.image)}' title='${this.escapeHTML(choice.answer)}' alt='${this.escapeHTML(choice.answer)}'></span>` : ``}
				<span class='answer'>${this.escapeHTML(choice.answer)}</span>`;
			if (! data.expired && ! cookieLimit) {
				choiceEl.className = this.name.choice; // enable onclick event
			}
			poll.list.appendChild(choiceEl);
		});

		if (data.expired || cookieLimit) {
			this.showResults(false, poll);
		}

		return true;
	}

	eventHandlers() {
		this.element.choices = document.querySelectorAll(`.${this.name.choice}`);
		this.element.voteButton = document.querySelectorAll(`.${this.name.voteButton}`);
		this.element.viewButton = document.querySelectorAll(`.${this.name.viewButton}`);
		this.element.closeButton = document.querySelector(`.${this.name.closeButton}`);

		// show / close modal poll
		if (this.mpoll && ! this.getCookie('onyx_poll_modal')) {
			this.element.modal = document.getElementById(this.name.modal);
			this.element.modal.classList.add('active', 'show');
			this.element.closeButton.onclick = () => {
				if (onyxpoll.modaltime >= 1) {
					console.log('cookie created');
					this.createCookie('onyx_poll_modal', 1, onyxpoll.modaltime);
				}
				this.element.modal.remove();
			};
		}

		// view poll results button and vote poll button (back from results)
		if (this.element.viewButton) {
			for (let i = 0; i < this.element.viewButton.length; i++) {
				this.element.viewButton[i].onclick = (event) => this.showResults(event, false);
				this.element.voteButton[i].onclick = (event) => this.showPoll(event);
			}
		}

		// vote events on poll choices
		for (let i = 0; i < this.element.choices.length; i++) {
			this.element.choices[i].addEventListener('click', this.submitVote);
		}
	}

	submitVote(event) {
		event.preventDefault();
		const t = event.currentTarget;
		if (! t || this.pendingVote) {
			return;
		}
		const poll = t.closest(`.${this.name.parent}`);
		this.pendingVote = true;
		this.togglePollLoader(poll, false);
		this.requestVote(t)
			.then(function(response) {
				this.togglePollLoader(poll, true);
				this.handleMessage(poll, response.message, response.code);
				poll.classList.add('voted');

				if (response.code == 'success') {
					t.classList.add('choosed');
				}

				// set cookie for modal time if vote is from a modal
				if (poll.id === this.name.modal && onyxpoll.modaltime >= 1) {
					this.createCookie('onyx_poll_modal', 1, onyxpoll.modaltime);
				}

				// remove list options if no results option is marked;
				if (typeof response.results != 'undefined') {
					poll.querySelector(`.${this.name.viewButton}`).click();
				} else {
					poll.querySelector(`.${this.name.list}`).remove();
				}
			}.bind(this))
			.catch(function(err) {
				console.warn(err.message);
				poll.classList.remove('voted');
				this.togglePollLoader(poll, true);
				this.handleMessage(poll, err.message, 'error');
			}.bind(this))
			.finally(() => {
				this.pendingVote = false;
			});
	}

	showResults(event, poll) {
		if (event) {
			event.preventDefault();
		}
		poll = (event) ? event.target.closest(`.${this.name.parent}`) : poll;
		const res = this.response[poll.getAttribute('data-poll')];
		if (! res || ! res.results) {
			return;
		}

		poll.classList.add('view');
		poll.querySelector(`.${this.name.total} span`).textContent = res.results.total;

		const choosedChoice = this.getCookie(`onyx_poll_choice_${res.id}`);
		if (/^[1-9][0-9]*$/.test(choosedChoice)) {
			const chosen = poll.querySelector(`li[data-choice="${choosedChoice}"]`);
			if (chosen) {
				chosen.classList.add('choosed');
			}
		}

		const choices = poll.querySelectorAll(`.${this.name.list} li`);
		choices.forEach((choice, i) => {
			const votes = res.answers[i].votes;
			const percentFull = res.answers[i].percent;
			const percent = (percentFull >= 1) ? percentFull.toFixed(2) + '%' : '0%';
			const label = (votes != 1) ? onyxpoll.labels.votes : onyxpoll.labels.vote_noun;

			let result = `${votes} ${label} / ${percent}`;
			if (res.results.type == 2) {
				result = `${votes} ${label}`;
			} else if (res.results.type == 1) {
				result = `${percent}`;
			}

			choice.removeEventListener('click', this.submitVote);
			choice.style.setProperty('--choicePercentage', `${percent}`);
			choice.style.setProperty('--choiceResult', `"${result}"`);
		});
	}

	showPoll(event) {
		event.preventDefault();
		const t = event.target;
		const poll = t.closest(`.${this.name.parent}`);
		poll.classList.remove('view');

		const choices = poll.querySelectorAll(`.${this.name.choice}`);
		choices.forEach((choice) => {
			choice.addEventListener('click', this.submitVote);
			choice.classList.remove('choosed');
			choice.style.removeProperty('--choicePercentage');
			choice.style.removeProperty('--choiceResult');
		});
	}

	handleMessage(poll, message, code) {
		const e = poll.querySelector(`.${this.name.message}`);
		e.classList.remove('error', 'success', 'warn', 'not_allowed');
		e.classList.add(code);
		e.textContent = message;
	}

	togglePollLoader(poll, active = true) {
		let removeClass, addClass;
		if (active) {
			removeClass = 'loading';
			addClass = 'active';
		} else {
			removeClass = 'active';
			addClass = 'loading';
		}
		poll.classList.remove(removeClass);
		poll.classList.add(addClass);
	}

	escapeHTML(value) {
		const element = document.createElement('span');
		element.textContent = String(value == null ? '' : value);
		return element.innerHTML.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
	}

	getCookie(cname) {
		var name = cname + '=';
		var ca = document.cookie.split(';');
		for (var i = 0; i < ca.length; i++) {
			var c = ca[i];
			while (c.charAt(0) == ' ') {
				c = c.substring(1);
			}
			if (c.indexOf(name) == 0) {
				try {
					return decodeURIComponent(c.substring(name.length));
				} catch (error) {
					return '';
				}
			}
		}
		return '';
	}

	createCookie(name, value, hours) {
		var expires;
		if (hours) {
			var now = new Date();
			now.setTime(now.getTime() + (hours * 60 * 60 * 1000));
			expires = '; expires=' + now.toUTCString();
		} else {
			expires = '';
		}
		document.cookie = name + '=' + value + expires + '; path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
	}
}

// Native blocks render their preview through the editor module.
if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', () => new onyxAcfPoll());
} else {
	new onyxAcfPoll();
}
