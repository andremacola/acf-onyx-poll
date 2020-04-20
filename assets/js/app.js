class onyxAcfPoll {
	constructor() {
		self = this; // bad pratice? whatever ¯\_(ツ)_/¯
		this.response = null;
		this.prefix = 'onyx-poll';
		this.element = {
			modal: document.getElementById('onyx-poll-modal'),
		};
		this.name = {
			parent: this.prefix,
			modal: `${this.prefix}-modal`,
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
		};

		this.submitVote = this.submitVote.bind(this);
	}

	prepareModal() {
		if (typeof onyxPollModal !== 'undefined') {
			this.requestModal()
				.then((data) => this.renderModalTemplate(data, this.element.modal))
				.catch((error) => console.warn(error));
		}
	}

	requestModal() {
		return new Promise((resolve, reject) => {
			const xhr = new XMLHttpRequest();
			xhr.open('GET', `${onyxpoll.apiurl}onyx/polls/list/?modal=1`);
			xhr.send(null);
			xhr.onload = function() {
				if (this.status >= 200 && this.status < 400) {
					self.response = JSON.parse(this.response);
					resolve(self.response);
				} else {
					reject('Poll cannot be loaded.');
				}
			};
		});
	}

	requestVote(choice) {
		const voteOptions = {
			choice: choice.getAttribute('data-choice'),
			poll: choice.getAttribute('data-poll'),
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

	renderModalTemplate(data, modal) {
		// poll template
		modal.innerHTML = `
			<div id='${this.name.wrapper}-${data.id}' class="${this.name.wrapper}">
				<p class='${this.name.question}'>${data.title}</p>
				<ul class='${this.name.list}'></ul>
				<div class="${this.name.footer}">
					<p class='${this.name.message}'></p>
					${data.results ? `
						<p class='${this.name.total}'>
							<strong>${onyxpoll.labels.total}: </strong>
							<span></span>
						</p>
					` : ``}
				</div>
			</div>
			<span class="${this.name.loader}"><span class="spinner"></span></span>
			<span class="${this.name.closeButton}"></span>
		`;

		// add view results option
		this.element.footer = document.querySelector(`.${this.name.footer}`);
		if (typeof data.results != 'undefined') {
			this.element.footer.innerHTML += `
				<a href="#" class="onyx-poll-ft-btn ${this.name.voteButton}">${onyxpoll.labels.vote}</a>
				<a href="#" class="onyx-poll-ft-btn ${this.name.viewButton}">${onyxpoll.labels.view}</a>`;
		}

		// add poll choices
		this.element.list = document.querySelector(`.${this.name.list}`);
		for (const choice of data.answers) {
			const choiceEl = document.createElement('li');
			choiceEl.setAttribute('data-choice', choice.option);
			choiceEl.setAttribute('data-poll', data.id);
			choiceEl.className = this.name.choice;
			choiceEl.textContent = choice.answer;
			this.element.list.appendChild(choiceEl);
		}

		// show modal
		this.element.modal.classList.add('show');

		// add event handlers
		this.eventHandlers();
	}

	eventHandlers() {
		this.element.parent = document.querySelector(`.${this.name.parent}`);
		this.element.choices = document.querySelectorAll(`.${this.name.choice}`);
		this.element.message = document.querySelector(`.${this.name.message}`);
		this.element.total = document.querySelector(`.${this.name.total} span`);
		this.element.voteButton = document.querySelector(`.${this.name.voteButton}`);
		this.element.viewButton = document.querySelector(`.${this.name.viewButton}`);
		this.element.closeButton = document.querySelector(`.${this.name.closeButton}`);
		this.element.loader = document.querySelector(`.${this.name.loader}`);

		// close modal poll
		this.element.closeButton.onclick = () => this.element.modal.remove();

		// view poll results button and vote poll button (back from results)
		if (typeof this.response.results != 'undefined') {
			this.element.viewButton.onclick = () => this.showResults();
			this.element.voteButton.onclick = () => this.showPoll();
		}

		// vote events on poll choices
		for (let i = 0; i < this.element.choices.length; i++) {
			this.element.choices[i].addEventListener('click', this.submitVote);
		}
	}

	submitVote(event) {
		const parent = event.target.parentNode.parentNode.parentNode;
		this.togglePollLoader(parent, false);
		this.requestVote(event.target)
			.then(function(response) {
				this.togglePollLoader(parent, true);
				this.handleMessage(response.message, response.code);
				parent.classList.add('voted');

				if (response.code == 'success') {
					event.target.classList.add('choosed');
				}

				// remove list options if no results option is marked;
				if (typeof response.results != 'undefined') {
					this.element.viewButton.click();
				} else {
					this.element.list.remove();
				}
			}.bind(this))
			.catch(function() {
				this.togglePollLoader(parent, true);
				this.handleMessage(onyxpoll.labels.error, 'error');
			}.bind(this));
	}

	showResults() {
		const res = this.response;
		this.element.parent.classList.add('view');
		this.element.total.textContent = res.results.total;

		const choosedChoice = this.getCookie(`onyx_poll_cookie_${res.id}`);
		if (choosedChoice) {
			document.querySelector(`li[data-choice="${choosedChoice}"]`).classList.add('choosed');
		}

		for (let i = 0; i < this.element.choices.length; i++) {
			const choice = this.element.choices[i];
			const votes = res.answers[i].votes;
			const percent = res.answers[i].percent.toFixed(2) + '%';
			let result = `${votes} ${onyxpoll.labels.votes} / ${percent}`;

			if (this.response.results.type == 2) {
				result = `${votes} ${onyxpoll.labels.votes}`;
			} else if (this.response.results.type == 1) {
				result = `${percent}`;
			}

			choice.removeEventListener('click', this.submitVote);
			choice.style.setProperty('--choicePercentage', `${percent}`);
			choice.style.setProperty('--choiceResult', `"${result}"`);
		}
	}

	showPoll() {
		this.element.parent.classList.remove('view');
		for (let i = 0; i < this.element.choices.length; i++) {
			const choice = this.element.choices[i];
			choice.addEventListener('click', this.submitVote);
			choice.classList.remove('choosed');
			choice.style.removeProperty('--choicePercentage');
			choice.style.removeProperty('--choiceResult');
		}
	}

	handleMessage(message, code) {
		const e = this.element.message;
		e.classList.remove('error', 'success', 'warn', 'not_allowed');
		e.classList.add(code);
		e.textContent = message;
	}

	togglePollLoader(element, active = true) {
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

	getCookie(cname) {
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
}

const onyxPoll = new onyxAcfPoll();
onyxPoll.prepareModal();
