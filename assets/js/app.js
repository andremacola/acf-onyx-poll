// import './promisse-polyfill.min-min'

class onyxPoll {
	// constructor() {
	// 	// empty
	// }

	requestModal() {
		return new Promise((resolve, reject) => {
			const xhr = new XMLHttpRequest();
			xhr.open('GET', `${onyxpoll.apiurl}onyx/polls/list/?modal=1`);
			xhr.send(null);

			xhr.onload = function() {
				if (this.status >= 200) {
					resolve(JSON.parse(this.response)[0]);
				} else {
					reject('Poll cannot be loaded.');
				}
			};
		});
	}

	prepareModal() {
		const modalEl = document.querySelector('#onyx-poll-modal');
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
				<p class='onyx-poll-message'></p>
				<p class='onyx-poll-results'></p>
			</div>
			<span class="poll-loader"></span>
			<span class="poll-close"></span>
		`;

		// add view results option
		const pollWrapper = document.querySelector(`#onyx-poll-wrapper-${data.id}`);
		if (data.results.total) {
			pollWrapper.innerHTML += `<p class="onyx-poll-view">${onyxpoll.labels.view}</p>`;
		}

		// add poll choices
		const pollChoices = document.querySelector(`#onyx-poll-choices-${data.id}`);
		for (const choice of data.answers) {
			const choiceEl = document.createElement('li');
			choiceEl.setAttribute('data-choice', choice.option);
			choiceEl.setAttribute('data-poll', data.id);
			choiceEl.textContent = choice.answer;
			pollChoices.appendChild(choiceEl);
		}
	}
}

var modal = new onyxPoll();
modal.prepareModal();
