/* bling.js */

window._$  = document.querySelectorAll.bind(document);
window._$$ = document.querySelector.bind(document);

Node.prototype.on = window.on = function (name, fn) {
	this.addEventListener(name, fn);
}

NodeList.prototype.__proto__ = Array.prototype;

NodeList.prototype.on = NodeList.prototype.addEventListener = function (name, fn) {
	this.forEach(function (elem, i) {
		elem.on(name, fn);
	});
}