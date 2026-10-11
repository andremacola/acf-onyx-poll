(function (wp) {
	'use strict';
	const e = wp.element.createElement;
	const { useState, useEffect } = wp.element;
	const { __ } = wp.i18n;
	const { ComboboxControl, SelectControl, Placeholder, Spinner, Notice, PanelBody } = wp.components;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const text = value => window.onyxPollBlockLabels[value] || __(value, 'acf-onyx-poll');

	function Preview({ id, style }) {
		const [poll, setPoll] = useState(null);
		const [error, setError] = useState('');
		useEffect(() => {
			let active = true;
			setPoll(null);
			setError('');
			if (id) {
				wp.apiFetch({ path: '/onyx/polls/list?id=' + id }).then(data => {
					if (active) { if (data.code === 'error') { setError(data.message); } else { setPoll(data); } }
				}).catch(err => { if (active) { setError(err.message); } });
			}
			return () => { active = false; };
		}, [id]);
		if (!id) { return e(Placeholder, { icon: 'chart-bar', label: text('Onyx Poll') }, text('Select a poll to preview it.')); }
		if (error) { return e(Notice, { status: 'warning', isDismissible: false }, error); }
		if (!poll) { return e(Spinner); }
		return e('div', { className: 'onyx-poll onyx-poll-widget active show onyx-poll-editor-preview ' + style + (poll.expired ? ' view' : '') },
			e('div', { className: 'onyx-poll-wrapper' },
				e('p', { className: 'onyx-poll-question' }, poll.title),
				e('ul', { className: 'onyx-poll-choices' }, poll.answers.map(answer => e('li', {
					key: answer.option, className: 'onyx-poll-choice',
					style: poll.expired ? { '--choicePercentage': answer.percent + '%', '--choiceResult': '"' + (poll.results.type === 2 ? answer.votes + ' ' + text('votes') : poll.results.type === 1 ? answer.percent.toFixed(2) + '%' : answer.votes + ' / ' + answer.percent.toFixed(2) + '%') + '"' } : {}
				}, poll.has_image && e('span', { className: 'image' }, e('img', { src: answer.image, alt: answer.answer })), e('span', { className: 'answer' }, answer.answer)))),
				e('div', { className: 'onyx-poll-footer' }, poll.results && e('p', { className: 'onyx-poll-total' }, text('Total votes') + ': ' + poll.results.total))));
	}

	wp.blocks.registerBlockType('acf/acf-onyx-poll', {
		apiVersion: 3, title: text('Onyx Poll'), description: text('Insert a poll in the post'),
		icon: 'chart-bar', category: 'widgets', keywords: ['poll', 'enquete', 'onyx'],
		attributes: { pollId: { type: 'integer', default: 0 }, pollStyle: { type: 'string', default: '' },
			data: { type: 'object', default: {} }, id: { type: 'string' }, name: { type: 'string' }, mode: { type: 'string' } },
		supports: { align: true, anchor: true, html: false },
		edit: function ({ attributes, setAttributes }) {
			const data = attributes.data || {};
			const id = attributes.pollId || Number(data.onyx_poll_block_id || data.field_5ed174c6b5a8f || 0);
			const style = attributes.pollStyle || data.onyx_poll_block_style || data.field_5ed17b7ef8646 || 'standard';
			const [options, setOptions] = useState([]);
			const [search, setSearch] = useState('');
			useEffect(() => {
				let active = true;
				const timer = setTimeout(() => {
					wp.apiFetch({ path: '/onyx/polls/editor?selected=' + id + '&search=' + encodeURIComponent(search) }).then(polls => {
						if (active) { setOptions(polls.map(poll => ({ value: String(poll.id), label: '[' + poll.id + '] / ' + poll.title }))); }
					}).catch(() => { if (active) { setOptions([]); } });
				}, 250);
				return () => { active = false; clearTimeout(timer); };
			}, [search, id]);
			const controls = e('div', { className: 'onyx-poll-block-controls' },
				e(ComboboxControl, { label: text('Poll'), value: id ? String(id) : null, options, __next40pxDefaultSize: true, __nextHasNoMarginBottom: true,
					onFilterValueChange: setSearch, onChange: value => setAttributes({ pollId: Number(value) || 0, data: { ...data, onyx_poll_block_id: Number(value) || 0 } }) }),
				e(SelectControl, { label: text('Style'), value: style, __next40pxDefaultSize: true, __nextHasNoMarginBottom: true, options: [{ value: 'standard', label: text('Bar style') }, { value: 'twitter', label: text('Twitter') }],
					onChange: value => setAttributes({ pollStyle: value, data: { ...data, onyx_poll_block_style: value } }) }));
			return e('div', useBlockProps({ className: 'onyx-poll-block' }),
				e(InspectorControls, {}, e(PanelBody, { title: text('Settings'), initialOpen: true }, controls)),
				!id && e('div', { className: 'onyx-poll-block-empty-controls' }, controls), e(Preview, { id, style }));
		},
		save: () => null
	});
})(window.wp);
