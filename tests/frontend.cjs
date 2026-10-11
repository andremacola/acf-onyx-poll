const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

async function run() {
	const requests = [];
	const fixture = { id: 7, version: 3, vote_token: 'fixture-token', answers: [], results: {} };
	let transport = 'normal';
	class Request {
		constructor() {
			this.headers = {};
		}
		open(method, url) {
			this.method = method;
			this.url = String(url);
		}
		setRequestHeader(name, value) {
			this.headers[name] = value;
		}
		send(body) {
			this.body = body;
			requests.push(this);
			queueMicrotask(() => {
				if (transport === 'timeout') {
					return this.ontimeout();
				}
				this.status = 200;
				this.response = transport === 'invalid-json' ? '<html>Error</html>' : JSON.stringify(this.method === 'GET' ? fixture : { poll: 7, voted: true });
				this.onload();
			});
		}
	}
	const document = { readyState: 'loading', cookie: '', addEventListener() {}, querySelector: () => null, querySelectorAll: () => [] };
	const labels = { error: 'Request failed', refresh: 'Refresh first' };
	const context = vm.createContext({ document, URL, Promise, XMLHttpRequest: Request, console: { ...console, warn() {} },
		onyxpoll: { listurl: 'https://example.test/?rest_route=/onyx/polls/list', voteurl: 'https://example.test/?rest_route=/onyx/polls/vote', labels } });
	const source = fs.readFileSync(path.join(__dirname, '../assets/js/app.js'), 'utf8');
	const Poll = vm.runInContext(source + '\nonyxAcfPoll;', context);
	const app = new Poll();
	let checks = 0;
	const check = (condition, message) => {
		assert.ok(condition, message);
		checks++;
	};
	document.cookie = 'other=%broken; onyx_poll_choice_7=%31';
	check(app.getCookie('onyx_poll_choice_7') === '1', 'An unrelated malformed cookie does not break the poll.');
	document.cookie = 'onyx_poll_choice_7=%broken';
	check(app.getCookie('onyx_poll_choice_7') === '', 'Malformed poll cookies fail safely.');
	await app.requestPoll('7');
	const url = new URL(requests[0].url);
	check(url.searchParams.get('rest_route') === '/onyx/polls/list' && url.searchParams.get('id') === '7', 'Plain WordPress permalink URLs preserve route and poll ID.');
	check(url.searchParams.has('_onyx'), 'Session token reads bypass previously cached URLs.');
	const choice = { getAttribute: (name) => ({ 'data-poll': '7', 'data-choice': '2', 'data-version': '3' })[name] };
	requests.length = 0;
	const vote = await app.requestVote(choice);
	check(vote.voted === true && requests.length === 2, 'A fresh challenge is fetched before submitting the vote.');
	check(requests[1].method === 'POST' && requests[1].headers['X-Onyx-Poll-Token'] === fixture.vote_token, 'Votes use POST and the session-bound token header.');
	check(JSON.parse(requests[1].body).choice === '2', 'The chosen answer is preserved.');
	fixture.version = 4;
	requests.length = 0;
	await assert.rejects(app.requestVote(choice), (error) => error.message === labels.refresh);
	check(requests.length === 1 && requests[0].method === 'GET', 'A changed poll cannot receive a vote based on stale answer order.');
	fixture.version = 3;
	transport = 'invalid-json';
	await assert.rejects(app.requestPoll('7'), (error) => error.message === labels.error);
	checks++;
	transport = 'timeout';
	await assert.rejects(app.requestPoll('7'), (error) => error.message === labels.error);
	checks++;
	transport = 'normal';
	let resolveVote;
	let submitted = 0;
	app.requestVote = () => {
		submitted++;
		return new Promise((resolve) => {
			resolveVote = resolve;
		});
	};
	app.togglePollLoader = () => {};
	app.handleMessage = () => {};
	const activeClasses = new Set();
	const classes = { add: (name) => activeClasses.add(name), remove: (name) => activeClasses.delete(name) };
	const poll = { id: 'fixture', classList: classes, querySelector: () => ({ click() {} }) };
	const target = { classList: classes, closest: () => poll };
	const event = { preventDefault() {}, currentTarget: target };
	app.submitVote(event);
	app.submitVote(event);
	check(submitted === 1 && app.pendingVote, 'Repeated clicks submit only one pending vote.');
	resolveVote({ code: 'success', results: {}, message: 'Saved' });
	await new Promise((resolve) => setImmediate(resolve));
	check(! app.pendingVote, 'The pending flag clears when the request completes.');
	activeClasses.clear();
	app.requestVote = () => Promise.reject({ message: labels.error });
	app.submitVote(event);
	await new Promise((resolve) => setImmediate(resolve));
	check(! app.pendingVote && ! activeClasses.has('voted'), 'A failed request permits a retry and does not mark an uncounted vote as submitted.');
	console.log(`${checks} frontend security checks passed.`);
}

run().catch((error) => {
	console.error(error);
	process.exitCode = 1;
});
