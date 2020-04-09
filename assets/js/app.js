// import './promisse-polyfill.min-min'

class onyxPoll {
	constructor() {
		// empty
	}

	requestModal() {
		return new Promise((resolve, reject) => {
			var xhr = new XMLHttpRequest();
			xhr.open('GET', onyxpoll.apiurl + 'onyx/polls/list/?modal=1');
			xhr.send(null);

			xhr.onload = function() {
				if (this.status >= 200) {
					resolve(JSON.parse(this.response)[0]);
				} else {
					reject('Poll cannot be loaded.');
				}
			}
		})
	}

	renderModal() {
		var modal = document.querySelector('#onyx-poll-modal');
		if (modal != null) {
			var poll = modal.dataset.poll;
			this.requestModal()
				.then(data => {
					var answers = Object.entries(data.answers);
					answers.forEach(([key, data]) => {
						console.log(key, data.answer);
					});
				})
				.catch(error => console.warn(error));
		}
	}

}

var modal = new onyxPoll();
modal.renderModal();