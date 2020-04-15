// import './promisse-polyfill.min-min'

function onyxGetCookie(cname) {
	var name = cname + '=';
	var decodedCookie = decodeURIComponent(document.cookie);
	var ca = decodedCookie.split(';');
	for (var i = 0; i < ca.length; i++) {
		var c = ca[i];
		while (c.charAt(0) == ' ') {
			c = c.substring(1);
		}
		if (c.indexOf(name) == 0) {
			return c.substring(name.length, c.length);
		}
	}
	return '';
}

class onyxPoll {
	constructor() {
		self = this;
		this.pollParent = 'onyx-poll';
		this.modalEl = 'onyx-poll-modal';
		this.loader = 'onyx-poll-loader';
		this.closeButton = 'onyx-poll-close';
		this.viewButton = 'onyx-poll-view';
		this.voteButton = 'onyx-poll-vote';
		this.choicesEl = 'onyx-poll-choice';
		this.messageEl = 'onyx-poll-message';
		this.totalEl = 'onyx-poll-total';
		this.listChoicesEl = 'onyx-poll-choices';
		this.pollWrapper = 'onyx-poll-wrapper';
		this.footerEl = 'onyx-poll-footer';
		this.pollQuestion = 'onyx-poll-question';
		this.results = null;
	}

	eventHandlers() {
		const poll = {
			parent: document.querySelector(`.${this.pollParent}`),
			modal: document.querySelector(`.${this.modalEl}`),
			loader: document.querySelector(`.${this.loader}`),
			closeButton: document.querySelector(`.${this.closeButton}`),
			viewButton: document.querySelector(`.${this.viewButton}`),
			voteButton: document.querySelector(`.${this.voteButton}`),
			choices: document.querySelectorAll(`.${this.choicesEl}`),
			totalEl: document.querySelector(`.${this.totalEl} span`),
		};

		// close modal poll
		poll.closeButton.onclick = () => poll.modal.remove();

		// view poll results button and vote poll button (back from results)
		if (typeof this.results.results != 'undefined') {
			poll.viewButton.onclick = () => this.showResults(poll);
			poll.voteButton.onclick = () => this.showPoll(poll);
		}

		// vote poll
		this.handleVoteEvent(poll);
	}

	requestVote(choiceEl) {
		const voteOptions = {
			choice: choiceEl.getAttribute('data-choice'),
			poll: choiceEl.getAttribute('data-poll'),
		};
		return new Promise((resolve, reject) => {
			const xhr = new XMLHttpRequest();
			xhr.open('POST', `${onyxpoll.apiurl}onyx/polls/vote`);
			xhr.setRequestHeader('Content-Type', 'application/json');
			xhr.send(JSON.stringify(voteOptions));

			xhr.onload = function() {
				if (this.status >= 200 && this.status < 400) {
					self.results = JSON.parse(this.response);
					resolve(JSON.parse(this.response));
				} else {
					reject('Voting not completed.');
				}
			};
		});
	}

	requestModal() {
		return new Promise((resolve, reject) => {
			const xhr = new XMLHttpRequest();
			xhr.open('GET', `${onyxpoll.apiurl}onyx/polls/list/?modal=1`);
			xhr.send(null);
			xhr.onload = function() {
				if (this.status >= 200 && this.status < 400) {
					self.results = JSON.parse(this.response);
					resolve(self.results);
				} else {
					reject('Poll cannot be loaded.');
				}
			};
		});
	}

	prepareModal() {
		const modalEl = document.getElementById(`${this.modalEl}`);
		if (modalEl != null) {
			this.requestModal()
				.then((data) => this.renderModal(data, modalEl))
				.catch((error) => console.warn(error));
		}
	}

	renderModal(data, modalEl) {
		// poll template
		modalEl.innerHTML = `
			<div id='${this.pollWrapper}-${data.id}' class="${this.pollWrapper}">
				<p class='${this.pollQuestion}'>${data.title}</p>
				<ul class='${this.listChoicesEl}'></ul>
				<div class="${this.footerEl}">
					<p class='${this.messageEl}'></p>
					${data.results ? `
						<p class='${this.totalEl}'>
							<strong>${onyxpoll.labels.total}: </strong>
							<span></span>
						</p>
					` : ``}
				</div>
			</div>
			<span class="${this.loader}"><span class="spinner"></span></span>
			<span class="${this.closeButton}"></span>
		`;

		// add view results option
		const pollFooter = document.querySelector(`.${this.footerEl}`);
		if (typeof data.results != 'undefined') {
			pollFooter.innerHTML += `
				<a href="#" class="onyx-poll-ft-btn ${this.voteButton}">${onyxpoll.labels.vote}</a>
				<a href="#" class="onyx-poll-ft-btn ${this.viewButton}">${onyxpoll.labels.view}</a>`;
		}

		// add poll choices
		const pollChoices = document.querySelector(`.${this.listChoicesEl}`);
		for (const choice of data.answers) {
			const choiceEl = document.createElement('li');
			choiceEl.setAttribute('data-choice', choice.option);
			choiceEl.setAttribute('data-poll', data.id);
			choiceEl.className = this.choicesEl;
			choiceEl.textContent = choice.answer;
			pollChoices.appendChild(choiceEl);
		}

		// show modal
		modalEl.classList.add('show');

		// add event handlers
		this.eventHandlers();
	}

	submitVote() {
		const parent = this.parentNode.parentNode.parentNode;
		self.togglePollActivation(parent, false);
		self.requestVote(this)
			.then(function(response) {
				self.togglePollActivation(parent, true);
				self.handleMessage(response.message, response.code);
				parent.classList.add('voted');

				if (response.code == 'success') {
					this.classList.add('choosed');
				}

				// improve this block;
				if (typeof response.results != 'undefined') {
					document.querySelector(`.${self.viewButton}`).click();
				} else {
					document.querySelector(`.${self.listChoicesEl}`).remove();
				}
			}.bind(this))
			.catch(function() {
				self.togglePollActivation(parent, true);
				self.handleMessage(onyxpoll.labels.error, 'error');
			});
	}

	togglePollActivation(element, active = true) {
		let removeClass, addClass;
		if (active) {
			removeClass = 'loading';
			addClass = 'active';
		} else {
			removeClass = 'active';
			addClass = 'loading';
		}
		element.classList.remove(removeClass);
		element.classList.add(addClass);
	}

	handleMessage(message, code) {
		const messageEl = document.querySelector(`.${this.messageEl}`);
		messageEl.classList.remove('error', 'success', 'warn', 'not_allowed');
		messageEl.classList.add(code);
		messageEl.textContent = message;
	}

	handleVoteEvent(poll) {
		const choices = poll.choices;
		for (let i = 0; i < choices.length; i++) {
			choices[i].addEventListener('click', this.submitVote);
		}
	}

	showResults(els) {
		const res = this.results;
		els.parent.classList.add('view');
		els.totalEl.textContent = res.results.total;

		const choosedChoice = onyxGetCookie(`onyx_poll_cookie_${res.id}`);
		if (choosedChoice) {
			document.querySelector(`li[data-choice="${choosedChoice}"]`).classList.add('choosed');
		}

		for (let i = 0; i < els.choices.length; i++) {
			const choice = els.choices[i];
			const votes = res.answers[i].votes;
			const percent = res.answers[i].percent.toFixed(2) + '%';
			let result = `${votes} ${onyxpoll.labels.votes} / ${percent}`;

			if (this.results.results.type == 2) {
				result = `${votes} ${onyxpoll.labels.votes}`;
			} else if (this.results.results.type == 1) {
				result = `${percent}`;
			}

			choice.removeEventListener('click', this.submitVote);
			choice.style.setProperty('--choicePercentage', `${percent}`);
			choice.style.setProperty('--choiceResult', `"${result}"`);
		}
	}

	showPoll(els) {
		els.parent.classList.remove('view');
		for (let i = 0; i < els.choices.length; i++) {
			const choice = els.choices[i];
			choice.addEventListener('click', this.submitVote);
			choice.classList.remove('choosed');
			choice.style.removeProperty('--choicePercentage');
			choice.style.removeProperty('--choiceResult');
		}
	}
}

var modal = new onyxPoll();
modal.prepareModal();

