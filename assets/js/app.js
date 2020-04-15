// import './promisse-polyfill.min-min'

class onyxPoll {
	constructor() {
		this.modalEl = 'onyx-poll-modal';
		this.loader = 'onyx-poll-loader';
		this.closeButton = 'onyx-poll-close';
		this.viewButton = 'onyx-poll-view';
		this.voteButton = 'onyx-poll-vote';
		this.choicesEls = 'onyx-poll-choice';
		this.messageEl = 'onyx-poll-message';
	}

	eventHandlers() {
		const poll = {
			modal: document.getElementById(this.modalEl),
			loader: document.getElementById(this.loader),
			closeButton: document.getElementById(this.closeButton),
			viewButton: document.getElementById(this.viewButton),
			voteButton: document.getElementById(this.voteButton),
			choices: document.getElementsByClassName(this.choicesEls),
		};

		// close modal poll
		poll.closeButton.onclick = () => poll.modal.remove();

		// view poll results
		poll.viewButton.onclick = () => console.log('view results');

		// vote poll
		this.handleVoteEvent(poll.choices);
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
					resolve(JSON.parse(this.response));
				} else {
					reject('Poll cannot be loaded.');
				}
			};
		});
	}

	prepareModal() {
		const modalEl = document.getElementById(this.modalEl);
		if (modalEl != null) {
			this.requestModal()
				.then((data) => this.renderModal(data, modalEl))
				.catch((error) => console.warn(error));
		}
	}

	renderModal(data, modalEl) {
		// poll template
		modalEl.innerHTML = `
			<div id='onyx-poll-wrapper-${data.id}'>
				<p class='onyx-poll-question'>${data.title}</p>
				<ul id="onyx-poll-choices-${data.id}" class='onyx-poll-choices'></ul>

				<div id="onyx-poll-footer-${data.id}" class="onyx-poll-footer">
					<p id="${this.messageEl}" class='${this.messageEl}'></p>
					<p class='onyx-poll-results'></p>
				</div>
			</div>
			<span id="${this.loader}" class="${this.loader}"></span>
			<span id="${this.closeButton}" class="${this.closeButton}"></span>
		`;

		// add view results option
		const pollFooter = document.getElementById(`onyx-poll-footer-${data.id}`);
		if (data.results.total) {
			pollFooter.innerHTML += `<a href="#" id="${this.viewButton}" class="${this.viewButton}">${onyxpoll.labels.view}</a>`;
		}

		// add poll choices
		const pollChoices = document.getElementById(`onyx-poll-choices-${data.id}`);
		for (const choice of data.answers) {
			const choiceEl = document.createElement('li');
			choiceEl.setAttribute('data-choice', choice.option);
			choiceEl.setAttribute('data-poll', data.id);
			choiceEl.className = 'onyx-poll-choice';
			choiceEl.textContent = choice.answer;
			pollChoices.appendChild(choiceEl);
		}

		// add event handlers
		this.eventHandlers();
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

	handleMessage(message, type) {
		const messageEl = document.getElementById(this.messageEl);
		messageEl.classList.remove('error', 'success', 'warn');
		messageEl.classList.add(type);
		messageEl.textContent = message;
	}

	handleVoteEvent(choices) {
		const self = this;
		const messageEl = document.getElementById(this.messageEl);
		for (let i = 0; i < choices.length; i++) {
			choices[i].addEventListener('click', function() {
				const parent = this.parentNode.parentNode.parentNode;
				self.togglePollActivation(parent, false);
				self.requestVote(this)
					.then(function(response) {
						console.log(response);
						messageEl.classList.remove('error');
						messageEl.classList.add('success');
					})
					.catch(function() {
						self.togglePollActivation(parent, true);
						self.handleMessage(onyxpoll.labels.error, 'error');
					});
			});
		}
	}
}

var modal = new onyxPoll();
modal.prepareModal();
